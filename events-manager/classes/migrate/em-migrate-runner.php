<?php
namespace EM\Migrate;

/**
 * Drives a Job through its source's steps in time-boxed batches, so the admin screen can poll it over AJAX and WP-CLI can loop it to the end.
 */
class Runner {

	public static function start( Source $source, $options = array() ) {
		Map::install();
		$job = new Job( $source->get_key() );
		$job->reset( 'migrate', $options );
		$job->save();
		return $job;
	}

	public static function start_rollback( Source $source ) {
		Map::install();
		$job = new Job( $source->get_key() );
		$options = $job->options;
		$job->reset( 'rollback', $options );
		$job->save();
		return $job;
	}

	/**
	 * Runs batches until the time budget is used or the job finishes.
	 */
	public static function tick( Source $source, $budget = 15, $limit = 10 ) {
		$job = new Job( $source->get_key() );
		if ( 'running' !== $job->status ) {
			return $job;
		}
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( max( 60, $budget * 4 ) );
		}
		static::prepare_environment();
		$steps = 'rollback' === $job->mode ? array( 'rollback' => __( 'Rollback', 'events-manager' ) ) : $source->get_steps();
		$keys = array_keys( $steps );
		if ( null === $job->step || ! in_array( $job->step, $keys, true ) ) {
			$job->step = $keys[0];
			$job->cursor = null;
		}
		$deadline = microtime( true ) + $budget;
		do {
			try {
				if ( 'rollback' === $job->mode ) {
					$result = static::rollback_batch( $source, $job, $limit * 5 );
				} else {
					$result = $source->run_step( $job->step, $job->cursor, $limit, $job );
				}
			} catch ( \Throwable $e ) {
				$job->log( sprintf( 'Stopped on an error: %s (%s:%d)', $e->getMessage(), basename( $e->getFile() ), $e->getLine() ), 'error' );
				$job->status = 'error';
				$job->save();
				return $job;
			}
			if ( ! empty( $result['done'] ) ) {
				$index = array_search( $job->step, $keys, true );
				if ( isset( $keys[ $index + 1 ] ) ) {
					$job->step = $keys[ $index + 1 ];
					$job->cursor = null;
				} else {
					$job->status = 'rollback' === $job->mode ? 'rolled_back' : 'done';
					$job->finished = current_time( 'mysql', true );
					$job->step = null;
					$job->cursor = null;
					$job->log( 'rollback' === $job->mode ? 'Rollback finished.' : 'Migration finished.' );
					do_action( 'em_migrate_finished', $source, $job );
					break;
				}
			} else {
				$job->cursor = $result['cursor'];
			}
			$job->last_run = current_time( 'mysql', true );
			$job->save();
			static::free_memory();
		} while ( microtime( true ) < $deadline );
		$job->save();
		return $job;
	}

	/**
	 * Silences side effects that only make sense for live front-end activity: emails, cancellation cascades and per-request globals EM loads from request keys.
	 */
	public static function prepare_environment() {
		if ( ! defined( 'EM_MIGRATING' ) ) {
			define( 'EM_MIGRATING', true );
		}
		// EM can send through its own PHPMailer instance, so route everything through wp_mail and stop it there.
		add_filter( 'pre_option_dbem_rsvp_mail_send_method', function() { return 'wp_mail'; } );
		add_filter( 'pre_wp_mail', '\EM\Migrate\Runner::block_mail', 10, 2 );
		foreach ( array( 'dbem_event_cancelled_bookings', 'dbem_event_cancelled_email', 'dbem_event_cancelled_bookings_email' ) as $option ) {
			add_filter( 'pre_option_' . $option, '__return_zero' );
		}
		wp_defer_term_counting( true );
	}

	public static function block_mail( $return, $atts ) {
		return false;
	}

	public static function free_memory() {
		global $EM_Event, $EM_Location, $EM_Booking, $EM_Ticket, $wpdb;
		$EM_Event = $EM_Location = $EM_Booking = $EM_Ticket = null;
		if ( class_exists( '\EM_Ticket' ) && property_exists( '\EM_Ticket', 'instances' ) ) {
			// protected static cache that only grows; reset it from inside the class scope
			\Closure::bind( function() { static::$instances = array(); }, null, \EM_Ticket::class )();
		}
		if ( function_exists( 'wp_cache_flush_runtime' ) ) {
			wp_cache_flush_runtime();
		}
		$wpdb->queries = array();
	}

	/**
	 * Removes what the migration created, newest first, so bookings and tickets go before their events and events before their locations and terms.
	 */
	public static function rollback_batch( Source $source, Job $job, $limit ) {
		$rows = Map::get_rows( $source->get_key(), null, $limit, $source->get_rollback_scope( $job ) );
		if ( ! $rows ) {
			return array( 'done' => true, 'cursor' => null );
		}
		foreach ( $rows as $row ) {
			$ok = Writer::undo( $row );
			$ok = apply_filters( 'em_migrate_rollback_row', $ok, $row, $source, $job );
			if ( false === $ok ) {
				$job->log( sprintf( 'Could not remove %s %d (from %s %s); left it in place.', $row['em_type'], $row['em_id'], $row['object_type'], $row['source_id'] ), 'error' );
				$job->count( 'rollback', 'failed' );
			} else {
				$job->count( 'rollback', $row['object_type'] );
			}
			Map::delete( $row['map_id'] );
		}
		return array( 'done' => false, 'cursor' => null );
	}
}
