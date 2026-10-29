<?php
/**
 * Inmuebles de cada promoción: una página por inmueble (tipo de contenido «vivienda»),
 * creada y actualizada desde Qobrix, como la ficha /property/... de la web anterior.
 *
 * URL: /propiedad/<nombre>-<ref>/ (p. ej. /propiedad/am-10-1080/).
 * Página: [qobrix_vivienda] (cabecera con precio y datos, fotos, planos, folleto,
 * descripción, detalles, superficies, contacto y propiedades similares).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ZQ_CPT_VIVIENDA', 'vivienda' );

add_action( 'init', function () {
	register_post_type( ZQ_CPT_VIVIENDA, array(
		'labels'       => array(
			'name'          => 'Inmuebles',
			'singular_name' => 'Inmueble',
			'all_items'     => 'Inmuebles',
			'edit_item'     => 'Editar inmueble',
			'view_item'     => 'Ver inmueble',
			'search_items'  => 'Buscar inmuebles',
			'not_found'     => 'No hay inmuebles. Sincroniza desde Qobrix → Ajustes.',
		),
		'public'       => true,
		'has_archive'  => false,
		'show_in_menu' => 'zorom-qobrix',
		'show_in_rest' => true,
		'supports'     => array( 'title', 'editor', 'thumbnail', 'custom-fields', 'elementor' ),
		'rewrite'      => array( 'slug' => 'propiedad', 'with_front' => false ),
	) );
}, 5 );

/* -------------------------------------------------------------------------
 * Sincronización
 * ---------------------------------------------------------------------- */

function zq_find_vivienda( $qobrix_id ) {
	$ids = get_posts( array(
		'post_type'        => ZQ_CPT_VIVIENDA,
		'post_status'      => 'any',
		'meta_key'         => '_qobrix_id',
		'meta_value'       => $qobrix_id,
		'fields'           => 'ids',
		'posts_per_page'   => 1,
		'suppress_filters' => true,
	) );
	return $ids ? (int) $ids[0] : 0;
}

/**
 * Crea o actualiza la página de cada inmueble. $rows = inmuebles de Qobrix con proyecto;
 * $promos = [id Qobrix del proyecto => id de la promoción en WordPress].
 * Devuelve [id Qobrix del inmueble => id de la página].
 */
function zq_sync_viviendas( array $rows, array $promos ) {
	$out  = array();
	$seen = array();
	foreach ( $rows as $row ) {
		if ( empty( $row['id'] ) ) {
			continue;
		}
		$project = isset( $row['project'] ) ? $row['project'] : '';
		$project = is_array( $project ) ? ( isset( $project['id'] ) ? $project['id'] : '' ) : $project;
		if ( ! $project || empty( $promos[ $project ] ) ) {
			continue;
		}
		$qid      = (string) $row['id'];
		$ref      = isset( $row['ref'] ) ? (string) $row['ref'] : '';
		$name     = isset( $row['name'] ) && '' !== (string) $row['name'] ? (string) $row['name'] : ( $ref ? 'Ref. ' . $ref : $qid );
		$modified = isset( $row['modified'] ) ? (string) $row['modified'] : '';
		$post_id  = zq_find_vivienda( $qid );

		if ( ! $post_id || get_post_meta( $post_id, '_qobrix_modified', true ) !== $modified ) {
			$desc    = '';
			foreach ( array( 'custom_description_es', 'description' ) as $f ) {
				if ( ! empty( $row[ $f ] ) && is_string( $row[ $f ] ) ) {
					$desc = $row[ $f ];
					break;
				}
			}
			$postarr = array(
				'post_type'    => ZQ_CPT_VIVIENDA,
				'post_title'   => $name,
				'post_content' => wp_kses_post( $desc ),
			);
			if ( $post_id ) {
				$postarr['ID'] = $post_id;
				$post_id       = wp_update_post( $postarr, true );
			} else {
				$postarr['post_status'] = 'publish';
				$postarr['post_name']   = sanitize_title( ( $ref && false === strpos( $name, $ref ) ) ? $name . '-' . $ref : $name );
				$post_id                = wp_insert_post( $postarr, true );
			}
			if ( is_wp_error( $post_id ) || ! $post_id ) {
				continue;
			}
			foreach ( $row as $k => $v ) {
				$key = ZQ_META_PREFIX . sanitize_key( $k );
				if ( is_scalar( $v ) || null === $v ) {
					update_post_meta( $post_id, $key, is_bool( $v ) ? (int) $v : (string) $v );
				}
			}
			update_post_meta( $post_id, '_qobrix_id', $qid );
			update_post_meta( $post_id, '_qobrix_modified', $modified );
			update_post_meta( $post_id, '_zq_media_pending', 1 );
		}
		if ( 'publish' !== get_post_status( $post_id ) && get_post_meta( $post_id, '_zq_hidden', true ) ) {
			wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
			delete_post_meta( $post_id, '_zq_hidden' );
		}
		update_post_meta( $post_id, '_zq_promocion', (int) $promos[ $project ] );
		$out[ $qid ] = $post_id;
		$seen[]      = $post_id;
	}

	// Inmuebles que ya no están en Qobrix: a borrador.
	$orphans = get_posts( array(
		'post_type'      => ZQ_CPT_VIVIENDA,
		'post_status'    => 'publish',
		'fields'         => 'ids',
		'posts_per_page' => -1,
		'post__not_in'   => $seen ? $seen : array( 0 ),
	) );
	foreach ( $orphans as $id ) {
		wp_update_post( array( 'ID' => $id, 'post_status' => 'draft' ) );
		update_post_meta( $id, '_zq_hidden', 1 );
	}
	return $out;
}

/* -------------------------------------------------------------------------
 * Datos y formato
 * ---------------------------------------------------------------------- */

/** Primer valor con dato entre varios nombres de campo («a|b|c»). */
function zq_viv_val( $post_id, $fields ) {
	foreach ( preg_split( '/[|,]/', $fields ) as $f ) {
		$v = get_post_meta( $post_id, ZQ_META_PREFIX . sanitize_key( trim( $f ) ), true );
		if ( '' !== $v && null !== $v && '0' !== $v && '0.00' !== $v ) {
			return $v;
		}
	}
	return '';
}

/** Traduce los valores codificados de Qobrix (under_construction…) a texto. */
function zq_humanize( $v ) {
	$dic = apply_filters( 'zq_traducciones', array(
		'available'            => 'Disponible',
		'reserved'             => 'Reservado',
		'sold'                 => 'Vendido',
		'withdrawn'            => 'Retirado',
		'for_sale'             => 'Venta',
		'for_rent'             => 'Alquiler',
		'under_construction'   => 'En construcción',
		'construction_phase'   => 'En construcción',
		'off_plan'             => 'Sobre plano',
		'completed'            => 'Terminado',
		'ready'                => 'Listo para entrar',
		'furnished'            => 'Amueblado',
		'unfurnished'          => 'Sin amueblar',
		'optionally_furnished' => 'Amueblado opcionalmente',
		'partially_furnished'  => 'Parcialmente amueblado',
		'open_plan'            => 'Americana',
		'american'             => 'Americana',
		'separate'             => 'Independiente',
		'concrete'             => 'Hormigón',
		'brick'                => 'Ladrillo',
		'house'                => 'Casa',
		'villa'                => 'Villa',
		'apartment'            => 'Apartamento',
		'penthouse'            => 'Ático',
		'townhouse'            => 'Adosado',
		'gf'                   => 'Planta baja',
		'ground_floor'         => 'Planta baja',
	) );
	$k = strtolower( trim( (string) $v ) );
	if ( isset( $dic[ $k ] ) ) {
		return $dic[ $k ];
	}
	return ( preg_match( '/^[a-z0-9_]+$/', $k ) && false !== strpos( $k, '_' ) ) ? ucfirst( str_replace( '_', ' ', $k ) ) : (string) $v;
}

function zq_viv_precio( $v ) {
	return is_numeric( $v ) ? number_format( (float) $v, 0, ',', '.' ) . ' €' : (string) $v;
}

function zq_viv_m2( $v ) {
	return is_numeric( $v ) ? number_format( (float) $v, 0, ',', '.' ) . ' m²' : (string) $v;
}

/** Campos de la ficha. Cada uno prueba varios nombres de Qobrix, en orden. */
function zq_viv_campos() {
	return apply_filters( 'zq_vivienda_campos', array(
		'detalles'    => array(
			'Etapa de construcción' => 'construction_stage',
			'Planta'                => 'floor_number|floor|floor_level|level',
			'Dormitorios'           => 'bedrooms',
			'Baños'                 => 'bathrooms',
			'Amueblado'             => 'furnished|furnishing',
			'Tipo de cocina'        => 'kitchen_type|kitchen',
			'Aparcamiento cubierto' => 'covered_parking|parking_covered|covered_parking_spaces|parking_spaces',
			'Año de construcción'   => 'construction_year|year_built|year_of_construction|completion_year',
			'Tipo de construcción'  => 'construction_type',
		),
		'superficies' => array(
			'Superficie útil'       => 'internal_area_amount|living_area_amount|covered_area_amount|net_area_amount',
			'Superficie construida' => 'total_area_amount|built_area_amount|gross_area_amount|covered_area_amount',
			'Parcela'               => 'plot_area_amount|land_area_amount',
			'Terraza'               => 'covered_veranda_amount|uncovered_veranda_amount|terrace_area_amount|veranda_area_amount',
		),
		'precio'      => 'list_selling_price_amount|selling_price_amount|price_amount',
		'util'        => 'internal_area_amount|living_area_amount|covered_area_amount|net_area_amount',
	) );
}

/* -------------------------------------------------------------------------
 * Página del inmueble
 * ---------------------------------------------------------------------- */

add_filter( 'template_include', function ( $template ) {
	if ( ! is_singular( ZQ_CPT_VIVIENDA ) ) {
		return $template;
	}
	// Si Elementor Pro tiene una plantilla «Entrada individual» para inmuebles, manda esa.
	if ( function_exists( 'elementor_location_exits' ) && elementor_location_exits( 'single', true ) ) {
		return $template;
	}
	return ZQ_DIR . 'templates/single-vivienda.php';
}, 21 );

add_action( 'wp_enqueue_scripts', function () {
	if ( is_singular( ZQ_CPT_VIVIENDA ) ) {
		zq_enqueue_ficha_style();
		zq_enqueue_fuentes();
	}
}, 1000 );

add_shortcode( 'qobrix_vivienda', function ( $atts ) {
	$a       = shortcode_atts( array( 'id' => 0 ), $atts, 'qobrix_vivienda' );
	$post_id = (int) $a['id'] ? (int) $a['id'] : get_the_ID();
	if ( ZQ_CPT_VIVIENDA !== get_post_type( $post_id ) ) {
		return '';
	}
	zq_enqueue_ficha_style();
	$c      = zq_viv_campos();
	$promo  = (int) get_post_meta( $post_id, '_zq_promocion', true );
	$ref    = get_post_meta( $post_id, ZQ_META_PREFIX . 'ref', true );
	$status = strtolower( (string) get_post_meta( $post_id, ZQ_META_PREFIX . 'status', true ) );
	$sale   = (string) get_post_meta( $post_id, ZQ_META_PREFIX . 'sale_rent', true );
	$precio = zq_viv_val( $post_id, $c['precio'] );
	$dorm   = zq_viv_val( $post_id, 'bedrooms' );
	$banos  = zq_viv_val( $post_id, 'bathrooms' );
	$util   = zq_viv_val( $post_id, $c['util'] );
	$ubi    = $promo ? zq_render_field( $promo, 'ubicacion' ) : esc_html( (string) get_post_meta( $post_id, ZQ_META_PREFIX . 'city', true ) );
	$folleto = (string) get_post_meta( $post_id, '_zq_folleto', true );
	if ( ! $folleto && $promo ) {
		$folleto = (string) get_post_meta( $promo, '_zq_folleto', true );
	}
	$fotos  = array_values( (array) get_post_meta( $post_id, '_zq_galeria', true ) );
	$planos = array_values( (array) get_post_meta( $post_id, '_zq_planos', true ) );
	if ( ! $fotos && $promo ) { // Sin fotos propias: las de la promoción.
		$fotos = array_values( (array) get_post_meta( $promo, '_zq_galeria', true ) );
	}

	ob_start();
	?>
	<div class="zq-f zq-v">
		<div class="zq-v-canvas">
			<?php if ( $promo ) : ?>
				<a class="zq-v-volver" href="<?php echo esc_url( get_permalink( $promo ) ); ?>">&larr; <?php echo esc_html( get_the_title( $promo ) ); ?></a>
			<?php endif; ?>

			<header class="zq-v-cab">
				<div class="zq-v-cab__txt">
					<h1 class="zq-f-h"><?php echo esc_html( get_the_title( $post_id ) ); ?></h1>
					<p class="zq-v-meta">
						<?php if ( $ref ) : ?><span>#<?php echo esc_html( $ref ); ?></span><?php endif; ?>
						<span><?php echo esc_html( $sale ? zq_humanize( $sale ) : 'Venta' ); ?></span>
						<span class="zq-v-estado zq-v-estado--<?php echo esc_attr( sanitize_html_class( $status ) ); ?>"><?php echo esc_html( zq_humanize( $status ) ); ?></span>
					</p>
					<p class="zq-v-ubi"><?php echo $promo ? esc_html( get_the_title( $promo ) ) . ' · ' : ''; ?><?php echo $ubi; // phpcs:ignore WordPress.Security.EscapeOutput ?></p>
				</div>
				<div class="zq-v-cab__precio">
					<?php if ( '' !== $precio ) : ?>
						<div class="zq-v-precio"><?php echo esc_html( zq_viv_precio( $precio ) ); ?> <small>+ IVA</small></div>
					<?php endif; ?>
					<ul class="zq-v-claves">
						<?php if ( '' !== $dorm ) : ?><li><span><?php echo esc_html( $dorm ); ?> dormitorios</span></li><?php endif; ?>
						<?php if ( '' !== $banos ) : ?><li><span><?php echo esc_html( $banos ); ?> baños</span></li><?php endif; ?>
						<?php if ( '' !== $util ) : ?><li><span><?php echo esc_html( zq_viv_m2( $util ) ); ?></span></li><?php endif; ?>
					</ul>
					<div class="zq-v-botones">
						<a class="zq-f-btn zq-v-btn--negro" href="#contacto">Solicitar información</a>
						<?php if ( $folleto ) : ?><a class="zq-f-btn zq-v-btn--blanco" href="<?php echo esc_url( $folleto ); ?>" target="_blank" rel="noopener">Folleto</a><?php endif; ?>
					</div>
				</div>
			</header>

			<?php if ( $fotos ) : ?>
				<section class="zq-v-fotos zq-v-fotos--<?php echo (int) min( 5, count( $fotos ) ); ?>">
					<?php foreach ( array_slice( $fotos, 0, 5 ) as $i => $id ) : ?>
						<a class="zq-v-foto zq-v-foto--<?php echo 0 === $i ? 'grande' : 'peq'; ?>" href="<?php echo esc_url( wp_get_attachment_image_url( $id, 'full' ) ); ?>" data-elementor-open-lightbox="yes" data-elementor-lightbox-slideshow="zq-v-<?php echo (int) $post_id; ?>">
							<?php echo wp_get_attachment_image( $id, 0 === $i ? 'large' : 'medium_large', false, array( 'loading' => 0 === $i ? 'eager' : 'lazy' ) ); ?>
						</a>
					<?php endforeach; ?>
					<?php foreach ( array_slice( $fotos, 5 ) as $id ) : ?>
						<a hidden href="<?php echo esc_url( wp_get_attachment_image_url( $id, 'full' ) ); ?>" data-elementor-open-lightbox="yes" data-elementor-lightbox-slideshow="zq-v-<?php echo (int) $post_id; ?>"></a>
					<?php endforeach; ?>
				</section>
			<?php endif; ?>

			<div class="zq-v-cuerpo">
				<section class="zq-v-desc">
					<h2 class="zq-f-h">Descripción</h2>
					<div class="zq-f-txt"><?php echo wpautop( wp_kses_post( get_post_field( 'post_content', $post_id ) ) ); // phpcs:ignore ?></div>
				</section>

				<aside class="zq-v-datos">
					<?php foreach ( array( 'Detalles' => $c['detalles'], 'Superficies' => $c['superficies'] ) as $titulo => $campos ) : ?>
						<?php
						$filas = '';
						foreach ( $campos as $label => $fields ) {
							$v = zq_viv_val( $post_id, $fields );
							if ( '' === $v ) {
								continue;
							}
							$v      = 'Superficies' === $titulo ? zq_viv_m2( $v ) : zq_humanize( $v );
							$filas .= '<div><dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $v ) . '</dd></div>';
						}
						if ( ! $filas ) {
							continue;
						}
						?>
						<h3 class="zq-f-h3"><?php echo esc_html( $titulo ); ?></h3>
						<dl class="zq-v-dl"><?php echo $filas; // phpcs:ignore ?></dl>
					<?php endforeach; ?>
				</aside>
			</div>

			<?php if ( $planos ) : ?>
				<section class="zq-v-planos">
					<h2 class="zq-f-h">Planos</h2>
					<div class="zq-v-planos__grid">
						<?php foreach ( $planos as $id ) : ?>
							<a href="<?php echo esc_url( wp_get_attachment_image_url( $id, 'full' ) ); ?>" data-elementor-open-lightbox="yes" data-elementor-lightbox-slideshow="zq-vp-<?php echo (int) $post_id; ?>"><?php echo wp_get_attachment_image( $id, 'large', false, array( 'loading' => 'lazy' ) ); ?></a>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endif; ?>
		</div>

		<?php echo zq_ficha_contacto( $promo ? $promo : $post_id, array( 'formulario' => '' ) ); // phpcs:ignore ?>

		<?php echo zq_viv_similares( $post_id, $promo ); // phpcs:ignore ?>
	</div>
	<?php
	return ob_get_clean();
} );

/** Otros inmuebles disponibles de la misma promoción. */
function zq_viv_similares( $post_id, $promo, $max = 3 ) {
	if ( ! $promo ) {
		return '';
	}
	$ids = get_posts( array(
		'post_type'      => ZQ_CPT_VIVIENDA,
		'post_status'    => 'publish',
		'posts_per_page' => 30,
		'fields'         => 'ids',
		'post__not_in'   => array( $post_id ),
		'meta_key'       => '_zq_promocion',
		'meta_value'     => $promo,
	) );
	$ids = array_values( array_filter( $ids, function ( $id ) {
		return 'sold' !== strtolower( (string) get_post_meta( $id, ZQ_META_PREFIX . 'status', true ) );
	} ) );
	$ids = array_slice( $ids, 0, $max );
	if ( ! $ids ) {
		return '';
	}
	$c    = zq_viv_campos();
	$html = '';
	foreach ( $ids as $id ) {
		$p     = zq_viv_val( $id, $c['precio'] );
		$html .= '<a class="zq-v-card" href="' . esc_url( get_permalink( $id ) ) . '">'
			. zq_image_html( $id, 'destacada', 'medium_large' )
			. '<span class="zq-v-card__ref">#' . esc_html( get_post_meta( $id, ZQ_META_PREFIX . 'ref', true ) ) . '</span>'
			. '<span class="zq-v-card__titulo">' . esc_html( get_the_title( $id ) ) . '</span>'
			. ( '' !== $p ? '<span class="zq-v-card__precio">' . esc_html( zq_viv_precio( $p ) ) . ' + IVA</span>' : '' )
			. '<span class="zq-v-card__claves">' . esc_html( trim( zq_viv_val( $id, 'bedrooms' ) . ' dorm. · ' . zq_viv_val( $id, 'bathrooms' ) . ' baños · ' . zq_viv_m2( zq_viv_val( $id, $c['util'] ) ), ' ·' ) ) . '</span>'
			. '</a>';
	}
	return '<section class="zq-f-sec zq-v-similares"><div class="zq-v-canvas"><h2 class="zq-f-hc">Propiedades similares</h2><div class="zq-v-cards">' . $html . '</div></div></section>';
}
