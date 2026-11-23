<?php
/**
 * Automatización completa:
 *  1. El CSV se genera cada día a la hora elegida.
 *  2. (Opcional) Después, cuando las fotos están preparadas, el plugin lanza la importación
 *     «Productos Cecotec» de WP All Import con sus propias URLs de cron (trigger + processing),
 *     llamándolas desde WP-Cron hasta que termina. Al acabar sincroniza el stock.
 *
 * Usa el mecanismo oficial de WP All Import Pro («Programación Cron»): no hace falta tocar
 * la importación ni contratar su servicio de programación.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CecoAutomation {

    const OPT_TIME      = 'ceco_auto_csv_time';     // 'HH:MM' hora local de la web
    const OPT_IMPORT    = 'ceco_auto_import';       // '1' | '0'
    const OPT_IMPORT_ID = 'ceco_auto_import_id';    // int, 0 = detectar
    const OPT_STATE     = 'ceco_auto_state';
    const EVENT_CSV     = 'ceco_import_products_event';
    const EVENT_STEP    = 'ceco_auto_step';
    const LOCK          = 'ceco_auto_lock';

    const MAX_IMAGE_WAIT = 4 * HOUR_IN_SECONDS;  // espera máxima a que se preparen las fotos
    const MAX_DURATION   = 10 * HOUR_IN_SECONDS; // duración máxima de una importación

    /* ------------------------------------------------------------ ajustes */

    public static function csv_time() {
        $t = (string) get_option( self::OPT_TIME, '05:00' );
        return preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $t ) ? $t : '05:00';
    }

    public static function auto_import() {
        return get_option( self::OPT_IMPORT, '0' ) === '1';
    }

    /**
     * Programa el CSV diario a la hora elegida (hora de la web).
     */
    public static function schedule_csv( $force = false ) {
        $next = wp_next_scheduled( self::EVENT_CSV );
        if ( $next && ! $force ) {
            return;
        }
        wp_clear_scheduled_hook( self::EVENT_CSV );

        list( $h, $m ) = array_map( 'intval', explode( ':', self::csv_time() ) );
        $tz  = wp_timezone();
        $now = new DateTime( 'now', $tz );
        $run = ( new DateTime( 'now', $tz ) )->setTime( $h, $m, 0 );
        if ( $run <= $now ) {
            $run->modify( '+1 day' );
        }
        wp_schedule_event( $run->getTimestamp(), 'daily', self::EVENT_CSV );
    }

    /* ------------------------------------------------------------ WP All Import */

    public static function import_id() {
        $id = (int) get_option( self::OPT_IMPORT_ID, 0 );
        if ( $id > 0 ) {
            return $id;
        }
        global $wpdb;
        $table = $wpdb->prefix . 'pmxi_imports';
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
            return 0;
        }
        return (int) $wpdb->get_var( "SELECT id FROM {$table} WHERE path LIKE '%products.csv%' ORDER BY id ASC LIMIT 1" );
    }

    public static function cron_key() {
        $options = get_option( 'PMXI_Plugin_Options', [] );
        return is_array( $options ) && ! empty( $options['cron_job_key'] ) ? (string) $options['cron_job_key'] : '';
    }

    private static function url( $action ) {
        return add_query_arg( [
            'import_key' => self::cron_key(),
            'import_id'  => self::import_id(),
            'action'     => $action,
        ], site_url( 'wp-load.php' ) );
    }

    /**
     * Llama a una URL de cron de WP All Import. Devuelve el mensaje de respuesta o WP_Error.
     */
    private static function call( $action ) {
        $response = wp_remote_get( self::url( $action ), [
            'timeout'   => 300,
            'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
            'headers'   => [ 'Cache-Control' => 'no-cache' ],
        ] );
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        $body = trim( wp_remote_retrieve_body( $response ) );
        $json = json_decode( $body, true );
        $msg  = is_array( $json ) && isset( $json['message'] ) ? (string) $json['message'] : wp_strip_all_tags( $body );
        return $msg !== '' ? $msg : 'HTTP ' . wp_remote_retrieve_response_code( $response );
    }

    /**
     * Requisitos para lanzar la importación automáticamente.
     */
    public static function check() {
        if ( ! self::import_id() ) {
            return new WP_Error( 'ceco_auto', 'No se encuentra la importación de WP All Import que usa products.csv.' );
        }
        if ( self::cron_key() === '' ) {
            return new WP_Error( 'ceco_auto', 'WP All Import no tiene «Cron Processing Key» (All Import › Ajustes).' );
        }
        return true;
    }

    /* ------------------------------------------------------------ estado */

    public static function state() {
        $s = get_option( self::OPT_STATE, [] );
        return is_array( $s ) ? $s : [];
    }

    private static function set_state( $changes ) {
        $s = array_merge( self::state(), $changes, [ 'updated' => current_time( 'mysql' ) ] );
        update_option( self::OPT_STATE, $s, false );
        return $s;
    }

    private static function next_step( $delay = 60 ) {
        wp_clear_scheduled_hook( self::EVENT_STEP );
        wp_schedule_single_event( time() + $delay, self::EVENT_STEP );
    }

    public static function running() {
        $s = self::state();
        return ! empty( $s['phase'] ) && in_array( $s['phase'], [ 'waiting_images', 'trigger', 'processing' ], true );
    }

    /**
     * Arranca un ciclo de actualización de la tienda.
     * $wait_images: esperar a que se preparen las fotos pendientes (ciclo automático tras el CSV).
     */
    public static function start( $origin, $wait_images = true ) {
        $check = self::check();
        if ( is_wp_error( $check ) ) {
            self::set_state( [ 'phase' => 'error', 'origin' => $origin, 'message' => $check->get_error_message() ] );
            return $check;
        }
        self::set_state( [
            'phase'      => $wait_images ? 'waiting_images' : 'trigger',
            'origin'     => $origin,
            'started'    => current_time( 'mysql' ),
            'started_ts' => time(),
            'calls'      => 0,
            'message'    => $wait_images ? 'Esperando a que se preparen las fotos.' : 'Lanzando la importación.',
        ] );
        self::next_step( $wait_images ? 120 : 1 );
        return true;
    }

    /**
     * Un paso del ciclo (lo llama WP-Cron y también el botón del panel).
     */
    public static function step() {
        if ( get_transient( self::LOCK ) ) {
            return self::state();
        }
        set_transient( self::LOCK, 1, 330 );
        if ( function_exists( 'set_time_limit' ) ) {
            @set_time_limit( 600 );
        }

        $s     = self::state();
        $phase = $s['phase'] ?? '';
        $age   = time() - (int) ( $s['started_ts'] ?? time() );

        if ( $phase === 'waiting_images' ) {
            $pending = CecoImageCache::enabled() ? count( CecoImageCache::queue_get() ) : 0;
            if ( $pending > 0 && $age < self::MAX_IMAGE_WAIT ) {
                $s = self::set_state( [ 'message' => sprintf( 'Esperando a que se preparen las fotos (%d pendientes).', $pending ) ] );
                self::next_step( 300 );
            } else {
                $s = self::set_state( [ 'phase' => 'trigger', 'message' => 'Lanzando la importación.' ] );
                self::next_step( 5 );
            }
        } elseif ( $phase === 'trigger' ) {
            $msg = self::call( 'trigger' );
            if ( is_wp_error( $msg ) ) {
                $s = self::set_state( [ 'phase' => 'error', 'message' => 'No se pudo lanzar la importación: ' . $msg->get_error_message() ] );
            } elseif ( stripos( $msg, 'manual' ) !== false ) {
                $s = self::set_state( [ 'phase' => 'error', 'message' => 'La importación se está ejecutando a mano en este momento; no se lanza otra. (' . $msg . ')' ] );
            } else {
                $s = self::set_state( [ 'phase' => 'processing', 'message' => 'Importación lanzada: ' . $msg ] );
                self::next_step( 30 );
            }
        } elseif ( $phase === 'processing' ) {
            $msg   = self::call( 'processing' );
            $calls = (int) ( $s['calls'] ?? 0 ) + 1;
            if ( is_wp_error( $msg ) ) {
                // Un corte puntual (timeout) no detiene el ciclo: se reintenta.
                $s = self::set_state( [ 'calls' => $calls, 'message' => 'Reintentando tras un corte: ' . $msg->get_error_message() ] );
                self::next_step( 120 );
            } elseif ( preg_match( '/complete|not triggered/i', $msg ) ) {
                $sync = CecoStockFeed::sync_woocommerce_stock( true );
                $s = self::set_state( [
                    'phase'    => 'done',
                    'calls'    => $calls,
                    'finished' => current_time( 'mysql' ),
                    'message'  => 'Tienda actualizada. ' . ( is_wp_error( $sync ) ? 'Stock: ' . $sync->get_error_message() : 'Stock sincronizado.' ),
                ] );
            } elseif ( $age > self::MAX_DURATION ) {
                $s = self::set_state( [ 'phase' => 'error', 'calls' => $calls, 'message' => 'La importación lleva demasiado tiempo; revisar All Import › Gestionar Importaciones. Último mensaje: ' . $msg ] );
            } else {
                $s = self::set_state( [ 'calls' => $calls, 'message' => 'Importando: ' . $msg ] );
                self::next_step( 60 );
            }
        }

        delete_transient( self::LOCK );
        return $s;
    }

    public static function describe() {
        $s = self::state();
        if ( empty( $s['phase'] ) ) {
            return self::auto_import() ? 'Todavía no se ha actualizado la tienda automáticamente.' : 'Actualización automática desactivada.';
        }
        $icon = [ 'done' => '✅', 'error' => '❌' ][ $s['phase'] ] ?? '⏳';
        $when = $s['phase'] === 'done' ? ( $s['finished'] ?? $s['updated'] ) : ( $s['updated'] ?? '' );
        return trim( $icon . ' ' . $when . ' — ' . ( $s['message'] ?? '' ) );
    }
}

/*******************************************************************************
 *  Hooks
 ******************************************************************************/

add_action( CecoAutomation::EVENT_STEP, [ 'CecoAutomation', 'step' ] );

// Tras el CSV diario (cron), si está activada la actualización automática, se lanza la importación.
add_action( 'ceco_csv_built', function ( $result, $origin ) {
    if ( $origin === 'cron' && CecoAutomation::auto_import() && ! CecoAutomation::running() ) {
        CecoAutomation::start( 'automática', true );
    }
}, 10, 2 );

/*******************************************************************************
 *  AJAX
 ******************************************************************************/

function ceco_ajax_auto_start() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Sin permisos.' ] );
    }
    check_ajax_referer( 'ceco_sync_stock', 'nonce' );
    if ( CecoAutomation::running() ) {
        wp_send_json_success( [ 'running' => true, 'message' => CecoAutomation::describe() ] );
    }
    $r = CecoAutomation::start( 'manual', false );
    if ( is_wp_error( $r ) ) {
        wp_send_json_error( [ 'message' => $r->get_error_message() ] );
    }
    wp_send_json_success( [ 'running' => true, 'message' => CecoAutomation::describe() ] );
}
add_action( 'wp_ajax_ceco_auto_start', 'ceco_ajax_auto_start' );

function ceco_ajax_auto_step() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Sin permisos.' ] );
    }
    check_ajax_referer( 'ceco_sync_stock', 'nonce' );
    if ( CecoAutomation::running() ) {
        CecoAutomation::step();
    }
    wp_send_json_success( [ 'running' => CecoAutomation::running(), 'message' => CecoAutomation::describe() ] );
}
add_action( 'wp_ajax_ceco_auto_step', 'ceco_ajax_auto_step' );

function ceco_handle_save_auto_settings() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Sin permisos.' );
    }
    check_admin_referer( 'ceco_save_auto_settings' );

    $time = isset( $_POST['ceco_auto_csv_time'] ) ? sanitize_text_field( wp_unslash( $_POST['ceco_auto_csv_time'] ) ) : '05:00';
    update_option( CecoAutomation::OPT_TIME, $time );
    update_option( CecoAutomation::OPT_IMPORT, empty( $_POST['ceco_auto_import'] ) ? '0' : '1' );
    update_option( CecoAutomation::OPT_IMPORT_ID, max( 0, (int) ( $_POST['ceco_auto_import_id'] ?? 0 ) ) );
    CecoAutomation::schedule_csv( true );

    wp_redirect( admin_url( 'admin.php?page=import-cecopublisher' ) );
    exit;
}
add_action( 'admin_post_save_auto_settings', 'ceco_handle_save_auto_settings' );
