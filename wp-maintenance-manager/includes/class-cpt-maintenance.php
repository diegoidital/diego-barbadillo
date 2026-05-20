<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WPMM_CPT_Maintenance {

	public static function init() {
		add_action( 'init', [ __CLASS__, 'register' ] );
		add_action( 'admin_menu', [ __CLASS__, 'add_menu' ] );
		add_action( 'restrict_manage_posts', [ __CLASS__, 'add_filters' ] );
		add_action( 'pre_get_posts', [ __CLASS__, 'apply_filters' ] );
	}

	public static function register() {
		$labels = [
			'name'               => 'Partes de Mantenimiento',
			'singular_name'      => 'Parte de Mantenimiento',
			'add_new'            => 'Nuevo parte',
			'add_new_item'       => 'Crear nuevo parte',
			'edit_item'          => 'Editar parte',
			'new_item'           => 'Nuevo parte',
			'view_item'          => 'Ver parte',
			'search_items'       => 'Buscar partes',
			'not_found'          => 'No se encontraron partes',
			'not_found_in_trash' => 'No hay partes en la papelera',
			'menu_name'          => 'Mantenimiento',
		];

		register_post_type( 'wpmm_parte', [
			'labels'       => $labels,
			'public'       => false,
			'show_ui'      => true,
			'show_in_menu' => 'wpmm_menu',
			'supports'     => [ 'title', 'author' ],
			'rewrite'      => false,
			'has_archive'  => false,
		] );
	}

	public static function add_menu() {
		add_menu_page(
			'Mantenimiento',
			'Mantenimiento',
			'edit_posts',
			'wpmm_menu',
			function() {
				wp_redirect( admin_url( 'edit.php?post_type=wpmm_parte' ) );
				exit;
			},
			'dashicons-hammer',
			30
		);
	}

	public static function add_filters( $post_type ) {
		if ( $post_type !== 'wpmm_parte' ) return;

		// Filtro por localización
		$selected_loc = isset( $_GET['wpmm_loc'] ) ? intval( $_GET['wpmm_loc'] ) : 0;
		$locations = get_posts( [
			'post_type'      => 'wpmm_localizacion',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'orderby'        => 'title',
			'order'          => 'ASC',
		] );
		echo '<select name="wpmm_loc">';
		echo '<option value="">— Todas las localizaciones —</option>';
		foreach ( $locations as $loc ) {
			printf(
				'<option value="%d"%s>%s</option>',
				$loc->ID,
				selected( $selected_loc, $loc->ID, false ),
				esc_html( $loc->post_title )
			);
		}
		echo '</select>';

		// Filtro por estado
		$selected_status = isset( $_GET['wpmm_estado'] ) ? sanitize_key( $_GET['wpmm_estado'] ) : '';
		$estados = WPMM_Meta_Boxes::get_estados();
		echo '<select name="wpmm_estado">';
		echo '<option value="">— Todos los estados —</option>';
		foreach ( $estados as $key => $label ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $key ),
				selected( $selected_status, $key, false ),
				esc_html( $label )
			);
		}
		echo '</select>';

		// Filtro por prioridad
		$selected_prio = isset( $_GET['wpmm_prioridad'] ) ? sanitize_key( $_GET['wpmm_prioridad'] ) : '';
		$prioridades = WPMM_Meta_Boxes::get_prioridades();
		echo '<select name="wpmm_prioridad">';
		echo '<option value="">— Todas las prioridades —</option>';
		foreach ( $prioridades as $key => $label ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $key ),
				selected( $selected_prio, $key, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
	}

	public static function apply_filters( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) return;
		if ( $query->get( 'post_type' ) !== 'wpmm_parte' ) return;

		$meta_query = [];

		if ( ! empty( $_GET['wpmm_loc'] ) ) {
			$meta_query[] = [
				'key'   => '_wpmm_localizacion',
				'value' => intval( $_GET['wpmm_loc'] ),
				'type'  => 'NUMERIC',
			];
		}

		if ( ! empty( $_GET['wpmm_estado'] ) ) {
			$meta_query[] = [
				'key'   => '_wpmm_estado',
				'value' => sanitize_key( $_GET['wpmm_estado'] ),
			];
		}

		if ( ! empty( $_GET['wpmm_prioridad'] ) ) {
			$meta_query[] = [
				'key'   => '_wpmm_prioridad',
				'value' => sanitize_key( $_GET['wpmm_prioridad'] ),
			];
		}

		if ( ! empty( $meta_query ) ) {
			$meta_query['relation'] = 'AND';
			$query->set( 'meta_query', $meta_query );
		}
	}
}

WPMM_CPT_Maintenance::init();
