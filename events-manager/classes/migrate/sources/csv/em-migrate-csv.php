<?php
namespace EM\Migrate\Sources\CSV;

use EM\Migrate\Source;
use EM\Migrate\Job;
use EM\Migrate\Map;
use EM\Migrate\Writer;
use EM\Migrate\Sources\TEC\Countries;

require_once __DIR__ . '/em-migrate-csv-file.php';
require_once __DIR__ . '/em-migrate-csv-fields.php';
require_once __DIR__ . '/em-migrate-csv-recurrence.php';

/**
 * A row that cannot be imported. The message is the reason shown in the result report.
 */
class Skip extends \Exception {}

/**
 * One-time import of events, locations or organizers from an uploaded CSV file, one row per item.
 *
 * Each run is an import with its own id. Everything it creates is mapped under that id, so rolling back removes that import alone and leaves earlier ones in place.
 */
class CSV extends Source {

	const KEY = 'csv';
	const PENDING_OPTION = 'dbem_migrate_csv_pending';
	const REPORT_OPTION = 'dbem_migrate_csv_report';
	const HISTORY_OPTION = 'dbem_migrate_csv_imports';
	const REPORT_LIMIT = 500;
	const HISTORY_LIMIT = 20;

	protected $notes = array();
	protected $report_rows = array();

	public function get_key() {
		return static::KEY;
	}

	public function get_name() {
		return __( 'CSV file', 'events-manager' );
	}

	public function is_detected() {
		return false;
	}

	public function is_always_available() {
		return true;
	}

	public function has_admin_setup() {
		return true;
	}

	public function admin_setup( Job $job ) {
		require_once __DIR__ . '/em-migrate-csv-admin.php';
		Admin::setup( $this, $job );
	}

	public static function admin_init() {
		if ( ! empty( $_POST['em_migrate_csv_action'] ) ) {
			require_once __DIR__ . '/em-migrate-csv-admin.php';
			Admin::handle_request();
		}
	}

	public function get_summary() {
		$summary = array(
			__( 'Events imported from CSV files', 'events-manager' ) => Map::count( static::KEY, 'event' ),
			__( 'Locations imported from CSV files', 'events-manager' ) => Map::count( static::KEY, 'location' ),
			__( 'Organizers imported from CSV files', 'events-manager' ) => Map::count( static::KEY, 'organizer' ),
		);
		// each import that still has something to roll back, with the id the command line takes
		foreach ( array_reverse( static::get_history(), true ) as $import => $entry ) {
			$count = Map::count( static::KEY, null, $import . ':' );
			if ( $count ) {
				$summary[ sprintf( __( 'Import %1$s, %2$s', 'events-manager' ), $import, $entry['name'] ?? '' ) ] = $count;
			}
		}
		return $summary;
	}

	public function get_steps() {
		return array(
			'prepare' => __( 'Preparing', 'events-manager' ),
			'rows' => __( 'Importing rows', 'events-manager' ),
			'finish' => __( 'Finishing', 'events-manager' ),
		);
	}

	public function get_options() {
		return array(
			'type' => array( 'label' => __( 'What the file holds', 'events-manager' ), 'type' => 'select', 'default' => 'events', 'choices' => Fields::types() ),
			'status' => array(
				'label' => __( 'Save imported events and locations as', 'events-manager' ),
				'type' => 'select',
				'default' => 'publish',
				'choices' => array( 'publish' => __( 'Published', 'events-manager' ), 'draft' => __( 'Drafts', 'events-manager' ), 'pending' => __( 'Pending review', 'events-manager' ) ),
			),
			'date_order' => array(
				'label' => __( 'Dates written as numbers', 'events-manager' ),
				'type' => 'select',
				'default' => 'auto',
				'choices' => array(
					'auto' => __( 'Work it out from the file', 'events-manager' ),
					'mdy' => __( 'Month first: 03/04/2027 is March 4', 'events-manager' ),
					'dmy' => __( 'Day first: 03/04/2027 is April 3', 'events-manager' ),
				),
				'description' => __( 'Dates such as 2027-03-04 or March 4, 2027 are always read the same way.', 'events-manager' ),
			),
			'unsupported_recurrence' => array(
				'label' => __( 'Repeat rules Events Manager does not have', 'events-manager' ),
				'type' => 'select',
				'default' => 'dates',
				'choices' => array( 'dates' => __( 'Import as a list of dates', 'events-manager' ), 'skip' => __( 'Skip the row', 'events-manager' ) ),
				'description' => __( 'Events Manager repeats daily, weekly, monthly on a weekday such as the second Tuesday, and yearly, up to an end date. It has no rule for a day of the month or for ending after a number of times. Either way the result report names each row.', 'events-manager' ),
			),
			'skip_duplicates' => array( 'label' => __( 'Skip rows already on this site: events with the same name and start, locations with the same name', 'events-manager' ), 'type' => 'checkbox', 'default' => true ),
			'images' => array( 'label' => __( 'Download featured images given as web addresses', 'events-manager' ), 'type' => 'checkbox', 'default' => true ),
		);
	}

	public function get_rollback_scope( Job $job ) {
		return ( $job->option( 'import' ) ?: 'none' ) . ':';
	}

	// ---- The uploaded file waiting to be mapped ----

	/**
	 * @return array|null file, name, type, delimiter, headers, map, rows
	 */
	public static function get_pending() {
		$pending = get_option( static::PENDING_OPTION );
		if ( ! is_array( $pending ) || empty( $pending['file'] ) ) {
			return null;
		}
		if ( ! is_readable( $pending['file'] ) ) {
			delete_option( static::PENDING_OPTION );
			return null;
		}
		return $pending;
	}

	/**
	 * Stores a copy of the file and works out its delimiter, headers and a first column mapping.
	 *
	 * @return array|\WP_Error
	 */
	public static function create_pending( $path, $name, $type, $delimiter = null ) {
		$type = array_key_exists( $type, Fields::types() ) ? $type : 'events';
		$job = new Job( static::KEY );
		File::purge( array_filter( array( $job->option( 'file' ) ) ) );
		$stored = File::store( $path );
		if ( is_wp_error( $stored ) ) {
			return $stored;
		}
		$delimiter = in_array( $delimiter, File::DELIMITERS, true ) ? $delimiter : File::detect_delimiter( $stored );
		$headers = File::headers( $stored, $delimiter );
		$rows = File::count_rows( $stored, $delimiter );
		if ( count( array_filter( $headers, 'strlen' ) ) < 1 || ! $rows ) {
			File::delete( $stored );
			return new \WP_Error( 'em_csv_no_rows', __( 'The file needs a first row of column names and at least one row below it.', 'events-manager' ) );
		}
		$previous = static::get_pending();
		if ( $previous ) {
			File::delete( $previous['file'] );
		}
		$pending = array(
			'file' => $stored,
			'name' => sanitize_file_name( $name ),
			'type' => $type,
			'delimiter' => $delimiter,
			'headers' => $headers,
			'map' => Fields::auto_map( $type, $headers ),
			'rows' => $rows,
		);
		update_option( static::PENDING_OPTION, $pending, false );
		return $pending;
	}

	public static function discard_pending() {
		$pending = get_option( static::PENDING_OPTION );
		if ( is_array( $pending ) && ! empty( $pending['file'] ) ) {
			File::delete( $pending['file'] );
		}
		delete_option( static::PENDING_OPTION );
	}

	/**
	 * Reads the column mapping out of a request (map_0, map_1 ... from the mapping form) or a command line (--map="Column=field,Column=field"), on top of the automatic one.
	 *
	 * @return array|\WP_Error
	 */
	public static function read_map( array $pending, array $input ) {
		$map = $pending['map'];
		foreach ( array_keys( $pending['headers'] ) as $i ) {
			if ( array_key_exists( 'map_' . $i, $input ) ) {
				$map[ $i ] = sanitize_key( $input[ 'map_' . $i ] );
			}
		}
		if ( ! empty( $input['map'] ) && is_string( $input['map'] ) ) {
			$fields = Fields::get( $pending['type'] );
			$normalized = array_map( array( Fields::class, 'normalize' ), $pending['headers'] );
			foreach ( array_filter( array_map( 'trim', explode( ',', $input['map'] ) ), 'strlen' ) as $pair ) {
				$parts = array_map( 'trim', explode( '=', $pair, 2 ) );
				$index = array_search( Fields::normalize( $parts[0] ), $normalized, true );
				if ( false === $index ) {
					return new \WP_Error( 'em_csv_map', sprintf( __( 'The file has no column named "%s".', 'events-manager' ), $parts[0] ) );
				}
				$field = $parts[1] ?? '';
				if ( '' !== $field && ! isset( $fields[ $field ] ) ) {
					return new \WP_Error( 'em_csv_map', sprintf( __( '"%1$s" is not a field. Fields: %2$s.', 'events-manager' ), $field, implode( ', ', array_keys( $fields ) ) ) );
				}
				// a field goes to one column, so naming it here takes it from the column that matched automatically
				foreach ( $map as $i => $existing ) {
					if ( '' !== $field && $existing === $field ) {
						$map[ $i ] = '';
					}
				}
				$map[ $index ] = $field;
			}
		}
		return Fields::sanitize_map( $pending['type'], $map, count( $pending['headers'] ) );
	}

	public function prepare_options( array $options, array $input ) {
		$cli = defined( 'WP_CLI' ) && WP_CLI;
		$prepared = $this->read_run( $options, $input, $cli );
		if ( $cli && is_wp_error( $prepared ) ) {
			// the command line stored its own copy of the file, which nothing will come back for
			static::discard_pending();
		}
		return $prepared;
	}

	/**
	 * @return array|\WP_Error
	 */
	protected function read_run( array $options, array $input, $cli ) {
		// a path is only taken from the command line; a web request can only run the file it uploaded
		if ( $cli ) {
			if ( empty( $input['file'] ) ) {
				return new \WP_Error( 'em_csv_no_file', __( 'Give the file to import with --file=<path>.', 'events-manager' ) );
			}
			$delimiter = isset( $input['delimiter'] ) ? (string) $input['delimiter'] : null;
			$pending = static::create_pending( (string) $input['file'], basename( (string) $input['file'] ), $options['type'], in_array( $delimiter, array( 'tab', '\t' ), true ) ? "\t" : $delimiter );
			if ( is_wp_error( $pending ) ) {
				return $pending;
			}
		} else {
			$pending = static::get_pending();
		}
		if ( ! $pending ) {
			return new \WP_Error( 'em_csv_no_file', __( 'Upload a file first.', 'events-manager' ) );
		}
		$map = static::read_map( $pending, $input );
		if ( is_wp_error( $map ) ) {
			return $map;
		}
		$missing = Fields::missing_required( $pending['type'], $map );
		if ( $missing ) {
			return new \WP_Error( 'em_csv_required', sprintf( __( 'No column is matched to: %s.', 'events-manager' ), implode( ', ', $missing ) ) );
		}
		// Events Manager refuses a save without these and gives no reason, which would skip every row
		$needs = array( 'events' => array( 'publish_events' ), 'locations' => array( 'publish_locations' ), 'organizers' => array() )[ $pending['type'] ];
		if ( 'events' === $pending['type'] && in_array( 'location', $map, true ) ) {
			$needs[] = 'publish_locations';
		}
		foreach ( $needs as $capability ) {
			if ( ! current_user_can( $capability ) ) {
				return new \WP_Error( 'em_csv_capability', __( 'Your user account cannot publish events or locations in Events Manager. Run the import as an administrator.', 'events-manager' ) );
			}
		}
		return array_merge( $options, array(
			'import' => 'i' . substr( md5( uniqid( '', true ) . wp_rand() ), 0, 9 ),
			'type' => $pending['type'],
			'file' => $pending['file'],
			'name' => $pending['name'],
			'delimiter' => $pending['delimiter'],
			'headers' => $pending['headers'],
			'map' => $map,
			'rows' => $pending['rows'],
		) );
	}

	// ---- Running ----

	public function run_step( $step, $cursor, $limit, Job $job ) {
		switch ( $step ) {
			case 'prepare':
				return $this->step_prepare( $job );
			case 'rows':
				return $this->step_rows( $cursor, $limit, $job );
			case 'finish':
				return $this->step_finish( $job );
		}
		return array( 'done' => true, 'cursor' => null );
	}

	protected function mapped( Job $job, $field ) {
		return in_array( $field, (array) $job->option( 'map', array() ), true );
	}

	protected function step_prepare( Job $job ) {
		$file = $job->option( 'file' );
		if ( ! $file || ! is_readable( $file ) ) {
			throw new \Exception( __( 'The uploaded file is no longer on the server. Upload it again.', 'events-manager' ) );
		}
		$import = $job->option( 'import' );
		$type = $job->option( 'type' );
		$map = (array) $job->option( 'map' );
		delete_option( static::PENDING_OPTION );
		update_option( static::REPORT_OPTION, array( 'import' => $import, 'rows' => array(), 'more' => 0 ), false );
		static::remember( $import, array( 'name' => $job->option( 'name' ), 'type' => $type, 'started' => $job->started, 'status' => 'running', 'imported' => 0, 'skipped' => 0 ) );

		$scan = $this->scan( $file, $job->option( 'delimiter' ), $map );
		$uses = $scan['uses'];
		$order = $job->option( 'date_order', 'auto' );
		$job->options['date_order_used'] = 'auto' === $order ? $scan['date_order'] : $order;
		if ( 'auto' === $order && $job->options['date_order_used'] ) {
			$job->log( 'dmy' === $job->options['date_order_used'] ? 'Dates in this file are written day first.' : 'Dates in this file are written month first.' );
		}

		$wanted = array();
		if ( 'organizers' === $type || $this->mapped( $job, 'organizers' ) ) {
			$wanted['dbem_organizers_enabled'] = 1;
		}
		if ( 'locations' === $type ) {
			$wanted['dbem_locations_enabled'] = 1;
			if ( $uses['attributes'] ) {
				$wanted['dbem_location_attributes_enabled'] = 1;
			}
		}
		if ( 'events' === $type ) {
			foreach ( array( 'categories' => 'dbem_categories_enabled', 'tags' => 'dbem_tags_enabled', 'location' => 'dbem_locations_enabled' ) as $field => $option ) {
				if ( $this->mapped( $job, $field ) ) {
					$wanted[ $option ] = 1;
				}
			}
			if ( $uses['attributes'] ) {
				$wanted['dbem_attributes_enabled'] = 1;
			}
			if ( $uses['recurrence'] ) {
				$wanted['dbem_recurrence_enabled'] = 1;
			}
			$location_types = (array) get_option( 'dbem_location_types', array() );
			if ( $uses['url'] && empty( $location_types['url'] ) ) {
				$wanted['dbem_location_types'] = array_merge( $location_types, array( 'url' => 1 ) );
			}
		}
		foreach ( $wanted as $option => $value ) {
			$previous = get_option( $option, null );
			$same = is_scalar( $value ) ? ( is_scalar( $previous ) && (string) $previous === (string) $value ) : maybe_serialize( $previous ) === maybe_serialize( $value );
			if ( $same ) {
				continue;
			}
			// locations are on unless the option says otherwise
			if ( 'dbem_locations_enabled' === $option && null === $previous ) {
				continue;
			}
			Map::set( static::KEY, 'option', $import . ':' . $option, 'option', 0, array( 'option' => $option, 'previous' => $previous ) );
			update_option( $option, $value );
			$job->log( sprintf( 'Set %s to %s.', $option, is_scalar( $value ) ? $value : wp_json_encode( $value ) ) );
		}
		Writer::ensure_taxonomies();
		$job->log( sprintf( 'Importing %1$s from %2$s (%3$d rows).', $type, $job->option( 'name' ), (int) $job->option( 'rows' ) ) );
		$left_out = array_values( array_filter( array_intersect_key( (array) $job->option( 'headers' ), array_filter( $map, function( $field ) { return '' === $field; } ) ), 'strlen' ) );
		if ( $left_out ) {
			$job->log( 'Columns not imported: ' . implode( ', ', $left_out ) . '.' );
		}
		return array( 'done' => true, 'cursor' => null );
	}

	/**
	 * One pass over the file: which way round its numeric dates are, and whether any row needs a feature that may be switched off.
	 *
	 * @return array{date_order: string, uses: array<string, bool>}
	 */
	public function scan( $file, $delimiter, array $map ) {
		$dates = array();
		$uses = array_fill_keys( array( 'recurrence', 'url', 'attributes' ), false );
		$columns = array_flip( array_filter( $map, 'strlen' ) );
		File::each_row( $file, $delimiter, function( $cells ) use ( &$dates, &$uses, $columns ) {
			$cell = function( $field ) use ( $cells, $columns ) {
				return isset( $columns[ $field ], $cells[ $columns[ $field ] ] ) ? $cells[ $columns[ $field ] ] : '';
			};
			foreach ( array( 'start_date', 'end_date', 'recurrence_end' ) as $field ) {
				if ( '' !== $cell( $field ) ) {
					$dates[] = $cell( $field );
				}
			}
			$uses['recurrence'] = $uses['recurrence'] || '' !== $cell( 'recurrence_freq' ) . $cell( 'recurrence_dates' );
			$uses['url'] = $uses['url'] || ( '' !== $cell( 'website' ) && '' === $cell( 'location' ) );
			$uses['attributes'] = $uses['attributes'] || '' !== $cell( 'cost' ) . $cell( 'website' ) . $cell( 'featured' ) . $cell( 'hide' ) . $cell( 'phone' );
		} );
		return array( 'date_order' => Values::detect_date_order( $dates ), 'uses' => $uses );
	}

	/**
	 * What the first rows would become with a mapping, without saving anything.
	 *
	 * @return array[] each: row (number), cells (label => text), problem (why it would be skipped, or empty), notes
	 */
	public function preview( array $pending, array $map, array $options, $limit = 5 ) {
		$job = new Job( static::KEY . '_preview' );
		$order = isset( $options['date_order'] ) ? $options['date_order'] : 'auto';
		$job->options = array_merge( $options, array( 'type' => $pending['type'], 'map' => $map, 'date_order_used' => 'auto' === $order ? $this->scan( $pending['file'], $pending['delimiter'], $map )['date_order'] : $order ) );
		$batch = File::read( $pending['file'], $pending['delimiter'], 0, 1, $limit );
		$preview = array();
		if ( is_wp_error( $batch ) ) {
			return $preview;
		}
		foreach ( $batch['rows'] as $number => $cells ) {
			$row = $this->map_cells( $cells, $map );
			$get = function( $field ) use ( $row ) {
				return isset( $row[ $field ] ) ? $row[ $field ] : '';
			};
			$entry = array( 'row' => $number, 'cells' => array( __( 'Name', 'events-manager' ) => $get( 'name' ) ), 'problem' => '', 'notes' => array() );
			try {
				if ( '' === $get( 'name' ) ) {
					throw new Skip( __( 'The name is empty.', 'events-manager' ) );
				}
				if ( 'locations' === $pending['type'] ) {
					$entry['cells'][ __( 'Address', 'events-manager' ) ] = implode( ', ', array_filter( array( $get( 'address' ), $get( 'address2' ), $get( 'town' ), $get( 'state' ), $get( 'postcode' ) ), 'strlen' ) );
					$entry['cells'][ __( 'Country', 'events-manager' ) ] = Countries::to_code( $get( 'country' ) );
					if ( '' !== $get( 'country' ) && ! Countries::to_code( $get( 'country' ) ) ) {
						$entry['notes'][] = sprintf( __( 'The country "%s" is not recognized and was left empty.', 'events-manager' ), $get( 'country' ) );
					}
					if ( $this->find_location( $get( 'name' ) ) && ! empty( $options['skip_duplicates'] ) ) {
						throw new Skip( __( 'A location with this name is already on this site.', 'events-manager' ) );
					}
				} elseif ( 'organizers' === $pending['type'] ) {
					$entry['cells'][ __( 'Email', 'events-manager' ) ] = $get( 'email' );
					$entry['cells'][ __( 'Website', 'events-manager' ) ] = $get( 'website' );
					if ( $this->find_term( $get( 'name' ), \EM\Organizers::TAXONOMY ) ) {
						throw new Skip( __( 'An organizer with this name is already on this site.', 'events-manager' ) );
					}
				} else {
					$d = $this->read_event( $row, $job );
					$when = $d['start_date'] . ( $d['all_day'] ? '' : ' ' . substr( $d['start_time'], 0, 5 ) );
					if ( empty( $d['recurrence'] ) ) {
						$when .= $d['all_day'] ? ( $d['end_date'] !== $d['start_date'] ? ' - ' . $d['end_date'] : '' ) : ' - ' . ( $d['end_date'] !== $d['start_date'] ? $d['end_date'] . ' ' : '' ) . substr( $d['end_time'], 0, 5 );
					}
					$entry['cells'][ __( 'When', 'events-manager' ) ] = $when . ( $d['all_day'] ? ', ' . __( 'all day', 'events-manager' ) : '' ) . ' (' . $d['timezone'] . ')';
					$entry['cells'][ __( 'Repeats', 'events-manager' ) ] = empty( $d['recurrence'] ) ? '' : $this->describe_recurrence( $d['recurrence']['sets'][0] );
					$entry['cells'][ __( 'Location', 'events-manager' ) ] = $d['location_name'];
					$entry['cells'][ __( 'Organizers', 'events-manager' ) ] = implode( ', ', $d['organizer_names'] );
					$entry['cells'][ __( 'Categories', 'events-manager' ) ] = implode( ', ', $d['category_names'] );
					$entry['notes'] = $d['notes'];
				}
			} catch ( Skip $e ) {
				$entry['problem'] = $e->getMessage();
			}
			$preview[] = $entry;
		}
		return $preview;
	}

	protected function describe_recurrence( array $set ) {
		if ( 'on' === $set['freq'] ) {
			return sprintf( _n( '%d date', '%d dates', count( $set['dates'] ), 'events-manager' ), count( $set['dates'] ) );
		}
		$labels = array( 'daily' => __( 'Daily', 'events-manager' ), 'weekly' => __( 'Weekly', 'events-manager' ), 'monthly' => __( 'Monthly', 'events-manager' ), 'yearly' => __( 'Yearly', 'events-manager' ) );
		$text = $labels[ $set['freq'] ];
		if ( $set['interval'] > 1 ) {
			$text .= ', ' . sprintf( __( 'every %d', 'events-manager' ), $set['interval'] );
		}
		if ( isset( $set['byday'] ) && '' !== $set['byday'] ) {
			global $wp_locale;
			$days = array_map( function( $day ) use ( $wp_locale ) { return $wp_locale->get_weekday_abbrev( $wp_locale->get_weekday( (int) $day ) ); }, explode( ',', $set['byday'] ) );
			$weeks = array( 1 => __( 'first', 'events-manager' ), 2 => __( 'second', 'events-manager' ), 3 => __( 'third', 'events-manager' ), 4 => __( 'fourth', 'events-manager' ), 5 => __( 'fifth', 'events-manager' ), -1 => __( 'last', 'events-manager' ) );
			$text .= ', ' . ( isset( $set['byweekno'], $weeks[ $set['byweekno'] ] ) ? $weeks[ $set['byweekno'] ] . ' ' : '' ) . implode( ' ', $days );
		}
		return $text . ', ' . sprintf( __( 'until %s', 'events-manager' ), $set['end_date'] );
	}

	protected function step_rows( $cursor, $limit, Job $job ) {
		$cursor = is_array( $cursor ) ? $cursor : array( 'offset' => 0, 'row' => 1 );
		$type = $job->option( 'type' );
		if ( 'events' !== $type ) {
			$limit *= 5;
		} elseif ( $this->mapped( $job, 'image' ) && $job->option( 'images', true ) ) {
			// a batch must fit in one request, and each row may download a file
			$limit = max( 2, (int) floor( $limit / 3 ) );
		}
		$batch = File::read( $job->option( 'file' ), $job->option( 'delimiter' ), $cursor['offset'], $cursor['row'], $limit );
		if ( is_wp_error( $batch ) ) {
			throw new \Exception( $batch->get_error_message() );
		}
		Writer::ensure_taxonomies();
		$object_type = array( 'events' => 'event', 'locations' => 'location', 'organizers' => 'organizer' )[ $type ];
		foreach ( $batch['rows'] as $number => $cells ) {
			$row = $this->map_cells( $cells, (array) $job->option( 'map' ) );
			$name = isset( $row['name'] ) ? $row['name'] : '';
			$source_id = $job->option( 'import' ) . ':' . $number;
			// a batch that was cut short is read again from its start, and the rows it had finished are already mapped
			if ( Map::get( static::KEY, $object_type, $source_id ) ) {
				$job->count( $type, 'created' );
				continue;
			}
			$this->notes = array();
			try {
				if ( '' === $name ) {
					throw new Skip( __( 'The name is empty.', 'events-manager' ) );
				}
				switch ( $type ) {
					case 'locations':
						$this->import_location( $row, $source_id, $number, $job );
						break;
					case 'organizers':
						$this->import_organizer( $row, $source_id, $number, $job );
						break;
					default:
						$this->import_event( $row, $source_id, $number, $job );
				}
				$job->count( $type, 'created' );
				foreach ( $this->notes as $note ) {
					$this->report_rows[] = array( 'row' => $number, 'name' => $name, 'result' => 'note', 'message' => $note );
					$job->log( sprintf( 'Row %1$d "%2$s": %3$s', $number, $name, $note ) );
				}
			} catch ( Skip $e ) {
				$job->count( $type, 'skipped' );
				$this->report_rows[] = array( 'row' => $number, 'name' => $name, 'result' => 'skipped', 'message' => $e->getMessage() );
				$job->log( sprintf( 'Row %1$d "%2$s" skipped: %3$s', $number, $name, $e->getMessage() ), 'error' );
			}
		}
		$this->flush_report( $job );
		return array( 'done' => $batch['done'], 'cursor' => array( 'offset' => $batch['offset'], 'row' => $batch['row'] ) );
	}

	protected function step_finish( Job $job ) {
		wp_defer_term_counting( false );
		File::delete( $job->option( 'file' ) );
		update_option( 'dbem_flush_needed', 1 );
		$type = $job->option( 'type' );
		$imported = (int) ( $job->stats[ $type ]['created'] ?? 0 );
		$skipped = (int) ( $job->stats[ $type ]['skipped'] ?? 0 );
		static::remember( $job->option( 'import' ), array( 'status' => 'done', 'imported' => $imported, 'skipped' => $skipped ) );
		$job->log( sprintf( '%1$d rows imported, %2$d skipped.', $imported, $skipped ) );
		return array( 'done' => true, 'cursor' => null );
	}

	/**
	 * @return array field key => cell, for the mapped columns only
	 */
	public function map_cells( array $cells, array $map ) {
		$row = array();
		foreach ( $map as $index => $field ) {
			if ( '' !== $field ) {
				$row[ $field ] = isset( $cells[ $index ] ) ? trim( (string) $cells[ $index ] ) : '';
			}
		}
		return $row;
	}

	// ---- Rows ----

	/**
	 * Checks an event row and turns it into what Writer::save_event() takes, without saving anything. Used by the import and by the preview.
	 *
	 * @return array save_event data, plus names of the location, organizers, categories and tags still to be looked up
	 * @throws Skip
	 */
	public function read_event( array $row, Job $job ) {
		$get = function( $field ) use ( $row ) {
			return isset( $row[ $field ] ) ? $row[ $field ] : '';
		};
		$order = (string) $job->option( 'date_order_used', '' );
		if ( '' === $get( 'start_date' ) ) {
			throw new Skip( __( 'The start date is empty.', 'events-manager' ) );
		}
		$start = Values::parse_date( $get( 'start_date' ), $order );
		if ( ! $start ) {
			throw new Skip( sprintf( __( 'The start date "%s" is not understood.', 'events-manager' ), $get( 'start_date' ) ) );
		}
		$end = array( 'date' => $start['date'], 'time' => null );
		if ( '' !== $get( 'end_date' ) ) {
			$end = Values::parse_date( $get( 'end_date' ), $order );
			if ( ! $end ) {
				throw new Skip( sprintf( __( 'The end date "%s" is not understood.', 'events-manager' ), $get( 'end_date' ) ) );
			}
		}
		// a time column wins over a time written inside the date cell
		if ( '' !== $get( 'start_time' ) ) {
			$start['time'] = Values::parse_time( $get( 'start_time' ) );
			if ( null === $start['time'] ) {
				throw new Skip( sprintf( __( 'The start time "%s" is not understood.', 'events-manager' ), $get( 'start_time' ) ) );
			}
		}
		if ( '' !== $get( 'end_time' ) ) {
			$end['time'] = Values::parse_time( $get( 'end_time' ) );
			if ( null === $end['time'] ) {
				throw new Skip( sprintf( __( 'The end time "%s" is not understood.', 'events-manager' ), $get( 'end_time' ) ) );
			}
		}
		// a row with a date and no times at all is an all day event, not one that starts and ends at midnight
		$all_day = Values::is_true( $get( 'all_day' ) ) || ( null === $start['time'] && null === $end['time'] );
		if ( $all_day ) {
			$start['time'] = '00:00:00';
			$end['time'] = '23:59:59';
		} else {
			$start['time'] = $start['time'] ?: '00:00:00';
			$end['time'] = $end['time'] ?: $start['time'];
		}
		if ( $end['date'] . ' ' . $end['time'] < $start['date'] . ' ' . $start['time'] ) {
			throw new Skip( __( 'The end is before the start.', 'events-manager' ) );
		}
		$timezone = Values::timezone( $get( 'timezone' ) );
		if ( null === $timezone ) {
			throw new Skip( sprintf( __( 'The time zone "%s" is not recognized. Use a name such as America/New_York or an offset such as UTC+2.', 'events-manager' ), $get( 'timezone' ) ) );
		}
		$timezone = $timezone ?: wp_timezone_string();

		$d = array(
			'name' => $get( 'name' ),
			'content' => $get( 'content' ),
			'excerpt' => $get( 'excerpt' ),
			'status' => $job->option( 'status', 'publish' ),
			'start_date' => $start['date'],
			'end_date' => $end['date'],
			'start_time' => $start['time'],
			'end_time' => $end['time'],
			'all_day' => $all_day,
			'timezone' => $timezone,
			'location_name' => $get( 'location' ),
			'organizer_names' => $this->split_names( $get( 'organizers' ), \EM\Organizers::TAXONOMY ),
			'category_names' => Values::split_list( $get( 'categories' ) ),
			'tag_names' => Values::split_list( $get( 'tags' ) ),
			'image' => $get( 'image' ),
			'attributes' => array(),
			'lines' => array(),
			'notes' => array(),
		);

		$duration = (int) round( ( strtotime( $end['date'] . ' 00:00:00 UTC' ) - strtotime( $start['date'] . ' 00:00:00 UTC' ) ) / DAY_IN_SECONDS );
		$recurrence = Recurrence::from_row( array(
			'freq' => $get( 'recurrence_freq' ),
			'interval' => $get( 'recurrence_interval' ),
			'days' => $get( 'recurrence_days' ),
			'week' => $get( 'recurrence_week' ),
			'end' => $get( 'recurrence_end' ),
			'dates' => $get( 'recurrence_dates' ),
			'month_day' => $get( 'recurrence_month_day' ),
			'count' => $get( 'recurrence_count' ),
		), $start['date'], $order, $job->option( 'unsupported_recurrence', 'dates' ) );
		if ( $recurrence['error'] ) {
			throw new Skip( $recurrence['error'] );
		}
		if ( $recurrence['note'] ) {
			$d['notes'][] = $recurrence['note'];
		}
		$d['start_date'] = $recurrence['start_date'];
		$d['end_date'] = gmdate( 'Y-m-d', strtotime( $recurrence['start_date'] . ' 00:00:00 UTC' ) + $duration * DAY_IN_SECONDS );
		if ( $recurrence['sets'] ) {
			foreach ( $recurrence['sets'] as &$set ) {
				$set += array( 'start_time' => $start['time'], 'end_time' => $end['time'], 'duration' => $duration, 'all_day' => $all_day );
			}
			unset( $set );
			$d['recurrence'] = array( 'sets' => $recurrence['sets'] );
			// a recurring event runs from its first date to the end of its last
			$last = 'on' === $recurrence['sets'][0]['freq'] ? gmdate( 'Y-m-d', strtotime( $recurrence['end_date'] . ' 00:00:00 UTC' ) + $duration * DAY_IN_SECONDS ) : $recurrence['end_date'];
			$d['end_date'] = max( $d['end_date'], $last );
		}

		// fields Events Manager has no column for become attributes, shown through a details block under the description, the same as when migrating from another plugin
		$cost = $get( 'cost' );
		if ( '' !== $cost ) {
			if ( is_numeric( $cost ) && '' !== $get( 'currency_symbol' ) ) {
				$cost = 'suffix' === strtolower( $get( 'currency_position' ) ) ? $cost . $get( 'currency_symbol' ) : $get( 'currency_symbol' ) . $cost;
			} elseif ( is_numeric( $cost ) && '' !== $get( 'currency_code' ) ) {
				$cost .= ' ' . strtoupper( $get( 'currency_code' ) );
			}
			$d['attributes'][ __( 'Cost', 'events-manager' ) ] = $cost;
			$d['lines'][ __( 'Cost', 'events-manager' ) ] = true;
		}
		$website = $get( 'website' );
		if ( '' !== $website ) {
			$url = esc_url_raw( preg_match( '#^[a-z][a-z0-9+.-]*://#i', $website ) ? $website : 'https://' . $website );
			if ( ! $url || ! wp_http_validate_url( $url ) ) {
				$d['notes'][] = sprintf( __( 'The website "%s" is not a web address and was left out.', 'events-manager' ), $website );
			} elseif ( '' === $d['location_name'] ) {
				$d['event_location'] = array( 'type' => 'url', 'url' => $url, 'text' => __( 'Event website', 'events-manager' ) );
			} else {
				$d['attributes'][ __( 'Website', 'events-manager' ) ] = $url;
				$d['lines'][ __( 'Website', 'events-manager' ) ] = true;
			}
		}
		if ( Values::is_true( $get( 'featured' ) ) ) {
			$d['attributes'][ __( 'Featured', 'events-manager' ) ] = __( 'Yes', 'events-manager' );
		}
		if ( Values::is_true( $get( 'hide' ) ) ) {
			$d['attributes'][ __( 'Hidden from upcoming', 'events-manager' ) ] = __( 'Yes', 'events-manager' );
		}
		return $d;
	}

	protected function import_event( array $row, $source_id, $number, Job $job ) {
		global $wpdb;
		$d = $this->read_event( $row, $job );
		$this->notes = $d['notes'];
		if ( $job->option( 'skip_duplicates', true ) ) {
			$existing = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT event_id FROM ' . EM_EVENTS_TABLE . ' WHERE event_name=%s AND event_start_date=%s AND event_start_time=%s AND (event_status IS NULL OR event_status <> -1) LIMIT 1', $d['name'], $d['start_date'], $d['start_time'] ) );
			if ( $existing ) {
				throw new Skip( sprintf( __( 'An event with this name and start is already on this site (event %d).', 'events-manager' ), $existing ) );
			}
		}
		$import = $job->option( 'import' );
		$d['location_id'] = '' !== $d['location_name'] ? $this->get_location_id( $d['location_name'], $import, $job ) : 0;
		$d['organizers'] = $this->get_term_ids( $d['organizer_names'], \EM\Organizers::TAXONOMY, 'organizer', $import, $job );
		$d['categories'] = $this->get_term_ids( $d['category_names'], EM_TAXONOMY_CATEGORY, 'category', $import, $job );
		$d['tags'] = $this->get_term_ids( $d['tag_names'], EM_TAXONOMY_TAG, 'tag', $import, $job );
		$d['thumbnail_id'] = $this->get_image_id( $d['image'], $import, $job );
		$lines = array();
		if ( $d['organizers'] ) {
			$lines[ _n( 'Organizer', 'Organizers', count( $d['organizers'] ), 'events-manager' ) ] = '#_EVENTORGANIZERS';
		}
		$d['content'] = Writer::append_details_block( $d['content'], $lines + $d['lines'], 'event' );
		$EM_Event = Writer::save_event( $d );
		if ( is_wp_error( $EM_Event ) ) {
			throw new Skip( wp_strip_all_tags( $EM_Event->get_error_message() ) );
		}
		Writer::pin_details_block( $EM_Event->post_id, 'event' );
		Map::set( static::KEY, 'event', $source_id, 'event', $EM_Event->event_id, array( 'row' => $number, 'post_id' => $EM_Event->post_id ) );
		if ( ! empty( $d['recurrence'] ) ) {
			$job->count( 'events', 'recurring' );
			if ( ! Writer::get_occurrence_starts( $EM_Event->event_id ) ) {
				$this->notes[] = __( 'The repeat rule gives no dates between the start date and the repeat-until date, so the event has no dates yet.', 'events-manager' );
			}
		}
	}

	protected function import_location( array $row, $source_id, $number, Job $job ) {
		$get = function( $field ) use ( $row ) {
			return isset( $row[ $field ] ) ? $row[ $field ] : '';
		};
		$existing = $this->find_location( $get( 'name' ) );
		if ( $existing && $job->option( 'skip_duplicates', true ) ) {
			throw new Skip( sprintf( __( 'A location with this name is already on this site (location %d).', 'events-manager' ), $existing ) );
		}
		$country = Countries::to_code( $get( 'country' ) );
		if ( ! $country && '' !== $get( 'country' ) ) {
			$this->notes[] = sprintf( __( 'The country "%s" is not recognized and was left empty.', 'events-manager' ), $get( 'country' ) );
		}
		$attributes = array();
		if ( '' !== $get( 'phone' ) ) {
			$attributes[ __( 'Phone', 'events-manager' ) ] = $get( 'phone' );
		}
		if ( '' !== $get( 'website' ) ) {
			$attributes[ __( 'Website', 'events-manager' ) ] = $get( 'website' );
		}
		$d = array(
			'name' => $get( 'name' ),
			'content' => Writer::append_details_block( $get( 'content' ), $attributes, 'location' ),
			'address' => implode( ', ', array_filter( array( $get( 'address' ), $get( 'address2' ) ), 'strlen' ) ),
			'town' => $get( 'town' ),
			'state' => $get( 'state' ),
			'postcode' => $get( 'postcode' ),
			'region' => $get( 'region' ),
			'country' => $country,
			'status' => $job->option( 'status', 'publish' ),
			'attributes' => $attributes,
			'thumbnail_id' => $this->get_image_id( $get( 'image' ), $job->option( 'import' ), $job ),
		);
		if ( '' !== $get( 'latitude' ) . $get( 'longitude' ) ) {
			if ( is_numeric( $get( 'latitude' ) ) && is_numeric( $get( 'longitude' ) ) && abs( (float) $get( 'latitude' ) ) <= 90 && abs( (float) $get( 'longitude' ) ) <= 180 ) {
				$d['latitude'] = $get( 'latitude' );
				$d['longitude'] = $get( 'longitude' );
			} else {
				$this->notes[] = __( 'The latitude and longitude are not a pair of coordinates and were left out.', 'events-manager' );
			}
		}
		$EM_Location = Writer::save_location( $d );
		if ( is_wp_error( $EM_Location ) ) {
			throw new Skip( wp_strip_all_tags( $EM_Location->get_error_message() ) );
		}
		Writer::pin_details_block( $EM_Location->post_id, 'location' );
		Map::set( static::KEY, 'location', $source_id, 'location', $EM_Location->location_id, array( 'row' => $number, 'post_id' => $EM_Location->post_id ) );
	}

	protected function import_organizer( array $row, $source_id, $number, Job $job ) {
		$get = function( $field ) use ( $row ) {
			return isset( $row[ $field ] ) ? $row[ $field ] : '';
		};
		$taxonomy = \EM\Organizers::TAXONOMY;
		$result = $this->find_term( $get( 'name' ), $taxonomy ) ? array( 'created' => false ) : Writer::save_term( $taxonomy, $get( 'name' ), sanitize_title( $get( 'name' ) ), 0, $get( 'content' ) );
		if ( is_wp_error( $result ) ) {
			throw new Skip( $result->get_error_message() );
		}
		if ( ! $result['created'] ) {
			throw new Skip( __( 'An organizer with this name is already on this site.', 'events-manager' ) );
		}
		$term_id = $result['term_id'];
		Map::set( static::KEY, 'organizer', $source_id, 'term', $term_id, array( 'taxonomy' => $taxonomy, 'row' => $number ) );
		if ( '' !== $get( 'email' ) ) {
			if ( is_email( $get( 'email' ) ) ) {
				update_term_meta( $term_id, 'organizer_email', sanitize_email( $get( 'email' ) ) );
			} else {
				$this->notes[] = sprintf( __( 'The email "%s" is not an email address and was left out.', 'events-manager' ), $get( 'email' ) );
			}
		}
		if ( '' !== $get( 'phone' ) ) {
			update_term_meta( $term_id, 'organizer_phone', sanitize_text_field( $get( 'phone' ) ) );
		}
		if ( '' !== $get( 'website' ) ) {
			$url = esc_url_raw( preg_match( '#^[a-z][a-z0-9+.-]*://#i', $get( 'website' ) ) ? $get( 'website' ) : 'https://' . $get( 'website' ) );
			if ( $url ) {
				update_term_meta( $term_id, 'organizer_website', $url );
			}
		}
		$image = $this->get_image_id( $get( 'image' ), $job->option( 'import' ), $job );
		if ( $image ) {
			update_term_meta( $term_id, 'organizer_image_id', $image );
		}
	}

	// ---- Things a row links to by name ----

	public function find_location( $name ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT location_id FROM ' . EM_LOCATIONS_TABLE . ' WHERE location_name=%s AND (location_status IS NULL OR location_status <> -1) ORDER BY location_status DESC, location_id ASC LIMIT 1', $name ) );
	}

	protected function get_location_id( $name, $import, Job $job ) {
		$existing = $this->find_location( $name );
		if ( $existing ) {
			$job->count( 'locations', 'reused' );
			return $existing;
		}
		$EM_Location = Writer::save_location( array( 'name' => $name, 'status' => 'publish' ) );
		if ( is_wp_error( $EM_Location ) ) {
			$this->notes[] = sprintf( __( 'The location "%1$s" could not be created: %2$s', 'events-manager' ), $name, wp_strip_all_tags( $EM_Location->get_error_message() ) );
			return 0;
		}
		Map::set( static::KEY, 'location', $import . ':' . md5( strtolower( $name ) ), 'location', $EM_Location->location_id, array( 'post_id' => $EM_Location->post_id, 'name' => $name ) );
		$job->count( 'locations', 'created' );
		$this->notes[] = sprintf( __( 'The location "%s" was not on this site, so it was created with its name only. Add its address, or import a locations file first.', 'events-manager' ), $name );
		return (int) $EM_Location->location_id;
	}

	public function find_term( $name, $taxonomy ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return 0;
		}
		$term = get_term_by( 'name', $name, $taxonomy ) ?: get_term_by( 'slug', sanitize_title( $name ), $taxonomy );
		return $term ? (int) $term->term_id : 0;
	}

	/**
	 * Splits a cell of names, keeping a name that itself contains a comma together when it is already on the site ("Smith, Jones and Co").
	 *
	 * @return string[]
	 */
	protected function split_names( $value, $taxonomy ) {
		$pieces = Values::split_list( $value );
		if ( count( $pieces ) < 2 || false !== strpos( $value, '|' ) || false !== strpos( $value, ';' ) ) {
			return $pieces;
		}
		$names = array();
		for ( $i = 0, $n = count( $pieces ); $i < $n; $i++ ) {
			$name = $pieces[ $i ];
			if ( isset( $pieces[ $i + 1 ] ) && ! $this->find_term( $name, $taxonomy ) && $this->find_term( $name . ', ' . $pieces[ $i + 1 ], $taxonomy ) ) {
				$name .= ', ' . $pieces[ ++$i ];
			}
			$names[] = $name;
		}
		return $names;
	}

	/**
	 * @return int[] term ids, creating the terms that are missing
	 */
	protected function get_term_ids( array $names, $taxonomy, $object_type, $import, Job $job ) {
		$ids = array();
		if ( ! $names || ! taxonomy_exists( $taxonomy ) ) {
			return $ids;
		}
		$counter = array( 'organizer' => 'organizers', 'category' => 'categories', 'tag' => 'tags' )[ $object_type ];
		foreach ( $names as $name ) {
			// The Events Calendar's importer also takes organizer IDs in this column
			if ( 'organizer' === $object_type && ctype_digit( $name ) && term_exists( (int) $name, $taxonomy ) ) {
				$ids[] = (int) $name;
				continue;
			}
			$existing = $this->find_term( $name, $taxonomy );
			if ( $existing ) {
				$ids[] = $existing;
				continue;
			}
			$result = Writer::save_term( $taxonomy, $name, sanitize_title( $name ) );
			if ( is_wp_error( $result ) ) {
				$this->notes[] = sprintf( __( '"%1$s" could not be created: %2$s', 'events-manager' ), $name, $result->get_error_message() );
				continue;
			}
			if ( $result['created'] ) {
				Map::set( static::KEY, $object_type, $import . ':' . md5( strtolower( $name ) ), 'term', $result['term_id'], array( 'taxonomy' => $taxonomy, 'name' => $name ) );
				$job->count( $counter, 'created' );
			}
			$ids[] = $result['term_id'];
		}
		return array_values( array_unique( $ids ) );
	}

	protected function get_image_id( $value, $import, Job $job ) {
		if ( '' === trim( (string) $value ) ) {
			return 0;
		}
		$mapped = Map::get( static::KEY, 'image', $import . ':' . md5( $value ) );
		if ( $mapped && get_post( (int) $mapped['em_id'] ) ) {
			return (int) $mapped['em_id'];
		}
		$image = Writer::get_image( $value, (bool) $job->option( 'images', true ) );
		if ( is_wp_error( $image ) ) {
			$this->notes[] = sprintf( __( 'The featured image was left out: %s', 'events-manager' ), wp_strip_all_tags( $image->get_error_message() ) );
			return 0;
		}
		if ( $image['created'] ) {
			Map::set( static::KEY, 'image', $import . ':' . md5( $value ), 'attachment', $image['id'], array( 'url' => $value ) );
			$job->count( 'images', 'downloaded' );
		}
		return $image['id'];
	}

	// ---- Result report and history ----

	protected function flush_report( Job $job ) {
		if ( ! $this->report_rows ) {
			return;
		}
		$report = get_option( static::REPORT_OPTION );
		if ( ! is_array( $report ) || ( $report['import'] ?? '' ) !== $job->option( 'import' ) ) {
			$report = array( 'import' => $job->option( 'import' ), 'rows' => array(), 'more' => 0 );
		}
		foreach ( $this->report_rows as $row ) {
			if ( count( $report['rows'] ) < static::REPORT_LIMIT ) {
				$report['rows'][] = $row;
			} else {
				$report['more']++;
			}
		}
		$this->report_rows = array();
		update_option( static::REPORT_OPTION, $report, false );
	}

	public function get_report( Job $job ) {
		$report = get_option( static::REPORT_OPTION );
		$type = $job->option( 'type' );
		if ( 'migrate' !== $job->mode || ! $job->started || ! is_array( $report ) || ( $report['import'] ?? '' ) !== $job->option( 'import' ) ) {
			return null;
		}
		$imported = (int) ( $job->stats[ $type ]['created'] ?? 0 );
		$skipped = (int) ( $job->stats[ $type ]['skipped'] ?? 0 );
		$rows = array();
		foreach ( $report['rows'] as $row ) {
			$rows[] = array( (string) $row['row'], $row['name'], 'skipped' === $row['result'] ? __( 'Skipped', 'events-manager' ) : __( 'Imported, with a note', 'events-manager' ), $row['message'] );
		}
		return array(
			'summary' => sprintf( __( '%1$s: %2$d rows imported, %3$d skipped.', 'events-manager' ), $job->option( 'name' ), $imported, $skipped ),
			'columns' => array( __( 'Row', 'events-manager' ), __( 'Name', 'events-manager' ), __( 'Result', 'events-manager' ), __( 'Why', 'events-manager' ) ),
			'rows' => $rows,
			'footnote' => $report['more'] ? sprintf( __( '%d more rows are not listed.', 'events-manager' ), $report['more'] ) : '',
		);
	}

	/**
	 * Keeps a short record of each import, so an earlier one can still be rolled back after a later one has run.
	 */
	public static function remember( $import, array $data ) {
		$history = static::get_history();
		$history[ $import ] = array_merge( $history[ $import ] ?? array(), $data );
		if ( count( $history ) > static::HISTORY_LIMIT ) {
			$history = array_slice( $history, - static::HISTORY_LIMIT, null, true );
		}
		update_option( static::HISTORY_OPTION, $history, false );
	}

	public static function get_history() {
		$history = get_option( static::HISTORY_OPTION );
		return is_array( $history ) ? $history : array();
	}

	public static function finished( $source, $job ) {
		if ( $source instanceof self && 'rolled_back' === $job->status && $job->option( 'import' ) ) {
			static::remember( $job->option( 'import' ), array( 'status' => 'rolled_back' ) );
		}
	}
}
add_action( 'em_migrate_finished', array( '\EM\Migrate\Sources\CSV\CSV', 'finished' ), 10, 2 );
add_action( 'admin_init', array( '\EM\Migrate\Sources\CSV\CSV', 'admin_init' ) );
