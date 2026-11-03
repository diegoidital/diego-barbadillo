/* Comprueba periódicamente si el pedido ya está pagado y recarga la página cuando lo esté. */
( function () {
	'use strict';
	var caja = document.querySelector( '.idital-pago[data-endpoint]' );
	if ( ! caja || ! window.fetch ) {
		return;
	}
	var url = new URL( caja.getAttribute( 'data-endpoint' ), window.location.href );
	url.searchParams.set( 'pedido', caja.getAttribute( 'data-pedido' ) );
	url.searchParams.set( 'clave', caja.getAttribute( 'data-clave' ) );
	var estado = caja.querySelector( '.idital-pago__estado' );
	var intentos = 0;

	function comprobar() {
		intentos++;
		fetch( url.toString(), { credentials: 'same-origin', headers: { Accept: 'application/json' }, cache: 'no-store' } )
			.then( function ( r ) { return r.ok ? r.json() : null; } )
			.then( function ( datos ) {
				if ( datos && datos.pagado === true ) {
					estado.textContent = '¡Pago recibido! Actualizando…';
					window.location.reload();
					return;
				}
				siguiente();
			} )
			.catch( siguiente );
	}

	function siguiente() {
		if ( intentos >= 150 ) {
			estado.textContent = 'Si ya has pagado, recibirás un email de confirmación en breve.';
			return;
		}
		window.setTimeout( comprobar, intentos < 24 ? 5000 : 20000 );
	}

	window.setTimeout( comprobar, 4000 );
} )();
