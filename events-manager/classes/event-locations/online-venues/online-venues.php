<?php
namespace EM_Event_Locations;

use WP_Error;

/**
 * Runs remote calls for online venue providers: the queue that creates, updates and deletes remote objects, polling sync, and the booking hooks that keep registrants or access lists current.
 */
class Online_Venues {

	const QUEUE_HOOK = 'em_online_venues_queue';
	const SYNC_HOOK = 'em_online_venues_sync';
	const MAX_ATTEMPTS = 8;

	protected static $initialized = false;

	public static function init(){
		if( static::$initialized ) return;
		static::$initialized = true;
		add_filter('cron_schedules', array( __CLASS__, 'cron_schedules' ));
		add_action( static::QUEUE_HOOK, array( __CLASS__, 'run_queue' ));
		add_action( static::SYNC_HOOK, array( __CLASS__, 'run_sync' ));
		add_action('init', array( __CLASS__, 'schedule_cron' ), 20);
		add_filter('em_event_save_events', array( __CLASS__, 'em_event_save_events' ), 10, 3);
		add_filter('em_event_set_status', array( __CLASS__, 'em_event_set_status' ), 10, 3);
		add_filter('em_booking_save', array( __CLASS__, 'em_booking_changed' ), 100, 2);
		add_filter('em_booking_set_status', array( __CLASS__, 'em_booking_changed' ), 100, 2);
		add_action('em_booking_deleted', array( __CLASS__, 'em_booking_deleted' ), 100, 1);
	}

	/**
	 * MySQL named locks are server-wide, so names carry the site's database and table prefix to keep other sites and multisite subsites out of each other's way.
	 */
	public static function lock_name( $name ){
		global $wpdb;
		return 'em_ov_' . substr( md5( DB_NAME . '|' . $wpdb->prefix ), 0, 10 ) . '_' . $name;
	}

	public static function cron_schedules( $schedules ){
		$schedules['em_online_venues'] = array( 'interval' => 5 * MINUTE_IN_SECONDS, 'display' => 'Events Manager online venues' );
		return $schedules;
	}

	public static function schedule_cron(){
		if( !wp_next_scheduled( static::QUEUE_HOOK ) ){
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'em_online_venues', static::QUEUE_HOOK );
		}
		if( !wp_next_scheduled( static::SYNC_HOOK ) ){
			wp_schedule_event( time() + 5 * MINUTE_IN_SECONDS, 'hourly', static::SYNC_HOOK );
		}
	}

	/**
	 * Brings the queue forward, for example straight after a series is saved.
	 * @param int|null $timestamp
	 */
	public static function schedule_queue( $timestamp = null ){
		$timestamp = max( time(), (int) $timestamp );
		$next = wp_next_scheduled( static::QUEUE_HOOK, array( 'single' ) );
		if( !$next || $next > $timestamp + MINUTE_IN_SECONDS ){
			if( $next ) wp_unschedule_event( $next, static::QUEUE_HOOK, array( 'single' ) );
			wp_schedule_single_event( $timestamp, static::QUEUE_HOOK, array( 'single' ) );
		}
	}

	/**
	 * Plans one session per occurrence once a series' recurrences exist, for providers that cannot share one room across dates.
	 */
	public static function em_event_save_events( $result, $EM_Event, $event_ids ){
		if( $result === false || !$EM_Event instanceof \EM_Event || !$EM_Event->has_event_location() ) return $result;
		$EM_Event_Location = $EM_Event->get_event_location();
		if( !$EM_Event_Location instanceof Online_Venue || $EM_Event_Location->get_session_strategy() !== 'occurrences' ) return $result;
		static::plan_occurrences( $EM_Event, $event_ids );
		return $result;
	}

	/**
	 * Publishing outside a full save, such as a scheduled post going live, only changes the status, so the remote objects are planned here.
	 */
	public static function em_event_set_status( $result, $status, $EM_Event ){
		if( $result && (int) $status === 1 && $EM_Event instanceof \EM_Event && $EM_Event->event_id && !$EM_Event->is_recurrence() && $EM_Event->has_event_location() ){
			$EM_Event_Location = $EM_Event->get_event_location();
			if( $EM_Event_Location instanceof Online_Venue ){
				$EM_Event_Location->plan( false );
				static::schedule_queue();
			}
		}
		return $result;
	}

	/**
	 * @param \EM_Event $EM_Event A recurring or repeating event.
	 * @return int[]
	 */
	public static function get_occurrence_ids( $EM_Event ){
		global $wpdb;
		$sql = $wpdb->prepare( 'SELECT event_id FROM ' . EM_EVENTS_TABLE . " WHERE event_type='recurrence' AND recurrence_set_id IN ( SELECT recurrence_set_id FROM " . EM_EVENT_RECURRENCES_TABLE . ' WHERE event_id=%d )', $EM_Event->event_id );
		return array_map( 'intval', $wpdb->get_col( $sql ) );
	}

	/**
	 * @param \EM_Event $EM_Event The recurring or repeating event.
	 * @param int[] $event_ids
	 */
	public static function plan_occurrences( $EM_Event, $event_ids ){
		$EM_Event_Location = $EM_Event->get_event_location();
		if( !$EM_Event_Location instanceof Online_Venue ) return;
		$keep = array();
		foreach( (array) $event_ids as $event_id ){
			$occurrence = em_get_event( $event_id );
			$occurrence->event_location_type = $EM_Event->event_location_type;
			$occurrence_location = $occurrence->get_event_location();
			if( $occurrence_location instanceof Online_Venue && $occurrence_location->type === $EM_Event_Location->type ){
				$occurrence_location->data = $EM_Event_Location->data;
				$occurrence_location->plan_session( $occurrence, $EM_Event->event_id );
				$keep[] = (int) $event_id;
			}
		}
		static::queue_series_deletes( $EM_Event, $EM_Event_Location->type, $keep );
		static::schedule_queue();
	}

	/**
	 * Queues deletion of a series' sessions that no longer match an occurrence, or the series room when it switches to one meeting per date.
	 * @param \EM_Event $EM_Event
	 * @param string $location_type
	 * @param int[] $keep Event IDs whose sessions stay.
	 */
	public static function queue_series_deletes( $EM_Event, $location_type, $keep = array() ){
		global $wpdb;
		$strategy = $EM_Event->get_event_location() instanceof Online_Venue ? $EM_Event->get_event_location()->get_session_strategy() : '';
		foreach( Online_Session::get_for_series( $EM_Event->event_id ) as $session ){
			if( $session->queue === 'delete' || in_array( $session->event_id, $keep, true ) ) continue;
			if( $session->location_type === $location_type && $strategy === 'occurrences' && $session->event_id !== (int) $EM_Event->event_id ){
				$exists = $wpdb->get_var( $wpdb->prepare( 'SELECT event_id FROM ' . EM_EVENTS_TABLE . " WHERE event_id=%d AND event_type='recurrence'", $session->event_id ) );
				if( $exists ) continue;
			}
			$session->queue('delete');
			$session->save();
		}
	}

	public static function run_queue(){
		global $wpdb;
		$lock = static::lock_name('queue');
		if( !$wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $lock ) ) ) return;
		try{
			$deadline = time() + (int) apply_filters('em_online_venues_queue_seconds', 40);
			foreach( Online_Session::get_due( 50, static::MAX_ATTEMPTS ) as $session ){
				if( time() > $deadline ) break;
				static::process( $session );
			}
		} finally {
			$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
		}
	}

	/**
	 * Runs the queued action for one session.
	 * @param Online_Session $session
	 * @param Online_Venue|null $EM_Event_Location The location being saved, when processing inline, since em_get_event() can still return the object cached before the save.
	 * @return true|WP_Error
	 */
	public static function process( $session, $EM_Event_Location = null ){
		global $wpdb;
		$lock = static::lock_name( 'session_' . $session->session_id );
		if( !$session->session_id || !$wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $lock ) ) ){
			return new WP_Error('em_online_venue_busy', __('Another request is already updating this online location.', 'events-manager'));
		}
		try{
			$fresh = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . EM_ONLINE_SESSIONS_TABLE . ' WHERE session_id=%d', $session->session_id ), ARRAY_A );
			if( !$fresh ) return true;
			$session = new Online_Session( $fresh );
			if( !$session->queue ) return true;
			$types = Event_Locations::get_types();
			if( empty( $types[ $session->location_type ] ) || !class_exists( $types[ $session->location_type ] ) ){
				$session->attempts++;
				$session->last_error = sprintf( 'The %s provider is not active.', $session->location_type );
				$session->not_before = gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS );
				$session->save_columns( array( 'attempts', 'last_error', 'not_before' ) );
				return new WP_Error('em_online_venue_missing', $session->last_error);
			}
			if( !static::take_rate_slot( $session ) ){
				return new WP_Error('em_online_venue_rate_limited', __('The provider rate limit was reached, so this will be retried shortly.', 'events-manager'));
			}
			$action = $session->queue;
			if( $action === 'delete' ){
				$class = $types[ $session->location_type ];
				$EM_Event_Location = new $class( null );
				if( $session->remote_id ){
					$EM_Event_Location->remote_delete( $session );
				}
				$session->delete();
				do_action('em_online_venue_remote_deleted', $session, $EM_Event_Location);
				return true;
			}
			if( $EM_Event_Location instanceof Online_Venue && $EM_Event_Location->event && (int) $EM_Event_Location->event->event_id === $session->event_id ){
				$EM_Event = $EM_Event_Location->event;
			}else{
				// em_get_event() can hand back the global or cached object from before a status change in this request, so read the event as it is now
				$EM_Event = new \EM_Event( $session->event_id );
				$EM_Event_Location = $EM_Event && $EM_Event->event_id ? $EM_Event->get_event_location() : false;
			}
			if( !$EM_Event_Location instanceof Online_Venue || $EM_Event_Location->type !== $session->location_type ){
				$session->queue('delete');
				$session->save();
				return true;
			}
			if( $action === 'create' && !$session->remote_id ){
				if( !$EM_Event_Location->should_create( $EM_Event ) ){
					$session->delete();
					return true;
				}
				$EM_Event_Location->remote_create( $session );
				$created = true;
			}else{
				$EM_Event_Location->remote_update( $session );
			}
			$session->set_data( 'fingerprint', $EM_Event_Location->get_fingerprint( $EM_Event ) );
			$session->queue = static::get_newer_queue( $session, $fresh['updated_at'], $action );
			$session->attempts = 0;
			$session->not_before = null;
			$session->last_error = '';
			$session->synced_at = gmdate('Y-m-d H:i:s');
			if( !$session->save() ){
				$error = sprintf( __('%1$s was updated, but the result could not be stored: %2$s', 'events-manager'), $session->location_type, $wpdb->last_error );
				$session->last_error = $error;
				$session->attempts++;
				$session->not_before = gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS );
				$session->save_columns( array( 'last_error', 'attempts', 'not_before' ) );
				do_action('em_online_venue_store_error', $session, $error);
				return new WP_Error('em_online_venue_store_error', $error);
			}
			if( !empty( $created ) ){
				static::register_existing_bookings( $EM_Event_Location, $session );
			}
			do_action('em_online_venue_remote_' . $action . 'd', $session, $EM_Event_Location);
			return true;
		}catch( \Exception $ex ){
			$message = $ex instanceof \EM_Exception ? implode( ' ', (array) $ex->get_messages() ) : $ex->getMessage();
			$session->attempts++;
			$session->last_error = wp_strip_all_tags( $message );
			$session->not_before = gmdate( 'Y-m-d H:i:s', time() + min( 6 * HOUR_IN_SECONDS, MINUTE_IN_SECONDS * pow( 2, $session->attempts ) ) );
			$session->save();
			do_action('em_online_venue_remote_error', $session, $ex);
			return new WP_Error('em_online_venue_remote_error', $session->last_error);
		} finally {
			$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
		}
	}

	/**
	 * An update or delete queued by a save while the remote call was running, which must survive the worker marking its own action done.
	 * @param Online_Session $session
	 * @param string $read_at updated_at when the worker read the row.
	 * @param string $action The action the worker just ran.
	 * @return string|null
	 */
	protected static function get_newer_queue( $session, $read_at, $action ){
		global $wpdb;
		$now = $wpdb->get_row( $wpdb->prepare( 'SELECT queue, updated_at FROM ' . EM_ONLINE_SESSIONS_TABLE . ' WHERE session_id=%d', $session->session_id ), ARRAY_A );
		if( $now && ( $now['updated_at'] !== $read_at || $now['queue'] !== $action ) && in_array( $now['queue'], array( 'update', 'delete' ), true ) ){
			return $now['queue'];
		}
		return null;
	}

	/**
	 * Counts a remote write against the provider's rate limit, pushing the session back to the next window when it is full.
	 * @param Online_Session $session
	 * @return bool
	 */
	protected static function take_rate_slot( $session ){
		$types = Event_Locations::get_types();
		$class = $types[ $session->location_type ];
		$capabilities = $class::get_capabilities();
		if( empty( $capabilities['rate_limit']['requests'] ) || empty( $capabilities['rate_limit']['per'] ) ) return true;
		$per = (int) $capabilities['rate_limit']['per'];
		$window = (int) floor( time() / $per );
		$key = 'em_ov_rate_' . $session->location_type . '_' . $window;
		$used = (int) get_transient( $key );
		if( $used >= (int) $capabilities['rate_limit']['requests'] ){
			$session->not_before = gmdate( 'Y-m-d H:i:s', ( $window + 1 ) * $per );
			$session->save();
			static::schedule_queue( ( $window + 1 ) * $per );
			return false;
		}
		set_transient( $key, $used + 1, $per * 2 );
		return true;
	}

	/**
	 * Polls providers for status and link changes. Only the columns a sync owns are written back, so a save that queues an update or delete meanwhile is kept.
	 */
	public static function run_sync(){
		global $wpdb;
		foreach( Online_Session::get_syncable( time() - DAY_IN_SECONDS, time() + 7 * DAY_IN_SECONDS ) as $session ){
			$lock = static::lock_name( 'session_' . $session->session_id );
			if( !$wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $lock ) ) ) continue;
			try{
				$fresh = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . EM_ONLINE_SESSIONS_TABLE . ' WHERE session_id=%d', $session->session_id ), ARRAY_A );
				if( !$fresh || $fresh['queue'] ) continue;
				$session = new Online_Session( $fresh );
				$EM_Event = em_get_event( $session->event_id );
				$EM_Event_Location = $EM_Event && $EM_Event->event_id ? $EM_Event->get_event_location() : false;
				if( !$EM_Event_Location instanceof Online_Venue || $EM_Event_Location->type !== $session->location_type ) continue;
				try{
					$EM_Event_Location->remote_sync( $session );
					$session->last_error = '';
				}catch( \Exception $ex ){
					$session->last_error = wp_strip_all_tags( $ex instanceof \EM_Exception ? implode( ' ', (array) $ex->get_messages() ) : $ex->getMessage() );
					do_action('em_online_venue_sync_error', $session, $ex);
				}
				$session->synced_at = gmdate('Y-m-d H:i:s');
				$session->save_columns( array( 'remote_status', 'join_url', 'last_error', 'synced_at' ) );
			} finally {
				$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
			}
		}
	}

	/**
	 * @param Online_Venue $EM_Event_Location
	 * @param Online_Session $session
	 */
	protected static function register_existing_bookings( $EM_Event_Location, $session ){
		$capabilities = $EM_Event_Location::get_capabilities();
		if( !$capabilities['registrants'] && !$capabilities['access_list'] ) return;
		foreach( $EM_Event_Location->event->get_bookings()->bookings as $EM_Booking ){
			static::sync_booking( $EM_Booking );
		}
	}

	public static function em_booking_changed( $result, $EM_Booking ){
		if( $result ) static::sync_booking( $EM_Booking );
		return $result;
	}

	public static function em_booking_deleted( $EM_Booking ){
		static::sync_booking( $EM_Booking, true );
	}

	/**
	 * Registers or unregisters a booking with its event's provider. Failures never block the booking itself; they are reported through em_online_venue_booking_error.
	 * @param \EM_Booking $EM_Booking
	 * @param bool $deleted
	 */
	public static function sync_booking( $EM_Booking, $deleted = false ){
		$EM_Event = $EM_Booking->get_event();
		$EM_Event_Location = $EM_Event && $EM_Event->has_event_location() ? $EM_Event->get_event_location() : false;
		if( !$EM_Event_Location instanceof Online_Venue ) return;
		$capabilities = $EM_Event_Location::get_capabilities();
		if( !$capabilities['registrants'] && !$capabilities['access_list'] ) return;
		$session = $EM_Event_Location->get_session();
		if( !$session || !$session->remote_id ) return;
		try{
			if( !$deleted && $EM_Event_Location->can_access( $EM_Booking ) ){
				$EM_Event_Location->register_booking( $EM_Booking, $session );
			}else{
				$EM_Event_Location->unregister_booking( $EM_Booking, $session );
			}
		}catch( \Exception $ex ){
			do_action('em_online_venue_booking_error', $ex, $EM_Booking, $EM_Event_Location);
		}
	}
}
