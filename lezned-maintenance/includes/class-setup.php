<?php
/**
 * LZND_Setup — CPT/Taxonomy registration, activation, menu, seed data.
 *
 * @package Lezned_Maintenance
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LZND_Setup {

	// ─── Bootstrap ───────────────────────────────────────────────────────────

	public static function init() {
		add_action( 'init',       array( __CLASS__, 'register_all' ) );
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		// Rename auto-generated CPT submenu items at late priority
		add_action( 'admin_menu', array( __CLASS__, 'fix_submenu_labels' ), 9999 );
	}

	// ─── Register CPTs & Taxonomy ────────────────────────────────────────────

	public static function register_all() {
		self::register_cliente();
		self::register_proyecto();
		self::register_actuacion();
		self::register_tarea();
		self::register_tipo();
	}

	// ── Cliente ──────────────────────────────────────────────────────────────

	public static function register_cliente() {
		$labels = array(
			'name'               => __( 'Clientes',                   'lezned-maintenance' ),
			'singular_name'      => __( 'Cliente',                    'lezned-maintenance' ),
			'add_new'            => __( 'Añadir nuevo',               'lezned-maintenance' ),
			'add_new_item'       => __( 'Añadir nuevo cliente',       'lezned-maintenance' ),
			'edit_item'          => __( 'Editar cliente',             'lezned-maintenance' ),
			'new_item'           => __( 'Nuevo cliente',              'lezned-maintenance' ),
			'view_item'          => __( 'Ver cliente',                'lezned-maintenance' ),
			'search_items'       => __( 'Buscar clientes',            'lezned-maintenance' ),
			'not_found'          => __( 'No se encontraron clientes', 'lezned-maintenance' ),
			'not_found_in_trash' => __( 'Papelera vacía',            'lezned-maintenance' ),
			'menu_name'          => __( 'Clientes',                   'lezned-maintenance' ),
			'all_items'          => __( 'Clientes',                    'lezned-maintenance' ),
		);

		register_post_type( 'lznd_cliente', array(
			'labels'          => $labels,
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => 'lznd-menu',
			'supports'        => array( 'title' ),
			'rewrite'         => false,
			'capability_type' => 'post',
			'map_meta_cap'    => true,
		) );
	}

	// ── Proyecto ─────────────────────────────────────────────────────────────

	public static function register_proyecto() {
		$labels = array(
			'name'               => __( 'Proyectos',                    'lezned-maintenance' ),
			'singular_name'      => __( 'Proyecto',                    'lezned-maintenance' ),
			'add_new'            => __( 'Añadir nuevo',                'lezned-maintenance' ),
			'add_new_item'       => __( 'Añadir nuevo proyecto',       'lezned-maintenance' ),
			'edit_item'          => __( 'Editar proyecto',             'lezned-maintenance' ),
			'new_item'           => __( 'Nuevo proyecto',              'lezned-maintenance' ),
			'view_item'          => __( 'Ver proyecto',                'lezned-maintenance' ),
			'search_items'       => __( 'Buscar proyectos',            'lezned-maintenance' ),
			'not_found'          => __( 'No se encontraron proyectos', 'lezned-maintenance' ),
			'not_found_in_trash' => __( 'Papelera vacía',             'lezned-maintenance' ),
			'menu_name'          => __( 'Proyectos',                   'lezned-maintenance' ),
			'all_items'          => __( 'Proyectos',                    'lezned-maintenance' ),
		);

		register_post_type( 'lznd_proyecto', array(
			'labels'          => $labels,
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => 'lznd-menu',
			'supports'        => array( 'title', 'thumbnail' ),
			'rewrite'         => false,
			'capability_type' => 'post',
			'map_meta_cap'    => true,
		) );
	}

	// ── Actuación ────────────────────────────────────────────────────────────

	public static function register_actuacion() {
		$labels = array(
			'name'               => __( 'Actuaciones',                    'lezned-maintenance' ),
			'singular_name'      => __( 'Actuación',                     'lezned-maintenance' ),
			'add_new'            => __( 'Añadir nueva',                  'lezned-maintenance' ),
			'add_new_item'       => __( 'Añadir nueva actuación',        'lezned-maintenance' ),
			'edit_item'          => __( 'Editar actuación',              'lezned-maintenance' ),
			'new_item'           => __( 'Nueva actuación',               'lezned-maintenance' ),
			'view_item'          => __( 'Ver actuación',                 'lezned-maintenance' ),
			'search_items'       => __( 'Buscar actuaciones',            'lezned-maintenance' ),
			'not_found'          => __( 'No se encontraron actuaciones', 'lezned-maintenance' ),
			'not_found_in_trash' => __( 'Papelera vacía',               'lezned-maintenance' ),
			'menu_name'          => __( 'Actuaciones',                   'lezned-maintenance' ),
			'all_items'          => __( 'Actuaciones',                    'lezned-maintenance' ),
		);

		register_post_type( 'lznd_actuacion', array(
			'labels'          => $labels,
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => 'lznd-menu',
			'supports'        => array( 'title', 'author' ),
			'rewrite'         => false,
			'capability_type' => 'post',
			'map_meta_cap'    => true,
		) );
	}

	// ── Tarea ────────────────────────────────────────────────────────────────

	public static function register_tarea() {
		$labels = array(
			'name'               => __( 'Tareas',                    'lezned-maintenance' ),
			'singular_name'      => __( 'Tarea',                    'lezned-maintenance' ),
			'add_new'            => __( 'Añadir nueva',             'lezned-maintenance' ),
			'add_new_item'       => __( 'Añadir nueva tarea',       'lezned-maintenance' ),
			'edit_item'          => __( 'Editar tarea',             'lezned-maintenance' ),
			'new_item'           => __( 'Nueva tarea',              'lezned-maintenance' ),
			'view_item'          => __( 'Ver tarea',                'lezned-maintenance' ),
			'search_items'       => __( 'Buscar tareas',            'lezned-maintenance' ),
			'not_found'          => __( 'No se encontraron tareas', 'lezned-maintenance' ),
			'not_found_in_trash' => __( 'Papelera vacía',          'lezned-maintenance' ),
			'menu_name'          => __( 'Tareas',                   'lezned-maintenance' ),
			'all_items'          => __( 'Tareas',                    'lezned-maintenance' ),
		);

		register_post_type( 'lznd_tarea', array(
			'labels'          => $labels,
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => 'lznd-menu',
			'supports'        => array( 'title' ),
			'rewrite'         => false,
			'capability_type' => 'post',
			'map_meta_cap'    => true,
		) );
	}

	// ── Taxonomy: lznd_tipo ──────────────────────────────────────────────────

	public static function register_tipo() {
		$labels = array(
			'name'              => __( 'Tipos de trabajo',         'lezned-maintenance' ),
			'singular_name'     => __( 'Tipo de trabajo',         'lezned-maintenance' ),
			'search_items'      => __( 'Buscar tipos',            'lezned-maintenance' ),
			'all_items'         => __( 'Todos los tipos',         'lezned-maintenance' ),
			'parent_item'       => __( 'Tipo padre',              'lezned-maintenance' ),
			'parent_item_colon' => __( 'Tipo padre:',             'lezned-maintenance' ),
			'edit_item'         => __( 'Editar tipo',             'lezned-maintenance' ),
			'update_item'       => __( 'Actualizar tipo',         'lezned-maintenance' ),
			'add_new_item'      => __( 'Añadir nuevo tipo',       'lezned-maintenance' ),
			'new_item_name'     => __( 'Nombre del nuevo tipo',   'lezned-maintenance' ),
			'menu_name'         => __( 'Tipos de trabajo',        'lezned-maintenance' ),
			'not_found'         => __( 'No se encontraron tipos', 'lezned-maintenance' ),
		);

		register_taxonomy( 'lznd_tipo', array( 'lznd_actuacion', 'lznd_tarea' ), array(
			'labels'            => $labels,
			'hierarchical'      => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => false,
			'rewrite'           => false,
		) );
	}

	// ─── Admin Menu ──────────────────────────────────────────────────────────

	public static function add_menu() {
		// Main menu — callback renders the dashboard (also becomes first submenu item)
		add_menu_page(
			__( 'Mantenimiento', 'lezned-maintenance' ),
			__( 'Mantenimiento', 'lezned-maintenance' ),
			'edit_posts',
			'lznd-menu',
			array( 'LZND_Dashboard', 'render' ),
			'dashicons-hammer',
			28
		);

		// NOTE: Proyectos, Clientes, Actuaciones, Tareas are added automatically
		// by WordPress because each CPT has show_in_menu='lznd-menu'.
		// Their label in the menu comes from the CPT 'all_items' label.
		// Do NOT manually add them here to avoid duplicates.

		// Tipos de trabajo — custom management page
		add_submenu_page(
			'lznd-menu',
			__( 'Tipos de trabajo', 'lezned-maintenance' ),
			__( 'Tipos de trabajo', 'lezned-maintenance' ),
			'manage_categories',
			'lznd-tipos',
			array( 'LZND_Admin_UI', 'render_tipos_page' )
		);

		// Project detail hidden page
		add_submenu_page(
			'',
			__( 'Detalle del proyecto', 'lezned-maintenance' ),
			__( 'Detalle del proyecto', 'lezned-maintenance' ),
			'edit_posts',
			'lznd-project-detail',
			array( 'LZND_Admin_UI', 'render_project_detail' )
		);

	}

	/**
	 * Rename the first auto-generated submenu item (copy of main menu) to "Dashboard".
	 * Runs at priority 9999 so all CPT submenus are already registered.
	 */
	public static function fix_submenu_labels() {
		global $submenu;
		if ( ! isset( $submenu['lznd-menu'] ) ) {
			return;
		}
		// The first entry WordPress creates mirrors the main menu title.
		// Rename it to "Dashboard".
		foreach ( $submenu['lznd-menu'] as &$item ) {
			if ( isset( $item[2] ) && $item[2] === 'lznd-menu' ) {
				$item[0] = '&#x1F4CA; Dashboard';
				break;
			}
		}
		unset( $item );
	}

	// ─── Activation ──────────────────────────────────────────────────────────

	public static function activate() {
		self::register_all();
		flush_rewrite_rules();
		self::seed_default_data();
		self::setup_page();
		self::setup_settings();
		LZND_Cron::setup_events();
	}

	// ─── Deactivation ────────────────────────────────────────────────────────

	public static function deactivate() {
		LZND_Cron::clear_events();
		flush_rewrite_rules();
	}

	// ─── Seed default data ───────────────────────────────────────────────────

	public static function seed_default_data() {
		// ── Default lznd_tipo terms ──────────────────────────────────────────
		$existing_terms = get_terms( array(
			'taxonomy'   => 'lznd_tipo',
			'hide_empty' => false,
			'fields'     => 'ids',
		) );

		if ( empty( $existing_terms ) || is_wp_error( $existing_terms ) ) {
			$default_tipos = array(
				'Mantenimiento',
				'Primera visita',
				'Urgencia',
				'Presupuesto',
			);
			foreach ( $default_tipos as $tipo ) {
				wp_insert_term( $tipo, 'lznd_tipo' );
			}
		}

		// ── Default lznd_tarea posts ─────────────────────────────────────────
		$existing_tareas = get_posts( array(
			'post_type'      => 'lznd_tarea',
			'posts_per_page' => 1,
			'post_status'    => 'any',
			'fields'         => 'ids',
		) );

		if ( empty( $existing_tareas ) ) {
			$default_tareas = array(
				'Revisión de juntas',
				'Revisión de llaves',
				'Revisión de grupo presión',
				'Conexiones de manguitos',
				'Estado de tuberías',
				'Pruebas de presión',
			);
			foreach ( $default_tareas as $tarea_name ) {
				wp_insert_post( array(
					'post_title'  => $tarea_name,
					'post_type'   => 'lznd_tarea',
					'post_status' => 'publish',
				) );
			}
		}
	}

	// ─── Setup frontend page ─────────────────────────────────────────────────

	public static function setup_page() {
		if ( get_option( 'lznd_page_id' ) ) {
			return;
		}

		$page_id = wp_insert_post( array(
			'post_title'   => __( 'Parte de Mantenimiento', 'lezned-maintenance' ),
			'post_content' => '[lznd_actuacion]',
			'post_status'  => 'publish',
			'post_type'    => 'page',
		) );

		if ( $page_id && ! is_wp_error( $page_id ) ) {
			update_option( 'lznd_page_id', $page_id );
		}
	}

	// ─── Setup default settings ──────────────────────────────────────────────

	public static function setup_settings() {
		if ( get_option( 'lznd_settings' ) ) {
			return;
		}

		update_option( 'lznd_settings', array(
			'admin_email'    => get_option( 'admin_email' ),
			'from_name'      => get_option( 'blogname' ),
			'whatsapp_admin' => '',
		) );
	}
}

LZND_Setup::init();
