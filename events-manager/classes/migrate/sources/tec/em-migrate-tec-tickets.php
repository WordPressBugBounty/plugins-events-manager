<?php
namespace EM\Migrate\Sources\TEC;

use EM\Migrate\Job;
use EM\Migrate\Map;
use EM\Migrate\Writer;

/**
 * Event Tickets and Event Tickets Plus: tickets become EM tickets, attendees become EM bookings (one per order and event) with one ticket booking per attendee.
 *
 * Providers, as stored by Event Tickets 5.x:
 * - rsvp: legacy RSVP v1 (tribe_rsvp_tickets / tribe_rsvp_attendees, grouped by the _tribe_rsvp_order hash)
 * - tc: Tickets Commerce, including RSVP v2 tickets with _type tc-rsvp (tec_tc_ticket / tec_tc_attendee, grouped by the tec_tc_order parent)
 * - tpp: legacy Tribe Commerce PayPal (tribe_tpp_tickets / tribe_tpp_attendees)
 * - woo: Event Tickets Plus WooCommerce products (product / tribe_wooticket, grouped by WooCommerce order)
 * - edd: Event Tickets Plus Easy Digital Downloads (download / tribe_eddticket)
 */
class Tickets {

	public static function providers() {
		return array(
			'rsvp' => array( 'ticket' => 'tribe_rsvp_tickets', 'event_key' => '_tribe_rsvp_for_event', 'attendee' => 'tribe_rsvp_attendees', 'a_event' => '_tribe_rsvp_event', 'a_ticket' => '_tribe_rsvp_product', 'a_order' => '_tribe_rsvp_order', 'a_name' => '_tribe_rsvp_full_name', 'a_email' => '_tribe_rsvp_email', 'a_checkin' => '_tribe_rsvp_checkedin', 'a_code' => '_tribe_rsvp_security_code' ),
			'tc' => array( 'ticket' => 'tec_tc_ticket', 'event_key' => '_tec_tickets_commerce_event', 'attendee' => 'tec_tc_attendee', 'a_event' => '_tec_tickets_commerce_event', 'a_ticket' => '_tec_tickets_commerce_ticket', 'a_order' => 'post_parent', 'a_name' => '_tec_tickets_commerce_full_name', 'a_email' => '_tec_tickets_commerce_email', 'a_checkin' => '_tec_tickets_commerce_checked_in', 'a_code' => '_tec_tickets_commerce_security_code' ),
			'tpp' => array( 'ticket' => 'tribe_tpp_tickets', 'event_key' => '_tribe_tpp_for_event', 'attendee' => 'tribe_tpp_attendees', 'a_event' => '_tribe_tpp_event', 'a_ticket' => '_tribe_tpp_product', 'a_order' => '_tribe_tpp_order', 'a_name' => '_tribe_tpp_full_name', 'a_email' => '_tribe_tpp_email', 'a_checkin' => '_tribe_tpp_checkedin', 'a_code' => '_tribe_tpp_security_code' ),
			'woo' => array( 'ticket' => 'product', 'event_key' => '_tribe_wooticket_for_event', 'attendee' => 'tribe_wooticket', 'a_event' => '_tribe_wooticket_event', 'a_ticket' => '_tribe_wooticket_product', 'a_order' => '_tribe_wooticket_order', 'a_name' => '_tribe_tickets_full_name', 'a_email' => '_tribe_tickets_email', 'a_checkin' => '_tribe_wooticket_checkedin', 'a_code' => '_tribe_wooticket_security_code' ),
			'edd' => array( 'ticket' => 'download', 'event_key' => '_tribe_eddticket_for_event', 'attendee' => 'tribe_eddticket', 'a_event' => '_tribe_eddticket_event', 'a_ticket' => '_tribe_eddticket_product', 'a_order' => '_tribe_eddticket_order', 'a_name' => '_tribe_tickets_full_name', 'a_email' => '_tribe_tickets_email', 'a_checkin' => '_tribe_eddticket_checkedin', 'a_code' => '_tribe_eddticket_security_code' ),
		);
	}

	/**
	 * @return array of array( provider, ticket_id, event_id )
	 */
	public static function get_tickets( $after_key = '', $limit = 0, $only = null ) {
		global $wpdb;
		$rows = array();
		foreach ( static::providers() as $provider => $p ) {
			if ( $only && $only !== $provider ) {
				continue;
			}
			$results = $wpdb->get_results( $wpdb->prepare( "SELECT p.ID AS ticket_id, pm.meta_value AS event_id FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} pm ON pm.post_id=p.ID AND pm.meta_key=%s WHERE p.post_type=%s AND p.post_status NOT IN ('trash','auto-draft') ORDER BY p.ID", $p['event_key'], $p['ticket'] ), ARRAY_A );
			foreach ( $results as $r ) {
				$rows[] = array( 'provider' => $provider, 'ticket_id' => (int) $r['ticket_id'], 'event_id' => (int) $r['event_id'], 'key' => $provider . ':' . $r['ticket_id'] );
			}
		}
		return $rows;
	}

	public static function count_tickets( $only = null ) {
		return count( static::get_tickets( '', 0, $only ) );
	}

	public static function count_attendees() {
		global $wpdb;
		$types = wp_list_pluck( static::providers(), 'attendee' );
		$in = implode( ',', array_map( function( $t ) use ( $wpdb ) { return $wpdb->prepare( '%s', $t ); }, $types ) );
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ($in) AND post_status NOT IN ('auto-draft')" );
	}

	public static function event_has_tickets( $event_post_id ) {
		global $wpdb;
		$keys = wp_list_pluck( static::providers(), 'event_key' );
		$in = implode( ',', array_map( function( $k ) use ( $wpdb ) { return $wpdb->prepare( '%s', $k ); }, $keys ) );
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT pm.post_id FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID=pm.post_id WHERE pm.meta_key IN ($in) AND pm.meta_value=%s AND p.post_status NOT IN ('trash','auto-draft') LIMIT 1", (string) $event_post_id ) );
	}

	public static function count_recurring_event_tickets( TEC $tec ) {
		$n = 0;
		foreach ( static::get_tickets() as $t ) {
			$type = get_post_type( $t['event_id'] );
			if ( 'tribe_event_series' === $type || ( 'tribe_events' === $type && $tec->get_rset( $t['event_id'] ) ) ) {
				$n++;
			}
		}
		return $n;
	}

	/**
	 * EM event a TEC ticket's post belongs to; series passes go to the first migrated event of the series.
	 */
	protected static function em_event_for( TEC $tec, $source_event_id ) {
		$event_id = Map::get_em_id( TEC::KEY, 'event', $source_event_id );
		if ( ! $event_id && 'tribe_event_series' === get_post_type( $source_event_id ) ) {
			$row = Map::find_by_data( TEC::KEY, 'event', 'series', (int) $source_event_id );
			$event_id = $row ? (int) $row['em_id'] : 0;
		}
		return $event_id ? em_get_event( $event_id ) : null;
	}

	public static function step_tickets( TEC $tec, $cursor, $limit, Job $job ) {
		$all = static::get_tickets();
		$offset = (int) $cursor;
		$batch = array_slice( $all, $offset, $limit );
		foreach ( $batch as $t ) {
			try {
				static::migrate_ticket( $tec, $t, $job );
			} catch ( \Throwable $e ) {
				$job->log( sprintf( 'Ticket #%d: %s', $t['ticket_id'], $e->getMessage() ), 'error' );
				$job->count( 'tickets', 'failed' );
			}
		}
		return array( 'done' => $offset + $limit >= count( $all ), 'cursor' => $offset + $limit );
	}

	protected static function migrate_ticket( TEC $tec, array $t, Job $job ) {
		if ( Map::get_live( TEC::KEY, 'ticket', $t['key'] ) ) {
			$job->count( 'tickets', 'skipped' );
			return;
		}
		$post = get_post( $t['ticket_id'] );
		$EM_Event = static::em_event_for( $tec, $t['event_id'] );
		if ( ! $EM_Event || ! $EM_Event->event_id ) {
			$job->log( sprintf( 'Ticket "%s" (#%d) belongs to an event that was not migrated, skipped.', $post->post_title, $post->ID ), 'error' );
			$job->count( 'tickets', 'orphaned' );
			return;
		}
		$m = function( $key ) use ( $post ) {
			return (string) get_post_meta( $post->ID, $key, true );
		};
		$event_post = $t['event_id'];
		$shared = $tec->is_truthy( get_post_meta( $event_post, '_tribe_ticket_use_global_stock', true ) );
		$shared_capacity = (int) get_post_meta( $event_post, '_tribe_ticket_capacity', true );
		$mode = $m( '_global_stock_mode' );
		$capacity = (int) $m( '_tribe_ticket_capacity' );
		if ( '' === $m( '_tribe_ticket_capacity' ) && 'yes' === $m( '_manage_stock' ) ) {
			$capacity = (int) $m( '_stock' ) + (int) $m( 'total_sales' );
		}
		$unlimited = $capacity < 0 || 'unlimited' === $mode || ( 'no' === $m( '_manage_stock' ) && $capacity <= 0 );
		if ( in_array( $mode, array( 'global', 'capped' ), true ) && $shared && $shared_capacity > 0 ) {
			if ( (int) $EM_Event->event_spaces !== $shared_capacity ) {
				$EM_Event->event_spaces = $shared_capacity;
				$GLOBALS['wpdb']->update( EM_EVENTS_TABLE, array( 'event_spaces' => $shared_capacity ), array( 'event_id' => $EM_Event->event_id ) );
				update_post_meta( $EM_Event->post_id, '_event_spaces', $shared_capacity );
			}
			if ( 'global' === $mode ) {
				$capacity = $shared_capacity;
			}
		}
		if ( $unlimited ) {
			// EM tickets always have a size; size an unlimited TEC ticket to the event's shared capacity or well beyond what it ever sold
			$capacity = $shared_capacity > 0 ? $shared_capacity : max( 1000, (int) $m( 'total_sales' ) * 10 );
			$job->count( 'tickets', 'unlimited_sized' );
		}
		$is_rsvp = 'rsvp' === $t['provider'] || 'tc-rsvp' === $m( '_type' );
		$start = static::ticket_datetime( $m( '_ticket_start_date' ), $m( '_ticket_start_time' ) );
		$end = static::ticket_datetime( $m( '_ticket_end_date' ), $m( '_ticket_end_time' ), '23:59:59' );
		$price = 'woo' === $t['provider'] && '' !== $m( '_regular_price' ) && '' === $m( '_price' ) ? $m( '_regular_price' ) : $m( '_price' );
		$meta = array( 'tec' => array( 'provider' => $t['provider'], 'ticket_id' => $post->ID, 'rsvp' => $is_rsvp ? 1 : 0 ) );
		$fields = get_post_meta( $post->ID, '_tribe_tickets_meta', true );
		if ( is_array( $fields ) && $fields ) {
			$meta['tec']['attendee_fields'] = array_map( function( $f ) { return array( 'slug' => $f['slug'] ?? '', 'label' => $f['label'] ?? '', 'type' => $f['type'] ?? 'text', 'required' => ! empty( $f['required'] ) ); }, $fields );
		}
		$EM_Ticket = Writer::save_ticket( $EM_Event, array(
			// Event Tickets saves ticket names and descriptions HTML-escaped (Chef&#039;s table)
			'name' => html_entity_decode( $post->post_title, ENT_QUOTES, 'UTF-8' ),
			'description' => html_entity_decode( $post->post_excerpt, ENT_QUOTES, 'UTF-8' ),
			'price' => $is_rsvp ? 0 : (float) $price,
			'spaces' => $capacity,
			'start' => $start,
			'end' => $end,
			'order' => $post->menu_order,
			'meta' => $meta,
		) );
		if ( is_wp_error( $EM_Ticket ) ) {
			throw new \Exception( $EM_Ticket->get_error_message() );
		}
		if ( ! $EM_Event->event_rsvp ) {
			$GLOBALS['wpdb']->update( EM_EVENTS_TABLE, array( 'event_rsvp' => 1 ), array( 'event_id' => $EM_Event->event_id ) );
			update_post_meta( $EM_Event->post_id, '_event_rsvp', 1 );
		}
		$data = array( 'event_id' => $EM_Event->event_id, 'provider' => $t['provider'] );
		if ( 'recurring' === $EM_Event->event_type ) {
			// a series pass (or any ticket on a TEC series) covers every date, so each date gets its own child ticket for the per-date bookings
			$children = Writer::add_occurrence_tickets( $EM_Event, array( $EM_Ticket ) );
			$data['occurrence_tickets'] = $children[ $EM_Ticket->ticket_id ] ?? array();
			$data['series_pass'] = 'series_pass' === $m( '_type' ) || 'tribe_event_series' === get_post_type( $t['event_id'] );
		}
		Map::set( TEC::KEY, 'ticket', $t['key'], 'ticket', $EM_Ticket->ticket_id, $data );
		$job->count( 'tickets', 'created' );
	}

	protected static function ticket_datetime( $date, $time = '', $default_time = '00:00:00' ) {
		$date = trim( (string) $date );
		if ( '' === $date ) {
			return null;
		}
		if ( strlen( $date ) <= 10 ) {
			$time = trim( (string) $time );
			$date .= ' ' . ( $time ? date( 'H:i:s', strtotime( '2000-01-01 ' . $time ) ) : $default_time );
		}
		return date( 'Y-m-d H:i:s', strtotime( $date ) );
	}

	/**
	 * Attendee groups: one EM booking per (provider, order, event).
	 */
	protected static function get_groups( $cursor_id, $limit ) {
		global $wpdb;
		$groups = array();
		$last = (int) $cursor_id;
		$types = array();
		foreach ( static::providers() as $provider => $p ) {
			$types[ $p['attendee'] ] = $provider;
		}
		$in = implode( ',', array_map( function( $t ) use ( $wpdb ) { return $wpdb->prepare( '%s', $t ); }, array_keys( $types ) ) );
		// trashed attendees are kept: refunded Tickets Commerce orders trash theirs, and they become cancelled bookings
		$ids = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_type FROM {$wpdb->posts} WHERE post_type IN ($in) AND post_status NOT IN ('auto-draft') AND ID > %d ORDER BY ID LIMIT %d", $last, $limit ), ARRAY_A );
		foreach ( $ids as $r ) {
			$provider = $types[ $r['post_type'] ];
			$p = static::providers()[ $provider ];
			$post = get_post( $r['ID'] );
			$order = 'post_parent' === $p['a_order'] ? (string) $post->post_parent : (string) get_post_meta( $post->ID, $p['a_order'], true );
			$event = (int) get_post_meta( $post->ID, $p['a_event'], true );
			$key = $provider . ':' . ( $order ?: 'attendee-' . $post->ID ) . ':' . $event;
			$groups[ $key ]['provider'] = $provider;
			$groups[ $key ]['order'] = $order;
			$groups[ $key ]['event'] = $event;
			$groups[ $key ]['attendees'][] = $post;
			$last = (int) $r['ID'];
		}
		return array( $groups, $last, count( $ids ) < $limit );
	}

	public static function step_attendees( TEC $tec, $cursor, $limit, Job $job ) {
		list( $groups, $last, $done ) = static::get_groups( $cursor, $limit * 5 );
		foreach ( $groups as $key => $g ) {
			try {
				static::migrate_group( $tec, $key, $g, $job );
			} catch ( \Throwable $e ) {
				$job->log( sprintf( 'Attendees %s: %s', $key, $e->getMessage() ), 'error' );
				$job->count( 'attendees', 'failed' );
			}
		}
		return array( 'done' => $done, 'cursor' => $last );
	}

	/**
	 * A group read in two batches (an order whose attendees straddle the cursor) adds the later attendees to the booking made from the first batch.
	 */
	protected static function migrate_group( TEC $tec, $key, array $g, Job $job ) {
		$provider = $g['provider'];
		$p = static::providers()[ $provider ];
		if ( 'woo' === $provider && 'skip' === $job->option( 'woo_bookings', 'em' ) ) {
			return;
		}
		$pending = array();
		foreach ( $g['attendees'] as $a ) {
			if ( ! Map::get_live( TEC::KEY, 'attendee', $provider . ':' . $a->ID ) ) {
				$pending[] = $a;
			}
		}
		if ( ! $pending ) {
			$job->count( 'attendees', 'skipped', count( $g['attendees'] ) );
			return;
		}
		$EM_Event = static::em_event_for( $tec, $g['event'] );
		if ( ! $EM_Event || ! $EM_Event->event_id ) {
			$job->count( 'attendees', 'orphaned', count( $pending ) );
			return;
		}
		$order = static::read_order( $provider, $g['order'] );
		if ( 'tpp' === $provider ) {
			$state = strtolower( (string) get_post_meta( $pending[0]->ID, '_tribe_tpp_status', true ) ) ?: 'completed';
			$order['state'] = $state;
			$order['status'] = array( 'completed' => 1, 'pending' => 5, 'refunded' => 3, 'denied' => 2, 'not-completed' => 0 )[ $state ] ?? 0;
			$order['migrate'] = 'not-completed' !== $state;
		}
		if ( ! $order['migrate'] ) {
			$job->count( 'attendees', 'skipped_' . $order['state'], count( $pending ) );
			return;
		}
		$tickets = array();
		$price = 0.0;
		$rsvp_status = null;
		$first = $pending[0];
		$user_id = (int) get_post_meta( $first->ID, '_tribe_tickets_attendee_user_id', true ) ?: (int) $order['user_id'];
		$codes = array();
		foreach ( $pending as $a ) {
			$am = function( $k ) use ( $a ) {
				return (string) get_post_meta( $a->ID, $k, true );
			};
			$ticket_source = (int) $am( $p['a_ticket'] );
			$em_ticket_id = Map::get_em_id( TEC::KEY, 'ticket', $provider . ':' . $ticket_source );
			if ( ! $em_ticket_id ) {
				$job->count( 'attendees', 'no_ticket' );
				continue;
			}
			$attendee = array(
				'attendee_name' => $am( $p['a_name'] ) ?: $am( '_tribe_tickets_full_name' ),
				'attendee_email' => $am( $p['a_email'] ) ?: $am( '_tribe_tickets_email' ),
				'tec_attendee_id' => $a->ID,
				'tec_security_code' => $am( $p['a_code'] ),
				'tec_checked_in' => $tec->is_truthy( $am( $p['a_checkin'] ) ) ? 1 : null,
			);
			$fields = get_post_meta( $a->ID, '_tribe_tickets_meta', true );
			if ( is_array( $fields ) ) {
				foreach ( $fields as $slug => $value ) {
					$attendee[ sanitize_key( $slug ) ] = is_array( $value ) ? implode( ', ', $value ) : $value;
				}
			}
			$tickets[ $em_ticket_id ]['ticket_id'] = $em_ticket_id;
			$tickets[ $em_ticket_id ]['attendees'][] = $attendee;
			$paid = $am( '_tec_tickets_commerce_price_paid' );
			$paid = '' !== $paid ? $paid : $am( '_paid_price' );
			$price += (float) $paid;
			$codes[] = $attendee['tec_security_code'];
			$rsvp = $am( '_tribe_rsvp_status' ) ?: $am( '_tec_tickets_commerce_rsvp_status' );
			if ( '' !== $rsvp ) {
				$rsvp_status = 'no' === strtolower( $rsvp ) ? 0 : 1;
			}
		}
		if ( ! $tickets ) {
			return;
		}
		$status = $order['status'];
		if ( 0 === (int) $EM_Event->event_active_status && in_array( $status, array( 0, 1, 5 ), true ) ) {
			// as when EM cancels an event: its bookings are cancelled, the TEC order status stays in the booking meta
			$status = 3;
			$job->count( 'attendees', 'cancelled_with_event' );
		}
		if ( 0 === $rsvp_status ) {
			// declined RSVPs hold no space in TEC, so they are kept as cancelled bookings marked Not Attending
			$status = 3;
		}
		$meta = array( 'tec' => array_filter( array(
			'provider' => $provider,
			'order_id' => $g['order'],
			'gateway' => $order['gateway'] ?? null,
			'gateway_order_id' => $order['gateway_order_id'] ?? null,
			'order_status' => $order['state'],
			'currency' => $order['currency'] ?? null,
		) ) );
		$woo_link = 'woo' === $provider && 'em-woocommerce' === $job->option( 'woo_bookings', 'em' ) && $g['order'];
		if ( $woo_link ) {
			$meta['woocommerce'] = array( 'order_id' => (int) $g['order'] );
		}
		// RSVPs and PayPal have no order record holding the purchaser, so the first attendee stands in
		$guest_name = $order['name'] ?: trim( (string) get_post_meta( $first->ID, $p['a_name'], true ) ?: (string) get_post_meta( $first->ID, '_tribe_tickets_full_name', true ) );
		$name_parts = explode( ' ', $guest_name, 2 );
		$booking_data = array(
			'user_id' => $user_id,
			'guest' => array( 'email' => $order['email'] ?: (string) get_post_meta( $first->ID, $p['a_email'], true ), 'name' => $guest_name, 'first_name' => $order['first_name'] ?: $name_parts[0], 'last_name' => $order['last_name'] ?: ( $name_parts[1] ?? '' ), 'phone' => $order['phone'] ?? '' ),
			'status' => $status,
			'rsvp_status' => $rsvp_status,
			'date_gmt' => $order['date_gmt'] ?: $first->post_date_gmt,
			'price' => isset( $order['total'] ) && null !== $order['total'] && count( $g['attendees'] ) === count( $pending ) && 'woo' !== $provider ? (float) $order['total'] : $price,
			'tickets' => array_values( $tickets ),
			'meta' => $meta,
		);
		if ( 'recurring' === $EM_Event->event_type ) {
			$booking_ids = static::save_series_bookings( $EM_Event, $booking_data, $key );
			$EM_Booking = em_get_booking( $booking_ids[0] );
		} else {
			$EM_Booking = Writer::save_booking( $EM_Event, $booking_data );
			if ( is_wp_error( $EM_Booking ) ) {
				throw new \Exception( $EM_Booking->get_error_message() );
			}
			Map::set( TEC::KEY, 'attendee_group', $key, 'booking', $EM_Booking->booking_id, array( 'event_id' => $EM_Event->event_id ) );
		}
		if ( $woo_link ) {
			static::link_woocommerce_order( (int) $g['order'], $EM_Booking, $tickets );
		}
		foreach ( $pending as $a ) {
			Map::set( TEC::KEY, 'attendee', $provider . ':' . $a->ID, 'attendee', 0, array( 'booking_id' => $EM_Booking->booking_id ) );
		}
		$job->count( 'attendees', 'migrated', count( $pending ) );
		$job->count( 'attendees', 'bookings' );
	}

	/**
	 * A series pass covers every date of the series, and EM books dates, not series: one booking per date on that date's child ticket, the whole amount on the first so revenue isn't counted eight times, each booking pointing at the pass it came from.
	 *
	 * @return int[] booking ids, first date first
	 */
	protected static function save_series_bookings( $EM_Event, array $booking_data, $key ) {
		$per_date = array();
		foreach ( $booking_data['tickets'] as $t ) {
			$row = $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( 'SELECT data FROM ' . Map::table() . ' WHERE source=%s AND object_type=%s AND em_id=%d', TEC::KEY, 'ticket', $t['ticket_id'] ) );
			$data = $row ? json_decode( $row, true ) : array();
			foreach ( (array) ( $data['occurrence_tickets'] ?? array() ) as $occurrence_id => $child_ticket_id ) {
				$per_date[ $occurrence_id ][] = array( 'ticket_id' => (int) $child_ticket_id, 'attendees' => $t['attendees'] );
			}
		}
		if ( ! $per_date ) {
			throw new \Exception( 'Series pass has no dates to book.' );
		}
		$ids = array();
		$n = 0;
		$total = count( $per_date );
		foreach ( $per_date as $occurrence_id => $tickets ) {
			$n++;
			$data = $booking_data;
			$data['tickets'] = $tickets;
			$data['price'] = 1 === $n ? $booking_data['price'] : 0;
			$data['meta']['tec']['series_pass'] = array( 'date' => $n, 'of' => $total, 'first_booking_id' => $ids[0] ?? 0 );
			if ( $n > 1 ) {
				$data['adjustment_name'] = sprintf( __( 'Series pass, paid with booking #%d', 'events-manager' ), $ids[0] );
			}
			$EM_Booking = Writer::save_booking( em_get_event( $occurrence_id ), $data );
			if ( is_wp_error( $EM_Booking ) ) {
				throw new \Exception( $EM_Booking->get_error_message() );
			}
			$ids[] = (int) $EM_Booking->booking_id;
			Map::set( TEC::KEY, 'attendee_group', 1 === $n ? $key : $key . ':' . $n, 'booking', $EM_Booking->booking_id, array( 'event_id' => $occurrence_id, 'series_pass' => $n ) );
		}
		return $ids;
	}

	/**
	 * @return array migrate (bool), state (source status), status (EM booking status), user_id, email, name, first_name, last_name, date_gmt, total
	 */
	protected static function read_order( $provider, $order_id ) {
		$o = array( 'migrate' => true, 'state' => 'completed', 'status' => 1, 'user_id' => 0, 'email' => '', 'name' => '', 'first_name' => '', 'last_name' => '', 'date_gmt' => '', 'total' => null );
		if ( 'tc' === $provider && $order_id ) {
			$post = get_post( (int) $order_id );
			if ( ! $post ) {
				return $o;
			}
			$state = preg_replace( '/^tec-tc-/', '', $post->post_status );
			$o['state'] = $state;
			$map = array( 'completed' => 1, 'approved' => 1, 'pending' => 5, 'action-req' => 5, 'created' => 5, 'refunded' => 3, 'reversed' => 3, 'voided' => 3, 'denied' => 2, 'trash' => 3 );
			if ( in_array( $state, array( 'not-completed', 'undefined', 'unsupported' ), true ) ) {
				// abandoned checkouts never became attendees anyone expects to see
				$o['migrate'] = false;
			}
			$o['status'] = $map[ $state ] ?? 0;
			$m = function( $k ) use ( $post ) {
				return (string) get_post_meta( $post->ID, $k, true );
			};
			$o['user_id'] = (int) $m( '_tec_tc_order_purchaser_user_id' );
			$o['email'] = $m( '_tec_tc_order_purchaser_email' );
			$o['name'] = $m( '_tec_tc_order_purchaser_full_name' );
			$o['first_name'] = $m( '_tec_tc_order_purchaser_first_name' );
			$o['last_name'] = $m( '_tec_tc_order_purchaser_last_name' );
			$o['gateway'] = $m( '_tec_tc_order_gateway' );
			$o['gateway_order_id'] = $m( '_tec_tc_order_gateway_order_id' );
			$o['currency'] = $m( '_tec_tc_order_currency' );
			$o['date_gmt'] = $post->post_date_gmt;
			$events = get_post_meta( $post->ID, '_tec_tc_order_events_in_order' );
			$o['total'] = count( array_unique( $events ) ) <= 1 && '' !== $m( '_tec_tc_order_total_value' ) ? (float) $m( '_tec_tc_order_total_value' ) : null;
		} elseif ( 'woo' === $provider && $order_id && function_exists( 'wc_get_order' ) ) {
			$order = wc_get_order( (int) $order_id );
			if ( ! $order ) {
				return $o;
			}
			$state = $order->get_status();
			$o['state'] = $state;
			$map = array( 'completed' => 1, 'processing' => 1, 'on-hold' => 5, 'pending' => 5, 'cancelled' => 3, 'refunded' => 3, 'failed' => 2 );
			$o['status'] = $map[ $state ] ?? 0;
			if ( in_array( $state, array( 'checkout-draft', 'trash' ), true ) ) {
				$o['migrate'] = false;
			}
			$o['user_id'] = (int) $order->get_customer_id();
			$o['email'] = $order->get_billing_email();
			$o['first_name'] = $order->get_billing_first_name();
			$o['last_name'] = $order->get_billing_last_name();
			$o['name'] = trim( $o['first_name'] . ' ' . $o['last_name'] );
			$o['phone'] = $order->get_billing_phone();
			$o['gateway'] = $order->get_payment_method();
			$o['gateway_order_id'] = $order->get_transaction_id();
			$o['currency'] = $order->get_currency();
			$created = $order->get_date_created();
			$o['date_gmt'] = $created ? gmdate( 'Y-m-d H:i:s', $created->getTimestamp() ) : '';
		} elseif ( 'edd' === $provider && $order_id ) {
			$post = get_post( (int) $order_id );
			if ( ! $post ) {
				return $o;
			}
			// EDD 2 payments are edd_payment posts; publish and complete are paid
			$state = 'publish' === $post->post_status ? 'complete' : $post->post_status;
			$o['state'] = $state;
			$map = array( 'complete' => 1, 'pending' => 5, 'processing' => 5, 'refunded' => 3, 'revoked' => 3, 'failed' => 2 );
			$o['status'] = $map[ $state ] ?? 0;
			if ( in_array( $state, array( 'abandoned', 'trash' ), true ) ) {
				$o['migrate'] = false;
			}
			$o['user_id'] = (int) get_post_meta( $post->ID, '_edd_payment_user_id', true );
			$o['email'] = (string) get_post_meta( $post->ID, '_edd_payment_user_email', true );
			$o['total'] = '' !== (string) get_post_meta( $post->ID, '_edd_payment_total', true ) ? (float) get_post_meta( $post->ID, '_edd_payment_total', true ) : null;
			$o['date_gmt'] = $post->post_date_gmt;
		} elseif ( 'tpp' === $provider ) {
			// Tribe Commerce PayPal keeps the payment status on each attendee, filled in by migrate_group()
			$o['state'] = 'completed';
		} elseif ( 'rsvp' === $provider ) {
			$o['state'] = 'rsvp';
		}
		return $o;
	}

	/**
	 * Points the original WooCommerce order's ticket line item at the new EM booking, which is how Events Manager for WooCommerce ties a booking to its order.
	 */
	protected static function link_woocommerce_order( $order_id, $EM_Booking, array $tickets ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$ticket_by_product = array();
		foreach ( $tickets as $em_ticket_id => $t ) {
			$row = $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( 'SELECT source_id FROM ' . Map::table() . ' WHERE source=%s AND object_type=%s AND em_id=%d', TEC::KEY, 'ticket', $em_ticket_id ) );
			if ( $row ) {
				$ticket_by_product[ (int) substr( $row, 4 ) ] = $em_ticket_id;
			}
		}
		foreach ( $order->get_items() as $item_id => $item ) {
			$product_id = (int) $item->get_product_id();
			if ( ! isset( $ticket_by_product[ $product_id ] ) ) {
				continue;
			}
			$item->update_meta_data( '_em_booking_id', $EM_Booking->booking_id );
			$item->update_meta_data( '_em_event_id', $EM_Booking->event_id );
			$item->update_meta_data( '_em_ticket_id', $ticket_by_product[ $product_id ] );
			$item->save();
			Map::set( TEC::KEY, 'woocommerce_item', $order_id . ':' . $item_id, 'woocommerce_item', $item_id, array( 'order_id' => $order_id ) );
		}
	}
}
