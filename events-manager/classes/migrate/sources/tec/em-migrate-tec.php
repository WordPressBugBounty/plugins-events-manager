<?php
namespace EM\Migrate\Sources\TEC;

use EM\Migrate\Source;
use EM\Migrate\Job;
use EM\Migrate\Map;
use EM\Migrate\Writer;
use EM\Migrate\Migrate;

require_once __DIR__ . '/em-migrate-tec-recurrence.php';
require_once __DIR__ . '/em-migrate-tec-tickets.php';

/**
 * The Events Calendar (StellarWP) with Events Calendar Pro, Event Tickets and Event Tickets Plus. Everything is read from the database
 * (posts, postmeta, the TEC custom tables and options) so it works with the TEC plugins active or deactivated.
 */
class TEC extends Source {

	const KEY = 'tec';
	const TEC_CONTENT_SQL = "( post_content LIKE '%[tribe\\_%' OR post_content LIKE '%[tribe:%' OR post_content LIKE '%<!-- wp:tribe/%' OR post_content LIKE '%<!-- wp:tec/%' )";

	public function get_key() {
		return static::KEY;
	}

	public function get_name() {
		return 'The Events Calendar';
	}

	public function is_plugin_active() {
		return class_exists( 'Tribe__Events__Main', false ) || defined( 'TRIBE_EVENTS_FILE' );
	}

	public function is_detected() {
		return (bool) $this->count_posts( 'tribe_events' );
	}

	public function count_posts( $post_type, $statuses = null ) {
		global $wpdb;
		$statuses = $statuses ?: $this->migratable_statuses();
		$in = implode( ',', array_map( function( $s ) use ( $wpdb ) { return $wpdb->prepare( '%s', $s ); }, $statuses ) );
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type=%s AND post_status IN ($in)", $post_type ) );
	}

	public function migratable_statuses() {
		return array( 'publish', 'future', 'draft', 'pending', 'private' );
	}

	public function get_summary() {
		global $wpdb;
		$tickets = Tickets::count_tickets();
		$attendees = Tickets::count_attendees();
		$summary = array(
			__( 'Events', 'events-manager' ) => $this->count_posts( 'tribe_events' ),
			__( 'Recurring events (Pro)', 'events-manager' ) => $this->count_recurring(),
			__( 'Venues', 'events-manager' ) => $this->count_posts( 'tribe_venue' ),
			__( 'Organizers', 'events-manager' ) => $this->count_posts( 'tribe_organizer' ),
			__( 'Event categories', 'events-manager' ) => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy='tribe_events_cat'" ),
			__( 'Tickets and RSVPs', 'events-manager' ) => $tickets,
			__( 'Attendees', 'events-manager' ) => $attendees,
			__( 'Pages using TEC shortcodes or blocks', 'events-manager' ) => count( $this->find_content_posts() ),
		);
		return $summary;
	}

	public function count_recurring() {
		global $wpdb;
		if ( $this->has_custom_tables() ) {
			return (int) $wpdb->get_var( "SELECT COUNT(DISTINCT e.post_id) FROM {$wpdb->prefix}tec_events e WHERE e.rset IS NOT NULL AND e.rset <> ''" );
		}
		return (int) $wpdb->get_var( "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key='_EventRecurrence' AND meta_value LIKE '%rules%'" );
	}

	public function has_custom_tables() {
		global $wpdb;
		static $has = null;
		if ( null === $has ) {
			$has = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . 'tec_occurrences' ) ) === $wpdb->prefix . 'tec_occurrences';
			if ( $has ) {
				$cols = $wpdb->get_col( "SHOW COLUMNS FROM {$wpdb->prefix}tec_events" );
				$has = in_array( 'rset', $cols, true ) || (bool) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}tec_events" );
			}
		}
		return $has;
	}

	public function get_tec_option( $key, $default = null ) {
		$options = get_option( 'tribe_events_calendar_options', array() );
		return is_array( $options ) && array_key_exists( $key, $options ) ? $options[ $key ] : $default;
	}

	public function get_options() {
		$options = array(
			'past_events' => array( 'label' => __( 'Include past events', 'events-manager' ), 'type' => 'checkbox', 'default' => true ),
			'tickets' => array( 'label' => __( 'Migrate tickets, RSVPs and attendees', 'events-manager' ), 'type' => 'checkbox', 'default' => true ),
			'woo_bookings' => array(
				'label' => __( 'WooCommerce ticket orders become', 'events-manager' ),
				'type' => 'select',
				'default' => 'em',
				'choices' => array(
					'em' => __( 'Events Manager bookings', 'events-manager' ),
					'em-woocommerce' => __( 'Events Manager bookings linked to their WooCommerce orders (Events Manager for WooCommerce)', 'events-manager' ),
					'skip' => __( 'Nothing, migrate the tickets only', 'events-manager' ),
				),
				'description' => __( 'Event Tickets Plus sells tickets as WooCommerce products. Linking keeps each booking attached to its original WooCommerce order.', 'events-manager' ),
			),
			'content' => array( 'label' => __( 'Replace TEC shortcodes and blocks in posts and pages with Events Manager equivalents', 'events-manager' ), 'type' => 'checkbox', 'default' => true ),
			'urls' => array( 'label' => __( 'Keep The Events Calendar\'s web addresses for events, venues, organizers, categories and tags', 'events-manager' ), 'type' => 'checkbox', 'default' => true, 'description' => __( 'Events Manager\'s permalink settings are changed to match, so links to your events keep working without redirects.', 'events-manager' ) ),
			'redirects' => array( 'label' => __( 'Redirect any other old addresses, such as single dates of recurring events, series pages and calendar views, once The Events Calendar is deactivated', 'events-manager' ), 'type' => 'checkbox', 'default' => true ),
		);
		if ( ! defined( 'EM_WC_VERSION' ) ) {
			unset( $options['woo_bookings']['choices']['em-woocommerce'] );
		}
		if ( ! Tickets::count_tickets( 'woo' ) ) {
			unset( $options['woo_bookings'] );
		}
		return $options;
	}

	public function get_notices() {
		$notices = array();
		if ( $this->is_plugin_active() ) {
			$notices[] = array( 'type' => 'info', 'message' => __( 'The Events Calendar is still active. It doesn\'t need to be: Events Manager reads its data directly, so you can deactivate it and its add-ons first. Once migrated, Events Manager uses the same web addresses, so the two plugins compete for them until The Events Calendar is deactivated. Your Events Calendar data is not changed either way.', 'events-manager' ) );
		}
		if ( Tickets::count_tickets() && ! get_option( 'dbem_rsvp_enabled' ) ) {
			$notices[] = array( 'type' => 'info', 'message' => __( 'Bookings are disabled in Events Manager. Migrating tickets turns them on.', 'events-manager' ) );
		}
		if ( ! defined( 'EMP_VERSION' ) && Tickets::count_attendees() ) {
			$notices[] = array( 'id' => 'attendee_details_need_pro', 'type' => 'info', 'message' => __( 'Attendee names, answers to attendee fields and check-in status are copied and kept with each ticket. Without Events Manager Pro they are not shown, so you will see each booking, its tickets and the person who booked. If you add Events Manager Pro later, run the migration again to set up its attendee forms and check-ins.', 'events-manager' ) );
		}
		$recurring_tickets = Tickets::count_recurring_event_tickets( $this );
		if ( $recurring_tickets ) {
			$notices[] = array( 'type' => 'warning', 'message' => sprintf( _n( '%d ticket belongs to a recurring event or series pass; each date of the migrated recurring event gets its own copy of the ticket, and each series pass order becomes one booking per date.', '%d tickets belong to recurring events or series passes; each date of the migrated recurring event gets its own copy of the ticket, and each series pass order becomes one booking per date.', $recurring_tickets, 'events-manager' ), $recurring_tickets ) );
		}
		return $notices;
	}

	public function get_steps() {
		return array(
			'settings' => __( 'Settings', 'events-manager' ),
			'categories' => __( 'Categories', 'events-manager' ),
			'tags' => __( 'Tags', 'events-manager' ),
			'organizers' => __( 'Organizers', 'events-manager' ),
			'venues' => __( 'Venues', 'events-manager' ),
			'events' => __( 'Events', 'events-manager' ),
			'tickets' => __( 'Tickets', 'events-manager' ),
			'attendees' => __( 'Attendees', 'events-manager' ),
			'descriptions' => __( 'Event and venue descriptions', 'events-manager' ),
			'content' => __( 'Shortcodes and blocks', 'events-manager' ),
			'finish' => __( 'Finishing', 'events-manager' ),
		);
	}

	public function run_step( $step, $cursor, $limit, Job $job ) {
		switch ( $step ) {
			case 'settings':
				return $this->step_settings( $job );
			case 'categories':
				return $this->step_categories( $cursor, $job );
			case 'tags':
				return $this->step_tags( $cursor, $job );
			case 'organizers':
				return $this->step_posts( 'tribe_organizer', $cursor, $limit * 5, $job, array( $this, 'migrate_organizer' ) );
			case 'venues':
				return $this->step_posts( 'tribe_venue', $cursor, $limit * 5, $job, array( $this, 'migrate_venue' ) );
			case 'events':
				return $this->step_posts( 'tribe_events', $cursor, $limit, $job, array( $this, 'migrate_event' ) );
			case 'tickets':
				return $job->option( 'tickets', true ) ? Tickets::step_tickets( $this, $cursor, $limit * 2, $job ) : array( 'done' => true, 'cursor' => null );
			case 'attendees':
				return $job->option( 'tickets', true ) ? Tickets::step_attendees( $this, $cursor, $limit * 2, $job ) : array( 'done' => true, 'cursor' => null );
			case 'descriptions':
				return $this->step_descriptions( $cursor, $job );
			case 'content':
				return $job->option( 'content', true ) ? $this->step_content( $cursor, $job ) : array( 'done' => true, 'cursor' => null );
			case 'finish':
				return $this->step_finish( $job );
		}
		return array( 'done' => true, 'cursor' => null );
	}

	/**
	 * Term ids of a post, read from the tables because TEC's taxonomy isn't registered while The Events Calendar is deactivated, and wp_get_object_terms() then fails.
	 * @return int[]
	 */
	protected function object_term_ids( $post_id, $taxonomy ) {
		global $wpdb;
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT tt.term_id FROM {$wpdb->term_relationships} tr JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id=tr.term_taxonomy_id WHERE tr.object_id=%d AND tt.taxonomy=%s ORDER BY tr.term_order, tt.term_id", $post_id, $taxonomy ) ) );
	}

	/**
	 * EM permalink settings that reproduce TEC's addresses: /event/slug/, /venue/slug/, /organizer/slug/, /events/category/slug/ and /events/tag/slug/ with TEC's default slugs.
	 * @return array option name => slug
	 */
	public function permalink_slugs() {
		$events = trim( (string) $this->get_tec_option( 'eventsSlug', 'events' ), '/' ) ?: 'events';
		$single = trim( (string) $this->get_tec_option( 'singleEventSlug', 'event' ), '/' ) ?: 'event';
		$slugs = array(
			'dbem_cp_events_slug' => $single,
			'dbem_cp_locations_slug' => 'venue',
			'dbem_taxonomy_organizer_slug' => 'organizer',
			'dbem_taxonomy_category_slug' => $events . '/category',
			'dbem_taxonomy_tag_slug' => $events . '/tag',
		);
		// TEC translates its venue, organizer, category and tag slugs on non-English sites, which can't be read while it is deactivated
		return apply_filters( 'em_migrate_tec_permalink_slugs', $slugs, $this );
	}

	/**
	 * Turns on the EM features the migrated data needs, recording each previous value so rollback restores it.
	 */
	protected function step_settings( Job $job ) {
		$wanted = array( 'dbem_categories_enabled' => 1, 'dbem_tags_enabled' => 1, 'dbem_attributes_enabled' => 1, 'dbem_location_attributes_enabled' => 1 );
		if ( $this->count_posts( 'tribe_organizer' ) ) {
			$wanted['dbem_organizers_enabled'] = 1;
		}
		if ( $this->count_recurring() ) {
			$wanted['dbem_recurrence_enabled'] = 1;
		}
		if ( $job->option( 'tickets', true ) && Tickets::count_tickets() ) {
			$wanted['dbem_rsvp_enabled'] = 1;
			$currency = $this->get_tec_option( 'defaultCurrencyCode' );
			$has_em_bookings = (bool) $GLOBALS['wpdb']->get_var( 'SELECT booking_id FROM ' . EM_BOOKINGS_TABLE . ' LIMIT 1' );
			if ( $currency && ! $has_em_bookings && array_key_exists( strtoupper( $currency ), em_get_currencies()->names ) ) {
				$wanted['dbem_bookings_currency'] = strtoupper( $currency );
			}
		}
		if ( $job->option( 'urls', true ) ) {
			$wanted += $this->permalink_slugs();
		}
		$location_types = (array) get_option( 'dbem_location_types', array() );
		if ( empty( $location_types['virtual'] ) && $GLOBALS['wpdb']->get_var( "SELECT post_id FROM {$GLOBALS['wpdb']->postmeta} WHERE meta_key='_tribe_events_is_virtual' AND meta_value IN ('1','yes','true') LIMIT 1" ) ) {
			$wanted['dbem_location_types'] = array_merge( $location_types, array( 'virtual' => 1 ) );
		}
		foreach ( $wanted as $option => $value ) {
			$previous = get_option( $option, null );
			if ( maybe_serialize( $previous ) === maybe_serialize( $value ) || ( is_scalar( $previous ) && is_scalar( $value ) && (string) $previous === (string) $value ) ) {
				continue;
			}
			if ( ! Map::get( static::KEY, 'option', $option ) ) {
				Map::set( static::KEY, 'option', $option, 'option', 0, array( 'option' => $option, 'previous' => $previous ) );
			}
			update_option( $option, $value );
			$job->log( sprintf( 'Set %s to %s.', $option, is_scalar( $value ) ? $value : wp_json_encode( $value ) ) );
		}
		$this->ensure_taxonomies();
		return array( 'done' => true, 'cursor' => null );
	}

	/**
	 * Taxonomies are registered at load from the options just changed, so register any still missing for the rest of this request.
	 */
	public function ensure_taxonomies() {
		$types = array_filter( array( EM_POST_TYPE_EVENT, defined( 'EM_POST_TYPE_EVENT_RECURRING' ) ? EM_POST_TYPE_EVENT_RECURRING : null ) );
		$defs = array(
			EM_TAXONOMY_CATEGORY => array( 'taxonomy' => EM_TAXONOMY_CATEGORY, 'shortname' => 'categories', 'label' => __( 'Event Categories', 'events-manager' ), 'singular_label' => __( 'Event Category', 'events-manager' ), 'slug' => EM_TAXONOMY_CATEGORY_SLUG ),
			EM_TAXONOMY_TAG => array( 'taxonomy' => EM_TAXONOMY_TAG, 'shortname' => 'tags', 'label' => __( 'Event Tags', 'events-manager' ), 'singular_label' => __( 'Event Tag', 'events-manager' ), 'slug' => EM_TAXONOMY_TAG_SLUG, 'hierarchical' => false ),
		);
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

	protected function step_categories( $cursor, Job $job ) {
		global $wpdb;
		$this->ensure_taxonomies();
		// parents before children, so a child's parent is already mapped
		$terms = $wpdb->get_results( "SELECT t.term_id, t.name, t.slug, tt.parent, tt.description FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} tt USING(term_id) WHERE tt.taxonomy='tribe_events_cat' ORDER BY tt.parent, t.term_id" );
		$pending = $terms;
		$guard = 0;
		while ( $pending && $guard++ < 50 ) {
			$next = array();
			foreach ( $pending as $t ) {
				if ( $t->parent && ! Map::get( static::KEY, 'category', $t->parent ) && $this->term_exists_in( $terms, $t->parent ) ) {
					$next[] = $t;
					continue;
				}
				$this->migrate_category( $t, $job );
			}
			$pending = $next;
		}
		return array( 'done' => true, 'cursor' => null );
	}

	protected function term_exists_in( array $terms, $term_id ) {
		foreach ( $terms as $t ) {
			if ( (int) $t->term_id === (int) $term_id ) {
				return true;
			}
		}
		return false;
	}

	protected function migrate_category( $t, Job $job ) {
		if ( Map::get_live( static::KEY, 'category', $t->term_id ) ) {
			$job->count( 'categories', 'skipped' );
			return;
		}
		$parent = $t->parent ? Map::get_em_id( static::KEY, 'category', $t->parent ) : 0;
		$result = Writer::save_term( EM_TAXONOMY_CATEGORY, $t->name, $t->slug, $parent, $t->description );
		if ( is_wp_error( $result ) ) {
			$job->log( sprintf( 'Category "%s": %s', $t->name, $result->get_error_message() ), 'error' );
			$job->count( 'categories', 'failed' );
			return;
		}
		Map::set( static::KEY, 'category', $t->term_id, 'term', $result['term_id'], array( 'taxonomy' => EM_TAXONOMY_CATEGORY, 'reused' => ! $result['created'], 'slug' => $t->slug ) );
		$color = get_term_meta( $t->term_id, 'tec-events-cat-colors-primary', true );
		if ( $color && $result['created'] ) {
			$this->upsert_em_meta( $result['term_id'], 'category-bgcolor', sanitize_hex_color( $color ) );
		}
		$job->count( 'categories', $result['created'] ? 'created' : 'reused' );
	}

	protected function upsert_em_meta( $object_id, $key, $value ) {
		global $wpdb;
		if ( ! $value ) {
			return;
		}
		$existing = $wpdb->get_var( $wpdb->prepare( 'SELECT meta_id FROM ' . EM_META_TABLE . ' WHERE object_id=%d AND meta_key=%s', $object_id, $key ) );
		if ( $existing ) {
			$wpdb->update( EM_META_TABLE, array( 'meta_value' => $value ), array( 'meta_id' => $existing ) );
		} else {
			$wpdb->insert( EM_META_TABLE, array( 'object_id' => $object_id, 'meta_key' => $key, 'meta_value' => $value ) );
		}
	}

	/**
	 * Only tags actually used on TEC events; post_tag is shared with blog posts and those stay as they are.
	 */
	protected function step_tags( $cursor, Job $job ) {
		global $wpdb;
		$this->ensure_taxonomies();
		$terms = $wpdb->get_results( "SELECT DISTINCT t.term_id, t.name, t.slug, tt.description FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} tt USING(term_id) JOIN {$wpdb->term_relationships} tr ON tr.term_taxonomy_id=tt.term_taxonomy_id JOIN {$wpdb->posts} p ON p.ID=tr.object_id WHERE tt.taxonomy='post_tag' AND p.post_type='tribe_events'" );
		foreach ( $terms as $t ) {
			if ( Map::get_live( static::KEY, 'tag', $t->term_id ) ) {
				$job->count( 'tags', 'skipped' );
				continue;
			}
			$result = Writer::save_term( EM_TAXONOMY_TAG, $t->name, $t->slug, 0, $t->description );
			if ( is_wp_error( $result ) ) {
				$job->log( sprintf( 'Tag "%s": %s', $t->name, $result->get_error_message() ), 'error' );
				$job->count( 'tags', 'failed' );
				continue;
			}
			Map::set( static::KEY, 'tag', $t->term_id, 'term', $result['term_id'], array( 'taxonomy' => EM_TAXONOMY_TAG, 'reused' => ! $result['created'], 'slug' => $t->slug ) );
			$job->count( 'tags', $result['created'] ? 'created' : 'reused' );
		}
		return array( 'done' => true, 'cursor' => null );
	}

	/**
	 * Walks a post type by ID, so a batch that times out resumes where it stopped.
	 */
	protected function step_posts( $post_type, $cursor, $limit, Job $job, callable $callback ) {
		global $wpdb;
		$after = (int) $cursor;
		$in = implode( ',', array_map( function( $s ) use ( $wpdb ) { return $wpdb->prepare( '%s', $s ); }, $this->migratable_statuses() ) );
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type=%s AND post_status IN ($in) AND ID > %d ORDER BY ID LIMIT %d", $post_type, $after, $limit ) );
		foreach ( $ids as $id ) {
			$post = get_post( $id );
			try {
				$callback( $post, $job );
			} catch ( \Throwable $e ) {
				$job->log( sprintf( '%s #%d "%s": %s', $post_type, $id, $post->post_title, $e->getMessage() ), 'error' );
				$job->count( $job->step, 'failed' );
			}
			$after = (int) $id;
		}
		return array( 'done' => count( $ids ) < $limit, 'cursor' => $after );
	}

	/**
	 * Whether a source post was migrated and has not changed since, in which case it is skipped.
	 */
	protected function is_current( $object_type, \WP_Post $post ) {
		$row = Map::get_live( static::KEY, $object_type, $post->ID );
		if ( ! $row ) {
			return array( false, null );
		}
		return array( $row['source_modified'] === $post->post_modified_gmt, $row );
	}

	public function migrate_organizer( \WP_Post $post, Job $job ) {
		list( $current, $row ) = $this->is_current( 'organizer', $post );
		if ( $current ) {
			$job->count( 'organizers', 'skipped' );
			return;
		}
		$this->ensure_taxonomies();
		if ( $row && term_exists( (int) $row['em_id'], \EM\Organizers::TAXONOMY ) ) {
			$term_id = (int) $row['em_id'];
			wp_update_term( $term_id, \EM\Organizers::TAXONOMY, array( 'name' => $post->post_title, 'description' => $post->post_content ) );
			$created = empty( $row['data']['reused'] );
			$action = 'updated';
		} else {
			$result = Writer::save_term( \EM\Organizers::TAXONOMY, $post->post_title, $post->post_name ?: sanitize_title( $post->post_title ), 0, $post->post_content );
			if ( is_wp_error( $result ) ) {
				throw new \Exception( $result->get_error_message() );
			}
			$term_id = $result['term_id'];
			$created = $result['created'];
			$action = $created ? 'created' : 'reused';
		}
		$meta = array(
			'organizer_email' => get_post_meta( $post->ID, '_OrganizerEmail', true ),
			'organizer_phone' => get_post_meta( $post->ID, '_OrganizerPhone', true ),
			'organizer_website' => get_post_meta( $post->ID, '_OrganizerWebsite', true ),
			'organizer_image_id' => get_post_thumbnail_id( $post->ID ),
		);
		foreach ( $meta as $key => $value ) {
			if ( $value ) {
				update_term_meta( $term_id, $key, $value );
			}
		}
		Map::set( static::KEY, 'organizer', $post->ID, 'term', $term_id, array( 'taxonomy' => \EM\Organizers::TAXONOMY, 'reused' => ! $created, 'slug' => $post->post_name ), $post->post_modified_gmt );
		$job->count( 'organizers', $action );
	}

	public function migrate_venue( \WP_Post $post, Job $job ) {
		list( $current, $row ) = $this->is_current( 'venue', $post );
		if ( $current ) {
			$job->count( 'venues', 'skipped' );
			return;
		}
		$m = function( $key ) use ( $post ) {
			$v = get_post_meta( $post->ID, $key, true );
			return is_array( $v ) ? reset( $v ) : (string) $v;
		};
		$country = Countries::to_code( $m( '_VenueCountry' ) );
		$state = $m( '_VenueStateProvince' ) ?: ( $m( '_VenueState' ) ?: $m( '_VenueProvince' ) );
		if ( 'US' === $country && $m( '_VenueState' ) ) {
			$state = $m( '_VenueState' );
		} elseif ( $country && 'US' !== $country && $m( '_VenueProvince' ) ) {
			$state = $m( '_VenueProvince' );
		}
		$d = array(
			'name' => $post->post_title,
			'content' => $post->post_content,
			'address' => $m( '_VenueAddress' ),
			'town' => $m( '_VenueCity' ),
			'state' => $state,
			'postcode' => $m( '_VenueZip' ),
			'country' => $country,
			'latitude' => $m( '_VenueLat' ),
			'longitude' => $m( '_VenueLng' ),
			'status' => $post->post_status,
			'author' => $post->post_author,
			'slug' => $post->post_name,
			'post_date' => $post->post_date,
			'post_date_gmt' => $post->post_date_gmt,
			'thumbnail_id' => get_post_thumbnail_id( $post->ID ),
			'attributes' => array_filter( array(
				__( 'Phone', 'events-manager' ) => $m( '_VenuePhone' ),
				__( 'Website', 'events-manager' ) => $m( '_VenueURL' ),
			) ),
		);
		if ( ! $d['country'] && $m( '_VenueCountry' ) ) {
			$job->log( sprintf( 'Venue "%s": country "%s" not recognised, left blank.', $post->post_title, $m( '_VenueCountry' ) ), 'error' );
		}
		$d['content'] = $this->append_details_block( $d['content'], $d['attributes'], 'location' );
		$existing = $row ? em_get_location( (int) $row['em_id'] ) : null;
		$EM_Location = Writer::save_location( $d, $existing && $existing->location_id ? $existing : null );
		if ( is_wp_error( $EM_Location ) ) {
			throw new \Exception( $EM_Location->get_error_message() );
		}
		$this->pin_details_block( $EM_Location->post_id, 'location' );
		Map::set( static::KEY, 'venue', $post->ID, 'location', $EM_Location->location_id, array( 'slug' => $post->post_name, 'post_id' => $EM_Location->post_id ), $post->post_modified_gmt );
		$job->count( 'venues', $row ? 'updated' : 'created' );
	}

	/**
	 * Appends a [event] or [location] shortcode listing the migrated details line by line, so they show without changing the site's formats.
	 */
	public function append_details_block( $content, array $lines, $type = 'event' ) {
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
	 * The [event]/[location] shortcode only finds its object from the global post on that object's own page, so point it at the post explicitly for lists, feeds and widgets.
	 */
	protected function pin_details_block( $post_id, $type ) {
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
	 * The stream link: Virtual Events' own URL field, or the join URL its Zoom, Webex, Microsoft or Google integration stored.
	 */
	public function get_virtual_url( $post_id ) {
		global $wpdb;
		$url = (string) get_post_meta( $post_id, '_tribe_events_virtual_url', true );
		if ( '' === $url ) {
			$url = (string) $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key LIKE %s AND meta_value <> '' ORDER BY meta_id LIMIT 1", $post_id, $wpdb->esc_like( '_tribe_events_' ) . '%' . $wpdb->esc_like( '_join_url' ) ) );
		}
		return esc_url_raw( $url );
	}

	public function get_occurrences( $post_id ) {
		global $wpdb;
		if ( $this->has_custom_tables() ) {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT occurrence_id, start_date, end_date FROM {$wpdb->prefix}tec_occurrences WHERE post_id=%d ORDER BY start_date", $post_id ), ARRAY_A );
			return array_map( function( $r ) { return array( 'id' => (int) $r['occurrence_id'], 'start' => $r['start_date'], 'end' => $r['end_date'] ); }, $rows );
		}
		// legacy Pro: each date is its own post, children of the first
		$ids = array_merge( array( $post_id ), $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_parent=%d AND post_type='tribe_events' AND post_status NOT IN ('trash','auto-draft')", $post_id ) ) );
		$out = array();
		foreach ( $ids as $id ) {
			$out[] = array( 'id' => (int) $id, 'post_id' => (int) $id, 'start' => get_post_meta( $id, '_EventStartDate', true ), 'end' => get_post_meta( $id, '_EventEndDate', true ) );
		}
		usort( $out, function( $a, $b ) { return strcmp( $a['start'], $b['start'] ); } );
		return $out;
	}

	public function get_rset( $post_id ) {
		global $wpdb;
		if ( ! $this->has_custom_tables() ) {
			return '';
		}
		return (string) $wpdb->get_var( $wpdb->prepare( "SELECT rset FROM {$wpdb->prefix}tec_events WHERE post_id=%d", $post_id ) );
	}

	public function is_truthy( $value ) {
		return in_array( strtolower( (string) $value ), array( '1', 'yes', 'true', 'on' ), true );
	}

	public function get_custom_fields() {
		$fields = array();
		foreach ( (array) $this->get_tec_option( 'custom-fields', array() ) as $field ) {
			if ( ! empty( $field['name'] ) && ! empty( $field['label'] ) ) {
				$fields[ $field['name'] ] = $field;
			}
		}
		return $fields;
	}

	public function migrate_event( \WP_Post $post, Job $job ) {
		// legacy Pro child dates are folded into their parent
		if ( $post->post_parent && 'tribe_events' === get_post_type( $post->post_parent ) && ! $this->has_custom_tables() ) {
			return;
		}
		list( $current, $row ) = $this->is_current( 'event', $post );
		if ( $current ) {
			$job->count( 'events', 'skipped' );
			return;
		}
		$m = function( $key ) use ( $post ) {
			return (string) get_post_meta( $post->ID, $key, true );
		};
		$start = $m( '_EventStartDate' );
		$end = $m( '_EventEndDate' ) ?: $start;
		if ( ! $start ) {
			$job->log( sprintf( 'Event #%d "%s" has no start date, skipped.', $post->ID, $post->post_title ), 'error' );
			$job->count( 'events', 'failed' );
			return;
		}
		if ( ! $job->option( 'past_events', true ) && strtotime( $m( '_EventEndDateUTC' ) ?: $end ) < time() && ! $this->get_rset( $post->ID ) ) {
			$job->count( 'events', 'past_skipped' );
			return;
		}
		$timezone = $m( '_EventTimezone' ) ?: wp_timezone_string();
		$all_day = $this->is_truthy( $m( '_EventAllDay' ) );

		$venue_id = (int) get_post_meta( $post->ID, '_EventVenueID', true );
		$location_id = $venue_id ? Map::get_em_id( static::KEY, 'venue', $venue_id ) : 0;
		$organizers = array();
		foreach ( get_post_meta( $post->ID, '_EventOrganizerID' ) as $oid ) {
			$term_id = Map::get_em_id( static::KEY, 'organizer', $oid );
			if ( $term_id ) {
				$organizers[] = $term_id;
			}
		}
		$categories = array();
		foreach ( $this->object_term_ids( $post->ID, 'tribe_events_cat' ) as $tid ) {
			if ( $em = Map::get_em_id( static::KEY, 'category', $tid ) ) {
				$categories[] = $em;
			}
		}
		$tags = array();
		foreach ( $this->object_term_ids( $post->ID, 'post_tag' ) as $tid ) {
			if ( $em = Map::get_em_id( static::KEY, 'tag', $tid ) ) {
				$tags[] = $em;
			}
		}

		$attributes = array();
		$visible = array();
		$cost = $m( '_EventCost' );
		if ( '' !== $cost ) {
			// TEC fills in its default symbol as an HTML entity only while it runs, so the same default is applied here and decoded
			$symbol = html_entity_decode( (string) ( $m( '_EventCurrencySymbol' ) ?: $this->get_tec_option( 'defaultCurrencySymbol', '$' ) ), ENT_QUOTES, 'UTF-8' );
			if ( is_numeric( $cost ) && 0.0 === (float) $cost ) {
				$cost = __( 'Free', 'events-manager' );
			} elseif ( is_numeric( $cost ) && $symbol ) {
				$suffix = 'suffix' === $m( '_EventCurrencyPosition' ) || ( ! $m( '_EventCurrencyPosition' ) && $this->get_tec_option( 'reverseCurrencyPosition' ) );
				$cost = $suffix ? $cost . $symbol : $symbol . $cost;
			}
			$attributes[ __( 'Cost', 'events-manager' ) ] = $cost;
			$visible[ __( 'Cost', 'events-manager' ) ] = true;
		}
		$is_virtual = $this->is_truthy( $m( '_tribe_events_is_virtual' ) );
		$virtual_url = $is_virtual ? $this->get_virtual_url( $post->ID ) : '';
		$website = $m( '_EventURL' );
		$event_location = null;
		if ( $virtual_url ) {
			// with a venue as well this is a hybrid event: EM keeps both, the venue in location_id and the stream as a Custom Link event location
			$event_location = array( 'type' => 'custom_link', 'url' => $virtual_url, 'text' => $m( '_tribe_events_virtual_linked_button_text' ) ?: __( 'Join', 'events-manager' ) );
			$show_to = array_filter( (array) maybe_unserialize( get_post_meta( $post->ID, '_tribe_events_virtual_show_embed_to', true ) ) );
			if ( array_intersect( $show_to, array( 'rsvp', 'ticket' ) ) && ! in_array( 'all', $show_to, true ) ) {
				$event_location['private'] = 1;
			} elseif ( in_array( 'logged-in', $show_to, true ) && ! in_array( 'all', $show_to, true ) ) {
				$job->log( sprintf( 'Event "%s" shows its stream to logged-in users only; Events Manager has no such setting, so the link stays public.', $post->post_title ), 'error' );
				$job->count( 'events', 'stream_logged_in_only' );
			}
		} elseif ( ! $location_id && $website ) {
			$event_location = array( 'type' => 'url', 'url' => $website, 'text' => __( 'Event website', 'events-manager' ) );
			$website = '';
		}
		if ( $location_id && $virtual_url ) {
			$job->count( 'events', 'hybrid' );
		}
		if ( $website ) {
			$attributes[ __( 'Website', 'events-manager' ) ] = $website;
			$visible[ __( 'Website', 'events-manager' ) ] = true;
		}
		$status = $m( '_tribe_events_status' );
		$active_status = null;
		if ( 'canceled' === $status || 'cancelled' === $status ) {
			$active_status = 0;
		} elseif ( $status ) {
			$attributes[ __( 'Status', 'events-manager' ) ] = ucfirst( $status );
			$visible[ __( 'Status', 'events-manager' ) ] = true;
		}
		if ( $m( '_tribe_events_status_reason' ) ) {
			$attributes[ __( 'Status reason', 'events-manager' ) ] = $m( '_tribe_events_status_reason' );
			$visible[ __( 'Status reason', 'events-manager' ) ] = true;
		}
		if ( $this->is_truthy( $m( '_tribe_featured' ) ) ) {
			$attributes[ __( 'Featured', 'events-manager' ) ] = __( 'Yes', 'events-manager' );
		}
		if ( $this->is_truthy( $m( '_EventHideFromUpcoming' ) ) ) {
			$attributes[ __( 'Hidden from upcoming', 'events-manager' ) ] = __( 'Yes', 'events-manager' );
		}
		foreach ( $this->get_custom_fields() as $key => $field ) {
			$value = $m( $key );
			if ( '' === $value ) {
				continue;
			}
			$value = str_replace( '|', ', ', $value );
			$attributes[ $field['label'] ] = $value;
			$visible[ $field['label'] ] = true;
		}
		$lines = array();
		if ( $organizers ) {
			$lines[ _n( 'Organizer', 'Organizers', count( $organizers ), 'events-manager' ) ] = '#_EVENTORGANIZERS';
		}
		foreach ( array_keys( $visible ) as $label ) {
			$lines[ $label ] = true;
		}

		$content = Content::strip_event_blocks( $post->post_content );
		if ( $content !== $post->post_content ) {
			$job->count( 'events', 'tec_blocks_removed' );
		}
		$d = array(
			'name' => $post->post_title,
			'content' => $this->append_details_block( $content, $lines, 'event' ),
			'excerpt' => $post->post_excerpt,
			'status' => $post->post_status,
			'author' => $post->post_author,
			'slug' => $post->post_name,
			'post_date' => $post->post_date,
			'post_date_gmt' => $post->post_date_gmt,
			'comment_status' => $post->comment_status,
			'password' => $post->post_password,
			'start_date' => substr( $start, 0, 10 ),
			'end_date' => substr( $end, 0, 10 ),
			'start_time' => substr( $start, 11, 8 ) ?: '00:00:00',
			'end_time' => substr( $end, 11, 8 ) ?: '23:59:59',
			'all_day' => $all_day,
			'timezone' => $timezone,
			'location_id' => $location_id,
			'event_location' => $event_location,
			'categories' => $categories,
			'tags' => $tags,
			'organizers' => $organizers,
			'attributes' => $attributes,
			'thumbnail_id' => get_post_thumbnail_id( $post->ID ),
			'active_status' => $active_status,
			'rsvp' => Tickets::event_has_tickets( $post->ID ),
		);
		$recurrence = null;
		$occurrences = array();
		$rset = $this->get_rset( $post->ID );
		if ( $rset || ! $this->has_custom_tables() ) {
			$occurrences = $this->get_occurrences( $post->ID );
		}
		if ( count( $occurrences ) > 1 ) {
			$first = $occurrences[0];
			$last = end( $occurrences );
			$d['start_date'] = substr( $first['start'], 0, 10 );
			$d['start_time'] = substr( $first['start'], 11, 8 );
			$d['end_time'] = substr( $first['end'], 11, 8 );
			$d['end_date'] = substr( $last['end'], 0, 10 );
			$sets = Recurrence::from_rset( $rset, $occurrences, $timezone );
			$d['recurrence'] = array( 'sets' => $sets ?: Recurrence::from_occurrences( $occurrences ) );
			$recurrence = $sets ? 'pattern' : 'dates';
		}

		if ( $row ) {
			// changed since the last run: replace the EM event unless it already holds bookings, which must not be lost
			$old = em_get_event( (int) $row['em_id'] );
			if ( $old && $old->event_id ) {
				if ( $this->event_has_migrated_bookings( $old ) ) {
					$job->log( sprintf( 'Event "%s" changed in TEC after migrating, but its EM copy has bookings, so it was left as it is.', $post->post_title ), 'error' );
					$job->count( 'events', 'changed_kept' );
					Map::set( static::KEY, 'event', $post->ID, 'event', $old->event_id, array(), $post->post_modified_gmt );
					return;
				}
				Writer::delete_event( $old->event_id );
			}
		}
		$EM_Event = Writer::save_event( $d );
		if ( is_wp_error( $EM_Event ) ) {
			throw new \Exception( $EM_Event->get_error_message() );
		}
		$this->pin_details_block( $EM_Event->post_id, 'event' );
		if ( $recurrence ) {
			$expected = array_map( function( $o ) { return $o['start'] . '|' . $o['end']; }, $occurrences );
			$actual = Writer::get_occurrence_starts( $EM_Event->event_id );
			if ( 'pattern' === $recurrence && $expected !== $actual ) {
				$job->log( sprintf( 'Recurring "%s": EM\'s version of the pattern gave %d dates instead of %d, saved as a list of dates instead.', $post->post_title, count( $actual ), count( $expected ) ), 'debug' );
				Writer::delete_event( $EM_Event->event_id );
				$d['recurrence'] = array( 'sets' => Recurrence::from_occurrences( $occurrences ) );
				$recurrence = 'dates';
				$EM_Event = Writer::save_event( $d );
				if ( is_wp_error( $EM_Event ) ) {
					throw new \Exception( $EM_Event->get_error_message() );
				}
				$this->pin_details_block( $EM_Event->post_id, 'event' );
				$actual = Writer::get_occurrence_starts( $EM_Event->event_id );
			}
			if ( $expected !== $actual ) {
				$job->log( sprintf( 'Recurring "%s": %d of %d dates matched.', $post->post_title, count( array_intersect( $expected, $actual ) ), count( $expected ) ), 'error' );
				$job->count( 'events', 'recurrence_mismatch' );
			}
			$job->count( 'events', 'recurring_' . $recurrence );
		}
		$comments = Writer::copy_comments( $post->ID, $EM_Event->post_id );
		$occurrence_map = array();
		if ( $recurrence ) {
			$occurrence_map = $this->map_occurrences( $EM_Event, $occurrences );
		}
		Map::set( static::KEY, 'event', $post->ID, 'event', $EM_Event->event_id, array(
			'slug' => $post->post_name,
			'post_id' => $EM_Event->post_id,
			'occurrences' => $occurrence_map,
			'comments' => count( $comments ),
			'series' => $this->get_series_id( $post->ID ),
		), $post->post_modified_gmt );
		$job->count( 'events', $row ? 'updated' : 'created' );
	}

	protected function event_has_migrated_bookings( $EM_Event ) {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . EM_BOOKINGS_TABLE . ' WHERE event_id=%d', $EM_Event->event_id ) );
	}

	/**
	 * TEC occurrence id => EM occurrence event_id, matched by start, for tickets bought on a specific date and for /event/slug/YYYY-MM-DD/ redirects.
	 */
	protected function map_occurrences( $EM_Event, array $occurrences ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT event_id, event_start_date, event_start_time FROM ' . EM_EVENTS_TABLE . ' WHERE recurrence_set_id IN (SELECT recurrence_set_id FROM ' . EM_EVENT_RECURRENCES_TABLE . ' WHERE event_id=%d) AND event_type=%s', $EM_Event->event_id, 'recurrence' ), ARRAY_A );
		$by_start = array();
		foreach ( $rows as $r ) {
			$by_start[ $r['event_start_date'] . ' ' . $r['event_start_time'] ] = (int) $r['event_id'];
		}
		$map = array();
		foreach ( $occurrences as $o ) {
			if ( isset( $by_start[ $o['start'] ] ) ) {
				$map[ $o['id'] ] = $by_start[ $o['start'] ];
			}
		}
		return $map;
	}

	public function get_series_id( $post_id ) {
		global $wpdb;
		if ( ! $this->has_custom_tables() || $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . 'tec_series_relationships' ) ) !== $wpdb->prefix . 'tec_series_relationships' ) {
			return 0;
		}
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT series_post_id FROM {$wpdb->prefix}tec_series_relationships WHERE event_post_id=%d LIMIT 1", $post_id ) );
	}

	/**
	 * Posts and pages (never events) whose content uses TEC shortcodes or blocks.
	 */
	public function find_content_posts( $after = 0, $limit = 0 ) {
		global $wpdb;
		$sql = "SELECT ID FROM {$wpdb->posts} WHERE post_type NOT IN ('tribe_events','tribe_venue','tribe_organizer','revision','" . esc_sql( EM_POST_TYPE_EVENT ) . "','" . esc_sql( EM_POST_TYPE_LOCATION ) . "') AND post_status NOT IN ('trash','auto-draft','inherit') AND " . static::TEC_CONTENT_SQL;
		// block templates saved under TEC's own theme slug only ever render TEC's views
		$sql .= " AND ID NOT IN (SELECT tr.object_id FROM {$wpdb->term_relationships} tr JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id=tr.term_taxonomy_id JOIN {$wpdb->terms} t ON t.term_id=tt.term_id WHERE tt.taxonomy='wp_theme' AND t.slug='tec')";
		$sql .= $wpdb->prepare( ' AND ID > %d ORDER BY ID', $after );
		if ( $limit ) {
			$sql .= $wpdb->prepare( ' LIMIT %d', $limit );
		}
		return $wpdb->get_col( $sql );
	}

	/**
	 * TEC shortcodes and calendar blocks in the migrated event and venue descriptions, converted once every event is mapped so inline event references resolve. These posts are new, so rollback deleting them is the whole undo.
	 */
	protected function step_descriptions( $cursor, Job $job ) {
		global $wpdb;
		$map = Map::table();
		$like = str_replace( 'post_content', 'p.post_content', static::TEC_CONTENT_SQL );
		$where = $wpdb->prepare( 'm.source=%s AND p.ID > %d AND ', static::KEY, (int) $cursor ) . $like;
		$ids = $wpdb->get_col( "SELECT p.ID FROM {$wpdb->posts} p JOIN " . EM_EVENTS_TABLE . " e ON e.post_id=p.ID JOIN {$map} m ON m.em_type='event' AND m.em_id=e.event_id WHERE $where
			UNION SELECT p.ID FROM {$wpdb->posts} p JOIN " . EM_LOCATIONS_TABLE . " l ON l.post_id=p.ID JOIN {$map} m ON m.em_type='location' AND m.em_id=l.location_id WHERE $where
			ORDER BY ID LIMIT 20" );
		foreach ( $ids as $id ) {
			$content = get_post_field( 'post_content', $id );
			$converted = Content::convert( $content, $this );
			if ( $converted !== $content ) {
				// a direct write, as in pin_details_block(): wp_update_post runs EM's save handlers
				$wpdb->update( $wpdb->posts, array( 'post_content' => $converted ), array( 'ID' => $id ) );
				clean_post_cache( $id );
				$job->count( 'descriptions', 'converted' );
			}
		}
		$last = end( $ids );
		return array( 'done' => count( $ids ) < 20, 'cursor' => $last ?: $cursor );
	}

	protected function step_content( $cursor, Job $job ) {
		$ids = $this->find_content_posts( (int) $cursor, 20 );
		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( Map::get( static::KEY, 'content', $id ) ) {
				$job->count( 'content', 'skipped' );
				continue;
			}
			$converted = Content::convert( $post->post_content, $this );
			if ( $converted === $post->post_content ) {
				continue;
			}
			Map::set( static::KEY, 'content', $id, 'post_content', $id, array( 'original' => $post->post_content ) );
			wp_update_post( array( 'ID' => $id, 'post_content' => wp_slash( $converted ) ) );
			$job->count( 'content', 'converted' );
			$job->log( sprintf( 'Converted TEC shortcodes/blocks in "%s".', $post->post_title ) );
		}
		$last = end( $ids );
		return array( 'done' => count( $ids ) < 20, 'cursor' => $last ?: $cursor );
	}

	protected function step_finish( Job $job ) {
		wp_defer_term_counting( false );
		Tickets::sync_pro( $this, $job );
		if ( $job->option( 'redirects', true ) ) {
			Migrate::enable_redirects( static::KEY );
			if ( ! Map::get( static::KEY, 'option', 'dbem_migrate_redirects' ) ) {
				Map::set( static::KEY, 'option', 'dbem_migrate_redirects', 'option', 0, array( 'option' => 'dbem_migrate_redirects', 'previous' => null ) );
			}
		}
		update_option( 'dbem_flush_needed', 1 );
		$counts = Map::counts_by_type( static::KEY );
		$job->log( 'Mapped objects: ' . wp_json_encode( $counts ) );
		return array( 'done' => true, 'cursor' => null );
	}

	public function resolve_redirect( $path, $query ) {
		return Redirects::resolve( $this, $path, $query );
	}
}

require_once __DIR__ . '/em-migrate-tec-support.php';
