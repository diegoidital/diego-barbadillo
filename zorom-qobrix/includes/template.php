<?php
/**
 * Plantilla de Elementor de partida para la página de promoción (diseño de Figma).
 * Se crea desde Qobrix → Ajustes → «Crear plantilla de ejemplo».
 * Usa widgets normales de Elementor con shortcodes [qobrix …], así funciona sin Elementor Pro.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function zq_tpl_id() {
	return substr( md5( wp_rand() . microtime() ), 0, 7 );
}

function zq_tpl_widget( $type, array $settings, $css_classes = '' ) {
	if ( $css_classes ) {
		$settings['_css_classes'] = $css_classes;
	}
	return array( 'id' => zq_tpl_id(), 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings, 'elements' => array() );
}

function zq_tpl_container( array $elements, array $settings = array() ) {
	return array(
		'id'       => zq_tpl_id(),
		'elType'   => 'container',
		'isInner'  => false,
		'settings' => $settings,
		'elements' => $elements,
	);
}

function zq_tpl_heading( $title, $size = 'h2', $align = '', $px = 32 ) {
	$s = array(
		'title'                 => $title,
		'header_size'           => $size,
		'typography_typography' => 'custom',
		'typography_font_size'  => array( 'unit' => 'px', 'size' => $px, 'sizes' => array() ),
		'typography_font_weight' => '400',
		'title_color'           => '#111111',
	);
	if ( $align ) {
		$s['align'] = $align;
	}
	return zq_tpl_widget( 'heading', $s );
}

function zq_tpl_sc( $shortcode, $classes = '' ) {
	return zq_tpl_widget( 'shortcode', array( 'shortcode' => $shortcode ), $classes );
}

function zq_tpl_section( array $elements, array $extra = array() ) {
	return zq_tpl_container( $elements, array_merge( array(
		'content_width' => 'boxed',
		'boxed_width'   => array( 'unit' => 'px', 'size' => 1140, 'sizes' => array() ),
		'padding'       => array( 'unit' => 'px', 'top' => '60', 'right' => '20', 'bottom' => '60', 'left' => '20', 'isLinked' => false ),
	), $extra ) );
}

function zq_tpl_row( array $left, array $right, $left_width = 55, $reverse_mobile = false ) {
	$col = function ( array $els, $w ) {
		$c            = zq_tpl_container( $els, array(
			'content_width' => 'full',
			'width'         => array( 'unit' => '%', 'size' => $w, 'sizes' => array() ),
			'width_mobile'  => array( 'unit' => '%', 'size' => 100, 'sizes' => array() ),
			'padding'       => array( 'unit' => 'px', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => true ),
		) );
		$c['isInner'] = true;
		return $c;
	};
	$settings = array(
		'flex_direction'        => 'row',
		'flex_direction_mobile' => $reverse_mobile ? 'column-reverse' : 'column',
		'flex_align_items'      => 'center',
		'flex_gap'              => array( 'unit' => 'px', 'column' => '60', 'row' => '40', 'isLinked' => false, 'size' => 60 ),
	);
	return zq_tpl_section( array( $col( $left, $left_width ), $col( $right, 100 - $left_width ) ), $settings );
}

/**
 * Contenido (_elementor_data) de la plantilla: una sección del Figma por contenedor,
 * a ancho completo y sin márgenes. Cada una se puede mover o quitar en Elementor;
 * el estilo exacto lo pone assets/ficha.css.
 */
function zq_starter_template_data() {
	$out = array();
	foreach ( zq_ficha_secciones() as $s ) {
		$out[] = zq_tpl_container( array( zq_tpl_sc( '[qobrix_ficha seccion="' . $s . '"]' ) ), array(
			'content_width' => 'full',
			'padding'       => array( 'unit' => 'px', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => true ),
			'flex_gap'      => array( 'unit' => 'px', 'column' => '0', 'row' => '0', 'isLinked' => true, 'size' => 0 ),
			'_title'        => 'Qobrix · ' . $s,
		) );
	}
	return $out;
}

/** Crea la plantilla en Plantillas → Plantillas guardadas y la deja seleccionada. */
function zq_create_starter_template() {
	$id = wp_insert_post( array(
		'post_type'   => 'elementor_library',
		'post_status' => 'publish',
		'post_title'  => 'Página de promoción (Qobrix)',
	), true );
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	wp_set_object_terms( $id, 'page', 'elementor_library_type' );
	update_post_meta( $id, '_elementor_edit_mode', 'builder' );
	update_post_meta( $id, '_elementor_template_type', 'page' );
	update_post_meta( $id, '_elementor_version', defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '3.0.0' );
	update_post_meta( $id, '_wp_page_template', 'elementor_header_footer' );
	update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( zq_starter_template_data() ) ) );

	$o               = (array) get_option( ZQ_OPTION, array() );
	$o['single_tpl'] = $id;
	update_option( ZQ_OPTION, $o );

	return $id;
}

add_action( 'admin_post_zq_create_template', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Sin permiso.' );
	}
	check_admin_referer( 'zq_create_template' );
	if ( ! class_exists( '\Elementor\Plugin' ) ) {
		wp_die( 'Elementor no está activo.' );
	}
	$id = zq_create_starter_template();
	if ( is_wp_error( $id ) ) {
		wp_die( esc_html( $id->get_error_message() ) );
	}
	wp_safe_redirect( admin_url( 'post.php?post=' . (int) $id . '&action=elementor' ) );
	exit;
} );
