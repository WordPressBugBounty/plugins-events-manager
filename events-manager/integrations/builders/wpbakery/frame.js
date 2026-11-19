( function ( $ ) {
	// EM sets up everything on the page once at DOM ready, so units present then are skipped and only ones the frontend editor inserts or re-renders later get set up.
	$( document ).on( 'em_javascript_loaded', function () {
		document.querySelectorAll( '.em-builder-unit' ).forEach( function ( el ) {
			el.dataset.emBuilderReady = '1';
		} );
	} );

	const setup = function ( el ) {
		if ( el.dataset.emBuilderReady ) {
			return;
		}
		el.dataset.emBuilderReady = '1';
		const unit = $( el );
		if ( typeof window.em_setup_ui_elements === 'function' ) {
			window.em_setup_ui_elements( el );
		}
		unit.find( '.em-calendar' ).trigger( 'em_calendar_load' );
		$( document ).triggerHandler( 'em_page_loaded', [ unit ] );
	};

	const scan = function ( root ) {
		if ( ! root || root.nodeType !== 1 ) {
			return;
		}
		if ( root.classList.contains( 'em-builder-unit' ) ) {
			setup( root );
		}
		root.querySelectorAll( '.em-builder-unit' ).forEach( setup );
	};

	// WPBakery replaces an element's HTML when its settings change; watching the DOM avoids depending on which of its re-render events fires.
	$( function () {
		new MutationObserver( function ( records ) {
			records.forEach( function ( record ) {
				record.addedNodes.forEach( scan );
			} );
		} ).observe( document.body, { childList: true, subtree: true } );
	} );
} )( jQuery );
