<?php
/**
 * Beaver Builder module template shared by every Events Manager unit. Output goes through Units::render(), which validates and sanitizes the settings.
 *
 * @var FLBuilderModule $module
 * @var stdClass        $settings
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Units::render() builds escaped widget output.
echo EM\Builders\Units::render( $module->unit_id, (array) $settings );
