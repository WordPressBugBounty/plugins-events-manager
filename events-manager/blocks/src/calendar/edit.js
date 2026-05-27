import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	TextControl,
	ToggleControl,
	SelectControl,
} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

const SIZE_OPTIONS = [
	{ label: __( 'Responsive', 'events-manager' ), value: 'auto' },
	{ label: __( 'Large', 'events-manager' ), value: 'large' },
	{ label: __( 'Medium', 'events-manager' ), value: 'medium' },
	{ label: __( 'Small', 'events-manager' ), value: 'small' },
];

export default function Edit( { attributes, setAttributes } ) {
	const { title, long_events, category, scope, calendar_size } = attributes;

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Calendar settings', 'events-manager' ) }>
					<TextControl
						label={ __( 'Title', 'events-manager' ) }
						value={ title }
						onChange={ ( v ) => setAttributes( { title: v } ) }
					/>
					<ToggleControl
						label={ __( 'Show long events', 'events-manager' ) }
						checked={ !! long_events }
						onChange={ ( v ) =>
							setAttributes( { long_events: !! v } )
						}
					/>
					<ToggleControl
						label={ __( 'Future events only', 'events-manager' ) }
						checked={ scope === 'future' }
						onChange={ ( v ) =>
							setAttributes( { scope: v ? 'future' : 'all' } )
						}
					/>
					<TextControl
						label={ __( 'Category IDs', 'events-manager' ) }
						help={ __(
							'1,2,3 or 2 (0 = all)',
							'events-manager'
						) }
						value={ category }
						onChange={ ( v ) => setAttributes( { category: v } ) }
					/>
					<SelectControl
						label={ __( 'Calendar size', 'events-manager' ) }
						value={ calendar_size }
						options={ SIZE_OPTIONS }
						onChange={ ( v ) =>
							setAttributes( { calendar_size: v } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...useBlockProps() }>
				<ServerSideRender
					block="events-manager/calendar"
					attributes={ attributes }
				/>
			</div>
		</>
	);
}
