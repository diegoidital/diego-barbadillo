<?php
/**
 * Email template: Parte de mantenimiento report
 *
 * Variables available:
 *   $actuacion_id, $actuacion (WP_Post), $proyecto (WP_Post|null),
 *   $tipo_term (WP_Term|null), $tecnico (WP_User|null),
 *   $tareas (array), $fotos (array of attach IDs),
 *   $firma_tecnico_url, $firma_cliente_url,
 *   $nombre_firmante_tecnico, $nombre_firmante_cliente, $firma_fecha,
 *   $fecha_ini, $fecha_fin, $observ
 *
 * @package Lezned_Maintenance_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$proyecto_title = $proyecto ? get_the_title( $proyecto ) : __( '—', 'lezned-maintenance' );
$tipo_name      = ( $tipo_term && ! is_wp_error( $tipo_term ) ) ? $tipo_term->name : '—';
$tecnico_name   = $tecnico ? $tecnico->display_name : ( isset( $nombre_firmante_tecnico ) ? $nombre_firmante_tecnico : '—' );

$fecha_ini_fmt  = ! empty( $fecha_ini ) ? date_i18n( 'd/m/Y H:i', strtotime( $fecha_ini ) ) : '—';
$fecha_fin_fmt  = ! empty( $fecha_fin ) ? date_i18n( 'd/m/Y H:i', strtotime( $fecha_fin ) ) : '—';
$firma_fecha_fmt = ! empty( $firma_fecha ) ? date_i18n( 'd/m/Y H:i', strtotime( $firma_fecha ) ) : date_i18n( 'd/m/Y' );

// Duration
$duracion = '—';
if ( ! empty( $fecha_ini ) && ! empty( $fecha_fin ) ) {
    $diff_seconds = strtotime( $fecha_fin ) - strtotime( $fecha_ini );
    if ( $diff_seconds > 0 ) {
        $h = floor( $diff_seconds / 3600 );
        $m = floor( ( $diff_seconds % 3600 ) / 60 );
        $duracion = $h > 0 ? sprintf( '%dh %02dmin', $h, $m ) : sprintf( '%dmin', $m );
    }
}

$hechas = count( array_filter( $tareas, fn( $t ) => ! empty( $t['completada'] ) ) );
$total  = count( $tareas );
$pct    = $total > 0 ? round( $hechas / $total * 100 ) : 0;

$foto_count = count( $fotos );

$blog_name = get_bloginfo( 'name' );
$blog_url  = get_bloginfo( 'url' );

// Colors
$c_primary = '#1a73e8';
$c_dark    = '#1a1a2e';
$c_light   = '#f8f9fa';
$c_border  = '#e0e0e0';
$c_success = '#28a745';
$c_muted   = '#6c757d';
$c_white   = '#ffffff';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php esc_html_e( 'Parte de Mantenimiento', 'lezned-maintenance' ); ?> #<?php echo esc_html( $actuacion_id ); ?></title>
</head>
<body style="margin:0;padding:0;background:#f0f2f5;font-family:'Helvetica Neue',Arial,sans-serif;color:#333;">

<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f2f5;padding:20px 0;">
  <tr>
    <td align="center">
      <table width="620" cellpadding="0" cellspacing="0" style="max-width:620px;width:100%;">

        <?php /* ---- Header ---- */ ?>
        <tr>
          <td style="background:linear-gradient(135deg,<?php echo $c_primary; ?> 0%,#0d47a1 100%);border-radius:12px 12px 0 0;padding:32px 40px;text-align:center;">
            <h1 style="margin:0;color:<?php echo $c_white; ?>;font-size:26px;font-weight:700;letter-spacing:-0.5px;">
              <?php esc_html_e( 'Parte de Mantenimiento', 'lezned-maintenance' ); ?>
            </h1>
            <p style="margin:8px 0 0;color:rgba(255,255,255,0.85);font-size:14px;">
              <?php echo esc_html( $blog_name ); ?>
            </p>
            <div style="display:inline-block;background:rgba(255,255,255,0.2);border-radius:20px;padding:4px 14px;margin-top:12px;">
              <span style="color:<?php echo $c_white; ?>;font-size:13px;font-weight:600;">
                #<?php echo esc_html( $actuacion_id ); ?>
              </span>
            </div>
          </td>
        </tr>

        <?php /* ---- Project info block ---- */ ?>
        <tr>
          <td style="background:<?php echo $c_white; ?>;padding:0 40px;">
            <table width="100%" cellpadding="0" cellspacing="0" style="border-bottom:1px solid <?php echo $c_border; ?>;padding:24px 0;">
              <tr>
                <td>
                  <p style="margin:0 0 4px;font-size:11px;text-transform:uppercase;letter-spacing:1px;color:<?php echo $c_muted; ?>;font-weight:600;">
                    <?php esc_html_e( 'Proyecto', 'lezned-maintenance' ); ?>
                  </p>
                  <h2 style="margin:0;font-size:20px;font-weight:700;color:<?php echo $c_dark; ?>;">
                    <?php echo esc_html( $proyecto_title ); ?>
                  </h2>
                </td>
                <td align="right" style="vertical-align:top;">
                  <span style="display:inline-block;background:#e8f0fe;color:<?php echo $c_primary; ?>;border-radius:6px;padding:4px 12px;font-size:13px;font-weight:600;">
                    <?php echo esc_html( $tipo_name ); ?>
                  </span>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <?php /* ---- Data table ---- */ ?>
        <tr>
          <td style="background:<?php echo $c_white; ?>;padding:24px 40px 0;">
            <table width="100%" cellpadding="0" cellspacing="0">
              <tr>
                <td colspan="2">
                  <h3 style="margin:0 0 16px;font-size:13px;text-transform:uppercase;letter-spacing:1px;color:<?php echo $c_muted; ?>;font-weight:600;">
                    <?php esc_html_e( 'Datos de la actuación', 'lezned-maintenance' ); ?>
                  </h3>
                </td>
              </tr>
              <tr>
                <td width="50%" style="vertical-align:top;padding-bottom:16px;">
                  <table cellpadding="0" cellspacing="0">
                    <tr>
                      <td style="padding-right:8px;vertical-align:top;">
                        <div style="width:36px;height:36px;background:#e8f0fe;border-radius:8px;display:flex;align-items:center;justify-content:center;text-align:center;line-height:36px;font-size:16px;">&#128100;</div>
                      </td>
                      <td style="vertical-align:top;">
                        <p style="margin:0;font-size:11px;color:<?php echo $c_muted; ?>;"><?php esc_html_e( 'Técnico', 'lezned-maintenance' ); ?></p>
                        <p style="margin:2px 0 0;font-size:14px;font-weight:600;color:<?php echo $c_dark; ?>;"><?php echo esc_html( $tecnico_name ); ?></p>
                      </td>
                    </tr>
                  </table>
                </td>
                <td width="50%" style="vertical-align:top;padding-bottom:16px;">
                  <table cellpadding="0" cellspacing="0">
                    <tr>
                      <td style="padding-right:8px;vertical-align:top;">
                        <div style="width:36px;height:36px;background:#e8f0fe;border-radius:8px;display:flex;align-items:center;justify-content:center;text-align:center;line-height:36px;font-size:16px;">&#128197;</div>
                      </td>
                      <td style="vertical-align:top;">
                        <p style="margin:0;font-size:11px;color:<?php echo $c_muted; ?>;"><?php esc_html_e( 'Tipo de trabajo', 'lezned-maintenance' ); ?></p>
                        <p style="margin:2px 0 0;font-size:14px;font-weight:600;color:<?php echo $c_dark; ?>;"><?php echo esc_html( $tipo_name ); ?></p>
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
              <tr>
                <td width="50%" style="vertical-align:top;padding-bottom:16px;">
                  <table cellpadding="0" cellspacing="0">
                    <tr>
                      <td style="padding-right:8px;vertical-align:top;">
                        <div style="width:36px;height:36px;background:#e8f4f8;border-radius:8px;text-align:center;line-height:36px;font-size:16px;">&#9200;</div>
                      </td>
                      <td style="vertical-align:top;">
                        <p style="margin:0;font-size:11px;color:<?php echo $c_muted; ?>;"><?php esc_html_e( 'Inicio', 'lezned-maintenance' ); ?></p>
                        <p style="margin:2px 0 0;font-size:14px;font-weight:600;color:<?php echo $c_dark; ?>;"><?php echo esc_html( $fecha_ini_fmt ); ?></p>
                      </td>
                    </tr>
                  </table>
                </td>
                <td width="50%" style="vertical-align:top;padding-bottom:16px;">
                  <table cellpadding="0" cellspacing="0">
                    <tr>
                      <td style="padding-right:8px;vertical-align:top;">
                        <div style="width:36px;height:36px;background:#e8f4f8;border-radius:8px;text-align:center;line-height:36px;font-size:16px;">&#9201;</div>
                      </td>
                      <td style="vertical-align:top;">
                        <p style="margin:0;font-size:11px;color:<?php echo $c_muted; ?>;"><?php esc_html_e( 'Fin', 'lezned-maintenance' ); ?></p>
                        <p style="margin:2px 0 0;font-size:14px;font-weight:600;color:<?php echo $c_dark; ?>;"><?php echo esc_html( $fecha_fin_fmt ); ?></p>
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
              <tr>
                <td colspan="2" style="padding-bottom:24px;">
                  <table cellpadding="0" cellspacing="0">
                    <tr>
                      <td style="padding-right:8px;vertical-align:top;">
                        <div style="width:36px;height:36px;background:#edf7ed;border-radius:8px;text-align:center;line-height:36px;font-size:16px;">&#8987;</div>
                      </td>
                      <td style="vertical-align:top;">
                        <p style="margin:0;font-size:11px;color:<?php echo $c_muted; ?>;"><?php esc_html_e( 'Duración', 'lezned-maintenance' ); ?></p>
                        <p style="margin:2px 0 0;font-size:14px;font-weight:600;color:<?php echo $c_dark; ?>;"><?php echo esc_html( $duracion ); ?></p>
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <?php /* ---- Checklist ---- */ ?>
        <tr>
          <td style="background:<?php echo $c_white; ?>;padding:0 40px 24px;">
            <div style="background:<?php echo $c_light; ?>;border-radius:10px;padding:20px;">
              <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                  <td>
                    <h3 style="margin:0 0 4px;font-size:15px;font-weight:700;color:<?php echo $c_dark; ?>;">
                      <?php esc_html_e( 'Tareas', 'lezned-maintenance' ); ?>
                    </h3>
                    <p style="margin:0 0 14px;font-size:12px;color:<?php echo $c_muted; ?>;">
                      <?php printf( esc_html__( '%d de %d completadas (%d%%)', 'lezned-maintenance' ), $hechas, $total, $pct ); ?>
                    </p>
                  </td>
                </tr>
                <?php /* Progress bar */ ?>
                <tr>
                  <td style="padding-bottom:16px;">
                    <div style="background:#dee2e6;border-radius:4px;height:8px;">
                      <div style="background:<?php echo $c_primary; ?>;border-radius:4px;height:8px;width:<?php echo esc_attr( $pct ); ?>%;"></div>
                    </div>
                  </td>
                </tr>
                <?php foreach ( $tareas as $tarea ) :
                    $done = ! empty( $tarea['completada'] );
                    $icon = $done ? '&#9989;' : '&#11036;';
                    $text_style = $done ? 'text-decoration:line-through;color:' . $c_muted . ';' : 'color:' . $c_dark . ';';
                ?>
                <tr>
                  <td style="padding:6px 0;border-bottom:1px solid <?php echo $c_border; ?>;">
                    <table width="100%" cellpadding="0" cellspacing="0">
                      <tr>
                        <td width="28" style="font-size:16px;"><?php echo $icon; ?></td>
                        <td style="font-size:14px;<?php echo $text_style; ?>"><?php echo esc_html( $tarea['texto'] ); ?></td>
                        <?php if ( $done && ! empty( $tarea['completada_en'] ) ) : ?>
                        <td align="right" style="font-size:11px;color:<?php echo $c_muted; ?>;white-space:nowrap;">
                          <?php echo esc_html( date_i18n( 'H:i', strtotime( $tarea['completada_en'] ) ) ); ?>
                        </td>
                        <?php endif; ?>
                      </tr>
                    </table>
                  </td>
                </tr>
                <?php endforeach; ?>
              </table>
            </div>
          </td>
        </tr>

        <?php /* ---- Observaciones ---- */ ?>
        <?php if ( ! empty( $observ ) ) : ?>
        <tr>
          <td style="background:<?php echo $c_white; ?>;padding:0 40px 24px;">
            <div style="border-left:4px solid <?php echo $c_primary; ?>;background:#f5f8ff;border-radius:0 8px 8px 0;padding:16px 20px;">
              <p style="margin:0 0 8px;font-size:12px;text-transform:uppercase;letter-spacing:1px;color:<?php echo $c_primary; ?>;font-weight:700;">
                <?php esc_html_e( 'Observaciones', 'lezned-maintenance' ); ?>
              </p>
              <p style="margin:0;font-size:14px;color:<?php echo $c_dark; ?>;line-height:1.6;white-space:pre-wrap;"><?php echo esc_html( $observ ); ?></p>
            </div>
          </td>
        </tr>
        <?php endif; ?>

        <?php /* ---- Fotos ---- */ ?>
        <?php if ( $foto_count > 0 ) : ?>
        <tr>
          <td style="background:<?php echo $c_white; ?>;padding:0 40px 24px;">
            <div style="background:#fff8e1;border:1px solid #ffecb3;border-radius:8px;padding:14px 18px;">
              <p style="margin:0;font-size:14px;color:#795548;">
                &#128247; <?php printf( esc_html( _n( '%d foto adjunta', '%d fotos adjuntas', $foto_count, 'lezned-maintenance' ) ), $foto_count ); ?>
              </p>
              <p style="margin:6px 0 0;font-size:12px;color:<?php echo $c_muted; ?>;">
                <?php esc_html_e( 'Las fotos se incluyen como archivos adjuntos en este email.', 'lezned-maintenance' ); ?>
              </p>
            </div>
          </td>
        </tr>
        <?php endif; ?>

        <?php /* ---- Firmas ---- */ ?>
        <?php if ( ! empty( $firma_tecnico_url ) || ! empty( $firma_cliente_url ) ) : ?>
        <tr>
          <td style="background:<?php echo $c_white; ?>;padding:0 40px 24px;">
            <h3 style="margin:0 0 16px;font-size:13px;text-transform:uppercase;letter-spacing:1px;color:<?php echo $c_muted; ?>;font-weight:600;">
              <?php esc_html_e( 'Firmas', 'lezned-maintenance' ); ?>
            </h3>
            <table width="100%" cellpadding="0" cellspacing="0">
              <tr>
                <?php if ( ! empty( $firma_tecnico_url ) ) : ?>
                <td width="50%" style="vertical-align:top;padding-right:12px;">
                  <div style="background:<?php echo $c_light; ?>;border:1px solid <?php echo $c_border; ?>;border-radius:8px;padding:16px;text-align:center;">
                    <p style="margin:0 0 10px;font-size:11px;text-transform:uppercase;letter-spacing:1px;color:<?php echo $c_primary; ?>;font-weight:600;">
                      <?php esc_html_e( 'Técnico', 'lezned-maintenance' ); ?>
                    </p>
                    <img src="<?php echo esc_url( $firma_tecnico_url ); ?>"
                         alt="<?php esc_attr_e( 'Firma técnico', 'lezned-maintenance' ); ?>"
                         style="max-width:160px;height:auto;display:block;margin:0 auto 10px;">
                    <?php if ( ! empty( $nombre_firmante_tecnico ) ) : ?>
                    <p style="margin:0;font-size:13px;font-weight:700;color:<?php echo $c_dark; ?>;"><?php echo esc_html( $nombre_firmante_tecnico ); ?></p>
                    <?php endif; ?>
                    <?php if ( ! empty( $firma_fecha ) ) : ?>
                    <p style="margin:4px 0 0;font-size:11px;color:<?php echo $c_muted; ?>;"><?php echo esc_html( $firma_fecha_fmt ); ?></p>
                    <?php endif; ?>
                  </div>
                </td>
                <?php endif; ?>

                <?php if ( ! empty( $firma_cliente_url ) ) : ?>
                <td width="50%" style="vertical-align:top;padding-left:12px;">
                  <div style="background:<?php echo $c_light; ?>;border:1px solid <?php echo $c_border; ?>;border-radius:8px;padding:16px;text-align:center;">
                    <p style="margin:0 0 10px;font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#2e7d32;font-weight:600;">
                      <?php esc_html_e( 'Cliente', 'lezned-maintenance' ); ?>
                    </p>
                    <img src="<?php echo esc_url( $firma_cliente_url ); ?>"
                         alt="<?php esc_attr_e( 'Firma cliente', 'lezned-maintenance' ); ?>"
                         style="max-width:160px;height:auto;display:block;margin:0 auto 10px;">
                    <?php if ( ! empty( $nombre_firmante_cliente ) ) : ?>
                    <p style="margin:0;font-size:13px;font-weight:700;color:<?php echo $c_dark; ?>;"><?php echo esc_html( $nombre_firmante_cliente ); ?></p>
                    <?php endif; ?>
                  </div>
                </td>
                <?php endif; ?>
              </tr>
            </table>
          </td>
        </tr>
        <?php endif; ?>

        <?php /* ---- Footer ---- */ ?>
        <tr>
          <td style="background:#1a1a2e;border-radius:0 0 12px 12px;padding:24px 40px;text-align:center;">
            <p style="margin:0 0 6px;color:rgba(255,255,255,0.9);font-size:14px;font-weight:600;">
              <?php echo esc_html( $blog_name ); ?>
            </p>
            <p style="margin:0;color:rgba(255,255,255,0.5);font-size:12px;">
              <?php esc_html_e( 'Este mensaje ha sido generado automáticamente.', 'lezned-maintenance' ); ?>
              <br>
              <a href="<?php echo esc_url( $blog_url ); ?>" style="color:rgba(255,255,255,0.5);text-decoration:underline;">
                <?php echo esc_html( $blog_url ); ?>
              </a>
            </p>
          </td>
        </tr>

      </table>
    </td>
  </tr>
</table>

</body>
</html>
