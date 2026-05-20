<?php
/**
 * Cron / Scheduled tasks Handler
 *
 * @package Lezned_Maintenance_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LZND_Cron {

    /**
     * Register hooks.
     */
    public static function init() {
        add_filter( 'cron_schedules', [ __CLASS__, 'add_intervals' ] );
        add_action( 'lznd_daily_check',    [ __CLASS__, 'daily_check' ] );
        add_action( 'lznd_weekly_summary', [ __CLASS__, 'weekly_summary' ] );
    }

    /**
     * Add custom cron intervals.
     *
     * @param array $schedules
     * @return array
     */
    public static function add_intervals( $schedules ) {
        $schedules['lznd_weekly'] = [
            'interval' => WEEK_IN_SECONDS,
            'display'  => __( 'Lezned: Semanal', 'lezned-maintenance' ),
        ];
        return $schedules;
    }

    /**
     * Schedule plugin events on activation.
     */
    public static function setup_events() {
        if ( ! wp_next_scheduled( 'lznd_daily_check' ) ) {
            wp_schedule_event( self::next_occurrence_at( 8, 0 ), 'daily', 'lznd_daily_check' );
        }

        if ( ! wp_next_scheduled( 'lznd_weekly_summary' ) ) {
            wp_schedule_event( self::next_monday_7am(), 'lznd_weekly', 'lznd_weekly_summary' );
        }
    }

    /**
     * Clear scheduled events on deactivation.
     */
    public static function clear_events() {
        $timestamp = wp_next_scheduled( 'lznd_daily_check' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'lznd_daily_check' );
        }

        $timestamp = wp_next_scheduled( 'lznd_weekly_summary' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'lznd_weekly_summary' );
        }
    }

    /**
     * Daily check: advance warnings + auto-create actuaciones.
     */
    public static function daily_check() {
        $settings   = get_option( 'lznd_settings', [] );
        $aviso_dias = intval( $settings['aviso_dias'] ?? 7 );

        // Get all active projects with recurrence
        $proyectos = get_posts( [
            'post_type'      => 'lznd_proyecto',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'     => '_lznd_recurrencia',
                    'value'   => 'ninguna',
                    'compare' => '!=',
                ],
                [
                    'key'     => '_lznd_recurrencia',
                    'compare' => 'EXISTS',
                ],
            ],
        ] );

        $today = strtotime( date( 'Y-m-d' ) );

        foreach ( $proyectos as $proyecto ) {
            $fecha_proxima = get_post_meta( $proyecto->ID, '_lznd_fecha_proxima', true );
            $auto_crear    = get_post_meta( $proyecto->ID, '_lznd_auto_crear', true );
            $aviso_enviado = get_post_meta( $proyecto->ID, '_lznd_aviso_enviado', true );

            if ( ! $fecha_proxima ) {
                continue;
            }

            $ts_proxima = strtotime( $fecha_proxima );
            $dias_diff  = (int) ( ( $ts_proxima - $today ) / DAY_IN_SECONDS );

            // Send advance warning if within aviso_dias and not yet sent
            if ( $dias_diff >= 0 && $dias_diff <= $aviso_dias && empty( $aviso_enviado ) ) {
                self::send_advance_warning( $proyecto->ID, $dias_diff );
                update_post_meta( $proyecto->ID, '_lznd_aviso_enviado', '1' );
            }

            // Auto-create actuacion if fecha_proxima is today
            if ( $auto_crear === '1' && $dias_diff === 0 ) {
                // Only create if no pending actuacion exists
                $existing = get_posts( [
                    'post_type'      => 'lznd_actuacion',
                    'post_status'    => 'publish',
                    'posts_per_page' => 1,
                    'meta_query'     => [
                        'relation' => 'AND',
                        [
                            'key'   => '_lznd_proyecto_id',
                            'value' => $proyecto->ID,
                            'type'  => 'NUMERIC',
                        ],
                        [
                            'key'   => '_lznd_fecha_prog',
                            'value' => $fecha_proxima,
                        ],
                        [
                            'key'     => '_lznd_estado',
                            'value'   => [ 'pendiente', 'en_curso' ],
                            'compare' => 'IN',
                        ],
                    ],
                ] );

                if ( empty( $existing ) ) {
                    // Determine tipo from project default
                    $tipo_term_id = get_post_meta( $proyecto->ID, '_lznd_tipo_term_id', true );
                    if ( ! $tipo_term_id ) {
                        // Default to first mantenimiento term
                        $term = get_term_by( 'name', 'Mantenimiento', 'lznd_tipo' );
                        $tipo_term_id = $term ? $term->term_id : 0;
                    }

                    if ( $tipo_term_id ) {
                        $current_user_id = get_current_user_id();
                        // For cron context, use admin user
                        $admin_users = get_users( [ 'role' => 'administrator', 'number' => 1 ] );
                        if ( ! empty( $admin_users ) ) {
                            wp_set_current_user( $admin_users[0]->ID );
                        }

                        $actuacion_id = self::create_actuacion_for_project( $proyecto->ID, $tipo_term_id );
                        wp_set_current_user( $current_user_id );
                    }
                }
            }

            // Advance recurrence if fecha_proxima is in the past
            if ( $auto_crear === '1' && $ts_proxima < $today ) {
                self::advance_recurrence( $proyecto->ID );
            }
        }
    }

    /**
     * Weekly summary email to admin.
     */
    public static function weekly_summary() {
        $settings    = get_option( 'lznd_settings', [] );
        $admin_email = $settings['admin_email'] ?? get_option( 'admin_email' );

        $lunes   = strtotime( 'monday this week' );
        $domingo = strtotime( 'sunday this week' );

        $lunes_str   = date( 'Y-m-d', $lunes );
        $domingo_str = date( 'Y-m-d', $domingo );

        // Get actuaciones programadas this week
        $actuaciones_semana = get_posts( [
            'post_type'      => 'lznd_actuacion',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'     => '_lznd_fecha_prog',
                    'value'   => [ $lunes_str, $domingo_str . ' 23:59:59' ],
                    'compare' => 'BETWEEN',
                    'type'    => 'DATETIME',
                ],
            ],
        ] );

        // Get proyectos with fecha_proxima this week
        $proyectos_semana = get_posts( [
            'post_type'      => 'lznd_proyecto',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'     => '_lznd_fecha_proxima',
                    'value'   => [ $lunes_str, $domingo_str ],
                    'compare' => 'BETWEEN',
                    'type'    => 'DATE',
                ],
            ],
        ] );

        // Group actuaciones by day
        $actuaciones_por_dia = [];
        foreach ( $actuaciones_semana as $act ) {
            $fecha = get_post_meta( $act->ID, '_lznd_fecha_prog', true );
            if ( $fecha ) {
                $day_key = date( 'Y-m-d', strtotime( $fecha ) );
                $actuaciones_por_dia[ $day_key ][] = $act;
            }
        }

        ob_start();
        include LZND_TEMPLATES . 'email-semanal.php';
        $body = ob_get_clean();

        $subject = sprintf(
            __( 'Resumen Semanal Mantenimiento: %s - %s', 'lezned-maintenance' ),
            date_i18n( 'd/m/Y', $lunes ),
            date_i18n( 'd/m/Y', $domingo )
        );

        add_filter( 'wp_mail_content_type', fn() => 'text/html' );
        wp_mail( $admin_email, $subject, $body );
        remove_filter( 'wp_mail_content_type', fn() => 'text/html' );
    }

    /**
     * Send a report for an actuacion via email or whatsapp.
     *
     * @param int    $actuacion_id
     * @param string $canal  'email' | 'whatsapp'
     * @return bool|array
     */
    public static function send_report( $actuacion_id, $canal = 'email' ) {
        $settings = get_option( 'lznd_settings', [] );

        $actuacion = get_post( $actuacion_id );
        if ( ! $actuacion ) {
            return false;
        }

        $proyecto_id  = get_post_meta( $actuacion_id, '_lznd_proyecto_id', true );
        $tipo_term_id = get_post_meta( $actuacion_id, '_lznd_tipo_term_id', true );

        $proyecto   = $proyecto_id ? get_post( $proyecto_id ) : null;
        $tipo_term  = $tipo_term_id ? get_term( $tipo_term_id, 'lznd_tipo' ) : null;

        $tecnico_name = get_post_meta( $actuacion_id, '_lznd_tecnico', true );
        $tecnico      = null;
        $users        = get_users( [ 'search' => $tecnico_name, 'number' => 1 ] );
        if ( ! empty( $users ) ) {
            $tecnico = $users[0];
        }

        $tareas_raw = get_post_meta( $actuacion_id, '_lznd_tareas', true );
        $tareas     = $tareas_raw ? json_decode( $tareas_raw, true ) : [];
        if ( ! is_array( $tareas ) ) {
            $tareas = [];
        }

        $fotos_raw = get_post_meta( $actuacion_id, '_lznd_fotos', true );
        $fotos     = is_array( $fotos_raw ) ? $fotos_raw : [];

        $firma_tecnico_url      = get_post_meta( $actuacion_id, '_lznd_firma_tecnico_url', true );
        $firma_cliente_url      = get_post_meta( $actuacion_id, '_lznd_firma_cliente_url', true );
        $nombre_firmante_tecnico = get_post_meta( $actuacion_id, '_lznd_nombre_firmante_tecnico', true );
        $nombre_firmante_cliente = get_post_meta( $actuacion_id, '_lznd_nombre_firmante_cliente', true );
        $firma_fecha            = get_post_meta( $actuacion_id, '_lznd_firma_fecha', true );
        $fecha_ini              = get_post_meta( $actuacion_id, '_lznd_fecha_inicio', true );
        $fecha_fin              = get_post_meta( $actuacion_id, '_lznd_fecha_fin', true );
        $observ                 = get_post_meta( $actuacion_id, '_lznd_observaciones', true );

        $admin_email    = $settings['admin_email'] ?? get_option( 'admin_email' );
        $contacto_email = $proyecto_id ? get_post_meta( $proyecto_id, '_lznd_contacto_email', true ) : '';

        if ( $canal === 'email' ) {
            ob_start();
            include LZND_TEMPLATES . 'email-reporte.php';
            $body = ob_get_clean();

            $to = array_filter( [ $admin_email, $contacto_email ] );

            $proyecto_title = $proyecto ? get_the_title( $proyecto ) : __( 'Proyecto', 'lezned-maintenance' );
            $fecha_display  = $fecha_ini ? date_i18n( 'd/m/Y', strtotime( $fecha_ini ) ) : date_i18n( 'd/m/Y' );
            $subject        = sprintf(
                __( 'Parte: %s - %s', 'lezned-maintenance' ),
                $proyecto_title,
                $fecha_display
            );

            // Collect photo file paths for attachments
            $attachments = [];
            foreach ( $fotos as $attach_id ) {
                $path = get_attached_file( $attach_id );
                if ( $path && file_exists( $path ) ) {
                    $attachments[] = $path;
                }
            }

            $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
            $from_name = $settings['from_name'] ?? get_bloginfo( 'name' );
            if ( $from_name ) {
                $headers[] = 'From: ' . $from_name . ' <' . $admin_email . '>';
            }

            $sent = wp_mail( $to, $subject, $body, $headers, $attachments );
            return (bool) $sent;

        } elseif ( $canal === 'whatsapp' ) {
            $proyecto_title = $proyecto ? get_the_title( $proyecto ) : __( 'Proyecto', 'lezned-maintenance' );
            $tipo_name      = ( $tipo_term && ! is_wp_error( $tipo_term ) ) ? $tipo_term->name : '';
            $fecha_display  = $fecha_ini ? date_i18n( 'd/m/Y H:i', strtotime( $fecha_ini ) ) : date_i18n( 'd/m/Y' );

            $hechas = count( array_filter( $tareas, fn( $t ) => ! empty( $t['completada'] ) ) );
            $total  = count( $tareas );

            $lines = [
                '*Parte de Mantenimiento #' . $actuacion_id . '*',
                '',
                '*Proyecto:* ' . $proyecto_title,
                '*Tipo:* ' . $tipo_name,
                '*Técnico:* ' . $tecnico_name,
                '*Fecha inicio:* ' . $fecha_display,
                '*Fecha fin:* ' . ( $fecha_fin ? date_i18n( 'd/m/Y H:i', strtotime( $fecha_fin ) ) : '-' ),
                '*Tareas:* ' . $hechas . '/' . $total . ' completadas',
                '',
            ];

            if ( ! empty( $tareas ) ) {
                $lines[] = '*Detalle de tareas:*';
                foreach ( $tareas as $tarea ) {
                    $check   = ! empty( $tarea['completada'] ) ? '✅' : '⬜';
                    $lines[] = $check . ' ' . $tarea['texto'];
                }
                $lines[] = '';
            }

            if ( ! empty( $observ ) ) {
                $lines[] = '*Observaciones:*';
                $lines[] = $observ;
                $lines[] = '';
            }

            if ( ! empty( $nombre_firmante_tecnico ) ) {
                $lines[] = '*Firma técnico:* ' . $nombre_firmante_tecnico;
            }
            if ( ! empty( $nombre_firmante_cliente ) ) {
                $lines[] = '*Firma cliente:* ' . $nombre_firmante_cliente;
            }

            $text = implode( "\n", $lines );

            $whatsapp_num = $proyecto_id ? get_post_meta( $proyecto_id, '_lznd_contacto_telefono', true ) : '';
            $whatsapp_num = preg_replace( '/[\s\+\-\(\)]/', '', $whatsapp_num );

            if ( empty( $whatsapp_num ) ) {
                // Fall back to admin whatsapp
                $whatsapp_num = preg_replace( '/[\s\+\-\(\)]/', '', $settings['whatsapp_admin'] ?? '' );
            }

            $url = 'https://wa.me/' . $whatsapp_num . '?text=' . rawurlencode( $text );

            return [ 'url' => $url ];
        }

        return false;
    }

    /**
     * Advance the recurrence date for a project.
     *
     * @param int $proyecto_id
     */
    public static function advance_recurrence( $proyecto_id ) {
        $recurrencia   = get_post_meta( $proyecto_id, '_lznd_recurrencia', true );
        $fecha_proxima = get_post_meta( $proyecto_id, '_lznd_fecha_proxima', true );

        if ( ! $recurrencia || $recurrencia === 'ninguna' || ! $fecha_proxima ) {
            return;
        }

        $ts = strtotime( $fecha_proxima );

        switch ( $recurrencia ) {
            case 'mensual':
                $new_ts = strtotime( '+1 month', $ts );
                break;
            case 'bimestral':
                $new_ts = strtotime( '+2 months', $ts );
                break;
            case 'trimestral':
                $new_ts = strtotime( '+3 months', $ts );
                break;
            case 'semestral':
                $new_ts = strtotime( '+6 months', $ts );
                break;
            case 'anual':
                $new_ts = strtotime( '+1 year', $ts );
                break;
            default:
                $new_ts = strtotime( '+1 year', $ts );
                break;
        }

        $new_fecha = date( 'Y-m-d', $new_ts );
        update_post_meta( $proyecto_id, '_lznd_fecha_proxima', $new_fecha );
        delete_post_meta( $proyecto_id, '_lznd_aviso_enviado' );
    }

    /**
     * Send an advance warning email for an upcoming project.
     *
     * @param int $proyecto_id
     * @param int $dias Days until the visit
     */
    public static function send_advance_warning( $proyecto_id, $dias ) {
        $settings        = get_option( 'lznd_settings', [] );
        $proyecto        = get_post( $proyecto_id );
        $proyecto_title  = $proyecto ? get_the_title( $proyecto ) : __( 'Proyecto', 'lezned-maintenance' );
        $fecha_proxima   = get_post_meta( $proyecto_id, '_lznd_fecha_proxima', true );
        $aviso_email     = get_post_meta( $proyecto_id, '_lznd_aviso_email', true );
        $admin_email     = $settings['admin_email'] ?? get_option( 'admin_email' );
        $to              = $aviso_email ?: $admin_email;
        $from_name       = $settings['from_name'] ?? get_bloginfo( 'name' );

        $subject = sprintf(
            _n(
                'Aviso: Mantenimiento de "%s" mañana (%s)',
                'Aviso: Mantenimiento de "%s" en %d días (%s)',
                $dias,
                'lezned-maintenance'
            ),
            $proyecto_title,
            $dias,
            $fecha_proxima ? date_i18n( 'd/m/Y', strtotime( $fecha_proxima ) ) : ''
        );

        $body = '<html><body style="font-family: Arial, sans-serif; color: #333;">';
        $body .= '<h2 style="color: #2271b1;">' . esc_html__( 'Aviso de Mantenimiento Próximo', 'lezned-maintenance' ) . '</h2>';
        $body .= '<p>' . sprintf(
            esc_html__( 'El proyecto <strong>%s</strong> tiene programado un mantenimiento el <strong>%s</strong>.', 'lezned-maintenance' ),
            esc_html( $proyecto_title ),
            esc_html( $fecha_proxima ? date_i18n( 'd/m/Y', strtotime( $fecha_proxima ) ) : '' )
        ) . '</p>';

        if ( $dias === 0 ) {
            $body .= '<p style="color: #d63638;"><strong>' . esc_html__( '¡Es hoy!', 'lezned-maintenance' ) . '</strong></p>';
        } else {
            $body .= '<p>' . sprintf(
                esc_html( _n( 'Faltan %d día.', 'Faltan %d días.', $dias, 'lezned-maintenance' ) ),
                $dias
            ) . '</p>';
        }

        $admin_url = admin_url( 'post.php?post=' . $proyecto_id . '&action=edit' );
        $body .= '<p><a href="' . esc_url( $admin_url ) . '" style="background:#2271b1;color:#fff;padding:8px 16px;border-radius:4px;text-decoration:none;">'
            . esc_html__( 'Ver proyecto', 'lezned-maintenance' ) . '</a></p>';
        $body .= '</body></html>';

        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
        if ( $from_name ) {
            $headers[] = 'From: ' . $from_name . ' <' . $admin_email . '>';
        }

        wp_mail( $to, $subject, $body, $headers );
    }

    /**
     * Get timestamp for next Monday at 07:00 local time.
     *
     * @return int
     */
    public static function next_monday_7am() {
        $now = current_time( 'timestamp' );
        // Find next monday
        $day_of_week = (int) date( 'N', $now ); // 1=Mon
        $days_until_monday = ( $day_of_week === 1 ) ? 7 : ( 8 - $day_of_week );
        $next_monday = strtotime( date( 'Y-m-d', $now + $days_until_monday * DAY_IN_SECONDS ) . ' 07:00:00' );
        return $next_monday;
    }

    /**
     * Get next occurrence of a given time (today if not yet passed, tomorrow if already past).
     *
     * @param int $h Hour
     * @param int $m Minute
     * @return int
     */
    public static function next_occurrence_at( $h, $m ) {
        $now       = current_time( 'timestamp' );
        $today     = strtotime( date( 'Y-m-d', $now ) . sprintf( ' %02d:%02d:00', $h, $m ) );
        if ( $today > $now ) {
            return $today;
        }
        return $today + DAY_IN_SECONDS;
    }

    /**
     * Create actuacion for a project (used in cron context).
     *
     * @param int $proyecto_id
     * @param int $tipo_term_id
     * @return int|WP_Error
     */
    private static function create_actuacion_for_project( $proyecto_id, $tipo_term_id ) {
        $proyecto = get_post( $proyecto_id );
        if ( ! $proyecto ) {
            return new WP_Error( 'invalid_project', __( 'Proyecto no encontrado.', 'lezned-maintenance' ) );
        }

        $tipo_term = get_term( $tipo_term_id, 'lznd_tipo' );
        if ( is_wp_error( $tipo_term ) || ! $tipo_term ) {
            return new WP_Error( 'invalid_term', __( 'Tipo no encontrado.', 'lezned-maintenance' ) );
        }

        $fecha_prog = get_post_meta( $proyecto_id, '_lznd_fecha_proxima', true );

        $title = sprintf(
            '%s - %s - %s',
            get_the_title( $proyecto_id ),
            $tipo_term->name,
            date_i18n( 'd/m/Y' )
        );

        $actuacion_id = wp_insert_post( [
            'post_type'   => 'lznd_actuacion',
            'post_title'  => $title,
            'post_status' => 'publish',
            'post_author' => get_current_user_id(),
        ] );

        if ( is_wp_error( $actuacion_id ) ) {
            return $actuacion_id;
        }

        update_post_meta( $actuacion_id, '_lznd_proyecto_id', $proyecto_id );
        update_post_meta( $actuacion_id, '_lznd_tipo_term_id', $tipo_term_id );
        update_post_meta( $actuacion_id, '_lznd_estado', 'pendiente' );
        update_post_meta( $actuacion_id, '_lznd_prioridad', get_post_meta( $proyecto_id, '_lznd_prioridad', true ) ?: 'normal' );
        update_post_meta( $actuacion_id, '_lznd_fecha_prog', $fecha_prog ?: current_time( 'Y-m-d' ) );
        update_post_meta( $actuacion_id, '_lznd_fotos', [] );

        // Copy tasks
        $term_tareas = get_posts( [
            'post_type'      => 'lznd_tarea',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'menu_order',
            'order'          => 'ASC',
            'tax_query'      => [
                [
                    'taxonomy' => 'lznd_tipo',
                    'field'    => 'term_id',
                    'terms'    => $tipo_term_id,
                ],
            ],
        ] );

        if ( empty( $term_tareas ) ) {
            $term_tareas = get_posts( [
                'post_type'      => 'lznd_tarea',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'orderby'        => 'menu_order',
                'order'          => 'ASC',
            ] );
        }

        $tareas = [];
        foreach ( $term_tareas as $tarea_post ) {
            $tareas[] = [
                'id'           => uniqid( 'task_', true ),
                'texto'        => get_the_title( $tarea_post->ID ),
                'completada'   => false,
                'completada_en' => '',
            ];
        }

        update_post_meta( $actuacion_id, '_lznd_tareas', wp_json_encode( $tareas ) );

        return $actuacion_id;
    }
}

LZND_Cron::init();
