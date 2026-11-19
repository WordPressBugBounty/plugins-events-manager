/* global EM_Builders_Divi, React, jQuery */
( function ( $ ) {
	var data = window.EM_Builders_Divi;
	if ( ! data || ! window.React ) {
		return;
	}
	var el = React.createElement;

	// One server-rendered preview shared by every unit, so a field added to the unit schema needs no change here.
	var makeModule = function ( id, unit ) {
		var Module = function ( props ) {
			React.Component.call( this, props );
			this.state = { html: null, error: null };
			this.node = null;
			this.timer = null;
			this.request = 0;
		};
		Module.prototype = Object.create( React.Component.prototype );
		Module.prototype.constructor = Module;
		Module.slug = unit.slug;

		Module.prototype.values = function ( props ) {
			var values = {};
			unit.fields.forEach( function ( key ) {
				if ( props[ key ] !== undefined && props[ key ] !== null ) {
					values[ key ] = props[ key ];
				}
			} );
			return values;
		};

		Module.prototype.load = function () {
			var self = this;
			var request = ++this.request;
			var body = new FormData();
			body.append( 'action', data.action );
			body.append( 'nonce', data.nonce );
			body.append( 'unit', id );
			body.append( 'settings', JSON.stringify( this.values( this.props ) ) );
			fetch( data.ajaxurl, { method: 'POST', body: body, credentials: 'same-origin' } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( result ) {
					if ( request === self.request ) {
						self.setState( result && result.success ? { html: result.data.html, error: null } : { error: true } );
					}
				} )
				.catch( function () {
					if ( request === self.request ) {
						self.setState( { error: true } );
					}
				} );
		};

		Module.prototype.componentDidMount = function () {
			this.load();
		};

		Module.prototype.componentDidUpdate = function ( prevProps, prevState ) {
			if ( JSON.stringify( this.values( prevProps ) ) !== JSON.stringify( this.values( this.props ) ) ) {
				clearTimeout( this.timer );
				this.timer = setTimeout( this.load.bind( this ), 300 );
			}
			if ( this.node && this.state.html !== prevState.html ) {
				// The calendar and other EM widgets bind their JS on page load, so markup injected later has to be set up again, using the jQuery of whichever window holds the node.
				var win = this.node.ownerDocument.defaultView;
				var $win = win.jQuery;
				if ( $win ) {
					$win( this.node ).find( '.em-calendar' ).trigger( 'em_calendar_load' );
				}
				if ( typeof win.em_setup_ui_elements === 'function' ) {
					win.em_setup_ui_elements( this.node );
				}
			}
		};

		Module.prototype.componentWillUnmount = function () {
			clearTimeout( this.timer );
			this.request++;
		};

		Module.prototype.render = function () {
			var self = this;
			if ( this.state.html === null ) {
				return el( 'div', { className: 'em-builder-divi-loading', style: { minHeight: '60px' } },
					el( 'div', { className: 'et-fb-loader-wrapper' }, el( 'div', { className: 'et-fb-loader' } ) )
				);
			}
			return el( 'div', {
				ref: function ( node ) { self.node = node; },
				dangerouslySetInnerHTML: { __html: this.state.html },
			} );
		};
		return Module;
	};

	$( window ).on( 'et_builder_api_ready', function ( event, API ) {
		var modules = [];
		Object.keys( data.units ).forEach( function ( id ) {
			modules.push( makeModule( id, data.units[ id ] ) );
		} );
		API.registerModules( modules );
	} );
} )( jQuery );
