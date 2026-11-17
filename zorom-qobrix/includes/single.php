<?php
/**
 * Página de cada promoción: si en Qobrix → Ajustes se elige una plantilla de Elementor,
 * todas las promociones se pintan con ella (cabecera y pie del tema incluidos).
 *
 * No se aplica si:
 *  - la promoción concreta se ha editado con Elementor (su diseño propio manda), o
 *  - Elementor Pro tiene una plantilla «Entrada individual» para Promociones (Theme Builder).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function zq_single_template_id() {
	$id = (int) zq_options()['single_tpl'];
	return ( $id && 'publish' === get_post_status( $id ) ) ? $id : 0;
}

add_filter( 'template_include', function ( $template ) {
	if ( ! is_singular( ZQ_CPT ) || ! zq_single_template_id() || ! class_exists( '\Elementor\Plugin' ) ) {
		return $template;
	}
	$post_id = get_queried_object_id();
	if ( 'builder' === get_post_meta( $post_id, '_elementor_edit_mode', true ) ) {
		return $template;
	}
	if ( function_exists( 'elementor_theme_do_location' ) && zq_pro_has_single_for_cpt() ) {
		return $template;
	}
	return ZQ_DIR . 'templates/single-promocion.php';
}, 20 );

/** ¿Hay una plantilla de Theme Builder (Pro) asignada a este tipo de contenido? */
function zq_pro_has_single_for_cpt() {
	if ( ! class_exists( '\ElementorPro\Modules\ThemeBuilder\Module' ) ) {
		return false;
	}
	$docs = \ElementorPro\Modules\ThemeBuilder\Module::instance()->get_conditions_manager()->get_documents_for_location( 'single' );
	return ! empty( $docs );
}

/** Carga el CSS de la plantilla en <head> para evitar saltos de diseño. */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_singular( ZQ_CPT ) || ! zq_single_template_id() || ! class_exists( '\Elementor\Core\Files\CSS\Post' ) ) {
		return;
	}
	\Elementor\Plugin::instance()->frontend->enqueue_styles();
	$css = \Elementor\Core\Files\CSS\Post::create( zq_single_template_id() );
	$css->enqueue();
	wp_enqueue_style( 'zorom-qobrix' );
	wp_enqueue_script( 'zorom-qobrix' );
}, 20 );
