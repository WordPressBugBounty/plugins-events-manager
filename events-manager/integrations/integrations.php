<?php
namespace EM\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// The builder unit registry also renders the Gutenberg blocks, so it loads whether or not a page builder is active.
include_once __DIR__ . '/builders/units.php';

class Integrations {

	public static $loaded = array();

	/**
	 * Third-party integrations, keyed by slug. Each entry lists the files (relative to this folder) to include and a detect callback that returns true when the other plugin or theme is active, optionally deferred to a later hook and priority.
	 *
	 * @return array
	 */
	public static function get_integrations() {
		$integrations = array(
			'wp-fullcalendar'        => array(
				'files'    => array( 'wp-fullcalendar/wp-fullcalendar.php' ),
				'hook'     => 'init',
				'priority' => 200,
				'detect'   => static function () {
					return defined( 'WPFC_VERSION' ) && ! function_exists( 'wpfc_em_init' );
				},
			),
			'bbpress'                => array(
				'files'  => array( 'bbpress/bbpress.php' ),
				'detect' => static function () {
					return class_exists( 'bbPress' );
				},
			),
			'thrive-automator'       => array(
				'files'  => array( 'thrive-automator/events-manager-thrive-automator.php' ),
				'detect' => static function () {
					return class_exists( '\Thrive\Automator\Admin' );
				},
			),
			'duplicate-post-plugins' => array(
				'files'  => array( 'duplicate-post-plugins/duplicate-post-plugins.php' ),
				'detect' => static function () {
					return defined( 'DUPLICATE_POST_CURRENT_VERSION' ) || defined( 'CDP_VERSION' ) || defined( 'DUPLICATE_PAGE_PLUGIN_VERSION' );
				},
			),
			'elementor'              => array(
				'files'  => array( 'builders/elementor/loader.php' ),
				'detect' => static function () {
					return defined( 'ELEMENTOR_VERSION' );
				},
			),
			// Divi ships as a theme (Divi or Extra) or as the Divi Builder plugin; themes haven't loaded at plugins_loaded, so check the active template, and the plugin only defines ET_BUILDER_PLUGIN_ACTIVE on init, so check the version constant it sets when its file loads.
			'divi'                   => array(
				'files'  => array( 'builders/divi/loader.php' ),
				'detect' => static function () {
					return defined( 'ET_BUILDER_PLUGIN_VERSION' ) || in_array( get_template(), array( 'Divi', 'Extra' ), true );
				},
			),
			'wpbakery'               => array(
				'files'  => array( 'builders/wpbakery/loader.php' ),
				'detect' => static function () {
					return defined( 'WPB_VC_VERSION' );
				},
			),
			'beaver-builder'         => array(
				'files'  => array( 'builders/beaver-builder/loader.php' ),
				'detect' => static function () {
					return defined( 'FL_BUILDER_VERSION' );
				},
			),
			'bricks'                 => array(
				'files'  => array( 'builders/bricks/loader.php' ),
				'detect' => static function () {
					return get_template() === 'bricks';
				},
			),
		);
		return apply_filters( 'em_integrations', $integrations );
	}

	public static function init() {
		foreach ( static::get_integrations() as $slug => $integration ) {
			if ( empty( $integration['files'] ) || empty( $integration['detect'] ) ) {
				continue;
			}
			if ( ! empty( $integration['hook'] ) ) {
				$priority = isset( $integration['priority'] ) ? (int) $integration['priority'] : 10;
				add_action( $integration['hook'], static function () use ( $slug, $integration ) {
					static::load( $slug, $integration );
				}, $priority );
			} else {
				static::load( $slug, $integration );
			}
		}
	}

	public static function load( $slug, $integration ) {
		if ( static::is_loaded( $slug ) || ! call_user_func( $integration['detect'] ) ) {
			return false;
		}
		$files = array();
		foreach ( (array) $integration['files'] as $file ) {
			$path = __DIR__ . '/' . ltrim( $file, '/' );
			if ( ! file_exists( $path ) ) {
				return false;
			}
			$files[] = $path;
		}
		foreach ( $files as $path ) {
			include_once $path;
		}
		static::$loaded[] = $slug;
		return true;
	}

	public static function is_loaded( $slug ) {
		return in_array( $slug, static::$loaded, true );
	}
}
Integrations::init();
