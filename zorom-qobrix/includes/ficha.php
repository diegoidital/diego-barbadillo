<?php
/**
 * Página de promoción calcada del Figma («Proyecto», lienzo de 1440 px).
 *
 *   [qobrix_ficha]                         Todas las secciones, en el orden del diseño.
 *   [qobrix_ficha seccion="zona"]          Una sola sección (para montarlas sueltas en Elementor).
 *
 * Secciones: hero, zona, caracteristicas, propiedades, urbanizacion, galeria, localizacion, zona2, contacto.
 * Estilos en assets/ficha.css; medidas tomadas de los nodos del Figma.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function zq_ficha_secciones() {
	return array( 'hero', 'zona', 'caracteristicas', 'propiedades', 'urbanizacion', 'galeria', 'localizacion', 'zona2', 'contacto' );
}

add_action( 'wp_enqueue_scripts', function () {
	$url = plugin_dir_url( ZQ_FILE );
	wp_register_style( 'zq-fuentes', 'https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600&family=Inter:wght@300;600&display=swap', array(), null );
	$deps = zq_options()['load_fonts'] ? array( 'zq-fuentes' ) : array();
	wp_register_style( 'zq-ficha', $url . 'assets/ficha.css', $deps, ZQ_VERSION );
	if ( is_singular( ZQ_CPT ) ) {
		wp_enqueue_style( 'zq-ficha' );
	}
} );

/** Icono SVG del Figma (assets/icons), coloreado con el color del texto. */
function zq_icon( $name ) {
	static $cache = array();
	$name = sanitize_key( $name );
	if ( ! isset( $cache[ $name ] ) ) {
		$file           = ZQ_DIR . 'assets/icons/' . $name . '.svg';
		$svg            = is_readable( $file ) ? (string) file_get_contents( $file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions
		$svg            = preg_replace( '/(stroke|fill)="#(?:101215|000000|000)"/i', '$1="currentColor"', $svg );
		$svg            = preg_replace( '/\sstyle="[^"]*"/', '', $svg );
		$cache[ $name ] = $svg ? preg_replace( '/^<svg/', '<svg class="zq-icon" aria-hidden="true" focusable="false"', trim( $svg ) ) : '';
	}
	return $cache[ $name ];
}

/** Primera imagen disponible de una lista de orígenes (destacada, zona_imagen, galeria:N…). */
function zq_ficha_img( $post_id, array $sources, $size = 'large', $class = '' ) {
	foreach ( $sources as $src ) {
		$id = zq_image_id( $post_id, $src );
		if ( $id ) {
			return wp_get_attachment_image( $id, $size, false, array(
				'class'   => trim( 'zq-f-img ' . $class ),
				'alt'     => get_the_title( $post_id ),
				'loading' => 'lazy',
			) );
		}
	}
	return '';
}

function zq_ficha_h( $text, $class = 'zq-f-h' ) {
	return '' === trim( wp_strip_all_tags( $text ) ) ? '' : '<h2 class="' . esc_attr( $class ) . '">' . $text . '</h2>';
}

add_shortcode( 'qobrix_ficha', function ( $atts ) {
	$a = shortcode_atts( array(
		'seccion'    => '',
		'proyecto'   => '',
		'formulario' => '',
		'boton'      => 'Solicitar información',
		'enlace'     => '#contacto',
	), $atts, 'qobrix_ficha' );

	$post_id = zq_resolve_project( $a['proyecto'] );
	if ( ! $post_id ) {
		return '';
	}
	wp_enqueue_style( 'zq-ficha' );

	$list = $a['seccion'] ? array_map( 'trim', explode( ',', $a['seccion'] ) ) : zq_ficha_secciones();
	$out  = '';
	foreach ( $list as $s ) {
		if ( in_array( $s, zq_ficha_secciones(), true ) ) {
			$out .= call_user_func( 'zq_ficha_' . $s, $post_id, $a );
		}
	}
	return '<div class="zq-f">' . $out . '</div>';
} );

/* 01. Portada ------------------------------------------------------------- */
function zq_ficha_hero( $post_id, $a ) {
	$img = (string) wp_get_attachment_image_url( zq_image_id( $post_id ), 'full' );
	$ubi = zq_render_field( $post_id, 'ubicacion' );
	$sub = zq_render_field( $post_id, 'subtitulo' );
	return '<section class="zq-f-hero"' . ( $img ? ' style="background-image:url(' . esc_url( $img ) . ')"' : '' ) . '>'
		. '<div class="zq-f-canvas"><div class="zq-f-hero__txt">'
		. '<h1 class="zq-f-hero__title">' . esc_html( get_the_title( $post_id ) ) . '</h1>'
		. ( $ubi ? '<p class="zq-f-hero__ubi">' . $ubi . '</p>' : '' )
		. ( $sub ? '<p class="zq-f-hero__sub">' . $sub . '</p>' : '' )
		. ( $a['boton'] ? '<a class="zq-f-btn zq-f-btn--blanco" href="' . esc_url( $a['enlace'] ) . '">' . esc_html( $a['boton'] ) . '</a>' : '' )
		. '</div></div></section>';
}

/* 02. Zona (texto + foto con bloque negro a la derecha) ------------------- */
function zq_ficha_zona( $post_id, $a ) {
	$sub = zq_render_field( $post_id, 'zona_subtitulo' );
	return '<section class="zq-f-sec zq-f-zona"><div class="zq-f-canvas zq-f-row">'
		. '<div class="zq-f-zona__txt">'
		. zq_ficha_h( zq_render_field( $post_id, 'zona_titulo' ) )
		. ( $sub ? '<p class="zq-f-sub">' . $sub . '</p>' : '' )
		. '<div class="zq-f-txt">' . zq_render_field( $post_id, 'zona_texto' ) . '</div>'
		. '</div>'
		. '<div class="zq-f-foto zq-f-foto--zona">' . zq_ficha_img( $post_id, array( 'zona_imagen', 'galeria:3', 'destacada' ) ) . '</div>'
		. '</div></section>';
}

/* 03. Características ------------------------------------------------------ */
function zq_ficha_caracteristicas( $post_id, $a ) {
	$ef    = zq_render_field( $post_id, 'eficiencia' );
	$items = array(
		array( 'fecha', 'Fecha', zq_render_field( $post_id, 'fecha' ) ),
		array( 'precio', 'Precio', zq_render_field( $post_id, 'precio' ) ),
		array( 'eficiencia', 'Eficiencia', $ef ? 'Categoría ' . $ef : '' ),
		array( 'unidades', 'Unidades', zq_render_field( $post_id, 'unidades' ) ),
	);
	$cards = '';
	foreach ( $items as $it ) {
		if ( '' === $it[2] ) {
			continue;
		}
		$cards .= '<div class="zq-f-card"><div class="zq-f-card__label">' . zq_icon( $it[0] ) . '<span>' . esc_html( $it[1] ) . '</span></div>'
			. '<div class="zq-f-card__value">' . $it[2] . '</div></div>';
	}
	return '<section class="zq-f-sec zq-f-caract"><div class="zq-f-canvas zq-f-center">'
		. '<h2 class="zq-f-hc">' . esc_html( get_the_title( $post_id ) ) . '</h2>'
		. '<div class="zq-f-cards">' . $cards . '</div>'
		. '</div></section>';
}

/* 07. Lista de propiedades ------------------------------------------------- */
function zq_ficha_propiedades( $post_id, $a ) {
	$tabla = do_shortcode( '[qobrix_propiedades proyecto="' . (int) $post_id . '" columnas="ref:Referencia,floor:Piso,bedrooms:Dormitorios,bathrooms:Baños,plot_area_amount:Área parcela:m2,covered_area_amount:Área interna:m2,list_selling_price_amount:Precio:precio,status:Estado:estado"]' );
	if ( ! $tabla ) {
		return '';
	}
	return '<section class="zq-f-sec zq-f-prop"><div class="zq-f-canvas zq-f-center">'
		. '<h2 class="zq-f-hc">Lista de propiedades</h2>' . $tabla
		. '</div></section>';
}

/* 03. Urbanización (foto con bloque negro a la izquierda + descripción) ---- */
function zq_ficha_urbanizacion( $post_id, $a ) {
	$com = zq_render_field( $post_id, 'comodidades' );
	return '<section class="zq-f-sec zq-f-urb"><div class="zq-f-canvas zq-f-row">'
		. '<div class="zq-f-foto zq-f-foto--urb">' . zq_ficha_img( $post_id, array( 'galeria:1', 'destacada' ) ) . '</div>'
		. '<div class="zq-f-urb__txt">'
		. zq_ficha_h( zq_render_field( $post_id, 'descripcion_titulo' ) )
		. '<div class="zq-f-txt">' . zq_render_field( $post_id, 'descripcion' )
		. ( $com ? '<h3 class="zq-f-h3">Comodidades</h3>' . $com : '' )
		. '</div></div>'
		. '</div></section>';
}

/* 04. Galería -------------------------------------------------------------- */
function zq_ficha_galeria( $post_id, $a ) {
	$ids = array_slice( array_values( (array) get_post_meta( $post_id, '_zq_galeria', true ) ), 1, 2 );
	if ( count( $ids ) < 2 ) {
		$ids = array_slice( array_values( (array) get_post_meta( $post_id, '_zq_galeria', true ) ), 0, 2 );
	}
	if ( ! $ids ) {
		return '';
	}
	$html = '';
	foreach ( $ids as $id ) {
		$html .= '<a class="zq-f-gal__item" href="' . esc_url( wp_get_attachment_image_url( $id, 'full' ) ) . '" data-elementor-open-lightbox="yes" data-elementor-lightbox-slideshow="zq-' . (int) $post_id . '">'
			. wp_get_attachment_image( $id, 'large', false, array( 'loading' => 'lazy', 'class' => 'zq-f-img' ) ) . '</a>';
	}
	return '<section class="zq-f-sec zq-f-gal"><div class="zq-f-canvas"><div class="zq-f-gal__grid">' . $html . '</div></div></section>';
}

/* 05. Localización --------------------------------------------------------- */
function zq_ficha_localizacion( $post_id, $a ) {
	// Elementos fijos del diseño; solo cambia el número de cada promoción.
	$dist = array(
		array( 'aeropuerto', 'Aeropuerto', 'distance_from_airport_amount' ),  // Aeropuerto más cercano.
		array( 'playa', 'Playa', 'distance_from_beach_amount' ),              // Playa más cercana.
		array( 'centro', 'Centro', 'distance_from_centre_amount' ),           // Centro de la localidad.
		array( 'hospital', 'Hospital', 'distance_from_hospital_amount' ),     // Hospital más cercano.
		array( 'escuela', 'Escuela', 'distance_from_school_amount' ),         // Escuela más próxima.
		array( 'tiendas', 'Tiendas', 'distance_from_shops_amount' ),          // Zona de compras más cercana.
	);
	$cells = '';
	foreach ( $dist as $d ) {
		$v = zq_get_manual( $post_id, 'dist_' . $d[0] );
		$v = '' !== trim( (string) $v ) ? $v : get_post_meta( $post_id, ZQ_META_PREFIX . $d[2], true );
		$v = zq_ficha_km( $v );
		$cells .= '<div class="zq-f-dist"><div class="zq-f-card__label">' . zq_icon( $d[0] ) . '<span>' . esc_html( $d[1] ) . '</span></div><div class="zq-f-card__value">' . $v . '</div></div>';
	}
	$c   = zq_coordinates( $post_id );
	$q   = $c ? $c[0] . ',' . $c[1] : trim( wp_strip_all_tags( zq_render_field( $post_id, 'ubicacion' ) ) );
	$map = $q ? '<div class="zq-f-mapa"><iframe src="' . esc_url( 'https://maps.google.com/maps?q=' . rawurlencode( $q ) . '&z=14&output=embed' ) . '" title="' . esc_attr( 'Mapa de ' . get_the_title( $post_id ) ) . '" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe></div>' : '';

	return '<section class="zq-f-sec zq-f-loc"><div class="zq-f-canvas zq-f-row">'
		. '<div class="zq-f-loc__txt">'
		. zq_ficha_h( zq_render_field( $post_id, 'localizacion_titulo' ) )
		. '<div class="zq-f-txt zq-f-txt--22">' . zq_render_field( $post_id, 'localizacion_texto' ) . '</div>'
		. ( $cells ? '<div class="zq-f-dists">' . $cells . '</div>' : '' )
		. '</div>' . $map
		. '</div></section>';
}

/** Distancia como en el diseño: «78 km», «2.5 km», «0.7 km»; sin dato, «—». */
function zq_ficha_km( $v ) {
	$v = str_replace( ',', '.', trim( (string) $v ) );
	if ( '' === $v || ! is_numeric( $v ) ) {
		return '—';
	}
	$n = round( (float) $v, 1 );
	return esc_html( ( floor( $n ) == $n ? number_format( $n, 0, '.', '' ) : number_format( $n, 1, '.', '' ) ) . ' km' );
}

/* 06. Zona, segundo bloque (foto con bloque negro a la izquierda) ---------- */
function zq_ficha_zona2( $post_id, $a ) {
	return '<section class="zq-f-sec zq-f-zona2"><div class="zq-f-canvas zq-f-row">'
		. '<div class="zq-f-foto zq-f-foto--zona2">' . zq_ficha_img( $post_id, array( 'zona2_imagen', 'galeria:4', 'galeria:2', 'destacada' ) ) . '</div>'
		. '<div class="zq-f-zona2__txt">'
		. zq_ficha_h( zq_render_field( $post_id, 'zona2_titulo' ) )
		. '<div class="zq-f-txt">' . zq_render_field( $post_id, 'zona2_texto' ) . '</div>'
		. '</div>'
		. '</div></section>';
}

/* 08. Contacto ------------------------------------------------------------- */
function zq_ficha_contacto( $post_id, $a ) {
	$form = $a['formulario'] ? $a['formulario'] : zq_options()['form_shortcode'];
	$form = $form ? do_shortcode( $form ) : '';
	if ( ! $form ) {
		$form = current_user_can( 'manage_options' )
			? '<p class="zq-f-aviso">Falta el formulario: indica su shortcode en Qobrix → Ajustes («Formulario de contacto»).</p>'
			: '';
	}
	return '<section class="zq-f-sec zq-f-contacto" id="contacto"><div class="zq-f-canvas zq-f-center">'
		. '<h2 class="zq-f-hc">Contacta</h2>'
		. '<div class="zq-f-form">' . $form . '</div>'
		. '</div></section>';
}
