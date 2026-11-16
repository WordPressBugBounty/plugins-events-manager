<?php
namespace EM;

/**
 * Event organizers, a taxonomy so one organizer is shared across events and an event can have several. Contact details are term meta.
 *
 * Placeholders on events: #_EVENTORGANIZERS (linked names), #_EVENTORGANIZERNAMES (plain names), #_EVENTORGANIZERSLIST (names with contact details),
 * conditionals {has_organizers} and {no_organizers}.
 */
class Organizers {

	const TAXONOMY = 'event-organizers';
	const META_FIELDS = array( 'organizer_email', 'organizer_phone', 'organizer_website' );

	public static function init() {
		if ( ! defined( 'EM_TAXONOMY_ORGANIZER' ) ) {
			define( 'EM_TAXONOMY_ORGANIZER', static::TAXONOMY );
		}
		add_action( 'em_taxonomies_init', array( static::class, 'em_taxonomies_init' ), 5 );
		add_action( 'pre_get_posts', array( static::class, 'archive_query' ) );
		if ( ! static::is_enabled() ) {
			return;
		}
		add_filter( 'em_event_output_placeholder', array( static::class, 'placeholder' ), 10, 3 );
		add_filter( 'em_event_output_show_condition', array( static::class, 'condition' ), 10, 4 );
		add_action( static::TAXONOMY . '_add_form_fields', array( static::class, 'add_form_fields' ) );
		add_action( static::TAXONOMY . '_edit_form_fields', array( static::class, 'edit_form_fields' ) );
		add_action( 'created_' . static::TAXONOMY, array( static::class, 'save_term_meta' ) );
		add_action( 'edited_' . static::TAXONOMY, array( static::class, 'save_term_meta' ) );
	}

	/**
	 * Lists an organizer's upcoming events on its archive, soonest first, as EM does for categories and tags; WordPress would otherwise look for blog posts, since events are left out of search by default.
	 */
	public static function archive_query( $wp_query ) {
		if ( is_admin() || ! $wp_query->is_main_query() || ! $wp_query->is_tax( static::TAXONOMY ) ) {
			return;
		}
		$wp_query->set( 'post_type', EM_POST_TYPE_EVENT );
		$meta_query = (array) $wp_query->get( 'meta_query' );
		$meta_query[] = array( 'key' => '_event_end', 'value' => current_time( 'mysql' ), 'compare' => '>=', 'type' => 'DATETIME' );
		$wp_query->set( 'meta_query', $meta_query );
		$wp_query->set( 'meta_key', '_event_start' );
		$wp_query->set( 'meta_type', 'DATETIME' );
		$wp_query->set( 'orderby', 'meta_value' );
		$wp_query->set( 'order', 'ASC' );
	}

	public static function is_enabled() {
		return (bool) get_option( 'dbem_organizers_enabled' );
	}

	public static function em_taxonomies_init() {
		if ( ! static::is_enabled() ) {
			return;
		}
		Taxonomies::$taxonomies[ static::TAXONOMY ] = array(
			'taxonomy' => static::TAXONOMY,
			'shortname' => 'organizers',
			'label' => 'Organizers',
			'singular_label' => 'Organizer',
			'slug' => get_option( 'dbem_taxonomy_organizer_slug', 'events/organizers' ),
			'hierarchical' => false,
		);
		Archetypes::$event['taxonomies'][] = static::TAXONOMY;
		// taxonomies are declared while EM loads, before translations can load, so the labels are translated just before registration on init
		add_action( 'em_archetypes_register_post_types', array( static::class, 'translate_labels' ), 9 );
	}

	public static function translate_labels() {
		if ( isset( Taxonomies::$taxonomies[ static::TAXONOMY ] ) ) {
			Taxonomies::$taxonomies[ static::TAXONOMY ]['label'] = __( 'Organizers', 'events-manager' );
			Taxonomies::$taxonomies[ static::TAXONOMY ]['singular_label'] = __( 'Organizer', 'events-manager' );
		}
	}

	/**
	 * @return \WP_Term[]
	 */
	public static function get_event_organizers( $EM_Event ) {
		$post_id = $EM_Event->post_id;
		if ( ! $post_id || ! taxonomy_exists( static::TAXONOMY ) ) {
			return array();
		}
		$terms = get_the_terms( $post_id, static::TAXONOMY );
		return is_array( $terms ) ? $terms : array();
	}

	public static function get_meta( $term_id, $field ) {
		return (string) get_term_meta( $term_id, $field, true );
	}

	public static function placeholder( $replace, $EM_Event, $result ) {
		switch ( $result ) {
			case '#_EVENTORGANIZERS':
				$links = array();
				foreach ( static::get_event_organizers( $EM_Event ) as $term ) {
					$links[] = '<a href="' . esc_url( get_term_link( $term ) ) . '">' . esc_html( $term->name ) . '</a>';
				}
				return implode( ', ', $links );
			case '#_EVENTORGANIZERNAMES':
				return esc_html( implode( ', ', wp_list_pluck( static::get_event_organizers( $EM_Event ), 'name' ) ) );
			case '#_EVENTORGANIZERSLIST':
				$items = array();
				foreach ( static::get_event_organizers( $EM_Event ) as $term ) {
					$details = array();
					$email = static::get_meta( $term->term_id, 'organizer_email' );
					$phone = static::get_meta( $term->term_id, 'organizer_phone' );
					$website = static::get_meta( $term->term_id, 'organizer_website' );
					if ( $email ) {
						$details[] = '<a href="mailto:' . esc_attr( antispambot( $email ) ) . '">' . esc_html( antispambot( $email ) ) . '</a>';
					}
					if ( $phone ) {
						$details[] = esc_html( $phone );
					}
					if ( $website ) {
						$details[] = '<a href="' . esc_url( $website ) . '">' . esc_html( preg_replace( '#^https?://#', '', untrailingslashit( $website ) ) ) . '</a>';
					}
					$items[] = '<li><a href="' . esc_url( get_term_link( $term ) ) . '">' . esc_html( $term->name ) . '</a>' . ( $details ? ' &mdash; ' . implode( ' &middot; ', $details ) : '' ) . '</li>';
				}
				return $items ? '<ul class="em-event-organizers">' . implode( '', $items ) . '</ul>' : '';
		}
		return $replace;
	}

	public static function condition( $show, $condition, $match, $EM_Event ) {
		if ( 'has_organizers' === $condition ) {
			return (bool) static::get_event_organizers( $EM_Event );
		}
		if ( 'no_organizers' === $condition ) {
			return ! static::get_event_organizers( $EM_Event );
		}
		return $show;
	}

	protected static function field_labels() {
		return array(
			'organizer_email' => __( 'Email', 'events-manager' ),
			'organizer_phone' => __( 'Phone', 'events-manager' ),
			'organizer_website' => __( 'Website', 'events-manager' ),
		);
	}

	public static function add_form_fields() {
		wp_nonce_field( 'em_organizer_meta', 'em_organizer_meta_nonce' );
		foreach ( static::field_labels() as $field => $label ) {
			$type = 'organizer_email' === $field ? 'email' : ( 'organizer_website' === $field ? 'url' : 'text' );
			echo '<div class="form-field term-' . esc_attr( $field ) . '-wrap"><label for="' . esc_attr( $field ) . '">' . esc_html( $label ) . '</label><input type="' . $type . '" name="' . esc_attr( $field ) . '" id="' . esc_attr( $field ) . '" value=""></div>';
		}
	}

	public static function edit_form_fields( $term ) {
		wp_nonce_field( 'em_organizer_meta', 'em_organizer_meta_nonce' );
		foreach ( static::field_labels() as $field => $label ) {
			$type = 'organizer_email' === $field ? 'email' : ( 'organizer_website' === $field ? 'url' : 'text' );
			echo '<tr class="form-field term-' . esc_attr( $field ) . '-wrap"><th scope="row"><label for="' . esc_attr( $field ) . '">' . esc_html( $label ) . '</label></th><td><input type="' . $type . '" name="' . esc_attr( $field ) . '" id="' . esc_attr( $field ) . '" value="' . esc_attr( static::get_meta( $term->term_id, $field ) ) . '"></td></tr>';
		}
	}

	public static function save_term_meta( $term_id ) {
		if ( empty( $_POST['em_organizer_meta_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['em_organizer_meta_nonce'] ) ), 'em_organizer_meta' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_term', $term_id ) ) {
			return;
		}
		foreach ( static::META_FIELDS as $field ) {
			if ( ! isset( $_POST[ $field ] ) ) {
				continue;
			}
			$value = wp_unslash( $_POST[ $field ] );
			if ( 'organizer_email' === $field ) {
				$value = sanitize_email( $value );
			} elseif ( 'organizer_website' === $field ) {
				$value = esc_url_raw( $value );
			} else {
				$value = sanitize_text_field( $value );
			}
			if ( '' === $value ) {
				delete_term_meta( $term_id, $field );
			} else {
				update_term_meta( $term_id, $field, $value );
			}
		}
	}
}
Organizers::init();
