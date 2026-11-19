<?php
namespace EM\Builders\Divi;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Divi 5 loads modules through its dependency tree; this file is only included once Divi 5 has defined the interface.
 */
class Divi5_Dependency implements \ET\Builder\Framework\DependencyManagement\Interfaces\DependencyInterface {

	public function load() {
		if ( did_action( 'init' ) ) {
			Divi5::register_modules();
		} else {
			add_action( 'init', array( Divi5::class, 'register_modules' ) );
		}
	}
}
