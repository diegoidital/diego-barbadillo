<?php
/**
 * Seed default task templates for Mantenimiento.
 *
 * @package Lezned_Maintenance
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LZND_Seed_Tasks {

	public static function init() {
		add_action( 'admin_post_lznd_seed_tasks', array( __CLASS__, 'run' ) );
	}

	/**
	 * Create (or update) all default task templates.
	 */
	public static function run() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No autorizado.', 'lezned-maintenance' ) );
		}

		check_admin_referer( 'lznd_seed_tasks' );

		// Get or create the "Mantenimiento" tipo term
		$term = get_term_by( 'slug', 'mantenimiento', 'lznd_tipo' );
		if ( ! $term ) {
			$result = wp_insert_term( 'Mantenimiento', 'lznd_tipo', array( 'slug' => 'mantenimiento' ) );
			if ( is_wp_error( $result ) ) {
				wp_redirect( add_query_arg( 'lznd_seed', 'error', admin_url( 'admin.php?page=lznd-settings' ) ) );
				exit;
			}
			$term_id = $result['term_id'];
		} else {
			$term_id = $term->term_id;
		}

		foreach ( self::task_definitions() as $def ) {
			// Find by exact title to avoid duplicates
			$existing = get_page_by_title( $def['titulo'], OBJECT, 'lznd_tarea' );

			if ( $existing ) {
				$post_id = $existing->ID;
				// Update menu_order in case it changed
				wp_update_post( array( 'ID' => $post_id, 'menu_order' => $def['orden'] ) );
			} else {
				$post_id = wp_insert_post( array(
					'post_type'   => 'lznd_tarea',
					'post_title'  => $def['titulo'],
					'post_status' => 'publish',
					'menu_order'  => $def['orden'],
				) );
			}

			if ( is_wp_error( $post_id ) || ! $post_id ) {
				continue;
			}

			update_post_meta( $post_id, '_lznd_tipo_campo', $def['tipo_campo'] );
			update_post_meta( $post_id, '_lznd_opciones',   wp_json_encode( $def['opciones'], JSON_UNESCAPED_UNICODE ) );
			wp_set_object_terms( $post_id, array( $term_id ), 'lznd_tipo' );
		}

		wp_redirect( add_query_arg( 'lznd_seed', 'done', admin_url( 'admin.php?page=lznd-settings' ) ) );
		exit;
	}

	/**
	 * Task definitions for Mantenimiento.
	 */
	private static function task_definitions() {
		return array(

			// ── Estado de componentes (radio: Correcto / Incidencia) ─────────
			array(
				'titulo'     => 'Estado de la bomba',
				'tipo_campo' => 'radio',
				'opciones'   => array( 'Correcto', 'Incidencia' ),
				'orden'      => 10,
			),
			array(
				'titulo'     => 'Estado del inyector',
				'tipo_campo' => 'radio',
				'opciones'   => array( 'Correcto', 'Incidencia' ),
				'orden'      => 20,
			),
			array(
				'titulo'     => 'Estado del variador',
				'tipo_campo' => 'radio',
				'opciones'   => array( 'Correcto', 'Incidencia' ),
				'orden'      => 30,
			),
			array(
				'titulo'     => 'Estado de la válvula de retención',
				'tipo_campo' => 'radio',
				'opciones'   => array( 'Correcto', 'Incidencia' ),
				'orden'      => 40,
			),
			array(
				'titulo'     => 'Llave de entrada',
				'tipo_campo' => 'radio',
				'opciones'   => array( 'Correcto', 'Incidencia' ),
				'orden'      => 50,
			),
			array(
				'titulo'     => 'Llave de salida',
				'tipo_campo' => 'radio',
				'opciones'   => array( 'Correcto', 'Incidencia' ),
				'orden'      => 60,
			),
			array(
				'titulo'     => 'Llave by-pass',
				'tipo_campo' => 'radio',
				'opciones'   => array( 'Correcto', 'Incidencia' ),
				'orden'      => 70,
			),

			// ── Mediciones y datos (texto libre) ─────────────────────────────
			array(
				'titulo'     => 'Presión de precarga (vacío) en Bar',
				'tipo_campo' => 'texto',
				'opciones'   => array(),
				'orden'      => 80,
			),
			array(
				'titulo'     => 'Presión final (con agua) en Bar',
				'tipo_campo' => 'texto',
				'opciones'   => array(),
				'orden'      => 90,
			),
			array(
				'titulo'     => 'Volumen del calderín (en litros)',
				'tipo_campo' => 'texto',
				'opciones'   => array(),
				'orden'      => 100,
			),
			array(
				'titulo'     => 'Número de viviendas',
				'tipo_campo' => 'texto',
				'opciones'   => array(),
				'orden'      => 110,
			),
			array(
				'titulo'     => 'Altura total del edificio (en plantas)',
				'tipo_campo' => 'texto',
				'opciones'   => array(),
				'orden'      => 120,
			),
			array(
				'titulo'     => 'Otros detalles importantes de la instalación',
				'tipo_campo' => 'texto',
				'opciones'   => array(),
				'orden'      => 130,
			),

			// ── Fallos detectados (selección múltiple — checkboxes) ──────────
			array(
				'titulo'     => 'Fallos detectados',
				'tipo_campo' => 'checkboxes',
				'opciones'   => array(
					'Fuga de agua',
					'Ruido anormal',
					'Bomba no arranca',
					'Fallo eléctrico/disyuntor',
					'Fallo de presión en red',
					'Otro',
				),
				'orden'      => 140,
			),

			// ── Clasificación del sistema (radio, una opción) ─────────────────
			array(
				'titulo'     => 'Tipo de bomba',
				'tipo_campo' => 'radio',
				'opciones'   => array( 'Vertical', 'Horizontal', 'Sumergida' ),
				'orden'      => 150,
			),
			array(
				'titulo'     => 'Tipo de alimentación',
				'tipo_campo' => 'radio',
				'opciones'   => array( 'Monofásico', 'Trifásico' ),
				'orden'      => 160,
			),
			array(
				'titulo'     => 'Estado actual de las llaves de corte',
				'tipo_campo' => 'radio',
				'opciones'   => array( 'Correcto', 'Requiere sustitución' ),
				'orden'      => 170,
			),
		);
	}
}

LZND_Seed_Tasks::init();
