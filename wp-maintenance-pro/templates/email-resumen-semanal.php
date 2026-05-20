<?php
if ( ! defined( 'ABSPATH' ) ) exit;
// Variables disponibles: $partes, $proyectos_semana, $desde, $hasta
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"></head>
<body style="margin:0;padding:0;background:#f0f2f5;font-family:Arial,sans-serif">

<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f2f5;padding:24px 0">
<tr><td align="center">
<table width="620" cellpadding="0" cellspacing="0" style="max-width:620px;width:100%;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.08)">

	<tr>
		<td style="background:linear-gradient(135deg,#1d5fa6,#0d3d6e);padding:28px 36px;color:#fff">
			<div style="font-size:22px;font-weight:700">📅 Resumen Semanal de Mantenimiento</div>
			<div style="font-size:14px;opacity:.85;margin-top:4px">
				Semana del <?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $desde ) ) ); ?>
				al <?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $hasta ) ) ); ?>
			</div>
		</td>
	</tr>

	<!-- Partes programados -->
	<?php if ( ! empty( $partes ) ) : ?>
	<tr>
		<td style="padding:28px 36px 0">
			<div style="font-size:16px;font-weight:700;margin-bottom:16px;color:#1d2327">
				🔧 Partes programados esta semana (<?php echo count( $partes ); ?>)
			</div>
			<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse">
				<tr style="background:#f8f9fa">
					<th style="padding:10px 14px;text-align:left;font-size:12px;color:#646970;border-bottom:2px solid #e0e0e0">Proyecto</th>
					<th style="padding:10px 14px;text-align:left;font-size:12px;color:#646970;border-bottom:2px solid #e0e0e0">Parte</th>
					<th style="padding:10px 14px;text-align:center;font-size:12px;color:#646970;border-bottom:2px solid #e0e0e0">Fecha</th>
					<th style="padding:10px 14px;text-align:center;font-size:12px;color:#646970;border-bottom:2px solid #e0e0e0">Estado</th>
				</tr>
				<?php foreach ( $partes as $i => $p ) :
					$proy_id  = get_post_meta( $p->ID, '_wmpro_proyecto_id', true );
					$proy     = $proy_id ? get_post( $proy_id ) : null;
					$estado   = get_post_meta( $p->ID, '_wmpro_estado', true ) ?: 'pendiente';
					$fecha    = get_post_meta( $p->ID, '_wmpro_fecha_prog', true );
					$estados  = WMPRO_CPT_Parte::get_estados();
					$est_lbl  = strip_tags( $estados[ $estado ] ?? $estado );
					$est_col  = [ 'pendiente' => '#8c8f94', 'en_proceso' => '#996800', 'completado' => '#2e7d32', 'enviado' => '#1565c0', 'cancelado' => '#b71c1c' ][ $estado ] ?? '#333';
					$page_url = add_query_arg( 'parte_id', $p->ID, get_permalink( get_option( 'wmpro_parte_page_id' ) ) );
					?>
					<tr style="<?php echo $i % 2 === 1 ? 'background:#fafafa' : ''; ?>">
						<td style="padding:10px 14px;font-size:13px;border-bottom:1px solid #f0f0f0;color:#646970"><?php echo esc_html( $proy ? $proy->post_title : '—' ); ?></td>
						<td style="padding:10px 14px;font-size:13px;border-bottom:1px solid #f0f0f0"><a href="<?php echo esc_url( $page_url ); ?>" style="color:#1d5fa6"><?php echo esc_html( $p->post_title ); ?></a></td>
						<td style="padding:10px 14px;font-size:13px;border-bottom:1px solid #f0f0f0;text-align:center"><?php echo $fecha ? esc_html( date_i18n( 'd/m', strtotime( $fecha ) ) ) : '—'; ?></td>
						<td style="padding:10px 14px;font-size:12px;border-bottom:1px solid #f0f0f0;text-align:center">
							<span style="color:<?php echo esc_attr( $est_col ); ?>;font-weight:600"><?php echo esc_html( $est_lbl ); ?></span>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
		</td>
	</tr>
	<?php endif; ?>

	<!-- Proyectos con fecha próxima -->
	<?php if ( ! empty( $proyectos_semana ) ) : ?>
	<tr>
		<td style="padding:24px 36px 0">
			<div style="font-size:16px;font-weight:700;margin-bottom:16px;color:#1d2327">
				📅 Proyectos con mantenimiento esta semana (<?php echo count( $proyectos_semana ); ?>)
			</div>
			<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse">
				<?php foreach ( $proyectos_semana as $i => $proy ) :
					$fecha    = get_post_meta( $proy->ID, '_wmpro_fecha_proxima', true );
					$rec      = get_post_meta( $proy->ID, '_wmpro_recurrencia', true );
					$rec_lbl  = [ 'semanal' => 'Semanal', 'quincenal' => 'Quincenal', 'mensual' => 'Mensual', 'trimestral' => 'Trimestral', 'semestral' => 'Semestral', 'anual' => 'Anual' ][ $rec ] ?? $rec;
					$edit_url = admin_url( "post.php?post={$proy->ID}&action=edit" );
					?>
					<tr style="<?php echo $i % 2 === 1 ? 'background:#fafafa' : ''; ?>">
						<td style="padding:10px 14px;font-size:13px;border-bottom:1px solid #f0f0f0;font-weight:600">
							<a href="<?php echo esc_url( $edit_url ); ?>" style="color:#1d5fa6"><?php echo esc_html( $proy->post_title ); ?></a>
						</td>
						<td style="padding:10px 14px;font-size:13px;border-bottom:1px solid #f0f0f0;color:#d63638;font-weight:600">
							📅 <?php echo $fecha ? esc_html( date_i18n( 'd/m/Y', strtotime( $fecha ) ) ) : '—'; ?>
						</td>
						<td style="padding:10px 14px;font-size:12px;border-bottom:1px solid #f0f0f0;color:#646970"><?php echo esc_html( $rec_lbl ); ?></td>
					</tr>
				<?php endforeach; ?>
			</table>
		</td>
	</tr>
	<?php endif; ?>

	<tr>
		<td style="padding:28px 36px">
			<hr style="border:none;border-top:1px solid #e0e0e0;margin-bottom:20px">
			<p style="text-align:center;margin:0">
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=wmpro_parte' ) ); ?>"
				   style="background:#1d5fa6;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:600;font-size:14px">
					Ver todos los partes →
				</a>
			</p>
			<p style="text-align:center;font-size:12px;color:#8c8f94;margin-top:20px">
				<?php echo esc_html( get_option( 'blogname' ) ); ?> · Resumen generado el <?php echo esc_html( date_i18n( 'd/m/Y H:i' ) ); ?>
			</p>
		</td>
	</tr>

</table>
</td></tr>
</table>
</body>
</html>
