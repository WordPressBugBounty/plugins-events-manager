<?php
namespace EM\Migrate;

/**
 * State of one source's migration, kept in an option so a run survives page loads, timeouts and CLI restarts.
 */
class Job {

	public $source;
	public $status = 'idle'; // idle, running, done, rolling_back, rolled_back
	public $mode = 'migrate'; // migrate, rollback
	public $options = array();
	public $step = null;
	public $cursor = null;
	public $stats = array();
	public $log = array();
	public $started = null;
	public $finished = null;
	public $last_run = null;

	const LOG_LIMIT = 300;

	public function __construct( $source ) {
		$this->source = $source;
		$data = get_option( static::option_name( $source ) );
		if ( is_array( $data ) ) {
			foreach ( $data as $k => $v ) {
				if ( property_exists( $this, $k ) && 'source' !== $k ) {
					$this->$k = $v;
				}
			}
		}
	}

	public static function option_name( $source ) {
		return 'dbem_migrate_job_' . sanitize_key( $source );
	}

	public function save() {
		$data = get_object_vars( $this );
		update_option( static::option_name( $this->source ), $data, false );
	}

	public function reset( $mode = 'migrate', $options = array() ) {
		$this->mode = $mode;
		$this->status = 'running';
		$this->options = $options;
		$this->step = null;
		$this->cursor = null;
		$this->stats = array();
		$this->started = current_time( 'mysql', true );
		$this->finished = null;
		$this->log = array();
		$this->log( 'rollback' === $mode ? 'Rollback started.' : 'Migration started.' );
	}

	public function option( $key, $default = null ) {
		return array_key_exists( $key, $this->options ) ? $this->options[ $key ] : $default;
	}

	/**
	 * @param string $what created, updated, skipped, failed, or a custom counter
	 */
	public function count( $step, $what, $n = 1 ) {
		if ( ! isset( $this->stats[ $step ][ $what ] ) ) {
			$this->stats[ $step ][ $what ] = 0;
		}
		$this->stats[ $step ][ $what ] += $n;
	}

	public function log( $message, $level = 'info' ) {
		if ( '' === $message ) {
			return;
		}
		$this->log[] = array( 't' => gmdate( 'H:i:s' ), 'level' => $level, 'step' => $this->step, 'message' => $message );
		if ( count( $this->log ) > static::LOG_LIMIT ) {
			$this->log = array_slice( $this->log, - static::LOG_LIMIT );
		}
		if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( '\WP_CLI' ) ) {
			$line = ( $this->step ? '[' . $this->step . '] ' : '' ) . $message;
			if ( 'error' === $level ) {
				\WP_CLI::warning( $line );
			} elseif ( 'debug' !== $level ) {
				\WP_CLI::log( $line );
			}
		}
	}

	public function to_array() {
		return get_object_vars( $this );
	}
}
