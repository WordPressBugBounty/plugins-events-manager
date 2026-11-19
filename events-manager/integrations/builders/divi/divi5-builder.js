/* global EM_Builders_Divi */
( function () {
	var data = window.EM_Builders_Divi;
	var vendor = window.vendor || {};
	var divi = window.divi || {};
	if ( ! data || ! vendor.React || ! vendor.wp || ! vendor.wp.hooks || ! divi.moduleLibrary ) {
		return;
	}
	var React = vendor.React;
	var el = React.createElement;
	var hooks = vendor.wp.hooks;
	var diviModule = divi.module || {};

	var icons = {
		list: 'M4 6h2v2H4zM8 6h12v2H8zM4 11h2v2H4zM8 11h12v2H8zM4 16h2v2H4zM8 16h12v2H8z',
		calendar: 'M7 2h2v2h6V2h2v2h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2zM5 9v11h14V9zm2 2h4v4H7z',
		location: 'M12 2a7 7 0 0 0-7 7c0 5 7 13 7 13s7-8 7-13a7 7 0 0 0-7-7zm0 4.5a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5z',
		cart: 'M2 3h3l3 11h10l3-8H7.4l-.6-2H2zM9 21a2 2 0 1 0 0-4 2 2 0 0 0 0 4zm8 0a2 2 0 1 0 0-4 2 2 0 0 0 0 4z',
	};

	hooks.addFilter( 'divi.iconLibrary.icon.map', 'events-manager', function ( map ) {
		var added = {};
		Object.keys( icons ).forEach( function ( key ) {
			var name = 'em-divi/icon-' + key;
			added[ name ] = {
				name: name,
				viewBox: '0 0 16 16',
				component: function () {
					// Drawn on a 24px grid and scaled, since Divi draws module icons in a 16px box.
					return el( 'path', { d: icons[ key ], fillRule: 'evenodd', transform: 'scale(0.6667)' } );
				},
			};
		} );
		return Object.assign( {}, map, added );
	} );

	// Divi 5 has no show_if in module.json, so schema conditions become visible callbacks on the field settings through the module attributes filter.
	Object.keys( data.units ).forEach( function ( id ) {
		var unit = data.units[ id ];
		var conditions = unit.conditions || {};
		if ( ! Object.keys( conditions ).length ) {
			return;
		}
		hooks.addFilter( 'divi.moduleLibrary.moduleAttributes.' + unit.metadata.name.replace( '/', '.' ), 'events-manager', function ( attributes ) {
			var filtered = Object.assign( {}, attributes );
			Object.keys( conditions ).forEach( function ( key ) {
				var attribute = filtered[ key ];
				if ( ! attribute || ! attribute.settings || ! attribute.settings.innerContent || ! attribute.settings.innerContent.item ) {
					return;
				}
				var item = Object.assign( {}, attribute.settings.innerContent.item, {
					visible: function ( args ) {
						return Object.keys( conditions[ key ] ).every( function ( depends ) {
							var saved = args && args.attrs && args.attrs[ depends ] && args.attrs[ depends ].innerContent && args.attrs[ depends ].innerContent.desktop ? args.attrs[ depends ].innerContent.desktop.value : undefined;
							if ( saved === undefined && unit.metadata.attributes[ depends ] ) {
								saved = unit.metadata.attributes[ depends ].default.innerContent.desktop.value;
							}
							return String( saved ) === String( conditions[ key ][ depends ] );
						} );
					},
				} );
				filtered[ key ] = Object.assign( {}, attribute, {
					settings: Object.assign( {}, attribute.settings, {
						innerContent: Object.assign( {}, attribute.settings.innerContent, { item: item } ),
					} ),
				} );
			} );
			return filtered;
		} );
	} );

	var values = function ( unit, attrs ) {
		var result = {};
		unit.fields.forEach( function ( key ) {
			var value = attrs && attrs[ key ] && attrs[ key ].innerContent && attrs[ key ].innerContent.desktop ? attrs[ key ].innerContent.desktop.value : undefined;
			if ( value !== undefined && value !== null && typeof value !== 'object' ) {
				result[ key ] = value;
			}
		} );
		return result;
	};

	// Design options (spacing, background, border...) for the module wrapper, as in Elegant Themes' d4-module example.
	var ModuleStyles = function ( props ) {
		if ( ! diviModule.StyleContainer ) {
			return null;
		}
		var defaults = props.defaultPrintedStyleAttrs && props.defaultPrintedStyleAttrs.module ? props.defaultPrintedStyleAttrs.module.decoration : undefined;
		return el( diviModule.StyleContainer, { mode: props.mode, state: props.state, noStyleTag: props.noStyleTag },
			props.elements.style( {
				attrName: 'module',
				styleProps: {
					defaultPrintedStyleAttrs: defaults,
					disabledOn: { disabledModuleVisibility: props.settings ? props.settings.disabledModuleVisibility : undefined },
				},
			} ),
			diviModule.CssStyle ? el( diviModule.CssStyle, { selector: props.orderClass, attr: props.attrs ? props.attrs.css : undefined, cssFields: {} } ) : null
		);
	};

	var ModuleScriptData = function ( props ) {
		return el( React.Fragment, null, props.elements.scriptData( { attrName: 'module' } ) );
	};

	// One server-rendered preview shared by every unit, so a field added to the unit schema needs no change here.
	var makeEdit = function ( id, unit ) {
		return function ( props ) {
			var settings = JSON.stringify( values( unit, props.attrs ) );
			var state = React.useState( null );
			var html = state[ 0 ];
			var setHtml = state[ 1 ];
			var ref = React.useRef( null );

			React.useEffect( function () {
				var cancelled = false;
				var timer = setTimeout( function () {
					var body = new FormData();
					body.append( 'action', data.action );
					body.append( 'nonce', data.nonce );
					body.append( 'unit', id );
					body.append( 'settings', settings );
					fetch( data.ajaxurl, { method: 'POST', body: body, credentials: 'same-origin' } )
						.then( function ( response ) { return response.json(); } )
						.then( function ( result ) {
							if ( ! cancelled ) {
								setHtml( result && result.success ? result.data.html : '' );
							}
						} )
						.catch( function () {
							if ( ! cancelled ) {
								setHtml( '' );
							}
						} );
				}, html === null ? 0 : 300 );
				return function () {
					cancelled = true;
					clearTimeout( timer );
				};
			}, [ settings ] );

			React.useEffect( function () {
				var node = ref.current;
				if ( ! node || ! html ) {
					return;
				}
				// EM binds its calendar and UI JS on page load, so injected markup has to be set up again.
				var win = node.ownerDocument.defaultView;
				if ( win.jQuery ) {
					win.jQuery( node ).find( '.em-calendar' ).trigger( 'em_calendar_load' );
				}
				if ( typeof win.em_setup_ui_elements === 'function' ) {
					win.em_setup_ui_elements( node );
				}
			}, [ html ] );

			var inner = html === null
				? el( 'div', { className: 'em-builder-divi-loading', style: { minHeight: '60px' } }, data.loading )
				: el( 'div', { ref: ref, dangerouslySetInnerHTML: { __html: html } } );
			if ( ! diviModule.ModuleContainer ) {
				return inner;
			}
			return el( diviModule.ModuleContainer, {
				attrs: props.attrs,
				elements: props.elements,
				id: props.id,
				name: props.name,
				stylesComponent: ModuleStyles,
				scriptDataComponent: ModuleScriptData,
				isLooped: props.isLooped,
				loopIndex: props.loopIndex,
			}, props.elements.styleComponents( { attrName: 'module' } ), inner );
		};
	};

	hooks.addAction( 'divi.moduleLibrary.registerModuleLibraryStore.after', 'events-manager', function () {
		Object.keys( data.units ).forEach( function ( id ) {
			var unit = data.units[ id ];
			var definition = {
				placeholderContent: {},
				conversionOutline: unit.conversion,
				renderers: { edit: makeEdit( id, unit ) },
			};
			if ( divi.moduleUtils && divi.moduleUtils.elementsCallbacks ) {
				definition.callbacks = { content: { elements: divi.moduleUtils.elementsCallbacks } };
			}
			divi.moduleLibrary.registerModule( unit.metadata, definition );
		} );
	} );
} )();
