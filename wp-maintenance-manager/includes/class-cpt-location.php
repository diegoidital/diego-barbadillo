<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WPMM_CPT_Location {

	public static function init() {
		add_action( 'init', [ __CLASS__, 'register' ] );
	}

	public static function register() {
		$labels = [
			'name'               => 'Localizaciones',
			'singular_name'      => 'Localización',
			'add_new'            => 'Añadir nueva',
			'add_new_item'       => 'Añadir nueva localización',
			'edit_item'          => 'Editar localización',
			'new_item'           => 'Nueva localización',
			'view_item'          => 'Ver localización',
			'search_items'       => 'Buscar localizaciones',
			'not_found'          => 'No se encontraron localizaciones',
			'not_found_in_trash' => 'No hay localizaciones en la papelera',
			'menu_name'          => 'Localizaciones',
		];

		register_post_type( 'wpmm_localizacion', [
			'labels'       => $labels,
			'public'       => false,
			'show_ui'      => true,
			'show_in_menu' => 'wpmm_menu',
			'supports'     => [ 'title', 'editor', 'thumbnail' ],
			'rewrite'      => false,
			'has_archive'  => false,
		] );
	}
}

WPMM_CPT_Location::init();
