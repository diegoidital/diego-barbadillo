/* Mapa de la oficina con el estilo de Zorom (MapLibre + OpenFreeMap, sin API de Google). */
( function () {
	var COLORES = {
		fondo: '#e4e4e4',
		zonas: '#d9d9d9',
		edificios: '#d0d0d0',
		agua: '#0d0d0d',
		vias: '#ffffff',
		viasPrincipales: '#9a9a9a',
		texto: '#555555'
	};

	// Recolorea el estilo «Positron» de OpenFreeMap con la paleta de Zorom.
	function estiloZorom( map ) {
		map.getStyle().layers.forEach( function ( l ) {
			var id = l.id;
			try {
				if ( 'background' === l.type ) {
					map.setPaintProperty( id, 'background-color', COLORES.fondo );
				} else if ( /water|ocean|sea/.test( id ) ) {
					if ( 'fill' === l.type ) {
						map.setPaintProperty( id, 'fill-color', COLORES.agua );
					} else if ( 'line' === l.type ) {
						// Ríos y arroyos: gris claro, para que no parezcan carreteras negras.
						map.setPaintProperty( id, 'line-color', '#c4c4c4' );
					} else if ( 'symbol' === l.type ) {
						map.setLayoutProperty( id, 'visibility', 'none' );
					}
				} else if ( 'symbol' === l.type ) {
					// Solo los nombres de pueblos y ciudades, en gris.
					if ( /place|city|town|village/.test( id ) ) {
						map.setPaintProperty( id, 'text-color', COLORES.texto );
						map.setPaintProperty( id, 'text-halo-color', COLORES.fondo );
					} else {
						map.setLayoutProperty( id, 'visibility', 'none' );
					}
				} else if ( /boundary|admin/.test( id ) ) {
					map.setLayoutProperty( id, 'visibility', 'none' );
				} else if ( 'fill' === l.type && /building/.test( id ) ) {
					map.setPaintProperty( id, 'fill-color', COLORES.edificios );
				} else if ( 'fill' === l.type ) {
					map.setPaintProperty( id, 'fill-color', COLORES.zonas );
				} else if ( 'line' === l.type && /road|highway|street|bridge|tunnel|path|rail/.test( id ) ) {
					map.setPaintProperty( id, 'line-color', /motorway|trunk|primary/.test( id ) ? COLORES.viasPrincipales : COLORES.vias );
				}
			} catch ( e ) {}
		} );
	}

	// La etiqueta con el nombre es también el botón «Cómo llegar».
	function marcador( c ) {
		var el    = document.createElement( 'div' );
		var label = document.createElement( c.enlace ? 'a' : 'span' );
		el.className = 'zq-mo';
		el.innerHTML = '<span class="zq-mo__h"></span><span class="zq-mo__v"></span>';
		label.className   = 'zq-mo__label';
		label.textContent = ( c.etiqueta || 'Zorom' ) + ( c.enlace ? ' →' : '' );
		if ( c.enlace ) {
			label.href   = c.enlace;
			label.target = '_blank';
			label.rel    = 'noopener';
			label.title  = c.boton || 'Cómo llegar';
			label.setAttribute( 'aria-label', ( c.etiqueta || 'Zorom' ) + ': ' + ( c.boton || 'Cómo llegar' ) );
		}
		el.appendChild( label );
		return el;
	}

	function iniciar( nodo ) {
		var c = JSON.parse( nodo.getAttribute( 'data-zq-mapa' ) || '{}' );
		var map = new maplibregl.Map( {
			container: nodo,
			style: 'https://tiles.openfreemap.org/styles/positron',
			center: [ c.lng, c.lat ],
			zoom: c.zoom || 13,
			attributionControl: { compact: true },
			dragRotate: false,
			pitchWithRotate: false,
			cooperativeGestures: false
		} );
		map.scrollZoom.disable(); // Que la rueda haga scroll en la página, no zoom.
		map.touchZoomRotate.disableRotation();
		map.on( 'load', function () {
			estiloZorom( map );
		} );
		new maplibregl.Marker( { element: marcador( c ), anchor: 'center' } )
			.setLngLat( [ c.lng, c.lat ] )
			.addTo( map );
	}

	function arrancar() {
		if ( ! window.maplibregl ) {
			return;
		}
		document.querySelectorAll( '[data-zq-mapa]' ).forEach( function ( n ) {
			// Se carga al acercarse a la pantalla, para no frenar la página.
			if ( 'IntersectionObserver' in window ) {
				var io = new IntersectionObserver( function ( e ) {
					if ( e[ 0 ].isIntersecting ) {
						io.disconnect();
						iniciar( n );
					}
				}, { rootMargin: '300px' } );
				io.observe( n );
			} else {
				iniciar( n );
			}
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', arrancar );
	} else {
		arrancar();
	}
}() );
