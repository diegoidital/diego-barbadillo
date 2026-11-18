<?php
/**
 * Plantilla EDITABLE de la página de promoción: la misma maqueta del Figma pero con widgets
 * normales de Elementor (Título, Editor de texto, Imagen, Caja de icono, Botón, Google Maps…),
 * cada uno con su dato ya puesto. Se cambian tamaños, márgenes y estilos como en cualquier página.
 *
 * Se crea desde Qobrix → Ajustes («Crear plantilla editable») como plantilla de Theme Builder
 * (Entrada individual, condición: Promociones) y se puede exportar como JSON.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Etiqueta dinámica de Qobrix serializada como la guarda Elementor. */
function zq_ed_tag( $name, array $settings = array() ) {
	return '[elementor-tag id="' . zq_tpl_id() . '" name="' . $name . '" settings="' . rawurlencode( wp_json_encode( $settings ) ) . '"]';
}

function zq_ed_px( $n ) {
	return array( 'unit' => 'px', 'size' => $n, 'sizes' => array() );
}

function zq_ed_box( $t, $r, $b, $l, $unit = 'px' ) {
	return array( 'unit' => $unit, 'top' => (string) $t, 'right' => (string) $r, 'bottom' => (string) $b, 'left' => (string) $l, 'isLinked' => false );
}

/** Tipografía fija del diseño. */
function zq_ed_type( $family, $size, $weight = '400', $lh = 1.4, $prefix = 'typography' ) {
	return array(
		$prefix . '_typography'  => 'custom',
		$prefix . '_font_family' => $family,
		$prefix . '_font_size'   => zq_ed_px( $size ),
		$prefix . '_font_weight' => $weight,
		$prefix . '_line_height' => array( 'unit' => 'em', 'size' => $lh, 'sizes' => array() ),
	);
}

function zq_ed_heading( $title, $size = 30, $tag = 'h2', $align = '', $family = 'Geist', $weight = '400', $color = '#101215' ) {
	return zq_tpl_widget( 'heading', array_merge( array(
		'title'       => $title,
		'header_size' => $tag,
		'align'       => $align,
		'title_color' => $color,
	), zq_ed_type( $family, $size, $weight ) ) );
}

function zq_ed_text( $html, $size = 16, $color = '#000000', $extra = array() ) {
	return zq_tpl_widget( 'text-editor', array_merge( array(
		'editor'     => $html,
		'text_color' => $color,
	), zq_ed_type( 'Geist', $size ), $extra ) );
}

function zq_ed_image( $campo, $clase ) {
	return zq_tpl_widget( 'image', array(
		'image'       => array( 'url' => '', 'id' => '' ),
		'__dynamic__' => array( 'image' => zq_ed_tag( 'qobrix-imagen', array( 'campo' => $campo ) ) ),
		'image_size'  => 'large',
		'width'       => array( 'unit' => '%', 'size' => 100, 'sizes' => array() ),
		'height'      => array( 'unit' => '%', 'size' => 100, 'sizes' => array() ),
		'object-fit'  => 'cover',
		'image_border_radius' => zq_ed_box( 20, 20, 20, 20 ),
	), $clase );
}

/** Caja de icono con etiqueta arriba y dato debajo (fichas y distancias). */
function zq_ed_iconbox( $icon, $label, $shortcode, $borde = false, $ancho = null ) {
	$s = array_merge(
		array(
			'selected_icon'     => array( 'value' => $icon, 'library' => 'fa-solid' ),
			'title_text'        => $label,
			'description_text'  => $shortcode,
			'position'          => 'top',
			'title_size'        => 'div',
			'text_align'        => 'center',
			'icon_size'         => zq_ed_px( 18 ),
			'icon_space'        => zq_ed_px( 6 ),
			'primary_color'     => '#101215',
			'title_color'       => '#101215',
			'description_color' => '#000000',
			'title_bottom_space' => zq_ed_px( 6 ),
		),
		zq_ed_type( 'Geist', 18, '400', 1.4, 'title_typography' ),
		zq_ed_type( 'Inter', 18, '600', 1.4, 'description_typography' )
	);
	if ( $ancho ) {
		$s += array(
			'_element_width'               => 'initial',
			'_element_custom_width'        => $ancho,
			'_element_width_mobile'        => 'initial',
			'_element_custom_width_mobile' => array( 'unit' => '%', 'size' => $borde ? 100 : 50, 'sizes' => array() ),
		);
	}
	if ( $borde ) {
		$s += array(
			'_border_border' => 'solid',
			'_border_width'  => zq_ed_box( 0.5, 0.5, 0.5, 0.5 ),
			'_border_color'  => '#000000',
			'_border_radius' => zq_ed_box( 10, 10, 10, 10 ),
			'_padding'       => zq_ed_box( 14, 10, 14, 10 ),
		);
	}
	return zq_tpl_widget( 'icon-box', $s );
}

function zq_ed_con( array $elements, array $s = array() ) {
	return zq_tpl_container( $elements, array_merge( array(
		'content_width' => 'boxed',
		'boxed_width'   => zq_ed_px( 1360 ),
		'padding'       => zq_ed_box( 0, 20, 0, 20 ),
	), $s ) );
}

function zq_ed_col( array $elements, $width, array $s = array() ) {
	$c            = zq_tpl_container( $elements, array_merge( array(
		'content_width'  => 'full',
		'width'          => array( 'unit' => '%', 'size' => $width, 'sizes' => array() ),
		'width_mobile'   => array( 'unit' => '%', 'size' => 100, 'sizes' => array() ),
		'padding'        => zq_ed_box( 0, 0, 0, 0 ),
		'flex_direction' => 'column',
		'flex_gap'       => array( 'unit' => 'px', 'column' => '12', 'row' => '12', 'isLinked' => true, 'size' => 12 ),
	), $s ) );
	$c['isInner'] = true;
	return $c;
}

/** Fila texto + imagen, con la imagen estirada a la altura del texto. */
function zq_ed_row( array $a, array $b, $wa, $wb, $padding_top ) {
	return zq_ed_con( array( $a === null ? array() : $a, $b ), array(
		'flex_direction'        => 'row',
		'flex_direction_mobile' => 'column',
		'flex_align_items'      => 'stretch',
		'flex_justify_content'  => 'space-between',
		'flex_gap'              => array( 'unit' => 'px', 'column' => '60', 'row' => '40', 'isLinked' => false, 'size' => 60 ),
		'padding'               => zq_ed_box( $padding_top, 20, 15, 20 ),
	) );
}

/** _elementor_data de la plantilla editable. */
function zq_editable_template_data() {
	$o    = array();
	$form = function_exists( 'zq_form_template_id' ) ? zq_form_template_id() : 0;

	// 1. Portada.
	$o[] = zq_tpl_container( array(
		zq_ed_con( array(
			zq_ed_heading( '[qobrix campo="titulo"]', 40, 'h1', '', 'Geist', '600', '#FFFFFF' ),
			zq_ed_text( '<p>[qobrix campo="ubicacion"]</p>', 20, '#FFFFFF' ),
			zq_ed_text( '<p>[qobrix campo="subtitulo"]</p>', 16, '#FFFFFF', array( '_margin' => zq_ed_box( 60, 0, 0, 0 ), 'typography_text_transform' => 'uppercase', 'typography_font_weight' => '500' ) ),
			zq_tpl_widget( 'button', array_merge( array(
				'text'              => 'Solicitar información',
				'link'              => array( 'url' => '#contacto' ),
				'background_color'  => '#FFFFFF',
				'button_text_color' => '#000000',
				'border_border'     => 'solid',
				'border_width'      => zq_ed_box( 1, 1, 1, 1 ),
				'border_color'      => '#000000',
				'border_radius'     => zq_ed_box( 10, 10, 10, 10 ),
				'text_padding'      => zq_ed_box( 11, 14, 11, 14 ),
				'typography_text_transform' => 'uppercase',
			), zq_ed_type( 'Geist', 14, '500' ) ) ),
		), array( 'flex_justify_content' => 'center', 'flex_gap' => array( 'unit' => 'px', 'column' => '8', 'row' => '8', 'isLinked' => true, 'size' => 8 ), 'padding' => zq_ed_box( 0, 20, 0, 111 ) ) ),
	), array(
		'content_width'               => 'full',
		'min_height'                  => zq_ed_px( 781 ),
		'flex_justify_content'        => 'center',
		'background_background'       => 'classic',
		'background_image'            => array( 'url' => '', 'id' => '' ),
		'__dynamic__'                 => array( 'background_image' => zq_ed_tag( 'qobrix-imagen', array( 'campo' => 'destacada' ) ) ),
		'background_position'         => 'center center',
		'background_size'             => 'cover',
		'background_overlay_background' => 'classic',
		'background_overlay_color'    => 'rgba(0,0,0,0.3)',
		'margin'                      => zq_ed_box( -80, 0, 0, 0 ),
		'_title'                      => 'Portada',
	) );

	// 2. Zona (primer bloque).
	$z = zq_ed_row(
		zq_ed_col( array(
			zq_ed_heading( '[qobrix campo="zona_titulo"]' ),
			zq_ed_text( '<p>[qobrix campo="zona_subtitulo"]</p>', 16 ),
			zq_ed_text( '[qobrix campo="zona_texto"]', 16 ),
		), 52 ),
		zq_ed_col( array( zq_ed_image( 'zona_imagen', 'zq-bloque-dcha' ) ), 42 ),
		52, 42, 210
	);
	$z['settings']['_title'] = 'Zona (primer bloque)';
	$o[] = $z;

	// 3. Características.
	$o[] = zq_ed_con( array(
		zq_ed_heading( '[qobrix campo="titulo"]', 30, 'h2', 'center', 'Inter', '300', '#000000' ),
		zq_ed_con( array(
			zq_ed_iconbox( 'far fa-calendar', 'FECHA', '[qobrix campo="fecha" vacio="—"]', true, zq_ed_px( 300 ) ),
			zq_ed_iconbox( 'fas fa-key', 'PRECIO', '[qobrix campo="precio" vacio="—"]', true, zq_ed_px( 300 ) ),
			zq_ed_iconbox( 'fas fa-leaf', 'EFICIENCIA', '[qobrix campo="eficiencia" antes="CATEGORÍA " vacio="—"]', true, zq_ed_px( 300 ) ),
			zq_ed_iconbox( 'fas fa-home', 'UNIDADES', '[qobrix campo="unidades" vacio="—"]', true, zq_ed_px( 300 ) ),
		), array( 'content_width' => 'full', 'flex_direction' => 'row', 'flex_direction_mobile' => 'column', 'flex_justify_content' => 'center', 'flex_gap' => array( 'unit' => 'px', 'column' => '16', 'row' => '16', 'isLinked' => true, 'size' => 16 ), 'padding' => zq_ed_box( 0, 0, 0, 0 ) ) ),
	), array( 'padding' => zq_ed_box( 92, 20, 0, 20 ), 'flex_gap' => array( 'unit' => 'px', 'column' => '20', 'row' => '20', 'isLinked' => true, 'size' => 20 ), '_title' => 'Características' ) );

	// 4. Lista de propiedades.
	$o[] = zq_ed_con( array(
		zq_ed_heading( 'Lista de propiedades', 30, 'h2', 'center', 'Inter', '300', '#000000' ),
		zq_tpl_sc( '[qobrix_propiedades]', 'zq-f' ),
	), array( 'padding' => zq_ed_box( 62, 20, 0, 20 ), '_title' => 'Lista de propiedades' ) );

	// 5. Urbanización.
	$u = zq_ed_row(
		zq_ed_col( array( zq_ed_image( 'galeria:1', 'zq-bloque-izda' ) ), 40 ),
		zq_ed_col( array(
			zq_ed_heading( '[qobrix campo="descripcion_titulo"]' ),
			zq_ed_text( '[qobrix campo="descripcion"]', 16 ),
			zq_ed_heading( 'Comodidades', 18, 'h3', '', 'Geist', '500' ),
			zq_ed_text( '[qobrix campo="comodidades"]', 16 ),
		), 54 ),
		40, 54, 127
	);
	$u['settings']['_title'] = 'Urbanización';
	$o[] = $u;

	// 6. Galería.
	$o[] = zq_ed_con( array( zq_tpl_sc( '[qobrix_galeria desde="2" limite="2" columnas="2"]' ) ), array( 'padding' => zq_ed_box( 87, 20, 0, 20 ), '_title' => 'Galería' ) );

	// 7. Localización.
	$dist = function ( $i, $l, $c ) {
		return zq_ed_iconbox( $i, $l, '[qobrix campo="' . $c . '" vacio="—"]', false, array( 'unit' => '%', 'size' => 33.33, 'sizes' => array() ) );
	};
	$fila = function ( array $els ) {
		return zq_ed_con( $els, array( 'content_width' => 'full', 'flex_direction' => 'row', 'flex_direction_mobile' => 'row', 'flex_wrap_mobile' => 'wrap', 'padding' => zq_ed_box( 0, 0, 0, 0 ), 'flex_gap' => array( 'unit' => 'px', 'column' => '0', 'row' => '12', 'isLinked' => false, 'size' => 0 ) ) );
	};
	$l = zq_ed_row(
		zq_ed_col( array(
			zq_ed_heading( '[qobrix campo="localizacion_titulo"]' ),
			zq_ed_text( '[qobrix campo="localizacion_texto"]', 16, '#000000', array( '_margin' => zq_ed_box( 30, 0, 30, 0 ) ) ),
			$fila( array(
				$dist( 'fas fa-suitcase', 'Aeropuerto', 'distancia_aeropuerto' ),
				$dist( 'fas fa-life-ring', 'Playa', 'distancia_playa' ),
				$dist( 'fas fa-map-marker-alt', 'Centro', 'distancia_centro' ),
			) ),
			$fila( array(
				$dist( 'fas fa-plus-circle', 'Hospital', 'distancia_hospital' ),
				$dist( 'fas fa-desktop', 'Escuela', 'distancia_escuela' ),
				$dist( 'fas fa-shopping-bag', 'Tiendas', 'distancia_tiendas' ),
			) ),
		), 53 ),
		zq_ed_col( array( zq_tpl_widget( 'google_maps', array(
			'address'     => 'Calpe, Alicante',
			'__dynamic__' => array( 'address' => zq_ed_tag( 'qobrix-campo', array( 'campo' => 'coordenadas', 'formato' => 'texto' ) ) ),
			'zoom'        => zq_ed_px( 14 ),
			'height'      => zq_ed_px( 627 ),
			'_border_radius' => zq_ed_box( 20, 20, 20, 20 ),
			'_css_classes'   => 'zq-mapa-redondo',
		) ) ), 42 ),
		53, 42, 170
	);
	$l['settings']['_title'] = 'Localización';
	$o[] = $l;

	// 8. Zona (segundo bloque).
	$z2 = zq_ed_row(
		zq_ed_col( array( zq_ed_image( 'zona2_imagen', 'zq-bloque-izda' ) ), 40 ),
		zq_ed_col( array(
			zq_ed_heading( '[qobrix campo="zona2_titulo"]' ),
			zq_ed_text( '[qobrix campo="zona2_texto"]', 16 ),
		), 54 ),
		40, 54, 85
	);
	$z2['settings']['_title'] = 'Zona (segundo bloque)';
	$o[] = $z2;

	// 9. Contacta.
	$o[] = zq_ed_con( array(
		zq_ed_heading( 'Contacta', 30, 'h2', 'center', 'Inter', '300', '#000000' ),
		$form
			? array( 'id' => zq_tpl_id(), 'elType' => 'widget', 'widgetType' => 'global', 'templateID' => $form, 'settings' => array(), 'elements' => array() )
			: zq_ed_text( '<p style="text-align:center">Arrastra aquí el global «Form_contacto» (Elementos → Globales).</p>', 16 ),
	), array( 'padding' => zq_ed_box( 151, 20, 120, 20 ), 'boxed_width' => zq_ed_px( 735 ), '_element_id' => 'contacto', '_title' => 'Contacta' ) );

	return $o;
}

/** Crea la plantilla editable como Entrada individual de Theme Builder para Promociones. */
function zq_create_editable_template() {
	$pro = class_exists( '\ElementorPro\Plugin' );
	$id  = wp_insert_post( array(
		'post_type'   => 'elementor_library',
		'post_status' => 'publish',
		'post_title'  => 'Promoción (editable)',
	), true );
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	$type = $pro ? 'single-post' : 'page';
	wp_set_object_terms( $id, $type, 'elementor_library_type' );
	update_post_meta( $id, '_elementor_edit_mode', 'builder' );
	update_post_meta( $id, '_elementor_template_type', $type );
	update_post_meta( $id, '_elementor_version', defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '3.0.0' );
	update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( zq_editable_template_data() ) ) );
	update_post_meta( $id, '_elementor_page_settings', array(
		'preview_type' => 'single/' . ZQ_CPT,
		'preview_id'   => (int) current( get_posts( array( 'post_type' => ZQ_CPT, 'posts_per_page' => 1, 'fields' => 'ids' ) ) ),
	) );

	if ( $pro ) {
		// Condición: todas las promociones. Sin Pro se usa como plantilla del plugin.
		update_post_meta( $id, '_elementor_conditions', array( 'include/singular/' . ZQ_CPT ) );
		if ( class_exists( '\ElementorPro\Modules\ThemeBuilder\Module' ) ) {
			\ElementorPro\Modules\ThemeBuilder\Module::instance()->get_conditions_manager()->get_cache()->regenerate();
		}
	}
	$o               = (array) get_option( ZQ_OPTION, array() );
	$o['single_tpl'] = $pro ? 0 : $id;
	update_option( ZQ_OPTION, $o );
	return $id;
}

add_action( 'admin_post_zq_create_editable', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Sin permiso.' );
	}
	check_admin_referer( 'zq_create_editable' );
	if ( ! class_exists( '\Elementor\Plugin' ) ) {
		wp_die( 'Elementor no está activo.' );
	}
	$id = zq_create_editable_template();
	if ( is_wp_error( $id ) ) {
		wp_die( esc_html( $id->get_error_message() ) );
	}
	wp_safe_redirect( admin_url( 'post.php?post=' . (int) $id . '&action=elementor' ) );
	exit;
} );

/** Descarga la plantilla editable como JSON importable (Plantillas → Importar). */
add_action( 'admin_post_zq_export_editable', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Sin permiso.' );
	}
	check_admin_referer( 'zq_export_editable' );
	nocache_headers();
	header( 'Content-Type: application/json; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="promocion-qobrix-editable.json"' );
	echo wp_json_encode( array(
		'content'       => zq_editable_template_data(),
		'page_settings' => array(),
		'version'       => '0.4',
		'title'         => 'Promoción (editable)',
		'type'          => class_exists( '\ElementorPro\Plugin' ) ? 'single-post' : 'page',
	) );
	exit;
} );
