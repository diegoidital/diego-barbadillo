<?php
/**
 * Email template: Weekly summary
 *
 * Variables available:
 *   $lunes    (timestamp)
 *   $domingo  (timestamp)
 *   $actuaciones_por_dia (array keyed 'Y-m-d' => [WP_Post,...])
 *   $proyectos_semana (array of WP_Post)
 *
 * @package Lezned_Maintenance_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$blog_name  = get_bloginfo( 'name' );
$blog_url   = get_bloginfo( 'url' );
$admin_url  = admin_url( 'edit.php?post_type=lznd_actuacion' );

$lunes_fmt  = date_i18n( 'd/m/Y', $lunes );
$domingo_fmt = date_i18n( 'd/m/Y', $domingo );

$dias_semana_es = [
    0 => 'Lunes',
    1 => 'Martes',
    2 => 'Miércoles',
    3 => 'Jueves',
    4 => 'Viernes',
    5 => 'Sábado',
    6 => 'Domingo',
];

// Total actuaciones this week
$total_actu = 0;
foreach ( $actuaciones_por_dia as $acts ) {
    $total_actu += count( $acts );
}

// Colors
$c_primary = '#1a73e8';
$c_dark    = '#1a1a2e';
$c_light   = '#f8f9fa';
$c_border  = '#e0e0e0';
$c_muted   = '#6c757d';
$c_white   = '#ffffff';
$c_green   = '#28a745';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php esc_html_e( 'Resumen Semanal', 'lezned-maintenance' ); ?></title>
</head>
<body style="margin:0;padding:0;background:#f0f2f5;font-family:'Helvetica Neue',Arial,sans-serif;color:#333;">

<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f2f5;padding:20px 0;">
  <tr>
    <td align="center">
      <table width="620" cellpadding="0" cellspacing="0" style="max-width:620px;width:100%;">

        <?php /* ---- Header ---- */ ?>
        <tr>
          <td style="background:linear-gradient(135deg,#0d47a1 0%,<?php echo $c_primary; ?> 100%);border-radius:12px 12px 0 0;padding:32px 40px;text-align:center;">
            <p style="margin:0 0 8px;color:rgba(255,255,255,0.7);font-size:12px;text-transform:uppercase;letter-spacing:2px;font-weight:600;">
              <?php echo esc_html( $blog_name ); ?>
            </p>
            <h1 style="margin:0;color:<?php echo $c_white; ?>;font-size:26px;font-weight:700;">
              <?php esc_html_e( 'Resumen Semanal', 'lezned-maintenance' ); ?>
            </h1>
            <p style="margin:12px 0 0;color:rgba(255,255,255,0.85);font-size:15px;">
              <?php echo esc_html( $lunes_fmt ); ?> &mdash; <?php echo esc_html( $domingo_fmt ); ?>
            </p>
          </td>
        </tr>

        <?php /* ---- Stats row ---- */ ?>
        <tr>
          <td style="background:<?php echo $c_white; ?>;padding:24px 40px 0;">
            <table width="100%" cellpadding="0" cellspacing="0">
              <tr>
                <td width="50%" style="text-align:center;padding:16px;background:<?php echo $c_light; ?>;border-radius:8px;margin-right:8px;">
                  <p style="margin:0;font-size:32px;font-weight:800;color:<?php echo $c_primary; ?>;"><?php echo esc_html( $total_actu ); ?></p>
                  <p style="margin:4px 0 0;font-size:12px;color:<?php echo $c_muted; ?>;text-transform:uppercase;letter-spacing:1px;">
                    <?php esc_html_e( 'Actuaciones', 'lezned-maintenance' ); ?>
                  </p>
                </td>
                <td width="8"></td>
                <td width="50%" style="text-align:center;padding:16px;background:<?php echo $c_light; ?>;border-radius:8px;">
                  <p style="margin:0;font-size:32px;font-weight:800;color:<?php echo $c_green; ?>;"><?php echo esc_html( count( $proyectos_semana ) ); ?></p>
                  <p style="margin:4px 0 0;font-size:12px;color:<?php echo $c_muted; ?>;text-transform:uppercase;letter-spacing:1px;">
                    <?php esc_html_e( 'Próximos mantenimientos', 'lezned-maintenance' ); ?>
                  </p>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <?php /* ---- Separator ---- */ ?>
        <tr>
          <td style="background:<?php echo $c_white; ?>;padding:24px 40px 0;">
            <hr style="border:none;border-top:1px solid <?php echo $c_border; ?>;margin:0;">
          </td>
        </tr>

        <?php /* ---- Daily schedule ---- */ ?>
        <tr>
          <td style="background:<?php echo $c_white; ?>;padding:24px 40px;">
            <h2 style="margin:0 0 20px;font-size:16px;font-weight:700;color:<?php echo $c_dark; ?>;">
              &#128197; <?php esc_html_e( 'Actuaciones de la semana', 'lezned-maintenance' ); ?>
            </h2>

            <?php
            for ( $i = 0; $i < 7; $i++ ) :
                $day_ts  = strtotime( '+' . $i . ' days', $lunes );
                $day_key = date( 'Y-m-d', $day_ts );
                $day_label = $dias_semana_es[ $i ] . ', ' . date_i18n( 'd/m', $day_ts );
                $acts_day  = $actuaciones_por_dia[ $day_key ] ?? [];
                $is_today  = ( $day_key === date( 'Y-m-d' ) );
                $header_bg = $is_today ? $c_primary : '#e9ecef';
                $header_color = $is_today ? $c_white : $c_dark;
            ?>
            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:16px;border:1px solid <?php echo $c_border; ?>;border-radius:8px;overflow:hidden;">
              <tr>
                <td style="background:<?php echo $header_bg; ?>;padding:10px 16px;">
                  <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                      <td>
                        <span style="font-size:13px;font-weight:700;color:<?php echo $header_color; ?>;">
                          <?php echo esc_html( $day_label ); ?>
                        </span>
                      </td>
                      <td align="right">
                        <?php if ( ! empty( $acts_day ) ) : ?>
                        <span style="background:<?php echo $is_today ? 'rgba(255,255,255,0.25)' : $c_primary; ?>;color:<?php echo $c_white; ?>;border-radius:10px;padding:2px 8px;font-size:11px;font-weight:600;">
                          <?php echo esc_html( count( $acts_day ) ); ?>
                        </span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
              <?php if ( ! empty( $acts_day ) ) :
                  foreach ( $acts_day as $act ) :
                      $act_proyecto_id  = get_post_meta( $act->ID, '_lznd_proyecto_id', true );
                      $act_tipo_tid     = get_post_meta( $act->ID, '_lznd_tipo_term_id', true );
                      $act_tecnico      = get_post_meta( $act->ID, '_lznd_tecnico', true );
                      $act_estado       = get_post_meta( $act->ID, '_lznd_estado', true ) ?: 'pendiente';
                      $act_proy_title   = $act_proyecto_id ? get_the_title( $act_proyecto_id ) : '—';
                      $act_tipo_term    = $act_tipo_tid ? get_term( $act_tipo_tid, 'lznd_tipo' ) : null;
                      $act_tipo_name    = ( $act_tipo_term && ! is_wp_error( $act_tipo_term ) ) ? $act_tipo_term->name : '—';
                      $estado_colors    = [
                          'pendiente'  => '#fd7e14',
                          'en_proceso' => $c_primary,
                          'completado' => $c_green,
                          'enviado'    => $c_green,
                          'cancelado'  => $c_muted,
                      ];
                      $estado_color = $estado_colors[ $act_estado ] ?? $c_muted;
              ?>
              <tr>
                <td style="padding:12px 16px;border-top:1px solid <?php echo $c_border; ?>;">
                  <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                      <td>
                        <p style="margin:0;font-size:14px;font-weight:600;color:<?php echo $c_dark; ?>;"><?php echo esc_html( $act_proy_title ); ?></p>
                        <p style="margin:3px 0 0;font-size:12px;color:<?php echo $c_muted; ?>;">
                          <?php echo esc_html( $act_tipo_name ); ?>
                          <?php if ( ! empty( $act_tecnico ) ) : ?>
                            &bull; <?php echo esc_html( $act_tecnico ); ?>
                          <?php endif; ?>
                        </p>
                      </td>
                      <td align="right" style="vertical-align:top;">
                        <span style="display:inline-block;border:1px solid <?php echo $estado_color; ?>;color:<?php echo $estado_color; ?>;border-radius:4px;padding:2px 8px;font-size:11px;font-weight:600;">
                          <?php echo esc_html( ucfirst( str_replace( '_', ' ', $act_estado ) ) ); ?>
                        </span>
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php else : ?>
              <tr>
                <td style="padding:12px 16px;text-align:center;">
                  <p style="margin:0;font-size:13px;color:<?php echo $c_muted; ?>;font-style:italic;">
                    <?php esc_html_e( 'Sin actuaciones este día', 'lezned-maintenance' ); ?>
                  </p>
                </td>
              </tr>
              <?php endif; ?>
            </table>
            <?php endfor; ?>

          </td>
        </tr>

        <?php /* ---- Upcoming projects ---- */ ?>
        <?php if ( ! empty( $proyectos_semana ) ) : ?>
        <tr>
          <td style="background:<?php echo $c_white; ?>;padding:0 40px 24px;">
            <div style="background:#e8f5e9;border-radius:10px;padding:20px;">
              <h2 style="margin:0 0 16px;font-size:15px;font-weight:700;color:#1b5e20;">
                &#128204; <?php esc_html_e( 'Proyectos con mantenimiento esta semana', 'lezned-maintenance' ); ?>
              </h2>
              <table width="100%" cellpadding="0" cellspacing="0">
                <tr style="border-bottom:2px solid #c8e6c9;">
                  <td style="padding:0 0 8px;font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#388e3c;font-weight:700;"><?php esc_html_e( 'Proyecto', 'lezned-maintenance' ); ?></td>
                  <td style="padding:0 0 8px;font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#388e3c;font-weight:700;" align="right"><?php esc_html_e( 'Fecha programada', 'lezned-maintenance' ); ?></td>
                </tr>
                <?php foreach ( $proyectos_semana as $proy ) :
                    $prox = get_post_meta( $proy->ID, '_lznd_fecha_proxima', true );
                ?>
                <tr>
                  <td style="padding:10px 0;font-size:14px;color:<?php echo $c_dark; ?>;font-weight:600;border-bottom:1px solid #c8e6c9;">
                    <?php echo esc_html( get_the_title( $proy->ID ) ); ?>
                  </td>
                  <td style="padding:10px 0;font-size:13px;color:<?php echo $c_muted; ?>;text-align:right;border-bottom:1px solid #c8e6c9;">
                    <?php echo $prox ? esc_html( date_i18n( 'd/m/Y', strtotime( $prox ) ) ) : '—'; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </table>
            </div>
          </td>
        </tr>
        <?php endif; ?>

        <?php /* ---- CTA button ---- */ ?>
        <tr>
          <td style="background:<?php echo $c_white; ?>;padding:0 40px 32px;text-align:center;">
            <a href="<?php echo esc_url( $admin_url ); ?>"
               style="display:inline-block;background:<?php echo $c_primary; ?>;color:<?php echo $c_white; ?>;text-decoration:none;padding:12px 28px;border-radius:8px;font-size:14px;font-weight:700;">
              <?php esc_html_e( 'Ver panel de administración', 'lezned-maintenance' ); ?>
            </a>
          </td>
        </tr>

        <?php /* ---- Footer ---- */ ?>
        <tr>
          <td style="background:#1a1a2e;border-radius:0 0 12px 12px;padding:24px 40px;text-align:center;">
            <p style="margin:0 0 6px;color:rgba(255,255,255,0.9);font-size:14px;font-weight:600;">
              <?php echo esc_html( $blog_name ); ?>
            </p>
            <p style="margin:0;color:rgba(255,255,255,0.5);font-size:12px;">
              <?php esc_html_e( 'Resumen generado automáticamente cada semana.', 'lezned-maintenance' ); ?>
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
