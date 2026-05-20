<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WMPRO_CPT_Proyecto {

	public static function init() {
		add_action( 'init', [ __CLASS__, 'register' ] );
		add_action( 'admin_menu', [ __CLASS__, 'add_menu' ] );
	}

	public static function register() {
		register_post_type( 'wmpro_proyecto', [
			'labels' => [
				'name'               => 'Proyectos / Localizaciones',
				'singular_name'      => 'Proyecto',
				'add_new'            => 'Nuevo proyecto',
				'add_new_item'       => 'Añadir proyecto',
				'edit_item'          => 'Editar proyecto',
				'search_items'       => 'Buscar proyectos',
				'not_found'          => 'No hay proyectos',
				'menu_name'          => 'Mantenimiento',
			],
			'public'       => false,
			'show_ui'      => true,
			'show_in_menu' => 'wmpro-menu',
			'supports'     => [ 'title', 'thumbnail' ],
			'rewrite'      => false,
		] );
	}

	public static function add_menu() {
		add_menu_page(
			'Mantenimiento Pro',
			'Mantenimiento',
			'edit_posts',
			'wmpro-menu',
			function() {
				wp_safe_redirect( admin_url( 'edit.php?post_type=wmpro_proyecto' ) );
				exit;
			},
			'dashicons-hammer',
			28
		);

		add_submenu_page( 'wmpro-menu', 'Proyectos', 'Proyectos', 'edit_posts', 'edit.php?post_type=wmpro_proyecto' );
		add_submenu_page( 'wmpro-menu', 'Partes',    'Partes',    'edit_posts', 'edit.php?post_type=wmpro_parte' );
	}
}

WMPRO_CPT_Proyecto::init();
