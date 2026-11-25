<?php
/**
 * Integración con Elementor: grupo de etiquetas dinámicas «Qobrix».
 * (Las etiquetas dinámicas solo aparecen en el editor con Elementor Pro;
 *  sin Pro se usan los shortcodes [qobrix …].)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** ¿Estamos en el editor o la vista previa de Elementor? */
function zq_is_elementor_editor() {
	if ( ! class_exists( '\Elementor\Plugin' ) || ! isset( \Elementor\Plugin::$instance ) ) {
		return false;
	}
	$el = \Elementor\Plugin::$instance;
	return ( isset( $el->editor ) && $el->editor->is_edit_mode() )
		|| ( isset( $el->preview ) && $el->preview->is_preview_mode() );
}

/** En el editor de una plantilla no hay promoción actual: se usa la primera para previsualizar. */
add_filter( 'zq_current_project_fallback', function ( $id ) {
	if ( $id || ! zq_is_elementor_editor() ) {
		return $id;
	}
	$first = get_posts( array(
		'post_type'      => ZQ_CPT,
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
	) );
	return $first ? (int) $first[0] : 0;
} );

/*
 * Caché de elementos de Elementor: guarda el HTML de títulos y cajas de icono con shortcodes
 * de una página y lo reutiliza en las demás que usan la misma plantilla (todas las promociones
 * mostrarían los datos de la primera, p. ej. Amiral). En las páginas de promoción y mientras se pintan
 * plantillas del plugin se desactiva; el resto de la web conserva su caché.
 */
add_filter( 'pre_option_elementor_element_cache_ttl', function ( $v ) {
	return ( is_singular( ZQ_CPT ) || ! empty( $GLOBALS['zq_sin_cache_elementor'] ) ) ? 'disable' : $v;
} );

add_filter( 'elementor/element/is_dynamic_content', function ( $is_dynamic, $element = null ) {
	if ( $is_dynamic || ! is_object( $element ) || ! method_exists( $element, 'get_settings' ) ) {
		return $is_dynamic;
	}
	return false !== strpos( wp_json_encode( $element->get_settings() ), '[qobrix' ) ? true : $is_dynamic;
}, 10, 2 );

add_action( 'elementor/dynamic_tags/register', function ( $dynamic_tags ) {
	if ( ! class_exists( '\Elementor\Core\DynamicTags\Tag' ) ) {
		return;
	}
	require_once ZQ_DIR . 'includes/elementor-tags.php';

	$dynamic_tags->register_group( 'qobrix', array( 'title' => 'Qobrix' ) );
	$dynamic_tags->register( new ZQ_Tag_Campo() );
	$dynamic_tags->register( new ZQ_Tag_Imagen() );
	$dynamic_tags->register( new ZQ_Tag_Enlace() );
	$dynamic_tags->register( new ZQ_Tag_Galeria() );
} );

/*
 * Shortcodes [qobrix…] en cualquier widget de Elementor (Título, Caja de icono, Botón…):
 * Elementor no siempre los procesa fuera del Editor de texto, así que se procesan aquí.
 */
/** ¿La descripción ya incluye las comodidades (mismo texto o su propio título «Comodidades»)? */
function zq_comodidades_duplicadas( $post_id ) {
	static $c = array();
	if ( isset( $c[ $post_id ] ) ) {
		return $c[ $post_id ];
	}
	$norm = function ( $h ) {
		return trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( (string) $h ), ENT_QUOTES, 'UTF-8' ) ) );
	};
	$desc = zq_render_field( $post_id, 'descripcion' );
	$com  = $norm( zq_render_field( $post_id, 'comodidades' ) );
	$dup  = false;
	if ( '' !== $com && '' !== $norm( $desc ) ) {
		$dup = false !== mb_stripos( $norm( $desc ), mb_substr( $com, 0, 60 ) )
			|| (bool) preg_match( '/(^|>|\n)\s*(<[^>]+>\s*)*comodidades\s*:?\s*(<|\n|$)/iu', $desc );
	}
	return $c[ $post_id ] = $dup;
}

// Título y texto «Comodidades» de la plantilla: se ocultan si la promoción no tiene comodidades
// o si la descripción ya las incluye (para que no salgan dos veces).
add_filter( 'elementor/widget/render_content', function ( $content, $widget ) {
	if ( ! is_object( $widget ) ) {
		return $content;
	}
	$name = $widget->get_name();
	$es_titulo = 'heading' === $name && 'comodidades' === strtolower( trim( wp_strip_all_tags( (string) $widget->get_settings( 'title' ) ) ) );
	$es_texto  = 'text-editor' === $name && false !== strpos( (string) $widget->get_settings( 'editor' ), 'campo="comodidades"' );
	if ( $es_titulo || $es_texto ) {
		$post_id = zq_resolve_project( '' );
		if ( $post_id && ( '' === trim( wp_strip_all_tags( (string) zq_render_field( $post_id, 'comodidades' ) ) ) || zq_comodidades_duplicadas( $post_id ) ) ) {
			return '';
		}
	}
	return $content;
}, 19, 2 );

add_filter( 'elementor/widget/render_content', function ( $content ) {
	return ( is_string( $content ) && false !== strpos( $content, '[qobrix' ) ) ? do_shortcode( $content ) : $content;
}, 20 );

// Cajas de icono con datos de Qobrix (ficha técnica): si el dato no existe se muestra «—»
// para que todas las cajas mantengan la misma altura.
add_filter( 'elementor/widget/render_content', function ( $content, $widget ) {
	if ( ! is_string( $content ) || ! is_object( $widget ) || 'icon-box' !== $widget->get_name() || false === strpos( (string) $widget->get_settings( 'description_text' ), '[qobrix' ) ) {
		return $content;
	}
	return preg_replace( '#(<(p|div)[^>]*elementor-icon-box-description[^>]*>)\s*(</\2>)#i', '$1—$3', $content );
}, 25, 2 );

/*
 * Vídeo en lugar de la imagen de «Urbanización»: si la promoción tiene «URL del vídeo»,
 * la imagen con bloque negro de la descripción (galeria:1, o cualquier imagen con la clase
 * «zq-video-aqui») pasa a ser el vídeo. Sin vídeo se queda la imagen.
 */
function zq_es_imagen_urbanizacion( $widget ) {
	if ( ! is_object( $widget ) || 'image' !== $widget->get_name() ) {
		return false;
	}
	$clases = (string) $widget->get_settings( '_css_classes' );
	if ( false !== strpos( $clases, 'zq-video-aqui' ) ) {
		return true;
	}
	if ( false === strpos( $clases, 'zq-bloque-izda' ) && false === strpos( $clases, 'zq-bloque-dcha' ) ) {
		return false;
	}
	$data = (array) $widget->get_data( 'settings' );
	$dyn  = isset( $data['__dynamic__']['image'] ) ? rawurldecode( rawurldecode( (string) $data['__dynamic__']['image'] ) ) : '';
	return false !== strpos( $dyn, 'galeria:1"' );
}

function zq_video_de_pagina() {
	$id = zq_resolve_project( '' );
	return $id ? zq_video_html( zq_get_manual( $id, 'video_url' ) ) : '';
}

add_action( 'elementor/frontend/widget/before_render', function ( $widget ) {
	if ( zq_es_imagen_urbanizacion( $widget ) && '' !== zq_video_de_pagina() ) {
		$widget->add_render_attribute( '_wrapper', 'class', 'zq-bloque-video' );
	}
} );

add_filter( 'elementor/widget/render_content', function ( $content, $widget ) {
	if ( ! zq_es_imagen_urbanizacion( $widget ) ) {
		return $content;
	}
	$video = zq_video_de_pagina();
	if ( '' === $video ) {
		return $content;
	}
	$GLOBALS['zq_video_usado'] = true; // El bloque [qobrix_video] suelto ya no se repite.
	wp_enqueue_style( 'zorom-qobrix' );
	return '<div class="elementor-widget-container"><div class="zq-video-relleno">' . $video . '</div></div>';
}, 18, 2 );

/*
 * Fecha de las entradas en los widgets «Publicaciones» y «Archivo» de Elementor con el formato
 * del diseño (21·09·2026), sin cambiar el formato de fecha del resto de la web.
 */
add_action( 'elementor/widget/before_render_content', function ( $widget ) {
	if ( in_array( $widget->get_name(), array( 'posts', 'archive-posts' ), true ) ) {
		$GLOBALS['zq_fecha_posts'] = true;
	}
} );
add_action( 'elementor/frontend/widget/after_render', function ( $widget ) {
	if ( in_array( $widget->get_name(), array( 'posts', 'archive-posts' ), true ) ) {
		$GLOBALS['zq_fecha_posts'] = false;
	}
} );
add_filter( 'get_the_date', function ( $fecha, $formato, $post ) {
	return empty( $GLOBALS['zq_fecha_posts'] ) ? $fecha : get_post_time( apply_filters( 'zq_formato_fecha_actualidad', 'd·m·Y' ), false, $post, true );
}, 10, 3 );

/*
 * Páginas legales: el menú sin el efecto de transparencia al hacer scroll
 * (Elementor → Estilo → Fondo → Efectos de scroll → Transparencia).
 */
add_filter( 'body_class', function ( $clases ) {
	$legales = apply_filters( 'zq_paginas_menu_fijo', array( 'politica-de-cookies', 'aviso-legal', 'politica-de-privacidad' ) );
	if ( is_page( $legales ) ) {
		$clases[] = 'zq-menu-sin-efecto';
	}
	return $clases;
} );

/*
 * Cabecera de las entradas: la imagen destacada como fondo (con su velo oscuro).
 * El fondo dinámico «Imagen destacada» de los Flexbox de Elementor 4 no sale en la web
 * (el CSS de la plantilla es el mismo para todas las entradas), así que se pone aquí.
 * Se aplica al contenedor con la clase «zq-fondo-destacada» y al de la plantilla «Entrada blog».
 */
add_action( 'wp_head', function () {
	if ( ! is_singular() || ! has_post_thumbnail() ) {
		return;
	}
	$url = get_the_post_thumbnail_url( null, 'full' );
	if ( ! $url ) {
		return;
	}
	$sel = implode( ',', apply_filters( 'zq_selectores_fondo_destacada', array(
		'.zq-fondo-destacada',
		'.elementor-location-single [data-id="a0a643c"]',
	) ) );
	printf(
		'<style id="zq-fondo-destacada">%1$s{background-image:linear-gradient(var(--zq-velo,#0000006b),var(--zq-velo,#0000006b)),url("%2$s")!important;background-size:cover!important;background-position:center!important;background-repeat:no-repeat!important}</style>' . "\n",
		$sel, // phpcs:ignore WordPress.Security.EscapeOutput -- selectores fijos o filtrados por código.
		esc_url( $url )
	);
}, 99 );
