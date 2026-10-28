<?php
/**
 * Lectura y formato de campos de una promoción. Lo usan los shortcodes y Elementor.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Campos con nombre propio. El resto de campos de Qobrix se usan con su nombre original
 * (p. ej. «city», «construction_stage», «sea_view»).
 */
function zq_field_aliases() {
	$manual = array();
	foreach ( zq_manual_keys() as $k => $f ) {
		if ( 'image' !== $f[1] && 'ubicacion' !== $k ) {
			$manual[ $k ] = array( 'label' => 'Manual: ' . $f[0], 'format' => 'html' === $f[1] ? 'html' : 'texto' );
		}
	}
	return $manual + array(
		'titulo'            => array( 'label' => 'Título', 'format' => 'texto' ),
		'descripcion'       => array( 'label' => 'Descripción', 'format' => 'html' ),
		'descripcion_corta' => array( 'label' => 'Descripción corta', 'format' => 'html' ),
		'precio'            => array( 'label' => 'Precio desde', 'format' => 'precio' ),
		'unidades'          => array( 'label' => 'Unidades', 'format' => 'numero' ),
		'fecha'             => array( 'label' => 'Fecha', 'format' => 'fecha' ),
		'eficiencia'        => array( 'label' => 'Eficiencia energética', 'format' => 'texto' ),
		'comodidades'       => array( 'label' => 'Comodidades (español de Qobrix)', 'format' => 'html' ),
		'ubicacion'         => array( 'label' => 'Ubicación (manual o ciudad de Qobrix)', 'format' => 'texto' ),
		'coordenadas'       => array( 'label' => 'Coordenadas (lat,lng)', 'format' => 'texto' ),
		'enlace'            => array( 'label' => 'Enlace a la promoción', 'format' => 'url' ),
		'imagen'            => array( 'label' => 'URL de la imagen destacada', 'format' => 'url' ),
		'id_qobrix'         => array( 'label' => 'ID de Qobrix', 'format' => 'texto' ),
	);
}

function zq_formats() {
	return array(
		'auto'   => 'Automático',
		'texto'  => 'Texto',
		'html'   => 'Texto con párrafos',
		'precio' => 'Precio (656.000 €)',
		'numero' => 'Número (1.250)',
		'fecha'  => 'Fecha (01/03/2026)',
		'si_no'  => 'Sí / No',
		'km'     => 'Distancia (2,4 km)',
		'url'    => 'URL',
	);
}

/** Opciones para desplegables: alias + campos vistos en la última sincronización. */
function zq_field_options() {
	$opts = array();
	foreach ( zq_field_aliases() as $k => $a ) {
		$opts[ $k ] = $a['label'];
	}
	$status = (array) get_option( ZQ_STATUS, array() );
	if ( ! empty( $status['fields'] ) ) {
		foreach ( array_keys( $status['fields'] ) as $k ) {
			if ( ! isset( $opts[ $k ] ) ) {
				$opts[ $k ] = 'Qobrix: ' . $k;
			}
		}
	}
	return $opts;
}

/**
 * Promoción a la que se refiere un shortcode o etiqueta.
 * $ref vacío = la promoción actual (página de la promoción, plantilla del listado o pestaña).
 * $ref puede ser ID de WordPress, ID de Qobrix, slug o título.
 */
function zq_resolve_project( $ref = '' ) {
	static $cache = array();
	$ref = trim( (string) $ref );

	if ( '' === $ref ) {
		$id = get_the_ID();
		$id = ( $id && ZQ_CPT === get_post_type( $id ) ) ? (int) $id : 0;
		return (int) apply_filters( 'zq_current_project_fallback', $id );
	}
	if ( isset( $cache[ $ref ] ) ) {
		return $cache[ $ref ];
	}

	$id = 0;
	if ( ctype_digit( $ref ) && ZQ_CPT === get_post_type( (int) $ref ) ) {
		$id = (int) $ref;
	}
	if ( ! $id ) {
		$id = zq_find_post_by_qobrix_id( $ref );
	}
	if ( ! $id ) {
		$p  = get_page_by_path( sanitize_title( $ref ), OBJECT, ZQ_CPT );
		$id = $p ? (int) $p->ID : 0;
	}
	if ( ! $id ) {
		$ids = get_posts( array(
			'post_type'      => ZQ_CPT,
			'post_status'    => 'publish',
			'title'          => $ref,
			'fields'         => 'ids',
			'posts_per_page' => 1,
		) );
		$id  = $ids ? (int) $ids[0] : 0;
	}

	return $cache[ $ref ] = $id;
}

/** Valor sin formato de un campo. */
function zq_get_raw( $post_id, $campo ) {
	$o     = zq_options();
	$campo = sanitize_key( $campo );

	$manual = zq_manual_keys();
	if ( isset( $manual[ $campo ] ) && 'ubicacion' !== $campo ) {
		$v = zq_get_manual( $post_id, $campo );
		if ( '' === $v && 'localizacion_texto' === $campo ) {
			$v = zq_meta_pick( $post_id, 'custom_location_description_es,location_description' );
		}
		if ( '' === $v ) {
			$v = zq_manual_example( $post_id, $campo ); // Texto de ejemplo hasta que se escriba el propio.
		}
		return $v;
	}

	switch ( $campo ) {
		case 'ubicacion':
			$v = zq_get_manual( $post_id, 'ubicacion' );
			if ( '' !== $v ) {
				return $v;
			}
			$parts = array_filter( array(
				get_post_meta( $post_id, ZQ_META_PREFIX . 'city', true ),
				get_post_meta( $post_id, ZQ_META_PREFIX . 'district', true ),
			) );
			return implode( ', ', array_unique( $parts ) ); // «La Nucia, Alicante».
		case 'eficiencia':
			return strtoupper( (string) zq_meta_pick( $post_id, $o['field_energy'] ) );
		case 'comodidades':
			return zq_meta_pick( $post_id, 'custom_amenities_description_es,amenities_description' );
		case 'coordenadas':
			$c = zq_coordinates( $post_id );
			return $c ? $c[0] . ',' . $c[1] : '';
		case 'titulo':
			return get_the_title( $post_id );
		case 'descripcion':
			$v = zq_get_manual( $post_id, 'descripcion_es' );
			return '' !== $v ? $v : get_post_field( 'post_content', $post_id );
		case 'descripcion_corta':
			return get_post_field( 'post_excerpt', $post_id );
		case 'enlace':
			return get_permalink( $post_id );
		case 'imagen':
			return (string) get_the_post_thumbnail_url( $post_id, ZQ_IMAGE_SIZE );
		case 'id_qobrix':
			return get_post_meta( $post_id, '_qobrix_id', true );
		case 'precio':
			return zq_meta_pick( $post_id, $o['field_price'] );
		case 'unidades':
			return zq_meta_pick( $post_id, $o['field_units'] );
		case 'fecha':
			return zq_meta_pick( $post_id, $o['field_date'] );
	}

	return get_post_meta( $post_id, ZQ_META_PREFIX . $campo, true );
}

function zq_meta_pick( $post_id, $fields ) {
	foreach ( array_map( 'trim', explode( ',', $fields ) ) as $f ) {
		$v = '' !== $f ? get_post_meta( $post_id, ZQ_META_PREFIX . sanitize_key( $f ), true ) : '';
		if ( '' !== $v && null !== $v ) {
			return $v;
		}
	}
	return '';
}

/** Valor formateado y escapado, listo para imprimir. */
function zq_render_field( $post_id, $campo, $formato = 'auto' ) {
	$value = zq_get_raw( $post_id, $campo );
	if ( 'auto' === $formato || '' === $formato ) {
		$aliases = zq_field_aliases();
		$formato = isset( $aliases[ $campo ] ) ? $aliases[ $campo ]['format'] : 'texto';
	}
	if ( '' === $value || null === $value ) {
		return '';
	}

	switch ( $formato ) {
		case 'precio':
			return is_numeric( $value ) ? esc_html( number_format( (float) $value, 0, ',', '.' ) . ' €' ) : esc_html( $value );
		case 'numero':
			return is_numeric( $value ) ? esc_html( number_format( (float) $value, 0, ',', '.' ) ) : esc_html( $value );
		case 'fecha':
			$ts = strtotime( $value );
			return esc_html( $ts ? gmdate( 'd/m/Y', $ts ) : $value );
		case 'km':
			return is_numeric( $value ) ? esc_html( rtrim( rtrim( number_format( (float) $value, 1, ',', '.' ), '0' ), ',' ) . ' km' ) : esc_html( $value );
		case 'si_no':
			return in_array( strtolower( (string) $value ), array( '1', 'true', 'yes', 'si', 'sí' ), true ) ? 'Sí' : 'No';
		case 'html':
			return wpautop( wp_kses_post( $value ) );
		case 'url':
			return esc_url( $value );
		default:
			return esc_html( $value );
	}
}

/**
 * Coordenadas [lat, lng] del proyecto. Qobrix puede enviarlas como «lat,lng», como objeto
 * JSON o en campos separados; se prueban todas las formas.
 */
function zq_coordinates( $post_id ) {
	$candidates = array( 'coordinates', 'location_coordinates', 'geo_location' );
	foreach ( $candidates as $f ) {
		$v = get_post_meta( $post_id, ZQ_META_PREFIX . $f, true );
		if ( ! $v ) {
			continue;
		}
		$j = json_decode( $v, true );
		if ( is_array( $j ) ) {
			$lat = isset( $j['lat'] ) ? $j['lat'] : ( isset( $j['latitude'] ) ? $j['latitude'] : ( isset( $j[0] ) ? $j[0] : null ) );
			$lng = isset( $j['lng'] ) ? $j['lng'] : ( isset( $j['lon'] ) ? $j['lon'] : ( isset( $j['longitude'] ) ? $j['longitude'] : ( isset( $j[1] ) ? $j[1] : null ) ) );
		} else {
			$parts = array_map( 'trim', explode( ',', (string) $v ) );
			$lat   = isset( $parts[0] ) ? $parts[0] : null;
			$lng   = isset( $parts[1] ) ? $parts[1] : null;
		}
		if ( is_numeric( $lat ) && is_numeric( $lng ) ) {
			return array( (float) $lat, (float) $lng );
		}
	}
	$lat = get_post_meta( $post_id, ZQ_META_PREFIX . 'latitude', true );
	$lng = get_post_meta( $post_id, ZQ_META_PREFIX . 'longitude', true );
	return ( is_numeric( $lat ) && is_numeric( $lng ) ) ? array( (float) $lat, (float) $lng ) : array();
}

/** Imagen (ID de adjunto) según su origen: destacada, campo manual o galería:N. */
function zq_image_id( $post_id, $campo = 'destacada' ) {
	if ( '' === $campo || 'destacada' === $campo ) {
		return (int) get_post_thumbnail_id( $post_id );
	}
	if ( preg_match( '/^galeria:?(\d+)?$/', $campo, $m ) ) {
		$gal = array_values( (array) get_post_meta( $post_id, '_zq_galeria', true ) );
		$i   = isset( $m[1] ) ? max( 1, (int) $m[1] ) - 1 : 0;
		$id  = isset( $gal[ $i ] ) ? (int) $gal[ $i ] : 0;
	} else {
		$id = (int) zq_get_manual( $post_id, sanitize_key( $campo ) );
	}
	// Sin imagen propia: la destacada de la promoción.
	return $id && wp_attachment_is_image( $id ) ? $id : (int) get_post_thumbnail_id( $post_id );
}

/** URL de la imagen por defecto (cuando la promoción no tiene ninguna). */
function zq_placeholder_url() {
	return plugin_dir_url( ZQ_FILE ) . 'assets/placeholder.jpg';
}

/** <img> de un hueco de imagen: la indicada, la destacada o la imagen por defecto. */
function zq_image_html( $post_id, $campo = 'destacada', $size = 'large', array $attr = array() ) {
	$id   = zq_image_id( $post_id, $campo );
	$attr = $attr + array( 'alt' => get_the_title( $post_id ), 'loading' => 'lazy' );
	if ( $id ) {
		return wp_get_attachment_image( $id, $size, false, $attr );
	}
	$html = '<img src="' . esc_url( zq_placeholder_url() ) . '"';
	foreach ( $attr as $k => $v ) {
		$html .= ' ' . esc_attr( $k ) . '="' . esc_attr( $v ) . '"';
	}
	return $html . '>';
}

/** URL a tamaño completo del hueco de imagen (para fondos). */
function zq_image_url( $post_id, $campo = 'destacada' ) {
	$id = zq_image_id( $post_id, $campo );
	return $id ? (string) wp_get_attachment_image_url( $id, 'full' ) : zq_placeholder_url();
}

/** Orígenes de imagen para desplegables. */
function zq_image_sources() {
	$out = array( 'destacada' => 'Imagen destacada (Qobrix)' );
	foreach ( zq_manual_keys() as $k => $f ) {
		if ( 'image' === $f[1] ) {
			$out[ $k ] = 'Manual: ' . $f[0];
		}
	}
	for ( $i = 1; $i <= 6; $i++ ) {
		$out[ 'galeria:' . $i ] = 'Galería de Qobrix – foto ' . $i;
	}
	return $out;
}
