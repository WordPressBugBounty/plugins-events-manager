<?php
namespace EM\Builders\Elementor;

use EM\Builders\Units;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers one Elementor widget per Events Manager builder unit. Only Elementor's own actions are hooked, so nothing runs unless Elementor is active.
 */
class Loader {

	const CATEGORY = 'events-manager';

	const PREFIX = 'events-manager-';

	public static function init() {
		add_action( 'elementor/elements/categories_registered', array( static::class, 'register_category' ) );
		add_action( 'elementor/widgets/register', array( static::class, 'register_widgets' ) );
		add_action( 'elementor/preview/enqueue_scripts', array( static::class, 'enqueue_preview' ) );
	}

	/**
	 * @param \Elementor\Elements_Manager $elements_manager
	 */
	public static function register_category( $elements_manager ) {
		$elements_manager->add_category( static::CATEGORY, array(
			'title' => __( 'Events Manager', 'events-manager' ),
			'icon'  => 'eicon-calendar',
		) );
	}

	/**
	 * @param \Elementor\Widgets_Manager $widgets_manager
	 */
	public static function register_widgets( $widgets_manager ) {
		include_once __DIR__ . '/widget.php';
		foreach ( array_keys( Units::get_all() ) as $id ) {
			$widgets_manager->register( new Widget( array(), array( 'em_unit' => $id ) ) );
		}
	}

	/**
	 * EM's own enqueue can skip a page when the "limit JS/CSS" settings are on, which would leave a widget unstyled on any page EM doesn't know about.
	 */
	public static function enqueue_assets() {
		if ( ! wp_script_is( 'events-manager', 'registered' ) ) {
			\EM\Scripts_and_Styles::enqueue_scripts( array( 'jquery' => 'jquery' ) );
		}
		if ( ! wp_style_is( 'events-manager', 'registered' ) ) {
			\EM\Scripts_and_Styles::enqueue_public_styles();
		}
	}

	public static function enqueue_preview() {
		static::enqueue_assets();
		wp_enqueue_script( 'em-builders-elementor', plugins_url( 'editor.js', __FILE__ ), array( 'jquery', 'elementor-frontend' ), EM_VERSION, true );
		wp_localize_script( 'em-builders-elementor', 'EM_Builders_Elementor', array(
			'widgets' => array_map( array( static::class, 'widget_name' ), array_keys( Units::get_all() ) ),
		) );
	}

	/**
	 * @param string $id Unit id.
	 * @return string
	 */
	public static function widget_name( $id ) {
		return static::PREFIX . $id;
	}
}
Loader::init();
