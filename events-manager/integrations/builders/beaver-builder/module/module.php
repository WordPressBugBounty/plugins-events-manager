<?php
namespace EM\Builders\Beaver;

use EM\Builders\Units;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One instance per unit. Beaver Builder clones a module with clone_instance set instead of calling new on its class, so a single class serves every unit.
 */
class Module extends \FLBuilderModule {

	public $unit_id = '';

	public $clone_instance = true;

	/**
	 * @param string $unit_id
	 * @param array  $unit
	 */
	public function __construct( $unit_id = '', $unit = array() ) {
		$this->unit_id = $unit_id;
		$unit          = $unit ? $unit : (array) Units::get( $unit_id );
		parent::__construct( array(
			'slug'            => Adapter::slug( $unit_id ),
			'name'            => isset( $unit['title'] ) ? $unit['title'] : $unit_id,
			'description'     => isset( $unit['description'] ) ? $unit['description'] : '',
			'category'        => __( 'Events Manager', 'events-manager' ),
			'group'           => __( 'Events Manager', 'events-manager' ),
			'dir'             => __DIR__ . '/',
			'url'             => plugins_url( '', __FILE__ ),
			'icon'            => static::icon( isset( $unit['icon'] ) ? $unit['icon'] : '' ),
			'partial_refresh' => false,
		) );
	}

	/**
	 * Maps a unit icon keyword onto a dashicon, which Beaver Builder renders for any name it does not find as an svg.
	 *
	 * @param string $keyword
	 * @return string
	 */
	protected static function icon( $keyword ) {
		$icons = array(
			'list'     => 'list-view',
			'calendar' => 'calendar-alt',
			'location' => 'location',
			'cart'     => 'cart',
		);
		return isset( $icons[ $keyword ] ) ? $icons[ $keyword ] : 'calendar-alt';
	}
}
