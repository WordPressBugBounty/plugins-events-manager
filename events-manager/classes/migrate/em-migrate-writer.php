<?php
namespace EM\Migrate;

use EM_Event, EM_Location, EM_Ticket, EM_Booking, EM_Person, EM_Ticket_Booking;

/**
 * Creates EM objects from plain arrays through EM's own classes, working around the setters and save paths that silently drop imported values (owner, slug, post dates, timezone, booking date and price).
 * Source-agnostic, so every migration source shares one tested way of writing into EM.
 */
class Writer {

	/**
	 * Post fields EM's save() never passes to wp_insert_post, applied to the next insert or update of the given post type.
	 */
	protected static $post_overrides = array();

	public static function with_post_overrides( $post_type, array $overrides, callable $fn ) {
		$overrides = array_filter( $overrides, function( $v ) { return null !== $v && '' !== $v; } );
		static::$post_overrides[ $post_type ] = $overrides;
		add_filter( 'wp_insert_post_data', '\EM\Migrate\Writer::apply_post_overrides', 999, 2 );
		try {
			return $fn();
		} finally {
			unset( static::$post_overrides[ $post_type ] );
			remove_filter( 'wp_insert_post_data', '\EM\Migrate\Writer::apply_post_overrides', 999 );
		}
	}

	public static function apply_post_overrides( $data, $postarr ) {
		if ( empty( static::$post_overrides[ $data['post_type'] ] ) ) {
			return $data;
		}
		$o = static::$post_overrides[ $data['post_type'] ];
		foreach ( array( 'post_author', 'post_date', 'post_date_gmt', 'comment_status', 'ping_status', 'post_password', 'menu_order' ) as $field ) {
			if ( isset( $o[ $field ] ) ) {
				$data[ $field ] = $o[ $field ];
			}
		}
		if ( isset( $o['post_name'] ) ) {
			$data['post_name'] = wp_unique_post_slug( sanitize_title( $o['post_name'] ), $postarr['ID'] ?? 0, $data['post_status'], $data['post_type'], $data['post_parent'] ?? 0 );
		}
		// a past post_date on a published event must not turn it into a scheduled post, and a future one must not publish early
		if ( isset( $o['post_status'] ) ) {
			$data['post_status'] = $o['post_status'];
		}
		return $data;
	}

	/**
	 * @param array $d name, content, address, town, state, postcode, region, country (ISO), latitude, longitude, status, author, slug, post_date, post_date_gmt, thumbnail_id, attributes
	 * @return EM_Location|\WP_Error
	 */
	public static function save_location( array $d, ?EM_Location $EM_Location = null ) {
		$EM_Location = $EM_Location ?: new EM_Location();
		$EM_Location->location_name = $d['name'];
		$EM_Location->post_content = $d['content'] ?? '';
		foreach ( array( 'address', 'town', 'state', 'postcode', 'region', 'country' ) as $field ) {
			$EM_Location->{'location_' . $field} = isset( $d[ $field ] ) ? (string) $d[ $field ] : '';
		}
		// 0/0 is a real coordinate to EM, so only set what the source actually geocoded
		if ( isset( $d['latitude'], $d['longitude'] ) && '' !== $d['latitude'] && '' !== $d['longitude'] && is_numeric( $d['latitude'] ) && is_numeric( $d['longitude'] ) ) {
			$EM_Location->location_latitude = round( (float) $d['latitude'], 6 );
			$EM_Location->location_longitude = round( (float) $d['longitude'], 6 );
		}
		$status = $d['status'] ?? 'publish';
		$EM_Location->force_status = $status;
		if ( ! empty( $d['author'] ) ) {
			$EM_Location->location_owner = (int) $d['author'];
		}
		$saved = static::with_post_overrides( EM_POST_TYPE_LOCATION, array(
			'post_author' => $d['author'] ?? null,
			'post_name' => $d['slug'] ?? null,
			'post_date' => $d['post_date'] ?? null,
			'post_date_gmt' => $d['post_date_gmt'] ?? null,
			'post_status' => $status,
		), function() use ( $EM_Location ) {
			return $EM_Location->save();
		} );
		if ( ! $saved || ! $EM_Location->location_id ) {
			return new \WP_Error( 'em_migrate_location', implode( ' ', (array) $EM_Location->get_errors() ) ?: 'Location could not be saved.' );
		}
		if ( ! empty( $d['thumbnail_id'] ) && get_post( $d['thumbnail_id'] ) ) {
			set_post_thumbnail( $EM_Location->post_id, (int) $d['thumbnail_id'] );
		}
		static::save_plain_meta( $EM_Location->post_id, $d['attributes'] ?? array() );
		return $EM_Location;
	}

	/**
	 * Attributes as plain (non-underscore) post meta: EM loads these into event/location attributes and #_ATT{} outputs them, whether or not the name appears in a format, and EM's own saves leave unregistered names alone.
	 */
	public static function save_plain_meta( $post_id, array $attributes ) {
		foreach ( $attributes as $name => $value ) {
			$name = trim( (string) $name );
			if ( '' === $name || '_' === $name[0] ) {
				continue;
			}
			if ( '' === $value || null === $value ) {
				delete_post_meta( $post_id, $name );
			} else {
				update_post_meta( $post_id, $name, $value );
			}
		}
	}

	/**
	 * Registers the category, tag and organizer taxonomies that are enabled now but were not when this request loaded, so a run that has just switched them on can use them straight away.
	 */
	public static function ensure_taxonomies() {
		$types = array_filter( array( EM_POST_TYPE_EVENT, defined( 'EM_POST_TYPE_EVENT_RECURRING' ) ? EM_POST_TYPE_EVENT_RECURRING : null ) );
		$defs = array();
		if ( get_option( 'dbem_categories_enabled' ) ) {
			$defs[ EM_TAXONOMY_CATEGORY ] = array( 'taxonomy' => EM_TAXONOMY_CATEGORY, 'shortname' => 'categories', 'label' => __( 'Event Categories', 'events-manager' ), 'singular_label' => __( 'Event Category', 'events-manager' ), 'slug' => EM_TAXONOMY_CATEGORY_SLUG );
		}
		if ( get_option( 'dbem_tags_enabled' ) ) {
			$defs[ EM_TAXONOMY_TAG ] = array( 'taxonomy' => EM_TAXONOMY_TAG, 'shortname' => 'tags', 'label' => __( 'Event Tags', 'events-manager' ), 'singular_label' => __( 'Event Tag', 'events-manager' ), 'slug' => EM_TAXONOMY_TAG_SLUG, 'hierarchical' => false );
		}
		if ( get_option( 'dbem_organizers_enabled' ) ) {
			$defs[ \EM\Organizers::TAXONOMY ] = array( 'taxonomy' => \EM\Organizers::TAXONOMY, 'shortname' => 'organizers', 'label' => __( 'Organizers', 'events-manager' ), 'singular_label' => __( 'Organizer', 'events-manager' ), 'slug' => get_option( 'dbem_taxonomy_organizer_slug', 'events/organizers' ), 'hierarchical' => false );
		}
		foreach ( $defs as $taxonomy => $def ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				\EM\Taxonomies::register_taxonomy( $def );
			}
			foreach ( $types as $type ) {
				register_taxonomy_for_object_type( $taxonomy, $type );
			}
		}
	}

	/**
	 * Appends an [event] or [location] shortcode listing imported details line by line, so fields Events Manager has no column for show without changing the site's formats. A line's value is an attribute name's own value, or a placeholder when it starts with #_.
	 */
	public static function append_details_block( $content, array $lines, $type = 'event' ) {
		$content = preg_replace( '/\s*<!-- em-migrate-details -->.*?<!-- \/em-migrate-details -->\s*/s', '', (string) $content );
		if ( ! $lines ) {
			return $content;
		}
		$placeholder = 'location' === $type ? '#_LATT' : '#_ATT';
		$rows = array();
		foreach ( $lines as $label => $value ) {
			if ( 0 === strpos( (string) $value, '#_' ) ) {
				$rows[] = esc_html( $label ) . ': ' . $value;
			} else {
				$rows[] = esc_html( $label ) . ': ' . $placeholder . '{' . $label . '}';
			}
		}
		$tag = 'location' === $type ? 'location' : 'event';
		return rtrim( $content ) . "\n\n<!-- em-migrate-details -->\n[" . $tag . ']<p class="em-migrated-details">' . implode( "<br>\n", $rows ) . '</p>[/' . $tag . "]\n<!-- /em-migrate-details -->";
	}

	/**
	 * The [event] and [location] shortcodes only find their object from the global post on that object's own page, so the details block is pointed at its post for lists, feeds and widgets.
	 */
	public static function pin_details_block( $post_id, $type ) {
		global $wpdb;
		$content = get_post_field( 'post_content', $post_id );
		$pinned = preg_replace( '/(<!-- em-migrate-details -->\s*)\[(event|location)(?:\s+post_id="\d+")?\]/', '$1[$2 post_id="' . (int) $post_id . '"]', $content );
		if ( $pinned !== $content ) {
			// a direct write: wp_update_post on an event runs EM's admin save handlers, which can re-validate and draft it
			$wpdb->update( $wpdb->posts, array( 'post_content' => $pinned ), array( 'ID' => $post_id ) );
			clean_post_cache( $post_id );
		}
	}

	/**
	 * Finds the media library image for an attachment ID or a web address, downloading it when it is not in the library yet.
	 *
	 * @return array{id: int, created: bool}|\WP_Error created tells the caller whether rollback may delete it
	 */
	public static function get_image( $value, $download = true ) {
		$value = trim( (string) $value );
		if ( ctype_digit( $value ) ) {
			if ( wp_attachment_is_image( (int) $value ) ) {
				return array( 'id' => (int) $value, 'created' => false );
			}
			return new \WP_Error( 'em_migrate_image', sprintf( __( 'there is no image with ID %d in the media library.', 'events-manager' ), $value ) );
		}
		if ( ! wp_http_validate_url( $value ) ) {
			return new \WP_Error( 'em_migrate_image', sprintf( __( '"%s" is neither a media ID nor a web address that can be reached.', 'events-manager' ), $value ) );
		}
		$existing = attachment_url_to_postid( $value );
		if ( $existing ) {
			return array( 'id' => (int) $existing, 'created' => false );
		}
		if ( ! $download ) {
			return new \WP_Error( 'em_migrate_image', __( 'downloading images is switched off.', 'events-manager' ) );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$id = media_sideload_image( $value, 0, null, 'id' );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		return array( 'id' => (int) $id, 'created' => true );
	}

	/**
	 * @param array $d name, content, excerpt, status, author, slug, post_date, post_date_gmt, comment_status, password,
	 *                 start_date, end_date (Y-m-d), start_time, end_time (H:i:s), all_day, timezone,
	 *                 location_id, event_location (array type, url, text), categories, tags (term ids), organizers (term ids),
	 *                 attributes, thumbnail_id, active_status, rsvp, spaces, recurrence (array sets, see build_recurrence_sets)
	 * @return EM_Event|\WP_Error
	 */
	public static function save_event( array $d ) {
		$EM_Event = new EM_Event();
		$EM_Event->event_name = $d['name'];
		$EM_Event->post_content = $d['content'] ?? '';
		$EM_Event->post_excerpt = $d['excerpt'] ?? '';
		$EM_Event->event_start_date = $d['start_date'];
		$EM_Event->event_end_date = $d['end_date'] ?: $d['start_date'];
		$all_day = ! empty( $d['all_day'] );
		$EM_Event->event_all_day = $all_day ? 1 : 0;
		$EM_Event->event_start_time = $all_day ? '00:00:00' : $d['start_time'];
		$EM_Event->event_end_time = $all_day ? '23:59:59' : $d['end_time'];
		$EM_Event->event_rsvp = ! empty( $d['rsvp'] ) ? 1 : 0;
		if ( isset( $d['spaces'] ) ) {
			$EM_Event->event_spaces = (int) $d['spaces'];
		}
		if ( ! empty( $d['author'] ) ) {
			$EM_Event->event_owner = (int) $d['author'];
		}
		if ( ! empty( $d['location_id'] ) ) {
			$EM_Event->location_id = (int) $d['location_id'];
		} else {
			$EM_Event->location_id = 0;
		}
		if ( ! empty( $d['event_location']['type'] ) ) {
			$EM_Event->event_location_type = $d['event_location']['type'];
		}
		if ( ! empty( $d['categories'] ) ) {
			$EM_Event->categories = new \EM_Categories( array_map( 'intval', $d['categories'] ) );
		}
		if ( ! empty( $d['recurrence']['sets'] ) ) {
			$EM_Event->event_type = 'recurring';
			static::build_recurrence_sets( $EM_Event, $d['recurrence']['sets'], $d['timezone'] );
		} else {
			$EM_Event->event_type = 'single';
		}
		// last, because it clears the cached UTC start/end the date and time assignments above leave stale
		$EM_Event->set_timezone( $d['timezone'] ?: wp_timezone_string() );
		$status = $d['status'] ?? 'publish';
		$EM_Event->force_status = $status;
		// EM_Event_Post_Admin::save_post leaves this true after saving any other post in an admin or AJAX request, which makes save() skip creating recurrences; this save isn't coming from save_post
		$GLOBALS['EM_EVENT_SAVE_POST'] = false;

		$saved = static::with_post_overrides( EM_POST_TYPE_EVENT, array(
			'post_author' => $d['author'] ?? null,
			'post_name' => $d['slug'] ?? null,
			'post_date' => $d['post_date'] ?? null,
			'post_date_gmt' => $d['post_date_gmt'] ?? null,
			'comment_status' => $d['comment_status'] ?? null,
			'post_password' => $d['password'] ?? null,
			'post_status' => $status,
		), function() use ( $EM_Event ) {
			return $EM_Event->save();
		} );
		if ( ! $saved || ! $EM_Event->event_id ) {
			return new \WP_Error( 'em_migrate_event', implode( ' ', (array) $EM_Event->get_errors() ) ?: 'Event could not be saved.' );
		}
		$post_id = $EM_Event->post_id;
		if ( ! empty( $d['event_location']['type'] ) && $EM_Event->has_event_location() ) {
			$event_location = $EM_Event->get_event_location();
			foreach ( $event_location->properties as $prop ) {
				if ( isset( $d['event_location'][ $prop ] ) ) {
					$event_location->$prop = $d['event_location'][ $prop ];
				}
			}
			$event_location->save();
			// properties this EM version's location type doesn't know yet (the hybrid URL type's private flag) go into the same meta key the type reads
			foreach ( array_diff( array_keys( $d['event_location'] ), $event_location->properties, array( 'type' ) ) as $prop ) {
				update_post_meta( $post_id, '_event_location_' . $d['event_location']['type'] . '_' . $prop, $d['event_location'][ $prop ] );
			}
		}
		if ( ! empty( $d['tags'] ) && taxonomy_exists( EM_TAXONOMY_TAG ) ) {
			wp_set_object_terms( $post_id, array_map( 'intval', $d['tags'] ), EM_TAXONOMY_TAG );
		}
		if ( ! empty( $d['categories'] ) && taxonomy_exists( EM_TAXONOMY_CATEGORY ) ) {
			wp_set_object_terms( $post_id, array_map( 'intval', $d['categories'] ), EM_TAXONOMY_CATEGORY );
		}
		if ( ! empty( $d['organizers'] ) && taxonomy_exists( \EM\Organizers::TAXONOMY ) ) {
			wp_set_object_terms( $post_id, array_map( 'intval', $d['organizers'] ), \EM\Organizers::TAXONOMY );
		}
		if ( ! empty( $d['thumbnail_id'] ) && get_post( $d['thumbnail_id'] ) ) {
			set_post_thumbnail( $post_id, (int) $d['thumbnail_id'] );
		}
		static::save_plain_meta( $post_id, $d['attributes'] ?? array() );
		if ( isset( $d['active_status'] ) && 0 === (int) $d['active_status'] ) {
			$EM_Event->set_active_status( 0 );
		}
		return em_get_event( $EM_Event->event_id );
	}

	/**
	 * @param array $sets each: type (include|exclude), freq (daily|weekly|monthly|yearly|on), interval, byday, byweekno, dates (Y-m-d[]),
	 *                    start_date, end_date, start_time, end_time, duration (extra days), all_day
	 */
	public static function build_recurrence_sets( EM_Event $EM_Event, array $sets, $timezone ) {
		$Sets = $EM_Event->get_recurrence_sets();
		$Sets->include = array();
		$Sets->exclude = array();
		$order = 1;
		foreach ( $sets as $s ) {
			$Set = new \EM\Recurrences\Recurrence_Set();
			$Set->event = $EM_Event;
			$type = ( $s['type'] ?? 'include' ) === 'exclude' ? 'exclude' : 'include';
			$Set->recurrence_type = $type;
			$Set->recurrence_freq = $s['freq'];
			$Set->recurrence_interval = max( 1, (int) ( $s['interval'] ?? 1 ) );
			if ( isset( $s['byday'] ) && '' !== $s['byday'] ) {
				$Set->recurrence_byday = $s['byday'];
			}
			if ( isset( $s['byweekno'] ) ) {
				$Set->recurrence_byweekno = (int) $s['byweekno'];
			}
			if ( 'on' === $s['freq'] ) {
				$dates = array_values( array_unique( $s['dates'] ) );
				sort( $dates );
				$Set->recurrence_dates = $dates;
				$Set->recurrence_start_date = $dates[0];
				$Set->recurrence_end_date = end( $dates );
			} else {
				$Set->recurrence_start_date = $s['start_date'];
				$Set->recurrence_end_date = $s['end_date'];
			}
			if ( isset( $s['start_time'] ) ) {
				$Set->recurrence_start_time = $s['start_time'];
				$Set->recurrence_end_time = $s['end_time'];
			}
			$Set->recurrence_duration = (int) ( $s['duration'] ?? 0 );
			$Set->recurrence_all_day = ! empty( $s['all_day'] ) ? 1 : 0;
			$Set->recurrence_timezone = $timezone;
			$Set->recurrence_status = 1;
			if ( 'include' === $type ) {
				$Set->recurrence_order = $order++;
				$Sets->include[] = $Set;
				if ( 1 === $Set->recurrence_order ) {
					$Sets->default = $Set;
				}
			} else {
				$Set->recurrence_order = 0;
				$Sets->exclude[] = $Set;
			}
		}
	}

	/**
	 * Start (local Y-m-d H:i:s) of every occurrence EM generated for a recurring event, to compare against the source.
	 */
	public static function get_occurrence_starts( $event_id ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT event_start_date, event_start_time, event_end_date, event_end_time FROM ' . EM_EVENTS_TABLE . " WHERE recurrence_set_id IN (SELECT recurrence_set_id FROM " . EM_EVENT_RECURRENCES_TABLE . ' WHERE event_id=%d) AND event_type=%s AND event_status IS NOT NULL ORDER BY event_start_date, event_start_time', $event_id, 'recurrence' ), ARRAY_A );
		return array_map( function( $r ) { return $r['event_start_date'] . ' ' . $r['event_start_time'] . '|' . $r['event_end_date'] . ' ' . $r['event_end_time']; }, $rows );
	}

	/**
	 * @param array $d name, description, price, spaces, min, max, start, end (local Y-m-d H:i:s), members, members_roles, guests, required, order, meta
	 */
	public static function save_ticket( EM_Event $EM_Event, array $d ) {
		$EM_Ticket = new EM_Ticket();
		$EM_Ticket->event_id = $EM_Event->event_id;
		$EM_Ticket->ticket_name = $d['name'];
		$EM_Ticket->ticket_description = $d['description'] ?? '';
		$EM_Ticket->ticket_price = (float) ( $d['price'] ?? 0 );
		$EM_Ticket->ticket_spaces = max( 1, (int) ( $d['spaces'] ?? 10 ) );
		$EM_Ticket->ticket_min = $d['min'] ?? null;
		$EM_Ticket->ticket_max = $d['max'] ?? null;
		$EM_Ticket->ticket_start = $d['start'] ?? null;
		$EM_Ticket->ticket_end = $d['end'] ?? null;
		$EM_Ticket->ticket_members = ! empty( $d['members'] ) ? 1 : 0;
		$EM_Ticket->ticket_members_roles = $d['members_roles'] ?? array();
		$EM_Ticket->ticket_guests = ! empty( $d['guests'] ) ? 1 : 0;
		$EM_Ticket->ticket_required = ! empty( $d['required'] ) ? 1 : 0;
		$EM_Ticket->ticket_order = $d['order'] ?? null;
		$EM_Ticket->ticket_status = 1;
		$EM_Ticket->ticket_meta = $d['meta'] ?? array();
		if ( ! $EM_Ticket->save() ) {
			return new \WP_Error( 'em_migrate_ticket', implode( ' ', (array) $EM_Ticket->get_errors() ) ?: 'Ticket could not be saved.' );
		}
		return $EM_Ticket;
	}

	/**
	 * Gives every date of a recurring event its own child of these parent tickets, through EM's own recurrence save: tickets listed as unchanged are added to any date that lacks them.
	 *
	 * @param EM_Event    $EM_Event recurring parent
	 * @param EM_Ticket[] $tickets  saved parent tickets
	 * @return int[] occurrence event_id => child ticket_id, per parent ticket_id
	 */
	public static function add_occurrence_tickets( EM_Event $EM_Event, array $tickets ) {
		global $wpdb;
		$EM_Event = em_get_event( $EM_Event->event_id );
		$EM_Event->event_rsvp = 1;
		$Sets = $EM_Event->get_recurrence_sets();
		$Sets->booking_updates = array( 'tickets' => array( 'added' => array(), 'modified' => array(), 'deleted' => array(), 'unchanged' => array() ) );
		foreach ( $tickets as $EM_Ticket ) {
			$Sets->booking_updates['tickets']['unchanged'][ $EM_Ticket->ticket_id ] = $EM_Ticket;
		}
		$GLOBALS['EM_EVENT_SAVE_POST'] = false;
		foreach ( $Sets->include as $Set ) {
			$Set->event = $EM_Event;
			$Set->save_recurrences();
		}
		$children = array();
		foreach ( $tickets as $EM_Ticket ) {
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT t.event_id, t.ticket_id FROM ' . EM_TICKETS_TABLE . ' t JOIN ' . EM_EVENTS_TABLE . ' e ON e.event_id=t.event_id WHERE t.ticket_parent=%d ORDER BY e.event_start', $EM_Ticket->ticket_id ) );
			foreach ( $rows as $r ) {
				$children[ $EM_Ticket->ticket_id ][ (int) $r->event_id ] = (int) $r->ticket_id;
			}
		}
		return $children;
	}

	protected static $booking_price = null;

	/**
	 * Saves a historical booking without emails, keeping its original date and amount.
	 *
	 * @param array $d user_id, guest (email, name, first_name, last_name, phone), status, date_gmt, price, tax_rate, comment,
	 *                 tickets (array of array( ticket_id, attendees => array of attendee meta arrays )), meta (booking meta key => value)
	 * @return EM_Booking|\WP_Error
	 */
	public static function save_booking( EM_Event $EM_Event, array $d ) {
		$EM_Booking = new EM_Booking();
		$EM_Booking->event_id = $EM_Event->event_id;
		$EM_Booking->booking_status = (int) $d['status'];
		$EM_Booking->previous_status = (int) $d['status'];
		$EM_Booking->booking_comment = $d['comment'] ?? '';
		if ( isset( $d['rsvp_status'] ) ) {
			$EM_Booking->booking_rsvp_status = (int) $d['rsvp_status'];
		}
		$EM_Booking->booking_tax_rate = isset( $d['tax_rate'] ) ? (float) $d['tax_rate'] : 0;
		$EM_Booking->booking_meta = $d['meta'] ?? array();
		// in admin requests EM preloads the logged-in admin as the booking person, so always set one explicitly
		$user_id = ! empty( $d['user_id'] ) && get_userdata( $d['user_id'] ) ? (int) $d['user_id'] : 0;
		$EM_Booking->person = new EM_Person( $user_id );
		if ( ! $user_id && ! empty( $d['guest'] ) ) {
			$g = $d['guest'];
			$EM_Booking->booking_meta['registration'] = array_filter( array(
				'user_email' => $g['email'] ?? '',
				'user_name' => $g['name'] ?? trim( ( $g['first_name'] ?? '' ) . ' ' . ( $g['last_name'] ?? '' ) ),
				'first_name' => $g['first_name'] ?? '',
				'last_name' => $g['last_name'] ?? '',
				'dbem_phone' => $g['phone'] ?? '',
			), 'strlen' );
		}
		$spaces = 0;
		foreach ( $d['tickets'] as $t ) {
			foreach ( $t['attendees'] as $attendee_meta ) {
				$EM_Ticket_Booking = new EM_Ticket_Booking( array( 'ticket_id' => (int) $t['ticket_id'], 'booking' => $EM_Booking ) );
				$EM_Ticket_Booking->meta = array_filter( (array) $attendee_meta, function( $v ) { return null !== $v && '' !== $v; } );
				$EM_Booking->get_tickets_bookings()->add( $EM_Ticket_Booking );
				$spaces++;
			}
		}
		if ( ! $spaces ) {
			return new \WP_Error( 'em_migrate_booking', 'Booking has no attendees.' );
		}
		$EM_Booking->booking_spaces = $spaces;
		static::$booking_price = isset( $d['price'] ) ? (float) $d['price'] : null;
		if ( null !== static::$booking_price ) {
			// EM recalculates a booking from its ticket lines, so a total that differs from the ticket prices (a series pass date after the first, a discount, a price changed since) is kept as an adjustment
			$difference = round( (float) $EM_Booking->get_tickets_bookings()->get_price() - static::$booking_price, 2 );
			if ( abs( $difference ) >= 0.01 ) {
				$EM_Booking->booking_meta[ $difference > 0 ? 'discounts' : 'surcharges' ][] = array(
					'name' => $d['adjustment_name'] ?? __( 'Difference from the price paid at the time of sale', 'events-manager' ),
					'type' => '#',
					'amount' => abs( $difference ),
					'desc' => '',
					'tax' => 'pre',
					'data' => array( 'migrated' => true ),
				);
			}
		}
		if ( null !== static::$booking_price ) {
			add_filter( 'em_booking_calculate_price', '\EM\Migrate\Writer::historical_price', 999 );
		}
		try {
			$saved = $EM_Booking->save( false );
		} finally {
			remove_filter( 'em_booking_calculate_price', '\EM\Migrate\Writer::historical_price', 999 );
		}
		if ( ! $saved || ! $EM_Booking->booking_id ) {
			return new \WP_Error( 'em_migrate_booking', implode( ' ', (array) $EM_Booking->get_errors() ) ?: 'Booking could not be saved.' );
		}
		global $wpdb;
		$row = array();
		if ( ! empty( $d['date_gmt'] ) ) {
			$row['booking_date'] = $d['date_gmt'];
		}
		if ( null !== static::$booking_price ) {
			$row['booking_price'] = static::$booking_price;
		}
		if ( $row ) {
			$wpdb->update( EM_BOOKINGS_TABLE, $row, array( 'booking_id' => $EM_Booking->booking_id ) );
		}
		static::$booking_price = null;
		return $EM_Booking;
	}

	public static function historical_price( $price ) {
		return null === static::$booking_price ? $price : static::$booking_price;
	}

	/**
	 * Reuses a term with the same slug instead of creating a duplicate; the returned flag tells the caller whether rollback may delete it.
	 *
	 * @return array{term_id: int, created: bool}|\WP_Error
	 */
	public static function save_term( $taxonomy, $name, $slug, $parent = 0, $description = '' ) {
		$existing = get_term_by( 'slug', $slug, $taxonomy );
		if ( $existing ) {
			return array( 'term_id' => (int) $existing->term_id, 'created' => false );
		}
		$result = wp_insert_term( $name, $taxonomy, array( 'slug' => $slug, 'parent' => (int) $parent, 'description' => $description ) );
		if ( is_wp_error( $result ) ) {
			if ( 'term_exists' === $result->get_error_code() ) {
				return array( 'term_id' => (int) $result->get_error_data(), 'created' => false );
			}
			return $result;
		}
		return array( 'term_id' => (int) $result['term_id'], 'created' => true );
	}

	/**
	 * Copies a post's comments and their meta onto another post, keeping threading.
	 */
	public static function copy_comments( $from_post_id, $to_post_id ) {
		$comments = get_comments( array( 'post_id' => $from_post_id, 'status' => 'all', 'orderby' => 'comment_ID', 'order' => 'ASC', 'type' => '' ) );
		$map = array();
		foreach ( $comments as $c ) {
			$data = (array) $c;
			unset( $data['comment_ID'], $data['children'], $data['populated_children'], $data['post_fields'] );
			$data['comment_post_ID'] = $to_post_id;
			$data['comment_parent'] = $c->comment_parent && isset( $map[ $c->comment_parent ] ) ? $map[ $c->comment_parent ] : 0;
			$data['comment_meta'] = array();
			foreach ( get_comment_meta( $c->comment_ID ) as $k => $vals ) {
				$data['comment_meta'][ $k ] = maybe_unserialize( $vals[0] );
			}
			$new_id = wp_insert_comment( wp_slash( $data ) );
			if ( $new_id ) {
				$map[ $c->comment_ID ] = $new_id;
			}
		}
		if ( $map ) {
			wp_update_comment_count_now( $to_post_id );
		}
		return $map;
	}

	/**
	 * Deletes an EM event and, for recurring events, the recurrence set rows EM's own delete leaves in em_event_recurrences.
	 */
	public static function delete_event( $event_id ) {
		global $wpdb;
		$EM_Event = em_get_event( $event_id );
		if ( ! $EM_Event || ! $EM_Event->event_id ) {
			return true;
		}
		$result = $EM_Event->delete( true );
		if ( $result ) {
			$wpdb->delete( EM_EVENT_RECURRENCES_TABLE, array( 'event_id' => (int) $event_id ) );
		}
		return $result;
	}

	/**
	 * Removes one mapped object. Objects the migration reused rather than created (an existing term with the same slug) are left alone.
	 */
	public static function undo( array $row ) {
		if ( ! empty( $row['data']['reused'] ) ) {
			return true;
		}
		$id = (int) $row['em_id'];
		if ( ! $id && ! in_array( $row['em_type'], array( 'option', 'attendee' ), true ) ) {
			return true;
		}
		switch ( $row['em_type'] ) {
			case 'event':
				return static::delete_event( $id );
			case 'location':
				$EM_Location = em_get_location( $id );
				if ( ! $EM_Location || ! $EM_Location->location_id ) {
					return true;
				}
				return $EM_Location->delete( true );
			case 'ticket':
				$EM_Ticket = new EM_Ticket( $id );
				return $EM_Ticket->ticket_id ? (bool) $EM_Ticket->delete() : true;
			case 'booking':
				$EM_Booking = em_get_booking( $id );
				if ( ! $EM_Booking->booking_id ) {
					return true;
				}
				$EM_Booking->manage_override = true;
				if ( defined( 'EM_TICKETS_BOOKINGS_CHECKINS_TABLE' ) ) {
					// Pro's check-in rows aren't removed with their ticket bookings
					global $wpdb;
					$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . EM_TICKETS_BOOKINGS_CHECKINS_TABLE . ' WHERE ticket_booking_id IN (SELECT ticket_booking_id FROM ' . EM_TICKETS_BOOKINGS_TABLE . ' WHERE booking_id=%d)', $id ) );
				}
				return (bool) $EM_Booking->delete();
			case 'term':
				$taxonomy = $row['data']['taxonomy'] ?? '';
				if ( ! $taxonomy || ! term_exists( $id, $taxonomy ) ) {
					return true;
				}
				$result = wp_delete_term( $id, $taxonomy );
				if ( ! is_wp_error( $result ) && in_array( $taxonomy, array( EM_TAXONOMY_CATEGORY, EM_TAXONOMY_TAG ), true ) ) {
					// EM keeps category and tag colors and images in em_meta, which deleting the term leaves behind
					global $wpdb;
					$prefix = EM_TAXONOMY_TAG === $taxonomy ? 'tag-' : 'category-';
					$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . EM_META_TABLE . ' WHERE object_id=%d AND meta_key LIKE %s', $id, $wpdb->esc_like( $prefix ) . '%' ) );
				}
				return ! is_wp_error( $result );
			case 'post_content':
				if ( isset( $row['data']['original'] ) && get_post( $id ) ) {
					return (bool) wp_update_post( array( 'ID' => $id, 'post_content' => wp_slash( $row['data']['original'] ) ) );
				}
				return true;
			case 'option':
				$name = $row['data']['option'] ?? '';
				if ( ! $name ) {
					return true;
				}
				if ( array_key_exists( 'previous', $row['data'] ) && null !== $row['data']['previous'] ) {
					update_option( $name, $row['data']['previous'] );
				} else {
					delete_option( $name );
				}
				return true;
			case 'attendee':
				return true;
			case 'em_meta':
				global $wpdb;
				return false !== $wpdb->delete( EM_META_TABLE, array( 'meta_id' => $id ) );
			case 'attachment':
				return ! get_post( $id ) || (bool) wp_delete_attachment( $id, true );
			case 'woocommerce_item':
				if ( function_exists( 'wc_delete_order_item_meta' ) ) {
					foreach ( array( '_em_booking_id', '_em_event_id', '_em_ticket_id' ) as $key ) {
						wc_delete_order_item_meta( $id, $key );
					}
				}
				return true;
		}
		return true;
	}
}
