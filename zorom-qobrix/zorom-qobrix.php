<?php
/**
 * Plugin Name: Zorom – Promociones Qobrix
 * Description: Crea una página por cada proyecto del CRM Qobrix (con plantilla de Elementor), sincroniza datos, inmuebles y fotos, y permite colocar cada campo en Elementor con shortcodes y etiquetas dinámicas.
 * Version:     2.14.0
 * Author:      Idital
 * Text Domain: zorom-qobrix
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ZQ_VERSION', '2.14.0' );
define( 'ZQ_FILE', __FILE__ );
define( 'ZQ_DIR', plugin_dir_path( __FILE__ ) );
define( 'ZQ_OPTION', 'zq_settings' );
define( 'ZQ_STATUS', 'zq_status' );
define( 'ZQ_CRON', 'zq_sync_event' );
define( 'ZQ_CPT', 'promocion' );
define( 'ZQ_META_PREFIX', 'qobrix_' );
define( 'ZQ_IMAGE_SIZE', 'zorom-promocion' );

require_once ZQ_DIR . 'includes/api.php';
require_once ZQ_DIR . 'includes/fields.php';
require_once ZQ_DIR . 'includes/traduccion.php';
require_once ZQ_DIR . 'includes/manual.php';
require_once ZQ_DIR . 'includes/single.php';
require_once ZQ_DIR . 'includes/template.php';
require_once ZQ_DIR . 'includes/template-editable.php';
require_once ZQ_DIR . 'includes/shortcodes.php';
require_once ZQ_DIR . 'includes/ficha.php';
require_once ZQ_DIR . 'includes/viviendas.php';
require_once ZQ_DIR . 'includes/mapa.php';
require_once ZQ_DIR . 'includes/elementor.php';
require_once ZQ_DIR . 'includes/admin.php';

/* -------------------------------------------------------------------------
 * Ajustes
 * ---------------------------------------------------------------------- */

function zq_defaults() {
	return array(
		'api_url'       => 'https://zorom8334.eu1.qobrix.com',
		'api_user'      => '',
		'api_key'       => '',
		'locale'        => 'es',
		'slug'          => 'promociones',
		'field_desc'    => 'description,short_description',
		'field_price'   => 'starting_price_from',
		'field_units'   => 'number_of_units',
		'field_date'    => 'completion_date',
		'field_energy'  => 'energy_efficiency_grade,energy_efficiency,energy_class',
		'image_variant' => 'huge',
		'gallery_max'   => 12,
		'single_tpl'    => 0,
		'form_shortcode' => '',
		'form_tpl'      => 0,
		'menu_height'   => 80,
		'escala'        => 100,
		'menu_transparente' => 1,
		'load_fonts'    => 1,
		'traducir'      => 1,
		'deepl_key'     => '',
	);
}

function zq_options() {
	return wp_parse_args( (array) get_option( ZQ_OPTION, array() ), zq_defaults() );
}

/* -------------------------------------------------------------------------
 * Tipo de contenido «Promoción»
 * ---------------------------------------------------------------------- */

function zq_register_cpt() {
	$o = zq_options();

	register_post_type( ZQ_CPT, array(
		'labels'       => array(
			'name'          => 'Promociones',
			'singular_name' => 'Promoción',
			'menu_name'     => 'Promociones',
			'all_items'     => 'Todas las promociones',
			'edit_item'     => 'Editar promoción',
			'view_item'     => 'Ver promoción',
			'search_items'  => 'Buscar promociones',
			'not_found'     => 'No hay promociones. Sincroniza desde Qobrix → Ajustes.',
		),
		'public'       => true,
		'has_archive'  => false, // La página «Ver todas» se monta en Elementor.
		'show_in_menu' => 'zorom-qobrix', // Dentro del menú «Qobrix».
		'show_in_rest' => true,
		'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'page-attributes', 'custom-fields', 'elementor' ),
		'rewrite'      => array( 'slug' => $o['slug'] ? $o['slug'] : 'promociones', 'with_front' => false ),
	) );
}

add_action( 'init', function () {
	add_image_size( ZQ_IMAGE_SIZE, 880, 1140, true );
	zq_register_cpt();

	// Tras actualizar el plugin o cambiar el slug, regenerar enlaces permanentes.
	if ( get_option( 'zq_version' ) !== ZQ_VERSION || get_option( 'zq_flush' ) ) {
		flush_rewrite_rules( false );
		update_option( 'zq_version', ZQ_VERSION );
		zq_migrate_template();
		// Revisar de nuevo fotos y planos de todo (p. ej. planos en PDF añadidos en 2.14.0).
		foreach ( get_posts( array( 'post_type' => array( ZQ_CPT, 'vivienda' ), 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $pid ) {
			update_post_meta( $pid, '_zq_media_pending', 1 );
		}
		if ( ! wp_next_scheduled( 'zq_media_event' ) ) {
			wp_schedule_single_event( time() + 30, 'zq_media_event' );
		}
		delete_option( 'zq_flush' );
		delete_option( 'zq_projects' ); // Datos de la versión 1.
		if ( ! wp_next_scheduled( ZQ_CRON ) ) {
			wp_schedule_event( time() + 60, 'hourly', ZQ_CRON );
		}
	}
} );

/* -------------------------------------------------------------------------
 * Activación y cron
 * ---------------------------------------------------------------------- */

register_activation_hook( ZQ_FILE, function () {
	zq_register_cpt();
	flush_rewrite_rules( false );
	if ( ! wp_next_scheduled( ZQ_CRON ) ) {
		wp_schedule_event( time() + 60, 'hourly', ZQ_CRON );
	}
} );

register_deactivation_hook( ZQ_FILE, function () {
	wp_clear_scheduled_hook( ZQ_CRON );
	wp_clear_scheduled_hook( 'zq_media_event' );
	flush_rewrite_rules( false );
} );

add_action( ZQ_CRON, 'zq_sync' );

/**
 * Si la plantilla asignada es la de ejemplo antigua (antes de la 2.5), crea la nueva calcada
 * del Figma y la asigna. La antigua se conserva en Plantillas guardadas.
 */
function zq_migrate_template() {
	if ( ! class_exists( '\Elementor\Plugin' ) || ! function_exists( 'zq_create_starter_template' ) ) {
		return;
	}
	$tpl  = (int) zq_options()['single_tpl'];
	$data = $tpl ? (string) get_post_meta( $tpl, '_elementor_data', true ) : '';
	if ( $data && false !== strpos( $data, '[qobrix_hero' ) && false === strpos( $data, '[qobrix_ficha' ) ) {
		zq_create_starter_template();
	}
}
