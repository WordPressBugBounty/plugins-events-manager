( function ( $ ) {
	// EM sets up everything on the page once at DOM ready, so units present then are skipped and only ones Elementor renders later in the editor get set up.
	$( document ).on( 'em_javascript_loaded', function () {
		document.querySelectorAll( '.em-builder-unit' ).forEach( function ( el ) {
			el.dataset.emBuilderReady = '1';
		} );
	} );

	const setup = function ( $scope ) {
		$( function () {
			$scope.find( '.em-builder-unit' ).addBack( '.em-builder-unit' ).each( function () {
				if ( this.dataset.emBuilderReady ) {
					return;
				}
				this.dataset.emBuilderReady = '1';
				const unit = $( this );
				if ( typeof window.em_setup_ui_elements === 'function' ) {
					window.em_setup_ui_elements( this );
				}
				unit.find( '.em-calendar' ).trigger( 'em_calendar_load' );
				$( document ).triggerHandler( 'em_page_loaded', [ unit ] );
			} );
		} );
	};

	$( window ).on( 'elementor/frontend/init', function () {
		const widgets = window.EM_Builders_Elementor ? window.EM_Builders_Elementor.widgets : [];
		widgets.forEach( function ( name ) {
			window.elementorFrontend.hooks.addAction( 'frontend/element_ready/' + name + '.default', setup );
		} );
	} );
} )( jQuery );
