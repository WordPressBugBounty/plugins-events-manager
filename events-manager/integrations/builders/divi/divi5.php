<?php
namespace EM\Builders\Divi;

use EM\Builders\Units;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Native Divi 5 modules, one per unit. Divi 5 reads each module's metadata from a module.json folder, so the metadata is generated from the unit schema and written to uploads whenever the schema changes, and the same metadata is handed to the Visual Builder script so both sides agree.
 *
 * Built from Elegant Themes' Divi 5 developer documentation and the d5-extension-example-modules repository (its d4-module example is the converted Divi 4 module pattern used here). Not yet run against a Divi 5 install.
 */
class Divi5 {

	const MODULE_PREFIX = 'em-divi/';

	/**
	 * @param string $id
	 * @return string
	 */
	public static function name( $id ) {
		return static::MODULE_PREFIX . sanitize_key( $id );
	}

	/**
	 * Settings group key for a unit field group; Divi 5 expects the group key, its groupName and each field's groupSlug to be the same string.
	 *
	 * @param string $group Unit field group.
	 * @return string
	 */
	public static function group_slug( $group ) {
		return 'contentEm' . ucfirst( sanitize_key( $group ) );
	}

	/**
	 * Divi 5 field component for each unit field type, filterable because the Divi 5 field library is still adding components.
	 *
	 * @param array  $field
	 * @param string $key
	 * @param string $id
	 * @return array
	 */
	public static function component( $field, $key, $id ) {
		switch ( $field['type'] ) {
			case 'toggle':
				$component = array( 'name' => 'divi/toggle', 'type' => 'field' );
				break;
			case 'select':
				$options = array();
				foreach ( Loader::encode_options( $field['options'] ) as $value => $label ) {
					$options[ $value ] = array( 'label' => $label );
				}
				$component = array(
					'name'  => 'divi/select',
					'type'  => 'field',
					'props' => array( 'options' => $options ),
				);
				break;
			case 'number':
				$component = array(
					'name'  => 'divi/range',
					'type'  => 'field',
					// Divi treats a range as px unless defaultUnit is empty, as on Divi's own unitless counts such as Instagram Feed's number of posts.
					'props' => array(
						'min'         => isset( $field['min'] ) ? $field['min'] : 0,
						'max'         => isset( $field['max'] ) ? $field['max'] : 100,
						'minLimit'    => isset( $field['min'] ) ? $field['min'] : 0,
						'maxLimit'    => isset( $field['max'] ) ? $field['max'] : 100,
						'step'        => 1,
						'defaultUnit' => '',
					),
				);
				break;
			case 'html':
				$component = array( 'name' => 'divi/textarea', 'type' => 'field' );
				break;
			default:
				$component = array( 'name' => 'divi/text', 'type' => 'field' );
		}
		return apply_filters( 'em_builders_divi5_component', $component, $field, $key, $id );
	}

	/**
	 * Design options kept on every module, matching the Divi 4 module (fonts, text and button groups have nothing to style in EM's output).
	 *
	 * @return array
	 */
	protected static function module_attribute() {
		$empty = new \stdClass();
		return array(
			'type'     => 'object',
			'selector' => '{{selector}}',
			'settings' => array(
				'meta'       => array( 'adminLabel' => $empty ),
				'advanced'   => array(
					'html'           => $empty,
					'htmlAttributes' => $empty,
				),
				'decoration' => array(
					'background' => $empty,
					'sizing'     => $empty,
					'spacing'    => $empty,
					'border'     => $empty,
					'boxShadow'  => $empty,
					'filters'    => $empty,
					'transform'  => $empty,
					'animation'  => $empty,
					'overflow'   => $empty,
					'disabledOn' => $empty,
					'transition' => $empty,
					'position'   => $empty,
					'zIndex'     => $empty,
					'scroll'     => $empty,
					'sticky'     => $empty,
				),
			),
		);
	}

	/**
	 * module.json contents for a unit.
	 *
	 * @param string $id
	 * @return array
	 */
	public static function metadata( $id ) {
		$unit       = Units::get( $id );
		$class      = Loader::slug( $id );
		$groups     = Units::groups();
		$attributes = array( 'module' => static::module_attribute() );
		$priority   = 10;
		foreach ( $unit['fields'] as $key => $field ) {
			$item = array(
				'groupSlug'   => static::group_slug( isset( $groups[ $field['group'] ] ) ? $field['group'] : 'content' ),
				'priority'    => $priority,
				'render'      => true,
				'attrName'    => $key . '.innerContent',
				'label'       => $field['label'],
				'description' => $field['description'],
				'features'    => array(
					'sticky'     => false,
					'responsive' => false,
					'hover'      => false,
				),
				'component'   => static::component( $field, $key, $id ),
			);
			$attributes[ $key ] = array(
				'type'     => 'object',
				'default'  => array( 'innerContent' => array( 'desktop' => array( 'value' => Loader::default_value( $field ) ) ) ),
				'settings' => array(
					'innerContent' => array(
						'groupType' => 'group-item',
						'item'      => $item,
					),
				),
			);
			$priority += 10;
		}
		$settings_groups = array();
		$priority        = 10;
		foreach ( $groups as $group => $label ) {
			$settings_groups[ static::group_slug( $group ) ] = array(
				'panel'         => 'content',
				'priority'      => $priority,
				'groupName'     => static::group_slug( $group ),
				'multiElements' => true,
				'component'     => array(
					'name'  => 'divi/composite',
					'props' => array( 'groupLabel' => $label ),
				),
			);
			$priority += 10;
		}
		return apply_filters( 'em_builders_divi5_metadata', array(
			'name'                 => static::name( $id ),
			'd4Shortcode'          => $class,
			'title'                => $unit['title'],
			'titles'               => $unit['title'],
			'moduleIcon'           => static::MODULE_PREFIX . 'icon-' . sanitize_key( $unit['icon'] ),
			'moduleClassName'      => $class,
			'moduleOrderClassName' => $class,
			'category'             => 'module',
			'childrenName'         => array(),
			'attributes'           => $attributes,
			'customCssFields'      => new \stdClass(),
			'settings'             => array(
				'content'  => 'auto',
				'design'   => 'auto',
				'advanced' => 'auto',
				'groups'   => $settings_groups,
			),
		), $id );
	}

	/**
	 * Maps a saved Divi 4 module onto the Divi 5 attributes when Divi 5 converts a layout: unit fields keep their names, and the Divi 4 design options the module kept map to the same Divi 5 decoration groups as in Elegant Themes' d4-module example.
	 *
	 * @param string $id
	 * @return array
	 */
	public static function conversion_outline( $id ) {
		$module = array();
		foreach ( array_keys( Units::get( $id )['fields'] ) as $key ) {
			$module[ $key ] = $key . '.innerContent.*';
		}
		return array(
			'advanced'                => array(
				'admin_label'     => 'module.meta.adminLabel',
				'animation'       => 'module.decoration.animation',
				'background'      => 'module.decoration.background',
				'borders'         => array( 'default' => 'module.decoration.border' ),
				'box_shadow'      => array( 'default' => 'module.decoration.boxShadow' ),
				'disabled_on'     => 'module.decoration.disabledOn',
				'filters'         => array( 'default' => 'module.decoration.filters' ),
				'height'          => 'module.decoration.sizing',
				'margin_padding'  => 'module.decoration.spacing',
				'max_width'       => 'module.decoration.sizing',
				'module'          => 'module.advanced.htmlAttributes',
				'overflow'        => 'module.decoration.overflow',
				'position_fields' => 'module.decoration.position',
				'scroll'          => 'module.decoration.scroll',
				'sticky'          => 'module.decoration.sticky',
				'transform'       => 'module.decoration.transform',
				'transition'      => 'module.decoration.transition',
				'z_index'         => 'module.decoration.zIndex',
			),
			'css'                     => array(
				'before'       => 'css.*.before',
				'main_element' => 'css.*.mainElement',
				'after'        => 'css.*.after',
				'free_form'    => 'css.*.freeForm',
			),
			'module'                  => $module,
			'nonResponsiveAttributes' => array(),
		);
	}

	/**
	 * Writes module.json and conversion-outline.json for a unit if the schema changed and returns its folder, or false if uploads isn't writable.
	 *
	 * @param string $id
	 * @return string|false
	 */
	public static function module_folder( $id ) {
		$uploads = wp_upload_dir( null, false );
		if ( ! empty( $uploads['error'] ) ) {
			return false;
		}
		$folder = trailingslashit( $uploads['basedir'] ) . 'em-builders/divi5/' . sanitize_key( $id ) . '/';
		// Divi 5 converts saved Divi 4 modules only when conversion-outline.json sits next to module.json; without it they stay wrapped in shortcode modules.
		$files = array(
			'module.json'             => static::metadata( $id ),
			'conversion-outline.json' => static::conversion_outline( $id ),
		);
		foreach ( $files as $name => $contents ) {
			$json = wp_json_encode( $contents, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			$file = $folder . $name;
			if ( ! file_exists( $file ) || md5_file( $file ) !== md5( $json ) ) {
				if ( ! wp_mkdir_p( $folder ) || file_put_contents( $file, $json ) === false ) {
					return false;
				}
			}
		}
		return $folder;
	}

	/**
	 * @param object $dependency_tree
	 */
	public static function register( $dependency_tree ) {
		if ( ! interface_exists( '\ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface' ) || ! is_object( $dependency_tree ) || ! method_exists( $dependency_tree, 'add_dependency' ) ) {
			return;
		}
		include_once __DIR__ . '/divi5-dependency.php';
		$dependency_tree->add_dependency( new Divi5_Dependency() );
	}

	/**
	 * Called on init by Divi5_Dependency::load().
	 */
	public static function register_modules() {
		if ( ! class_exists( '\ET\Builder\Packages\ModuleLibrary\ModuleRegistration' ) ) {
			return;
		}
		foreach ( array_keys( Units::get_all() ) as $id ) {
			$folder = static::module_folder( $id );
			if ( ! $folder ) {
				continue;
			}
			\ET\Builder\Packages\ModuleLibrary\ModuleRegistration::register_module( $folder, array(
				'render_callback' => function ( $attrs, $content, $block, $elements, $default_printed_style_attrs = array() ) use ( $id ) {
					return Divi5::render_callback( $id, $attrs, $content, $block, $elements );
				},
			) );
		}
	}

	/**
	 * Divi 5 saves each value as attribute > innerContent > breakpoint > value.
	 *
	 * @param array $attrs
	 * @return array
	 */
	public static function values( $attrs ) {
		$values = array();
		foreach ( (array) $attrs as $key => $attr ) {
			if ( isset( $attr['innerContent']['desktop']['value'] ) && is_scalar( $attr['innerContent']['desktop']['value'] ) ) {
				$values[ $key ] = Loader::decode_divi_value( $attr['innerContent']['desktop']['value'] );
			}
		}
		return $values;
	}

	/**
	 * Same arguments and wrapper as the d4-module example's render callback, with the unit's registry output as the module content.
	 *
	 * @param string $id
	 * @param array  $attrs
	 * @param string $content  Inner blocks HTML.
	 * @param object $block    WP_Block.
	 * @param object $elements ET\Builder\Packages\Module\Layout\Components\ModuleElements\ModuleElements.
	 * @return string
	 */
	public static function render_callback( $id, $attrs, $content, $block, $elements ) {
		$html = Loader::render( $id, static::values( $attrs ) );
		if ( ! class_exists( '\ET\Builder\Packages\Module\Module' ) || ! is_object( $block ) || empty( $block->parsed_block ) || ! is_object( $elements ) ) {
			return $html;
		}
		$parsed = $block->parsed_block;
		$parent = null;
		if ( class_exists( '\ET\Builder\FrontEnd\BlockParser\BlockParserStore' ) && isset( $parsed['id'], $parsed['storeInstance'] ) ) {
			$parent = \ET\Builder\FrontEnd\BlockParser\BlockParserStore::get_parent( $parsed['id'], $parsed['storeInstance'] );
		}
		return \ET\Builder\Packages\Module\Module::render( array(
			'orderIndex'          => isset( $parsed['orderIndex'] ) ? $parsed['orderIndex'] : 0,
			'storeInstance'       => isset( $parsed['storeInstance'] ) ? $parsed['storeInstance'] : null,
			'id'                  => isset( $parsed['id'] ) ? $parsed['id'] : '',
			'name'                => $block->block_type->name,
			'moduleCategory'      => $block->block_type->category,
			'attrs'               => $attrs,
			'elements'            => $elements,
			'stylesComponent'     => array( static::class, 'module_styles' ),
			'scriptDataComponent' => array( static::class, 'module_script_data' ),
			'parentAttrs'         => isset( $parent->attrs ) ? $parent->attrs : array(),
			'parentId'            => isset( $parent->id ) ? $parent->id : '',
			'parentName'          => isset( $parent->blockName ) ? $parent->blockName : '',
			'childrenIds'         => array(),
			'children'            => array(
				$elements->style_components( array( 'attrName' => 'module' ) ),
				$html,
			),
		) );
	}

	/**
	 * Prints the module's design options (spacing, background, border and so on), as in the d4-module example's ModuleStylesTrait.
	 *
	 * @param array $args
	 */
	public static function module_styles( $args ) {
		if ( ! class_exists( '\ET\Builder\FrontEnd\Module\Style' ) ) {
			return;
		}
		$attrs    = isset( $args['attrs'] ) ? $args['attrs'] : array();
		$settings = isset( $args['settings'] ) ? $args['settings'] : array();
		$defaults = isset( $args['defaultPrintedStyleAttrs'] ) ? $args['defaultPrintedStyleAttrs'] : array();
		$styles   = array(
			$args['elements']->style( array(
				'attrName'   => 'module',
				'styleProps' => array(
					'defaultPrintedStyleAttrs' => isset( $defaults['module']['decoration'] ) ? $defaults['module']['decoration'] : array(),
					'disabledOn'               => array(
						'disabledModuleVisibility' => isset( $settings['disabledModuleVisibility'] ) ? $settings['disabledModuleVisibility'] : null,
					),
				),
			) ),
		);
		if ( class_exists( '\ET\Builder\Packages\Module\Options\Css\CssStyle' ) ) {
			$styles[] = \ET\Builder\Packages\Module\Options\Css\CssStyle::style( array(
				'selector'  => isset( $args['orderClass'] ) ? $args['orderClass'] : '',
				'attr'      => isset( $attrs['css'] ) ? $attrs['css'] : array(),
				'cssFields' => array(),
			) );
		}
		\ET\Builder\FrontEnd\Module\Style::add( array(
			'id'            => $args['id'],
			'name'          => $args['name'],
			'orderIndex'    => $args['orderIndex'],
			'storeInstance' => $args['storeInstance'],
			'styles'        => $styles,
		) );
	}

	/**
	 * Front-end script data for the module's decoration options (animation, scroll and sticky effects), as in the d4-module example.
	 *
	 * @param array $args
	 */
	public static function module_script_data( $args ) {
		if ( ! class_exists( '\ET\Builder\Packages\Module\Options\Element\ElementScriptData' ) ) {
			return;
		}
		\ET\Builder\Packages\Module\Options\Element\ElementScriptData::set( array(
			'id'            => isset( $args['id'] ) ? $args['id'] : '',
			'selector'      => isset( $args['selector'] ) ? $args['selector'] : '',
			'attrs'         => isset( $args['attrs']['module']['decoration'] ) ? $args['attrs']['module']['decoration'] : array(),
			'storeInstance' => isset( $args['storeInstance'] ) ? $args['storeInstance'] : null,
		) );
	}

	public static function enqueue_builder() {
		if ( ! class_exists( '\ET\Builder\VisualBuilder\Assets\PackageBuildManager' ) ) {
			return;
		}
		$data = Loader::builder_data();
		foreach ( $data['units'] as $id => $unit ) {
			$data['units'][ $id ]['metadata']   = static::metadata( $id );
			$data['units'][ $id ]['conversion'] = static::conversion_outline( $id );
		}
		wp_register_script( 'em-builders-divi5-data', false, array(), EM_VERSION, false );
		wp_add_inline_script( 'em-builders-divi5-data', 'window.EM_Builders_Divi = ' . wp_json_encode( $data ) . ';' );
		\ET\Builder\VisualBuilder\Assets\PackageBuildManager::register_package_build( array(
			'name'    => 'em-builders-divi5',
			'version' => EM_VERSION,
			'script'  => array(
				'src'                => plugins_url( 'divi5-builder.js', __FILE__ ),
				'deps'               => array( 'em-builders-divi5-data', 'divi-module-library', 'divi-vendor-wp-hooks', 'jquery' ),
				'enqueue_top_window' => false,
				'enqueue_app_window' => true,
			),
		) );
	}
}
