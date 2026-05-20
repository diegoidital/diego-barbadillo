<?php
/**
 * Plugin Name: WP Maintenance Pro
 * Plugin URI:  #
 * Description: Gestión completa de partes de mantenimiento: proyectos, checklist interactivo, fotos, firma digital y envío de informes.
 * Version:     1.0.0
 * Author:      Tu Empresa
 * Text Domain: wp-maintenance-pro
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'WMPRO_VERSION',    '1.0.0' );
define( 'WMPRO_DIR',        plugin_dir_path( __FILE__ ) );
define( 'WMPRO_URL',        plugin_dir_url( __FILE__ ) );
define( 'WMPRO_TEMPLATES',  WMPRO_DIR . 'templates/' );

foreach ( [
	'class-activator',
	'class-cpt-proyecto',
	'class-cpt-parte',
	'class-meta-boxes',
	'class-admin-columns',
	'class-ajax',
	'class-cron',
	'class-settings',
	'class-frontend',
] as $file ) {
	require_once WMPRO_DIR . "includes/{$file}.php";
}

/* Activation / Deactivation */
register_activation_hook(   __FILE__, [ 'WMPRO_Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'WMPRO_Activator', 'deactivate' ] );

/* Admin assets */
add_action( 'admin_enqueue_scripts', function ( $hook ) {
	$screen = get_current_screen();
	if ( ! $screen ) return;
	if ( ! in_array( $screen->post_type, [ 'wmpro_proyecto', 'wmpro_parte' ], true )
		&& $screen->id !== 'mantenimiento_page_wmpro-settings' ) {
		return;
	}
	wp_enqueue_style(  'wmpro-admin',  WMPRO_URL . 'admin/css/admin.css',  [], WMPRO_VERSION );
	wp_enqueue_script( 'wmpro-admin',  WMPRO_URL . 'admin/js/admin.js',   [ 'jquery', 'jquery-ui-sortable' ], WMPRO_VERSION, true );
	wp_localize_script( 'wmpro-admin', 'wmproAdmin', [
		'ajax_url' => admin_url( 'admin-ajax.php' ),
		'nonce'    => wp_create_nonce( 'wmpro_admin' ),
	] );
} );
