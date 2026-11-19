<?php
namespace EM\Builders\Beaver;

use EM\Builders\Units;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers one Beaver Builder module per Events Manager unit, with the settings form generated from the unit field schema.
 */
class Adapter {

	const SLUG_PREFIX = 'em-';

	public static function init() {
		// Beaver Builder registers its own modules on init at priority 10, custom modules go on after that.
		add_action( 'init', array( static::class, 'register_modules' ), 20 );
		add_action( 'wp_enqueue_scripts', array( static::class, 'enqueue_builder_script' ) );
	}

	public static function register_modules() {
		if ( ! class_exists( 'FLBuilder' ) || ! class_exists( 'FLBuilderModule' ) ) {
			return;
		}
		include_once __DIR__ . '/module/module.php';
		foreach ( Units::get_all() as $id => $unit ) {
			$module = new Module( $id, $unit );
			\FLBuilder::register_module( $module, static::form( $unit ) );
		}
	}

	/**
	 * Re-runs Events Manager's UI setup on module HTML that Beaver Builder injects over AJAX, since EM only sets up elements that exist on page load.
	 */
	public static function enqueue_builder_script() {
		if ( ! class_exists( 'FLBuilderModel' ) || ! \FLBuilderModel::is_builder_active() ) {
			return;
		}
		wp_enqueue_script( 'em-builder-beaver', plugins_url( 'js/builder.js', __FILE__ ), array( 'jquery' ), EM_VERSION, true );
	}

	/**
	 * @param string $unit_id
	 * @return string
	 */
	public static function slug( $unit_id ) {
		return static::SLUG_PREFIX . sanitize_key( $unit_id );
	}

	/**
	 * Settings form, one section per field group, in the shape FLBuilder::register_module() expects.
	 *
	 * @param array $unit
	 * @return array
	 */
	public static function form( $unit ) {
		$toggles  = static::toggles( $unit['fields'] );
		$sections = array();
		foreach ( Units::groups() as $group => $label ) {
			$fields = array();
			foreach ( $unit['fields'] as $key => $field ) {
				if ( $field['group'] !== $group ) {
					continue;
				}
				$control = static::control( $key, $field );
				if ( isset( $toggles[ $key ] ) ) {
					$control['toggle'] = $toggles[ $key ];
				}
				$fields[ $key ] = $control;
			}
			if ( $fields ) {
				$sections[ $group ] = array(
					'title'  => $label,
					'fields' => $fields,
				);
			}
		}
		return array(
			'general' => array(
				'title'    => $unit['title'],
				'sections' => $sections,
			),
		);
	}

	/**
	 * @param string $key
	 * @param array  $field
	 * @return array
	 */
	protected static function control( $key, $field ) {
		$control = array(
			'label' => $field['label'],
		);
		if ( ! empty( $field['description'] ) ) {
			$control['help'] = $field['description'];
		}
		switch ( $field['type'] ) {
			case 'toggle':
				$control['type']    = 'select';
				$control['default'] = $field['default'] ? '1' : '0';
				$control['options'] = array(
					'0' => __( 'No', 'events-manager' ),
					'1' => __( 'Yes', 'events-manager' ),
				);
				break;
			case 'select':
				$control['type']    = 'select';
				$control['default'] = (string) $field['default'];
				$control['options'] = array_map( 'wp_strip_all_tags', (array) $field['options'] );
				break;
			case 'number':
				$control['type']    = 'unit';
				$control['default'] = $field['default'];
				$slider             = array_intersect_key( $field, array_flip( array( 'min', 'max' ) ) );
				if ( $slider ) {
					$slider['step']    = 1;
					$control['slider'] = $slider;
				}
				break;
			case 'html':
				$control['type']    = 'textarea';
				$control['rows']    = 4;
				$control['default'] = $field['default'];
				break;
			default:
				$control['type']    = 'text';
				$control['default'] = $field['default'];
				if ( $field['default'] !== '' && $field['default'] !== null ) {
					$control['placeholder'] = (string) $field['default'];
				}
		}
		return $control;
	}

	/**
	 * Beaver Builder puts show/hide rules on the controlling field, so a field's 'condition' is inverted into toggles on the fields it depends on.
	 *
	 * @param array $fields
	 * @return array Field key => toggle config.
	 */
	protected static function toggles( $fields ) {
		$toggles = array();
		foreach ( $fields as $key => $field ) {
			if ( empty( $field['condition'] ) || ! is_array( $field['condition'] ) ) {
				continue;
			}
			foreach ( $field['condition'] as $controller => $value ) {
				if ( ! isset( $fields[ $controller ] ) ) {
					continue;
				}
				foreach ( (array) $value as $match ) {
					if ( is_bool( $match ) ) {
						$match = $match ? '1' : '0';
					}
					$toggles[ $controller ][ (string) $match ]['fields'][] = $key;
				}
			}
		}
		// Values of a controller that no dependent field lists still need an entry so Beaver hides the dependents.
		foreach ( $toggles as $controller => $values ) {
			if ( $fields[ $controller ]['type'] === 'toggle' ) {
				foreach ( array( '0', '1' ) as $value ) {
					if ( ! isset( $toggles[ $controller ][ $value ] ) ) {
						$toggles[ $controller ][ $value ] = array( 'fields' => array() );
					}
				}
			}
		}
		return $toggles;
	}
}
