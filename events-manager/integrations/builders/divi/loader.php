<?php
namespace EM\Builders\Divi;

use EM\Builders\Units;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers one Divi module per Events Manager builder unit, as a Divi 4 module (ET_Builder_Module, which Divi 5 also runs through its backward-compatibility layer) and as a native Divi 5 module. Only Divi's own actions are hooked, so nothing runs unless the Divi theme or Divi Builder plugin is active.
 */
class Loader {

	/**
	 * Divi 4 module slugs, also the shortcode names saved in post content, so they must never change.
	 */
	const PREFIX = 'em_divi_';

	const AJAX_ACTION = 'em_divi_preview';

	public static function init() {
		add_action( 'et_builder_ready', array( static::class, 'register_divi4' ) );
		add_action( 'wp_enqueue_scripts', array( static::class, 'enqueue_divi4_builder' ) );
		add_action( 'divi_module_library_modules_dependency_tree', array( static::class, 'register_divi5' ) );
		add_action( 'divi_visual_builder_assets_before_enqueue_scripts', array( static::class, 'enqueue_divi5_builder' ) );
		add_action( 'init', array( static::class, 'register_ajax' ) );
	}

	/**
	 * Theme constants are set once the theme loads, after plugins_loaded, so this is only reliable from init onwards.
	 *
	 * @return bool
	 */
	public static function is_divi_active() {
		return defined( 'ET_BUILDER_THEME' ) || defined( 'ET_BUILDER_PLUGIN_ACTIVE' ) || function_exists( 'et_builder_d5_enabled' );
	}

	/**
	 * @return bool
	 */
	public static function is_divi5() {
		return function_exists( 'et_builder_d5_enabled' ) && et_builder_d5_enabled();
	}

	public static function register_ajax() {
		if ( static::is_divi_active() ) {
			add_action( 'wp_ajax_' . static::AJAX_ACTION, array( static::class, 'ajax_preview' ) );
		}
	}

	/**
	 * @param string $id Unit id.
	 * @return string
	 */
	public static function slug( $id ) {
		return static::PREFIX . str_replace( '-', '_', sanitize_key( $id ) );
	}

	/**
	 * Divi stores select values as strings and rejects numeric option keys, so integer keys are prefixed on the way in and restored by decode_option().
	 *
	 * @param int|string $key
	 * @return string
	 */
	public static function encode_option( $key ) {
		return is_int( $key ) ? 'k' . $key : (string) $key;
	}

	/**
	 * @param string $value
	 * @param array  $options
	 * @return int|string
	 */
	public static function decode_option( $value, $options ) {
		if ( ! array_key_exists( $value, $options ) && preg_match( '/^k(-?\d+)$/', $value, $match ) && array_key_exists( (int) $match[1], $options ) ) {
			return (int) $match[1];
		}
		return $value;
	}

	/**
	 * Divi 4 saves square brackets in attributes as entities so they can't close the shortcode, which would otherwise break shortcodes and bracketed text inside EM formats, and angle brackets arrive encoded by protect_attributes(). Layouts converted to Divi 5 may carry the same encoding.
	 *
	 * @param mixed $value
	 * @return mixed
	 */
	public static function decode_divi_value( $value ) {
		return is_string( $value ) ? str_replace( array( '&#091;', '&#91;', '&#093;', '&#93;', '%3C', '%3E' ), array( '[', '[', ']', ']', '<', '>' ), $value ) : $value;
	}

	/**
	 * @param array $options
	 * @return array
	 */
	public static function encode_options( $options ) {
		$encoded = array();
		foreach ( $options as $key => $label ) {
			$encoded[ static::encode_option( $key ) ] = $label;
		}
		return $encoded;
	}

	/**
	 * A field default in the value format Divi saves, so an untouched field matches what Divi treats as its default.
	 *
	 * @param array $field
	 * @return string
	 */
	public static function default_value( $field ) {
		switch ( $field['type'] ) {
			case 'toggle':
				return Units::to_bool( $field['default'] ) ? 'on' : 'off';
			case 'select':
				return static::encode_option( $field['default'] );
		}
		return is_scalar( $field['default'] ) ? (string) $field['default'] : '';
	}

	/**
	 * A field's schema condition as the values Divi stores for the fields it depends on.
	 *
	 * @param array $unit
	 * @param array $field
	 * @return array Field name => saved value that shows this field.
	 */
	public static function condition( $unit, $field ) {
		$condition = array();
		foreach ( empty( $field['condition'] ) ? array() : $field['condition'] as $depends => $value ) {
			$type                  = isset( $unit['fields'][ $depends ]['type'] ) ? $unit['fields'][ $depends ]['type'] : 'text';
			$condition[ $depends ] = $type === 'toggle' ? ( Units::to_bool( $value ) ? 'on' : 'off' ) : static::encode_option( $value );
		}
		return $condition;
	}

	/**
	 * Turn flat values saved by either Divi version back into unit settings for Units::render(), which does all validation and sanitizing.
	 *
	 * @param array $unit
	 * @param array $values Field name => value as Divi saved it.
	 * @return array
	 */
	public static function settings( $unit, $values ) {
		$settings = array();
		foreach ( $unit['fields'] as $key => $field ) {
			if ( ! isset( $values[ $key ] ) || ! is_scalar( $values[ $key ] ) ) {
				continue;
			}
			$value = (string) $values[ $key ];
			if ( $field['type'] === 'select' ) {
				$value = static::decode_option( $value, $field['options'] );
			} elseif ( $field['type'] === 'number' ) {
				// Divi range fields can carry a unit such as "5px".
				$value = preg_replace( '/[^0-9\-]/', '', $value );
			}
			$settings[ $key ] = $value;
		}
		return $settings;
	}

	/**
	 * @param string $id
	 * @param array  $values
	 * @return string
	 */
	public static function render( $id, $values ) {
		$unit = Units::get( $id );
		if ( ! $unit ) {
			return '';
		}
		static::enqueue_assets();
		return Units::render( $id, static::settings( $unit, $values ), array( 'wrapper_class' => 'em-builder-divi' ) );
	}

	/**
	 * Data both builder scripts need: one entry per unit with its module slug and field names, so the generic preview component can be registered for every unit.
	 *
	 * @return array
	 */
	public static function builder_data() {
		$units = array();
		foreach ( Units::get_all() as $id => $unit ) {
			$conditions = array();
			foreach ( $unit['fields'] as $key => $field ) {
				if ( ! empty( $field['condition'] ) ) {
					$conditions[ $key ] = static::condition( $unit, $field );
				}
			}
			$units[ $id ] = array(
				'slug'       => static::slug( $id ),
				'title'      => $unit['title'],
				'icon'       => $unit['icon'],
				'fields'     => array_keys( $unit['fields'] ),
				'conditions' => (object) $conditions,
			);
		}
		return array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'action'  => static::AJAX_ACTION,
			'nonce'   => wp_create_nonce( static::AJAX_ACTION ),
			'units'   => $units,
			'loading' => __( 'Loading...', 'events-manager' ),
		);
	}

	/**
	 * Server-rendered preview for both Visual Builders, through the same Units::render() path as the front end.
	 */
	public static function ajax_preview() {
		check_ajax_referer( static::AJAX_ACTION, 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( null, 403 );
		}
		$id     = isset( $_POST['unit'] ) ? sanitize_key( wp_unslash( $_POST['unit'] ) ) : '';
		$values = isset( $_POST['settings'] ) ? json_decode( wp_unslash( $_POST['settings'] ), true ) : array();
		if ( ! Units::get( $id ) ) {
			wp_send_json_error( null, 400 );
		}
		wp_send_json_success( array( 'html' => static::render( $id, is_array( $values ) ? $values : array() ) ) );
	}

	/**
	 * EM's own enqueue can skip a page when the "limit JS/CSS" settings are on, which would leave a module unstyled on any page EM doesn't know about.
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

	public static function register_divi4() {
		if ( ! class_exists( 'ET_Builder_Module' ) ) {
			return;
		}
		include_once __DIR__ . '/divi4.php';
		foreach ( array_keys( Units::get_all() ) as $id ) {
			new Divi4_Module( $id );
		}
		add_filter( 'the_content', array( static::class, 'protect_attributes' ), 0 );
		add_filter( 'et_builder_render_layout', array( static::class, 'protect_attributes' ), 0 );
	}

	/**
	 * wpautop runs on the_content before shortcodes expand and treats block tags inside attribute values (a <ul> header, <li> formats) as markup, injecting stray <p> tags into the saved formats, so the tags are hidden from it here and restored by Divi4_Module::render(), the same way Divi protects its Code module.
	 *
	 * @param string $content
	 * @return string
	 */
	public static function protect_attributes( $content ) {
		if ( ! is_string( $content ) || strpos( $content, '[' . static::PREFIX ) === false ) {
			return $content;
		}
		return preg_replace_callback( '/\[(' . preg_quote( static::PREFIX, '/' ) . '[a-z0-9_]+)([^\]]*)\]/', function ( $match ) {
			return '[' . $match[1] . str_replace( array( '<', '>' ), array( '%3C', '%3E' ), $match[2] ) . ']';
		}, $content );
	}

	/**
	 * Divi 4's Visual Builder registers React components for modules through the et_builder_api_ready event; Divi 5 has its own hook below.
	 */
	public static function enqueue_divi4_builder() {
		if ( ! function_exists( 'et_core_is_fb_enabled' ) || ! et_core_is_fb_enabled() || static::is_divi5() ) {
			return;
		}
		static::enqueue_assets();
		wp_enqueue_script( 'em-builders-divi4', plugins_url( 'divi4-builder.js', __FILE__ ), array( 'jquery', 'react', 'react-dom' ), EM_VERSION, true );
		wp_localize_script( 'em-builders-divi4', 'EM_Builders_Divi', static::builder_data() );
	}

	/**
	 * @param object $dependency_tree Divi 5 dependency tree.
	 */
	public static function register_divi5( $dependency_tree ) {
		include_once __DIR__ . '/divi5.php';
		Divi5::register( $dependency_tree );
	}

	public static function enqueue_divi5_builder() {
		include_once __DIR__ . '/divi5.php';
		static::enqueue_assets();
		Divi5::enqueue_builder();
	}
}
Loader::init();
