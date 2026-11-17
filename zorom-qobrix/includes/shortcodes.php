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
	if ( ! $post_id ) {
		return '';
	}
	return zq_image_html( $post_id, $a['campo'], $a['tamano'], array( 'class' => trim( 'zq-imagen ' . $a['clase'] ) ) );
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
		case 'prioridad': // Prioridad de destacado en Qobrix (1 = primera).
			$args['meta_key'] = ZQ_META_PREFIX . 'featured_priority';
			$args['orderby']  = array( 'meta_value_num' => 'ASC', 'title' => 'ASC' );
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
	$GLOBALS['zq_sin_cache_elementor'] = true;
	$html = \Elementor\Plugin::instance()->frontend->get_builder_content_for_display( (int) $template_id, true );
	$GLOBALS['zq_sin_cache_elementor'] = false;
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
			? '<p><em>Promociones Qobrix: no hay promociones publicadas. Revisa Qobrix → Ajustes.</em></p>'
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
		<a class="zq-link" href="<?php echo esc_url( get_permalink( $p ) ); ?>"><?php echo esc_html( $a['texto_enlace'] ); ?><span class="zq-flecha" aria-hidden="true">&#10142;</span></a>
	</div>
	<div class="zq-panel__img">
		<?php
		echo zq_image_html( $p->ID, 'destacada', ZQ_IMAGE_SIZE, array( 'loading' => $first ? 'eager' : 'lazy' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
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
		'palabras'     => 24, // Palabras del texto de cada promoción (0 = sin texto).
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
			$ubi    = trim( wp_strip_all_tags( zq_render_field( $p->ID, 'ubicacion' ) ) );
			$texto  = '';
			if ( (int) $a['palabras'] ) {
				// Extracto manual de la promoción; si no hay, su descripción de Qobrix.
				$texto = has_excerpt( $p ) ? get_the_excerpt( $p ) : zq_render_field( $p->ID, 'descripcion' );
				$texto = wp_trim_words( wp_strip_all_tags( $texto ), (int) $a['palabras'], '…' );
			}
			$out   .= '<a class="zq-card" href="' . esc_url( get_permalink( $p ) ) . '">'
				. zq_image_html( $p->ID, 'destacada', ZQ_IMAGE_SIZE )
				. '<span class="zq-card__title">' . esc_html( get_the_title( $p ) ) . '</span>'
				. ( $ubi ? '<span class="zq-card__ubi">' . esc_html( $ubi ) . '</span>' : '' )
				. ( $precio ? '<span class="zq-card__price">Desde ' . $precio . '</span>' : '' )
				. ( $texto ? '<span class="zq-card__texto">' . esc_html( $texto ) . '</span>' : '' )
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
	$img = zq_image_url( $post_id );
	$ubi = zq_render_field( $post_id, 'ubicacion' );
	$sub = zq_render_field( $post_id, 'subtitulo' );

	return '<section class="zq-hero zq-bajo-menu" style="min-height:' . esc_attr( $a['altura'] ) . ';' . ( $img ? 'background-image:url(' . esc_url( $img ) . ')' : '' ) . '">'
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
		'proyecto' => '',
		// campo:Etiqueta:formato. Varios nombres posibles separados por «|» (se usa el primero con dato).
		'columnas' => 'ref:Referencia,bedrooms:Dorm. · Baños:dormbanos,total_area_amount|built_area_amount|gross_area_amount|covered_area_amount:Sup. total:m2,internal_area_amount|living_area_amount|net_area_amount:Sup. interior:m2,uncovered_veranda_amount|uncovered_verandas_amount|uncovered_terrace_amount|open_terrace_amount|uncovered_area_amount:Terraza descubierta:m2,covered_veranda_amount|covered_verandas_amount|covered_terrace_amount|veranda_area_amount|terrace_area_amount:Terraza cubierta:m2,list_selling_price_amount|selling_price_amount|price_amount:Precio:precio,_plano:Plano:plano',
		'ocultar'  => 'withdrawn',
		'vacio'    => '',
		'reservadas' => 'Ver propiedades reservadas',
		'sin_disponibles' => 'En este momento no hay propiedades a la venta en esta promoción.',
		'todas_vendidas'  => 'Todas las propiedades vendidas',
		'ver'             => 'Ver propiedad', // Texto de la última columna (vacío = sin columna).
		'vendidas' => 'Ver propiedades vendidas',
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

	$cols = array();
	foreach ( array_filter( array_map( 'trim', explode( ',', $a['columnas'] ) ) ) as $c ) {
		$p      = array_map( 'trim', explode( ':', $c ) );
		$cols[] = array( explode( '|', $p[0] ), isset( $p[1] ) ? $p[1] : $p[0], isset( $p[2] ) ? $p[2] : 'texto' );
	}

	// Disponibles arriba; reservadas y vendidas, cada una en su desplegable.
	$disponibles = array();
	$reservadas  = array();
	$vendidas    = array();
	foreach ( $units as $u ) {
		$st = strtolower( (string) ( isset( $u['status'] ) ? $u['status'] : '' ) );
		if ( preg_match( '/sold|vendid/', $st ) ) {
			$vendidas[] = $u;
		} elseif ( preg_match( '/reserv|offer|hold|option|pending/', $st ) ) {
			$reservadas[] = $u;
		} else {
			$disponibles[] = $u;
		}
	}
	foreach ( array( &$disponibles, &$reservadas, &$vendidas ) as &$grupo ) {
		usort( $grupo, function ( $x, $y ) {
			return strnatcmp( (string) ( $x['ref'] ?? '' ), (string) ( $y['ref'] ?? '' ) );
		} );
	}
	unset( $grupo );

	$thead = '<thead><tr>';
	foreach ( $cols as $c ) {
		$thead .= '<th scope="col">' . esc_html( $c[1] ) . '</th>';
	}
	if ( $a['ver'] ) {
		$thead .= '<th scope="col" class="zq-col-ver"><span class="screen-reader-text">' . esc_html( $a['ver'] ) . '</span></th>';
	}
	$thead .= '</tr></thead>';

	$ver   = $a['ver'];
	$filas = function ( array $list ) use ( $cols, $ver ) {
		$html = '';
		foreach ( $list as $u ) {
			$status = strtolower( (string) ( isset( $u['status'] ) ? $u['status'] : '' ) );
			$url    = ! empty( $u['_post'] ) ? get_permalink( (int) $u['_post'] ) : '';
			$html  .= '<tr class="zq-estado-' . esc_attr( sanitize_html_class( $status ) ) . ( $url ? ' zq-fila-enlace' : '' ) . '"' . ( $url ? ' data-href="' . esc_url( $url ) . '" tabindex="0"' : '' ) . '>';
			foreach ( $cols as $i => $c ) {
				$v = '';
				foreach ( $c[0] as $f ) {
					if ( isset( $u[ $f ] ) && '' !== (string) $u[ $f ] && '0' !== (string) $u[ $f ] ) {
						$v = $u[ $f ];
						break;
					}
				}
				$clase = '';
				switch ( $c[2] ) {
					case 'precio':
						// Reservadas y vendidas: el estado en lugar del precio.
						if ( preg_match( '/sold|vendid/', $status ) ) {
							$v     = 'Vendido';
							$clase = 'zq-precio-estado zq-precio-estado--vendido';
						} elseif ( preg_match( '/reserv|offer|hold|option|pending/', $status ) ) {
							$v     = 'Reservado';
							$clase = 'zq-precio-estado';
						} else {
							$v = is_numeric( $v ) ? number_format( (float) $v, 0, ',', '.' ) . ' €' : $v;
						}
						break;
					case 'm2':
						$v = is_numeric( $v ) ? number_format( (float) $v, 0, ',', '.' ) . ' m²' : $v;
						break;
					case 'estado':
						$v = zq_humanize( $status );
						break;
					case 'dormbanos':
						$d   = isset( $u['bedrooms'] ) && '' !== (string) $u['bedrooms'] ? (string) $u['bedrooms'] : '—';
						$b   = isset( $u['bathrooms'] ) && '' !== (string) $u['bathrooms'] ? (string) $u['bathrooms'] : '—';
						$txt = '<span class="zq-dormbanos"><span title="Dormitorios">' . zq_viv_icono( 'cama' ) . esc_html( $d ) . '</span><span title="Baños">' . zq_viv_icono( 'bano' ) . esc_html( $b ) . '</span></span>';
						break;
					case 'plano':
						$pl = ! empty( $u['_post'] ) ? zq_planos_de_unidad( (int) $u['_post'] ) : array();
						if ( count( $pl ) > 1 && $url ) {
							// Varios planos (sótano, baja, primera…): a la ficha del inmueble, donde están todos.
							$txt = '<a class="zq-ver-plano" href="' . esc_url( $url . '#planos' ) . '">' . zq_viv_icono( 'plano' ) . 'Ver planos (' . count( $pl ) . ')</a>';
						} elseif ( $pl ) {
							$txt = '<a class="zq-ver-plano" href="' . esc_url( wp_get_attachment_url( $pl[0] ) ) . '" target="_blank" rel="noopener">' . zq_viv_icono( 'plano' ) . 'Ver plano</a>';
						} else {
							$txt = '<span class="zq-vacio">—</span>';
						}
						break;
					default:
						$v = zq_humanize( $v );
				}
				if ( ! in_array( $c[2], array( 'dormbanos', 'plano' ), true ) ) {
					$v   = '' === (string) $v ? '—' : $v;
					$txt = esc_html( (string) $v );
					$txt = ( 0 === $i && $url ) ? '<a href="' . esc_url( $url ) . '">' . $txt . '</a>' : $txt;
					$txt = $clase ? '<b class="' . esc_attr( $clase ) . '">' . $txt . '</b>' : $txt;
				}
				$html .= '<td data-label="' . esc_attr( $c[1] ) . '"><span>' . $txt . '</span></td>';
			}
			if ( $ver ) {
				// Columna extra para que se entienda que la fila lleva a la ficha del inmueble.
				$html .= '<td class="zq-col-ver">' . ( $url ? '<a class="zq-ver-propiedad" href="' . esc_url( $url ) . '">' . esc_html( $ver ) . ' <span aria-hidden="true">&rarr;</span></a>' : '' ) . '</td>';
			}
			$html .= '</tr>';
		}
		return $html;
	};

	$out = '<div class="zq-propiedades-wrap">';
	if ( $disponibles ) {
		$out .= '<table class="zq-propiedades">' . $thead . '<tbody>' . $filas( $disponibles ) . '</tbody></table>';
	} else {
		// Sin disponibles: la cabecera de la tabla se mantiene, con el aviso en una fila.
		$aviso = ( $vendidas && ! $reservadas ) ? $a['todas_vendidas'] : $a['sin_disponibles'];
		$out  .= '<table class="zq-propiedades zq-propiedades--vacia">' . $thead . '<tbody><tr><td class="zq-sin-propiedades" colspan="' . ( count( $cols ) + ( $a['ver'] ? 1 : 0 ) ) . '">' . esc_html( $aviso ) . '</td></tr></tbody></table>';
	}
	if ( $reservadas ) {
		$out .= '<details class="zq-vendidas zq-reservadas"><summary>' . esc_html( $a['reservadas'] ) . ' (' . count( $reservadas ) . ')</summary>'
			. '<table class="zq-propiedades zq-propiedades--vendidas zq-propiedades--reservadas">' . $thead . '<tbody>' . $filas( $reservadas ) . '</tbody></table></details>';
	}
	if ( $vendidas ) {
		$out .= '<details class="zq-vendidas"><summary>' . esc_html( $a['vendidas'] ) . ' (' . count( $vendidas ) . ')</summary>'
			. '<table class="zq-propiedades zq-propiedades--vendidas">' . $thead . '<tbody>' . $filas( $vendidas ) . '</tbody></table></details>';
	}
	$out .= '</div>';

	// Toda la fila lleva a la página del inmueble.
	static $js = false;
	if ( ! $js ) {
		$js   = true;
		$out .= '<script>document.addEventListener("click",function(e){var t=e.target.closest(".zq-fila-enlace");if(t&&!e.target.closest("a")){location.href=t.dataset.href;}});document.addEventListener("keydown",function(e){var t=e.target.closest&&e.target.closest(".zq-fila-enlace");if(t&&e.key==="Enter"){location.href=t.dataset.href;}});</script>';
	}
	return $out;
} );

add_shortcode( 'qobrix_galeria', function ( $atts ) {
	$a = shortcode_atts( array(
		'proyecto' => '',
		'desde'    => 1,
		'limite'   => 0,
		'columnas' => 2,
		'tamano'   => 'large',
		'variado'  => 'si', // «no» = fotos seguidas, tal cual vienen de Qobrix.
	), $atts, 'qobrix_galeria' );

	$post_id = zq_resolve_project( $a['proyecto'] );
	$ids     = $post_id ? array_values( (array) get_post_meta( $post_id, '_zq_galeria', true ) ) : array();
	if ( (int) $a['limite'] && 'no' !== $a['variado'] ) {
		// Fotos que no se parezcan entre sí ni a las ya usadas arriba (destacada y anteriores a «desde»).
		$antes = array_slice( $ids, 0, max( 0, (int) $a['desde'] - 1 ) );
		$antes[] = (int) get_post_thumbnail_id( $post_id );
		$ids   = zq_fotos_distintas( $ids, (int) $a['limite'], $antes, $post_id );
		zq_registrar_fotos( $post_id, $ids );
	} else {
		$ids = array_slice( $ids, max( 0, (int) $a['desde'] - 1 ), (int) $a['limite'] ? (int) $a['limite'] : null );
	}
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
