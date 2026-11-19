<?php
namespace EM\Builders\Elementor;

use EM\Builders\Units;
use Elementor\Controls_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One class serves every unit: Elementor passes the registered instance's args to each element it creates, the same way its own WordPress widget wrapper works.
 */
class Widget extends \Elementor\Widget_Base {

	/**
	 * @var string
	 */
	protected $em_unit = '';

	public static $icons = array(
		'list'     => 'eicon-post-list',
		'calendar' => 'eicon-calendar',
		'location' => 'eicon-map-pin',
		'cart'     => 'eicon-cart-medium',
	);

	public function __construct( $data = array(), $args = null ) {
		if ( ! empty( $args['em_unit'] ) ) {
			$this->em_unit = $args['em_unit'];
		}
		parent::__construct( $data, $args );
	}

	/**
	 * @return array
	 */
	protected function get_unit() {
		$unit = Units::get( $this->em_unit );
		return $unit ? $unit : array(
			'title'    => $this->em_unit,
			'icon'     => '',
			'keywords' => array(),
			'fields'   => array(),
		);
	}

	public function get_name() {
		return Loader::widget_name( $this->em_unit );
	}

	public function get_title() {
		return $this->get_unit()['title'];
	}

	public function get_icon() {
		$icon = $this->get_unit()['icon'];
		return isset( static::$icons[ $icon ] ) ? static::$icons[ $icon ] : 'eicon-calendar';
	}

	public function get_categories() {
		return array( Loader::CATEGORY );
	}

	public function get_keywords() {
		return array_merge( array( 'events manager' ), $this->get_unit()['keywords'] );
	}

	public function get_custom_help_url() {
		$unit = $this->get_unit();
		return ! empty( $unit['help_url'] ) ? $unit['help_url'] : 'https://wp-events-plugin.com/documentation/placeholders/';
	}

	public function get_script_depends() {
		return array( 'events-manager' );
	}

	public function get_style_depends() {
		return array( 'events-manager' );
	}

	/**
	 * Lists, calendars and carts change with every request, so Elementor's element cache must never store them.
	 */
	protected function is_dynamic_content(): bool {
		return true;
	}

	public function has_widget_inner_wrapper(): bool {
		return false;
	}

	protected function register_controls() {
		$unit = $this->get_unit();
		foreach ( Units::groups() as $group => $label ) {
			$fields = wp_list_filter( $unit['fields'], array( 'group' => $group ) );
			if ( ! $fields ) {
				continue;
			}
			$this->start_controls_section( 'em_section_' . $group, array(
				'label' => $label,
				'tab'   => Controls_Manager::TAB_CONTENT,
			) );
			foreach ( $fields as $key => $field ) {
				$this->add_control( $key, static::control_args( $field, $unit['fields'] ) );
			}
			$this->end_controls_section();
		}
	}

	/**
	 * Map a unit field to Elementor control args.
	 *
	 * @param array $field
	 * @param array $fields All fields of the unit, to resolve condition values.
	 * @return array
	 */
	public static function control_args( $field, $fields ) {
		$args = array(
			'label'   => $field['label'],
			'default' => $field['default'],
		);
		if ( $field['description'] !== '' ) {
			$args['description'] = $field['description'];
		}
		switch ( $field['type'] ) {
			case 'toggle':
				$args['type']         = Controls_Manager::SWITCHER;
				$args['return_value'] = 'yes';
				$args['default']      = Units::to_bool( $field['default'] ) ? 'yes' : '';
				break;
			case 'number':
				$args['type'] = Controls_Manager::NUMBER;
				$args['step'] = 1;
				foreach ( array( 'min', 'max' ) as $limit ) {
					if ( isset( $field[ $limit ] ) ) {
						$args[ $limit ] = $field[ $limit ];
					}
				}
				break;
			case 'select':
				$args['type']    = Controls_Manager::SELECT;
				$args['options'] = isset( $field['options'] ) ? $field['options'] : array();
				break;
			case 'html':
				// Not CODE: its HTML linter flags list header and footer formats, which open and close a wrapper across two fields, as errors.
				$args['type']        = Controls_Manager::TEXTAREA;
				$args['rows']        = 6;
				$args['label_block'] = true;
				$args['ai']          = array( 'active' => false );
				break;
			default:
				$args['type']        = Controls_Manager::TEXT;
				$args['label_block'] = true;
				if ( ! empty( $field['pattern'] ) ) {
					$args['ai'] = array( 'active' => false );
				}
		}
		if ( ! empty( $field['condition'] ) ) {
			$args['condition'] = array();
			foreach ( $field['condition'] as $key => $value ) {
				if ( isset( $fields[ $key ] ) && $fields[ $key ]['type'] === 'toggle' ) {
					$value = Units::to_bool( $value ) ? 'yes' : '';
				}
				$args['condition'][ $key ] = $value;
			}
		}
		return $args;
	}

	protected function render() {
		echo Units::render( $this->em_unit, $this->get_settings_for_display() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		Loader::enqueue_assets();
	}

	/**
	 * Elementor copies this into post_content as a fallback; a snapshot would freeze a list or calendar and store the editing user's own cart, so leave it empty like Elementor's WordPress widget wrapper does.
	 */
	public function render_plain_content() {}
}
