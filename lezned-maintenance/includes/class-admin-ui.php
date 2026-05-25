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

		// Project detail page registration + row action
		add_action( 'admin_menu', array( __CLASS__, 'add_project_detail_page' ) );
		add_filter( 'post_row_actions', array( __CLASS__, 'proyecto_row_actions' ), 10, 2 );

		// AJAX calendar navigation
		add_action( 'wp_ajax_lznd_calendar_nav', array( __CLASS__, 'ajax_calendar' ) );

		// Tipos de trabajo AJAX
		add_action( 'wp_ajax_lznd_save_tipo',   array( __CLASS__, 'ajax_save_tipo' ) );
		add_action( 'wp_ajax_lznd_delete_tipo', array( __CLASS__, 'ajax_delete_tipo' ) );
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

		// Estado filter
		$estados = array(
			'pendiente'  => __( 'Pendiente',   'lezned-maintenance' ),
			'en_proceso' => __( 'En proceso',  'lezned-maintenance' ),
			'completado' => __( 'Completado',  'lezned-maintenance' ),
			'enviado'    => __( 'Enviado',     'lezned-maintenance' ),
			'cancelado'  => __( 'Cancelado',   'lezned-maintenance' ),
		);

		$current_estado = isset( $_GET['lznd_filter_estado'] ) ? sanitize_key( $_GET['lznd_filter_estado'] ) : '';

		echo '<select name="lznd_filter_estado">';
		echo '<option value="">' . esc_html__( 'Todos los estados', 'lezned-maintenance' ) . '</option>';
		foreach ( $estados as $val => $label ) {
			echo '<option value="' . esc_attr( $val ) . '"' . selected( $current_estado, $val, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
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

	// ─── Proyecto Row Action ─────────────────────────────────────────────────

	public static function proyecto_row_actions( $actions, $post ) {
		if ( 'lznd_proyecto' !== $post->post_type ) {
			return $actions;
		}

		$detail_url = admin_url( 'admin.php?page=lznd-project-detail&id=' . $post->ID );
		$actions['lznd_detail'] = '<a href="' . esc_url( $detail_url ) . '">' . esc_html__( 'Ver detalle', 'lezned-maintenance' ) . '</a>';

		return $actions;
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

		// ── Render ──────────────────────────────────────────────────────────
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

		</div><!-- .lznd-project-detail -->
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

	// ─── Tipos de trabajo management page ───────────────────────────────────

	public static function render_tipos_page() {
		if ( ! current_user_can( 'manage_categories' ) ) {
			wp_die( __( 'Sin permiso.', 'lezned-maintenance' ) );
		}

		// Handle add
		if ( isset( $_POST['lznd_tipos_action'] ) && $_POST['lznd_tipos_action'] === 'add' ) {
			check_admin_referer( 'lznd_add_tipo' );
			$name = sanitize_text_field( $_POST['tipo_name'] ?? '' );
			$slug = sanitize_title( $_POST['tipo_slug'] ?? '' );
			if ( $name ) {
				$args = array();
				if ( $slug ) {
					$args['slug'] = $slug;
				}
				wp_insert_term( $name, 'lznd_tipo', $args );
			}
			wp_redirect( admin_url( 'admin.php?page=lznd-tipos&added=1' ) );
			exit;
		}

		$terms = get_terms( array(
			'taxonomy'   => 'lznd_tipo',
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		) );
		if ( is_wp_error( $terms ) ) {
			$terms = array();
		}

		$added = isset( $_GET['added'] );
		?>
		<div class="wrap lznd-tipos-wrap">
			<h1><?php esc_html_e( 'Tipos de trabajo', 'lezned-maintenance' ); ?></h1>

			<?php if ( $added ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Tipo añadido correctamente.', 'lezned-maintenance' ); ?></p></div>
			<?php endif; ?>

			<div class="lznd-tipos-layout">

				<!-- Add new column -->
				<div class="lznd-tipos-add-col">
					<div class="lznd-tipos-add-box">
						<h2><?php esc_html_e( 'Añadir nuevo tipo', 'lezned-maintenance' ); ?></h2>
						<form method="post" action="">
							<?php wp_nonce_field( 'lznd_add_tipo' ); ?>
							<input type="hidden" name="lznd_tipos_action" value="add">

							<div class="lznd-tipos-field">
								<label for="lznd-tipo-name">
									<?php esc_html_e( 'Nombre', 'lezned-maintenance' ); ?>
									<span class="required">*</span>
								</label>
								<input type="text" id="lznd-tipo-name" name="tipo_name" class="regular-text" required
									   placeholder="<?php esc_attr_e( 'Ej: Mantenimiento preventivo', 'lezned-maintenance' ); ?>">
								<p class="description"><?php esc_html_e( 'Nombre que verán los usuarios.', 'lezned-maintenance' ); ?></p>
							</div>

							<div class="lznd-tipos-field">
								<label for="lznd-tipo-slug"><?php esc_html_e( 'Slug', 'lezned-maintenance' ); ?></label>
								<input type="text" id="lznd-tipo-slug" name="tipo_slug" class="regular-text"
									   placeholder="<?php esc_attr_e( 'ej: mantenimiento-preventivo', 'lezned-maintenance' ); ?>">
								<p class="description"><?php esc_html_e( 'Identificador único. Se genera automáticamente si se deja vacío.', 'lezned-maintenance' ); ?></p>
							</div>

							<button type="submit" class="button button-primary">
								<?php esc_html_e( 'Añadir tipo', 'lezned-maintenance' ); ?>
							</button>
						</form>
					</div>
				</div>

				<!-- Table column -->
				<div class="lznd-tipos-table-col">
					<div id="lznd-tipos-notice" style="display:none;margin-bottom:10px;"></div>

					<table class="wp-list-table widefat fixed striped lznd-tipos-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Nombre', 'lezned-maintenance' ); ?></th>
								<th style="width:200px;"><?php esc_html_e( 'Slug', 'lezned-maintenance' ); ?></th>
								<th style="width:100px;"><?php esc_html_e( 'Actuaciones', 'lezned-maintenance' ); ?></th>
								<th style="width:180px;"><?php esc_html_e( 'Acciones', 'lezned-maintenance' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $terms ) ) : ?>
								<tr><td colspan="4" style="text-align:center;padding:20px;color:#646970;">
									<?php esc_html_e( 'No hay tipos de trabajo todavía.', 'lezned-maintenance' ); ?>
								</td></tr>
							<?php else : ?>
								<?php foreach ( $terms as $term ) : ?>
								<tr data-term-id="<?php echo esc_attr( $term->term_id ); ?>">
									<td>
										<span class="lznd-view-mode lznd-tipo-name-view">
											<strong><?php echo esc_html( $term->name ); ?></strong>
										</span>
										<input type="text" class="lznd-edit-mode lznd-tipo-name-input regular-text"
											   value="<?php echo esc_attr( $term->name ); ?>"
											   data-original="<?php echo esc_attr( $term->name ); ?>"
											   style="display:none;" placeholder="<?php esc_attr_e( 'Nombre', 'lezned-maintenance' ); ?>">
									</td>
									<td>
										<span class="lznd-view-mode lznd-tipo-slug-view">
											<code><?php echo esc_html( $term->slug ); ?></code>
										</span>
										<input type="text" class="lznd-edit-mode lznd-tipo-slug-input"
											   value="<?php echo esc_attr( $term->slug ); ?>"
											   data-original="<?php echo esc_attr( $term->slug ); ?>"
											   style="display:none; width:100%;" placeholder="<?php esc_attr_e( 'slug', 'lezned-maintenance' ); ?>">
									</td>
									<td style="text-align:center;">
										<?php echo esc_html( $term->count ); ?>
									</td>
									<td class="lznd-tipos-actions">
										<button type="button" class="button button-small lznd-tipo-edit-btn">
											<?php esc_html_e( 'Editar', 'lezned-maintenance' ); ?>
										</button>
										<button type="button" class="button button-small button-primary lznd-tipo-save-btn" style="display:none;">
											<?php esc_html_e( 'Guardar', 'lezned-maintenance' ); ?>
										</button>
										<button type="button" class="button button-small lznd-tipo-cancel-btn" style="display:none;">
											<?php esc_html_e( 'Cancelar', 'lezned-maintenance' ); ?>
										</button>
										<button type="button" class="button button-small lznd-tipo-delete-btn"
												data-count="<?php echo esc_attr( $term->count ); ?>">
											<?php esc_html_e( 'Eliminar', 'lezned-maintenance' ); ?>
										</button>
									</td>
								</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>

			</div><!-- .lznd-tipos-layout -->
		</div>
		<?php
	}

	public static function ajax_save_tipo() {
		if ( ! current_user_can( 'manage_categories' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sin permiso.', 'lezned-maintenance' ) ) );
		}
		check_ajax_referer( 'lznd_admin', 'nonce' );

		$term_id = absint( $_POST['term_id'] ?? 0 );
		$name    = sanitize_text_field( $_POST['name'] ?? '' );
		$slug    = sanitize_title( $_POST['slug'] ?? '' );

		if ( ! $term_id || ! $name ) {
			wp_send_json_error( array( 'message' => __( 'Datos incompletos.', 'lezned-maintenance' ) ) );
		}

		$args = array( 'name' => $name );
		if ( $slug ) {
			$args['slug'] = $slug;
		}

		$result = wp_update_term( $term_id, 'lznd_tipo', $args );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$updated_term = get_term( $result['term_id'], 'lznd_tipo' );

		wp_send_json_success( array(
			'name' => $updated_term->name,
			'slug' => $updated_term->slug,
		) );
	}

	public static function ajax_delete_tipo() {
		if ( ! current_user_can( 'manage_categories' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sin permiso.', 'lezned-maintenance' ) ) );
		}
		check_ajax_referer( 'lznd_admin', 'nonce' );

		$term_id = absint( $_POST['term_id'] ?? 0 );
		if ( ! $term_id ) {
			wp_send_json_error( array( 'message' => __( 'ID inválido.', 'lezned-maintenance' ) ) );
		}

		$result = wp_delete_term( $term_id, 'lznd_tipo' );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'deleted' => true ) );
	}
}

LZND_Admin_UI::init();
