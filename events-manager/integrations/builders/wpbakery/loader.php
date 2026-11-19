<?php
namespace EM\Builders\WPBakery;

use EM\Builders\Units;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers one WPBakery Page Builder element per Events Manager builder unit. Each element is its own prefixed shortcode and renders only through Units::render(). Nothing runs unless WPBakery fires vc_before_init.
 */
class Loader {

	/**
	 * Shortcode tags saved in post content, so the prefix must never change.
	 */
	const PREFIX = 'em_wpb_';

	public static function init() {
		add_action( 'vc_before_init', array( static::class, 'register' ) );
		add_action( 'wp_loaded', array( static::class, 'register_fallback_shortcodes' ) );
		add_action( 'wp_enqueue_scripts', array( static::class, 'enqueue_editor_frame' ) );
		add_action( 'vc_load_iframe_jscss', array( static::class, 'enqueue_editor_frame' ) );
		if ( did_action( 'vc_before_init' ) ) {
			static::register();
		}
	}

	/**
	 * @return bool
	 */
	public static function is_wpbakery_active() {
		return defined( 'WPB_VC_VERSION' ) || function_exists( 'vc_map' );
	}

	/**
	 * @param string $id Unit id.
	 * @return string
	 */
	public static function slug( $id ) {
		return static::PREFIX . str_replace( '-', '_', sanitize_key( $id ) );
	}

	/**
	 * @param string $tag
	 * @return string|null Unit id for a shortcode tag, or null if the tag isn't one of ours.
	 */
	public static function unit_from_slug( $tag ) {
		foreach ( array_keys( Units::get_all() ) as $id ) {
			if ( static::slug( $id ) === $tag ) {
				return $id;
			}
		}
		return null;
	}

	public static function register() {
		static $registered = false;
		if ( $registered || ! function_exists( 'vc_map' ) ) {
			return;
		}
		$registered = true;
		if ( class_exists( 'WPBakeryShortCode' ) ) {
			include_once __DIR__ . '/element.php';
		}
		foreach ( Units::get_all() as $id => $unit ) {
			$slug = static::slug( $id );
			// WPBakery looks up a WPBakeryShortCode_{base} class to render each element.
			if ( class_exists( __NAMESPACE__ . '\Element' ) && ! class_exists( 'WPBakeryShortCode_' . $slug ) ) {
				class_alias( __NAMESPACE__ . '\Element', 'WPBakeryShortCode_' . $slug );
			}
			vc_map( static::map( $id, $unit ) );
		}
	}

	/**
	 * Only needed if WPBakery hasn't registered a handler for an element's tag, so saved content still renders.
	 */
	public static function register_fallback_shortcodes() {
		if ( ! static::is_wpbakery_active() ) {
			return;
		}
		foreach ( array_keys( Units::get_all() ) as $id ) {
			$slug = static::slug( $id );
			if ( ! shortcode_exists( $slug ) ) {
				add_shortcode( $slug, function ( $atts ) use ( $slug ) {
					return Loader::render( $slug, $atts );
				} );
			}
		}
	}

	/**
	 * Build the vc_map() settings for a unit, with every param generated from its field schema.
	 *
	 * @param string $id
	 * @param array  $unit
	 * @return array
	 */
	public static function map( $id, $unit ) {
		$groups = Units::groups();
		$params = array();
		foreach ( $unit['fields'] as $key => $field ) {
			$params[] = static::param( $key, $field, $unit, $groups );
		}
		$params[] = array(
			'type'        => 'textfield',
			'heading'     => __( 'Extra class name', 'events-manager' ),
			'param_name'  => 'el_class',
			'group'       => __( 'Design Options', 'events-manager' ),
			'description' => __( 'Add a CSS class to the wrapper, for custom styles.', 'events-manager' ),
		);
		$params[] = array(
			'type'       => 'css_editor',
			'heading'    => __( 'CSS box', 'events-manager' ),
			'param_name' => 'css',
			'group'      => __( 'Design Options', 'events-manager' ),
		);
		$icon = file_exists( __DIR__ . '/icons/' . sanitize_file_name( $unit['icon'] ) . '.svg' ) ? $unit['icon'] : 'calendar';
		return array(
			'name'        => $unit['title'],
			'base'        => static::slug( $id ),
			'description' => $unit['description'],
			'category'    => 'Events Manager',
			'icon'        => plugins_url( 'icons/' . sanitize_file_name( $icon ) . '.svg', __FILE__ ),
			'params'      => $params,
		);
	}

	/**
	 * @param string $key
	 * @param array  $field
	 * @param array  $unit
	 * @param array  $groups
	 * @return array
	 */
	public static function param( $key, $field, $unit, $groups ) {
		$description = $field['description'];
		$param       = array(
			'heading'    => $field['label'],
			'param_name' => $key,
			'group'      => isset( $groups[ $field['group'] ] ) ? $groups[ $field['group'] ] : $groups['content'],
		);
		switch ( $field['type'] ) {
			case 'toggle':
				if ( Units::to_bool( $field['default'] ) ) {
					// An unchecked checkbox saves nothing, which can't be told apart from "use the default", so default-on toggles are a Yes/No dropdown.
					$param['type']        = 'dropdown';
					$param['value']       = array(
						__( 'Yes', 'events-manager' ) => 'yes',
						__( 'No', 'events-manager' )  => 'no',
					);
					$param['std']         = 'yes';
					$param['save_always'] = true;
				} else {
					$param['type']  = 'checkbox';
					$param['value'] = array( $field['label'] => 'yes' );
				}
				break;
			case 'select':
				$param['type']        = 'dropdown';
				$param['value']       = static::dropdown_options( $field['options'] );
				$param['std']         = (string) $field['default'];
				$param['save_always'] = true;
				break;
			case 'number':
				$param['type']        = 'textfield';
				$param['std']         = (string) $field['default'];
				$param['save_always'] = true;
				if ( isset( $field['min'] ) && isset( $field['max'] ) ) {
					/* translators: 1: minimum, 2: maximum */
					$range       = sprintf( __( 'Between %1$d and %2$d.', 'events-manager' ), $field['min'], $field['max'] );
					$description = trim( $description . ' ' . $range );
				}
				break;
			case 'html':
				// Stored base64 encoded, so markup and quotes in formats never reach wpautop or the shortcode parser.
				$param['type'] = 'textarea_raw_html';
				break;
			default:
				$param['type']        = 'textfield';
				$param['std']         = is_scalar( $field['default'] ) ? (string) $field['default'] : '';
				$param['save_always'] = true;
		}
		if ( isset( $param['std'] ) ) {
			// WPBakery 9 moved the default into settings and flags a bare std as deprecated, but older releases only read std, so both are sent.
			$param['settings'] = array( 'default' => $param['std'] );
		}
		if ( $description !== '' ) {
			$param['description'] = $description;
		}
		if ( $key === 'title' ) {
			$param['admin_label'] = true;
		}
		if ( ! empty( $field['condition'] ) ) {
			$dependency = static::dependency( $field['condition'], $unit );
			if ( $dependency ) {
				$param['dependency'] = $dependency;
			}
		}
		return $param;
	}

	/**
	 * WPBakery dropdowns take label => value pairs.
	 *
	 * @param array $options Field options, value => label.
	 * @return array
	 */
	public static function dropdown_options( $options ) {
		$list = array();
		foreach ( $options as $value => $label ) {
			$label = wp_strip_all_tags( (string) $label );
			if ( isset( $list[ $label ] ) ) {
				$label .= ' (' . $value . ')';
			}
			$list[ $label ] = (string) $value;
		}
		return $list;
	}

	/**
	 * WPBakery dependencies watch one other param, so only the first condition is mapped.
	 *
	 * @param array $condition Field name => required value.
	 * @param array $unit
	 * @return array
	 */
	public static function dependency( $condition, $unit ) {
		foreach ( $condition as $key => $required ) {
			if ( ! isset( $unit['fields'][ $key ] ) ) {
				continue;
			}
			$target = $unit['fields'][ $key ];
			if ( $target['type'] === 'toggle' ) {
				$on_by_default = Units::to_bool( $target['default'] );
				if ( Units::to_bool( $required ) ) {
					return array( 'element' => $key, 'value' => array( 'yes' ) );
				}
				return $on_by_default ? array( 'element' => $key, 'value' => array( 'no' ) ) : array( 'element' => $key, 'is_empty' => true );
			}
			return array( 'element' => $key, 'value' => array_map( 'strval', (array) $required ) );
		}
		return array();
	}

	/**
	 * WPBakery stores textarea_raw_html values as base64 of the URL-encoded text. Anything that doesn't decode to clean text is treated as a plain value, such as a shortcode written by hand.
	 *
	 * @param string $value
	 * @return string
	 */
	public static function decode_html( $value ) {
		$decoded = base64_decode( $value, true );
		if ( $decoded === false ) {
			return $value;
		}
		$decoded = rawurldecode( $decoded );
		if ( ! mb_check_encoding( $decoded, 'UTF-8' ) || preg_match( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $decoded ) ) {
			return $value;
		}
		return $decoded;
	}

	/**
	 * Turn shortcode attributes into unit settings for Units::render(), which does all validation and sanitizing.
	 *
	 * @param array $unit
	 * @param array $atts
	 * @return array
	 */
	public static function settings( $unit, $atts ) {
		$settings = array();
		foreach ( $unit['fields'] as $key => $field ) {
			if ( ! isset( $atts[ $key ] ) || ! is_scalar( $atts[ $key ] ) ) {
				continue;
			}
			$value = (string) $atts[ $key ];
			if ( $field['type'] === 'html' ) {
				$value = static::decode_html( $value );
			} else {
				// WPBakery saves a double quote as two backticks inside attribute values.
				$value = str_replace( '``', '"', $value );
			}
			$settings[ $key ] = $value;
		}
		return $settings;
	}

	/**
	 * @param string $tag  Shortcode tag.
	 * @param array  $atts Shortcode attributes.
	 * @return string
	 */
	public static function render( $tag, $atts ) {
		$id = static::unit_from_slug( $tag );
		if ( ! $id ) {
			return '';
		}
		$atts  = is_array( $atts ) ? $atts : array();
		$class = 'em-builder-wpbakery';
		if ( ! empty( $atts['el_class'] ) ) {
			$class .= ' ' . $atts['el_class'];
		}
		if ( ! empty( $atts['css'] ) && function_exists( 'vc_shortcode_custom_css_class' ) ) {
			$class .= ' ' . vc_shortcode_custom_css_class( $atts['css'] );
		}
		static::enqueue_assets();
		return Units::render( $id, static::settings( Units::get( $id ), $atts ), array( 'wrapper_class' => $class ) );
	}

	/**
	 * EM's own enqueue can skip a page when the "limit JS/CSS" settings are on, which would leave an element unstyled on any page EM doesn't know about.
	 */
	public static function enqueue_assets() {
		if ( wp_doing_ajax() ) {
			return;
		}
		if ( ! wp_script_is( 'events-manager', 'enqueued' ) ) {
			\EM\Scripts_and_Styles::enqueue_scripts( array( 'jquery' => 'jquery' ) );
		}
		if ( ! wp_style_is( 'events-manager', 'enqueued' ) ) {
			\EM\Scripts_and_Styles::enqueue_public_styles();
		}
	}

	/**
	 * The frontend editor adds and re-renders elements over AJAX inside its preview frame, so EM's assets must already be on the frame page even when no EM element is on it yet.
	 */
	public static function enqueue_editor_frame() {
		if ( ! function_exists( 'vc_is_page_editable' ) || ! vc_is_page_editable() || wp_script_is( 'em-builders-wpbakery', 'enqueued' ) ) {
			return;
		}
		static::enqueue_assets();
		wp_enqueue_script( 'em-builders-wpbakery', plugins_url( 'frame.js', __FILE__ ), array( 'jquery' ), EM_VERSION, true );
	}
}
Loader::init();
