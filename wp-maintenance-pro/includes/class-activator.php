<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WMPRO_Activator {

	public static function activate() {
		WMPRO_CPT_Proyecto::register();
		WMPRO_CPT_Parte::register();
		flush_rewrite_rules();
		WMPRO_Cron::setup_events();

		// Crear página frontend si no existe
		if ( ! get_option( 'wmpro_parte_page_id' ) ) {
			$page_id = wp_insert_post( [
				'post_title'   => 'Parte de Mantenimiento',
				'post_name'    => 'parte-mantenimiento',
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_content' => '[wmpro_parte]',
			] );
			update_option( 'wmpro_parte_page_id', $page_id );
		}

		if ( ! get_option( 'wmpro_settings' ) ) {
			update_option( 'wmpro_settings', [
				'admin_email'     => get_option( 'admin_email' ),
				'from_name'       => get_option( 'blogname' ),
				'aviso_dias'      => 7,
				'enviar_cliente'  => 1,
			] );
		}
	}

	public static function deactivate() {
		WMPRO_Cron::clear_events();
		flush_rewrite_rules();
	}
}
