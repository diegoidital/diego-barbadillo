<?php
/**
 * [zorom_mapa_oficina]: mapa con el estilo de Zorom sin la API de Google.
 * MapLibre GL (código abierto) + mapas de OpenStreetMap servidos por OpenFreeMap (sin clave ni límite).
 *
 * Atributos: lat, lng, zoom (13), alto (520px), etiqueta, direccion, enlace (Cómo llegar).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ZQ_MAPLIBRE', '4.7.1' );

add_shortcode( 'zorom_mapa_oficina', function ( $atts ) {
	$a = shortcode_atts( array(
		'lat'       => '38.642435',
		'lng'       => '0.0482607',
		'zoom'      => 13,
		'alto'      => '520px',
		'etiqueta'  => 'Zorom · Oficina central',
		'direccion' => 'C. Delfín, 10 Bajo, 03710 Calp, Alicante',
		'enlace'    => 'https://maps.app.goo.gl/ieNdjei5TesCEhkc7',
		'boton'     => 'Cómo llegar',
	), $atts, 'zorom_mapa_oficina' );

	wp_enqueue_style( 'maplibre-gl', 'https://unpkg.com/maplibre-gl@' . ZQ_MAPLIBRE . '/dist/maplibre-gl.css', array(), ZQ_MAPLIBRE );
	wp_enqueue_script( 'maplibre-gl', 'https://unpkg.com/maplibre-gl@' . ZQ_MAPLIBRE . '/dist/maplibre-gl.js', array(), ZQ_MAPLIBRE, true );
	wp_enqueue_style( 'zq-mapa', plugins_url( 'assets/mapa.css', ZQ_FILE ), array(), ZQ_VERSION );
	wp_enqueue_script( 'zq-mapa', plugins_url( 'assets/mapa.js', ZQ_FILE ), array( 'maplibre-gl' ), ZQ_VERSION, true );

	$cfg = array(
		'lat'      => (float) $a['lat'],
		'lng'      => (float) $a['lng'],
		'zoom'     => (float) $a['zoom'],
		'etiqueta' => $a['etiqueta'],
	);
	return '<div class="zq-mapa-oficina" style="height:' . esc_attr( $a['alto'] ) . '">'
		. '<div class="zq-mapa-oficina__mapa" data-zq-mapa="' . esc_attr( wp_json_encode( $cfg ) ) . '" role="img" aria-label="' . esc_attr( 'Mapa: ' . $a['direccion'] ) . '"></div>'
		. ( $a['enlace'] ? '<a class="zq-mapa-oficina__boton" href="' . esc_url( $a['enlace'] ) . '" target="_blank" rel="noopener">' . esc_html( $a['boton'] ) . ' <span aria-hidden="true">&rarr;</span></a>' : '' )
		. '</div>';
} );
