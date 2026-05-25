<?php
/**
 * Plugin Name: Lezned Maintenance Manager
 * Plugin URI:  https://lezned.com
 * Description: Gestión de mantenimientos, proyectos, clientes y actuaciones técnicas.
 * Version:     1.0.0
 * Author:      Lezned
 * Author URI:  https://lezned.com
 * Text Domain: lezned-maintenance
 * Domain Path: /languages
 * License:     GPL-2.0+
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ─── Constants ───────────────────────────────────────────────────────────────
define( 'LZND_VERSION',   '1.0.0' );
define( 'LZND_DIR',       plugin_dir_path( __FILE__ ) );
define( 'LZND_URL',       plugin_dir_url( __FILE__ ) );
define( 'LZND_TEMPLATES', LZND_DIR . 'templates/' );

// ─── Includes ────────────────────────────────────────────────────────────────
require_once LZND_DIR . 'includes/class-setup.php';
require_once LZND_DIR . 'includes/class-dashboard.php';
require_once LZND_DIR . 'includes/class-meta-boxes.php';
require_once LZND_DIR . 'includes/class-admin-ui.php';
require_once LZND_DIR . 'includes/class-ajax.php';
require_once LZND_DIR . 'includes/class-cron.php';
require_once LZND_DIR . 'includes/class-settings.php';
require_once LZND_DIR . 'includes/class-frontend.php';

// ─── Activation / Deactivation ───────────────────────────────────────────────
register_activation_hook( __FILE__, array( 'LZND_Setup', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'LZND_Setup', 'deactivate' ) );

// ─── Admin Assets ────────────────────────────────────────────────────────────
add_action( 'admin_enqueue_scripts', 'lznd_admin_enqueue_scripts' );

/**
 * Enqueue admin CSS and JS only on relevant screens.
 *
 * @param string $hook Current admin page hook suffix.
 */
function lznd_admin_enqueue_scripts( $hook ) {
	global $post;

	$lznd_post_types = array(
		'lznd_cliente',
		'lznd_proyecto',
		'lznd_actuacion',
		'lznd_tarea',
	);

	$is_lznd_post_type = false;

	// post.php / post-new.php
	if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
		if ( $post && in_array( $post->post_type, $lznd_post_types, true ) ) {
			$is_lznd_post_type = true;
		}
	}

	// edit.php list screens
	if ( 'edit.php' === $hook ) {
		$screen_post_type = isset( $_GET['post_type'] ) ? sanitize_key( $_GET['post_type'] ) : '';
		if ( in_array( $screen_post_type, $lznd_post_types, true ) ) {
			$is_lznd_post_type = true;
		}
	}

	// taxonomy screen
	if ( 'edit-tags.php' === $hook || 'term.php' === $hook ) {
		$tax = isset( $_GET['taxonomy'] ) ? sanitize_key( $_GET['taxonomy'] ) : '';
		if ( 'lznd_tipo' === $tax ) {
			$is_lznd_post_type = true;
		}
	}

	// custom admin page
	if ( false !== strpos( $hook, 'lznd-project-detail' ) ) {
		$is_lznd_post_type = true;
	}

	// tipos page
	if ( false !== strpos( $hook, 'lznd-tipos' ) ) {
		$is_lznd_post_type = true;
	}

	// dashboard (toplevel_page_lznd-menu)
	if ( 'toplevel_page_lznd-menu' === $hook ) {
		$is_lznd_post_type = true;
	}

	if ( ! $is_lznd_post_type ) {
		return;
	}

	wp_enqueue_style(
		'lznd-admin',
		LZND_URL . 'admin/css/admin.css',
		array(),
		LZND_VERSION
	);

	wp_enqueue_script(
		'lznd-admin',
		LZND_URL . 'admin/js/admin.js',
		array( 'jquery', 'jquery-ui-sortable' ),
		LZND_VERSION,
		true
	);

	wp_localize_script(
		'lznd-admin',
		'lzndAdmin',
		array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'lznd_admin' ),
		)
	);
}
