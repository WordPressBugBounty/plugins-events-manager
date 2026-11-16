<?php
/**
 * Booking intents: a booking the visitor is still putting together, saved with status 10 so the spaces they picked are held for them while they fill in the rest of the booking form.
 *
 * An intent is created by the booking form's summary refresh once spaces are picked, updated as the selection changes, and converted in place into the real booking when the form is submitted, keeping its row, ID and uuid. It is left out wherever bookings are read unless status 10 is asked for, its spaces count as taken for everyone but its owner until its hold runs out, and an intent left behind is deleted once its hold has run out.
 *
 * Saving or deleting an intent fires em_booking_intent_* hooks instead of the usual booking hooks, and converting one fires the hooks of a new booking once.
 *
 * So nobody can hold a whole event, holds together take at most a set share of an event's spaces, and each visitor may hold an event only so often and only so many events, counted by account or IP address (see check_limits()). Spaces past a limit can still be booked, they are just not held.
 */
class EM_Booking_Intents {

	const STATUS = 10;
	const CRON_HOOK = 'em_booking_intents_expire';
	/** @var int Minutes an intent is kept after its hold runs out, so a visitor submitting right at the deadline still converts their own row rather than racing its deletion. */
	const DELETE_GRACE = 10;

	/** @var int[] Intents owned by the current request, which are never counted against the request's own booking. */
	protected static $excluded = array();

	/** @var EM_Booking|null The intent the submitted booking form was holding, adopted by that booking once it has been validated. */
	protected static $submitting = null;

	/** @var string[] Why the last update of an intent was refused, shown with the booking summary. */
	public static $errors = array();

	/** @var string[] Why the visitor's picked spaces were not held although they can still be booked, shown with the booking summary. */
	public static $notices = array();

	/** @var bool|null */
	protected static $has_column = null;

	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'expire' ) );
		add_action( 'init', array( __CLASS__, 'schedule' ) );
	}

	/**
	 * Whether visitors' spaces are held while they book, which also needs the booking_expires column the plugin upgrade adds.
	 * @return bool
	 */
	public static function enabled() {
		return (bool) em_get_option( 'dbem_bookings_intents' ) && static::has_column();
	}

	/**
	 * How many minutes an intent holds its spaces. Filterable, at least one minute.
	 * @param EM_Booking|null $EM_Booking
	 * @return int
	 */
	public static function hold_minutes( $EM_Booking = null ) {
		return max( 1, (int) apply_filters( 'em_bookings_hold_minutes', (int) em_get_option( 'dbem_bookings_hold_minutes', 15 ), $EM_Booking ) );
	}

	/**
	 * Whether the bookings table has the booking_expires column, cached in an option the table installer clears.
	 * @return bool
	 */
	public static function has_column() {
		if ( static::$has_column === null ) {
			$flag = get_option( 'dbem_bookings_expires_column' );
			if ( $flag === false ) {
				global $wpdb;
				$flag = $wpdb->get_var( 'SHOW COLUMNS FROM ' . EM_BOOKINGS_TABLE . " LIKE 'booking_expires'" ) ? 1 : 0;
				update_option( 'dbem_bookings_expires_column', $flag );
			}
			static::$has_column = (bool) $flag;
		}
		return static::$has_column;
	}

	/**
	 * Forgets the cached column check, after the bookings table was (re)created.
	 */
	public static function reset_column_check() {
		static::$has_column = null;
		delete_option( 'dbem_bookings_expires_column' );
	}

	/**
	 * The secret the booking form sends back with an intent's uuid, proving the request comes from the visitor the intent was created for.
	 * @param string $uuid
	 * @return string
	 */
	public static function token( $uuid ) {
		return hash_hmac( 'sha256', 'em_booking_intent|' . $uuid, wp_salt( 'nonce' ) );
	}

	/**
	 * The booking whose uuid and token were sent, whatever its status, or null when the pair does not match a booking.
	 * @param string $uuid
	 * @param string $token
	 * @return EM_Booking|null
	 */
	public static function find( $uuid, $token ) {
		if ( !is_string( $uuid ) || !is_string( $token ) || !preg_match( '/^[a-zA-Z0-9]{32}$/D', $uuid ) || !hash_equals( static::token( $uuid ), $token ) ) {
			return null;
		}
		global $wpdb;
		$booking_id = $wpdb->get_var( $wpdb->prepare( 'SELECT booking_id FROM ' . EM_BOOKINGS_TABLE . ' WHERE booking_uuid=%s', $uuid ) );
		if ( !$booking_id ) {
			return null;
		}
		$EM_Booking = new EM_Booking( absint( $booking_id ) );
		return $EM_Booking->booking_id ? $EM_Booking : null;
	}

	/**
	 * The booking matching the intent uuid and token in this request, whatever its status.
	 * @return EM_Booking|null
	 */
	public static function from_request() {
		if ( empty( $_REQUEST['booking_intent'] ) || empty( $_REQUEST['booking_intent_token'] ) ) {
			return null;
		}
		return static::find( wp_unslash( $_REQUEST['booking_intent'] ), wp_unslash( $_REQUEST['booking_intent_token'] ) );
	}

	/**
	 * Stops an intent counting against the booking this request is making from it.
	 * @param EM_Booking $EM_Booking
	 */
	public static function exclude( $EM_Booking ) {
		if ( !empty( $EM_Booking->booking_id ) ) {
			static::$excluded[ (int) $EM_Booking->booking_id ] = (int) $EM_Booking->booking_id;
		}
	}

	/**
	 * Forgets the intents this request owns and the one being submitted, for code that handles more than one visitor in a process, such as tests.
	 */
	public static function reset_request() {
		static::$excluded   = array();
		static::$submitting = null;
		static::$errors     = array();
		static::$notices    = array();
	}

	/**
	 * SQL limiting a query on the bookings table to live intents not owned by this request.
	 * @param string $alias table alias, with its dot
	 * @return string
	 */
	public static function live_sql( $alias = '' ) {
		global $wpdb;
		$sql = $alias . 'booking_status=' . self::STATUS . $wpdb->prepare( ' AND ' . $alias . 'booking_expires > %s', gmdate( 'Y-m-d H:i:s' ) );
		if ( static::$excluded ) {
			$sql .= ' AND ' . $alias . 'booking_id NOT IN (' . implode( ',', array_map( 'absint', static::$excluded ) ) . ')';
		}
		return $sql;
	}

	/**
	 * Whether intents can be holding spaces at all, so the availability counts can skip their query otherwise.
	 * @return bool
	 */
	public static function counting() {
		return static::enabled();
	}

	/**
	 * @param EM_Booking $EM_Booking
	 * @return bool
	 */
	public static function is_expired( $EM_Booking ) {
		return empty( $EM_Booking->booking_expires ) || $EM_Booking->booking_expires <= gmdate( 'Y-m-d H:i:s' );
	}

	/**
	 * The UTC timestamp an intent's hold runs out.
	 * @param EM_Booking $EM_Booking
	 * @return int
	 */
	public static function expires_timestamp( $EM_Booking ) {
		return empty( $EM_Booking->booking_expires ) ? 0 : (int) strtotime( $EM_Booking->booking_expires . ' UTC' );
	}

	/**
	 * Creates, updates or releases the visitor's intent from a booking form summary refresh, which runs whenever their selection changes.
	 *
	 * The first refresh with spaces picked creates the intent with a fresh hold, later refreshes replace its tickets but keep the hold's deadline, and a refresh with nothing picked releases it. A selection that is no longer available leaves the intent as it was and the reason is kept in self::$errors for the summary to show. An intent whose hold has run out is replaced by a new one, with a new deadline.
	 *
	 * @param EM_Booking $EM_Booking the booking built from the submitted form by get_post()
	 * @return void
	 */
	public static function sync_summary( $EM_Booking ) {
		static::$errors  = array();
		static::$notices = array();
		if ( !static::enabled() || !wp_verify_nonce( $_REQUEST['_wpnonce'] ?? '', 'booking_add' ) ) {
			return;
		}
		$existing = static::from_request();
		if ( $existing && ( !$existing->is_intent() || (int) $existing->event_id !== (int) $EM_Booking->event_id || (int) $existing->timeslot_id !== (int) $EM_Booking->timeslot_id ) ) {
			// a timeslot or recurrence switch renders a new form, so an intent of this visitor's for another date stops holding it now
			if ( $existing->is_intent() ) {
				static::release( $existing );
			}
			$existing = null;
		}
		if ( $EM_Booking->get_spaces( true ) < 1 ) {
			if ( $existing ) {
				static::release( $existing );
			}
			return;
		}
		if ( $existing ) {
			static::exclude( $existing );
		}
		$event_id = $EM_Booking->get_event_id();
		$locked = EM_Bookings::acquire_lock( $event_id );
		try {
			static::$errors = static::check_availability( $EM_Booking );
			if ( static::$errors ) {
				if ( $existing ) {
					// the reply keeps naming the intent the form already holds, so the next refresh still finds it
					$EM_Booking->booking_id      = $existing->booking_id;
					$EM_Booking->booking_uuid    = $existing->booking_uuid;
					$EM_Booking->booking_expires = $existing->booking_expires;
					$EM_Booking->booking_status  = self::STATUS;
				}
				return;
			}
			$limited = static::check_limits( $EM_Booking );
			if ( $limited ) {
				// past a hold limit the visitor can still book these spaces, they are just not held, so a hold they had goes too rather than holding part of what they picked
				if ( $existing ) {
					static::release( $existing );
				}
				static::$notices[] = $limited;
				return;
			}
			if ( $existing && !static::is_expired( $existing ) ) {
				$expires = $existing->booking_expires;
				static::adopt_into( $EM_Booking, $existing );
			} else {
				if ( $existing ) {
					static::release( $existing );
				}
				$expires = gmdate( 'Y-m-d H:i:s', time() + static::hold_minutes( $EM_Booking ) * MINUTE_IN_SECONDS );
			}
			$EM_Booking->booking_status = self::STATUS;
			$EM_Booking->manage_override = true;
			/**
			 * When an intent's hold runs out, as a UTC MySQL datetime, for example to line it up with other intents the visitor holds in a cart.
			 * @param string $expires
			 * @param EM_Booking $EM_Booking
			 */
			$expires = apply_filters( 'em_booking_intent_expires', $expires, $EM_Booking );
			if ( $EM_Booking->save( false ) ) {
				static::set_expires( $EM_Booking, $expires );
				static::record_owner( $EM_Booking );
				static::exclude( $EM_Booking );
			}
		} finally {
			if ( $locked ) {
				EM_Bookings::release_lock( $event_id );
			}
		}
	}

	/**
	 * Why the spaces picked in a booking cannot be held right now: the space limits a booking of them would be refused for, and every other visitor's hold, not counting this one's own.
	 * @param EM_Booking $EM_Booking
	 * @return string[]
	 */
	public static function check_availability( $EM_Booking ) {
		$errors = array();
		$EM_Event = $EM_Booking->get_event();
		if ( $EM_Event->rsvp_end()->getTimestamp() < time() ) {
			return apply_filters( 'em_booking_intent_availability_errors', array( $EM_Booking->get_option( 'dbem_bookings_form_msg_closed' ) ), $EM_Booking );
		}
		if ( $EM_Event->event_rsvp_spaces > 0 && $EM_Booking->get_spaces() > $EM_Event->event_rsvp_spaces ) {
			$errors[] = sprintf( $EM_Booking->get_option( 'dbem_booking_feedback_spaces_limit' ), $EM_Event->event_rsvp_spaces );
		}
		// the same person EM_Ticket_Bookings::validate() checks a ticket against
		$user = null;
		if ( $EM_Booking->person_id === 0 ) {
			$user = false;
		} elseif ( $EM_Booking->person_id > 0 ) {
			$user = $EM_Booking->get_person();
		}
		foreach ( $EM_Booking->get_tickets_bookings()->tickets_bookings as $EM_Ticket_Bookings ) {
			$spaces = $EM_Ticket_Bookings->get_spaces();
			if ( $spaces < 1 ) {
				continue;
			}
			$EM_Ticket = $EM_Ticket_Bookings->get_ticket();
			if ( $EM_Ticket->max > 0 && $spaces > $EM_Ticket->max ) {
				$errors[] = sprintf( __( 'You cannot book more than %1$d spaces for the ticket %2$s.', 'events-manager' ), $EM_Ticket->max, "'" . $EM_Ticket->name . "'" );
			}
			if ( $EM_Ticket->min > 0 && $spaces < $EM_Ticket->min ) {
				$errors[] = sprintf( __( 'You must book at least %1$d spaces for the ticket %2$s.', 'events-manager' ), $EM_Ticket->min, "'" . $EM_Ticket->name . "'" );
			}
			if ( !$EM_Ticket->is_available( false, false, true, $user ) ) {
				$errors[] = $EM_Booking->get_option( 'dbem_booking_feedback_ticket_unavailable', sprintf( __( 'The ticket %s is no longer available.', 'events-manager' ), "'" . $EM_Ticket->ticket_name . "'" ) );
				continue;
			}
			$available = $EM_Ticket->get_available_spaces();
			if ( $available < $spaces ) {
				if ( $available > 0 ) {
					$errors[] = sprintf( _n( 'Only %1$d space is left for %2$s.', 'Only %1$d spaces are left for %2$s.', $available, 'events-manager' ), $available, $EM_Ticket->ticket_name );
				} else {
					$errors[] = $EM_Booking->get_option( 'dbem_booking_feedback_ticket_unavailable', sprintf( __( 'The ticket %s is no longer available.', 'events-manager' ), "'" . $EM_Ticket->ticket_name . "'" ) );
				}
			}
		}
		if ( !$errors && $EM_Booking->get_event()->get_bookings()->get_available_spaces( true ) < $EM_Booking->get_spaces() ) {
			$errors[] = $EM_Booking->get_option( 'dbem_booking_feedback_full' );
		}
		return apply_filters( 'em_booking_intent_availability_errors', $errors, $EM_Booking );
	}

	/**
	 * The most holds one visitor may have at once, counted by IP address for guests and by account for logged-in users: 'event_ip' and 'event_user' on any one event, 'ip' and 'user' the number of events held. 0 or less is no limit.
	 *
	 * Set with the EM_BOOKING_EVENT_HOLDS_BY_IP, EM_BOOKING_HOLDS_BY_IP, EM_BOOKING_EVENT_HOLDS_BY_USER and EM_BOOKING_HOLDS_BY_USER constants, or the em_booking_intent_limits filter.
	 * @return int[]
	 */
	public static function limits() {
		$limits = array(
			'event_ip'   => defined( 'EM_BOOKING_EVENT_HOLDS_BY_IP' ) ? (int) EM_BOOKING_EVENT_HOLDS_BY_IP : 1,
			'ip'         => defined( 'EM_BOOKING_HOLDS_BY_IP' ) ? (int) EM_BOOKING_HOLDS_BY_IP : 2,
			'event_user' => defined( 'EM_BOOKING_EVENT_HOLDS_BY_USER' ) ? (int) EM_BOOKING_EVENT_HOLDS_BY_USER : 1,
			'user'       => defined( 'EM_BOOKING_HOLDS_BY_USER' ) ? (int) EM_BOOKING_HOLDS_BY_USER : 2,
		);
		return array_map( 'intval', apply_filters( 'em_booking_intent_limits', $limits ) );
	}

	/**
	 * The percentage of an event's spaces that all visitors' holds together may take at once.
	 * @return int
	 */
	public static function held_share() {
		$share = em_get_option( 'dbem_bookings_hold_share', 50 );
		return $share === '' || $share === false ? 50 : min( 100, max( 0, (int) $share ) );
	}

	/**
	 * The visitor's IP address, filterable for sites behind a proxy that passes it on in a header.
	 * @return string
	 */
	public static function visitor_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return (string) apply_filters( 'em_booking_intent_ip', $ip );
	}

	/**
	 * How an intent's IP address is stored, so holds can be counted per address without keeping the address itself.
	 * @param string $ip
	 * @return string
	 */
	public static function ip_hash( $ip ) {
		return hash_hmac( 'sha256', 'em_booking_intent_ip|' . $ip, wp_salt( 'nonce' ) );
	}

	/**
	 * Who the current visitor is when counting their holds: their account when logged in, otherwise a hash of their IP address, or empty when neither is known.
	 * @return string
	 */
	public static function owner_key() {
		if ( is_user_logged_in() ) {
			return 'user:' . get_current_user_id();
		}
		$ip = static::visitor_ip();
		return $ip === '' ? '' : 'ip:' . static::ip_hash( $ip );
	}

	/**
	 * Notes who holds an intent, so their holds can be counted.
	 * @param EM_Booking $EM_Booking a saved intent
	 */
	public static function record_owner( $EM_Booking ) {
		global $wpdb;
		$owner = static::owner_key();
		if ( empty( $EM_Booking->booking_id ) || $owner === '' ) {
			return;
		}
		$wpdb->delete( EM_BOOKINGS_META_TABLE, array( 'booking_id' => $EM_Booking->booking_id, 'meta_key' => 'intent_owner' ) );
		$wpdb->insert( EM_BOOKINGS_META_TABLE, array( 'booking_id' => $EM_Booking->booking_id, 'meta_key' => 'intent_owner', 'meta_value' => $owner ) );
	}

	/**
	 * Why the spaces picked in a booking cannot be held for this visitor, although they can still book them: holds already take as much of the event as they may, or the visitor holds as much as they may. Someone who can manage the event's bookings is not limited.
	 *
	 * Counts every live intent except the ones this request owns, so a visitor changing their own hold is not limited by it.
	 *
	 * @param EM_Booking $EM_Booking
	 * @return string the message for the visitor, empty when the spaces may be held
	 */
	public static function check_limits( $EM_Booking ) {
		global $wpdb;
		$EM_Event = $EM_Booking->get_event();
		if ( $EM_Event->can_manage( 'manage_bookings', 'manage_others_bookings' ) ) {
			return '';
		}
		$reason = '';
		$EM_Bookings = $EM_Event->get_bookings();
		$most_held = max( 1, (int) floor( $EM_Bookings->get_spaces() * static::held_share() / 100 ) );
		if ( $EM_Bookings->get_intent_spaces( true ) + $EM_Booking->get_spaces() > $most_held ) {
			$reason = 'share';
		} else {
			$limits = static::limits();
			$owner  = static::owner_key();
			$by     = is_user_logged_in() ? 'user' : 'ip';
			$per_event = $limits[ 'event_' . $by ];
			$per_site  = $limits[ $by ];
			if ( $owner ) {
				$from = $wpdb->prepare( 'FROM ' . EM_BOOKINGS_TABLE . ' b INNER JOIN ' . EM_BOOKINGS_META_TABLE . " m ON m.booking_id=b.booking_id AND m.meta_key='intent_owner' AND m.meta_value=%s WHERE ", $owner ) . static::live_sql( 'b.' );
				if ( $per_event > 0 && (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) ' . $from . ' AND b.event_id=%d', $EM_Booking->event_id ) ) >= $per_event ) {
					$reason = 'event';
				} elseif ( $per_site > 0 && (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(DISTINCT b.event_id) ' . $from . ' AND b.event_id!=%d', $EM_Booking->event_id ) ) >= $per_site ) {
					$reason = 'events';
				}
			}
		}
		$message = $reason ? __( 'These spaces cannot be held for you right now, but you can still book them while they are available.', 'events-manager' ) : '';
		/**
		 * The message telling a visitor their picked spaces are not held, empty to hold them anyway.
		 * @param string $message
		 * @param string $reason 'share' when holds take as much of the event as they may, 'event' when the visitor holds this event as often as they may, 'events' when they hold as many events as they may, or empty
		 * @param EM_Booking $EM_Booking
		 */
		return (string) apply_filters( 'em_booking_intent_limit_message', $message, $reason, $EM_Booking );
	}

	/**
	 * Makes a booking built from a form take over an intent's row, so saving it replaces the intent's tickets.
	 * @param EM_Booking $EM_Booking
	 * @param EM_Booking $intent
	 */
	protected static function adopt_into( $EM_Booking, $intent ) {
		$EM_Booking->booking_id      = $intent->booking_id;
		$EM_Booking->booking_uuid    = $intent->booking_uuid;
		$EM_Booking->booking_expires = $intent->booking_expires;
		$EM_Booking->previous_status = self::STATUS;
		$EM_Booking->intent_adopted  = true;
		$EM_Booking->manage_override = true;
	}

	/**
	 * Picks up the intent a submitted booking form was holding, so its spaces do not count against the booking being made from it.
	 *
	 * Returns false when that intent was already turned into a booking, which means the form was submitted twice.
	 *
	 * @return bool
	 */
	public static function claim_for_submit() {
		static::$submitting = null;
		$found = static::has_column() ? static::from_request() : null;
		if ( !$found ) {
			return true;
		}
		if ( !$found->is_intent() ) {
			return false;
		}
		static::exclude( $found );
		static::$submitting = $found;
		return true;
	}

	/**
	 * Converts the claimed intent in place: the validated booking takes over the intent's row, ID and uuid, and saving it then turns the intent into this booking.
	 *
	 * An intent claimed for a different event or timeslot is left to run out.
	 *
	 * @param EM_Booking $EM_Booking
	 * @return bool whether the booking now converts an intent
	 */
	public static function adopt( $EM_Booking ) {
		$intent = static::$submitting;
		static::$submitting = null;
		if ( !$intent || !empty( $EM_Booking->booking_id ) || (int) $intent->event_id !== (int) $EM_Booking->event_id || (int) $intent->timeslot_id !== (int) $EM_Booking->timeslot_id ) {
			return false;
		}
		static::adopt_into( $EM_Booking, $intent );
		return true;
	}

	/**
	 * Deletes an intent, giving its spaces back.
	 * @param EM_Booking $EM_Booking
	 * @return bool
	 */
	public static function release( $EM_Booking ) {
		if ( empty( $EM_Booking->booking_id ) || !$EM_Booking->is_intent() ) {
			return false;
		}
		$EM_Booking->manage_override = true;
		unset( static::$excluded[ (int) $EM_Booking->booking_id ] );
		return (bool) $EM_Booking->delete();
	}

	/**
	 * @param EM_Booking $EM_Booking
	 * @param string $expires UTC MySQL datetime
	 */
	public static function set_expires( $EM_Booking, $expires ) {
		global $wpdb;
		$wpdb->update( EM_BOOKINGS_TABLE, array( 'booking_expires' => $expires ), array( 'booking_id' => $EM_Booking->booking_id ), array( '%s' ), array( '%d' ) );
		$EM_Booking->booking_expires = $expires;
	}

	/**
	 * Clears the deadline of a booking that is no longer an intent.
	 * @param EM_Booking $EM_Booking
	 */
	public static function clear_expires( $EM_Booking ) {
		global $wpdb;
		if ( static::has_column() && !empty( $EM_Booking->booking_id ) ) {
			$wpdb->query( $wpdb->prepare( 'UPDATE ' . EM_BOOKINGS_TABLE . ' SET booking_expires=NULL WHERE booking_id=%d', $EM_Booking->booking_id ) );
			$wpdb->delete( EM_BOOKINGS_META_TABLE, array( 'booking_id' => $EM_Booking->booking_id, 'meta_key' => 'intent_owner' ) );
		}
		unset( $EM_Booking->booking_meta['intent_owner'] );
		$EM_Booking->booking_expires = null;
	}

	/**
	 * The hidden token field sent back with a persisted intent's uuid.
	 * @param EM_Booking $EM_Booking
	 * @return string
	 */
	public static function output_token_html( $EM_Booking ) {
		return '<input type="hidden" name="booking_intent_token" value="' . esc_attr( static::token( $EM_Booking->booking_uuid ) ) . '" class="em-booking-intent-token" data-uuid="' . esc_attr( $EM_Booking->booking_uuid ) . '">';
	}

	/**
	 * Why the last intent update was refused, for the booking summary.
	 * @return string
	 */
	public static function output_errors() {
		if ( !static::$errors && !static::$notices ) {
			return '';
		}
		// a notice that spaces are not held goes in the same place as a refusal, marked so the form shows it as a notice rather than an error
		$html = static::$errors ? '<div class="em-booking-intent-errors">' : '<div class="em-booking-intent-errors em-booking-intent-notice">';
		foreach ( static::$errors ?: static::$notices as $error ) {
			$html .= '<p>' . esc_html( $error ) . '</p>';
		}
		return $html . '</div>';
	}

	public static function cron_schedules( $schedules ) {
		if ( empty( $schedules['em_minute'] ) ) {
			$schedules['em_minute'] = array( 'interval' => 60, 'display' => __( 'Every Minute', 'events-manager' ) );
		}
		return $schedules;
	}

	public static function schedule() {
		$next = wp_next_scheduled( self::CRON_HOOK );
		if ( static::enabled() ) {
			if ( !$next ) {
				wp_schedule_event( time(), 'em_minute', self::CRON_HOOK );
			}
		} elseif ( $next ) {
			wp_unschedule_event( $next, self::CRON_HOOK );
		}
	}

	/**
	 * Deletes intents whose hold ran out a while ago, under their event's booking lock so a visitor submitting at that moment is not undercut.
	 * @param int $limit
	 * @return int how many were deleted
	 */
	public static function expire( $limit = 200 ) {
		if ( !static::has_column() ) {
			return 0;
		}
		global $wpdb;
		$cut_off = gmdate( 'Y-m-d H:i:s', time() - self::DELETE_GRACE * MINUTE_IN_SECONDS );
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT booking_id, event_id FROM ' . EM_BOOKINGS_TABLE . ' WHERE booking_status=%d AND ( booking_expires IS NULL OR booking_expires <= %s ) ORDER BY booking_id LIMIT %d', self::STATUS, $cut_off, $limit ), ARRAY_A );
		$deleted = 0;
		foreach ( $rows as $row ) {
			$locked = EM_Bookings::acquire_lock( (int) $row['event_id'] );
			try {
				$EM_Booking = new EM_Booking( (int) $row['booking_id'] );
				if ( $EM_Booking->booking_id && $EM_Booking->is_intent() && static::release( $EM_Booking ) ) {
					$deleted++;
				}
			} finally {
				if ( $locked ) {
					EM_Bookings::release_lock( (int) $row['event_id'] );
				}
			}
		}
		return $deleted;
	}
}
EM_Booking_Intents::init();
