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
	wp_register_style( 'zq-ficha', $url . 'assets/ficha.css', array(), ZQ_VERSION );
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
	$thumb = (int) get_post_thumbnail_id( $post_id );
	foreach ( $sources as $src ) {
		$id = zq_image_id( $post_id, $src );
		if ( $id && ( $id !== $thumb || 'destacada' === $src ) ) {
			return zq_image_html( $post_id, $src, $size, array( 'class' => trim( 'zq-f-img ' . $class ) ) );
		}
	}
	// Ninguna propia: destacada o imagen por defecto.
	return zq_image_html( $post_id, 'destacada', $size, array( 'class' => trim( 'zq-f-img ' . $class ) ) );
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
	$img = zq_image_url( $post_id );
	$ubi = zq_render_field( $post_id, 'ubicacion' );
	$sub = zq_render_field( $post_id, 'subtitulo' );
	return '<section class="zq-f-hero zq-bajo-menu"' . ( $img ? ' style="background-image:url(' . esc_url( $img ) . ')"' : '' ) . '>'
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
	$tabla = do_shortcode( '[qobrix_propiedades proyecto="' . (int) $post_id . '"]' );
	if ( ! $tabla ) {
		return '';
	}
	return '<section class="zq-f-sec zq-f-prop"><div class="zq-f-canvas zq-f-center">'
		. '<h2 class="zq-f-hc">Lista de propiedades</h2>' . $tabla
		. '</div></section>';
}

/* 03. Urbanización (foto con bloque negro a la izquierda + descripción) ---- */
function zq_ficha_urbanizacion( $post_id, $a ) {
	$desc = zq_render_field( $post_id, 'descripcion' );
	$com  = zq_render_field( $post_id, 'comodidades' );
	// Si la descripción ya incluye las comodidades (escritas a mano), no se repiten.
	if ( $com && false !== stripos( wp_strip_all_tags( $desc ), 'comodidades' ) ) {
		$com = '';
	}
	return '<section class="zq-f-sec zq-f-urb"><div class="zq-f-canvas zq-f-row">'
		. '<div class="zq-f-foto zq-f-foto--urb">' . zq_ficha_img( $post_id, array( 'galeria:1', 'destacada' ) ) . '</div>'
		. '<div class="zq-f-urb__txt">'
		. zq_ficha_h( zq_render_field( $post_id, 'descripcion_titulo' ) )
		. '<div class="zq-f-txt">' . $desc
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
	$form = '';
	if ( $a['formulario'] ) {
		$form = do_shortcode( $a['formulario'] );
	} elseif ( zq_form_template_id() && class_exists( '\Elementor\Plugin' ) ) {
		$form = \Elementor\Plugin::instance()->frontend->get_builder_content_for_display( zq_form_template_id(), true );
	} elseif ( zq_options()['form_shortcode'] ) {
		$form = do_shortcode( zq_options()['form_shortcode'] );
	}
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

/**
 * Formulario de contacto guardado en Elementor (global o plantilla): el elegido en Ajustes
 * o, si no hay ninguno, el que se llame «Form_contacto».
 */
function zq_form_template_id() {
	$id = (int) zq_options()['form_tpl'];
	if ( $id && 'publish' === get_post_status( $id ) ) {
		return $id;
	}
	$found = get_posts( array(
		'post_type'      => 'elementor_library',
		'post_status'    => 'publish',
		'title'          => 'Form_contacto',
		'fields'         => 'ids',
		'posts_per_page' => 1,
	) );
	return $found ? (int) $found[0] : 0;
}

/* Portada bajo el menú: margen negativo y menú por encima, transparente, en las promociones. */
add_action( 'wp_enqueue_scripts', function () {
	$h   = (int) zq_options()['menu_height'];
	$css = ':root{--zq-menu-h:' . $h . 'px;--zq-escala:' . ( (int) zq_options()['escala'] / 100 ) . '}';
	if ( zq_options()['menu_transparente'] ) {
		$css .= 'body.single-' . ZQ_CPT . ' .elementor-location-header,body.single-' . ZQ_CPT . ' header.site-header,body.single-' . ZQ_CPT . ' #masthead{position:relative;z-index:50;background:transparent!important}'
			. 'body.single-' . ZQ_CPT . ' .elementor-location-header>.e-con,body.single-' . ZQ_CPT . ' .elementor-location-header>.elementor-section,body.single-' . ZQ_CPT . ' .elementor-location-header>.elementor-element{background:transparent!important;background-color:transparent!important}';
	}
	wp_add_inline_style( 'zq-ficha', $css );
	wp_add_inline_style( 'zorom-qobrix', $css );
}, 30 );

/*
 * Los estilos del diseño (bloque negro, tabla, formulario…) también en el editor y la vista previa
 * de Elementor y en cualquier página hecha con Elementor, no solo en las promociones.
 */
function zq_enqueue_ficha_style() {
	if ( ! wp_style_is( 'zq-ficha', 'registered' ) ) {
		wp_register_style( 'zq-ficha', plugin_dir_url( ZQ_FILE ) . 'assets/ficha.css', array(), ZQ_VERSION );
	}
	wp_enqueue_style( 'zq-ficha' );
}

function zq_enqueue_fuentes() {
	if ( zq_options()['load_fonts'] ) {
		wp_enqueue_style( 'zq-fuentes', 'https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600&family=Inter:wght@300;600&display=swap', array(), null );
	}
}

// Web: en toda la web con Elementor activo; tipografías solo en promociones.
add_action( 'wp_enqueue_scripts', function () {
	if ( did_action( 'elementor/loaded' ) || is_singular( ZQ_CPT ) ) {
		zq_enqueue_ficha_style();
	}
	if ( is_singular( ZQ_CPT ) ) {
		zq_enqueue_fuentes();
	}
}, 999 );

// Editor y vista previa de Elementor.
add_action( 'elementor/preview/enqueue_styles', function () {
	zq_enqueue_ficha_style();
	zq_enqueue_fuentes();
} );
add_action( 'elementor/editor/after_enqueue_styles', 'zq_enqueue_ficha_style' );
