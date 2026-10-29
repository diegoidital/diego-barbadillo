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

	// Galería de la promoción (si no hay, las fotos del inmueble).
	$galeria = $promo ? array_values( (array) get_post_meta( $promo, '_zq_galeria', true ) ) : array();
	if ( ! $galeria ) {
		$galeria = $fotos;
	}
	$portada = $fotos ? (int) $fotos[0] : (int) get_post_thumbnail_id( $post_id );
	if ( ! $portada && $promo ) {
		$portada = (int) get_post_thumbnail_id( $promo );
	}
	$coord = $promo ? zq_coordinates( $promo ) : array();
	$lb    = 'zq-v-' . (int) $post_id;

	ob_start();
	?>
	<div class="zq-f zq-v">
		<header class="zq-v-hero">
			<div class="zq-v-hero__txt">
				<?php if ( $promo ) : ?>
					<a class="zq-v-volver" href="<?php echo esc_url( get_permalink( $promo ) ); ?>">&larr; <?php echo esc_html( get_the_title( $promo ) ); ?></a>
				<?php endif; ?>
				<h1 class="zq-v-titulo"><?php echo esc_html( get_the_title( $post_id ) ); ?></h1>
				<p class="zq-v-meta">
					<?php if ( $ref ) : ?><span>#<?php echo esc_html( $ref ); ?></span><?php endif; ?>
					<span><?php echo esc_html( $sale ? zq_humanize( $sale ) : 'Venta' ); ?></span>
					<span class="zq-v-estado zq-v-estado--<?php echo esc_attr( sanitize_html_class( $status ) ); ?>"><?php echo esc_html( zq_humanize( $status ) ); ?></span>
				</p>
				<p class="zq-v-ubi"><?php echo $promo ? esc_html( get_the_title( $promo ) ) . ' · ' : ''; ?><?php echo $ubi; // phpcs:ignore WordPress.Security.EscapeOutput ?></p>

				<div class="zq-v-hero__pie">
					<?php if ( '' !== $precio ) : ?>
						<div class="zq-v-precio"><?php echo esc_html( zq_viv_precio( $precio ) ); ?> <small>+ IVA</small></div>
					<?php endif; ?>
					<ul class="zq-v-claves">
						<?php if ( '' !== $dorm ) : ?><li><?php echo zq_viv_icono( 'cama' ); // phpcs:ignore ?><span><?php echo esc_html( $dorm ); ?></span></li><?php endif; ?>
						<?php if ( '' !== $banos ) : ?><li><?php echo zq_viv_icono( 'bano' ); // phpcs:ignore ?><span><?php echo esc_html( $banos ); ?></span></li><?php endif; ?>
						<?php if ( '' !== $util ) : ?><li><?php echo zq_viv_icono( 'plano' ); // phpcs:ignore ?><span><?php echo esc_html( str_replace( ' ', '', zq_viv_m2( $util ) ) ); ?></span></li><?php endif; ?>
					</ul>
					<div class="zq-v-botones">
						<a class="zq-v-btn zq-v-btn--negro" href="#contacto">Solicitar información</a>
						<?php if ( $folleto ) : ?><a class="zq-v-btn zq-v-btn--linea" href="<?php echo esc_url( $folleto ); ?>" target="_blank" rel="noopener">Folleto</a><?php endif; ?>
					</div>
				</div>
			</div>
			<div class="zq-v-hero__img">
				<?php echo $portada ? wp_get_attachment_image( $portada, 'full', false, array( 'loading' => 'eager', 'fetchpriority' => 'high' ) ) : zq_image_html( $post_id, 'destacada', 'full' ); // phpcs:ignore ?>
			</div>
		</header>

		<div class="zq-v-canvas">
			<section class="zq-v-desc">
				<h2 class="zq-v-h2">Descripción</h2>
				<div class="zq-f-txt"><?php echo wpautop( wp_kses_post( get_post_field( 'post_content', $post_id ) ) ); // phpcs:ignore ?></div>
			</section>

			<div class="zq-v-datos">
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
					<section class="zq-v-bloque">
						<h3 class="zq-v-h3"><?php echo esc_html( $titulo ); ?></h3>
						<dl class="zq-v-dl"><?php echo $filas; // phpcs:ignore ?></dl>
					</section>
				<?php endforeach; ?>
			</div>
		</div>

		<?php if ( $galeria ) : ?>
			<section class="zq-v-galeria zq-v-galeria--<?php echo (int) min( 5, count( $galeria ) ); ?>">
				<?php foreach ( $galeria as $i => $id ) : ?>
					<a class="zq-v-galeria__foto"<?php echo $i >= 5 ? ' hidden' : ''; ?> href="<?php echo esc_url( wp_get_attachment_image_url( $id, 'full' ) ); ?>" data-zq-lb="<?php echo esc_attr( $lb ); ?>">
						<?php echo $i < 5 ? wp_get_attachment_image( $id, 0 === $i ? 'large' : 'medium_large', false, array( 'loading' => 'lazy' ) ) : ''; // phpcs:ignore ?>
					</a>
				<?php endforeach; ?>
			</section>
		<?php endif; ?>

		<?php if ( $planos ) : ?>
			<div class="zq-v-canvas">
				<section class="zq-v-planos">
					<h2 class="zq-v-h2">Planos</h2>
					<div class="zq-v-planos__grid">
						<?php foreach ( $planos as $id ) : ?>
							<a href="<?php echo esc_url( wp_get_attachment_image_url( $id, 'full' ) ); ?>" data-zq-lb="<?php echo esc_attr( $lb ); ?>-planos"><?php echo wp_get_attachment_image( $id, 'large', false, array( 'loading' => 'lazy' ) ); ?></a>
						<?php endforeach; ?>
					</div>
				</section>
			</div>
		<?php endif; ?>

		<?php if ( $coord ) : ?>
			<section class="zq-v-mapa">
				<iframe src="<?php echo esc_url( 'https://maps.google.com/maps?q=' . rawurlencode( $coord[0] . ',' . $coord[1] ) . '&z=13&output=embed' ); ?>" title="<?php echo esc_attr( 'Mapa de ' . get_the_title( $post_id ) ); ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
			</section>
		<?php endif; ?>

		<?php echo zq_ficha_contacto( $promo ? $promo : $post_id, array( 'formulario' => '' ) ); // phpcs:ignore ?>

		<?php echo zq_viv_similares( $post_id, $promo ); // phpcs:ignore ?>
	</div>
	<?php echo zq_viv_lightbox(); // phpcs:ignore ?>
	<?php
	return ob_get_clean();
} );

/** Iconos de línea para dormitorios, baños y superficie. */
function zq_viv_icono( $n ) {
	$p = array(
		'cama'  => '<path d="M3 18v-6.5A1.5 1.5 0 0 1 4.5 10h19a1.5 1.5 0 0 1 1.5 1.5V18M3 18h22M3 18v3m22-3v3M5 10V7a1 1 0 0 1 1-1h16a1 1 0 0 1 1 1v3M8 10V8.5h5V10m2 0V8.5h5V10M3 15h22"/>',
		'bano'  => '<path d="M3 14h22v2a5 5 0 0 1-5 5H8a5 5 0 0 1-5-5v-2zM6 21l-1 2m17-2 1 2M6 14V6a2.5 2.5 0 0 1 4.6-1.4M9 6.5l3-1.5M3 14h22"/>',
		'plano' => '<path d="M3 4h22v20H3zM3 11h7v7H3M14 4v7h11M14 15v9M18 15h7"/>',
	);
	return '<svg class="zq-v-ico" viewBox="0 0 28 28" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p[ $n ] . '</svg>';
}

/** Visor de fotos propio: no depende de que Elementor cargue su lightbox. */
function zq_viv_lightbox() {
	static $done = false;
	if ( $done ) {
		return '';
	}
	$done = true;
	return <<<'HTML'
<div class="zq-lb" hidden><button class="zq-lb__x" aria-label="Cerrar">&times;</button><button class="zq-lb__p" aria-label="Anterior">&#8249;</button><img alt=""><button class="zq-lb__n" aria-label="Siguiente">&#8250;</button></div>
<script>
(function(){var b=document.querySelector('.zq-lb');if(!b)return;var img=b.querySelector('img'),l=[],i=0;
function show(){img.src=l[i].href;}
function close(){b.hidden=true;document.documentElement.style.overflow='';}
document.addEventListener('click',function(e){var a=e.target.closest('a[data-zq-lb]');if(!a)return;e.preventDefault();e.stopPropagation();
l=[].slice.call(document.querySelectorAll('a[data-zq-lb="'+a.dataset.zqLb+'"]'));i=l.indexOf(a);show();b.hidden=false;document.documentElement.style.overflow='hidden';},true);
b.querySelector('.zq-lb__x').onclick=close;b.onclick=function(e){if(e.target===b)close();};
b.querySelector('.zq-lb__p').onclick=function(){i=(i-1+l.length)%l.length;show();};
b.querySelector('.zq-lb__n').onclick=function(){i=(i+1)%l.length;show();};
document.addEventListener('keydown',function(e){if(b.hidden)return;if(e.key==='Escape')close();if(e.key==='ArrowLeft')b.querySelector('.zq-lb__p').click();if(e.key==='ArrowRight')b.querySelector('.zq-lb__n').click();});})();
</script>
HTML;
}

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
	return '<section class="zq-v-similares"><div class="zq-v-canvas"><h2 class="zq-v-h2 zq-v-h2--centro">Propiedades similares</h2><div class="zq-v-cards">' . $html . '</div></div></section>';
}
