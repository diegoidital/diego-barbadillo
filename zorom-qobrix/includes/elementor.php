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
// Título «Comodidades» de la plantilla: si la promoción no tiene comodidades, no se muestra.
add_filter( 'elementor/widget/render_content', function ( $content, $widget ) {
	if ( is_object( $widget ) && 'heading' === $widget->get_name() && 'comodidades' === strtolower( trim( wp_strip_all_tags( (string) $widget->get_settings( 'title' ) ) ) ) ) {
		$post_id = zq_resolve_project( '' );
		if ( $post_id && '' === trim( wp_strip_all_tags( (string) zq_render_field( $post_id, 'comodidades' ) ) ) ) {
			return '';
		}
	}
	return $content;
}, 19, 2 );

add_filter( 'elementor/widget/render_content', function ( $content ) {
	return ( is_string( $content ) && false !== strpos( $content, '[qobrix' ) ) ? do_shortcode( $content ) : $content;
}, 20 );

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
