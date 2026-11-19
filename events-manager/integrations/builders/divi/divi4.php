<?php
namespace EM\Builders\Divi;

use EM\Builders\Units;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One Divi 4 module class shared by every unit: Divi keys modules by slug, not by class, so each unit is an instance with its own slug and fields generated from the unit schema.
 */
class Divi4_Module extends \ET_Builder_Module {

	public $vb_support = 'on';

	/**
	 * @var string
	 */
	public $em_unit_id;

	protected $module_credits = array(
		'module_uri' => 'https://wp-events-plugin.com',
		'author'     => 'Pixelite',
		'author_uri' => 'https://pixelite.com',
	);

	/**
	 * @param string $unit_id
	 */
	public function __construct( $unit_id ) {
		$this->em_unit_id       = $unit_id;
		$this->slug             = Loader::slug( $unit_id );
		$this->main_css_element = '%%order_class%%';
		parent::__construct();
	}

	/**
	 * @return array
	 */
	protected function unit() {
		return Units::get( $this->em_unit_id );
	}

	public function init() {
		$unit         = $this->unit();
		$this->name   = $unit['title'];
		$this->plural = $unit['title'];
	}

	public function get_settings_modal_toggles() {
		$toggles = array();
		$i       = 1;
		foreach ( Units::groups() as $group => $label ) {
			$toggles[ 'em_' . $group ] = array(
				'title'    => $label,
				'priority' => $i++,
			);
		}
		return array(
			'general' => array(
				'toggles' => $toggles,
			),
		);
	}

	public function get_fields() {
		$unit   = $this->unit();
		$fields = array();
		foreach ( $unit['fields'] as $key => $field ) {
			$divi = array(
				'label'           => $field['label'],
				'description'     => $field['description'],
				'option_category' => 'basic_option',
				'tab_slug'        => 'general',
				'toggle_slug'     => 'em_' . ( isset( Units::groups()[ $field['group'] ] ) ? $field['group'] : 'content' ),
				'default'         => Loader::default_value( $field ),
			);
			switch ( $field['type'] ) {
				case 'toggle':
					$divi['type']    = 'yes_no_button';
					$divi['options'] = array(
						'on'  => __( 'Yes', 'events-manager' ),
						'off' => __( 'No', 'events-manager' ),
					);
					break;
				case 'select':
					$divi['type']    = 'select';
					$divi['options'] = Loader::encode_options( $field['options'] );
					break;
				case 'number':
					$divi['type']           = 'range';
					$divi['unitless']       = true;
					$divi['validate_unit']  = false;
					$divi['fixed_unit']     = '';
					$divi['range_settings'] = array(
						'min'  => isset( $field['min'] ) ? $field['min'] : 0,
						'max'  => isset( $field['max'] ) ? $field['max'] : 100,
						'step' => 1,
					);
					break;
				case 'html':
					$divi['type'] = 'textarea';
					break;
				default:
					$divi['type'] = 'text';
			}
			if ( ! empty( $field['condition'] ) ) {
				$divi['show_if'] = Loader::condition( $unit, $field );
			}
			$fields[ $key ] = $divi;
		}
		return $fields;
	}

	/**
	 * Keep Divi's spacing, background, border and visibility options but drop the font and button groups, which have nothing to style in EM's output.
	 */
	public function get_advanced_fields_config() {
		return array(
			'fonts'        => false,
			'text'         => false,
			'button'       => false,
			'link_options' => false,
		);
	}

	public function render( $attrs, $content = null, $render_slug = '' ) {
		return Loader::render( $this->em_unit_id, array_map( array( Loader::class, 'decode_divi_value' ), (array) $this->props ) );
	}
}
