<?php
namespace EM_Event_Locations;

use WP_Error;

/**
 * Booking-signed join links for event locations, so emails and tickets never carry a provider's room or host URL and access is checked again when the link is followed.
 */
class Join_Gate {

	const QUERY_VAR = 'em_join';

	public static function init(){
		add_action('template_redirect', array( __CLASS__, 'template_redirect' ), 1);
	}

	/**
	 * @param \EM_Event $EM_Event
	 * @param \EM_Booking $EM_Booking
	 * @return string
	 */
	public static function get_url( $EM_Event, $EM_Booking ){
		$args = array(
			static::QUERY_VAR => absint( $EM_Event->event_id ),
			'booking' => $EM_Booking->booking_uuid,
			'key' => static::sign( $EM_Event->event_id, $EM_Booking ),
		);
		return apply_filters('em_event_location_join_gate_url', add_query_arg( $args, home_url('/') ), $EM_Event, $EM_Booking);
	}

	/**
	 * Links name the booking by its UUID, so a booking ID never appears in a link, and the UUID is also part of the signed message.
	 * @param int $event_id
	 * @param \EM_Booking $EM_Booking
	 * @return string
	 */
	public static function sign( $event_id, $EM_Booking ){
		$message = implode('|', array( 'em_join', absint($event_id), absint($EM_Booking->booking_id), (string) $EM_Booking->booking_uuid ));
		return substr( hash_hmac('sha256', $message, wp_salt('auth')), 0, 32 );
	}

	public static function template_redirect(){
		if( !isset( $_GET[ static::QUERY_VAR ] ) || is_admin() ) return; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- authorized by the signed key or the logged-in booking owner
		if( !defined('DONOTCACHEPAGE') ) define('DONOTCACHEPAGE', true);
		nocache_headers();
		header('X-Robots-Tag: noindex, nofollow');
		header('Referrer-Policy: no-referrer');
		$result = static::handle( wp_unslash( $_GET ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if( is_wp_error( $result ) ){
			static::output_error( $result );
		}
		exit;
	}

	/**
	 * @param array $request
	 * @return WP_Error|true
	 */
	public static function handle( $request ){
		$event_id = isset( $request[ static::QUERY_VAR ] ) ? absint( $request[ static::QUERY_VAR ] ) : 0;
		$booking_uuid = isset( $request['booking'] ) && is_string( $request['booking'] ) ? $request['booking'] : '';
		$key = isset( $request['key'] ) ? preg_replace('/[^a-f0-9]/', '', strtolower( (string) $request['key'] )) : '';
		if( !$event_id || !preg_match('/^[A-Za-z0-9]{32}$/', $booking_uuid) ){
			return static::error('em_join_invalid', 404);
		}
		$EM_Booking = em_get_booking( $booking_uuid );
		if( empty( $EM_Booking->booking_id ) || (int) $EM_Booking->event_id !== $event_id ){
			return static::error('em_join_invalid', 404);
		}
		$EM_Event = $EM_Booking->get_event();
		$authorized = $key !== '' && hash_equals( static::sign( $event_id, $EM_Booking ), $key );
		if( !$authorized && is_user_logged_in() ){
			$authorized = (int) $EM_Booking->person_id === get_current_user_id() || $EM_Event->can_manage('manage_bookings', 'manage_others_bookings');
		}
		if( !$authorized ){
			return static::error('em_join_invalid', 404);
		}
		$EM_Event_Location = $EM_Event->get_event_location();
		if( !$EM_Event_Location ){
			return static::error('em_join_no_location', 404, $EM_Event, $EM_Booking);
		}
		if( !$EM_Event_Location->can_access( $EM_Booking ) ){
			return static::error('em_join_no_access', 403, $EM_Event, $EM_Booking);
		}
		$window = $EM_Event_Location->get_join_window();
		$now = time();
		if( $now < $window['opens'] ){
			$hidden_text = method_exists( $EM_Event_Location, 'is_link_hidden' ) && $EM_Event_Location->is_link_hidden() ? $EM_Event_Location->get_link_hidden_text() : '';
			return static::error('em_join_too_early', 200, $EM_Event, $EM_Booking, $window, $hidden_text);
		}
		if( $now > $window['closes'] ){
			return static::error('em_join_closed', 410, $EM_Event, $EM_Booking, $window);
		}
		do_action('em_event_location_join', $EM_Event_Location, $EM_Booking);
		if( $EM_Event_Location->output_join_page( $EM_Booking ) ){
			return true;
		}
		$url = $EM_Event_Location->resolve_join_url( $EM_Booking );
		if( empty( $url ) ){
			return static::error('em_join_not_ready', 503, $EM_Event, $EM_Booking, $window);
		}
		wp_redirect( $url, 302, 'Events Manager' );
		return true;
	}

	protected static function error( $code, $status, $EM_Event = null, $EM_Booking = null, $window = array(), $message = '' ){
		return new WP_Error( $code, $message !== '' ? $message : static::get_error_message( $code ), array(
			'status' => $status,
			'event' => $EM_Event,
			'booking' => $EM_Booking,
			'window' => $window,
		));
	}

	public static function get_error_message( $code ){
		$messages = array(
			'em_join_invalid' => __('This join link is not valid. Please use the link from your booking confirmation.', 'events-manager'),
			'em_join_no_location' => __('This event has no virtual location to join.', 'events-manager'),
			'em_join_no_access' => __('Your booking does not include virtual access to this event, or it is not confirmed yet.', 'events-manager'),
			'em_join_too_early' => __('This event is not open for joining yet.', 'events-manager'),
			'em_join_closed' => __('This event has ended and can no longer be joined.', 'events-manager'),
			'em_join_not_ready' => __('The virtual location for this event is not ready yet. Please try again shortly.', 'events-manager'),
		);
		$message = !empty( $messages[ $code ] ) ? $messages[ $code ] : $messages['em_join_invalid'];
		return apply_filters('em_event_location_join_error_message', $message, $code);
	}

	/**
	 * @param WP_Error $error
	 */
	public static function output_error( $error ){
		$data = $error->get_error_data();
		ob_start();
		em_locate_template('templates/event-location-join.php', true, array(
			'error' => $error,
			'EM_Event' => $data['event'],
			'EM_Booking' => $data['booking'],
			'window' => $data['window'],
		));
		$html = ob_get_clean();
		$title = $data['event'] instanceof \EM_Event ? $data['event']->event_name : get_bloginfo('name');
		wp_die( $html, esc_html( $title ), array( 'response' => $data['status'], 'back_link' => false ) );
	}
}
Join_Gate::init();
