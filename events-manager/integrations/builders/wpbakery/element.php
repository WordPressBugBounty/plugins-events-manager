<?php
namespace EM\Builders\WPBakery;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared class behind every WPBakery element, aliased to WPBakeryShortCode_{tag} for each unit by Loader::register(). WPBakery wraps content() with its own output handling, which the frontend editor needs to track elements, and nothing is cached so calendars, lists and the cart always render live.
 */
class Element extends \WPBakeryShortCode {

	protected function content( $atts, $content = null ) {
		$tag = ! empty( $this->shortcode ) ? $this->shortcode : $this->settings( 'base' );
		return Loader::render( $tag, $atts );
	}
}
