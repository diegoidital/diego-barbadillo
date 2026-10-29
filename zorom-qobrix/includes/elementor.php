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
add_filter( 'elementor/widget/render_content', function ( $content ) {
	return ( is_string( $content ) && false !== strpos( $content, '[qobrix' ) ) ? do_shortcode( $content ) : $content;
}, 20 );
