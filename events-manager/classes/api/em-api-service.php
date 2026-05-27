<?php
namespace EM\API;

class Service {

	public static function list_events( $params = array() ) {
		$params = Utils::normalize_input( $params );
		$accepted = array( 'search', 'scope', 'status', 'active_status', 'cancelled', 'active', 'category', 'tag', 'location', 'location_id', 'town', 'state', 'country', 'region', 'near', 'near_unit', 'near_distance', 'event_type', 'event_archetype', 'orderby', 'order', 'blog', 'private', 'private_only', 'timeslots' );
		$args = Utils::collection_args( $params, Utils::pick_search_args( $params, $accepted ) );
		if ( empty( $params['context'] ) || $params['context'] !== 'edit' || !current_user_can( 'edit_others_events' ) ) {
			$args['private'] = current_user_can( 'read_private_events' );
			if ( empty( $params['status'] ) ) {
				$args['status'] = 1;
			}
		}
		$events = \EM_Events::get( $args );
		$items = array();
		foreach ( $events as $EM_Event ) {
			$items[] = static::prepare_event( $EM_Event, $params['context'] ?? 'view' );
		}
		return array(
			'items' => $items,
			'pagination' => Utils::pagination( \EM_Events::$num_rows_found, $args['page'], $args['limit'] ),
		);
	}

	public static function get_event( $id, $context = 'view' ) {
		$EM_Event = em_get_event( $id );
		if ( !$EM_Event || !$EM_Event->get_id() ) {
			return Utils::error( 'em_api_event_not_found', __( 'Event not found.', 'events-manager' ), 404 );
		}
		if ( !static::can_read_event( $EM_Event, $context ) ) {
			return Utils::error( 'em_api_event_forbidden', __( 'You do not have permission to view this event.', 'events-manager' ), 403 );
		}
		return static::prepare_event( $EM_Event, $context );
	}

	public static function get_event_availability( $id ) {
		$EM_Event = em_get_event( $id );
		if ( !$EM_Event || !$EM_Event->get_id() ) {
			return Utils::error( 'em_api_event_not_found', __( 'Event not found.', 'events-manager' ), 404 );
		}
		if ( !static::can_read_event( $EM_Event ) ) {
			return Utils::error( 'em_api_event_forbidden', __( 'You do not have permission to view this event.', 'events-manager' ), 403 );
		}
		$EM_Bookings = $EM_Event->get_bookings();
		$tickets = array();
		foreach ( $EM_Event->get_tickets() as $EM_Ticket ) {
			$tickets[] = array(
				'id' => absint( $EM_Ticket->ticket_id ),
				'name' => $EM_Ticket->ticket_name,
				'description' => $EM_Ticket->ticket_description,
				'price' => $EM_Ticket->get_price(),
				'spaces' => $EM_Ticket->get_spaces(),
				'available_spaces' => $EM_Ticket->get_available_spaces(),
				'available' => $EM_Ticket->is_available(),
			);
		}
		return apply_filters( 'em_api_event_availability', array(
			'event_id' => $EM_Event->get_event_uid(),
			'bookings_enabled' => !empty( $EM_Event->event_rsvp ),
			'open' => $EM_Bookings->is_open(),
			'spaces' => $EM_Bookings->get_spaces(),
			'available_spaces' => $EM_Bookings->get_available_spaces(),
			'tickets' => $tickets,
		), $EM_Event );
	}

	public static function create_event( $data ) {
		return static::save_event( $data );
	}

	public static function update_event( $id, $data ) {
		return static::save_event( $data, $id );
	}

	public static function save_event( $data, $id = 0 ) {
		$data = Utils::normalize_input( $data );
		$EM_Event = $id ? em_get_event( $id ) : new \EM_Event();
		if ( $id && ( !$EM_Event || !$EM_Event->get_id() ) ) {
			return Utils::error( 'em_api_event_not_found', __( 'Event not found.', 'events-manager' ), 404 );
		}
		if ( !$EM_Event->can_manage( 'edit_events', 'edit_others_events' ) ) {
			return Utils::object_error( 'em_api_event_forbidden', $EM_Event, __( 'You do not have permission to save this event.', 'events-manager' ), 403 );
		}
		if ( !empty( $data['em_tickets'] ) && !$EM_Event->can_manage( 'manage_bookings', 'manage_others_bookings' ) ) {
			return Utils::object_error( 'em_api_ticket_forbidden', $EM_Event, __( 'You do not have permission to manage tickets for this event.', 'events-manager' ), 403 );
		}
		static::apply_consent_to_cpt( $EM_Event, $data );
		do_action( 'em_api_event_save_pre', $EM_Event, $data, $id );
		// For partial updates, pre-load current event state so EM_Event::get_post()
		// (which is destructive on missing $_POST keys) doesn't wipe untouched fields.
		$base = $id ? $EM_Event->to_request_data() : array();
		// Translate API-friendly flat times to the nested event_timeranges[0] shape EM expects.
		$data = static::translate_event_input( $data );
		$request_data = static::normalize_em_tickets_keys( array_merge( $base, $data ) );
		$valid = Utils::with_request_data( $request_data, function() use ( $EM_Event, $data ) {
			if ( !$EM_Event->get_post( true ) ) {
				return false;
			}
			static::apply_force_status( $EM_Event, $data, 'event' );
			return true;
		} );
		if ( !$valid ) {
			return Utils::object_error( 'em_api_event_invalid', $EM_Event, __( 'Event data is invalid.', 'events-manager' ), 400 );
		}
		if ( !$EM_Event->save() ) {
			return Utils::object_error( 'em_api_event_save_failed', $EM_Event, __( 'Event could not be saved.', 'events-manager' ), 500 );
		}
		static::save_event_terms( $EM_Event, $data );
		do_action( 'em_api_event_save', $EM_Event, $data, $id );
		return static::prepare_event( $EM_Event, 'edit' );
	}

	public static function delete_event( $id, $force = false ) {
		$EM_Event = em_get_event( $id );
		if ( !$EM_Event || !$EM_Event->get_id() ) {
			return Utils::error( 'em_api_event_not_found', __( 'Event not found.', 'events-manager' ), 404 );
		}
		if ( !$EM_Event->can_manage( 'delete_events', 'delete_others_events' ) ) {
			return Utils::object_error( 'em_api_event_forbidden', $EM_Event, __( 'You do not have permission to delete this event.', 'events-manager' ), 403 );
		}
		$previous = static::prepare_event( $EM_Event, 'edit' );
		if ( !$EM_Event->delete( Utils::is_truthy( $force ) ) ) {
			return Utils::object_error( 'em_api_event_delete_failed', $EM_Event, __( 'Event could not be deleted.', 'events-manager' ), 500 );
		}
		return array( 'deleted' => true, 'previous' => $previous );
	}

	public static function list_event_tickets( $id, $params = array() ) {
		$EM_Event = em_get_event( $id );
		if ( !$EM_Event || !$EM_Event->get_id() ) {
			return Utils::error( 'em_api_event_not_found', __( 'Event not found.', 'events-manager' ), 404 );
		}
		$params = Utils::normalize_input( $params );
		if ( !static::can_read_event( $EM_Event, $params['context'] ?? 'view' ) ) {
			return Utils::error( 'em_api_event_forbidden', __( 'You do not have permission to view this event.', 'events-manager' ), 403 );
		}
		$items = array();
		foreach ( $EM_Event->get_tickets( true ) as $EM_Ticket ) {
			$items[] = static::prepare_ticket( $EM_Ticket, $params['context'] ?? 'view' );
		}
		return array(
			'items' => $items,
			'pagination' => Utils::pagination( count( $items ), 1, max( 1, count( $items ) ) ),
		);
	}

	public static function get_ticket( $id, $context = 'view' ) {
		$EM_Ticket = static::get_ticket_object( $id );
		if ( is_wp_error( $EM_Ticket ) ) return $EM_Ticket;
		if ( !static::can_read_event( $EM_Ticket->get_event(), $context ) ) {
			return Utils::error( 'em_api_ticket_forbidden', __( 'You do not have permission to view this ticket.', 'events-manager' ), 403 );
		}
		return static::prepare_ticket( $EM_Ticket, $context );
	}

	public static function create_event_ticket( $event_id, $data ) {
		$EM_Event = em_get_event( $event_id );
		if ( !$EM_Event || !$EM_Event->get_id() ) {
			return Utils::error( 'em_api_event_not_found', __( 'Event not found.', 'events-manager' ), 404 );
		}
		if ( !$EM_Event->can_manage( 'manage_bookings', 'manage_others_bookings' ) ) {
			return Utils::object_error( 'em_api_ticket_forbidden', $EM_Event, __( 'You do not have permission to manage tickets for this event.', 'events-manager' ), 403 );
		}
		$data = Utils::normalize_input( $data );
		unset( $data['id'], $data['ticket_id'] );
		$data['event_id'] = $EM_Event->get_event_id();
		$EM_Ticket = new \EM_Ticket();
		$EM_Ticket->event = $EM_Event;
		$EM_Ticket->get_post( $data );
		if ( !$EM_Event->event_rsvp ) {
			$EM_Event->event_rsvp = 1;
			$EM_Event->save();
		}
		if ( !$EM_Ticket->save() ) {
			return Utils::object_error( 'em_api_ticket_save_failed', $EM_Ticket, __( 'Ticket could not be saved.', 'events-manager' ), 400 );
		}
		return static::prepare_ticket( $EM_Ticket, 'edit' );
	}

	public static function update_ticket( $id, $data ) {
		$EM_Ticket = static::get_ticket_object( $id );
		if ( is_wp_error( $EM_Ticket ) ) return $EM_Ticket;
		if ( !$EM_Ticket->can_manage() ) {
			return Utils::object_error( 'em_api_ticket_forbidden', $EM_Ticket, __( 'You do not have permission to update this ticket.', 'events-manager' ), 403 );
		}
		$EM_Ticket->get_post( Utils::normalize_input( $data ) );
		if ( !$EM_Ticket->save() ) {
			return Utils::object_error( 'em_api_ticket_save_failed', $EM_Ticket, __( 'Ticket could not be saved.', 'events-manager' ), 400 );
		}
		return static::prepare_ticket( $EM_Ticket, 'edit' );
	}

	public static function delete_ticket( $id, $force = false ) {
		$EM_Ticket = static::get_ticket_object( $id );
		if ( is_wp_error( $EM_Ticket ) ) return $EM_Ticket;
		if ( !$EM_Ticket->can_manage() ) {
			return Utils::object_error( 'em_api_ticket_forbidden', $EM_Ticket, __( 'You do not have permission to delete this ticket.', 'events-manager' ), 403 );
		}
		$previous = static::prepare_ticket( $EM_Ticket, 'edit' );
		if ( $EM_Ticket->get_bookings_count( false, true ) > 0 ) {
			return Utils::error( 'em_api_ticket_has_bookings', __( 'Tickets with bookings cannot be deleted.', 'events-manager' ), 409, array( 'previous' => $previous ) );
		}
		if ( !$EM_Ticket->delete() ) {
			return Utils::object_error( 'em_api_ticket_delete_failed', $EM_Ticket, __( 'Ticket could not be deleted.', 'events-manager' ), 500 );
		}
		return array( 'deleted' => true, 'previous' => $previous );
	}

	public static function list_locations( $params = array() ) {
		$params = Utils::normalize_input( $params );
		$accepted = array( 'search', 'scope', 'status', 'event_status', 'eventful', 'eventless', 'category', 'tag', 'town', 'state', 'country', 'region', 'near', 'near_unit', 'near_distance', 'orderby', 'order', 'blog', 'private', 'private_only' );
		$args = Utils::collection_args( $params, Utils::pick_search_args( $params, $accepted ) );
		if ( empty( $params['context'] ) || $params['context'] !== 'edit' || !current_user_can( 'edit_others_locations' ) ) {
			$args['private'] = current_user_can( 'read_private_locations' );
			if ( empty( $params['status'] ) ) {
				$args['status'] = 1;
			}
		}
		$locations = \EM_Locations::get( $args );
		$items = array();
		foreach ( $locations as $EM_Location ) {
			$items[] = static::prepare_location( $EM_Location, $params['context'] ?? 'view' );
		}
		return array(
			'items' => $items,
			'pagination' => Utils::pagination( \EM_Locations::$num_rows_found, $args['page'], $args['limit'] ),
		);
	}

	public static function get_location( $id, $context = 'view' ) {
		$EM_Location = em_get_location( absint( $id ) );
		if ( !$EM_Location || !$EM_Location->get_id() ) {
			return Utils::error( 'em_api_location_not_found', __( 'Location not found.', 'events-manager' ), 404 );
		}
		if ( !static::can_read_location( $EM_Location, $context ) ) {
			return Utils::error( 'em_api_location_forbidden', __( 'You do not have permission to view this location.', 'events-manager' ), 403 );
		}
		return static::prepare_location( $EM_Location, $context );
	}

	public static function create_location( $data ) {
		return static::save_location( $data );
	}

	public static function update_location( $id, $data ) {
		return static::save_location( $data, $id );
	}

	public static function save_location( $data, $id = 0 ) {
		$data = Utils::normalize_input( $data );
		$EM_Location = $id ? em_get_location( absint( $id ) ) : new \EM_Location();
		if ( $id && ( !$EM_Location || !$EM_Location->get_id() ) ) {
			return Utils::error( 'em_api_location_not_found', __( 'Location not found.', 'events-manager' ), 404 );
		}
		if ( !$EM_Location->can_manage( 'edit_locations', 'edit_others_locations' ) ) {
			return Utils::object_error( 'em_api_location_forbidden', $EM_Location, __( 'You do not have permission to save this location.', 'events-manager' ), 403 );
		}
		static::apply_consent_to_cpt( $EM_Location, $data );
		// For partial updates, pre-load current location state — same reasoning as save_event().
		$base = $id ? $EM_Location->to_request_data() : array();
		$request_data = array_merge( $base, $data );
		$valid = Utils::with_request_data( $request_data, function() use ( $EM_Location, $data ) {
			if ( !$EM_Location->get_post( true ) ) {
				return false;
			}
			static::apply_force_status( $EM_Location, $data, 'location' );
			return true;
		} );
		if ( !$valid ) {
			return Utils::object_error( 'em_api_location_invalid', $EM_Location, __( 'Location data is invalid.', 'events-manager' ), 400 );
		}
		if ( !$EM_Location->save() ) {
			return Utils::object_error( 'em_api_location_save_failed', $EM_Location, __( 'Location could not be saved.', 'events-manager' ), 500 );
		}
		return static::prepare_location( $EM_Location, 'edit' );
	}

	public static function delete_location( $id, $force = false ) {
		$EM_Location = em_get_location( absint( $id ) );
		if ( !$EM_Location || !$EM_Location->get_id() ) {
			return Utils::error( 'em_api_location_not_found', __( 'Location not found.', 'events-manager' ), 404 );
		}
		if ( !$EM_Location->can_manage( 'delete_locations', 'delete_others_locations' ) ) {
			return Utils::object_error( 'em_api_location_forbidden', $EM_Location, __( 'You do not have permission to delete this location.', 'events-manager' ), 403 );
		}
		$previous = static::prepare_location( $EM_Location, 'edit' );
		if ( !$EM_Location->delete( Utils::is_truthy( $force ) ) ) {
			return Utils::object_error( 'em_api_location_delete_failed', $EM_Location, __( 'Location could not be deleted.', 'events-manager' ), 500 );
		}
		return array( 'deleted' => true, 'previous' => $previous );
	}

	public static function list_bookings( $params = array() ) {
		$params = Utils::normalize_input( $params );
		if ( !is_user_logged_in() ) {
			return Utils::error( 'em_api_booking_forbidden', __( 'You must be logged in to view bookings.', 'events-manager' ), 401 );
		}
		$accepted = array( 'search', 'event', 'event_id', 'status', 'rsvp_status', 'person', 'ticket_id', 'booking_id', 'orderby', 'order', 'blog', 'timeslot_id' );
		$args = Utils::collection_args( $params, Utils::pick_search_args( $params, $accepted ) );
		if ( !current_user_can( 'manage_others_bookings' ) && !current_user_can( 'manage_bookings' ) ) {
			$args['person'] = get_current_user_id();
		}
		if ( !empty( $params['event_id'] ) && empty( $args['event'] ) ) {
			$args['event'] = $params['event_id'];
		}
		$bookings = \EM_Bookings::get( $args );
		$items = array();
		foreach ( $bookings as $EM_Booking ) {
			if ( static::can_read_booking( $EM_Booking ) ) {
				$items[] = static::prepare_booking( $EM_Booking, $params['context'] ?? 'view' );
			}
		}
		$total = method_exists( '\EM_Bookings', 'count' ) ? \EM_Bookings::count( $args ) : count( $items );
		return array(
			'items' => $items,
			'pagination' => Utils::pagination( $total, $args['page'], $args['limit'] ),
		);
	}

	public static function get_booking( $id, $context = 'view' ) {
		$EM_Booking = em_get_booking( absint( $id ) );
		if ( !$EM_Booking || !$EM_Booking->booking_id ) {
			return Utils::error( 'em_api_booking_not_found', __( 'Booking not found.', 'events-manager' ), 404 );
		}
		if ( !static::can_read_booking( $EM_Booking ) ) {
			return Utils::error( 'em_api_booking_forbidden', __( 'You do not have permission to view this booking.', 'events-manager' ), 403 );
		}
		return static::prepare_booking( $EM_Booking, $context );
	}

	public static function create_booking( $data ) {
		return static::save_booking( $data );
	}

	public static function update_booking( $id, $data ) {
		return static::save_booking( $data, $id );
	}

	public static function save_booking( $data, $id = 0 ) {
		$data = Utils::normalize_input( $data );
		$EM_Booking = $id ? em_get_booking( absint( $id ) ) : new \EM_Booking();
		if ( !$id ) {
			$filtered_booking = apply_filters( 'em_api_create_booking_object', $EM_Booking, $data, $id );
			if ( $filtered_booking instanceof \EM_Booking ) {
				$EM_Booking = $filtered_booking;
			}
		}
		if ( $id && ( !$EM_Booking || !$EM_Booking->booking_id ) ) {
			return Utils::error( 'em_api_booking_not_found', __( 'Booking not found.', 'events-manager' ), 404 );
		}
		if ( $id && !$EM_Booking->can_manage( 'manage_bookings', 'manage_others_bookings' ) ) {
			return Utils::object_error( 'em_api_booking_forbidden', $EM_Booking, __( 'You do not have permission to save this booking.', 'events-manager' ), 403 );
		}
		if ( !$id && !static::can_create_booking() ) {
			return Utils::error( 'em_api_booking_forbidden', __( 'You do not have permission to create bookings through the API.', 'events-manager' ), 403 );
		}
		$request_data = static::booking_request_data( $data, $EM_Booking );
		$override_availability = !empty( $data['override_availability'] ) && current_user_can( 'manage_bookings' );
		$result = Utils::with_request_data( $request_data, function() use ( $EM_Booking, $override_availability ) {
			return $EM_Booking->get_post( $override_availability );
		} );
		if ( !$result || !$EM_Booking->validate( $override_availability ) ) {
			return Utils::object_error( 'em_api_booking_invalid', $EM_Booking, __( 'Booking data is invalid.', 'events-manager' ), 400 );
		}
		static::assign_booking_person( $EM_Booking, $data );
		if ( !$EM_Booking->person_id && !empty( $EM_Booking->booking_meta['registration'] ) ) {
			if ( empty( $EM_Booking->booking_meta['registration']['user_email'] ) || !is_email( $EM_Booking->booking_meta['registration']['user_email'] ) ) {
				return Utils::error( 'em_api_booking_person_invalid', __( 'A valid guest email address is required for guest bookings.', 'events-manager' ), 400 );
			}
		}
		if ( !$id ) {
			$result = $EM_Booking->get_event()->get_bookings()->add( $EM_Booking );
		} else {
			$result = $EM_Booking->save( !isset( $data['send_email'] ) || Utils::is_truthy( $data['send_email'] ) );
		}
		if ( !$result ) {
			return Utils::object_error( 'em_api_booking_save_failed', $EM_Booking, __( 'Booking could not be saved.', 'events-manager' ), 500 );
		}
		return static::prepare_booking( $EM_Booking, 'edit' );
	}

	public static function set_booking_status( $id, $status, $send_email = true, $ignore_spaces = false ) {
		$EM_Booking = em_get_booking( absint( $id ) );
		if ( !$EM_Booking || !$EM_Booking->booking_id ) {
			return Utils::error( 'em_api_booking_not_found', __( 'Booking not found.', 'events-manager' ), 404 );
		}
		if ( !is_numeric( $status ) || !array_key_exists( absint( $status ), $EM_Booking->status_array ) ) {
			return Utils::error( 'em_api_booking_status_invalid', __( 'Booking status is invalid.', 'events-manager' ), 400 );
		}
		if ( !$EM_Booking->can_manage( 'manage_bookings', 'manage_others_bookings' ) ) {
			return Utils::object_error( 'em_api_booking_forbidden', $EM_Booking, __( 'You do not have permission to change this booking status.', 'events-manager' ), 403 );
		}
		if ( !$EM_Booking->set_status( absint( $status ), Utils::is_truthy( $send_email ), Utils::is_truthy( $ignore_spaces ) ) ) {
			return Utils::object_error( 'em_api_booking_status_failed', $EM_Booking, __( 'Booking status could not be changed.', 'events-manager' ), 500 );
		}
		return static::prepare_booking( $EM_Booking, 'edit' );
	}

	public static function delete_booking( $id ) {
		$EM_Booking = em_get_booking( absint( $id ) );
		if ( !$EM_Booking || !$EM_Booking->booking_id ) {
			return Utils::error( 'em_api_booking_not_found', __( 'Booking not found.', 'events-manager' ), 404 );
		}
		if ( !$EM_Booking->can_manage( 'manage_bookings', 'manage_others_bookings' ) ) {
			return Utils::object_error( 'em_api_booking_forbidden', $EM_Booking, __( 'You do not have permission to delete this booking.', 'events-manager' ), 403 );
		}
		$previous = static::prepare_booking( $EM_Booking, 'edit' );
		if ( !$EM_Booking->delete() ) {
			return Utils::object_error( 'em_api_booking_delete_failed', $EM_Booking, __( 'Booking could not be deleted.', 'events-manager' ), 500 );
		}
		return array( 'deleted' => true, 'previous' => $previous );
	}

	public static function list_terms( $taxonomy, $params = array() ) {
		if ( !taxonomy_exists( $taxonomy ) ) {
			return Utils::error( 'em_api_taxonomy_not_found', __( 'Taxonomy not found.', 'events-manager' ), 404 );
		}
		$params = Utils::normalize_input( $params );
		$page = !empty( $params['page'] ) ? absint( $params['page'] ) : 1;
		$per_page = !empty( $params['per_page'] ) ? min( 100, max( 1, absint( $params['per_page'] ) ) ) : 20;
		$args = array(
			'taxonomy' => $taxonomy,
			'hide_empty' => isset( $params['hide_empty'] ) ? Utils::is_truthy( $params['hide_empty'] ) : false,
			'number' => $per_page,
			'offset' => ( $page - 1 ) * $per_page,
		);
		if ( !empty( $params['search'] ) ) {
			$args['search'] = sanitize_text_field( $params['search'] );
		}
		$terms = get_terms( $args );
		if ( is_wp_error( $terms ) ) return $terms;
		$count_args = $args;
		unset( $count_args['number'], $count_args['offset'] );
		$total = wp_count_terms( $count_args );
		if ( is_wp_error( $total ) ) $total = count( $terms );
		$items = array();
		foreach ( $terms as $term ) {
			$items[] = static::prepare_term( $term, $taxonomy );
		}
		return array(
			'items' => $items,
			'pagination' => Utils::pagination( $total, $page, $per_page ),
		);
	}

	public static function get_term( $taxonomy, $id ) {
		$term = get_term( absint( $id ), $taxonomy );
		if ( !$term || is_wp_error( $term ) ) {
			return Utils::error( 'em_api_term_not_found', __( 'Term not found.', 'events-manager' ), 404 );
		}
		return static::prepare_term( $term, $taxonomy );
	}

	public static function save_term( $taxonomy, $data, $id = 0 ) {
		if ( !static::can_manage_terms() ) {
			return Utils::error( 'em_api_term_forbidden', __( 'You do not have permission to manage event terms.', 'events-manager' ), 403 );
		}
		$data = Utils::normalize_input( $data );
		$args = array();
		if ( isset( $data['slug'] ) ) $args['slug'] = sanitize_title( $data['slug'] );
		if ( isset( $data['description'] ) ) $args['description'] = wp_kses_post( $data['description'] );
		if ( isset( $data['parent'] ) ) $args['parent'] = absint( $data['parent'] );
		$name = !empty( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';
		if ( $id ) {
			$result = wp_update_term( absint( $id ), $taxonomy, array_merge( array_filter( array( 'name' => $name ) ), $args ) );
		} else {
			if ( !$name ) {
				return Utils::error( 'em_api_term_invalid', __( 'Term name is required.', 'events-manager' ), 400 );
			}
			$result = wp_insert_term( $name, $taxonomy, $args );
		}
		if ( is_wp_error( $result ) ) return $result;
		return static::get_term( $taxonomy, $result['term_id'] );
	}

	public static function delete_term( $taxonomy, $id ) {
		if ( !current_user_can( 'delete_event_categories' ) ) {
			return Utils::error( 'em_api_term_forbidden', __( 'You do not have permission to delete event terms.', 'events-manager' ), 403 );
		}
		$previous = static::get_term( $taxonomy, $id );
		if ( is_wp_error( $previous ) ) return $previous;
		$result = wp_delete_term( absint( $id ), $taxonomy );
		if ( is_wp_error( $result ) ) return $result;
		if ( !$result ) {
			return Utils::error( 'em_api_term_delete_failed', __( 'Term could not be deleted.', 'events-manager' ), 500 );
		}
		return array( 'deleted' => true, 'previous' => $previous );
	}

	public static function can_create_booking() {
		$allow_anonymous = em_get_option( 'dbem_bookings_anonymous' ) && apply_filters( 'em_api_allow_anonymous_booking_create', false );
		$allowed = is_user_logged_in() || $allow_anonymous;
		return apply_filters( 'em_api_can_create_booking', $allowed );
	}

	public static function can_manage_terms() {
		return current_user_can( 'edit_event_categories' );
	}

	protected static function can_read_event( $EM_Event, $context = 'view' ) {
		if ( $context === 'edit' ) {
			return $EM_Event->can_manage( 'edit_events', 'edit_others_events' );
		}
		if ( $EM_Event->is_published() && empty( $EM_Event->event_private ) ) {
			return true;
		}
		if ( $EM_Event->event_private && current_user_can( 'read_private_events' ) ) {
			return true;
		}
		return $EM_Event->can_manage( 'edit_events', 'edit_others_events' );
	}

	protected static function can_read_location( $EM_Location, $context = 'view' ) {
		if ( $context === 'edit' ) {
			return $EM_Location->can_manage( 'edit_locations', 'edit_others_locations' );
		}
		if ( $EM_Location->is_published() && empty( $EM_Location->location_private ) ) {
			return true;
		}
		if ( $EM_Location->location_private && current_user_can( 'read_private_locations' ) ) {
			return true;
		}
		return $EM_Location->can_manage( 'edit_locations', 'edit_others_locations' );
	}

	protected static function can_read_booking( $EM_Booking ) {
		if ( !$EM_Booking || !$EM_Booking->booking_id ) return false;
		if ( $EM_Booking->can_manage( 'manage_bookings', 'manage_others_bookings' ) ) return true;
		return is_user_logged_in() && absint( $EM_Booking->person_id ) === get_current_user_id();
	}

	protected static function prepare_event( $EM_Event, $context = 'view' ) {
		$can_manage = $context === 'edit' && $EM_Event->can_manage( 'edit_events', 'edit_others_events' );
		$api = $EM_Event->to_api();
		$api = Utils::strip_private_owner_data( $api, $can_manage && $context === 'edit' );
		if ( $context !== 'edit' ) {
			unset( $api['blog_id'] );
		} else {
			$api['permissions'] = array(
				'edit' => $can_manage,
				'delete' => $EM_Event->can_manage( 'delete_events', 'delete_others_events' ),
			);
		}
		return apply_filters( 'em_api_prepare_event', $api, $EM_Event, $context );
	}

	protected static function prepare_location( $EM_Location, $context = 'view' ) {
		$can_manage = $context === 'edit' && $EM_Location->can_manage( 'edit_locations', 'edit_others_locations' );
		$api = $EM_Location->to_api();
		if ( !$can_manage || $context !== 'edit' ) {
			unset( $api['blog_id'] );
		} else {
			$api['permissions'] = array(
				'edit' => $can_manage,
				'delete' => $EM_Location->can_manage( 'delete_locations', 'delete_others_locations' ),
			);
		}
		return apply_filters( 'em_api_prepare_location', $api, $EM_Location, $context );
	}

	protected static function prepare_booking( $EM_Booking, $context = 'view' ) {
		$args = array( 'event' => $context !== 'embed' );
		$api = $EM_Booking->to_api( $args );
		if ( $context !== 'edit' && !current_user_can( 'manage_bookings' ) && !current_user_can( 'manage_others_bookings' ) ) {
			unset( $api['meta'] );
		}
		if ( $context === 'edit' ) {
			$api['permissions'] = array(
				'edit' => $EM_Booking->can_manage( 'manage_bookings', 'manage_others_bookings' ),
				'delete' => $EM_Booking->can_manage( 'manage_bookings', 'manage_others_bookings' ),
			);
		}
		return apply_filters( 'em_api_prepare_booking', $api, $EM_Booking, $context );
	}

	protected static function prepare_ticket( $EM_Ticket, $context = 'view' ) {
		$api = array(
			'id' => absint( $EM_Ticket->ticket_id ),
			'event_id' => absint( $EM_Ticket->event_id ),
			'name' => $EM_Ticket->name,
			'description' => $EM_Ticket->description,
			'status' => (bool) $EM_Ticket->status,
			'price' => (float) $EM_Ticket->get_price(),
			'spaces' => $EM_Ticket->get_spaces(),
			'available_spaces' => $EM_Ticket->get_available_spaces(),
			'booked_spaces' => $EM_Ticket->get_booked_spaces(),
			'reserved_spaces' => $EM_Ticket->get_reserved_spaces(),
			'pending_spaces' => $EM_Ticket->get_pending_spaces(),
			'min' => $EM_Ticket->min === null ? null : absint( $EM_Ticket->min ),
			'max' => $EM_Ticket->max === null ? null : absint( $EM_Ticket->max ),
			'required' => (bool) $EM_Ticket->required,
			'members' => (bool) $EM_Ticket->members,
			'members_roles' => array_values( (array) $EM_Ticket->members_roles ),
			'guests' => (bool) $EM_Ticket->guests,
			'start' => static::prepare_ticket_datetime( $EM_Ticket->ticket_start ),
			'end' => static::prepare_ticket_datetime( $EM_Ticket->ticket_end ),
			'order' => $EM_Ticket->ticket_order === null ? null : absint( $EM_Ticket->ticket_order ),
			'bookings_count' => $EM_Ticket->ticket_id ? absint( $EM_Ticket->get_bookings_count() ) : 0,
		);
		if ( $context === 'edit' ) {
			$api['meta'] = is_array( $EM_Ticket->ticket_meta ) ? $EM_Ticket->ticket_meta : array();
			$api['permissions'] = array(
				'edit' => $EM_Ticket->can_manage(),
				'delete' => $EM_Ticket->can_manage() && $EM_Ticket->get_bookings_count() === 0,
			);
		}
		return apply_filters( 'em_api_prepare_ticket', $api, $EM_Ticket, $context );
	}

	protected static function prepare_term( $term, $taxonomy ) {
		$class = $taxonomy === EM_TAXONOMY_TAG ? 'EM_Tag' : 'EM_Category';
		$EM_Term = class_exists( $class ) ? new $class( $term ) : false;
		$api = array(
			'id' => absint( $term->term_id ),
			'name' => $term->name,
			'slug' => $term->slug,
			'taxonomy' => $taxonomy,
			'description' => $term->description,
			'parent' => absint( $term->parent ),
			'count' => absint( $term->count ),
		);
		if ( $EM_Term ) {
			$api['color'] = $EM_Term->get_color();
			$api['image_url'] = $EM_Term->get_image_url();
			$api['url'] = $EM_Term->get_url();
		}
		return apply_filters( 'em_api_prepare_term', $api, $term, $taxonomy );
	}

	protected static function get_ticket_object( $id ) {
		$EM_Ticket = \EM_Ticket::get( absint( $id ) );
		if ( !$EM_Ticket || empty( $EM_Ticket->ticket_id ) ) {
			return Utils::error( 'em_api_ticket_not_found', __( 'Ticket not found.', 'events-manager' ), 404 );
		}
		return $EM_Ticket;
	}

	/**
	 * Translate API-friendly flat time fields to the event_timeranges[0] shape EM core expects.
	 *
	 * EM core's EM_Event::get_post_meta() routes start/end times through the timeranges
	 * subsystem, not the flat event_start_time / event_end_time keys. Accept both shapes from
	 * API consumers; rewrite to the canonical nested form before with_request_data().
	 *
	 * Precedence: an explicit event_timeranges in $data wins. Flat fields fill in what's
	 * missing so a partial PATCH (e.g. just event_start_time) doesn't drop the other endpoint.
	 */
	protected static function translate_event_input( $data ) {
		$all_day    = $data['event_all_day'] ?? null;
		$start_time = $data['event_start_time'] ?? null;
		$end_time   = $data['event_end_time'] ?? null;
		if ( $all_day !== null || $start_time !== null || $end_time !== null ) {
			$tr = ( isset( $data['event_timeranges'][0] ) && is_array( $data['event_timeranges'][0] ) )
				? $data['event_timeranges'][0]
				: array();
			if ( Utils::is_truthy( $all_day ) ) {
				$tr['all_day'] = '1';
				unset( $tr['start'], $tr['end'] );
			} else {
				unset( $tr['all_day'] );
				if ( $start_time !== null ) $tr['start'] = $start_time;
				if ( $end_time !== null )   $tr['end']   = $end_time;
			}
			$data['event_timeranges'] = array( 0 => $tr ) + ( $data['event_timeranges'] ?? array() );
		}
		unset( $data['event_start_time'], $data['event_end_time'], $data['event_all_day'] );
		return $data;
	}

	/**
	 * Apply post_status from API payload to an EM CPT object, respecting publish capability.
	 * Used after EM_Event::get_post() / EM_Location::get_post() since force_status isn't
	 * part of the native $_POST contract.
	 */
	protected static function apply_force_status( $EM_Object, $data, $type ) {
		if ( empty( $data['post_status'] ) ) return;
		$status = Utils::sanitize_post_status( $data['post_status'] );
		if ( !$status ) return;
		$cap = $type === 'location' ? 'publish_locations' : 'publish_events';
		if ( current_user_can( $cap ) || $status !== 'publish' ) {
			$EM_Object->force_status = $status;
		}
	}

	/**
	 * EM_Tickets::get_post() skips $_POST['em_tickets'][0] because the EM admin form
	 * reserves row 0 for the JS template. API consumers shouldn't need to know that —
	 * if the payload uses 0-indexed sequential keys, shift everything up by 1.
	 */
	protected static function normalize_em_tickets_keys( $data ) {
		if ( !isset( $data['em_tickets'] ) || !is_array( $data['em_tickets'] ) ) {
			return $data;
		}
		$tickets = $data['em_tickets'];
		if ( array_key_exists( 0, $tickets ) || array_key_exists( '0', $tickets ) ) {
			$shifted = array();
			$i = 1;
			foreach ( $tickets as $row ) {
				$shifted[ $i++ ] = $row;
			}
			$data['em_tickets'] = $shifted;
		}
		return $data;
	}

	protected static function prepare_ticket_datetime( $value ) {
		if ( empty( $value ) || $value === '0000-00-00 00:00:00' ) {
			return null;
		}
		$timestamp = strtotime( $value );
		return $timestamp ? gmdate( 'c', $timestamp ) : $value;
	}

	protected static function save_event_terms( $EM_Event, $data ) {
		if ( empty( $EM_Event->post_id ) ) return;
		// Prefer event_categories / event_tags to match the live event form ($_POST contract).
		// Accept categories / tags as deprecated aliases.
		$categories = $data['event_categories'] ?? $data['categories'] ?? null;
		$tags       = $data['event_tags']       ?? $data['tags']       ?? null;
		if ( $categories !== null && taxonomy_exists( EM_TAXONOMY_CATEGORY ) && current_user_can( 'edit_events' ) ) {
			wp_set_object_terms( $EM_Event->post_id, array_map( 'absint', (array) $categories ), EM_TAXONOMY_CATEGORY );
		}
		if ( $tags !== null && taxonomy_exists( EM_TAXONOMY_TAG ) && current_user_can( 'edit_events' ) ) {
			wp_set_object_terms( $EM_Event->post_id, array_map( 'absint', (array) $tags ), EM_TAXONOMY_TAG );
		}
	}

	/**
	 * Prepare the $_REQUEST/$_POST data for EM_Booking::get_post().
	 *
	 * Field shape matches the live booking form ($_POST contract): flat user_name,
	 * user_email, dbem_phone, dbem_country at top level — no `person` wrapper. EM core
	 * and Pro add-ons read what they need from $_REQUEST directly (coupon_code, waitlist,
	 * donation_amount, etc.) so the payload passes through verbatim. We only gate
	 * admin-only fields (booking_tax_rate, person_id, booking_status, manual_booking*,
	 * payment_*) when the caller is not a booking manager.
	 */
	protected static function booking_request_data( $data, $EM_Booking ) {
		$request = $data;
		$request['event_id'] = $data['event_id'] ?? $data['event'] ?? $EM_Booking->event_id ?? null;
		// Flatten booking-form sub-objects so EM_Form / Pro forms read them from $_REQUEST.
		foreach ( array( 'booking', 'booking_fields', 'fields', 'registration' ) as $key ) {
			if ( !empty( $data[ $key ] ) && is_array( $data[ $key ] ) ) {
				$request = array_merge( $request, $data[ $key ] );
			}
		}
		// Gate admin-only fields when the caller is not a booking manager.
		if ( !$EM_Booking->can_manage() ) {
			foreach ( array( 'booking_tax_rate', 'person_id', 'booking_status',
				'manual_booking', 'manual_booking_confirm', 'manual_booking_override',
				'payment_amount', 'payment_full' ) as $admin_field ) {
				unset( $request[ $admin_field ] );
			}
		}
		return static::apply_consent_to_request_data( $request, $data, 'booking' );
	}

	protected static function assign_booking_person( $EM_Booking, $data ) {
		$can_assign_person = current_user_can( 'manage_bookings' ) || current_user_can( 'manage_others_bookings' );
		if ( isset( $data['person_id'] ) && $can_assign_person ) {
			$EM_Booking->person_id = absint( $data['person_id'] );
		}
		if ( ( !$EM_Booking->person_id || $can_assign_person ) && !empty( $data['person'] ) && is_array( $data['person'] ) ) {
			if ( $can_assign_person || !is_user_logged_in() ) {
				$EM_Booking->person_id = 0;
				$EM_Booking->person = new \EM_Person( 0 );
			}
			$EM_Booking->booking_meta['registration'] = array_merge( $EM_Booking->booking_meta['registration'] ?? array(), array_filter( array(
				'user_name' => !empty( $data['person']['name'] ) ? sanitize_text_field( $data['person']['name'] ) : null,
				'user_email' => !empty( $data['person']['email'] ) ? sanitize_email( $data['person']['email'] ) : null,
				'dbem_phone' => !empty( $data['person']['phone'] ) ? sanitize_text_field( $data['person']['phone'] ) : null,
			) ) );
		}
	}

	protected static function apply_consent_to_request_data( $request, $data, $context ) {
		foreach ( static::get_consent_classes() as $class ) {
			$options = $class::$options;
			if ( empty( $options['param'] ) ) {
				continue;
			}
			if ( array_key_exists( $options['param'], $data ) ) {
				$request[ $options['param'] ] = Utils::is_truthy( $data[ $options['param'] ] ) ? 1 : 0;
			} elseif ( apply_filters( 'em_api_consent_default', true, $context, $class, $data ) ) {
				$request[ $options['param'] ] = 1;
			}
		}
		return $request;
	}

	protected static function apply_consent_to_cpt( $EM_Object, $data ) {
		$attributes = $EM_Object instanceof \EM_Event ? 'event_attributes' : ( $EM_Object instanceof \EM_Location ? 'location_attributes' : '' );
		if ( !$attributes ) {
			return;
		}
		foreach ( static::get_consent_classes() as $class ) {
			$options = $class::$options;
			if ( empty( $options['param'] ) || empty( $options['meta_key'] ) ) {
				continue;
			}
			if ( array_key_exists( $options['param'], $data ) ) {
				$consented = Utils::is_truthy( $data[ $options['param'] ] );
			} else {
				$consented = apply_filters( 'em_api_consent_default', true, $attributes === 'event_attributes' ? 'event' : 'location', $class, $data );
			}
			if ( $consented ) {
				$EM_Object->{$attributes}[ '_' . $options['meta_key'] ] = 1;
			}
		}
	}

	protected static function get_consent_classes() {
		$classes = array_filter( array(
			class_exists( '\EM\Consent\Consent' ) ? '\EM\Consent\Consent' : null,
			class_exists( '\EM\Consent\Privacy' ) ? '\EM\Consent\Privacy' : null,
			class_exists( '\EM\Consent\Comms' ) ? '\EM\Consent\Comms' : null,
		) );
		return apply_filters( 'em_api_consent_classes', $classes );
	}
}
