<?php
if ( ! defined( 'ABSPATH' ) ) exit;
// Variables disponibles: $parte_id, $parte, $proyecto

$proy_id      = get_post_meta( $parte_id, '_wmpro_proyecto_id', true );
$proyecto_loc = $proy_id ? get_post( $proy_id ) : null;
$tecnico_id   = get_post_meta( $parte_id, '_wmpro_tecnico', true );
$tecnico      = $tecnico_id ? get_user_by( 'ID', $tecnico_id ) : null;
$tareas       = get_post_meta( $parte_id, '_wmpro_tareas', true ) ?: [];
$fotos        = get_post_meta( $parte_id, '_wmpro_fotos', true ) ?: [];
$firma_url    = get_post_meta( $parte_id, '_wmpro_firma_url', true );
$nom_firmante = get_post_meta( $parte_id, '_wmpro_nombre_firmante', true );
$firma_fecha  = get_post_meta( $parte_id, '_wmpro_firma_fecha', true );
$fecha_ini    = get_post_meta( $parte_id, '_wmpro_fecha_inicio', true );
$fecha_fin    = get_post_meta( $parte_id, '_wmpro_fecha_fin', true );
$observ       = get_post_meta( $parte_id, '_wmpro_observaciones', true );
$prioridad    = get_post_meta( $parte_id, '_wmpro_prioridad', true ) ?: 'normal';

$total  = count( $tareas );
$hechas = count( array_filter( $tareas, fn( $t ) => $t['completada'] ) );
$pct    = $total > 0 ? round( $hechas / $total * 100 ) : 0;

// Calcular duración
$duracion = '';
if ( $fecha_ini && $fecha_fin ) {
	$diff = strtotime( $fecha_fin ) - strtotime( $fecha_ini );
	$h    = floor( $diff / 3600 );
	$m    = floor( ( $diff % 3600 ) / 60 );
	$duracion = $h > 0 ? "{$h}h {$m}min" : "{$m} min";
}

$prioridad_map = WMPRO_CPT_Parte::get_prioridades();
$prio_label    = strip_tags( $prioridad_map[ $prioridad ] ?? $prioridad );
$prio_color    = [ 'baja' => '#2e7d32', 'normal' => '#1565c0', 'alta' => '#e65100', 'urgente' => '#b71c1c' ][ $prioridad ] ?? '#333';

$addr = '';
if ( $proy_id ) {
	$dir  = get_post_meta( $proy_id, '_wmpro_direccion', true );
	$city = get_post_meta( $proy_id, '_wmpro_ciudad', true );
	$cp   = get_post_meta( $proy_id, '_wmpro_cp', true );
	$addr = implode( ', ', array_filter( [ $dir, $cp, $city ] ) );
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Parte de Mantenimiento</title>
</head>
<body style="margin:0;padding:0;background:#f0f2f5;font-family:Arial,Helvetica,sans-serif;color:#1d2327">

<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f2f5;padding:24px 0">
<tr><td align="center">
<table width="640" cellpadding="0" cellspacing="0" style="max-width:640px;width:100%;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.08)">

	<!-- Header -->
	<tr>
		<td style="background:linear-gradient(135deg,#1d5fa6 0%,#0d3d6e 100%);padding:32px 40px;color:#fff">
			<table width="100%" cellpadding="0" cellspacing="0">
				<tr>
					<td>
						<div style="font-size:28px;font-weight:700;margin-bottom:4px">🔧 Parte de Mantenimiento</div>
						<div style="font-size:15px;opacity:.85"><?php echo esc_html( get_option( 'blogname' ) ); ?></div>
					</td>
					<td align="right" style="vertical-align:top">
						<div style="background:rgba(255,255,255,.15);border-radius:8px;padding:8px 16px;text-align:center">
							<div style="font-size:11px;opacity:.8;text-transform:uppercase;letter-spacing:1px">Parte Nº</div>
							<div style="font-size:22px;font-weight:700"><?php echo esc_html( $parte_id ); ?></div>
						</div>
					</td>
				</tr>
			</table>
		</td>
	</tr>

	<!-- Info proyecto -->
	<tr>
		<td style="padding:32px 40px 0">
			<table width="100%" cellpadding="0" cellspacing="0" style="background:#f8f9fa;border-radius:8px;border-left:4px solid #1d5fa6;padding:0">
				<tr>
					<td style="padding:20px 24px">
						<div style="font-size:20px;font-weight:700;color:#1d2327;margin-bottom:8px">
							<?php echo esc_html( $proyecto_loc ? $proyecto_loc->post_title : $parte->post_title ); ?>
						</div>
						<?php if ( $addr ) : ?>
							<div style="color:#646970;font-size:14px;margin-bottom:4px">📍 <?php echo esc_html( $addr ); ?></div>
						<?php endif; ?>
						<div style="margin-top:8px">
							<span style="display:inline-block;background:<?php echo esc_attr( $prio_color ); ?>1a;color:<?php echo esc_attr( $prio_color ); ?>;border:1px solid <?php echo esc_attr( $prio_color ); ?>4d;border-radius:20px;padding:3px 12px;font-size:12px;font-weight:600">
								Prioridad: <?php echo esc_html( $prio_label ); ?>
							</span>
						</div>
					</td>
				</tr>
			</table>
		</td>
	</tr>

	<!-- Datos del parte -->
	<tr>
		<td style="padding:24px 40px 0">
			<table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e0e0e0;border-radius:8px;overflow:hidden">
				<tr style="background:#f8f9fa">
					<td colspan="2" style="padding:12px 20px;font-weight:700;font-size:14px;color:#646970;text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid #e0e0e0">
						📋 Información del parte
					</td>
				</tr>
				<?php $rows = [
					['👷 Técnico',       $tecnico ? $tecnico->display_name : 'Sin asignar'],
					['📅 Fecha inicio',  $fecha_ini ? date_i18n( 'd/m/Y H:i', strtotime( $fecha_ini ) ) : '—'],
					['🏁 Fecha fin',     $fecha_fin ? date_i18n( 'd/m/Y H:i', strtotime( $fecha_fin ) ) : '—'],
					['⏱️ Duración',      $duracion ?: '—'],
					['✅ Tareas',        "{$hechas}/{$total} completadas ({$pct}%)"],
				];
				foreach ( $rows as $i => $row ) : ?>
					<tr style="<?php echo $i % 2 === 1 ? 'background:#fafafa' : ''; ?>">
						<td style="padding:10px 20px;color:#646970;font-size:14px;width:180px;border-bottom:1px solid #f0f0f0"><?php echo esc_html( $row[0] ); ?></td>
						<td style="padding:10px 20px;font-size:14px;font-weight:600;border-bottom:1px solid #f0f0f0"><?php echo esc_html( $row[1] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</table>
		</td>
	</tr>

	<!-- Checklist -->
	<?php if ( ! empty( $tareas ) ) : ?>
	<tr>
		<td style="padding:24px 40px 0">
			<table width="100%" cellpadding="0" cellspacing="0">
				<tr>
					<td style="padding-bottom:12px">
						<span style="font-size:16px;font-weight:700">✅ Checklist de tareas</span>
						<span style="font-size:13px;color:#646970;margin-left:8px"><?php echo esc_html( "{$hechas}/{$total}" ); ?></span>
					</td>
				</tr>
				<?php foreach ( $tareas as $t ) :
					$done  = $t['completada'];
					$color = $done ? '#2e7d32' : '#d63638';
					$icon  = $done ? '✅' : '⬜';
					?>
					<tr>
						<td style="padding:8px 12px;border-bottom:1px solid #f0f0f0">
							<span style="margin-right:8px;font-size:16px"><?php echo $icon; ?></span>
							<span style="font-size:14px;<?php echo $done ? 'color:#1d2327' : 'color:#8c8f94'; ?>">
								<?php echo esc_html( $t['texto'] ); ?>
							</span>
							<?php if ( $done && $t['completada_en'] ) : ?>
								<span style="float:right;font-size:11px;color:#8c8f94">
									<?php echo esc_html( date_i18n( 'd/m H:i', strtotime( $t['completada_en'] ) ) ); ?>
								</span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
		</td>
	</tr>
	<?php endif; ?>

	<!-- Observaciones -->
	<?php if ( $observ ) : ?>
	<tr>
		<td style="padding:24px 40px 0">
			<div style="background:#fffde7;border-left:4px solid #f9a825;border-radius:4px;padding:16px 20px">
				<div style="font-weight:700;font-size:14px;margin-bottom:8px;color:#7b5c00">📝 Observaciones</div>
				<div style="font-size:14px;line-height:1.6;color:#1d2327"><?php echo nl2br( esc_html( $observ ) ); ?></div>
			</div>
		</td>
	</tr>
	<?php endif; ?>

	<!-- Fotos -->
	<?php if ( ! empty( $fotos ) ) : ?>
	<tr>
		<td style="padding:24px 40px 0">
			<div style="font-size:16px;font-weight:700;margin-bottom:12px">📷 Fotos adjuntas (<?php echo count( $fotos ); ?>)</div>
			<table width="100%" cellpadding="0" cellspacing="0">
				<tr>
					<?php $cols = 0; foreach ( $fotos as $fid ) :
						$url   = wp_get_attachment_url( $fid );
						$thumb = wp_get_attachment_image_url( $fid, 'medium' );
						if ( ! $url ) continue;
						?>
						<td style="padding:4px;width:33.3%;vertical-align:top">
							<a href="<?php echo esc_url( $url ); ?>" style="display:block">
								<img src="<?php echo esc_url( $thumb ); ?>" alt="" style="width:100%;height:140px;object-fit:cover;border-radius:6px;border:1px solid #e0e0e0">
							</a>
						</td>
						<?php $cols++;
						if ( $cols % 3 === 0 ) echo '</tr><tr>';
					endforeach; ?>
				</tr>
			</table>
		</td>
	</tr>
	<?php endif; ?>

	<!-- Firma -->
	<?php if ( $firma_url ) : ?>
	<tr>
		<td style="padding:24px 40px 0">
			<div style="border:1px solid #e0e0e0;border-radius:8px;overflow:hidden">
				<div style="background:#f8f9fa;padding:12px 20px;font-weight:700;font-size:14px;border-bottom:1px solid #e0e0e0">
					✍️ Firma del cliente
				</div>
				<div style="padding:20px;text-align:center">
					<img src="<?php echo esc_url( $firma_url ); ?>" alt="Firma del cliente" style="max-width:300px;height:auto;border:1px solid #e0e0e0;border-radius:4px;padding:8px">
					<?php if ( $nom_firmante ) : ?>
						<p style="margin:12px 0 0;font-size:14px;color:#1d2327">
							Firmado por: <strong><?php echo esc_html( $nom_firmante ); ?></strong>
						</p>
					<?php endif; ?>
					<?php if ( $firma_fecha ) : ?>
						<p style="margin:4px 0 0;font-size:12px;color:#8c8f94">
							<?php echo esc_html( date_i18n( 'd/m/Y \a \l\a\s H:i', strtotime( $firma_fecha ) ) ); ?>
						</p>
					<?php endif; ?>
				</div>
			</div>
		</td>
	</tr>
	<?php endif; ?>

	<!-- Footer -->
	<tr>
		<td style="padding:32px 40px;margin-top:24px">
			<hr style="border:none;border-top:1px solid #e0e0e0;margin:0 0 24px">
			<table width="100%" cellpadding="0" cellspacing="0">
				<tr>
					<td style="font-size:12px;color:#8c8f94">
						<?php echo esc_html( get_option( 'blogname' ) ); ?> · Generado el <?php echo esc_html( date_i18n( 'd/m/Y H:i' ) ); ?>
					</td>
					<td align="right" style="font-size:12px;color:#8c8f94">
						Parte ID: #<?php echo esc_html( $parte_id ); ?>
					</td>
				</tr>
			</table>
		</td>
	</tr>

</table>
</td></tr>
</table>

</body>
</html>
