/**
 * Beaver Builder swaps module HTML in over AJAX, after Events Manager's page-load setup has already run, so the new markup is set up again here.
 */
( function( $ ) {
	var setup = function() {
		$( '.em-builder-unit' ).each( function() {
			if ( this.getAttribute( 'data-em-builder-ready' ) ) {
				return;
			}
			this.setAttribute( 'data-em-builder-ready', '1' );
			if ( typeof em_setup_ui_elements === 'function' ) {
				em_setup_ui_elements( this );
			}
			if ( typeof em_setup_scripts === 'function' ) {
				em_setup_scripts( this );
			}
			$( this ).find( '.em-calendar' ).trigger( 'em_calendar_load' );
		} );
	};
	$( document ).on( 'fl-builder.layout-rendered fl-builder.preview-rendered', setup );
	$( setup );
	if ( window.FLBuilder && typeof FLBuilder.addHook === 'function' ) {
		FLBuilder.addHook( 'didRenderLayoutComplete', setup );
	}
} )( jQuery );
