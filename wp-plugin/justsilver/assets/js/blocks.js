/* JustSilver blocks – editor side. No build step; uses the wp.* globals. */
( function ( wp ) {
	if ( ! wp || ! wp.blocks || ! window.JustSilverBlocks ) {
		return;
	}
	var el = wp.element.createElement;
	var SSR = wp.serverSideRender;
	var be = wp.blockEditor;
	var c = wp.components;
	var __ = wp.i18n.__;

	window.JustSilverBlocks.forEach( function ( b ) {
		wp.blocks.registerBlockType( b.name, {
			apiVersion: 3,
			title: b.title,
			description: b.description,
			icon: b.icon,
			category: 'widgets',
			keywords: [ 'silver', 'sølv', 'justsilver' ],
			attributes: b.attributes,
			supports: { html: false, align: [ 'wide', 'full' ] },
			edit: function ( props ) {
				var controls = [];
				if ( b.attributes.dage ) {
					controls.push( el( c.SelectControl, {
						key: 'dage',
						label: __( 'Period', 'justsilver' ),
						value: String( props.attributes.dage ),
						options: [
							{ label: '30', value: '30' },
							{ label: '90', value: '90' },
							{ label: '365', value: '365' }
						],
						onChange: function ( v ) { props.setAttributes( { dage: parseInt( v, 10 ) } ); }
					} ) );
				}
				if ( b.attributes.enhed ) {
					controls.push( el( c.SelectControl, {
						key: 'enhed',
						label: __( 'Unit', 'justsilver' ),
						value: props.attributes.enhed,
						options: [ { label: 'kr/g', value: 'g' }, { label: 'kr/kg', value: 'kg' } ],
						onChange: function ( v ) { props.setAttributes( { enhed: v } ); }
					} ) );
				}
				return el( 'div', be.useBlockProps(),
					controls.length ? el( be.InspectorControls, null, el( c.PanelBody, { title: b.title }, controls ) ) : null,
					el( c.Disabled, null, el( SSR, { block: b.name, attributes: props.attributes } ) )
				);
			},
			save: function () { return null; }
		} );
	} );
} )( window.wp );
