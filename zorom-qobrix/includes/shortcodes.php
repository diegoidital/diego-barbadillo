<?php
/**
 * Shortcodes:
 *   [qobrix campo="precio"]                    Cualquier campo de la promoción actual.
 *   [qobrix_imagen tamano="zorom-promocion"]   Imagen destacada.
 *   [qobrix_enlace texto="Ver promoción"]      Enlace a la página de la promoción.
 *   [zorom_promociones plantilla="123"]        Pestañas (una por promoción).
 *   [qobrix_listado plantilla="123"]           Rejilla de promociones.
 *   [qobrix_hero]                              Portada de la página de la promoción.
 *   [qobrix_datos campos="fecha,precio"]       Fichas de datos con icono.
 *   [qobrix_propiedades]                       Tabla de inmuebles del proyecto.
 *   [qobrix_galeria]                           Fotos del proyecto.
 *   [qobrix_mapa]                              Mapa de la ubicación.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_enqueue_scripts', function () {
	$url = plugin_dir_url( ZQ_FILE );
	wp_register_style( 'zorom-qobrix', $url . 'assets/promociones.css', array(), ZQ_VERSION );
	wp_register_script( 'zorom-qobrix', $url . 'assets/promociones.js', array(), ZQ_VERSION, true );
} );

/* -------------------------------------------------------------------------
 * Campos sueltos
 * ---------------------------------------------------------------------- */

add_shortcode( 'qobrix', function ( $atts ) {
	$a = shortcode_atts( array(
		'campo'    => 'titulo',
		'formato'  => 'auto',
		'proyecto' => '',
		'antes'    => '',
		'despues'  => '',
		'vacio'    => '',
	), $atts, 'qobrix' );

	$post_id = zq_resolve_project( $a['proyecto'] );
	if ( ! $post_id ) {
		return '';
	}
	$out = zq_render_field( $post_id, $a['campo'], $a['formato'] );
	if ( '' === $out ) {
		return esc_html( $a['vacio'] );
	}
	return esc_html( $a['antes'] ) . $out . esc_html( $a['despues'] );
} );

add_shortcode( 'qobrix_imagen', function ( $atts ) {
	$a = shortcode_atts( array(
		'campo'    => 'destacada', // destacada | zona_imagen | zona2_imagen | galeria:N
		'proyecto' => '',
		'tamano'   => ZQ_IMAGE_SIZE,
		'clase'    => '',
	), $atts, 'qobrix_imagen' );

	$post_id = zq_resolve_project( $a['proyecto'] );
	$thumb   = $post_id ? zq_image_id( $post_id, $a['campo'] ) : 0;
	if ( ! $thumb ) {
		return '';
	}
	return wp_get_attachment_image( $thumb, $a['tamano'], false, array(
		'class' => trim( 'zq-imagen ' . $a['clase'] ),
		'alt'   => get_the_title( $post_id ),
	) );
} );

add_shortcode( 'qobrix_enlace', function ( $atts ) {
	$a = shortcode_atts( array(
		'proyecto' => '',
		'texto'    => 'Ver promoción',
		'flecha'   => 'si',
		'clase'    => 'zq-link',
	), $atts, 'qobrix_enlace' );

	$post_id = zq_resolve_project( $a['proyecto'] );
	if ( ! $post_id ) {
		return '';
	}
	return sprintf(
		'<a class="%s" href="%s">%s%s</a>',
		esc_attr( $a['clase'] ),
		esc_url( get_permalink( $post_id ) ),
		esc_html( $a['texto'] ),
		'no' === $a['flecha'] ? '' : ' <span aria-hidden="true">&rarr;</span>'
	);
} );

/* -------------------------------------------------------------------------
 * Consulta común para pestañas y listado
 * ---------------------------------------------------------------------- */

function zq_query_projects( $a ) {
	$o    = zq_options();
	$args = array(
		'post_type'      => ZQ_CPT,
		'post_status'    => 'publish',
		'posts_per_page' => (int) $a['limite'] ? (int) $a['limite'] : -1,
		'no_found_rows'  => true,
	);

	if ( 'si' === $a['destacados'] ) {
		$args['meta_query'] = array( array( 'key' => ZQ_META_PREFIX . 'featured', 'value' => array( '1', 'true' ), 'compare' => 'IN' ) );
	}

	if ( '' !== trim( $a['ids'] ) ) {
		$ids = array();
		foreach ( array_map( 'trim', explode( ',', $a['ids'] ) ) as $ref ) {
			$id = zq_resolve_project( $ref );
			if ( $id ) {
				$ids[] = $id;
			}
		}
		$args['post__in'] = $ids ? $ids : array( 0 );
		$args['orderby']  = 'post__in';
		return get_posts( $args );
	}

	$first = function ( $list ) {
		$parts = explode( ',', $list );
		return ZQ_META_PREFIX . sanitize_key( trim( $parts[0] ) );
	};
	switch ( $a['orden'] ) {
		case 'titulo':
			$args['orderby'] = 'title';
			$args['order']   = 'ASC';
			break;
		case 'precio':
			$args['meta_key'] = $first( $o['field_price'] );
			$args['orderby']  = 'meta_value_num';
			$args['order']    = 'ASC';
			break;
		case 'fecha':
			$args['meta_key'] = $first( $o['field_date'] );
			$args['orderby']  = 'meta_value';
			$args['order']    = 'ASC';
			break;
		default: // «Orden» de la promoción en WordPress y luego título.
			$args['orderby'] = array( 'menu_order' => 'ASC', 'title' => 'ASC' );
	}

	return get_posts( $args );
}

/** Pinta una plantilla de Elementor con la promoción como entrada actual. */
function zq_render_template( $template_id, WP_Post $project ) {
	if ( ! $template_id || ! class_exists( '\Elementor\Plugin' ) ) {
		return '';
	}
	global $post;
	$post = $project; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
	setup_postdata( $post );
	$html = \Elementor\Plugin::instance()->frontend->get_builder_content_for_display( (int) $template_id, true );
	wp_reset_postdata();
	return $html;
}

/* -------------------------------------------------------------------------
 * Pestañas: [zorom_promociones]
 * ---------------------------------------------------------------------- */

add_shortcode( 'zorom_promociones', function ( $atts ) {
	static $instance = 0;
	$instance++;

	$a = shortcode_atts( array(
		'limite'        => 4,
		'destacados'    => 'no',
		'ids'           => '',
		'orden'         => 'menu',
		'plantilla'     => '',
		'campo_pestana' => 'titulo',
		'fondo'         => 'si',
		'texto_enlace'  => 'Ver promoción',
		'etiq_precio'   => 'Precio desde',
		'etiq_unidades' => 'Unidades',
		'etiq_fecha'    => 'Fecha',
	), $atts, 'zorom_promociones' );

	$projects = zq_query_projects( $a );
	if ( ! $projects ) {
		return current_user_can( 'manage_options' )
			? '<p><em>Promociones Qobrix: no hay promociones publicadas. Revisa Ajustes → Qobrix.</em></p>'
			: '';
	}

	wp_enqueue_style( 'zorom-qobrix' );
	wp_enqueue_script( 'zorom-qobrix' );

	$uid = 'zq' . $instance;
	ob_start();
	?>
	<div class="zq-promos<?php echo 'no' === $a['fondo'] ? ' zq-promos--sin-fondo' : ''; ?>" data-zq-tabs>
		<div class="zq-tabs" role="tablist">
			<?php foreach ( $projects as $i => $p ) : ?>
				<button type="button" role="tab" class="zq-tab<?php echo 0 === $i ? ' is-active' : ''; ?>"
					id="<?php echo esc_attr( "$uid-tab-$i" ); ?>"
					aria-controls="<?php echo esc_attr( "$uid-panel-$i" ); ?>"
					aria-selected="<?php echo 0 === $i ? 'true' : 'false'; ?>"
					tabindex="<?php echo 0 === $i ? '0' : '-1'; ?>"><?php echo zq_render_field( $p->ID, $a['campo_pestana'], 'texto' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
			<?php endforeach; ?>
		</div>

		<?php foreach ( $projects as $i => $p ) : ?>
			<div class="zq-panel<?php echo $a['plantilla'] ? ' zq-panel--plantilla' : ''; ?>" role="tabpanel"
				id="<?php echo esc_attr( "$uid-panel-$i" ); ?>"
				aria-labelledby="<?php echo esc_attr( "$uid-tab-$i" ); ?>"
				<?php echo 0 === $i ? '' : 'hidden'; ?>>
				<?php
				if ( $a['plantilla'] ) {
					echo zq_render_template( (int) $a['plantilla'], $p ); // phpcs:ignore WordPress.Security.EscapeOutput
				} else {
					zq_default_panel( $p, $a, 0 === $i );
				}
				?>
			</div>
		<?php endforeach; ?>
	</div>
	<?php
	return ob_get_clean();
} );

/** Diseño por defecto de una pestaña (cuando no se indica plantilla de Elementor). */
function zq_default_panel( WP_Post $p, array $a, $first ) {
	$datos = array(
		$a['etiq_precio']   => zq_render_field( $p->ID, 'precio' ),
		$a['etiq_unidades'] => zq_render_field( $p->ID, 'unidades' ),
		$a['etiq_fecha']    => zq_render_field( $p->ID, 'fecha' ),
	);
	$desc  = zq_render_field( $p->ID, 'descripcion' );
	?>
	<div class="zq-panel__info">
		<h3 class="zq-title"><?php echo esc_html( get_the_title( $p ) ); ?></h3>
		<?php if ( $desc ) : ?>
			<div class="zq-desc"><?php echo $desc; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
		<?php endif; ?>
		<dl class="zq-datos">
			<?php foreach ( $datos as $label => $value ) : ?>
				<?php if ( '' !== $value ) : ?>
					<div class="zq-dato"><dt><?php echo esc_html( $label ); ?></dt><dd><?php echo $value; // phpcs:ignore WordPress.Security.EscapeOutput ?></dd></div>
				<?php endif; ?>
			<?php endforeach; ?>
		</dl>
		<a class="zq-link" href="<?php echo esc_url( get_permalink( $p ) ); ?>"><?php echo esc_html( $a['texto_enlace'] ); ?> <span aria-hidden="true">&rarr;</span></a>
	</div>
	<div class="zq-panel__img">
		<?php
		echo get_the_post_thumbnail( $p, ZQ_IMAGE_SIZE, array(
			'alt'     => get_the_title( $p ),
			'loading' => $first ? 'eager' : 'lazy',
		) );
		?>
	</div>
	<?php
}

/* -------------------------------------------------------------------------
 * Listado: [qobrix_listado]
 * ---------------------------------------------------------------------- */

add_shortcode( 'qobrix_listado', function ( $atts ) {
	$a = shortcode_atts( array(
		'limite'       => 0,
		'destacados'   => 'no',
		'ids'          => '',
		'orden'        => 'menu',
		'plantilla'    => '',
		'columnas'     => 3,
		'texto_enlace' => 'Ver promoción',
	), $atts, 'qobrix_listado' );

	$projects = zq_query_projects( $a );
	if ( ! $projects ) {
		return '';
	}
	wp_enqueue_style( 'zorom-qobrix' );

	$cols = max( 1, min( 6, (int) $a['columnas'] ) );
	$out  = '<div class="zq-listado" style="--zq-cols:' . $cols . '">';
	foreach ( $projects as $p ) {
		$out .= '<div class="zq-listado__item">';
		if ( $a['plantilla'] ) {
			$out .= zq_render_template( (int) $a['plantilla'], $p );
		} else {
			$precio = zq_render_field( $p->ID, 'precio' );
			$out   .= '<a class="zq-card" href="' . esc_url( get_permalink( $p ) ) . '">'
				. get_the_post_thumbnail( $p, ZQ_IMAGE_SIZE, array( 'alt' => get_the_title( $p ), 'loading' => 'lazy' ) )
				. '<span class="zq-card__title">' . esc_html( get_the_title( $p ) ) . '</span>'
				. ( $precio ? '<span class="zq-card__price">Desde ' . $precio . '</span>' : '' )
				. '<span class="zq-link">' . esc_html( $a['texto_enlace'] ) . ' <span aria-hidden="true">&rarr;</span></span>'
				. '</a>';
		}
		$out .= '</div>';
	}
	return $out . '</div>';
} );

/* -------------------------------------------------------------------------
 * Bloques de la página de promoción
 * ---------------------------------------------------------------------- */

/** Iconos sencillos de trazo (SVG en línea). */
function zq_icon( $name ) {
	$paths = array(
		'fecha'      => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
		'precio'     => '<circle cx="8" cy="15" r="5"/><path d="M11.5 11.5 21 2M17 6l3 3M15 8l2 2"/>',
		'eficiencia' => '<path d="M5 19c0-9 6-14 15-14 0 9-5 15-14 15"/><path d="M5 19 13 11"/>',
		'unidades'   => '<path d="M3 11 12 4l9 7v9H3z"/><path d="M10 20v-6h4v6"/>',
		'aeropuerto' => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V4h8v3M9 7v13M15 7v13"/>',
		'playa'      => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="4"/><path d="m5.6 5.6 3.6 3.6M14.8 14.8l3.6 3.6M18.4 5.6l-3.6 3.6M9.2 14.8l-3.6 3.6"/>',
		'centro'     => '<path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/>',
		'hospital'   => '<circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/>',
		'escuela'    => '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/>',
		'tiendas'    => '<path d="M5 7h14l-1 13H6z"/><path d="M9 10V6a3 3 0 0 1 6 0v4"/>',
	);
	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}
	return '<svg class="zq-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths[ $name ] . '</svg>';
}

/**
 * Lista «campo:Etiqueta:icono:formato, …» → filas. Etiqueta, icono y formato son opcionales.
 */
function zq_parse_fields_list( $list ) {
	$out = array();
	foreach ( array_filter( array_map( 'trim', explode( ',', $list ) ) ) as $item ) {
		$p     = array_map( 'trim', explode( ':', $item ) );
		$out[] = array(
			'campo'   => $p[0],
			'label'   => isset( $p[1] ) && '' !== $p[1] ? $p[1] : $p[0],
			'icono'   => isset( $p[2] ) && '' !== $p[2] ? $p[2] : $p[0],
			'formato' => isset( $p[3] ) && '' !== $p[3] ? $p[3] : 'auto',
		);
	}
	return $out;
}

add_shortcode( 'qobrix_hero', function ( $atts ) {
	$a = shortcode_atts( array(
		'proyecto'    => '',
		'boton'       => 'Solicitar información',
		'enlace'      => '#contacto',
		'altura'      => '640px',
	), $atts, 'qobrix_hero' );

	$post_id = zq_resolve_project( $a['proyecto'] );
	if ( ! $post_id ) {
		return '';
	}
	wp_enqueue_style( 'zorom-qobrix' );
	$img = (string) wp_get_attachment_image_url( zq_image_id( $post_id ), 'full' );
	$ubi = zq_render_field( $post_id, 'ubicacion' );
	$sub = zq_render_field( $post_id, 'subtitulo' );

	return '<section class="zq-hero" style="min-height:' . esc_attr( $a['altura'] ) . ';' . ( $img ? 'background-image:url(' . esc_url( $img ) . ')' : '' ) . '">'
		. '<div class="zq-hero__inner">'
		. '<h1 class="zq-hero__title">' . esc_html( get_the_title( $post_id ) ) . '</h1>'
		. ( $ubi ? '<p class="zq-hero__ubicacion">' . $ubi . '</p>' : '' )
		. ( $sub ? '<p class="zq-hero__subtitulo">' . $sub . '</p>' : '' )
		. ( $a['boton'] ? '<a class="zq-hero__boton" href="' . esc_url( $a['enlace'] ) . '">' . esc_html( $a['boton'] ) . '</a>' : '' )
		. '</div></section>';
} );

add_shortcode( 'qobrix_datos', function ( $atts ) {
	$a = shortcode_atts( array(
		'proyecto' => '',
		'campos'   => 'fecha:Fecha,precio:Precio,eficiencia:Eficiencia,unidades:Unidades',
		'estilo'   => 'tarjetas', // tarjetas | lista
		'columnas' => 0,
	), $atts, 'qobrix_datos' );

	$post_id = zq_resolve_project( $a['proyecto'] );
	if ( ! $post_id ) {
		return '';
	}
	wp_enqueue_style( 'zorom-qobrix' );

	$items = '';
	$n     = 0;
	foreach ( zq_parse_fields_list( $a['campos'] ) as $f ) {
		$value = zq_render_field( $post_id, $f['campo'], $f['formato'] );
		if ( '' === $value ) {
			continue;
		}
		$n++;
		$items .= '<div class="zq-dato-card">'
			. '<div class="zq-dato-card__label">' . zq_icon( $f['icono'] ) . '<span>' . esc_html( $f['label'] ) . '</span></div>'
			. '<div class="zq-dato-card__value">' . $value . '</div></div>';
	}
	if ( ! $n ) {
		return '';
	}
	$cols = (int) $a['columnas'] ? (int) $a['columnas'] : $n;
	return '<div class="zq-datos-grid zq-datos-grid--' . esc_attr( $a['estilo'] ) . '" style="--zq-cols:' . (int) $cols . '">' . $items . '</div>';
} );

add_shortcode( 'qobrix_propiedades', function ( $atts ) {
	$a = shortcode_atts( array(
		'proyecto'   => '',
		'columnas'   => 'ref:Referencia,floor:Piso,bedrooms:Dormitorios,bathrooms:Baños,plot_area_amount:Área parcela:m2,covered_area_amount:Área interna:m2,list_selling_price_amount:Precio:precio,status:Estado:estado',
		'ocultar'    => 'withdrawn',
		'vacio'      => '',
	), $atts, 'qobrix_propiedades' );

	$post_id = zq_resolve_project( $a['proyecto'] );
	$units   = $post_id ? zq_get_units( $post_id ) : array();
	$hide    = array_filter( array_map( 'trim', explode( ',', strtolower( $a['ocultar'] ) ) ) );
	$units   = array_filter( $units, function ( $u ) use ( $hide ) {
		return ! in_array( strtolower( (string) ( isset( $u['status'] ) ? $u['status'] : '' ) ), $hide, true );
	} );
	if ( ! $units ) {
		return $a['vacio'] ? '<p class="zq-propiedades-vacio">' . esc_html( $a['vacio'] ) . '</p>' : '';
	}
	wp_enqueue_style( 'zorom-qobrix' );

	// En esta tabla el tercer valor de cada columna es el formato.
	$cols = array();
	foreach ( array_filter( array_map( 'trim', explode( ',', $a['columnas'] ) ) ) as $c ) {
		$p      = array_map( 'trim', explode( ':', $c ) );
		$cols[] = array( $p[0], isset( $p[1] ) ? $p[1] : $p[0], isset( $p[2] ) ? $p[2] : 'texto' );
	}
	$estados = array(
		'available' => 'Disponible',
		'reserved'  => 'Reservado',
		'sold'      => 'Vendido',
		'withdrawn' => 'Retirado',
	);

	$html = '<div class="zq-propiedades-wrap"><table class="zq-propiedades"><thead><tr>';
	foreach ( $cols as $c ) {
		$html .= '<th scope="col">' . esc_html( $c[1] ) . '</th>';
	}
	$html .= '</tr></thead><tbody>';
	foreach ( $units as $u ) {
		$status = strtolower( (string) ( isset( $u['status'] ) ? $u['status'] : '' ) );
		$html  .= '<tr class="zq-estado-' . esc_attr( sanitize_html_class( $status ) ) . '">';
		foreach ( $cols as $c ) {
			$v = isset( $u[ $c[0] ] ) ? $u[ $c[0] ] : '';
			switch ( $c[2] ) {
				case 'precio':
					$v = is_numeric( $v ) ? number_format( (float) $v, 0, ',', '.' ) . ' €' : $v;
					break;
				case 'm2':
					$v = is_numeric( $v ) ? number_format( (float) $v, 0, ',', '.' ) . ' m²' : $v;
					break;
				case 'estado':
					$v = isset( $estados[ $status ] ) ? $estados[ $status ] : ucfirst( (string) $v );
					break;
			}
			$html .= '<td data-label="' . esc_attr( $c[1] ) . '"><span>' . esc_html( (string) $v ) . '</span></td>';
		}
		$html .= '</tr>';
	}
	return $html . '</tbody></table></div>';
} );

add_shortcode( 'qobrix_galeria', function ( $atts ) {
	$a = shortcode_atts( array(
		'proyecto' => '',
		'desde'    => 1,
		'limite'   => 0,
		'columnas' => 2,
		'tamano'   => 'large',
	), $atts, 'qobrix_galeria' );

	$post_id = zq_resolve_project( $a['proyecto'] );
	$ids     = $post_id ? array_values( (array) get_post_meta( $post_id, '_zq_galeria', true ) ) : array();
	$ids     = array_slice( $ids, max( 0, (int) $a['desde'] - 1 ), (int) $a['limite'] ? (int) $a['limite'] : null );
	if ( ! $ids ) {
		return '';
	}
	wp_enqueue_style( 'zorom-qobrix' );

	$html = '<div class="zq-galeria" style="--zq-cols:' . max( 1, (int) $a['columnas'] ) . '">';
	foreach ( $ids as $id ) {
		$full  = wp_get_attachment_image_url( $id, 'full' );
		$html .= '<a class="zq-galeria__item" href="' . esc_url( $full ) . '" data-elementor-open-lightbox="yes" data-elementor-lightbox-slideshow="zq-' . (int) $post_id . '">'
			. wp_get_attachment_image( $id, $a['tamano'], false, array( 'loading' => 'lazy' ) ) . '</a>';
	}
	return $html . '</div>';
} );

add_shortcode( 'qobrix_mapa', function ( $atts ) {
	$a = shortcode_atts( array(
		'proyecto' => '',
		'zoom'     => 14,
		'altura'   => '480px',
	), $atts, 'qobrix_mapa' );

	$post_id = zq_resolve_project( $a['proyecto'] );
	$c       = $post_id ? zq_coordinates( $post_id ) : array();
	$q       = $c ? $c[0] . ',' . $c[1] : trim( wp_strip_all_tags( zq_render_field( $post_id, 'ubicacion' ) ) );
	if ( ! $post_id || '' === $q ) {
		return '';
	}
	wp_enqueue_style( 'zorom-qobrix' );
	$src = 'https://maps.google.com/maps?q=' . rawurlencode( $q ) . '&z=' . (int) $a['zoom'] . '&output=embed';
	return '<div class="zq-mapa" style="height:' . esc_attr( $a['altura'] ) . '"><iframe src="' . esc_url( $src ) . '" title="' . esc_attr( 'Mapa de ' . get_the_title( $post_id ) ) . '" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe></div>';
} );
