<?php
/**
 * LZND_Admin_UI — Admin list columns, filters, custom pages, and project detail view.
 *
 * @package Lezned_Maintenance
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LZND_Admin_UI {

	// ─── Bootstrap ───────────────────────────────────────────────────────────

	public static function init() {
		// Cliente columns
		add_filter( 'manage_lznd_cliente_posts_columns',       array( __CLASS__, 'cliente_cols' ) );
		add_action( 'manage_lznd_cliente_posts_custom_column', array( __CLASS__, 'cliente_col_content' ), 10, 2 );

		// Proyecto columns
		add_filter( 'manage_lznd_proyecto_posts_columns',       array( __CLASS__, 'proyecto_cols' ) );
		add_action( 'manage_lznd_proyecto_posts_custom_column', array( __CLASS__, 'proyecto_col_content' ), 10, 2 );

		// Actuación columns
		add_filter( 'manage_lznd_actuacion_posts_columns',       array( __CLASS__, 'actuacion_cols' ) );
		add_action( 'manage_lznd_actuacion_posts_custom_column', array( __CLASS__, 'actuacion_col_content' ), 10, 2 );

		// Actuación filters
		add_action( 'restrict_manage_posts', array( __CLASS__, 'add_actuacion_filters' ) );
		add_action( 'pre_get_posts',         array( __CLASS__, 'apply_actuacion_filters' ) );

		// Project detail page registration
		add_action( 'admin_menu', array( __CLASS__, 'add_project_detail_page' ) );

		// Client dashboard page registration
		add_action( 'admin_menu', array( __CLASS__, 'add_client_dashboard_page' ) );

		// "Ver panel" row action on cliente list
		add_filter( 'post_row_actions', array( __CLASS__, 'cliente_row_actions' ), 10, 2 );

		// Enqueue Chart.js on project detail and client dashboard pages
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_project_detail' ) );

		// AJAX calendar navigation (project)
		add_action( 'wp_ajax_lznd_calendar_nav', array( __CLASS__, 'ajax_calendar' ) );

		// AJAX project stats chart
		add_action( 'wp_ajax_lznd_get_project_stats', array( __CLASS__, 'ajax_project_stats' ) );

		// AJAX client stats chart
		add_action( 'wp_ajax_lznd_get_client_stats', array( __CLASS__, 'ajax_client_stats' ) );
	}

	// ─── Cliente Columns ─────────────────────────────────────────────────────

	public static function cliente_cols( $cols ) {
		$new = array();
		$new['cb']              = $cols['cb'];
		$new['title']           = __( 'Nombre', 'lezned-maintenance' );
		$new['lznd_telefono']   = __( 'Teléfono', 'lezned-maintenance' );
		$new['lznd_email']      = __( 'Email', 'lezned-maintenance' );
		$new['lznd_proyectos']  = __( 'Proyectos', 'lezned-maintenance' );
		$new['date']            = $cols['date'];
		return $new;
	}

	public static function cliente_col_content( $col, $post_id ) {
		switch ( $col ) {
			case 'lznd_telefono':
				$tel = get_post_meta( $post_id, '_lznd_telefono', true );
				if ( $tel ) {
					echo '<a href="tel:' . esc_attr( $tel ) . '">' . esc_html( $tel ) . '</a>';
				} else {
					echo '<span class="lznd-empty">—</span>';
				}
				break;

			case 'lznd_email':
				$email = get_post_meta( $post_id, '_lznd_email', true );
				if ( $email ) {
					echo '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>';
				} else {
					echo '<span class="lznd-empty">—</span>';
				}
				break;

			case 'lznd_proyectos':
				$proyectos = get_posts( array(
					'post_type'      => 'lznd_proyecto',
					'posts_per_page' => -1,
					'post_status'    => 'publish',
					'meta_key'       => '_lznd_cliente_id',
					'meta_value'     => $post_id,
					'fields'         => 'ids',
				) );
				$count = count( $proyectos );
				if ( $count > 0 ) {
					$url = admin_url( 'edit.php?post_type=lznd_proyecto&lznd_cliente_id=' . $post_id );
					echo '<a href="' . esc_url( $url ) . '">' . sprintf(
						/* translators: %d: number of projects */
						_n( '%d proyecto', '%d proyectos', $count, 'lezned-maintenance' ),
						$count
					) . '</a>';
				} else {
					echo '<span class="lznd-empty">0</span>';
				}
				break;
		}
	}

	// ─── Proyecto Columns ────────────────────────────────────────────────────

	public static function proyecto_cols( $cols ) {
		$new = array();
		$new['cb']                 = $cols['cb'];
		$new['title']              = __( 'Nombre', 'lezned-maintenance' );
		$new['lznd_cliente']       = __( 'Cliente', 'lezned-maintenance' );
		$new['lznd_recurrencia']   = __( 'Recurrencia', 'lezned-maintenance' );
		$new['lznd_proxima']       = __( 'Próx. fecha', 'lezned-maintenance' );
		$new['lznd_actuaciones']   = __( 'Actuaciones', 'lezned-maintenance' );
		$new['date']               = $cols['date'];
		return $new;
	}

	public static function proyecto_col_content( $col, $post_id ) {
		switch ( $col ) {
			case 'lznd_cliente':
				$cliente_id = get_post_meta( $post_id, '_lznd_cliente_id', true );
				if ( $cliente_id ) {
					$cliente = get_post( $cliente_id );
					if ( $cliente ) {
						$edit_url = get_edit_post_link( $cliente_id );
						echo '<a href="' . esc_url( $edit_url ) . '">' . esc_html( $cliente->post_title ) . '</a>';
					} else {
						echo '<span class="lznd-empty">—</span>';
					}
				} else {
					echo '<span class="lznd-empty">—</span>';
				}
				break;

			case 'lznd_recurrencia':
				$rec = get_post_meta( $post_id, '_lznd_recurrencia', true );
				$labels = array(
					'ninguna'   => __( 'Sin recurrencia', 'lezned-maintenance' ),
					'semestral' => __( 'Cada 6 meses',    'lezned-maintenance' ),
					'anual'     => __( 'Anual',            'lezned-maintenance' ),
				);
				$label = isset( $labels[ $rec ] ) ? $labels[ $rec ] : __( 'Sin recurrencia', 'lezned-maintenance' );
				$badge_class = ( 'ninguna' === $rec || ! $rec ) ? 'lznd-badge lznd-badge-gray' : 'lznd-badge lznd-badge-blue';
				echo '<span class="' . esc_attr( $badge_class ) . '">' . esc_html( $label ) . '</span>';
				break;

			case 'lznd_proxima':
				$fecha = get_post_meta( $post_id, '_lznd_fecha_proxima', true );
				if ( $fecha ) {
					$timestamp = strtotime( $fecha );
					$today     = strtotime( 'today' );
					$diff_days = ( $timestamp - $today ) / DAY_IN_SECONDS;
					$formatted = date_i18n( get_option( 'date_format' ), $timestamp );
					if ( $diff_days < 0 ) {
						echo '<span class="lznd-date lznd-date-overdue">' . esc_html( $formatted ) . '</span>';
					} elseif ( $diff_days <= 30 ) {
						echo '<span class="lznd-date lznd-date-near">' . esc_html( $formatted ) . '</span>';
					} else {
						echo '<span class="lznd-date">' . esc_html( $formatted ) . '</span>';
					}
				} else {
					echo '<span class="lznd-empty">—</span>';
				}
				break;

			case 'lznd_actuaciones':
				$acts = get_posts( array(
					'post_type'      => 'lznd_actuacion',
					'posts_per_page' => -1,
					'post_status'    => 'any',
					'meta_key'       => '_lznd_proyecto_id',
					'meta_value'     => $post_id,
					'fields'         => 'ids',
				) );
				$count = count( $acts );
				$detail_url = admin_url( 'admin.php?page=lznd-project-detail&id=' . $post_id );
				echo '<a href="' . esc_url( $detail_url ) . '">' . sprintf(
					/* translators: %d: number of actuaciones */
					_n( '%d actuación', '%d actuaciones', $count, 'lezned-maintenance' ),
					$count
				) . '</a>';
				break;
		}
	}

	// ─── Actuación Columns ───────────────────────────────────────────────────

	public static function actuacion_cols( $cols ) {
		$new = array();
		$new['cb']             = $cols['cb'];
		$new['title']          = __( 'Título', 'lezned-maintenance' );
		$new['lznd_proyecto']  = __( 'Proyecto', 'lezned-maintenance' );
		$new['lznd_tipo']      = __( 'Tipo', 'lezned-maintenance' );
		$new['lznd_estado']    = __( 'Estado', 'lezned-maintenance' );
		$new['lznd_tecnico']   = __( 'Técnico', 'lezned-maintenance' );
		$new['lznd_tareas']    = __( 'Progreso', 'lezned-maintenance' );
		$new['lznd_fecha']     = __( 'Fecha prog.', 'lezned-maintenance' );
		$new['date']           = $cols['date'];
		return $new;
	}

	public static function actuacion_col_content( $col, $post_id ) {
		switch ( $col ) {
			case 'lznd_proyecto':
				$proyecto_id = get_post_meta( $post_id, '_lznd_proyecto_id', true );
				if ( $proyecto_id ) {
					$proyecto = get_post( $proyecto_id );
					if ( $proyecto ) {
						$detail_url = admin_url( 'admin.php?page=lznd-project-detail&id=' . $proyecto_id );
						echo '<a href="' . esc_url( $detail_url ) . '">' . esc_html( $proyecto->post_title ) . '</a>';
					} else {
						echo '<span class="lznd-empty">—</span>';
					}
				} else {
					echo '<span class="lznd-empty">—</span>';
				}
				break;

			case 'lznd_tipo':
				$terms = wp_get_post_terms( $post_id, 'lznd_tipo' );
				if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
					$term_names = wp_list_pluck( $terms, 'name' );
					echo '<span class="lznd-badge lznd-badge-tipo">' . esc_html( implode( ', ', $term_names ) ) . '</span>';
				} else {
					echo '<span class="lznd-empty">—</span>';
				}
				break;

			case 'lznd_estado':
				$estado = get_post_meta( $post_id, '_lznd_estado', true );
				echo self::estado_badge( $estado );
				break;

			case 'lznd_tecnico':
				$tecnico_id = get_post_meta( $post_id, '_lznd_tecnico', true );
				if ( $tecnico_id ) {
					$user = get_userdata( $tecnico_id );
					if ( $user ) {
						echo esc_html( $user->display_name );
					} else {
						echo '<span class="lznd-empty">—</span>';
					}
				} else {
					echo '<span class="lznd-empty">—</span>';
				}
				break;

			case 'lznd_tareas':
				$checklist = get_post_meta( $post_id, '_lznd_tareas_checklist', true );
				if ( $checklist ) {
					$tasks = json_decode( $checklist, true );
					if ( is_array( $tasks ) ) {
						$total = count( $tasks );
						$done  = 0;
						foreach ( $tasks as $task ) {
							if ( ! empty( $task['done'] ) ) {
								$done++;
							}
						}
						$pct = $total > 0 ? round( ( $done / $total ) * 100 ) : 0;
						echo '<div class="lznd-progress-wrap" title="' . esc_attr( "$done/$total" ) . '">';
						echo '<div class="lznd-progress-bar"><div class="lznd-progress-fill" style="width:' . esc_attr( $pct ) . '%"></div></div>';
						echo '<span class="lznd-progress-label">' . esc_html( "$done/$total" ) . '</span>';
						echo '</div>';
					} else {
						echo '<span class="lznd-empty">—</span>';
					}
				} else {
					echo '<span class="lznd-empty">—</span>';
				}
				break;

			case 'lznd_fecha':
				$fecha = get_post_meta( $post_id, '_lznd_fecha_prog', true );
				if ( $fecha ) {
					$timestamp = strtotime( $fecha );
					$today     = strtotime( 'today' );
					$diff_days = ( $timestamp - $today ) / DAY_IN_SECONDS;
					$formatted = date_i18n( get_option( 'date_format' ), $timestamp );
					if ( $diff_days < 0 ) {
						echo '<span class="lznd-date lznd-date-overdue">' . esc_html( $formatted ) . '</span>';
					} elseif ( $diff_days <= 7 ) {
						echo '<span class="lznd-date lznd-date-near">' . esc_html( $formatted ) . '</span>';
					} else {
						echo '<span class="lznd-date">' . esc_html( $formatted ) . '</span>';
					}
				} else {
					echo '<span class="lznd-empty">—</span>';
				}
				break;
		}
	}

	// ─── Helper: Estado Badge ────────────────────────────────────────────────

	private static function estado_badge( $estado ) {
		$map = array(
			'pendiente'  => array( 'label' => '⏳ Pendiente',   'class' => 'lznd-badge-pendiente' ),
			'en_proceso' => array( 'label' => '🔧 En proceso',  'class' => 'lznd-badge-proceso' ),
			'completado' => array( 'label' => '✅ Completado',  'class' => 'lznd-badge-completado' ),
			'enviado'    => array( 'label' => '📤 Enviado',     'class' => 'lznd-badge-enviado' ),
			'cancelado'  => array( 'label' => '❌ Cancelado',   'class' => 'lznd-badge-cancelado' ),
		);

		if ( isset( $map[ $estado ] ) ) {
			return '<span class="lznd-badge ' . esc_attr( $map[ $estado ]['class'] ) . '">' . esc_html( $map[ $estado ]['label'] ) . '</span>';
		}

		return '<span class="lznd-badge lznd-badge-gray">' . esc_html( $estado ) . '</span>';
	}

	// ─── Actuación Filters ───────────────────────────────────────────────────

	public static function add_actuacion_filters( $post_type ) {
		if ( 'lznd_actuacion' !== $post_type ) {
			return;
		}

		// Proyecto filter
		$proyectos = get_posts( array(
			'post_type'      => 'lznd_proyecto',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );

		$current_proyecto = isset( $_GET['lznd_filter_proyecto'] ) ? absint( $_GET['lznd_filter_proyecto'] ) : 0;

		echo '<select name="lznd_filter_proyecto">';
		echo '<option value="">' . esc_html__( 'Todos los proyectos', 'lezned-maintenance' ) . '</option>';
		foreach ( $proyectos as $p ) {
			echo '<option value="' . esc_attr( $p->ID ) . '"' . selected( $current_proyecto, $p->ID, false ) . '>' . esc_html( $p->post_title ) . '</option>';
		}
		echo '</select>';

		// Tipo (taxonomy) filter
		$tipos = get_terms( array(
			'taxonomy'   => 'lznd_tipo',
			'hide_empty' => false,
		) );

		$current_tipo = isset( $_GET['lznd_filter_tipo'] ) ? absint( $_GET['lznd_filter_tipo'] ) : 0;

		echo '<select name="lznd_filter_tipo">';
		echo '<option value="">' . esc_html__( 'Todos los tipos', 'lezned-maintenance' ) . '</option>';
		if ( ! is_wp_error( $tipos ) ) {
			foreach ( $tipos as $term ) {
				echo '<option value="' . esc_attr( $term->term_id ) . '"' . selected( $current_tipo, $term->term_id, false ) . '>' . esc_html( $term->name ) . '</option>';
			}
		}
		echo '</select>';

		// Estado filter — color-coded pill buttons
		$estados = array(
			'pendiente'  => array( 'label' => __( 'Pendiente',  'lezned-maintenance' ), 'color' => '#fd7e14' ),
			'en_proceso' => array( 'label' => __( 'En proceso', 'lezned-maintenance' ), 'color' => '#1a73e8' ),
			'completado' => array( 'label' => __( 'Completado', 'lezned-maintenance' ), 'color' => '#28a745' ),
			'enviado'    => array( 'label' => __( 'Enviado',    'lezned-maintenance' ), 'color' => '#6f42c1' ),
			'cancelado'  => array( 'label' => __( 'Cancelado',  'lezned-maintenance' ), 'color' => '#6c757d' ),
		);

		$current_estado = isset( $_GET['lznd_filter_estado'] ) ? sanitize_key( $_GET['lznd_filter_estado'] ) : '';

		// Build base URL preserving other active filters
		$preserve = array( 'post_type' => 'lznd_actuacion' );
		if ( ! empty( $_GET['lznd_filter_proyecto'] ) ) {
			$preserve['lznd_filter_proyecto'] = absint( $_GET['lznd_filter_proyecto'] );
		}
		if ( ! empty( $_GET['lznd_filter_tipo'] ) ) {
			$preserve['lznd_filter_tipo'] = absint( $_GET['lznd_filter_tipo'] );
		}
		$base_url = add_query_arg( $preserve, admin_url( 'edit.php' ) );

		echo '<span class="lznd-estado-pills">';

		// "Todos" pill
		$is_all = empty( $current_estado );
		echo '<a href="' . esc_url( $base_url ) . '" class="lznd-pill' . ( $is_all ? ' lznd-pill--active' : '' ) . '" style="' . ( $is_all ? 'background:#333;color:#fff;' : 'background:#eee;color:#333;' ) . '">';
		echo esc_html__( 'Todos', 'lezned-maintenance' );
		echo '</a>';

		foreach ( $estados as $val => $info ) {
			$url       = add_query_arg( 'lznd_filter_estado', $val, $base_url );
			$is_active = ( $current_estado === $val );
			echo '<a href="' . esc_url( $url ) . '" class="lznd-pill lznd-pill--' . esc_attr( $val ) . ( $is_active ? ' lznd-pill--active' : '' ) . '" style="background:' . ( $is_active ? esc_attr( $info['color'] ) : '#eee' ) . ';color:' . ( $is_active ? '#fff' : esc_attr( $info['color'] ) ) . ';border-color:' . esc_attr( $info['color'] ) . ';">';
			echo esc_html( $info['label'] );
			echo '</a>';
		}

		echo '</span>';
	}

	public static function apply_actuacion_filters( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( 'lznd_actuacion' !== $query->get( 'post_type' ) ) {
			return;
		}

		$meta_query = array();
		$tax_query  = array();

		// Proyecto filter
		if ( ! empty( $_GET['lznd_filter_proyecto'] ) ) {
			$meta_query[] = array(
				'key'   => '_lznd_proyecto_id',
				'value' => absint( $_GET['lznd_filter_proyecto'] ),
				'type'  => 'NUMERIC',
			);
		}

		// Estado filter
		if ( ! empty( $_GET['lznd_filter_estado'] ) ) {
			$valid = array( 'pendiente', 'en_proceso', 'completado', 'enviado', 'cancelado' );
			$estado = sanitize_key( $_GET['lznd_filter_estado'] );
			if ( in_array( $estado, $valid, true ) ) {
				$meta_query[] = array(
					'key'   => '_lznd_estado',
					'value' => $estado,
				);
			}
		}

		// Tipo (taxonomy) filter
		if ( ! empty( $_GET['lznd_filter_tipo'] ) ) {
			$tax_query[] = array(
				'taxonomy' => 'lznd_tipo',
				'field'    => 'term_id',
				'terms'    => absint( $_GET['lznd_filter_tipo'] ),
			);
		}

		if ( ! empty( $meta_query ) ) {
			$query->set( 'meta_query', $meta_query );
		}

		if ( ! empty( $tax_query ) ) {
			$query->set( 'tax_query', $tax_query );
		}
	}

	// ─── Enqueue Chart.js on project detail and client dashboard pages ──────

	public static function enqueue_project_detail( $hook ) {
		if ( false === strpos( $hook, 'lznd-project-detail' )
			&& false === strpos( $hook, 'lznd-client-dashboard' ) ) {
			return;
		}
		wp_enqueue_script(
			'lznd-chartjs',
			'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
			array(),
			'4.4.0',
			true
		);
	}

	// ─── Cliente row action: Ver panel ───────────────────────────────────────

	public static function cliente_row_actions( $actions, $post ) {
		if ( 'lznd_cliente' !== $post->post_type ) {
			return $actions;
		}
		$url = admin_url( 'admin.php?page=lznd-client-dashboard&id=' . $post->ID );
		$actions['lznd_panel'] = '<a href="' . esc_url( $url ) . '" style="color:#1875B8;font-weight:600;">'
			. esc_html__( 'Ver panel', 'lezned-maintenance' )
			. '</a>';
		return $actions;
	}

	// ─── Register Client Dashboard Page ──────────────────────────────────────

	public static function add_client_dashboard_page() {
		add_submenu_page(
			'',
			__( 'Panel del cliente', 'lezned-maintenance' ),
			__( 'Panel del cliente', 'lezned-maintenance' ),
			'edit_posts',
			'lznd-client-dashboard',
			array( __CLASS__, 'render_client_dashboard' )
		);
	}

	// ─── Render Client Dashboard Page ────────────────────────────────────────

	public static function render_client_dashboard() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'No tienes permiso para acceder a esta página.', 'lezned-maintenance' ) );
		}

		$cliente_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		if ( ! $cliente_id ) {
			echo '<div class="wrap"><p>' . esc_html__( 'ID de cliente no válido.', 'lezned-maintenance' ) . '</p></div>';
			return;
		}

		$cliente = get_post( $cliente_id );
		if ( ! $cliente || 'lznd_cliente' !== $cliente->post_type ) {
			echo '<div class="wrap"><p>' . esc_html__( 'Cliente no encontrado.', 'lezned-maintenance' ) . '</p></div>';
			return;
		}

		$back_url = admin_url( 'edit.php?post_type=lznd_cliente' );
		$today    = current_time( 'Y-m-d' );
		$year     = isset( $_GET['cal_year'] )  ? absint( $_GET['cal_year'] )  : (int) current_time( 'Y' );
		$month    = isset( $_GET['cal_month'] ) ? absint( $_GET['cal_month'] ) : (int) current_time( 'm' );

		// All projects for this client
		$proyectos = get_posts( array(
			'post_type'      => 'lznd_proyecto',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'orderby'        => 'title',
			'order'          => 'ASC',
			'meta_query'     => array( array( 'key' => '_lznd_cliente_id', 'value' => $cliente_id ) ),
		) );
		$proyecto_ids = wp_list_pluck( $proyectos, 'ID' );

		// Base meta query for all actuaciones of this client
		if ( empty( $proyecto_ids ) ) {
			$base_cond = array( 'key' => '_lznd_proyecto_id', 'value' => 0, 'type' => 'NUMERIC' );
		} else {
			$base_cond = array( 'key' => '_lznd_proyecto_id', 'value' => $proyecto_ids, 'compare' => 'IN' );
		}

		// Total actuaciones
		$all_ids    = get_posts( array(
			'post_type' => 'lznd_actuacion', 'post_status' => 'publish',
			'posts_per_page' => -1, 'fields' => 'ids',
			'meta_query' => array( $base_cond ),
		) );
		$total_acts = count( $all_ids );

		// Completadas (enviado / cerrado / completado)
		$past = get_posts( array(
			'post_type' => 'lznd_actuacion', 'post_status' => 'publish',
			'posts_per_page' => 10,
			'orderby' => 'meta_value', 'meta_key' => '_lznd_fecha_prog', 'order' => 'DESC',
			'meta_query' => array(
				'relation' => 'AND', $base_cond,
				array( 'key' => '_lznd_estado', 'value' => array( 'completado', 'enviado', 'cerrado' ), 'compare' => 'IN' ),
			),
		) );

		// Pendientes
		$pending = get_posts( array(
			'post_type' => 'lznd_actuacion', 'post_status' => 'publish',
			'posts_per_page' => -1,
			'orderby' => 'meta_value', 'meta_key' => '_lznd_fecha_prog', 'order' => 'ASC',
			'meta_query' => array(
				'relation' => 'AND', $base_cond,
				array( 'key' => '_lznd_estado', 'value' => array( 'pendiente', 'en_proceso', 'en_curso' ), 'compare' => 'IN' ),
			),
		) );

		// Próximas
		$upcoming = get_posts( array(
			'post_type' => 'lznd_actuacion', 'post_status' => 'publish',
			'posts_per_page' => -1,
			'orderby' => 'meta_value', 'meta_key' => '_lznd_fecha_prog', 'order' => 'ASC',
			'meta_query' => array(
				'relation' => 'AND', $base_cond,
				array( 'key' => '_lznd_fecha_prog', 'value' => $today, 'compare' => '>=', 'type' => 'DATE' ),
				array( 'key' => '_lznd_estado', 'value' => array( 'completado', 'enviado', 'cerrado', 'cancelado' ), 'compare' => 'NOT IN' ),
			),
		) );

		$completadas_n = count( $past );
		$pendientes_n  = count( $pending );
		$proximas_n    = count( $upcoming );

		$telefono = get_post_meta( $cliente_id, '_lznd_telefono', true );
		$email    = get_post_meta( $cliente_id, '_lznd_email', true );
		$ajax_url = admin_url( 'admin-ajax.php' );
		$nonce    = wp_create_nonce( 'lznd_admin' );
		?>
		<div class="wrap lznd-client-dashboard">

			<div class="lznd-detail-header">
				<div class="lznd-detail-header-left">
					<a href="<?php echo esc_url( $back_url ); ?>" class="lznd-back-btn">&#8592; <?php esc_html_e( 'Volver a clientes', 'lezned-maintenance' ); ?></a>
					<h1 class="lznd-detail-title">
						<span class="dashicons dashicons-admin-users" style="font-size:22px;width:22px;height:22px;vertical-align:middle;margin-right:6px;color:#1875B8;"></span>
						<?php echo esc_html( $cliente->post_title ); ?>
					</h1>
					<?php if ( $telefono || $email ) : ?>
						<p class="lznd-detail-meta">
							<?php if ( $telefono ) : ?>
								<span class="dashicons dashicons-phone" style="font-size:13px;width:13px;height:13px;vertical-align:middle;"></span>
								<a href="tel:<?php echo esc_attr( $telefono ); ?>"><?php echo esc_html( $telefono ); ?></a>
								&nbsp;&nbsp;
							<?php endif; ?>
							<?php if ( $email ) : ?>
								<span class="dashicons dashicons-email-alt" style="font-size:13px;width:13px;height:13px;vertical-align:middle;"></span>
								<a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a>
							<?php endif; ?>
						</p>
					<?php endif; ?>
				</div>
				<div class="lznd-detail-header-right">
					<a href="<?php echo esc_url( get_edit_post_link( $cliente_id ) ); ?>" class="button"><?php esc_html_e( 'Editar cliente', 'lezned-maintenance' ); ?></a>
					<?php if ( ! empty( $proyecto_ids ) ) : ?>
						<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=lznd_proyecto&lznd_cliente_id=' . $cliente_id ) ); ?>" class="button"><?php esc_html_e( 'Ver proyectos', 'lezned-maintenance' ); ?></a>
					<?php endif; ?>
				</div>
			</div>

			<!-- Stats cards -->
			<div class="lznd-pd-stats">
				<div class="lznd-pd-stat lznd-pd-stat--total">
					<div class="lznd-pd-stat__icon dashicons dashicons-hammer"></div>
					<div class="lznd-pd-stat__body">
						<div class="lznd-pd-stat__value"><?php echo (int) $total_acts; ?></div>
						<div class="lznd-pd-stat__label"><?php esc_html_e( 'Total actuaciones', 'lezned-maintenance' ); ?></div>
					</div>
				</div>
				<div class="lznd-pd-stat lznd-pd-stat--completadas">
					<div class="lznd-pd-stat__icon dashicons dashicons-yes-alt"></div>
					<div class="lznd-pd-stat__body">
						<div class="lznd-pd-stat__value"><?php echo (int) $completadas_n; ?></div>
						<div class="lznd-pd-stat__label"><?php esc_html_e( 'Completadas', 'lezned-maintenance' ); ?></div>
					</div>
				</div>
				<div class="lznd-pd-stat lznd-pd-stat--pendientes">
					<div class="lznd-pd-stat__icon dashicons dashicons-clock"></div>
					<div class="lznd-pd-stat__body">
						<div class="lznd-pd-stat__value"><?php echo (int) $pendientes_n; ?></div>
						<div class="lznd-pd-stat__label"><?php esc_html_e( 'Pendientes', 'lezned-maintenance' ); ?></div>
					</div>
				</div>
				<div class="lznd-pd-stat lznd-pd-stat--proximas">
					<div class="lznd-pd-stat__icon dashicons dashicons-calendar-alt"></div>
					<div class="lznd-pd-stat__body">
						<div class="lznd-pd-stat__value"><?php echo (int) $proximas_n; ?></div>
						<div class="lznd-pd-stat__label"><?php esc_html_e( 'Próximas', 'lezned-maintenance' ); ?></div>
					</div>
				</div>
			</div>

			<!-- Calendar + chart -->
			<div class="lznd-cd-two-col">
				<div class="lznd-cd-col-left">
					<h2 style="font-size:15px;margin:0 0 12px;font-weight:700;"><?php esc_html_e( 'Calendario de actuaciones', 'lezned-maintenance' ); ?></h2>
					<?php echo self::render_client_calendar( $cliente_id, $proyecto_ids, $year, $month ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
				<div class="lznd-cd-col-right">
					<div class="lznd-pd-chart-card">
						<h2 class="lznd-pd-chart-title"><?php esc_html_e( 'Estadísticas de trabajos', 'lezned-maintenance' ); ?></h2>
						<div class="lznd-pd-chart-wrap">
							<canvas id="lznd-cd-bar-chart"></canvas>
						</div>
					</div>
				</div>
			</div>

			<!-- Projects -->
			<?php if ( ! empty( $proyectos ) ) : ?>
			<div class="lznd-cd-projects-section">
				<h2><?php esc_html_e( 'Proyectos', 'lezned-maintenance' ); ?></h2>
				<div class="lznd-cd-projects-grid">
					<?php foreach ( $proyectos as $proyecto ) : ?>
						<?php
						$proj_act_ids = get_posts( array(
							'post_type' => 'lznd_actuacion', 'post_status' => 'publish',
							'posts_per_page' => -1, 'fields' => 'ids',
							'meta_query' => array( array( 'key' => '_lznd_proyecto_id', 'value' => $proyecto->ID ) ),
						) );
						$proj_done = 0;
						$proj_pend = 0;
						foreach ( $proj_act_ids as $aid ) {
							$es = get_post_meta( $aid, '_lznd_estado', true );
							if ( in_array( $es, array( 'completado', 'enviado', 'cerrado' ), true ) ) {
								$proj_done++;
							} elseif ( 'cancelado' !== $es ) {
								$proj_pend++;
							}
						}
						$detail_url = admin_url( 'admin.php?page=lznd-project-detail&id=' . $proyecto->ID );
						$fecha_prox = get_post_meta( $proyecto->ID, '_lznd_fecha_proxima', true );
						?>
						<div class="lznd-cd-project-card">
							<div class="lznd-cd-project-name">
								<a href="<?php echo esc_url( $detail_url ); ?>"><?php echo esc_html( $proyecto->post_title ); ?></a>
							</div>
							<div class="lznd-cd-project-meta">
								<span class="lznd-sbadge lznd-sbadge--green"><?php echo (int) $proj_done; ?> <?php esc_html_e( 'completadas', 'lezned-maintenance' ); ?></span>
								<span class="lznd-sbadge lznd-sbadge--orange"><?php echo (int) $proj_pend; ?> <?php esc_html_e( 'pendientes', 'lezned-maintenance' ); ?></span>
							</div>
							<?php if ( $fecha_prox ) : ?>
								<div class="lznd-cd-project-fecha">
									<?php echo esc_html__( 'Próxima:', 'lezned-maintenance' ) . ' ' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $fecha_prox ) ) ); ?>
								</div>
							<?php endif; ?>
							<a href="<?php echo esc_url( $detail_url ); ?>" class="button button-small"><?php esc_html_e( 'Ver detalle', 'lezned-maintenance' ); ?></a>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
			<?php endif; ?>

		</div><!-- .lznd-client-dashboard -->

		<script>
		(function($){
			var AJAX      = <?php echo wp_json_encode( $ajax_url ); ?>;
			var NONCE     = <?php echo wp_json_encode( $nonce ); ?>;
			var CLIENT_ID = <?php echo (int) $cliente_id; ?>;
			var barChart  = null;

			$(document).ready(function(){
				$.post(AJAX, {
					action:     'lznd_get_client_stats',
					nonce:      NONCE,
					cliente_id: CLIENT_ID,
				}, function(res){
					if (!res || !res.success) return;
					var d   = res.data;
					var ctx = document.getElementById('lznd-cd-bar-chart');
					if (!ctx) return;
					if (barChart) barChart.destroy();
					barChart = new Chart(ctx, {
						type: 'bar',
						data: {
							labels: d.labels,
							datasets: [
								{ label: 'Completadas', data: d.completadas, backgroundColor: '#22c55e', borderRadius: 4 },
								{ label: 'Pendientes',  data: d.pendientes,  backgroundColor: '#f59e0b', borderRadius: 4 },
							]
						},
						options: {
							responsive: true,
							maintainAspectRatio: false,
							plugins: { legend: { position: 'bottom' } },
							scales: {
								x: { stacked: false },
								y: { beginAtZero: true, ticks: { stepSize: 1 } }
							}
						}
					});
				});
			});
		})(jQuery);
		</script>
		<?php
	}

	// ─── Render Client Calendar ───────────────────────────────────────────────

	public static function render_client_calendar( $cliente_id, $proyecto_ids, $year, $month ) {
		if ( $month < 1 )  { $month = 12; $year--; }
		if ( $month > 12 ) { $month = 1;  $year++; }

		$days_in_month = (int) date( 't', mktime( 0, 0, 0, $month, 1, $year ) );
		$first_day_dow = (int) date( 'N', mktime( 0, 0, 0, $month, 1, $year ) );

		$month_start = sprintf( '%04d-%02d-01', $year, $month );
		$month_end   = sprintf( '%04d-%02d-%02d', $year, $month, $days_in_month );

		// Fetch actuaciones across all projects of this client in this month
		$acts = array();
		if ( ! empty( $proyecto_ids ) ) {
			$acts = get_posts( array(
				'post_type'      => 'lznd_actuacion',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'meta_query'     => array(
					'relation' => 'AND',
					array( 'key' => '_lznd_proyecto_id', 'value' => $proyecto_ids, 'compare' => 'IN' ),
					array( 'key' => '_lznd_fecha_prog', 'value' => array( $month_start, $month_end ), 'compare' => 'BETWEEN', 'type' => 'DATE' ),
				),
			) );
		}

		$acts_by_day = array();
		foreach ( $acts as $act ) {
			$fecha = get_post_meta( $act->ID, '_lznd_fecha_prog', true );
			if ( $fecha ) {
				$day = (int) date( 'j', strtotime( $fecha ) );
				if ( ! isset( $acts_by_day[ $day ] ) ) {
					$acts_by_day[ $day ] = array();
				}
				$acts_by_day[ $day ][] = $act;
			}
		}

		$prev_month = $month - 1; $prev_year = $year;
		if ( $prev_month < 1 )  { $prev_month = 12; $prev_year--; }
		$next_month = $month + 1; $next_year = $year;
		if ( $next_month > 12 ) { $next_month = 1;  $next_year++; }

		$base_url   = admin_url( 'admin.php?page=lznd-client-dashboard&id=' . $cliente_id );
		$prev_url   = add_query_arg( array( 'cal_year' => $prev_year, 'cal_month' => $prev_month ), $base_url );
		$next_url   = add_query_arg( array( 'cal_year' => $next_year, 'cal_month' => $next_month ), $base_url );
		$month_name = date_i18n( 'F Y', mktime( 0, 0, 0, $month, 1, $year ) );

		$today_day   = (int) current_time( 'j' );
		$today_month = (int) current_time( 'm' );
		$today_year  = (int) current_time( 'Y' );

		$day_names = array(
			__( 'Lun', 'lezned-maintenance' ), __( 'Mar', 'lezned-maintenance' ),
			__( 'Mié', 'lezned-maintenance' ), __( 'Jue', 'lezned-maintenance' ),
			__( 'Vie', 'lezned-maintenance' ), __( 'Sáb', 'lezned-maintenance' ),
			__( 'Dom', 'lezned-maintenance' ),
		);

		ob_start();
		?>
		<div class="lznd-calendar">
			<div class="lznd-calendar-nav">
				<a href="<?php echo esc_url( $prev_url ); ?>" class="button button-small" data-year="<?php echo esc_attr( $prev_year ); ?>" data-month="<?php echo esc_attr( $prev_month ); ?>">&#8592;</a>
				<span class="lznd-cal-month-title"><?php echo esc_html( ucfirst( $month_name ) ); ?></span>
				<a href="<?php echo esc_url( $next_url ); ?>" class="button button-small" data-year="<?php echo esc_attr( $next_year ); ?>" data-month="<?php echo esc_attr( $next_month ); ?>">&#8594;</a>
			</div>
			<table class="lznd-calendar-table">
				<thead><tr>
					<?php foreach ( $day_names as $dn ) : ?><th><?php echo esc_html( $dn ); ?></th><?php endforeach; ?>
				</tr></thead>
				<tbody>
				<?php
				$total_cells = $days_in_month + ( $first_day_dow - 1 );
				$rows        = (int) ceil( $total_cells / 7 );
				for ( $row = 0; $row < $rows; $row++ ) :
				?>
					<tr>
					<?php for ( $dow = 1; $dow <= 7; $dow++ ) : ?>
						<?php
						$cell_index = $row * 7 + $dow;
						$is_empty   = $cell_index < $first_day_dow;
						$day_num    = $cell_index - $first_day_dow + 1;
						$is_over    = $day_num > $days_in_month;
						$is_today   = ( $year === $today_year && $month === $today_month && $day_num === $today_day );

						$cell_class = 'lznd-cal-cell';
						if ( $is_empty || $is_over ) { $cell_class .= ' lznd-cal-empty'; }
						if ( $is_today ) { $cell_class .= ' lznd-cal-today'; }
						if ( ! $is_empty && ! $is_over && isset( $acts_by_day[ $day_num ] ) ) { $cell_class .= ' lznd-cal-has-acts'; }
						?>
						<td class="<?php echo esc_attr( $cell_class ); ?>">
						<?php if ( ! $is_empty && ! $is_over ) : ?>
							<span class="lznd-cal-day-num"><?php echo (int) $day_num; ?></span>
							<?php if ( isset( $acts_by_day[ $day_num ] ) ) : ?>
								<div class="lznd-cal-dots">
									<?php foreach ( $acts_by_day[ $day_num ] as $dot_act ) : ?>
										<?php
										$dot_estado = get_post_meta( $dot_act->ID, '_lznd_estado', true );
										$dot_class  = 'lznd-cal-dot lznd-dot-' . ( $dot_estado ? esc_attr( $dot_estado ) : 'pendiente' );
										?>
										<a href="<?php echo esc_url( get_edit_post_link( $dot_act->ID ) ); ?>" title="<?php echo esc_attr( $dot_act->post_title ); ?>" class="<?php echo esc_attr( $dot_class ); ?>"></a>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
						<?php endif; ?>
						</td>
					<?php endfor; ?>
					</tr>
				<?php endfor; ?>
				</tbody>
			</table>
			<?php if ( ! empty( $acts ) ) : ?>
			<div class="lznd-cal-legend">
				<span class="lznd-cal-dot lznd-dot-pendiente"></span> <?php esc_html_e( 'Pendiente', 'lezned-maintenance' ); ?>
				<span class="lznd-cal-dot lznd-dot-en_proceso"></span> <?php esc_html_e( 'En proceso', 'lezned-maintenance' ); ?>
				<span class="lznd-cal-dot lznd-dot-completado"></span> <?php esc_html_e( 'Completado', 'lezned-maintenance' ); ?>
				<span class="lznd-cal-dot lznd-dot-enviado"></span> <?php esc_html_e( 'Enviado', 'lezned-maintenance' ); ?>
			</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	// ─── Register Project Detail Admin Page ──────────────────────────────────

	public static function add_project_detail_page() {
		// Register as hidden submenu (parent_slug = '' = not shown in any menu)
		add_submenu_page(
			'',
			__( 'Detalle del Proyecto', 'lezned-maintenance' ),
			__( 'Detalle del Proyecto', 'lezned-maintenance' ),
			'edit_posts',
			'lznd-project-detail',
			array( __CLASS__, 'render_project_detail' )
		);
	}

	// ─── Render Project Detail Page ──────────────────────────────────────────

	public static function render_project_detail() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'No tienes permiso para acceder a esta página.', 'lezned-maintenance' ) );
		}

		$proyecto_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

		if ( ! $proyecto_id ) {
			echo '<div class="wrap"><p>' . esc_html__( 'ID de proyecto no válido.', 'lezned-maintenance' ) . '</p></div>';
			return;
		}

		$proyecto = get_post( $proyecto_id );
		if ( ! $proyecto || 'lznd_proyecto' !== $proyecto->post_type ) {
			echo '<div class="wrap"><p>' . esc_html__( 'Proyecto no encontrado.', 'lezned-maintenance' ) . '</p></div>';
			return;
		}

		$back_url    = admin_url( 'edit.php?post_type=lznd_proyecto' );
		$edit_url    = get_edit_post_link( $proyecto_id );
		$new_act_url = admin_url( 'post-new.php?post_type=lznd_actuacion&_lznd_proyecto_id=' . $proyecto_id );

		$today     = current_time( 'Y-m-d' );
		$year      = isset( $_GET['cal_year'] )  ? absint( $_GET['cal_year'] )  : (int) current_time( 'Y' );
		$month     = isset( $_GET['cal_month'] ) ? absint( $_GET['cal_month'] ) : (int) current_time( 'm' );

		// Clamp month
		if ( $month < 1 )  { $month = 12; $year--; }
		if ( $month > 12 ) { $month = 1;  $year++; }

		// ── Fetch actuaciones ───────────────────────────────────────────────

		$args_base = array(
			'post_type'      => 'lznd_actuacion',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'meta_query'     => array(
				array(
					'key'   => '_lznd_proyecto_id',
					'value' => $proyecto_id,
					'type'  => 'NUMERIC',
				),
			),
		);

		// Past: completed or enviado, last 10
		$past = get_posts( array_merge( $args_base, array(
			'posts_per_page' => 10,
			'meta_query'     => array(
				array(
					'key'   => '_lznd_proyecto_id',
					'value' => $proyecto_id,
					'type'  => 'NUMERIC',
				),
				array(
					'key'     => '_lznd_estado',
					'value'   => array( 'completado', 'enviado' ),
					'compare' => 'IN',
				),
			),
			'orderby'        => 'meta_value',
			'meta_key'       => '_lznd_fecha_fin',
			'order'          => 'DESC',
		) ) );

		// Pending: pendiente or en_proceso
		$pending = get_posts( array_merge( $args_base, array(
			'meta_query' => array(
				array(
					'key'   => '_lznd_proyecto_id',
					'value' => $proyecto_id,
					'type'  => 'NUMERIC',
				),
				array(
					'key'     => '_lznd_estado',
					'value'   => array( 'pendiente', 'en_proceso' ),
					'compare' => 'IN',
				),
			),
			'orderby'    => 'meta_value',
			'meta_key'   => '_lznd_fecha_prog',
			'order'      => 'ASC',
		) ) );

		// Upcoming: fecha_prog >= today (regardless of status, not past/pending)
		$upcoming = get_posts( array_merge( $args_base, array(
			'meta_query' => array(
				'relation' => 'AND',
				array(
					'key'   => '_lznd_proyecto_id',
					'value' => $proyecto_id,
					'type'  => 'NUMERIC',
				),
				array(
					'key'     => '_lznd_fecha_prog',
					'value'   => $today,
					'compare' => '>=',
					'type'    => 'DATE',
				),
				array(
					'key'     => '_lznd_estado',
					'value'   => array( 'completado', 'enviado', 'cancelado' ),
					'compare' => 'NOT IN',
				),
			),
			'orderby'    => 'meta_value',
			'meta_key'   => '_lznd_fecha_prog',
			'order'      => 'ASC',
		) ) );

		// ── Client info ─────────────────────────────────────────────────────
		$cliente_id   = get_post_meta( $proyecto_id, '_lznd_cliente_id', true );
		$cliente_name = '';
		if ( $cliente_id ) {
			$cliente = get_post( $cliente_id );
			if ( $cliente ) {
				$cliente_name = $cliente->post_title;
			}
		}

		$recurrencia = get_post_meta( $proyecto_id, '_lznd_recurrencia', true );
		$fecha_prox  = get_post_meta( $proyecto_id, '_lznd_fecha_proxima', true );

		// ── Stats counts ────────────────────────────────────────────────────
		$all_acts = get_posts( array_merge( $args_base, array(
			'posts_per_page' => -1,
			'fields'         => 'ids',
		) ) );
		$total_acts    = count( $all_acts );
		$completadas_n = count( $past );
		$pendientes_n  = count( $pending );
		$proximas_n    = count( $upcoming );

		// ── Render ──────────────────────────────────────────────────────────
		$ajax_url = admin_url( 'admin-ajax.php' );
		$nonce    = wp_create_nonce( 'lznd_admin' );
		?>
		<div class="wrap lznd-project-detail">

			<div class="lznd-detail-header">
				<div class="lznd-detail-header-left">
					<a href="<?php echo esc_url( $back_url ); ?>" class="lznd-back-btn">&#8592; <?php esc_html_e( 'Volver a proyectos', 'lezned-maintenance' ); ?></a>
					<h1 class="lznd-detail-title"><?php echo esc_html( $proyecto->post_title ); ?></h1>
					<?php if ( $cliente_name ) : ?>
						<p class="lznd-detail-meta"><?php echo esc_html__( 'Cliente:', 'lezned-maintenance' ) . ' ' . esc_html( $cliente_name ); ?></p>
					<?php endif; ?>
					<?php if ( $recurrencia && 'ninguna' !== $recurrencia ) : ?>
						<?php
						$rec_labels = array(
							'semestral' => __( 'Cada 6 meses', 'lezned-maintenance' ),
							'anual'     => __( 'Anual', 'lezned-maintenance' ),
						);
						$rec_label = isset( $rec_labels[ $recurrencia ] ) ? $rec_labels[ $recurrencia ] : $recurrencia;
						?>
						<p class="lznd-detail-meta">
							<?php echo esc_html__( 'Recurrencia:', 'lezned-maintenance' ) . ' '; ?>
							<span class="lznd-badge lznd-badge-blue"><?php echo esc_html( $rec_label ); ?></span>
							<?php if ( $fecha_prox ) : ?>
								&nbsp;<?php echo esc_html__( 'Próxima:', 'lezned-maintenance' ) . ' ' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $fecha_prox ) ) ); ?>
							<?php endif; ?>
						</p>
					<?php endif; ?>
				</div>
				<div class="lznd-detail-header-right">
					<a href="<?php echo esc_url( $edit_url ); ?>" class="button"><?php esc_html_e( 'Editar proyecto', 'lezned-maintenance' ); ?></a>
					<a href="<?php echo esc_url( $new_act_url ); ?>" class="button button-primary"><?php esc_html_e( '+ Nueva actuación', 'lezned-maintenance' ); ?></a>
				</div>
			</div>

			<!-- Stats cards -->
			<div class="lznd-pd-stats">
				<div class="lznd-pd-stat lznd-pd-stat--total">
					<div class="lznd-pd-stat__icon dashicons dashicons-hammer"></div>
					<div class="lznd-pd-stat__body">
						<div class="lznd-pd-stat__value"><?php echo (int) $total_acts; ?></div>
						<div class="lznd-pd-stat__label"><?php esc_html_e( 'Total actuaciones', 'lezned-maintenance' ); ?></div>
					</div>
				</div>
				<div class="lznd-pd-stat lznd-pd-stat--completadas">
					<div class="lznd-pd-stat__icon dashicons dashicons-yes-alt"></div>
					<div class="lznd-pd-stat__body">
						<div class="lznd-pd-stat__value"><?php echo (int) $completadas_n; ?></div>
						<div class="lznd-pd-stat__label"><?php esc_html_e( 'Completadas', 'lezned-maintenance' ); ?></div>
					</div>
				</div>
				<div class="lznd-pd-stat lznd-pd-stat--pendientes">
					<div class="lznd-pd-stat__icon dashicons dashicons-clock"></div>
					<div class="lznd-pd-stat__body">
						<div class="lznd-pd-stat__value"><?php echo (int) $pendientes_n; ?></div>
						<div class="lznd-pd-stat__label"><?php esc_html_e( 'Pendientes', 'lezned-maintenance' ); ?></div>
					</div>
				</div>
				<div class="lznd-pd-stat lznd-pd-stat--proximas">
					<div class="lznd-pd-stat__icon dashicons dashicons-calendar-alt"></div>
					<div class="lznd-pd-stat__body">
						<div class="lznd-pd-stat__value"><?php echo (int) $proximas_n; ?></div>
						<div class="lznd-pd-stat__label"><?php esc_html_e( 'Próximas', 'lezned-maintenance' ); ?></div>
					</div>
				</div>
			</div>

			<!-- Calendar Section -->
			<div class="lznd-section lznd-calendar-section">
				<h2><?php esc_html_e( 'Calendario de actuaciones', 'lezned-maintenance' ); ?></h2>
				<div id="lznd-calendar-container" data-proyecto="<?php echo esc_attr( $proyecto_id ); ?>">
					<?php echo self::render_calendar( $proyecto_id, $year, $month ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			</div>

			<!-- Three sections -->
			<div class="lznd-actuaciones-grid">

				<!-- Pasadas -->
				<div class="lznd-act-section lznd-act-past">
					<h2 class="lznd-act-section-title lznd-act-title-past">
						<?php esc_html_e( 'Actuaciones pasadas', 'lezned-maintenance' ); ?>
						<span class="lznd-count"><?php echo count( $past ); ?></span>
					</h2>
					<?php if ( empty( $past ) ) : ?>
						<p class="lznd-empty-state"><?php esc_html_e( 'No hay actuaciones pasadas.', 'lezned-maintenance' ); ?></p>
					<?php else : ?>
						<div class="lznd-act-list">
							<?php foreach ( $past as $act ) : ?>
								<?php self::render_actuacion_card( $act ); ?>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>

				<!-- Pendientes -->
				<div class="lznd-act-section lznd-act-pending">
					<h2 class="lznd-act-section-title lznd-act-title-pending">
						<?php esc_html_e( 'Actuaciones pendientes', 'lezned-maintenance' ); ?>
						<span class="lznd-count"><?php echo count( $pending ); ?></span>
					</h2>
					<?php if ( empty( $pending ) ) : ?>
						<p class="lznd-empty-state"><?php esc_html_e( 'No hay actuaciones pendientes.', 'lezned-maintenance' ); ?></p>
					<?php else : ?>
						<div class="lznd-act-list">
							<?php foreach ( $pending as $act ) : ?>
								<?php self::render_actuacion_card( $act ); ?>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>

				<!-- Próximas -->
				<div class="lznd-act-section lznd-act-upcoming">
					<h2 class="lznd-act-section-title lznd-act-title-upcoming">
						<?php esc_html_e( 'Próximas actuaciones', 'lezned-maintenance' ); ?>
						<span class="lznd-count"><?php echo count( $upcoming ); ?></span>
					</h2>
					<?php if ( empty( $upcoming ) ) : ?>
						<p class="lznd-empty-state"><?php esc_html_e( 'No hay próximas actuaciones.', 'lezned-maintenance' ); ?></p>
					<?php else : ?>
						<div class="lznd-act-list">
							<?php foreach ( $upcoming as $act ) : ?>
								<?php self::render_actuacion_card( $act ); ?>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>

			</div><!-- .lznd-actuaciones-grid -->

			<!-- Estadísticas de trabajos bar chart -->
			<div class="lznd-pd-chart-section">
				<div class="lznd-pd-chart-card">
					<h2 class="lznd-pd-chart-title"><?php esc_html_e( 'Estadísticas de trabajos', 'lezned-maintenance' ); ?></h2>
					<div class="lznd-pd-chart-wrap">
						<canvas id="lznd-pd-bar-chart"></canvas>
					</div>
				</div>
			</div>

		</div><!-- .lznd-project-detail -->

		<script>
		(function($){
			var AJAX     = <?php echo wp_json_encode( $ajax_url ); ?>;
			var NONCE    = <?php echo wp_json_encode( $nonce ); ?>;
			var PROJ_ID  = <?php echo (int) $proyecto_id; ?>;
			var barChart = null;

			$(document).ready(function(){
				$.post(AJAX, {
					action:      'lznd_get_project_stats',
					nonce:       NONCE,
					proyecto_id: PROJ_ID,
				}, function(res){
					if (!res || !res.success) return;
					var d = res.data;
					var ctx = document.getElementById('lznd-pd-bar-chart');
					if (!ctx) return;
					if (barChart) barChart.destroy();
					barChart = new Chart(ctx, {
						type: 'bar',
						data: {
							labels: d.labels,
							datasets: [
								{
									label: 'Completadas',
									data: d.completadas,
									backgroundColor: '#22c55e',
									borderRadius: 4,
								},
								{
									label: 'Pendientes',
									data: d.pendientes,
									backgroundColor: '#f59e0b',
									borderRadius: 4,
								},
							]
						},
						options: {
							responsive: true,
							maintainAspectRatio: false,
							plugins: { legend: { position: 'bottom' } },
							scales: {
								x: { stacked: false },
								y: { beginAtZero: true, ticks: { stepSize: 1 } }
							}
						}
					});
				});
			});
		})(jQuery);
		</script>
		<?php
	}

	// ─── Render a Single Actuación Card ──────────────────────────────────────

	private static function render_actuacion_card( $act ) {
		$edit_url    = get_edit_post_link( $act->ID );
		$estado      = get_post_meta( $act->ID, '_lznd_estado',        true );
		$fecha_prog  = get_post_meta( $act->ID, '_lznd_fecha_prog',    true );
		$tecnico_id  = get_post_meta( $act->ID, '_lznd_tecnico',       true );
		$checklist   = get_post_meta( $act->ID, '_lznd_tareas_checklist', true );

		$terms = wp_get_post_terms( $act->ID, 'lznd_tipo' );
		$tipo_html = '';
		if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
			$tipo_html = '<span class="lznd-badge lznd-badge-tipo">' . esc_html( $terms[0]->name ) . '</span>';
		}

		$tecnico_name = '';
		if ( $tecnico_id ) {
			$user = get_userdata( $tecnico_id );
			if ( $user ) {
				$tecnico_name = $user->display_name;
			}
		}

		$progress_html = '';
		if ( $checklist ) {
			$tasks = json_decode( $checklist, true );
			if ( is_array( $tasks ) ) {
				$total = count( $tasks );
				$done  = 0;
				foreach ( $tasks as $task ) {
					if ( ! empty( $task['done'] ) ) {
						$done++;
					}
				}
				$pct = $total > 0 ? round( ( $done / $total ) * 100 ) : 0;
				$progress_html = '<div class="lznd-progress-wrap">'
					. '<div class="lznd-progress-bar"><div class="lznd-progress-fill" style="width:' . esc_attr( $pct ) . '%"></div></div>'
					. '<span class="lznd-progress-label">' . esc_html( "$done/$total" ) . '</span>'
					. '</div>';
			}
		}

		$fecha_html = '';
		if ( $fecha_prog ) {
			$timestamp = strtotime( $fecha_prog );
			$today     = strtotime( 'today' );
			$diff_days = ( $timestamp - $today ) / DAY_IN_SECONDS;
			$formatted = date_i18n( get_option( 'date_format' ), $timestamp );
			if ( $diff_days < 0 ) {
				$fecha_html = '<span class="lznd-date lznd-date-overdue">' . esc_html( $formatted ) . '</span>';
			} elseif ( $diff_days <= 7 ) {
				$fecha_html = '<span class="lznd-date lznd-date-near">' . esc_html( $formatted ) . '</span>';
			} else {
				$fecha_html = '<span class="lznd-date">' . esc_html( $formatted ) . '</span>';
			}
		}

		?>
		<div class="lznd-act-card">
			<div class="lznd-act-card-top">
				<div class="lznd-act-card-title">
					<a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $act->post_title ); ?></a>
				</div>
				<div class="lznd-act-card-badges">
					<?php echo $tipo_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php echo self::estado_badge( $estado ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			</div>
			<div class="lznd-act-card-meta">
				<?php if ( $fecha_html ) : ?>
					<span class="lznd-act-meta-item">
						<span class="dashicons dashicons-calendar-alt"></span>
						<?php echo $fecha_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</span>
				<?php endif; ?>
				<?php if ( $tecnico_name ) : ?>
					<span class="lznd-act-meta-item">
						<span class="dashicons dashicons-admin-users"></span>
						<?php echo esc_html( $tecnico_name ); ?>
					</span>
				<?php endif; ?>
			</div>
			<?php if ( $progress_html ) : ?>
				<div class="lznd-act-card-progress">
					<?php echo $progress_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			<?php endif; ?>
			<div class="lznd-act-card-actions">
				<a href="<?php echo esc_url( $edit_url ); ?>" class="button button-small"><?php esc_html_e( 'Editar', 'lezned-maintenance' ); ?></a>
			</div>
		</div>
		<?php
	}

	// ─── Render Calendar ─────────────────────────────────────────────────────

	public static function render_calendar( $proyecto_id, $year, $month ) {
		// Clamp
		if ( $month < 1 )  { $month = 12; $year--; }
		if ( $month > 12 ) { $month = 1;  $year++; }

		$days_in_month = (int) date( 't', mktime( 0, 0, 0, $month, 1, $year ) );
		$first_day_dow = (int) date( 'N', mktime( 0, 0, 0, $month, 1, $year ) ); // 1=Mon, 7=Sun

		$month_start = sprintf( '%04d-%02d-01', $year, $month );
		$month_end   = sprintf( '%04d-%02d-%02d', $year, $month, $days_in_month );

		// Fetch actuaciones in this month
		$acts = get_posts( array(
			'post_type'      => 'lznd_actuacion',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'   => '_lznd_proyecto_id',
					'value' => $proyecto_id,
					'type'  => 'NUMERIC',
				),
				array(
					'key'     => '_lznd_fecha_prog',
					'value'   => array( $month_start, $month_end ),
					'compare' => 'BETWEEN',
					'type'    => 'DATE',
				),
			),
		) );

		// Index by day
		$acts_by_day = array();
		foreach ( $acts as $act ) {
			$fecha = get_post_meta( $act->ID, '_lznd_fecha_prog', true );
			if ( $fecha ) {
				$day = (int) date( 'j', strtotime( $fecha ) );
				if ( ! isset( $acts_by_day[ $day ] ) ) {
					$acts_by_day[ $day ] = array();
				}
				$acts_by_day[ $day ][] = $act;
			}
		}

		// Navigation URLs
		$prev_month = $month - 1;
		$prev_year  = $year;
		if ( $prev_month < 1 ) { $prev_month = 12; $prev_year--; }

		$next_month = $month + 1;
		$next_year  = $year;
		if ( $next_month > 12 ) { $next_month = 1; $next_year++; }

		$base_url   = admin_url( 'admin.php?page=lznd-project-detail&id=' . $proyecto_id );
		$prev_url   = add_query_arg( array( 'cal_year' => $prev_year, 'cal_month' => $prev_month ), $base_url );
		$next_url   = add_query_arg( array( 'cal_year' => $next_year, 'cal_month' => $next_month ), $base_url );

		$month_name = date_i18n( 'F Y', mktime( 0, 0, 0, $month, 1, $year ) );

		$today_day   = (int) current_time( 'j' );
		$today_month = (int) current_time( 'm' );
		$today_year  = (int) current_time( 'Y' );

		$day_names = array(
			__( 'Lun', 'lezned-maintenance' ),
			__( 'Mar', 'lezned-maintenance' ),
			__( 'Mié', 'lezned-maintenance' ),
			__( 'Jue', 'lezned-maintenance' ),
			__( 'Vie', 'lezned-maintenance' ),
			__( 'Sáb', 'lezned-maintenance' ),
			__( 'Dom', 'lezned-maintenance' ),
		);

		ob_start();
		?>
		<div class="lznd-calendar" data-year="<?php echo esc_attr( $year ); ?>" data-month="<?php echo esc_attr( $month ); ?>">
			<div class="lznd-calendar-nav">
				<a href="<?php echo esc_url( $prev_url ); ?>" class="lznd-cal-prev button button-small" data-year="<?php echo esc_attr( $prev_year ); ?>" data-month="<?php echo esc_attr( $prev_month ); ?>">&#8592;</a>
				<span class="lznd-cal-month-title"><?php echo esc_html( ucfirst( $month_name ) ); ?></span>
				<a href="<?php echo esc_url( $next_url ); ?>" class="lznd-cal-next button button-small" data-year="<?php echo esc_attr( $next_year ); ?>" data-month="<?php echo esc_attr( $next_month ); ?>">&#8594;</a>
			</div>
			<table class="lznd-calendar-table">
				<thead>
					<tr>
						<?php foreach ( $day_names as $dn ) : ?>
							<th><?php echo esc_html( $dn ); ?></th>
						<?php endforeach; ?>
					</tr>
				</thead>
				<tbody>
				<?php
				$current_day = 1;
				$cells_printed = 0;
				$total_cells = $days_in_month + ( $first_day_dow - 1 );
				$rows = (int) ceil( $total_cells / 7 );

				for ( $row = 0; $row < $rows; $row++ ) :
				?>
					<tr>
					<?php for ( $dow = 1; $dow <= 7; $dow++ ) : ?>
						<?php
						$cell_index = $row * 7 + $dow;
						$is_empty   = $cell_index < $first_day_dow;
						$day_num    = $cell_index - $first_day_dow + 1;
						$is_over    = $day_num > $days_in_month;
						$is_today   = ( $year === $today_year && $month === $today_month && $day_num === $today_day );

						$cell_class = 'lznd-cal-cell';
						if ( $is_empty || $is_over ) {
							$cell_class .= ' lznd-cal-empty';
						}
						if ( $is_today ) {
							$cell_class .= ' lznd-cal-today';
						}
						if ( ! $is_empty && ! $is_over && isset( $acts_by_day[ $day_num ] ) ) {
							$cell_class .= ' lznd-cal-has-acts';
						}
						?>
						<td class="<?php echo esc_attr( $cell_class ); ?>">
						<?php if ( ! $is_empty && ! $is_over ) : ?>
							<span class="lznd-cal-day-num"><?php echo (int) $day_num; ?></span>
							<?php if ( isset( $acts_by_day[ $day_num ] ) ) : ?>
								<div class="lznd-cal-dots">
									<?php foreach ( $acts_by_day[ $day_num ] as $dot_act ) : ?>
										<?php
										$dot_estado = get_post_meta( $dot_act->ID, '_lznd_estado', true );
										$dot_class  = 'lznd-cal-dot lznd-dot-' . ( $dot_estado ? esc_attr( $dot_estado ) : 'pendiente' );
										$dot_url    = get_edit_post_link( $dot_act->ID );
										?>
										<a href="<?php echo esc_url( $dot_url ); ?>" title="<?php echo esc_attr( $dot_act->post_title ); ?>" class="<?php echo esc_attr( $dot_class ); ?>"></a>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
						<?php endif; ?>
						</td>
					<?php endfor; ?>
					</tr>
				<?php endfor; ?>
				</tbody>
			</table>

			<?php if ( ! empty( $acts ) ) : ?>
			<div class="lznd-cal-legend">
				<span class="lznd-cal-dot lznd-dot-pendiente"></span> <?php esc_html_e( 'Pendiente', 'lezned-maintenance' ); ?>
				<span class="lznd-cal-dot lznd-dot-en_proceso"></span> <?php esc_html_e( 'En proceso', 'lezned-maintenance' ); ?>
				<span class="lznd-cal-dot lznd-dot-completado"></span> <?php esc_html_e( 'Completado', 'lezned-maintenance' ); ?>
				<span class="lznd-cal-dot lznd-dot-enviado"></span> <?php esc_html_e( 'Enviado', 'lezned-maintenance' ); ?>
				<span class="lznd-cal-dot lznd-dot-cancelado"></span> <?php esc_html_e( 'Cancelado', 'lezned-maintenance' ); ?>
			</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	// ─── AJAX: Calendar Navigation ───────────────────────────────────────────

	public static function ajax_calendar() {
		check_ajax_referer( 'lznd_admin', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sin permiso.', 'lezned-maintenance' ) ) );
		}

		$proyecto_id = isset( $_POST['proyecto_id'] ) ? absint( $_POST['proyecto_id'] ) : 0;
		$year        = isset( $_POST['year'] )        ? absint( $_POST['year'] )        : (int) current_time( 'Y' );
		$month       = isset( $_POST['month'] )       ? absint( $_POST['month'] )       : (int) current_time( 'm' );

		if ( ! $proyecto_id ) {
			wp_send_json_error( array( 'message' => __( 'ID de proyecto inválido.', 'lezned-maintenance' ) ) );
		}

		$html = self::render_calendar( $proyecto_id, $year, $month );
		wp_send_json_success( array( 'html' => $html ) );
	}

	// ─── AJAX: Project Stats ─────────────────────────────────────────────────

	public static function ajax_project_stats() {
		check_ajax_referer( 'lznd_admin', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sin permiso.', 'lezned-maintenance' ) ) );
		}

		$proyecto_id = isset( $_POST['proyecto_id'] ) ? absint( $_POST['proyecto_id'] ) : 0;
		if ( ! $proyecto_id ) {
			wp_send_json_error( array( 'message' => __( 'ID inválido.', 'lezned-maintenance' ) ) );
		}

		// Last 12 months
		$labels      = array();
		$completadas = array();
		$pendientes  = array();

		for ( $i = 11; $i >= 0; $i-- ) {
			$ts         = strtotime( "-{$i} months" );
			$m          = (int) date( 'n', $ts );
			$y          = (int) date( 'Y', $ts );
			$month_str  = date_i18n( 'M Y', $ts );
			$month_start = sprintf( '%04d-%02d-01', $y, $m );
			$month_end   = date( 'Y-m-t', strtotime( $month_start ) );

			$acts = get_posts( array(
				'post_type'      => 'lznd_actuacion',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'   => '_lznd_proyecto_id',
						'value' => $proyecto_id,
						'type'  => 'NUMERIC',
					),
					array(
						'key'     => '_lznd_fecha_prog',
						'value'   => array( $month_start, $month_end ),
						'compare' => 'BETWEEN',
						'type'    => 'DATE',
					),
				),
			) );

			$comp = 0;
			$pend = 0;
			foreach ( $acts as $act_id ) {
				$estado = get_post_meta( $act_id, '_lznd_estado', true );
				if ( in_array( $estado, array( 'enviado', 'cerrado', 'completado' ), true ) ) {
					$comp++;
				} else {
					$pend++;
				}
			}

			$labels[]      = $month_str;
			$completadas[] = $comp;
			$pendientes[]  = $pend;
		}

		wp_send_json_success( array(
			'labels'      => $labels,
			'completadas' => $completadas,
			'pendientes'  => $pendientes,
		) );
	}

	// ─── AJAX: Client Stats chart ─────────────────────────────────────────────

	public static function ajax_client_stats() {
		check_ajax_referer( 'lznd_admin', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sin permiso.', 'lezned-maintenance' ) ) );
		}

		$cliente_id = isset( $_POST['cliente_id'] ) ? absint( $_POST['cliente_id'] ) : 0;
		if ( ! $cliente_id ) {
			wp_send_json_error( array( 'message' => __( 'ID inválido.', 'lezned-maintenance' ) ) );
		}

		$proyecto_ids = get_posts( array(
			'post_type'      => 'lznd_proyecto',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array( array( 'key' => '_lznd_cliente_id', 'value' => $cliente_id ) ),
		) );

		if ( empty( $proyecto_ids ) ) {
			wp_send_json_success( array( 'labels' => array(), 'completadas' => array(), 'pendientes' => array() ) );
			return;
		}

		$labels      = array();
		$completadas = array();
		$pendientes  = array();

		for ( $i = 11; $i >= 0; $i-- ) {
			$ts          = strtotime( "-{$i} months" );
			$m           = (int) date( 'n', $ts );
			$y           = (int) date( 'Y', $ts );
			$month_start = sprintf( '%04d-%02d-01', $y, $m );
			$month_end   = date( 'Y-m-t', strtotime( $month_start ) );

			$acts = get_posts( array(
				'post_type'      => 'lznd_actuacion',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array(
					'relation' => 'AND',
					array( 'key' => '_lznd_proyecto_id', 'value' => $proyecto_ids, 'compare' => 'IN' ),
					array( 'key' => '_lznd_fecha_prog', 'value' => array( $month_start, $month_end ), 'compare' => 'BETWEEN', 'type' => 'DATE' ),
				),
			) );

			$comp = 0;
			$pend = 0;
			foreach ( $acts as $act_id ) {
				$estado = get_post_meta( $act_id, '_lznd_estado', true );
				if ( in_array( $estado, array( 'enviado', 'cerrado', 'completado' ), true ) ) {
					$comp++;
				} else {
					$pend++;
				}
			}

			$labels[]      = date_i18n( 'M Y', $ts );
			$completadas[] = $comp;
			$pendientes[]  = $pend;
		}

		wp_send_json_success( array(
			'labels'      => $labels,
			'completadas' => $completadas,
			'pendientes'  => $pendientes,
		) );
	}
}

LZND_Admin_UI::init();
