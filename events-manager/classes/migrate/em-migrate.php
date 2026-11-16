<?php
namespace EM\Migrate;

require_once __DIR__ . '/em-migrate-map.php';
require_once __DIR__ . '/em-migrate-job.php';
require_once __DIR__ . '/em-migrate-source.php';
require_once __DIR__ . '/em-migrate-writer.php';
require_once __DIR__ . '/em-migrate-runner.php';
require_once __DIR__ . '/sources/tec/em-migrate-tec.php';
require_once __DIR__ . '/sources/csv/em-migrate-csv.php';

class Migrate {

	protected static $sources;

	public static function init() {
		add_action( 'wp_ajax_em_migrate', array( static::class, 'ajax' ) );
		add_action( 'template_redirect', array( static::class, 'redirect' ), 5 );
		if ( is_admin() ) {
			require_once __DIR__ . '/em-migrate-admin.php';
			Admin::init();
		}
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once __DIR__ . '/em-migrate-cli.php';
			\WP_CLI::add_command( 'em migrate', '\EM\Migrate\CLI' );
		}
	}

	/**
	 * @return Source[]
	 */
	public static function get_sources() {
		if ( null === static::$sources ) {
			$sources = apply_filters( 'em_migrate_sources', array( 'tec' => new Sources\TEC\TEC(), 'csv' => new Sources\CSV\CSV() ) );
			static::$sources = array();
			foreach ( $sources as $source ) {
				if ( $source instanceof Source ) {
					static::$sources[ $source->get_key() ] = $source;
				}
			}
		}
		return static::$sources;
	}

	/**
	 * @return Source|null
	 */
	public static function get_source( $key ) {
		$sources = static::get_sources();
		return $sources[ $key ] ?? null;
	}

	/**
	 * Sources with data on this site, or with a migration already started, and those that need no data to be there.
	 */
	public static function get_available_sources() {
		return array_filter( static::get_sources(), function( Source $source ) {
			return $source->is_always_available() || $source->is_detected() || Map::count( $source->get_key() ) || ( new Job( $source->get_key() ) )->started;
		} );
	}

	public static function can_migrate() {
		return current_user_can( 'manage_options' );
	}

	public static function ajax() {
		check_ajax_referer( 'em_migrate', 'nonce' );
		if ( ! static::can_migrate() ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to migrate data.', 'events-manager' ) ), 403 );
		}
		$source = static::get_source( sanitize_key( wp_unslash( $_POST['source'] ?? '' ) ) );
		if ( ! $source ) {
			wp_send_json_error( array( 'message' => __( 'Unknown migration source.', 'events-manager' ) ), 400 );
		}
		$task = sanitize_key( wp_unslash( $_POST['task'] ?? 'status' ) );
		switch ( $task ) {
			case 'start':
				$options = static::sanitize_options( $source, (array) wp_unslash( $_POST['options'] ?? array() ) );
				if ( is_wp_error( $options ) ) {
					wp_send_json_error( array( 'message' => $options->get_error_message() ), 400 );
				}
				Runner::start( $source, $options );
				$job = Runner::tick( $source, 10 );
				break;
			case 'tick':
				$job = Runner::tick( $source, 15 );
				break;
			case 'rollback':
				Runner::start_rollback( $source );
				$job = Runner::tick( $source, 10 );
				break;
			case 'stop':
				$job = new Job( $source->get_key() );
				if ( 'running' === $job->status ) {
					$job->status = 'idle';
					$job->log( 'Paused by ' . wp_get_current_user()->user_login . '.' );
					$job->save();
				}
				break;
			case 'resume':
				$job = new Job( $source->get_key() );
				if ( in_array( $job->status, array( 'idle', 'error' ), true ) && $job->started ) {
					$job->status = 'running';
					$job->save();
				}
				$job = Runner::tick( $source, 10 );
				break;
			default:
				$job = new Job( $source->get_key() );
		}
		wp_send_json_success( static::job_response( $source, $job ) );
	}

	public static function job_response( Source $source, Job $job ) {
		$steps = 'rollback' === $job->mode ? array( 'rollback' => __( 'Rollback', 'events-manager' ) ) : $source->get_steps();
		return array(
			'status' => $job->status,
			'mode' => $job->mode,
			'step' => $job->step,
			'step_label' => $job->step && isset( $steps[ $job->step ] ) ? $steps[ $job->step ] : '',
			'steps' => $steps,
			'stats' => $job->stats,
			'log' => array_slice( $job->log, -60 ),
			'mapped' => Map::counts_by_type( $source->get_key(), $source->get_rollback_scope( $job ) ),
			'started' => $job->started,
			'finished' => $job->finished,
			'report' => $source->get_report( $job ),
		);
	}

	/**
	 * @return array|\WP_Error
	 */
	public static function sanitize_options( Source $source, array $input ) {
		return $source->prepare_options( static::sanitize_fields( $source, $input ), $input );
	}

	/**
	 * The checkbox and select options of a source, from a request or command line.
	 */
	public static function sanitize_fields( Source $source, array $input ) {
		$options = array();
		foreach ( $source->get_options() as $key => $def ) {
			if ( 'checkbox' === $def['type'] ) {
				$options[ $key ] = ! empty( $input[ $key ] );
			} elseif ( 'select' === $def['type'] ) {
				$value = isset( $input[ $key ] ) ? (string) $input[ $key ] : $def['default'];
				$options[ $key ] = array_key_exists( $value, $def['choices'] ) ? $value : $def['default'];
			}
		}
		return $options;
	}

	/**
	 * 301s old URLs of a migrated plugin to the new EM pages, only once the old plugin is off (while it runs, its own URLs still work).
	 */
	public static function redirect() {
		// EM's own legacy rules claim /events/category/... and /events/event/..., and fall through to the events page when the slug isn't one of theirs
		if ( ! ( is_404() || get_query_var( 'em_redirect' ) ) || ! get_option( 'dbem_migrate_redirects' ) ) {
			return;
		}
		$path = trim( (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH ), '/' );
		$home = trim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
		if ( $home && 0 === strpos( $path, $home ) ) {
			$path = trim( substr( $path, strlen( $home ) ), '/' );
		}
		$query = array();
		wp_parse_str( (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_QUERY ), $query );
		foreach ( (array) get_option( 'dbem_migrate_redirects' ) as $key ) {
			$source = static::get_source( $key );
			if ( ! $source || $source->is_plugin_active() ) {
				continue;
			}
			$url = $source->resolve_redirect( urldecode( $path ), $query );
			if ( $url ) {
				wp_safe_redirect( $url, 301, 'Events Manager' );
				exit;
			}
		}
	}

	public static function enable_redirects( $key, $enable = true ) {
		$keys = array_filter( (array) get_option( 'dbem_migrate_redirects', array() ) );
		$keys = $enable ? array_unique( array_merge( $keys, array( $key ) ) ) : array_diff( $keys, array( $key ) );
		update_option( 'dbem_migrate_redirects', array_values( $keys ) );
	}
}
add_action( 'init', array( '\EM\Migrate\Migrate', 'init' ), 20 );
