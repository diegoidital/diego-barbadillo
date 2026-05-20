<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WMPRO_Cron {

	public static function init() {
		add_filter( 'cron_schedules', [ __CLASS__, 'add_intervals' ] );
		add_action( 'wmpro_daily_check',    [ __CLASS__, 'daily_check' ] );
		add_action( 'wmpro_weekly_summary', [ __CLASS__, 'weekly_summary' ] );
	}

	public static function add_intervals( $schedules ) {
		$schedules['wmpro_weekly_monday'] = [
			'interval' => WEEK_IN_SECONDS,
			'display'  => 'Cada semana (lunes 8h)',
		];
		return $schedules;
	}

	public static function setup_events() {
		// Check diario a las 8:00
		if ( ! wp_next_scheduled( 'wmpro_daily_check' ) ) {
			$next_8am = self::next_occurrence_at( 8, 0 );
			wp_schedule_event( $next_8am, 'daily', 'wmpro_daily_check' );
		}

		// Resumen semanal: próximo lunes a las 8:00
		if ( ! wp_next_scheduled( 'wmpro_weekly_summary' ) ) {
			$next_monday = self::next_monday_8am();
			wp_schedule_event( $next_monday, 'wmpro_weekly_monday', 'wmpro_weekly_summary' );
		}
	}

	public static function clear_events() {
		foreach ( [ 'wmpro_daily_check', 'wmpro_weekly_summary' ] as $hook ) {
			$ts = wp_next_scheduled( $hook );
			if ( $ts ) wp_unschedule_event( $ts, $hook );
		}
	}

	private static function next_occurrence_at( $hour, $min ) {
		$tz   = new DateTimeZone( wp_timezone_string() );
		$now  = new DateTime( 'now', $tz );
		$next = new DateTime( "today {$hour}:{$min}", $tz );
		if ( $now >= $next ) $next->modify( '+1 day' );
		return $next->getTimestamp();
	}

	private static function next_monday_8am() {
		$tz   = new DateTimeZone( wp_timezone_string() );
		$now  = new DateTime( 'now', $tz );
		$dow  = (int) $now->format( 'N' ); // 1=Mon
		$diff = ( $dow === 1 && (int) $now->format( 'H' ) < 8 ) ? 0 : ( 8 - $dow ) % 7 ?: 7;
		$next = new DateTime( "today 08:00", $tz );
		$next->modify( "+{$diff} days" );
		return $next->getTimestamp();
	}

	/* ------------------------------------------------------------------ */
	/* Check diario: avisos y creación automática                           */
	/* ------------------------------------------------------------------ */
	public static function daily_check() {
		$settings   = get_option( 'wmpro_settings', [] );
		$aviso_dias = intval( $settings['aviso_dias'] ?? 7 );

		$proyectos = get_posts( [
			'post_type'      => 'wmpro_proyecto',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'meta_query'     => [
				[ 'key' => '_wmpro_recurrencia',      'value' => 'ninguna', 'compare' => '!=' ],
				[ 'key' => '_wmpro_estado_proyecto',  'value' => 'activo' ],
				[ 'key' => '_wmpro_fecha_proxima',    'compare' => 'EXISTS' ],
			],
		] );

		$hoy = new DateTime( current_time( 'Y-m-d' ) );

		foreach ( $proyectos as $proy ) {
			$fecha_proxima_str = get_post_meta( $proy->ID, '_wmpro_fecha_proxima', true );
			if ( ! $fecha_proxima_str ) continue;

			$fecha_proxima = new DateTime( $fecha_proxima_str );
			$diff_dias     = (int) $hoy->diff( $fecha_proxima )->format( '%r%a' );

			// Crear parte automático el día de la fecha
			if ( $diff_dias <= 0 && get_post_meta( $proy->ID, '_wmpro_auto_crear', true ) === '1' ) {
				$ya_existe = get_posts( [
					'post_type'      => 'wmpro_parte',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'meta_query'     => [
						[ 'key' => '_wmpro_proyecto_id', 'value' => $proy->ID ],
						[ 'key' => '_wmpro_fecha_prog',  'value' => $fecha_proxima_str ],
					],
				] );
				if ( empty( $ya_existe ) ) {
					WMPRO_CPT_Parte::create_from_proyecto( $proy->ID );
				}
			}

			// Aviso 1 semana antes
			$aviso_enviado = get_post_meta( $proy->ID, '_wmpro_aviso_enviado', true );
			if ( $diff_dias > 0 && $diff_dias <= $aviso_dias && $aviso_enviado !== '1' ) {
				self::send_advance_warning( $proy->ID, $diff_dias );
				update_post_meta( $proy->ID, '_wmpro_aviso_enviado', '1' );
			}

			// Reset aviso si la fecha fue superada y ya avanzamos la recurrencia
			if ( $diff_dias < -1 ) {
				update_post_meta( $proy->ID, '_wmpro_aviso_enviado', '0' );
			}
		}
	}

	/* ------------------------------------------------------------------ */
	/* Resumen semanal (lunes 8:00)                                         */
	/* ------------------------------------------------------------------ */
	public static function weekly_summary() {
		$settings    = get_option( 'wmpro_settings', [] );
		$admin_email = $settings['admin_email'] ?? get_option( 'admin_email' );
		$from_name   = $settings['from_name']   ?? get_option( 'blogname' );

		$lunes     = new DateTime( 'monday this week', new DateTimeZone( wp_timezone_string() ) );
		$domingo   = clone $lunes;
		$domingo->modify( '+6 days' );

		$desde = $lunes->format( 'Y-m-d' );
		$hasta = $domingo->format( 'Y-m-d' );

		// Partes programados esta semana
		$partes = get_posts( [
			'post_type'      => 'wmpro_parte',
			'posts_per_page' => -1,
			'meta_query'     => [
				'relation' => 'AND',
				[ 'key' => '_wmpro_fecha_prog', 'value' => $desde, 'compare' => '>=' ],
				[ 'key' => '_wmpro_fecha_prog', 'value' => $hasta, 'compare' => '<=' ],
			],
		] );

		// Proyectos activos con fecha próxima esta semana
		$proyectos_semana = get_posts( [
			'post_type'      => 'wmpro_proyecto',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'meta_query'     => [
				'relation' => 'AND',
				[ 'key' => '_wmpro_estado_proyecto',  'value' => 'activo' ],
				[ 'key' => '_wmpro_fecha_proxima',    'value' => $desde, 'compare' => '>=' ],
				[ 'key' => '_wmpro_fecha_proxima',    'value' => $hasta, 'compare' => '<=' ],
			],
		] );

		if ( empty( $partes ) && empty( $proyectos_semana ) ) return; // Nada que notificar

		$subject = sprintf( '[%s] Resumen semanal de mantenimiento — %s a %s',
			get_option( 'blogname' ),
			date_i18n( 'd/m', strtotime( $desde ) ),
			date_i18n( 'd/m/Y', strtotime( $hasta ) )
		);

		ob_start();
		include WMPRO_TEMPLATES . 'email-resumen-semanal.php';
		$body = ob_get_clean();

		add_filter( 'wp_mail_content_type', fn() => 'text/html' );
		wp_mail( $admin_email, $subject, $body, [ "From: {$from_name} <{$admin_email}>" ] );
		remove_filter( 'wp_mail_content_type', fn() => 'text/html' );
	}

	/* ------------------------------------------------------------------ */
	/* Aviso de mantenimiento próximo                                        */
	/* ------------------------------------------------------------------ */
	public static function send_advance_warning( $proyecto_id, $dias ) {
		$settings    = get_option( 'wmpro_settings', [] );
		$admin_email = get_post_meta( $proyecto_id, '_wmpro_aviso_email', true ) ?: ( $settings['admin_email'] ?? get_option( 'admin_email' ) );
		$from_name   = $settings['from_name'] ?? get_option( 'blogname' );

		$proyecto    = get_post( $proyecto_id );
		$fecha       = get_post_meta( $proyecto_id, '_wmpro_fecha_proxima', true );
		$recurrencia = get_post_meta( $proyecto_id, '_wmpro_recurrencia', true );

		$subject = sprintf( '[AVISO] Mantenimiento en %d días — %s', $dias, $proyecto->post_title );
		$body    = self::aviso_email_body( $proyecto, $fecha, $dias, $recurrencia );

		add_filter( 'wp_mail_content_type', fn() => 'text/html' );
		wp_mail( $admin_email, $subject, $body, [ "From: {$from_name} <{$admin_email}>" ] );
		remove_filter( 'wp_mail_content_type', fn() => 'text/html' );
	}

	private static function aviso_email_body( $proyecto, $fecha, $dias, $recurrencia ) {
		$labels = [ 'semanal' => 'Semanal', 'quincenal' => 'Quincenal', 'mensual' => 'Mensual', 'trimestral' => 'Trimestral', 'semestral' => 'Semestral', 'anual' => 'Anual' ];
		$rec_label = $labels[ $recurrencia ] ?? $recurrencia;
		$fecha_fmt = date_i18n( 'l, d \d\e F \d\e Y', strtotime( $fecha ) );
		$admin_url = admin_url( "post.php?post={$proyecto->ID}&action=edit" );

		return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Arial,sans-serif;background:#f5f5f5;padding:20px">
			<div style="max-width:600px;margin:0 auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.1)">
				<div style="background:#1d5fa6;color:#fff;padding:24px 32px">
					<h2 style="margin:0;font-size:20px">⚠️ Aviso de Mantenimiento Próximo</h2>
				</div>
				<div style="padding:32px">
					<p>Se acerca la fecha de mantenimiento programado:</p>
					<table style="width:100%;border-collapse:collapse;margin:16px 0">
						<tr><td style="padding:8px;border-bottom:1px solid #eee;color:#666">Proyecto</td><td style="padding:8px;border-bottom:1px solid #eee;font-weight:600">' . esc_html( $proyecto->post_title ) . '</td></tr>
						<tr><td style="padding:8px;border-bottom:1px solid #eee;color:#666">Fecha programada</td><td style="padding:8px;border-bottom:1px solid #eee;font-weight:600;color:#d63638">' . esc_html( $fecha_fmt ) . '</td></tr>
						<tr><td style="padding:8px;border-bottom:1px solid #eee;color:#666">Días restantes</td><td style="padding:8px;border-bottom:1px solid #eee;font-weight:600;color:#d63638">' . esc_html( $dias ) . ' días</td></tr>
						<tr><td style="padding:8px;color:#666">Recurrencia</td><td style="padding:8px">' . esc_html( $rec_label ) . '</td></tr>
					</table>
					<p style="text-align:center;margin-top:24px">
						<a href="' . esc_url( $admin_url ) . '" style="background:#1d5fa6;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;font-weight:600">Ver proyecto →</a>
					</p>
				</div>
				<div style="background:#f9f9f9;padding:16px 32px;text-align:center;color:#888;font-size:12px">
					' . esc_html( get_option( 'blogname' ) ) . ' · Gestión de Mantenimiento
				</div>
			</div>
		</body></html>';
	}

	/* ------------------------------------------------------------------ */
	/* Enviar informe del parte                                              */
	/* ------------------------------------------------------------------ */
	public static function send_report_email( $parte_id ) {
		$settings    = get_option( 'wmpro_settings', [] );
		$admin_email = $settings['admin_email'] ?? get_option( 'admin_email' );
		$from_name   = $settings['from_name']   ?? get_option( 'blogname' );

		$parte      = get_post( $parte_id );
		$proy_id    = get_post_meta( $parte_id, '_wmpro_proyecto_id', true );
		$proyecto   = $proy_id ? get_post( $proy_id ) : null;
		$email_cli  = $proy_id ? get_post_meta( $proy_id, '_wmpro_email_contacto', true ) : '';

		$to = array_filter( [ $admin_email, $email_cli ] );

		$subject = sprintf( 'Parte de mantenimiento: %s — %s',
			$proyecto ? $proyecto->post_title : 'Sin proyecto',
			date_i18n( 'd/m/Y', strtotime( $parte->post_date ) )
		);

		ob_start();
		include WMPRO_TEMPLATES . 'email-reporte.php';
		$body = ob_get_clean();

		$headers = [
			"From: {$from_name} <{$admin_email}>",
			'Content-Type: text/html; charset=UTF-8',
		];

		$sent = wp_mail( $to, $subject, $body, $headers );
		return $sent;
	}

	/* ------------------------------------------------------------------ */
	/* Calcular próxima fecha de recurrencia                                 */
	/* ------------------------------------------------------------------ */
	public static function advance_recurrence( $proyecto_id ) {
		$recurrencia   = get_post_meta( $proyecto_id, '_wmpro_recurrencia', true );
		$fecha_proxima = get_post_meta( $proyecto_id, '_wmpro_fecha_proxima', true );

		if ( $recurrencia === 'ninguna' || ! $fecha_proxima ) return;

		$dt  = new DateTime( $fecha_proxima );
		$map = [
			'semanal'    => '+1 week',
			'quincenal'  => '+2 weeks',
			'mensual'    => '+1 month',
			'trimestral' => '+3 months',
			'semestral'  => '+6 months',
			'anual'      => '+1 year',
		];

		$modifier = $map[ $recurrencia ] ?? null;
		if ( ! $modifier ) return;

		$dt->modify( $modifier );
		update_post_meta( $proyecto_id, '_wmpro_fecha_proxima', $dt->format( 'Y-m-d' ) );
		update_post_meta( $proyecto_id, '_wmpro_aviso_enviado', '0' );
	}
}

WMPRO_Cron::init();
