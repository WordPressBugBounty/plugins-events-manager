<?php
namespace EM\Migrate\Sources\CSV;

/**
 * A CSV file kept in the uploads folder for the length of one import. It is stored as UTF-8 without a byte order mark and read from a byte offset, so a run can stop and resume without loading the whole file.
 */
class File {

	const DIR = 'em-import';
	const DELIMITERS = array( ',', ';', "\t", '|' );

	public static function dir() {
		$uploads = wp_upload_dir( null, false );
		return trailingslashit( $uploads['basedir'] ) . static::DIR;
	}

	/**
	 * Copies a file into the import folder under a name nobody can guess, since the folder sits inside the web root.
	 *
	 * @return string|\WP_Error path of the stored copy
	 */
	public static function store( $path ) {
		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			return new \WP_Error( 'em_csv_unreadable', __( 'The file could not be read.', 'events-manager' ) );
		}
		$contents = file_get_contents( $path );
		if ( false === $contents || '' === trim( $contents ) ) {
			return new \WP_Error( 'em_csv_empty', __( 'The file is empty.', 'events-manager' ) );
		}
		$contents = static::to_utf8( $contents );
		// files saved by classic Mac OS end lines with a bare carriage return, which fgetcsv reads as one long row
		if ( false === strpos( $contents, "\n" ) && false !== strpos( $contents, "\r" ) ) {
			$contents = str_replace( "\r", "\n", $contents );
		}
		$dir = static::dir();
		if ( ! wp_mkdir_p( $dir ) ) {
			return new \WP_Error( 'em_csv_dir', __( 'The uploads folder is not writable.', 'events-manager' ) );
		}
		static::protect( $dir );
		$stored = $dir . '/' . strtolower( wp_generate_password( 32, false ) ) . '.csv';
		if ( false === file_put_contents( $stored, $contents ) ) {
			return new \WP_Error( 'em_csv_write', __( 'The uploads folder is not writable.', 'events-manager' ) );
		}
		return $stored;
	}

	public static function to_utf8( $contents ) {
		if ( 0 === strncmp( $contents, "\xEF\xBB\xBF", 3 ) ) {
			return substr( $contents, 3 );
		}
		if ( 0 === strncmp( $contents, "\xFF\xFE", 2 ) ) {
			return static::convert( substr( $contents, 2 ), 'UTF-16LE' );
		}
		if ( 0 === strncmp( $contents, "\xFE\xFF", 2 ) ) {
			return static::convert( substr( $contents, 2 ), 'UTF-16BE' );
		}
		$valid = function_exists( 'mb_check_encoding' ) ? mb_check_encoding( $contents, 'UTF-8' ) : (bool) preg_match( '//u', $contents );
		// Excel on Windows saves "CSV" in the system code page unless told otherwise
		return $valid ? $contents : static::convert( $contents, 'Windows-1252' );
	}

	protected static function convert( $contents, $from ) {
		if ( function_exists( 'mb_convert_encoding' ) ) {
			return mb_convert_encoding( $contents, 'UTF-8', $from );
		}
		if ( function_exists( 'iconv' ) ) {
			$converted = iconv( $from, 'UTF-8//IGNORE', $contents );
			return false === $converted ? $contents : $converted;
		}
		return $contents;
	}

	protected static function protect( $dir ) {
		if ( ! file_exists( $dir . '/index.php' ) ) {
			file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" );
		}
		if ( ! file_exists( $dir . '/.htaccess' ) ) {
			file_put_contents( $dir . '/.htaccess', "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n" );
		}
	}

	public static function owns( $path ) {
		return $path && 0 === strpos( wp_normalize_path( $path ), wp_normalize_path( static::dir() ) . '/' );
	}

	public static function delete( $path ) {
		if ( static::owns( $path ) && is_file( $path ) ) {
			unlink( $path );
		}
	}

	/**
	 * Removes stored files older than a day that no import still needs: uploads that were never mapped or started.
	 */
	public static function purge( array $keep = array() ) {
		foreach ( (array) glob( static::dir() . '/*.csv' ) as $file ) {
			if ( ! in_array( $file, $keep, true ) && filemtime( $file ) < time() - DAY_IN_SECONDS ) {
				unlink( $file );
			}
		}
	}

	public static function detect_delimiter( $path ) {
		$handle = static::open( $path );
		if ( ! $handle ) {
			return ',';
		}
		$line = (string) fgets( $handle, 65536 );
		fclose( $handle );
		$best = ',';
		$most = 1;
		foreach ( static::DELIMITERS as $delimiter ) {
			$columns = count( str_getcsv( $line, $delimiter, '"', '' ) );
			if ( $columns > $most ) {
				$most = $columns;
				$best = $delimiter;
			}
		}
		return $best;
	}

	protected static function open( $path ) {
		return is_file( $path ) && is_readable( $path ) ? fopen( $path, 'r' ) : false;
	}

	protected static function read_row( $handle, $delimiter ) {
		return fgetcsv( $handle, 0, $delimiter, '"', '' );
	}

	/**
	 * @return string[] the first row, trimmed
	 */
	public static function headers( $path, $delimiter ) {
		$handle = static::open( $path );
		if ( ! $handle ) {
			return array();
		}
		$row = static::read_row( $handle, $delimiter );
		fclose( $handle );
		return is_array( $row ) ? array_map( function( $cell ) { return trim( (string) $cell ); }, $row ) : array();
	}

	/**
	 * Reads data rows from a byte offset. Rows are keyed by the number a spreadsheet shows for them, where the header is row 1, and blank rows are numbered but left out.
	 *
	 * @param int $offset    0 to start after the header, otherwise the offset a previous call returned.
	 * @param int $after_row Number of the last row already read.
	 * @return array{rows: array<int, string[]>, offset: int, row: int, done: bool}|\WP_Error
	 */
	public static function read( $path, $delimiter, $offset = 0, $after_row = 1, $limit = 50 ) {
		$handle = static::open( $path );
		if ( ! $handle ) {
			return new \WP_Error( 'em_csv_missing', __( 'The uploaded file is no longer on the server. Upload it again.', 'events-manager' ) );
		}
		if ( $offset > 0 ) {
			fseek( $handle, $offset );
		} else {
			static::read_row( $handle, $delimiter );
		}
		$rows = array();
		$number = max( 1, (int) $after_row );
		$done = false;
		while ( count( $rows ) < $limit ) {
			$cells = static::read_row( $handle, $delimiter );
			if ( false === $cells ) {
				$done = true;
				break;
			}
			$number++;
			if ( '' === trim( implode( '', array_map( 'strval', $cells ) ) ) ) {
				continue;
			}
			$rows[ $number ] = array_map( function( $cell ) { return trim( (string) $cell ); }, $cells );
		}
		$position = ftell( $handle );
		fclose( $handle );
		return array( 'rows' => $rows, 'offset' => (int) $position, 'row' => $number, 'done' => $done );
	}

	/**
	 * Calls back with every data row, for whole-file checks such as counting rows or working out the date order.
	 */
	public static function each_row( $path, $delimiter, callable $callback ) {
		$offset = 0;
		$row = 1;
		do {
			$batch = static::read( $path, $delimiter, $offset, $row, 500 );
			if ( is_wp_error( $batch ) ) {
				return;
			}
			foreach ( $batch['rows'] as $number => $cells ) {
				$callback( $cells, $number );
			}
			$offset = $batch['offset'];
			$row = $batch['row'];
		} while ( ! $batch['done'] );
	}

	public static function count_rows( $path, $delimiter ) {
		$count = 0;
		static::each_row( $path, $delimiter, function() use ( &$count ) { $count++; } );
		return $count;
	}
}
