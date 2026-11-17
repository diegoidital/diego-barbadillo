<?php
/**
 * Traducción automática al español de los textos que Qobrix solo tiene en inglés
 * (descripción de promociones e inmuebles), con DeepL.
 *
 * - Cada texto se traduce una sola vez y se guarda; solo se vuelve a traducir si cambia en Qobrix.
 * - Lo escrito a mano en la promoción («Descripción en español») manda sobre la traducción.
 * - Sin clave de DeepL, o si DeepL falla, se muestra el texto original.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** ¿Parece inglés? Evita traducir (y pagar) textos que ya están en español. */
function zq_parece_ingles( $texto ) {
	$t = ' ' . strtolower( wp_strip_all_tags( $texto ) ) . ' ';
	$en = preg_match_all( '/\b(the|and|with|of|is|are|for|this|offers|home|homes|views|located|in|to|on|from|by|at|its|all|private|pool|garden|terrace|bedrooms|bathrooms|swimming|gym|parking|sea|beach|modern|luxury|close|minutes|within|each|every|your|you)\b/', $t );
	$es = preg_match_all( '/\b(el|la|los|las|y|con|de|del|es|son|para|esta|ofrece|vivienda|viviendas|vistas|en|un|una|por|al|su|sus|piscina|jardín|terraza|dormitorios|baños|playa|mar|cerca|minutos|tu)\b/', $t );
	return $en > $es;
}

function zq_traducir( $texto ) {
	$texto = (string) $texto;
	$o     = zq_options();
	if ( '' === trim( wp_strip_all_tags( $texto ) ) || empty( $o['traducir'] ) || empty( $o['deepl_key'] ) || ! zq_parece_ingles( $texto ) ) {
		return $texto;
	}
	$clave = 'zq_trad_' . md5( $texto );
	$hecho = get_option( $clave );
	if ( is_string( $hecho ) && '' !== $hecho ) {
		return $hecho;
	}
	// Si DeepL falló hace poco, no reintentar en cada visita.
	if ( get_transient( 'zq_trad_pausa' ) ) {
		return $texto;
	}
	$key = trim( $o['deepl_key'] );
	$url = ( ':fx' === substr( $key, -3 ) ? 'https://api-free.deepl.com' : 'https://api.deepl.com' ) . '/v2/translate';
	$res = wp_remote_post( $url, array(
		'timeout' => 20,
		'headers' => array(
			'Authorization' => 'DeepL-Auth-Key ' . $key,
			'Content-Type'  => 'application/json',
		),
		'body'    => wp_json_encode( array(
			'text'         => array( $texto ),
			'source_lang'  => 'EN',
			'target_lang'  => 'ES',
			'tag_handling' => 'html',
			'formality'    => 'prefer_more',
		) ),
	) );
	$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
	$data = 200 === $code ? json_decode( wp_remote_retrieve_body( $res ), true ) : null;
	if ( empty( $data['translations'][0]['text'] ) ) {
		update_option( 'zq_trad_error', ( is_wp_error( $res ) ? $res->get_error_message() : 'HTTP ' . $code . ' ' . mb_substr( wp_remote_retrieve_body( $res ), 0, 160 ) ), false );
		set_transient( 'zq_trad_pausa', 1, 10 * MINUTE_IN_SECONDS );
		return $texto;
	}
	delete_option( 'zq_trad_error' );
	$es = (string) $data['translations'][0]['text'];
	add_option( $clave, $es, '', 'no' );
	return $es;
}

/**
 * Tras sincronizar, deja traducidas las descripciones (para que la primera visita no espere a DeepL).
 * Se limita a unas cuantas por pasada; el resto se traducen en la siguiente o al visitarlas.
 */
add_action( 'zq_tras_sincronizar', 'zq_pretraducir' );
function zq_pretraducir() {
	$o = zq_options();
	if ( empty( $o['traducir'] ) || empty( $o['deepl_key'] ) ) {
		return;
	}
	$hechas = 0;
	foreach ( get_posts( array( 'post_type' => array( ZQ_CPT, 'vivienda' ), 'post_status' => 'publish', 'posts_per_page' => -1 ) ) as $p ) {
		$t = (string) $p->post_content;
		if ( '' === trim( $t ) || get_option( 'zq_trad_' . md5( $t ) ) || ! zq_parece_ingles( $t ) ) {
			// Aunque la descripción ya esté, pueden faltar la corta o las comodidades.
			zq_traducir( (string) $p->post_excerpt );
			zq_traducir( (string) zq_meta_pick( $p->ID, 'custom_amenities_description_es,amenities_description' ) );
			continue;
		}
		zq_traducir( $t );
		// También la descripción corta y las comodidades.
		zq_traducir( (string) $p->post_excerpt );
		zq_traducir( (string) zq_meta_pick( $p->ID, 'custom_amenities_description_es,amenities_description' ) );
		if ( ++$hechas >= 25 || get_transient( 'zq_trad_pausa' ) ) {
			break;
		}
	}
}
