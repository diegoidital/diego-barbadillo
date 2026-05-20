<?php
/**
 * Plugin Name: WP Maintenance Manager
 * Plugin URI:  #
 * Description: Gestión de partes de mantenimiento organizados por localizaciones o proyectos.
 * Version:     1.0.0
 * Author:      Tu Empresa
 * Text Domain: wp-maintenance-manager
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPMM_VERSION', '1.0.0' );
define( 'WPMM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPMM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once WPMM_PLUGIN_DIR . 'includes/class-cpt-location.php';
require_once WPMM_PLUGIN_DIR . 'includes/class-cpt-maintenance.php';
require_once WPMM_PLUGIN_DIR . 'includes/class-meta-boxes.php';
require_once WPMM_PLUGIN_DIR . 'includes/class-admin-columns.php';

add_action( 'admin_enqueue_scripts', 'wpmm_enqueue_admin_assets' );
function wpmm_enqueue_admin_assets( $hook ) {
	$screen = get_current_screen();
	if ( ! $screen || ! in_array( $screen->post_type, [ 'wpmm_parte', 'wpmm_localizacion' ], true ) ) {
		return;
	}
	wp_enqueue_style( 'wpmm-admin', WPMM_PLUGIN_URL . 'admin/css/admin.css', [], WPMM_VERSION );
	wp_enqueue_script( 'wpmm-admin', WPMM_PLUGIN_URL . 'admin/js/admin.js', [ 'jquery' ], WPMM_VERSION, true );
}

register_activation_hook( __FILE__, 'wpmm_activate' );
function wpmm_activate() {
	WPMM_CPT_Location::register();
	WPMM_CPT_Maintenance::register();
	flush_rewrite_rules();
}

register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
