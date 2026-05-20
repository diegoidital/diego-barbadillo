<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WMPRO_CPT_Parte {

	public static function init() {
		add_action( 'init', [ __CLASS__, 'register' ] );
		add_action( 'restrict_manage_posts', [ __CLASS__, 'add_filters' ] );
		add_action( 'pre_get_posts',         [ __CLASS__, 'apply_filters' ] );
		add_filter( 'post_row_actions',      [ __CLASS__, 'row_actions' ], 10, 2 );
	}

	public static function register() {
		register_post_type( 'wmpro_parte', [
			'labels' => [
				'name'          => 'Partes de Mantenimiento',
				'singular_name' => 'Parte',
				'add_new'       => 'Nuevo parte',
				'add_new_item'  => 'Crear parte',
				'edit_item'     => 'Editar parte',
				'not_found'     => 'No hay partes',
			],
			'public'       => false,
			'show_ui'      => true,
			'show_in_menu' => 'wmpro-menu',
			'supports'     => [ 'title', 'author' ],
			'rewrite'      => false,
		] );
	}

	public static function get_estados() {
		return [
			'pendiente'  => '⏳ Pendiente',
			'en_proceso' => '🔧 En proceso',
			'completado' => '✅ Completado',
			'enviado'    => '📧 Enviado',
			'cancelado'  => '❌ Cancelado',
		];
	}

	public static function get_prioridades() {
		return [
			'baja'    => '🟢 Baja',
			'normal'  => '🔵 Normal',
			'alta'    => '🟠 Alta',
			'urgente' => '🔴 Urgente',
		];
	}

	public static function add_filters( $post_type ) {
		if ( $post_type !== 'wmpro_parte' ) return;

		$sel_proyecto  = isset( $_GET['wmpro_proyecto'] )  ? intval( $_GET['wmpro_proyecto'] ) : 0;
		$sel_estado    = isset( $_GET['wmpro_estado'] )    ? sanitize_key( $_GET['wmpro_estado'] ) : '';
		$sel_prioridad = isset( $_GET['wmpro_prioridad'] ) ? sanitize_key( $_GET['wmpro_prioridad'] ) : '';

		// Proyectos
		$proyectos = get_posts( [ 'post_type' => 'wmpro_proyecto', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC', 'post_status' => 'publish' ] );
		echo '<select name="wmpro_proyecto"><option value="">— Todos los proyectos —</option>';
		foreach ( $proyectos as $p ) {
			printf( '<option value="%d"%s>%s</option>', $p->ID, selected( $sel_proyecto, $p->ID, false ), esc_html( $p->post_title ) );
		}
		echo '</select>';

		// Estado
		echo '<select name="wmpro_estado"><option value="">— Todos los estados —</option>';
		foreach ( self::get_estados() as $k => $v ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $sel_estado, $k, false ), esc_html( $v ) );
		}
		echo '</select>';

		// Prioridad
		echo '<select name="wmpro_prioridad"><option value="">— Todas las prioridades —</option>';
		foreach ( self::get_prioridades() as $k => $v ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $sel_prioridad, $k, false ), esc_html( $v ) );
		}
		echo '</select>';
	}

	public static function apply_filters( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || $query->get( 'post_type' ) !== 'wmpro_parte' ) return;

		$mq = [];
		if ( ! empty( $_GET['wmpro_proyecto'] ) )  $mq[] = [ 'key' => '_wmpro_proyecto_id',  'value' => intval( $_GET['wmpro_proyecto'] ), 'type' => 'NUMERIC' ];
		if ( ! empty( $_GET['wmpro_estado'] ) )    $mq[] = [ 'key' => '_wmpro_estado',        'value' => sanitize_key( $_GET['wmpro_estado'] ) ];
		if ( ! empty( $_GET['wmpro_prioridad'] ) ) $mq[] = [ 'key' => '_wmpro_prioridad',     'value' => sanitize_key( $_GET['wmpro_prioridad'] ) ];

		if ( $mq ) {
			$mq['relation'] = 'AND';
			$query->set( 'meta_query', $mq );
		}
	}

	public static function row_actions( $actions, $post ) {
		if ( $post->post_type !== 'wmpro_parte' ) return $actions;

		$page_id  = get_option( 'wmpro_parte_page_id' );
		$url      = add_query_arg( 'parte_id', $post->ID, get_permalink( $page_id ) );
		$actions['wmpro_frontend'] = '<a href="' . esc_url( $url ) . '" target="_blank">Ver frontend</a>';
		return $actions;
	}

	/**
	 * Crea un parte a partir de un proyecto, copiando la plantilla de tareas.
	 */
	public static function create_from_proyecto( $proyecto_id ) {
		$proyecto = get_post( $proyecto_id );
		if ( ! $proyecto ) return false;

		$parte_id = wp_insert_post( [
			'post_type'   => 'wmpro_parte',
			'post_title'  => sprintf( '%s — %s', $proyecto->post_title, date_i18n( 'd/m/Y' ) ),
			'post_status' => 'publish',
		] );

		if ( is_wp_error( $parte_id ) ) return false;

		update_post_meta( $parte_id, '_wmpro_proyecto_id', $proyecto_id );
		update_post_meta( $parte_id, '_wmpro_estado',      'pendiente' );
		update_post_meta( $parte_id, '_wmpro_prioridad',   get_post_meta( $proyecto_id, '_wmpro_prioridad_defecto', true ) ?: 'normal' );
		update_post_meta( $parte_id, '_wmpro_fecha_prog',  current_time( 'Y-m-d' ) );

		// Copiar plantilla de tareas
		$template = get_post_meta( $proyecto_id, '_wmpro_tareas_template', true );
		if ( $template ) {
			$tareas = [];
			foreach ( $template as $t ) {
				$tareas[] = [
					'id'             => uniqid( 'task_' ),
					'texto'          => $t['texto'],
					'completada'     => false,
					'completada_en'  => null,
				];
			}
			update_post_meta( $parte_id, '_wmpro_tareas', $tareas );
		}

		return $parte_id;
	}
}

WMPRO_CPT_Parte::init();
