/* Registro de las pasarelas en el checkout por bloques de WooCommerce (sin compilación). */
( function () {
	'use strict';
	if ( ! window.wc || ! window.wc.wcBlocksRegistry || ! window.wc.wcSettings || ! window.wp || ! window.wp.element ) {
		return;
	}
	var registrar = window.wc.wcBlocksRegistry.registerPaymentMethod;
	var ajustes = window.wc.wcSettings.getSetting;
	var el = window.wp.element.createElement;
	var decodificar = window.wp.htmlEntities ? window.wp.htmlEntities.decodeEntities : function ( t ) { return t; };

	[ 'idital_qonto', 'idital_tropipay' ].forEach( function ( id ) {
		var datos = ajustes( id + '_data', null );
		if ( ! datos ) {
			return;
		}
		var titulo = decodificar( datos.title || '' );
		var Contenido = function () {
			return el( 'div', { className: 'idital-pago__descripcion' }, decodificar( datos.description || '' ) );
		};
		registrar( {
			name: id,
			label: el( 'span', { className: 'idital-pago__etiqueta idital-pago__etiqueta--' + id }, titulo ),
			ariaLabel: titulo,
			content: el( Contenido ),
			edit: el( Contenido ),
			canMakePayment: function () { return true; },
			supports: { features: datos.supports || [ 'products' ] }
		} );
	} );
} )();
