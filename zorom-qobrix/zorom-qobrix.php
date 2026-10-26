<?php
/**
 * Plugin Name: Zorom – Promociones Qobrix
 * Description: Crea una página por cada proyecto del CRM Qobrix (con plantilla de Elementor), sincroniza datos, inmuebles y fotos, y permite colocar cada campo en Elementor con shortcodes y etiquetas dinámicas.
 * Version:     2.1.0
 * Author:      Idital
 * Text Domain: zorom-qobrix
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ZQ_VERSION', '2.1.0' );
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
require_once ZQ_DIR . 'includes/manual.php';
require_once ZQ_DIR . 'includes/single.php';
require_once ZQ_DIR . 'includes/template.php';
require_once ZQ_DIR . 'includes/shortcodes.php';
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
			'not_found'     => 'No hay promociones. Sincroniza desde Ajustes → Qobrix.',
		),
		'public'       => true,
		'has_archive'  => false, // La página «Ver todas» se monta en Elementor.
		'menu_icon'    => 'dashicons-building',
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
	flush_rewrite_rules( false );
} );

add_action( ZQ_CRON, 'zq_sync' );
