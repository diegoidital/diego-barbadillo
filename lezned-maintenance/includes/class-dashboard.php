<?php
/**
 * LZND_Dashboard — Admin dashboard page.
 *
 * Shows: stats cards, per-client summary, this week's schedule,
 * recent completed actuaciones.
 *
 * @package Lezned_Maintenance
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LZND_Dashboard {

	public static function init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	public static function enqueue( $hook ) {
		if ( 'toplevel_page_lznd-menu' !== $hook ) {
			return;
		}
		// Chart.js for mini sparkline bars
		wp_enqueue_script(
			'lznd-chartjs',
			'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
			array(),
			'4.4.0',
			true
		);
	}

	/* ------------------------------------------------------------------ */
	/* Render                                                               */
	/* ------------------------------------------------------------------ */
	public static function render() {
		$stats   = self::get_stats();
		$clientes = self::get_client_summaries();
		$semana  = self::get_week_schedule();
		$recientes = self::get_recent_completed( 8 );
		?>
		<div class="wrap lznd-dashboard">
			<h1 class="lznd-dash__heading">
				<span class="dashicons dashicons-hammer" style="font-size:28px;height:28px;width:28px;margin-right:8px;vertical-align:middle;color:#1d5fa6"></span>
				Dashboard — Mantenimiento
			</h1>

			<!-- ═══ STATS CARDS ═══ -->
			<div class="lznd-stats-grid">
				<?php
				$cards = array(
					array(
						'label' => 'Clientes activos',
						'value' => $stats['clientes'],
						'icon'  => 'dashicons-groups',
						'color' => '#1d5fa6',
						'url'   => admin_url( 'edit.php?post_type=lznd_cliente' ),
					),
					array(
						'label' => 'Proyectos activos',
						'value' => $stats['proyectos'],
						'icon'  => 'dashicons-building',
						'color' => '#2e7d32',
						'url'   => admin_url( 'edit.php?post_type=lznd_proyecto' ),
					),
					array(
						'label' => 'Actuaciones pendientes',
						'value' => $stats['pendientes'],
						'icon'  => 'dashicons-clock',
						'color' => '#996800',
						'url'   => admin_url( 'edit.php?post_type=lznd_actuacion&wmpro_estado=pendiente' ),
					),
					array(
						'label' => 'En proceso',
						'value' => $stats['en_proceso'],
						'icon'  => 'dashicons-hammer',
						'color' => '#1565c0',
						'url'   => admin_url( 'edit.php?post_type=lznd_actuacion' ),
					),
					array(
						'label' => 'Vencidas',
						'value' => $stats['vencidas'],
						'icon'  => 'dashicons-warning',
						'color' => '#b71c1c',
						'url'   => admin_url( 'edit.php?post_type=lznd_actuacion' ),
					),
					array(
						'label' => 'Completadas este mes',
						'value' => $stats['completadas_mes'],
						'icon'  => 'dashicons-yes-alt',
						'color' => '#00695c',
						'url'   => admin_url( 'edit.php?post_type=lznd_actuacion' ),
					),
				);
				foreach ( $cards as $card ) :
					?>
					<a href="<?php echo esc_url( $card['url'] ); ?>" class="lznd-stat-card" style="--card-color:<?php echo esc_attr( $card['color'] ); ?>">
						<div class="lznd-stat-card__icon">
							<span class="dashicons <?php echo esc_attr( $card['icon'] ); ?>"></span>
						</div>
						<div class="lznd-stat-card__body">
							<div class="lznd-stat-card__value"><?php echo esc_html( $card['value'] ); ?></div>
							<div class="lznd-stat-card__label"><?php echo esc_html( $card['label'] ); ?></div>
						</div>
					</a>
					<?php
				endforeach;
				?>
			</div>

			<div class="lznd-dash__cols">

				<!-- ═══ COLUMN LEFT: Clientes ═══ -->
				<div class="lznd-dash__col lznd-dash__col--wide">
					<div class="lznd-dash-box">
						<div class="lznd-dash-box__header">
							<h2 class="lznd-dash-box__title">
								<span class="dashicons dashicons-groups"></span> Resumen por cliente
							</h2>
							<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=lznd_cliente' ) ); ?>" class="button button-small">
								+ Nuevo cliente
							</a>
						</div>
						<div class="lznd-dash-box__body">
							<?php if ( empty( $clientes ) ) : ?>
								<p class="lznd-dash-empty">No hay clientes creados. <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=lznd_cliente' ) ); ?>">Crear el primero</a>.</p>
							<?php else : ?>
							<table class="lznd-dash-table widefat striped">
								<thead>
									<tr>
										<th>Cliente</th>
										<th class="center">Proyectos</th>
										<th class="center">Pendientes</th>
										<th class="center">Completadas</th>
										<th>Próxima cita</th>
										<th></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $clientes as $c ) :
										$proxima_ts = $c['proxima_fecha'] ? strtotime( $c['proxima_fecha'] ) : 0;
										$hoy        = strtotime( current_time( 'Y-m-d' ) );
										$diff_dias  = $proxima_ts ? (int) ceil( ( $proxima_ts - $hoy ) / DAY_IN_SECONDS ) : null;
										$fecha_cls  = '';
										if ( $diff_dias !== null ) {
											if ( $diff_dias < 0 )     $fecha_cls = 'style="color:#b71c1c;font-weight:600"';
											elseif ( $diff_dias <= 7 ) $fecha_cls = 'style="color:#e65100;font-weight:600"';
										}
										?>
										<tr>
											<td>
												<strong>
													<a href="<?php echo esc_url( admin_url( 'post.php?post=' . $c['id'] . '&action=edit' ) ); ?>">
														<?php echo esc_html( $c['nombre'] ); ?>
													</a>
												</strong>
												<?php if ( $c['email'] ) : ?>
													<br><small class="lznd-text-muted"><?php echo esc_html( $c['email'] ); ?></small>
												<?php endif; ?>
											</td>
											<td class="center">
												<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=lznd_proyecto&lznd_cliente_filter=' . $c['id'] ) ); ?>">
													<strong><?php echo esc_html( $c['proyectos'] ); ?></strong>
												</a>
											</td>
											<td class="center">
												<?php if ( $c['pendientes'] > 0 ) : ?>
													<span class="lznd-badge lznd-badge--en_proceso"><?php echo esc_html( $c['pendientes'] ); ?></span>
												<?php else : ?>
													<span class="lznd-text-muted">0</span>
												<?php endif; ?>
											</td>
											<td class="center">
												<span class="lznd-badge lznd-badge--completado"><?php echo esc_html( $c['completadas'] ); ?></span>
											</td>
											<td <?php echo $fecha_cls; // phpcs:ignore ?>>
												<?php if ( $c['proxima_fecha'] ) : ?>
													<?php echo esc_html( date_i18n( 'd/m/Y', $proxima_ts ) ); ?>
													<?php if ( $diff_dias !== null && $diff_dias >= 0 ) : ?>
														<br><small>(<?php echo esc_html( $diff_dias ); ?> días)</small>
													<?php elseif ( $diff_dias !== null && $diff_dias < 0 ) : ?>
														<br><small>Vencida</small>
													<?php endif; ?>
												<?php else : ?>
													<span class="lznd-text-muted">—</span>
												<?php endif; ?>
											</td>
											<td>
												<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=lznd_proyecto&lznd_cliente_filter=' . $c['id'] ) ); ?>" class="button button-small">
													Ver proyectos
												</a>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
							<?php endif; ?>
						</div>
					</div>
				</div>

				<!-- ═══ COLUMN RIGHT: Semana + Recientes ═══ -->
				<div class="lznd-dash__col lznd-dash__col--narrow">

					<!-- Semana actual -->
					<div class="lznd-dash-box">
						<div class="lznd-dash-box__header">
							<h2 class="lznd-dash-box__title">
								<span class="dashicons dashicons-calendar-alt"></span> Esta semana
							</h2>
						</div>
						<div class="lznd-dash-box__body lznd-dash-box__body--no-pad">
							<?php
							$dias_semana = array( 'Lunes', 'Martes', 'Mi&#233;rcoles', 'Jueves', 'Viernes', 'S&#225;bado', 'Domingo' );
							$lunes = new DateTime( 'monday this week', new DateTimeZone( wp_timezone_string() ) );
							$has_any = false;
							for ( $d = 0; $d < 7; $d++ ) :
								$dia_dt   = clone $lunes;
								$dia_dt->modify( "+{$d} days" );
								$dia_key  = $dia_dt->format( 'Y-m-d' );
								$dia_acts = isset( $semana[ $dia_key ] ) ? $semana[ $dia_key ] : array();
								$is_today = $dia_key === current_time( 'Y-m-d' );
								if ( empty( $dia_acts ) && ! $is_today ) continue;
								$has_any = true;
								?>
								<div class="lznd-week-day <?php echo $is_today ? 'lznd-week-day--today' : ''; ?>">
									<div class="lznd-week-day__header">
										<strong><?php echo $dias_semana[ $d ]; // phpcs:ignore ?></strong>
										<span class="lznd-week-day__date"><?php echo esc_html( $dia_dt->format( 'd/m' ) ); ?></span>
									</div>
									<?php if ( empty( $dia_acts ) ) : ?>
										<p class="lznd-week-empty">Sin actuaciones</p>
									<?php else : ?>
										<ul class="lznd-week-list">
											<?php foreach ( $dia_acts as $act ) :
												$estado = get_post_meta( $act->ID, '_lznd_estado', true ) ?: 'pendiente';
												?>
												<li class="lznd-week-item">
													<span class="lznd-dot lznd-dot--<?php echo esc_attr( $estado ); ?>"></span>
													<a href="<?php echo esc_url( admin_url( 'post.php?post=' . $act->ID . '&action=edit' ) ); ?>">
														<?php echo esc_html( $act->post_title ); ?>
													</a>
												</li>
											<?php endforeach; ?>
										</ul>
									<?php endif; ?>
								</div>
							<?php endfor; ?>
							<?php if ( ! $has_any ) : ?>
								<p class="lznd-dash-empty" style="padding:16px">No hay actuaciones programadas esta semana.</p>
							<?php endif; ?>
						</div>
					</div>

					<!-- Completadas recientes -->
					<div class="lznd-dash-box" style="margin-top:20px">
						<div class="lznd-dash-box__header">
							<h2 class="lznd-dash-box__title">
								<span class="dashicons dashicons-yes-alt"></span> &#218;ltimas completadas
							</h2>
						</div>
						<div class="lznd-dash-box__body lznd-dash-box__body--no-pad">
							<?php if ( empty( $recientes ) ) : ?>
								<p class="lznd-dash-empty" style="padding:16px">Sin actuaciones completadas.</p>
							<?php else : ?>
								<ul class="lznd-recent-list">
									<?php foreach ( $recientes as $act ) :
										$proy_id  = get_post_meta( $act->ID, '_lznd_proyecto_id', true );
										$proy     = $proy_id ? get_post( $proy_id ) : null;
										$fecha_fin = get_post_meta( $act->ID, '_lznd_fecha_fin', true );
										?>
										<li class="lznd-recent-item">
											<div class="lznd-recent-item__icon">
												<span class="dashicons dashicons-yes-alt" style="color:#2e7d32"></span>
											</div>
											<div class="lznd-recent-item__body">
												<a href="<?php echo esc_url( admin_url( 'post.php?post=' . $act->ID . '&action=edit' ) ); ?>" class="lznd-recent-item__title">
													<?php echo esc_html( $act->post_title ); ?>
												</a>
												<?php if ( $proy ) : ?>
													<span class="lznd-recent-item__sub"><?php echo esc_html( $proy->post_title ); ?></span>
												<?php endif; ?>
											</div>
											<div class="lznd-recent-item__date">
												<?php echo $fecha_fin ? esc_html( date_i18n( 'd/m', strtotime( $fecha_fin ) ) ) : esc_html( date_i18n( 'd/m', strtotime( $act->post_modified ) ) ); ?>
											</div>
										</li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>
						</div>
					</div>

				</div><!-- .lznd-dash__col--narrow -->
			</div><!-- .lznd-dash__cols -->

			<!-- ═══ QUICK ACTIONS ═══ -->
			<div class="lznd-quick-actions">
				<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=lznd_proyecto' ) ); ?>" class="lznd-quick-btn">
					<span class="dashicons dashicons-building"></span> Nuevo proyecto
				</a>
				<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=lznd_cliente' ) ); ?>" class="lznd-quick-btn">
					<span class="dashicons dashicons-admin-users"></span> Nuevo cliente
				</a>
				<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=lznd_actuacion' ) ); ?>" class="lznd-quick-btn">
					<span class="dashicons dashicons-hammer"></span> Nueva actuaci&#243;n
				</a>
				<a href="<?php echo esc_url( get_permalink( get_option( 'lznd_page_id' ) ) ); ?>" class="lznd-quick-btn lznd-quick-btn--green" target="_blank">
					<span class="dashicons dashicons-smartphone"></span> Abrir frontend m&#243;vil
				</a>
			</div>

		</div><!-- .lznd-dashboard -->
		<?php
	}

	/* ------------------------------------------------------------------ */
	/* Data queries                                                          */
	/* ------------------------------------------------------------------ */

	private static function get_stats() {
		$today     = current_time( 'Y-m-d' );
		$mes_inicio = current_time( 'Y-m' ) . '-01';

		return array(
			'clientes'        => wp_count_posts( 'lznd_cliente' )->publish,
			'proyectos'       => wp_count_posts( 'lznd_proyecto' )->publish,
			'pendientes'      => self::count_actuaciones_by_estado( array( 'pendiente' ) ),
			'en_proceso'      => self::count_actuaciones_by_estado( array( 'en_proceso' ) ),
			'vencidas'        => self::count_vencidas( $today ),
			'completadas_mes' => self::count_completadas_mes( $mes_inicio ),
		);
	}

	private static function count_actuaciones_by_estado( array $estados ) {
		$posts = get_posts( array(
			'post_type'      => 'lznd_actuacion',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array( 'key' => '_lznd_estado', 'value' => $estados, 'compare' => 'IN' ),
			),
		) );
		return count( $posts );
	}

	private static function count_vencidas( $today ) {
		$posts = get_posts( array(
			'post_type'      => 'lznd_actuacion',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array(
				'relation' => 'AND',
				array( 'key' => '_lznd_estado',     'value' => array( 'pendiente', 'en_proceso' ), 'compare' => 'IN' ),
				array( 'key' => '_lznd_fecha_prog', 'value' => $today, 'compare' => '<', 'type' => 'DATE' ),
			),
		) );
		return count( $posts );
	}

	private static function count_completadas_mes( $desde ) {
		$posts = get_posts( array(
			'post_type'      => 'lznd_actuacion',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array(
				'relation' => 'AND',
				array( 'key' => '_lznd_estado',   'value' => array( 'completado', 'enviado' ), 'compare' => 'IN' ),
				array( 'key' => '_lznd_fecha_fin', 'value' => $desde, 'compare' => '>=', 'type' => 'DATE' ),
			),
		) );
		return count( $posts );
	}

	private static function get_client_summaries() {
		$clientes = get_posts( array(
			'post_type'      => 'lznd_cliente',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );

		$summaries = array();

		foreach ( $clientes as $cli ) {
			// Projects for this client
			$proyectos = get_posts( array(
				'post_type'      => 'lznd_proyecto',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array( 'key' => '_lznd_cliente_id', 'value' => $cli->ID ),
				),
			) );

			if ( empty( $proyectos ) ) {
				$summaries[] = array(
					'id'            => $cli->ID,
					'nombre'        => $cli->post_title,
					'email'         => get_post_meta( $cli->ID, '_lznd_email', true ),
					'proyectos'     => 0,
					'pendientes'    => 0,
					'completadas'   => 0,
					'proxima_fecha' => null,
				);
				continue;
			}

			// Actuaciones for these projects
			$pending_count   = 0;
			$completed_count = 0;
			$proxima_fecha   = null;

			$actuaciones = get_posts( array(
				'post_type'      => 'lznd_actuacion',
				'posts_per_page' => -1,
				'meta_query'     => array(
					array( 'key' => '_lznd_proyecto_id', 'value' => $proyectos, 'compare' => 'IN' ),
				),
			) );

			foreach ( $actuaciones as $act ) {
				$estado = get_post_meta( $act->ID, '_lznd_estado', true ) ?: 'pendiente';
				if ( in_array( $estado, array( 'pendiente', 'en_proceso' ), true ) ) {
					$pending_count++;
				}
				if ( in_array( $estado, array( 'completado', 'enviado' ), true ) ) {
					$completed_count++;
				}
			}

			// Next scheduled date from projects
			foreach ( $proyectos as $pid ) {
				$fecha = get_post_meta( $pid, '_lznd_fecha_proxima', true );
				if ( $fecha ) {
					if ( ! $proxima_fecha || $fecha < $proxima_fecha ) {
						$proxima_fecha = $fecha;
					}
				}
			}

			$summaries[] = array(
				'id'            => $cli->ID,
				'nombre'        => $cli->post_title,
				'email'         => get_post_meta( $cli->ID, '_lznd_email', true ),
				'proyectos'     => count( $proyectos ),
				'pendientes'    => $pending_count,
				'completadas'   => $completed_count,
				'proxima_fecha' => $proxima_fecha,
			);
		}

		return $summaries;
	}

	private static function get_week_schedule() {
		$lunes   = new DateTime( 'monday this week', new DateTimeZone( wp_timezone_string() ) );
		$domingo = clone $lunes;
		$domingo->modify( '+6 days' );

		$desde = $lunes->format( 'Y-m-d' );
		$hasta = $domingo->format( 'Y-m-d' );

		$actuaciones = get_posts( array(
			'post_type'      => 'lznd_actuacion',
			'posts_per_page' => -1,
			'orderby'        => 'meta_value',
			'meta_key'       => '_lznd_fecha_prog',
			'order'          => 'ASC',
			'meta_query'     => array(
				array( 'key' => '_lznd_fecha_prog', 'value' => array( $desde, $hasta ), 'compare' => 'BETWEEN', 'type' => 'DATE' ),
			),
		) );

		$by_day = array();
		foreach ( $actuaciones as $act ) {
			$fecha = get_post_meta( $act->ID, '_lznd_fecha_prog', true );
			if ( $fecha ) {
				$by_day[ $fecha ][] = $act;
			}
		}

		return $by_day;
	}

	private static function get_recent_completed( $limit = 8 ) {
		return get_posts( array(
			'post_type'      => 'lznd_actuacion',
			'posts_per_page' => $limit,
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'meta_query'     => array(
				array( 'key' => '_lznd_estado', 'value' => array( 'completado', 'enviado' ), 'compare' => 'IN' ),
			),
		) );
	}
}

LZND_Dashboard::init();
