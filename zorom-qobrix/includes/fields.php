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
		'distancia_aeropuerto' => array( 'label' => 'Distancia: aeropuerto más cercano', 'format' => 'km' ),
		'distancia_playa'      => array( 'label' => 'Distancia: playa más cercana', 'format' => 'km' ),
		'distancia_centro'     => array( 'label' => 'Distancia: centro de la localidad', 'format' => 'km' ),
		'distancia_hospital'   => array( 'label' => 'Distancia: hospital más cercano', 'format' => 'km' ),
		'distancia_escuela'    => array( 'label' => 'Distancia: escuela más próxima', 'format' => 'km' ),
		'distancia_tiendas'    => array( 'label' => 'Distancia: zona de compras', 'format' => 'km' ),
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
		// Dentro de una plantilla/bloque guardado de Elementor el «post actual» es la plantilla:
		// se usa entonces la promoción de la página que se está visitando.
		if ( ! $id && ! is_admin() && is_singular( ZQ_CPT ) ) {
			$id = (int) get_queried_object_id();
		}
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
		case 'distancia_aeropuerto':
		case 'distancia_playa':
		case 'distancia_centro':
		case 'distancia_hospital':
		case 'distancia_escuela':
		case 'distancia_tiendas':
			$k   = substr( $campo, 10 );
			$map = array( 'aeropuerto' => 'airport', 'playa' => 'beach', 'centro' => 'centre', 'hospital' => 'hospital', 'escuela' => 'school', 'tiendas' => 'shops' );
			$v   = zq_get_manual( $post_id, 'dist_' . $k );
			return '' !== trim( (string) $v ) ? $v : get_post_meta( $post_id, ZQ_META_PREFIX . 'distance_from_' . $map[ $k ] . '_amount', true );
		case 'comodidades':
			return zq_meta_pick( $post_id, 'custom_amenities_description_es,amenities_description' );
		case 'coordenadas':
			$c = zq_coordinates( $post_id );
			return $c ? $c[0] . ',' . $c[1] : '';
		case 'titulo':
			return get_the_title( $post_id );
		case 'descripcion':
			$v = zq_get_manual( $post_id, 'descripcion_es' );
			return '' !== $v ? $v : zq_traducir( get_post_field( 'post_content', $post_id ) );
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
		case 'km': // Como en el diseño: «78 km», «2.5 km».
			$n = str_replace( ',', '.', (string) $value );
			return is_numeric( $n ) ? esc_html( rtrim( rtrim( number_format( (float) $n, 1, '.', '' ), '0' ), '.' ) . ' km' ) : esc_html( $value );
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
	$id = $id && wp_attachment_is_image( $id ) ? $id : (int) get_post_thumbnail_id( $post_id );
	return zq_imagen_no_repetida( $post_id, $campo, $id );
}

/**
 * Evita que la misma foto (o una casi igual) salga dos veces en la página de una promoción:
 * si el hueco cae en una foto ya usada, toma otra de la galería que no se parezca a ninguna.
 * Cada hueco recuerda su foto, así que pedirlo varias veces devuelve siempre la misma.
 */
function zq_imagen_no_repetida( $post_id, $campo, $id ) {
	static $huecos = array();
	if ( is_admin() && ! wp_doing_ajax() ) {
		return $id;
	}
	$k = (int) $post_id;
	if ( isset( $huecos[ $k ][ $campo ] ) ) {
		return $huecos[ $k ][ $campo ];
	}
	$usadas = isset( $GLOBALS['zq_fotos_usadas'][ $k ] ) ? $GLOBALS['zq_fotos_usadas'][ $k ] : array();
	if ( $id && zq_se_parece( $id, $usadas ) ) {
		$gal = array_values( (array) get_post_meta( $post_id, '_zq_galeria', true ) );
		foreach ( $gal as $g ) {
			$g = (int) $g;
			if ( $g && wp_attachment_is_image( $g ) && ! zq_se_parece( $g, $usadas ) ) {
				$id = $g;
				break;
			}
		}
	}
	$huecos[ $k ][ $campo ] = $id;
	zq_registrar_fotos( $k, array( $id ) );
	return $id;
}

/** Anota fotos ya mostradas en la página de la promoción. */
function zq_registrar_fotos( $post_id, array $ids ) {
	$k = (int) $post_id;
	$GLOBALS['zq_fotos_usadas'][ $k ] = array_values( array_unique( array_merge( isset( $GLOBALS['zq_fotos_usadas'][ $k ] ) ? $GLOBALS['zq_fotos_usadas'][ $k ] : array(), array_filter( array_map( 'intval', $ids ) ) ) ) );
}

/** ¿La foto es la misma o casi igual que alguna de la lista? */
function zq_se_parece( $id, array $lista, $max = 10 ) {
	$h = zq_img_huella( $id );
	foreach ( $lista as $o ) {
		if ( (int) $o === (int) $id ) {
			return true;
		}
		$ho = zq_img_huella( (int) $o );
		if ( $h && $ho && count( array_diff_assoc( str_split( $h ), str_split( $ho ) ) ) <= $max ) {
			return true;
		}
	}
	return false;
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

/**
 * Huella visual de una imagen (8×8, media de grises) para detectar fotos casi iguales
 * (la misma estancia desde otro ángulo). Se guarda en el adjunto.
 */
function zq_img_huella( $id ) {
	$h = get_post_meta( $id, '_zq_huella', true );
	if ( $h ) {
		return $h;
	}
	$file = get_attached_file( $id );
	$med  = image_get_intermediate_size( $id, 'thumbnail' );
	if ( $med && ! empty( $med['path'] ) ) {
		$up   = wp_upload_dir();
		$file = trailingslashit( $up['basedir'] ) . $med['path'];
	}
	if ( ! $file || ! is_readable( $file ) || ! function_exists( 'imagecreatefromstring' ) ) {
		return '';
	}
	$src = @imagecreatefromstring( (string) file_get_contents( $file ) ); // phpcs:ignore
	if ( ! $src ) {
		return '';
	}
	$t = imagecreatetruecolor( 8, 8 );
	imagecopyresampled( $t, $src, 0, 0, 0, 0, 8, 8, imagesx( $src ), imagesy( $src ) );
	$g = array();
	for ( $y = 0; $y < 8; $y++ ) {
		for ( $x = 0; $x < 8; $x++ ) {
			$c   = imagecolorat( $t, $x, $y );
			$g[] = ( ( $c >> 16 ) & 255 ) * .3 + ( ( $c >> 8 ) & 255 ) * .59 + ( $c & 255 ) * .11;
		}
	}
	$avg = array_sum( $g ) / 64;
	$h   = '';
	foreach ( $g as $v ) {
		$h .= $v >= $avg ? '1' : '0';
	}
	update_post_meta( $id, '_zq_huella', $h );
	return $h;
}

/**
 * Elige $n fotos de la galería que no se parezcan entre sí: repartidas por toda la galería
 * y descartando las casi iguales. $excluir: fotos ya usadas en otras partes de la página.
 */
function zq_fotos_distintas( array $ids, $n, array $excluir = array(), $post_id = 0 ) {
	$ids = array_values( array_diff( array_map( 'intval', array_filter( $ids ) ), array_map( 'intval', $excluir ) ) );
	$len = count( $ids );
	if ( $len <= $n ) {
		return $ids;
	}
	// Orden de prueba: primero fotos repartidas a lo largo de la galería, luego el resto.
	$orden = array();
	for ( $i = 0; $i < $n; $i++ ) {
		$orden[] = $ids[ (int) floor( ( $i + .5 ) * $len / $n ) ];
	}
	$orden = array_values( array_unique( array_merge( $orden, $ids ) ) );
	$usar  = array();
	$huell = array();
	$pagina = ( $post_id && isset( $GLOBALS['zq_fotos_usadas'][ (int) $post_id ] ) ) ? $GLOBALS['zq_fotos_usadas'][ (int) $post_id ] : array();
	$ids   = array_values( array_filter( $ids, function ( $id ) use ( $pagina ) {
		return ! $pagina || ! zq_se_parece( $id, $pagina );
	} ) ) ?: $ids;
	$orden = array_values( array_intersect( $orden, $ids ) ) ?: $ids;
	foreach ( array( 18, 10, -1 ) as $min ) { // Si no hay bastantes distintas, se relaja el criterio.
		foreach ( $orden as $id ) {
			if ( count( $usar ) >= $n ) {
				break 2;
			}
			if ( in_array( $id, $usar, true ) ) {
				continue;
			}
			$h  = zq_img_huella( $id );
			$ok = true;
			foreach ( $huell as $o ) {
				if ( $h && $o && count( array_diff_assoc( str_split( $h ), str_split( $o ) ) ) <= $min ) {
					$ok = false;
					break;
				}
			}
			if ( $ok ) {
				$usar[]  = $id;
				$huell[] = $h;
			}
		}
	}
	return $usar;
}
