<?php
namespace EM\Builders;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builder-agnostic definitions of the Events Manager widgets and blocks, so each page builder adapter maps one schema to its own controls and every builder renders through the same widget code as the classic widgets and Gutenberg blocks.
 *
 * Field types: text, html, number, select, toggle. Field groups: content, advanced. Adapters must pass settings through Units::render(), which validates selects, sanitizes text and HTML, and drops empty values so widget defaults apply.
 */
class Units {

	/**
	 * @var array|null
	 */
	protected static $units;

	/**
	 * All units, keyed by unit id. Built lazily because option labels are translated.
	 *
	 * @return array
	 */
	public static function get_all() {
		if ( static::$units === null ) {
			$units = array(
				'events'    => static::events_unit(),
				'calendar'  => static::calendar_unit(),
				'locations' => static::locations_unit(),
			);
			if ( ! get_option( 'dbem_locations_enabled', true ) ) {
				unset( $units['locations'] );
			}
			$units = apply_filters( 'em_builders_units', $units );
			foreach ( $units as $id => $unit ) {
				if ( empty( $unit['widget'] ) || ! class_exists( $unit['widget'] ) ) {
					unset( $units[ $id ] );
					continue;
				}
				$units[ $id ] = array_merge( array(
					'id'          => $id,
					'title'       => $id,
					'description' => '',
					'icon'        => 'calendar',
					'keywords'    => array(),
					'fields'      => array(),
				), $unit );
				foreach ( $units[ $id ]['fields'] as $key => $field ) {
					$units[ $id ]['fields'][ $key ] = array_merge( array(
						'type'        => 'text',
						'label'       => $key,
						'default'     => '',
						'group'       => 'content',
						'description' => '',
					), $field );
				}
			}
			static::$units = $units;
		}
		return static::$units;
	}

	/**
	 * @param string $id
	 * @return array|null
	 */
	public static function get( $id ) {
		$units = static::get_all();
		return isset( $units[ $id ] ) ? $units[ $id ] : null;
	}

	/**
	 * Render a unit with settings saved by a builder.
	 *
	 * @param string $id       Unit id.
	 * @param array  $settings Raw settings from the builder, keyed by field name.
	 * @param array  $args     Optional widget args (before_title etc.) and 'wrapper_class'.
	 * @return string
	 */
	public static function render( $id, $settings = array(), $args = array() ) {
		$unit = static::get( $id );
		if ( ! $unit ) {
			return '';
		}
		$instance = static::normalize( $unit, (array) $settings );
		$instance = apply_filters( 'em_builders_render_instance', $instance, $id, $settings, $args );
		$html     = static::render_widget( $unit['widget'], $instance, $args );
		$class    = 'em-builder-unit em-builder-unit-' . sanitize_html_class( $id );
		if ( ! empty( $args['wrapper_class'] ) ) {
			$class .= ' ' . implode( ' ', array_map( 'sanitize_html_class', preg_split( '/\s+/', $args['wrapper_class'] ) ) );
		}
		return '<div class="' . esc_attr( $class ) . '">' . $html . '</div>';
	}

	/**
	 * Run a WP_Widget subclass and return its output, shared with the Gutenberg blocks.
	 *
	 * @param string $widget_class
	 * @param array  $instance Already-normalized widget instance; empty values are dropped so widget defaults apply.
	 * @param array  $args
	 * @return string
	 */
	public static function render_widget( $widget_class, $instance, $args = array() ) {
		if ( ! class_exists( $widget_class ) ) {
			return '';
		}
		$widget = new $widget_class();
		$args   = array_merge( array(
			'before_widget' => '',
			'after_widget'  => '',
			'before_title'  => '<h3 class="em-builder-unit-title">',
			'after_title'   => '</h3>',
		), array_intersect_key( (array) $args, array_flip( array( 'before_widget', 'after_widget', 'before_title', 'after_title' ) ) ) );
		$clean  = array();
		foreach ( (array) $instance as $key => $value ) {
			if ( $value === '' || $value === null ) {
				continue;
			}
			$clean[ $key ] = $value;
		}
		// Without the v6 flag the events and locations widgets treat the instance as a pre-v6 widget and replace any custom format with the default.
		if ( ! isset( $clean['v6'] ) && class_exists( 'EM_Options' ) ) {
			$clean['v6'] = \EM_Options::get( 'v6', false );
		}
		ob_start();
		$widget->widget( $args, $clean );
		$html = ob_get_clean();
		// Indented template output picks up stray <p> and <br> from wpautop when it lands inside post content.
		return preg_replace( '/>\s+</', '><', $html );
	}

	/**
	 * Turn raw builder settings into a widget instance.
	 *
	 * @param array $unit
	 * @param array $settings
	 * @return array
	 */
	public static function normalize( $unit, $settings ) {
		$instance = array();
		foreach ( $unit['fields'] as $key => $field ) {
			if ( ! array_key_exists( $key, $settings ) ) {
				continue;
			}
			$value = $settings[ $key ];
			switch ( $field['type'] ) {
				case 'toggle':
					$instance[ $key ] = static::to_bool( $value ) ? 1 : 0;
					break;
				case 'number':
					if ( is_array( $value ) && isset( $value['size'] ) ) {
						$value = $value['size'];
					}
					if ( $value === '' || $value === null || ! is_numeric( $value ) ) {
						break;
					}
					$value = (int) $value;
					if ( isset( $field['min'] ) && $value < $field['min'] ) {
						$value = $field['min'];
					}
					if ( isset( $field['max'] ) && $value > $field['max'] ) {
						$value = $field['max'];
					}
					$instance[ $key ] = $value;
					break;
				case 'select':
					$value = is_scalar( $value ) ? (string) $value : '';
					if ( array_key_exists( $value, $field['options'] ) ) {
						$instance[ $key ] = $value;
					}
					break;
				case 'html':
					if ( is_string( $value ) && $value !== '' ) {
						$value = apply_filters( 'em_builders_kses_html', true, $key, $unit['id'] ) ? wp_kses_post( $value ) : $value;
						// Header and footer formats open and close a wrapper between them (<ul> then </ul>), so balancing each alone would close the header and orphan the footer.
						$instance[ $key ] = in_array( $key, array( 'format_header', 'format_footer' ), true ) ? $value : force_balance_tags( $value );
					}
					break;
				default:
					if ( is_scalar( $value ) && (string) $value !== '' ) {
						$value = (string) $value;
						if ( ! empty( $field['pattern'] ) && ! preg_match( $field['pattern'], $value ) ) {
							break;
						}
						$instance[ $key ] = wp_kses_post( $value );
					}
			}
		}
		return $instance;
	}

	/**
	 * Builders store booleans as 'yes', 'on', 'true', '1' or real booleans.
	 *
	 * @param mixed $value
	 * @return bool
	 */
	public static function to_bool( $value ) {
		if ( is_bool( $value ) ) {
			return $value;
		}
		return in_array( strtolower( (string) $value ), array( '1', 'yes', 'on', 'true' ), true );
	}

	/**
	 * Default values for every field of a unit, for builders that need defaults declared up front.
	 *
	 * @param string $id
	 * @return array
	 */
	public static function defaults( $id ) {
		$unit = static::get( $id );
		if ( ! $unit ) {
			return array();
		}
		return wp_list_pluck( $unit['fields'], 'default' );
	}

	/**
	 * Field group labels, so adapters can build one section per group.
	 *
	 * @return array
	 */
	public static function groups() {
		return array(
			'content'  => __( 'Content', 'events-manager' ),
			'advanced' => __( 'Formatting', 'events-manager' ),
		);
	}

	protected static function archetype_field() {
		if ( ! class_exists( '\EM\Archetypes' ) || empty( \EM\Archetypes::$types ) ) {
			return array();
		}
		$options = array( '' => __( 'Default', 'events-manager' ) );
		foreach ( \EM\Archetypes::$types as $type => $archetype ) {
			$options[ $type ] = isset( $archetype['label'] ) ? $archetype['label'] : $type;
		}
		return array(
			'event_archetype' => array(
				'type'    => 'select',
				'label'   => __( 'Archetype', 'events-manager' ),
				'options' => $options,
				'default' => '',
			),
		);
	}

	protected static function category_field() {
		return array(
			'category' => array(
				'type'        => 'text',
				'label'       => __( 'Category IDs', 'events-manager' ),
				'description' => __( '1,2,3 or 2 (0 = all)', 'events-manager' ),
				'default'     => '',
				'pattern'     => '/^[\p{L}\p{N}\s,_-]*$/u',
			),
		);
	}

	protected static function order_field() {
		return array(
			'order' => array(
				'type'    => 'select',
				'label'   => __( 'Order', 'events-manager' ),
				'options' => array(
					'ASC'  => __( 'Ascending', 'events-manager' ),
					'DESC' => __( 'Descending', 'events-manager' ),
				),
				'default' => 'ASC',
			),
		);
	}

	protected static function events_unit() {
		$orderby = apply_filters( 'em_settings_events_default_orderby_ddm', array(
			'event_start_date,event_start_time,event_name' => __( 'start date, start time, event name', 'events-manager' ),
			'event_name,event_start_date,event_start_time' => __( 'name, start date, start time', 'events-manager' ),
			'event_name,event_end_date,event_end_time'     => __( 'name, end date, end time', 'events-manager' ),
			'event_end_date,event_end_time,event_name'     => __( 'end date, end time, event name', 'events-manager' ),
		) );
		$fields  = array(
			'title' => array(
				'type'    => 'text',
				'label'   => __( 'Title', 'events-manager' ),
				'default' => '',
			),
			'limit' => array(
				'type'    => 'number',
				'label'   => __( 'Number of events', 'events-manager' ),
				'default' => 5,
				'min'     => 1,
				'max'     => 100,
			),
			'scope' => array(
				'type'    => 'select',
				'label'   => __( 'Scope', 'events-manager' ),
				'options' => em_get_scopes(),
				'default' => 'future',
			),
		);
		$fields += static::archetype_field();
		$fields += array(
			'orderby' => array(
				'type'    => 'select',
				'label'   => __( 'Order By', 'events-manager' ),
				'options' => $orderby,
				'default' => 'event_start_date,event_start_time,event_name',
			),
		);
		$fields += static::order_field();
		$fields += static::category_field();
		$fields += array(
			'all_events'      => array(
				'type'    => 'toggle',
				'label'   => __( 'Show all events link', 'events-manager' ),
				'default' => false,
			),
			'all_events_text' => array(
				'type'      => 'text',
				'label'     => __( 'All events link text', 'events-manager' ),
				'default'   => '',
				'condition' => array( 'all_events' => true ),
			),
			'no_events_text'  => array(
				'type'    => 'html',
				'label'   => __( 'No events message', 'events-manager' ),
				'default' => '',
				'group'   => 'advanced',
			),
			'format_header'   => array(
				'type'    => 'html',
				'label'   => __( 'List header format', 'events-manager' ),
				'default' => '',
				'group'   => 'advanced',
			),
			'format'          => array(
				'type'        => 'html',
				'label'       => __( 'List item format', 'events-manager' ),
				'description' => __( 'Leave blank to use the default format. Accepts event placeholders such as #_EVENTLINK.', 'events-manager' ),
				'default'     => '',
				'group'       => 'advanced',
			),
			'format_footer'   => array(
				'type'    => 'html',
				'label'   => __( 'List footer format', 'events-manager' ),
				'default' => '',
				'group'   => 'advanced',
			),
		);
		return array(
			'title'       => __( 'Events List', 'events-manager' ),
			'description' => __( 'Display a list of events with filtering and ordering.', 'events-manager' ),
			'icon'        => 'list',
			'keywords'    => array( 'events', 'list', 'upcoming' ),
			'widget'      => 'EM_Widget',
			'block'       => 'events-manager/events',
			'fields'      => $fields,
		);
	}

	protected static function calendar_unit() {
		$sizes  = array(
			'auto'   => __( 'Responsive', 'events-manager' ),
			'large'  => __( 'Large', 'events-manager' ),
			'medium' => __( 'Medium', 'events-manager' ),
			'small'  => __( 'Small', 'events-manager' ),
		);
		$styles = array(
			'pill' => __( 'Pills', 'events-manager' ),
			'dot'  => __( 'Single dot', 'events-manager' ),
			'dots' => __( 'Multiple dots', 'events-manager' ),
			'ring' => __( 'Rings', 'events-manager' ),
		);
		$fields = array(
			'title'                => array(
				'type'    => 'text',
				'label'   => __( 'Title', 'events-manager' ),
				'default' => '',
			),
			'scope'                => array(
				'type'    => 'select',
				'label'   => __( 'Events shown', 'events-manager' ),
				'options' => array(
					'all'    => __( 'All events', 'events-manager' ),
					'future' => __( 'Future events only', 'events-manager' ),
				),
				'default' => 'all',
			),
			'long_events'          => array(
				'type'    => 'toggle',
				'label'   => __( 'Show Long Events?', 'events-manager' ),
				'default' => false,
			),
			'calendar_size'        => array(
				'type'    => 'select',
				'label'   => __( 'Calendar Size', 'events-manager' ),
				'options' => $sizes,
				'default' => em_get_option( 'dbem_calendar_default', 'auto' ),
			),
			'calendar_event_style' => array(
				'type'    => 'select',
				'label'   => __( 'Event Style', 'events-manager' ),
				'options' => $styles,
				'default' => em_get_option( 'dbem_calendar_event_style', 'pill' ),
			),
			'limit'                => array(
				'type'        => 'number',
				'label'       => __( 'Events per day', 'events-manager' ),
				'description' => __( 'Max events shown per calendar day (0 = no limit).', 'events-manager' ),
				'default'     => (int) em_get_option( 'dbem_display_calendar_events_limit', 3 ),
				'min'         => 0,
				'max'         => 50,
			),
		);
		$fields += static::category_field();
		$fields += static::archetype_field();
		return array(
			'title'       => __( 'Events Calendar', 'events-manager' ),
			'description' => __( 'Display events in a monthly calendar grid.', 'events-manager' ),
			'icon'        => 'calendar',
			'keywords'    => array( 'events', 'calendar', 'schedule' ),
			'widget'      => 'EM_Widget_Calendar',
			'block'       => 'events-manager/calendar',
			'fields'      => $fields,
		);
	}

	protected static function locations_unit() {
		$fields = array(
			'title'   => array(
				'type'    => 'text',
				'label'   => __( 'Title', 'events-manager' ),
				'default' => '',
			),
			'limit'   => array(
				'type'    => 'number',
				'label'   => __( 'Show how many locations?', 'events-manager' ),
				'default' => 5,
				'min'     => 1,
				'max'     => 100,
			),
			'scope'   => array(
				'type'    => 'select',
				'label'   => __( 'Scope of the locations?', 'events-manager' ),
				'options' => em_get_scopes(),
				'default' => 'future',
			),
			'orderby' => array(
				'type'    => 'select',
				'label'   => __( 'Order By', 'events-manager' ),
				'options' => array(
					'event_start_date,event_start_time,location_name' => __( 'Event start date/time, location name', 'events-manager' ),
					'location_name'                                    => __( 'Location name', 'events-manager' ),
				),
				'default' => 'event_start_date,event_start_time,location_name',
			),
		);
		$fields += static::order_field();
		$fields += array(
			'no_locations_text' => array(
				'type'    => 'html',
				'label'   => __( 'No locations message', 'events-manager' ),
				'default' => '',
				'group'   => 'advanced',
			),
			'format_header'     => array(
				'type'    => 'html',
				'label'   => __( 'List header format', 'events-manager' ),
				'default' => '',
				'group'   => 'advanced',
			),
			'format'            => array(
				'type'        => 'html',
				'label'       => __( 'List item format', 'events-manager' ),
				'description' => __( 'Leave blank to use the default format. Accepts location placeholders such as #_LOCATIONLINK.', 'events-manager' ),
				'default'     => '',
				'group'       => 'advanced',
			),
			'format_footer'     => array(
				'type'    => 'html',
				'label'   => __( 'List footer format', 'events-manager' ),
				'default' => '',
				'group'   => 'advanced',
			),
		);
		return array(
			'title'       => __( 'Event Locations', 'events-manager' ),
			'description' => __( 'Display a list of event locations.', 'events-manager' ),
			'icon'        => 'location',
			'keywords'    => array( 'locations', 'venues', 'places' ),
			'widget'      => 'EM_Locations_Widget',
			'block'       => 'events-manager/locations',
			'fields'      => $fields,
		);
	}
}
