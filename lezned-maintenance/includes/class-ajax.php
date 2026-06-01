<?php
/**
 * AJAX Handler Class
 *
 * @package Lezned_Maintenance_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LZND_Ajax {

    /**
     * Register all AJAX actions.
     */
    public static function init() {
        $actions = [
            'lznd_get_project_tasks',
            'lznd_get_project_dashboard',
            'lznd_toggle_task',
            'lznd_add_task',
            'lznd_delete_task',
            'lznd_upload_foto',
            'lznd_delete_foto',
            'lznd_save_signature',
            'lznd_save_field',
            'lznd_save_task_value',
            'lznd_submit_actuacion',
            'lznd_get_client_data',
            'lznd_calendar_nav',
        ];

        foreach ( $actions as $action ) {
            add_action( 'wp_ajax_' . $action, [ __CLASS__, str_replace( 'lznd_', 'handle_', $action ) ] );
        }
    }

    /**
     * Verify request: logged in + nonce.
     */
    private static function verify() {
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => __( 'No autorizado.', 'lezned-maintenance' ) ], 403 );
        }
        check_ajax_referer( 'lznd_frontend', 'nonce' );
    }

    /**
     * Get or create actuacion for a project + tipo, return tasks and meta.
     */
    public static function handle_get_project_tasks() {
        self::verify();

        $proyecto_id   = intval( $_POST['proyecto_id'] ?? 0 );
        $tipo_term_id  = intval( $_POST['tipo_term_id'] ?? 0 );
        $force_new     = ! empty( $_POST['force_new'] );

        if ( ! $proyecto_id || ! $tipo_term_id ) {
            wp_send_json_error( [ 'message' => __( 'Datos incompletos.', 'lezned-maintenance' ) ] );
        }

        // Look for an existing pending actuacion for this project + tipo (skip if force_new)
        $existing = $force_new ? [] : get_posts( [
            'post_type'      => 'lznd_actuacion',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'   => '_lznd_proyecto_id',
                    'value' => $proyecto_id,
                    'type'  => 'NUMERIC',
                ],
                [
                    'key'   => '_lznd_tipo_term_id',
                    'value' => $tipo_term_id,
                    'type'  => 'NUMERIC',
                ],
                [
                    'key'     => '_lznd_estado',
                    'value'   => [ 'pendiente', 'en_curso' ],
                    'compare' => 'IN',
                ],
            ],
        ] );

        if ( ! empty( $existing ) ) {
            $actuacion_id = $existing[0]->ID;
        } else {
            $actuacion_id = self::create_actuacion( $proyecto_id, $tipo_term_id );
            if ( is_wp_error( $actuacion_id ) ) {
                wp_send_json_error( [ 'message' => $actuacion_id->get_error_message() ] );
            }
        }

        // Mark as in-progress so the recovery popup appears on future visits
        update_post_meta( $actuacion_id, '_lznd_estado', 'en_curso' );

        $tareas_raw   = get_post_meta( $actuacion_id, '_lznd_tareas', true );
        $tareas       = $tareas_raw ? json_decode( $tareas_raw, true ) : [];
        if ( ! is_array( $tareas ) ) {
            $tareas = [];
        }
        $tareas_fixed = self::fix_tareas_encoding( $tareas );
        // Persist repaired encoding back to DB so it only runs once.
        $tareas_json  = wp_json_encode( $tareas_fixed, JSON_UNESCAPED_UNICODE );
        if ( $tareas_json !== $tareas_raw ) {
            update_post_meta( $actuacion_id, '_lznd_tareas', $tareas_json );
        }
        $tareas = $tareas_fixed;

        // ── Enrich tasks from templates ──────────────────────────────────────
        $tareas = self::enrich_tareas( $tareas, $tipo_term_id, $actuacion_id );
        // ── end enrichment ──────────────────────────────────────────────────

        $fotos_raw = get_post_meta( $actuacion_id, '_lznd_fotos', true );
        $fotos     = is_array( $fotos_raw ) ? $fotos_raw : [];

        $fotos_data = [];
        foreach ( $fotos as $attach_id ) {
            $url   = wp_get_attachment_url( $attach_id );
            $thumb = wp_get_attachment_image_url( $attach_id, 'thumbnail' );
            if ( $url ) {
                $fotos_data[] = [
                    'attach_id' => intval( $attach_id ),
                    'url'       => $url,
                    'thumb'     => $thumb ?: $url,
                ];
            }
        }

        $firma_tecnico_url = get_post_meta( $actuacion_id, '_lznd_firma_tecnico_url', true );
        $firma_cliente_url = get_post_meta( $actuacion_id, '_lznd_firma_cliente_url', true );

        wp_send_json_success( [
            'actuacion_id'          => $actuacion_id,
            'tasks'                 => $tareas,
            'fotos'                 => $fotos_data,
            'has_firma_tecnico'     => ! empty( $firma_tecnico_url ),
            'has_firma_cliente'     => ! empty( $firma_cliente_url ),
            'firma_tecnico_url'     => $firma_tecnico_url ?: '',
            'firma_cliente_url'     => $firma_cliente_url ?: '',
            'nombre_firmante_tecnico' => get_post_meta( $actuacion_id, '_lznd_nombre_firmante_tecnico', true ),
            'nombre_firmante_cliente' => get_post_meta( $actuacion_id, '_lznd_nombre_firmante_cliente', true ),
            'estado'                => get_post_meta( $actuacion_id, '_lznd_estado', true ) ?: 'pendiente',
            'observaciones'         => get_post_meta( $actuacion_id, '_lznd_observaciones', true ) ?: '',
            'fecha_prog'            => get_post_meta( $actuacion_id, '_lznd_fecha_prog', true ) ?: '',
            'fecha_inicio'          => get_post_meta( $actuacion_id, '_lznd_fecha_inicio', true ) ?: '',
            'fecha_fin'             => get_post_meta( $actuacion_id, '_lznd_fecha_fin', true ) ?: '',
            'tecnico'               => get_post_meta( $actuacion_id, '_lznd_tecnico', true ) ?: '',
            'prioridad'             => get_post_meta( $actuacion_id, '_lznd_prioridad', true ) ?: 'normal',
        ] );
    }

    /**
     * Back-fill tipo_campo/opciones from templates into task array.
     * Also appends any template tasks missing from the actuacion.
     * Persists changes to DB when anything changes.
     *
     * @param array $tareas       Current task array.
     * @param int   $tipo_term_id Taxonomy term ID (0 = no filter, use all templates).
     * @param int   $actuacion_id Post ID to persist enriched data back to.
     * @return array              Enriched task array.
     */
    public static function enrich_tareas( array $tareas, $tipo_term_id, $actuacion_id ) {
        $template_posts = [];

        if ( $tipo_term_id > 0 ) {
            $template_posts = get_posts( [
                'post_type'      => 'lznd_tarea',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'orderby'        => 'menu_order',
                'order'          => 'ASC',
                'tax_query'      => [
                    [
                        'taxonomy' => 'lznd_tipo',
                        'field'    => 'term_id',
                        'terms'    => intval( $tipo_term_id ),
                    ],
                ],
            ] );
        }

        if ( empty( $template_posts ) ) {
            $template_posts = get_posts( [
                'post_type'      => 'lznd_tarea',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'orderby'        => 'menu_order',
                'order'          => 'ASC',
            ] );
        }

        $tmpl_map = [];
        foreach ( $template_posts as $tp ) {
            $tc     = get_post_meta( $tp->ID, '_lznd_tipo_campo', true ) ?: 'texto';
            $op_raw = get_post_meta( $tp->ID, '_lznd_opciones',   true );
            $op     = ( $op_raw && $op_raw !== '[]' ) ? json_decode( $op_raw, true ) : [];
            if ( ! is_array( $op ) ) {
                $op = [];
            }
            $tmpl_map[ $tp->post_title ] = [ 'tipo_campo' => $tc, 'opciones' => $op ];
        }

        $enriched     = false;
        $existing_txt = array_column( $tareas, 'texto' );

        foreach ( $tareas as &$t ) {
            if ( isset( $tmpl_map[ $t['texto'] ] ) ) {
                $def = $tmpl_map[ $t['texto'] ];
                if ( empty( $t['tipo_campo'] ) ) {
                    $t['tipo_campo'] = $def['tipo_campo'];
                    $enriched = true;
                }
                if ( empty( $t['opciones'] ) && ! empty( $def['opciones'] ) ) {
                    $t['opciones'] = $def['opciones'];
                    $enriched = true;
                }
            }
            if ( ! isset( $t['valor'] ) ) {
                $t['valor'] = '';
                $enriched   = true;
            }
            if ( ! isset( $t['opciones'] ) ) {
                $t['opciones'] = [];
                $enriched      = true;
            }
        }
        unset( $t );

        foreach ( $template_posts as $tp ) {
            if ( ! in_array( $tp->post_title, $existing_txt, true ) ) {
                $def      = $tmpl_map[ $tp->post_title ];
                $tareas[] = [
                    'id'            => uniqid( 'task_', true ),
                    'texto'         => $tp->post_title,
                    'tipo_campo'    => $def['tipo_campo'],
                    'opciones'      => $def['opciones'],
                    'completada'    => false,
                    'completada_en' => '',
                    'valor'         => '',
                ];
                $enriched = true;
            }
        }

        if ( $enriched && $actuacion_id > 0 ) {
            update_post_meta( $actuacion_id, '_lznd_tareas', wp_json_encode( $tareas, JSON_UNESCAPED_UNICODE ) );
        }

        return $tareas;
    }

    /**
     * Toggle a task's completada flag.
     */
    public static function handle_toggle_task() {
        self::verify();

        $actuacion_id = intval( $_POST['parte_id'] ?? $_POST['actuacion_id'] ?? 0 );
        $task_id      = sanitize_text_field( $_POST['task_id'] ?? '' );

        if ( ! $actuacion_id || ! $task_id ) {
            wp_send_json_error( [ 'message' => __( 'Datos incompletos.', 'lezned-maintenance' ) ] );
        }

        $tareas_raw = get_post_meta( $actuacion_id, '_lznd_tareas', true );
        $tareas     = $tareas_raw ? json_decode( $tareas_raw, true ) : [];
        if ( ! is_array( $tareas ) ) {
            $tareas = [];
        }
        $tareas = self::fix_tareas_encoding( $tareas );

        $completada = false;
        foreach ( $tareas as &$tarea ) {
            if ( isset( $tarea['id'] ) && $tarea['id'] === $task_id ) {
                $tarea['completada'] = ! ( $tarea['completada'] ?? false );
                $completada = $tarea['completada'];
                if ( $completada ) {
                    $tarea['completada_en'] = current_time( 'mysql' );
                } else {
                    $tarea['completada_en'] = '';
                }
                break;
            }
        }
        unset( $tarea );

        update_post_meta( $actuacion_id, '_lznd_tareas', wp_json_encode( $tareas, JSON_UNESCAPED_UNICODE ) );

        $total  = count( $tareas );
        $hechas = count( array_filter( $tareas, fn( $t ) => ! empty( $t['completada'] ) ) );

        wp_send_json_success( [
            'completada' => $completada,
            'hechas'     => $hechas,
            'total'      => $total,
        ] );
    }

    /**
     * Add a new task to an actuacion.
     */
    public static function handle_add_task() {
        self::verify();

        $actuacion_id = intval( $_POST['actuacion_id'] ?? 0 );
        $texto        = sanitize_text_field( $_POST['texto'] ?? '' );

        if ( ! $actuacion_id || ! $texto ) {
            wp_send_json_error( [ 'message' => __( 'Datos incompletos.', 'lezned-maintenance' ) ] );
        }

        $tareas_raw = get_post_meta( $actuacion_id, '_lznd_tareas', true );
        $tareas     = $tareas_raw ? json_decode( $tareas_raw, true ) : [];
        if ( ! is_array( $tareas ) ) {
            $tareas = [];
        }
        $tareas = self::fix_tareas_encoding( $tareas );

        $new_task = [
            'id'           => uniqid( 'task_', true ),
            'texto'        => $texto,
            'completada'   => false,
            'completada_en' => '',
        ];

        $tareas[] = $new_task;
        update_post_meta( $actuacion_id, '_lznd_tareas', wp_json_encode( $tareas, JSON_UNESCAPED_UNICODE ) );

        wp_send_json_success( [ 'task' => $new_task ] );
    }

    /**
     * Delete a task from an actuacion.
     */
    public static function handle_delete_task() {
        self::verify();

        $actuacion_id = intval( $_POST['actuacion_id'] ?? 0 );
        $task_id      = sanitize_text_field( $_POST['task_id'] ?? '' );

        if ( ! $actuacion_id || ! $task_id ) {
            wp_send_json_error( [ 'message' => __( 'Datos incompletos.', 'lezned-maintenance' ) ] );
        }

        $tareas_raw = get_post_meta( $actuacion_id, '_lznd_tareas', true );
        $tareas     = $tareas_raw ? json_decode( $tareas_raw, true ) : [];
        if ( ! is_array( $tareas ) ) {
            $tareas = [];
        }
        $tareas = self::fix_tareas_encoding( $tareas );

        $tareas = array_values( array_filter( $tareas, fn( $t ) => ( $t['id'] ?? '' ) !== $task_id ) );
        update_post_meta( $actuacion_id, '_lznd_tareas', wp_json_encode( $tareas, JSON_UNESCAPED_UNICODE ) );

        wp_send_json_success( [ 'success' => true ] );
    }

    /**
     * Upload a photo for an actuacion.
     */
    public static function handle_upload_foto() {
        self::verify();

        $actuacion_id = intval( $_POST['actuacion_id'] ?? 0 );

        if ( ! $actuacion_id ) {
            wp_send_json_error( [ 'message' => __( 'ID de actuación inválido.', 'lezned-maintenance' ) ] );
        }

        if ( empty( $_FILES['foto'] ) ) {
            wp_send_json_error( [ 'message' => __( 'No se recibió ningún archivo.', 'lezned-maintenance' ) ] );
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $attach_id = media_handle_upload( 'foto', $actuacion_id );

        if ( is_wp_error( $attach_id ) ) {
            wp_send_json_error( [ 'message' => $attach_id->get_error_message() ] );
        }

        $fotos = get_post_meta( $actuacion_id, '_lznd_fotos', true );
        if ( ! is_array( $fotos ) ) {
            $fotos = [];
        }
        $fotos[] = $attach_id;
        update_post_meta( $actuacion_id, '_lznd_fotos', $fotos );

        $url   = wp_get_attachment_url( $attach_id );
        $thumb = wp_get_attachment_image_url( $attach_id, 'thumbnail' );

        wp_send_json_success( [
            'attach_id' => $attach_id,
            'url'       => $url,
            'thumb'     => $thumb ?: $url,
        ] );
    }

    /**
     * Delete a photo from an actuacion.
     */
    public static function handle_delete_foto() {
        self::verify();

        $actuacion_id = intval( $_POST['actuacion_id'] ?? 0 );
        $attach_id    = intval( $_POST['attach_id'] ?? 0 );

        if ( ! $actuacion_id || ! $attach_id ) {
            wp_send_json_error( [ 'message' => __( 'Datos incompletos.', 'lezned-maintenance' ) ] );
        }

        $fotos = get_post_meta( $actuacion_id, '_lznd_fotos', true );
        if ( ! is_array( $fotos ) ) {
            $fotos = [];
        }

        $fotos = array_values( array_filter( $fotos, fn( $id ) => intval( $id ) !== $attach_id ) );
        update_post_meta( $actuacion_id, '_lznd_fotos', $fotos );

        wp_delete_attachment( $attach_id, true );

        wp_send_json_success( [ 'success' => true ] );
    }

    /**
     * Save a signature (tecnico or cliente).
     */
    public static function handle_save_signature() {
        self::verify();

        $actuacion_id   = intval( $_POST['actuacion_id'] ?? 0 );
        $tipo           = sanitize_key( $_POST['tipo'] ?? '' );
        $signature_data = $_POST['signature'] ?? '';
        $nombre         = sanitize_text_field( $_POST['nombre_firmante'] ?? '' );

        if ( ! $actuacion_id || ! in_array( $tipo, [ 'tecnico', 'cliente' ], true ) || empty( $signature_data ) ) {
            wp_send_json_error( [ 'message' => __( 'Datos incompletos.', 'lezned-maintenance' ) ] );
        }

        // Decode base64 PNG
        if ( preg_match( '/^data:image\/png;base64,(.+)$/', $signature_data, $m ) ) {
            $img_data = base64_decode( $m[1] );
        } else {
            $img_data = base64_decode( $signature_data );
        }

        if ( ! $img_data ) {
            wp_send_json_error( [ 'message' => __( 'Datos de firma inválidos.', 'lezned-maintenance' ) ] );
        }

        $upload_dir = wp_upload_dir();
        $subdir     = '/lznd-firmas';
        $target_dir = $upload_dir['basedir'] . $subdir;

        if ( ! file_exists( $target_dir ) ) {
            wp_mkdir_p( $target_dir );
            file_put_contents( $target_dir . '/index.php', '<?php // Silence is golden.' );
        }

        $filename = 'firma-' . $tipo . '-' . $actuacion_id . '-' . time() . '.png';
        $filepath = $target_dir . '/' . $filename;
        $fileurl  = $upload_dir['baseurl'] . $subdir . '/' . $filename;

        // Delete old signature file if exists
        $old_path = get_post_meta( $actuacion_id, '_lznd_firma_' . $tipo . '_path', true );
        if ( $old_path && file_exists( $old_path ) ) {
            @unlink( $old_path );
        }

        file_put_contents( $filepath, $img_data );

        update_post_meta( $actuacion_id, '_lznd_firma_' . $tipo . '_url', $fileurl );
        update_post_meta( $actuacion_id, '_lznd_firma_' . $tipo . '_path', $filepath );
        update_post_meta( $actuacion_id, '_lznd_nombre_firmante_' . $tipo, $nombre );
        update_post_meta( $actuacion_id, '_lznd_firma_fecha', current_time( 'mysql' ) );

        wp_send_json_success( [ 'url' => $fileurl ] );
    }

    /**
     * Save a specific field for an actuacion.
     */
    public static function handle_save_field() {
        self::verify();

        $actuacion_id = intval( $_POST['actuacion_id'] ?? 0 );
        $field        = sanitize_key( $_POST['field'] ?? '' );
        $value        = sanitize_text_field( $_POST['value'] ?? '' );

        $whitelist = [ '_lznd_observaciones', '_lznd_fecha_inicio', '_lznd_fecha_fin', '_lznd_estado' ];

        if ( ! $actuacion_id || ! in_array( $field, $whitelist, true ) ) {
            wp_send_json_error( [ 'message' => __( 'Campo no permitido.', 'lezned-maintenance' ) ] );
        }

        if ( $field === '_lznd_observaciones' ) {
            $value = sanitize_textarea_field( $_POST['value'] ?? '' );
        }

        update_post_meta( $actuacion_id, $field, $value );

        wp_send_json_success( [ 'success' => true ] );
    }

    /**
     * Submit an actuacion (send report via email or whatsapp).
     */
    public static function handle_submit_actuacion() {
        self::verify();

        $actuacion_id = intval( $_POST['actuacion_id'] ?? 0 );
        $canal        = sanitize_key( $_POST['canal'] ?? 'email' );

        if ( ! $actuacion_id || ! in_array( $canal, [ 'email', 'whatsapp' ], true ) ) {
            wp_send_json_error( [ 'message' => __( 'Datos incompletos.', 'lezned-maintenance' ) ] );
        }

        // Set estado enviado
        update_post_meta( $actuacion_id, '_lznd_estado', 'enviado' );

        // Set fecha_fin if not set
        $fecha_fin = get_post_meta( $actuacion_id, '_lznd_fecha_fin', true );
        if ( empty( $fecha_fin ) ) {
            update_post_meta( $actuacion_id, '_lznd_fecha_fin', current_time( 'Y-m-d H:i:s' ) );
        }

        // Send the report
        $result = LZND_Cron::send_report( $actuacion_id, $canal );

        // Advance project recurrence
        $proyecto_id = get_post_meta( $actuacion_id, '_lznd_proyecto_id', true );
        if ( $proyecto_id ) {
            $recurrencia = get_post_meta( $proyecto_id, '_lznd_recurrencia', true );
            if ( $recurrencia && $recurrencia !== 'ninguna' ) {
                LZND_Cron::advance_recurrence( $proyecto_id );
            }
        }

        if ( $canal === 'whatsapp' && is_array( $result ) && isset( $result['url'] ) ) {
            wp_send_json_success( [
                'success'       => true,
                'whatsapp_url'  => $result['url'],
            ] );
        }

        wp_send_json_success( [ 'success' => true ] );
    }

    /**
     * Get dashboard data for a project (stats + pending + upcoming + in-progress).
     */
    public static function handle_get_project_dashboard() {
        self::verify();

        $proyecto_id = intval( $_POST['proyecto_id'] ?? 0 );

        if ( ! $proyecto_id ) {
            wp_send_json_error( [ 'message' => __( 'ID de proyecto inválido.', 'lezned-maintenance' ) ] );
        }

        $all_actuaciones = get_posts( [
            'post_type'      => 'lznd_actuacion',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'   => '_lznd_proyecto_id',
                    'value' => $proyecto_id,
                    'type'  => 'NUMERIC',
                ],
            ],
        ] );

        $completadas_count = 0;
        $pendientes        = [];
        $proximas          = [];
        $en_curso          = null;
        $today             = current_time( 'Y-m-d' );

        foreach ( $all_actuaciones as $act ) {
            $estado     = get_post_meta( $act->ID, '_lznd_estado', true ) ?: 'pendiente';
            $fecha_prog = get_post_meta( $act->ID, '_lznd_fecha_prog', true ) ?: '';
            $tipo_tid   = get_post_meta( $act->ID, '_lznd_tipo_term_id', true );
            $tipo_term  = $tipo_tid ? get_term( $tipo_tid, 'lznd_tipo' ) : null;
            $tipo_name  = ( $tipo_term && ! is_wp_error( $tipo_term ) ) ? $tipo_term->name : __( 'Sin tipo', 'lezned-maintenance' );

            $tareas_raw = get_post_meta( $act->ID, '_lznd_tareas', true );
            $tareas     = $tareas_raw ? json_decode( $tareas_raw, true ) : [];
            if ( ! is_array( $tareas ) ) {
                $tareas = [];
            }
            $tareas = self::fix_tareas_encoding( $tareas );

            $total_t  = count( $tareas );
            $hechas_t = count( array_filter( $tareas, fn( $t ) => ! empty( $t['completada'] ) ) );

            $task_list = array_values( array_map( fn( $t ) => [
                'texto'      => $t['texto'] ?? '',
                'completada' => (bool) ( $t['completada'] ?? false ),
            ], $tareas ) );

            $act_data = [
                'id'         => $act->ID,
                'tipo'       => $tipo_name,
                'estado'     => $estado,
                'fecha_prog' => $fecha_prog,
                'total'      => $total_t,
                'hechas'     => $hechas_t,
                'tareas'     => $task_list,
            ];

            if ( in_array( $estado, [ 'enviado', 'cerrado' ], true ) ) {
                $completadas_count++;
            } elseif ( $estado === 'en_curso' ) {
                if ( ! $en_curso ) {
                    $en_curso = $act_data;
                }
                $pendientes[] = $act_data;
            } elseif ( $estado === 'pendiente' ) {
                if ( ! empty( $fecha_prog ) && $fecha_prog > $today ) {
                    // Future scheduled work — show in upcoming, not popup
                    $proximas[] = $act_data;
                } else {
                    // Past, today, or undated — likely started/abandoned work
                    if ( ! $en_curso ) {
                        $en_curso = $act_data;
                    }
                    $pendientes[] = $act_data;
                }
            }
        }

        usort( $proximas, fn( $a, $b ) => strcmp( $a['fecha_prog'] ?: '9999-12-31', $b['fecha_prog'] ?: '9999-12-31' ) );

        wp_send_json_success( [
            'completadas_count' => $completadas_count,
            'pendientes_count'  => count( $pendientes ),
            'proximas_count'    => count( $proximas ),
            'pendientes'        => $pendientes,
            'proximas'          => array_slice( $proximas, 0, 5 ),
            'en_curso'          => $en_curso,
        ] );
    }

    /**
     * Get client data for auto-fill.
     */
    public static function handle_get_client_data() {
        self::verify();

        $cliente_id = intval( $_POST['cliente_id'] ?? 0 );

        if ( ! $cliente_id ) {
            wp_send_json_error( [ 'message' => __( 'ID de cliente inválido.', 'lezned-maintenance' ) ] );
        }

        $post = get_post( $cliente_id );
        if ( ! $post || $post->post_type !== 'lznd_cliente' ) {
            wp_send_json_error( [ 'message' => __( 'Cliente no encontrado.', 'lezned-maintenance' ) ] );
        }

        wp_send_json_success( [
            'nombre'    => get_the_title( $cliente_id ),
            'email'     => get_post_meta( $cliente_id, '_lznd_email', true ),
            'telefono'  => get_post_meta( $cliente_id, '_lznd_telefono', true ),
            'direccion' => get_post_meta( $cliente_id, '_lznd_direccion', true ),
            'nif'       => get_post_meta( $cliente_id, '_lznd_nif', true ),
        ] );
    }

    /**
     * Calendar navigation AJAX.
     */
    public static function handle_calendar_nav() {
        self::verify();

        $proyecto_id = intval( $_POST['proyecto_id'] ?? 0 );
        $year        = intval( $_POST['year'] ?? date( 'Y' ) );
        $month       = intval( $_POST['month'] ?? date( 'n' ) );

        if ( ! $proyecto_id ) {
            wp_send_json_error( [ 'message' => __( 'ID de proyecto inválido.', 'lezned-maintenance' ) ] );
        }

        $html = self::render_calendar( $proyecto_id, $year, $month );

        wp_send_json_success( [ 'html' => $html ] );
    }

    /**
     * Render calendar HTML for a given project, year, month.
     */
    public static function render_calendar( $proyecto_id, $year, $month ) {
        $month = max( 1, min( 12, $month ) );
        $year  = max( 2000, min( 2100, $year ) );

        $first_day     = mktime( 0, 0, 0, $month, 1, $year );
        $days_in_month = (int) date( 't', $first_day );
        $start_dow     = (int) date( 'N', $first_day ); // 1=Mon, 7=Sun

        // Get actuaciones for this month
        $month_start = date( 'Y-m-01', $first_day );
        $month_end   = date( 'Y-m-t', $first_day );

        $actuaciones = get_posts( [
            'post_type'      => 'lznd_actuacion',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'   => '_lznd_proyecto_id',
                    'value' => $proyecto_id,
                    'type'  => 'NUMERIC',
                ],
                [
                    'key'     => '_lznd_fecha_prog',
                    'value'   => [ $month_start, $month_end . ' 23:59:59' ],
                    'compare' => 'BETWEEN',
                    'type'    => 'DATETIME',
                ],
            ],
        ] );

        // Index actuaciones by day
        $by_day = [];
        foreach ( $actuaciones as $act ) {
            $fecha = get_post_meta( $act->ID, '_lznd_fecha_prog', true );
            if ( $fecha ) {
                $day = (int) date( 'j', strtotime( $fecha ) );
                $estado = get_post_meta( $act->ID, '_lznd_estado', true ) ?: 'pendiente';
                $by_day[ $day ][] = [
                    'id'     => $act->ID,
                    'estado' => $estado,
                ];
            }
        }

        $months_es = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];

        $prev_month = $month - 1;
        $prev_year  = $year;
        if ( $prev_month < 1 ) { $prev_month = 12; $prev_year--; }

        $next_month = $month + 1;
        $next_year  = $year;
        if ( $next_month > 12 ) { $next_month = 1; $next_year++; }

        $today_day   = (int) date( 'j' );
        $today_month = (int) date( 'n' );
        $today_year  = (int) date( 'Y' );

        ob_start();
        ?>
        <div class="lznd-calendar" data-proyecto="<?php echo esc_attr( $proyecto_id ); ?>" data-year="<?php echo esc_attr( $year ); ?>" data-month="<?php echo esc_attr( $month ); ?>">
            <div class="lznd-cal-header">
                <button type="button" class="lznd-cal-nav" id="lznd-cal-prev" data-year="<?php echo esc_attr( $prev_year ); ?>" data-month="<?php echo esc_attr( $prev_month ); ?>">&lsaquo;</button>
                <span class="lznd-cal-title"><?php echo esc_html( $months_es[ $month ] . ' ' . $year ); ?></span>
                <button type="button" class="lznd-cal-nav" id="lznd-cal-next" data-year="<?php echo esc_attr( $next_year ); ?>" data-month="<?php echo esc_attr( $next_month ); ?>">&rsaquo;</button>
            </div>
            <div class="lznd-cal-grid">
                <div class="lznd-cal-day-name">L</div>
                <div class="lznd-cal-day-name">M</div>
                <div class="lznd-cal-day-name">X</div>
                <div class="lznd-cal-day-name">J</div>
                <div class="lznd-cal-day-name">V</div>
                <div class="lznd-cal-day-name lznd-cal-weekend">S</div>
                <div class="lznd-cal-day-name lznd-cal-weekend">D</div>
                <?php
                // Empty cells before first day
                for ( $i = 1; $i < $start_dow; $i++ ) {
                    echo '<div class="lznd-cal-day lznd-cal-day--empty"></div>';
                }

                for ( $day = 1; $day <= $days_in_month; $day++ ) {
                    $dow       = (int) date( 'N', mktime( 0, 0, 0, $month, $day, $year ) );
                    $is_today  = ( $day === $today_day && $month === $today_month && $year === $today_year );
                    $has_acts  = isset( $by_day[ $day ] );
                    $classes   = 'lznd-cal-day';
                    if ( $is_today ) $classes .= ' lznd-cal-day--today';
                    if ( $has_acts ) $classes .= ' lznd-cal-day--has-events';
                    if ( $dow >= 6 ) $classes .= ' lznd-cal-weekend';

                    echo '<div class="' . esc_attr( $classes ) . '">';
                    echo '<span class="lznd-cal-day-num">' . esc_html( $day ) . '</span>';

                    if ( $has_acts ) {
                        echo '<div class="lznd-cal-dots">';
                        foreach ( $by_day[ $day ] as $act_info ) {
                            $dot_class = 'lznd-cal-dot lznd-cal-dot--' . esc_attr( $act_info['estado'] );
                            echo '<span class="' . $dot_class . '" title="' . esc_attr( ucfirst( $act_info['estado'] ) ) . '"></span>';
                        }
                        echo '</div>';
                    }

                    echo '</div>';
                }

                // Fill remaining cells to complete the last row
                $total_cells = $start_dow - 1 + $days_in_month;
                $remaining   = $total_cells % 7;
                if ( $remaining > 0 ) {
                    for ( $i = $remaining; $i < 7; $i++ ) {
                        echo '<div class="lznd-cal-day lznd-cal-day--empty"></div>';
                    }
                }
                ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Create a new actuacion for a project + tipo.
     *
     * @param int $proyecto_id
     * @param int $tipo_term_id
     * @return int|WP_Error
     */
    public static function create_actuacion( $proyecto_id, $tipo_term_id ) {
        $proyecto = get_post( $proyecto_id );
        if ( ! $proyecto || $proyecto->post_type !== 'lznd_proyecto' ) {
            return new WP_Error( 'invalid_project', __( 'Proyecto no encontrado.', 'lezned-maintenance' ) );
        }

        $tipo_term = get_term( $tipo_term_id, 'lznd_tipo' );
        if ( is_wp_error( $tipo_term ) || ! $tipo_term ) {
            return new WP_Error( 'invalid_term', __( 'Tipo no encontrado.', 'lezned-maintenance' ) );
        }

        $current_user = wp_get_current_user();
        $fecha_prog   = get_post_meta( $proyecto_id, '_lznd_fecha_proxima', true );

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
        update_post_meta( $actuacion_id, '_lznd_tecnico', $current_user->display_name );
        update_post_meta( $actuacion_id, '_lznd_estado', 'pendiente' );
        update_post_meta( $actuacion_id, '_lznd_prioridad', get_post_meta( $proyecto_id, '_lznd_prioridad', true ) ?: 'normal' );
        update_post_meta( $actuacion_id, '_lznd_fecha_prog', $fecha_prog ?: current_time( 'Y-m-d' ) );
        update_post_meta( $actuacion_id, '_lznd_fecha_inicio', current_time( 'Y-m-d H:i:s' ) );
        update_post_meta( $actuacion_id, '_lznd_fecha_fin', '' );
        update_post_meta( $actuacion_id, '_lznd_observaciones', '' );
        update_post_meta( $actuacion_id, '_lznd_fotos', [] );

        // Copy tasks from lznd_tarea CPT filtered by this tipo term
        $tareas_args = [
            'post_type'      => 'lznd_tarea',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'menu_order',
            'order'          => 'ASC',
        ];

        // Try to filter by term
        $term_tareas = get_posts( array_merge( $tareas_args, [
            'tax_query' => [
                [
                    'taxonomy' => 'lznd_tipo',
                    'field'    => 'term_id',
                    'terms'    => $tipo_term_id,
                ],
            ],
        ] ) );

        if ( ! empty( $term_tareas ) ) {
            $source_tareas = $term_tareas;
        } else {
            // Fall back to all tasks
            $source_tareas = get_posts( $tareas_args );
        }

        $tareas = [];
        foreach ( $source_tareas as $tarea_post ) {
            $tipo_campo  = get_post_meta( $tarea_post->ID, '_lznd_tipo_campo', true ) ?: 'texto';
            $opciones_raw = get_post_meta( $tarea_post->ID, '_lznd_opciones', true );
            $opciones    = $opciones_raw ? json_decode( $opciones_raw, true ) : [];
            if ( ! is_array( $opciones ) ) {
                $opciones = [];
            }
            $tareas[] = [
                'id'            => uniqid( 'task_', true ),
                'texto'         => $tarea_post->post_title,
                'tipo_campo'    => $tipo_campo,
                'opciones'      => $opciones,
                'completada'    => false,
                'completada_en' => '',
                'valor'         => '',
            ];
        }

        update_post_meta( $actuacion_id, '_lznd_tareas', wp_json_encode( $tareas, JSON_UNESCAPED_UNICODE ) );

        return $actuacion_id;
    }

    /**
     * Fix legacy tareas whose 'texto' was stored with escaped unicode sequences.
     *
     * Handles two corruption patterns:
     *  1. "Revisión"  — literal backslash-u from double JSON encoding
     *  2. "Revisiu00f3n"   — backslash was lost, leaving bare "u00f3" in the string
     *
     * @param array $tareas
     * @return array
     */
    public static function fix_tareas_encoding( array $tareas ) {
        foreach ( $tareas as &$tarea ) {
            if ( ! isset( $tarea['texto'] ) || ! is_string( $tarea['texto'] ) ) {
                continue;
            }

            $cb = function ( $m ) {
                $decoded = json_decode( '"\\u' . $m[1] . '"' );
                return ( $decoded !== null ) ? $decoded : $m[0];
            };

            // Pass 1: literal \uXXXX with backslash (double-encoded JSON)
            $tarea['texto'] = preg_replace_callback(
                '/\\\\u([0-9a-fA-F]{4})/',
                $cb,
                $tarea['texto']
            );

            // Pass 2: bare uXXXX without backslash (backslash was stripped).
            // Only targets Latin Extended range U+00C0..U+00FF (all Western accents)
            // guarded by a letter on the left so we don't corrupt hex literals.
            $tarea['texto'] = preg_replace_callback(
                '/(?<=[a-zA-Z])(u00[c-fC-F][0-9a-fA-F])/',
                function ( $m ) use ( $cb ) {
                    return $cb( [ $m[0], substr( $m[1], 1 ) ] );
                },
                $tarea['texto']
            );
        }
        unset( $tarea );
        return $tareas;
    }

    /**
     * Save a task field value (text, select, radio) for an actuacion.
     */
    public static function handle_save_task_value() {
        self::verify();

        $actuacion_id = intval( $_POST['actuacion_id'] ?? 0 );
        $task_id      = sanitize_text_field( $_POST['task_id'] ?? '' );
        $valor        = sanitize_textarea_field( wp_unslash( $_POST['valor'] ?? '' ) );

        if ( ! $actuacion_id || ! $task_id ) {
            wp_send_json_error( [ 'message' => __( 'Datos incompletos.', 'lezned-maintenance' ) ] );
        }

        $tareas_raw = get_post_meta( $actuacion_id, '_lznd_tareas', true );
        $tareas     = $tareas_raw ? json_decode( $tareas_raw, true ) : [];
        if ( ! is_array( $tareas ) ) {
            $tareas = [];
        }

        $found = false;
        foreach ( $tareas as &$t ) {
            if ( isset( $t['id'] ) && $t['id'] === $task_id ) {
                $t['valor'] = $valor;
                $found = true;
                break;
            }
        }
        unset( $t );

        if ( ! $found ) {
            wp_send_json_error( [ 'message' => __( 'Tarea no encontrada.', 'lezned-maintenance' ) ] );
        }

        update_post_meta( $actuacion_id, '_lznd_tareas', wp_json_encode( $tareas, JSON_UNESCAPED_UNICODE ) );
        wp_send_json_success();
    }
}

LZND_Ajax::init();
