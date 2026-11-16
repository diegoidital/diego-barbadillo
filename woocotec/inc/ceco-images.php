<?php
/**
 * Optimización de la carga de imágenes en WP All Import.
 *
 * Lo que más tarda al importar no es descargar las fotos sino que WordPress genere todas las
 * miniaturas de cada una. Ese tiempo depende de los píxeles de la foto original y del número de
 * tamaños registrados. Para reducirlo:
 *
 *  1. Caché de fotos: el plugin descarga las fotos en segundo plano (WP-Cron o botón), las reduce
 *     a un máximo de N px (1200 por defecto) y las guarda en wp-content/uploads/woocotec-img/.
 *     El CSV apunta a esas copias, así WP All Import descarga desde el propio servidor archivos
 *     pequeños. Si una foto aún no está preparada, el CSV usa la URL original.
 *  2. Solo productos nuevos: los productos existentes no actualizan imágenes, así que solo se
 *     preparan las fotos de los productos que WP All Import va a crear.
 *  3. Máximo de fotos por producto.
 *  4. Miniaturas esenciales: mientras WP All Import importa, WordPress solo genera los tamaños
 *     que usa WooCommerce. Los demás tamaños, si alguna plantilla los pide, se sirven con la
 *     imagen más próxima.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CecoImageCache {

    const OPT_ENABLED     = 'ceco_img_cache';         // '1' | '0'
    const OPT_MAX_PX      = 'ceco_img_max_px';        // int
    const OPT_ONLY_NEW    = 'ceco_img_only_new';      // '1' | '0'
    const OPT_PER_PRODUCT = 'ceco_img_per_product';   // int
    const OPT_LIGHT_SIZES = 'ceco_img_light_sizes';   // '1' | '0'
    const OPT_STATS       = 'ceco_img_stats';
    const OPT_FAILED      = 'ceco_img_failed';
    const LOCK            = 'ceco_img_lock';
    const OPT_CURRENT     = 'ceco_img_current';       // foto en proceso (para detectar caídas)
    const EVENT           = 'ceco_process_images_event';

    // Fotos de más de 16 megapíxeles (p. ej. originales de Plytix de 4.600-5.100 px) no se abren:
    // Imagick usa memoria fuera del límite de PHP y el servidor puede matar el proceso.
    const MAX_PIXELS      = 16000000;

    // Tamaños que se generan durante la importación si está activado "miniaturas esenciales".
    const ESSENTIAL_SIZES = [
        'thumbnail',
        'medium',
        'woocommerce_thumbnail',
        'woocommerce_single',
        'woocommerce_gallery_thumbnail',
        'shop_catalog',
        'shop_single',
        'shop_thumbnail',
    ];

    /* ---------------------------------------------------------------- ajustes */

    public static function enabled()      { return get_option( self::OPT_ENABLED, '1' ) === '1'; }
    public static function only_new()     { return get_option( self::OPT_ONLY_NEW, '1' ) === '1'; }
    public static function light_sizes()  { return get_option( self::OPT_LIGHT_SIZES, '1' ) === '1'; }
    public static function max_px()       { return max( 600, min( 3000, (int) get_option( self::OPT_MAX_PX, 1200 ) ) ); }
    public static function per_product()  { return max( 1, min( 20, (int) get_option( self::OPT_PER_PRODUCT, 5 ) ) ); }

    /* ---------------------------------------------------------------- rutas */

    public static function dir() {
        $upload = wp_upload_dir();
        return trailingslashit( $upload['basedir'] ) . 'woocotec-img';
    }

    public static function base_url() {
        $upload = wp_upload_dir();
        return trailingslashit( $upload['baseurl'] ) . 'woocotec-img';
    }

    private static function queue_file() {
        $upload = wp_upload_dir();
        return trailingslashit( $upload['basedir'] ) . 'woocotec/image-queue.json';
    }

    /**
     * Nombre local estable para una URL: nombre original + hash corto + extensión original.
     */
    public static function local_name( $url ) {
        $path = (string) wp_parse_url( $url, PHP_URL_PATH );
        $base = rawurldecode( basename( $path ) );
        $ext  = strtolower( pathinfo( $base, PATHINFO_EXTENSION ) );
        if ( ! in_array( $ext, [ 'jpg', 'jpeg', 'png', 'gif', 'webp' ], true ) ) {
            $ext = 'jpg';
        }
        $name = sanitize_file_name( pathinfo( $base, PATHINFO_FILENAME ) );
        $name = substr( $name !== '' ? $name : 'img', 0, 80 );
        return $name . '-' . substr( md5( $url ), 0, 8 ) . '.' . $ext;
    }

    /**
     * URL de la copia local si ya está preparada; null si no.
     */
    public static function cached_url( $url ) {
        $name = self::local_name( $url );
        if ( file_exists( self::dir() . '/' . $name ) ) {
            return self::base_url() . '/' . rawurlencode( $name );
        }
        return null;
    }

    /* ---------------------------------------------------------------- cola */

    public static function queue_get() {
        $file = self::queue_file();
        if ( ! file_exists( $file ) ) {
            return [];
        }
        $data = json_decode( (string) file_get_contents( $file ), true );
        return is_array( $data ) ? $data : [];
    }

    private static function queue_put( $urls ) {
        $file = self::queue_file();
        wp_mkdir_p( dirname( $file ) );
        file_put_contents( $file, wp_json_encode( array_values( $urls ) ) );
    }

    /**
     * Sustituye la cola por las URLs pendientes del último CSV y programa el proceso.
     */
    public static function queue_set( $urls ) {
        $failed = (array) get_option( self::OPT_FAILED, [] );
        $urls   = array_values( array_unique( array_filter( $urls, function ( $url ) use ( $failed ) {
            // Las que fallaron en las últimas 24 h no se reintentan.
            return ! ( isset( $failed[ $url ] ) && ( time() - $failed[ $url ] ) < DAY_IN_SECONDS );
        } ) ) );
        self::queue_put( $urls );

        $stats            = (array) get_option( self::OPT_STATS, [] );
        $stats['queued']  = count( $urls );
        $stats['done']    = 0;
        $stats['started'] = current_time( 'mysql' );
        update_option( self::OPT_STATS, $stats, false );

        if ( ! empty( $urls ) && ! wp_next_scheduled( self::EVENT ) ) {
            wp_schedule_single_event( time() + 30, self::EVENT );
        }
    }

    /* ---------------------------------------------------------------- proceso */

    /**
     * Prepara fotos de la cola durante $budget segundos como máximo.
     *
     * @return array [ 'processed' => int, 'remaining' => int, 'failed' => int ]
     */
    public static function process( $budget = 20 ) {
        if ( get_transient( self::LOCK ) ) {
            return [ 'processed' => 0, 'remaining' => count( self::queue_get() ), 'failed' => 0, 'busy' => true ];
        }
        set_transient( self::LOCK, 1, 60 );

        require_once ABSPATH . 'wp-admin/includes/file.php';

        $dir = self::dir();
        wp_mkdir_p( $dir );
        if ( ! file_exists( $dir . '/index.php' ) ) {
            @file_put_contents( $dir . '/index.php', '<?php // Silence is golden' );
        }

        $queue   = self::queue_get();
        $failed  = (array) get_option( self::OPT_FAILED, [] );

        // Si el lote anterior se cayó (memoria, tiempo...) con una foto, esa foto se descarta
        // para no quedarse atascado en ella: el CSV usará su URL original.
        $crashed = get_option( self::OPT_CURRENT, '' );
        if ( $crashed !== '' ) {
            $failed[ $crashed ] = time();
            $queue = array_values( array_diff( $queue, [ $crashed ] ) );
            delete_option( self::OPT_CURRENT );
            error_log( '⚠️ Imagen Cecotec descartada tras una caída del proceso: ' . $crashed );
        }

        if ( function_exists( 'wp_raise_memory_limit' ) ) {
            wp_raise_memory_limit( 'image' );
        }
        $memory_limit = function_exists( 'wp_convert_hr_to_bytes' ) ? wp_convert_hr_to_bytes( ini_get( 'memory_limit' ) ) : -1;
        $max     = self::max_px();
        $start   = microtime( true );
        $done    = 0;
        $errors  = 0;

        while ( ! empty( $queue ) && ( microtime( true ) - $start ) < $budget ) {
            $url  = array_shift( $queue );
            $dest = $dir . '/' . self::local_name( $url );
            if ( file_exists( $dest ) ) {
                continue;
            }

            update_option( self::OPT_CURRENT, $url, false );
            if ( function_exists( 'set_time_limit' ) ) {
                @set_time_limit( 120 ); // una foto pesada (descarga + reducción) no debe agotar el tiempo de PHP
            }

            $ok  = false;
            $tmp = download_url( $url, 30 );
            if ( ! is_wp_error( $tmp ) ) {
                try {
                    // Fotos enormes (originales de Plytix de ~5000 px): abrirlas puede agotar la memoria
                    // de PHP. Si no cabe, se descarta y el CSV usa la URL original.
                    $info = @getimagesize( $tmp );
                    $need = $info ? (int) $info[0] * (int) $info[1] * 6 : 0;
                    if ( ! $info ) {
                        error_log( '⚠️ Imagen Cecotec no válida: ' . $url );
                    } elseif ( (int) $info[0] * (int) $info[1] > self::MAX_PIXELS ) {
                        error_log( sprintf( '⚠️ Imagen Cecotec demasiado grande (%dx%d), se usa la URL original: %s', $info[0], $info[1], $url ) );
                    } elseif ( $memory_limit > 0 && memory_get_usage() + $need > $memory_limit * 0.8 ) {
                        error_log( sprintf( '⚠️ Imagen Cecotec demasiado grande para la memoria de PHP (%dx%d): %s', $info[0], $info[1], $url ) );
                    } else {
                        $editor = wp_get_image_editor( $tmp );
                        if ( ! is_wp_error( $editor ) ) {
                            $size = $editor->get_size();
                            if ( $size['width'] > $max || $size['height'] > $max ) {
                                $editor->resize( $max, $max, false );
                            }
                            $editor->set_quality( 85 );
                            $saved = $editor->save( $dest );
                            $ok    = ! is_wp_error( $saved );
                            unset( $editor );
                        }
                    }
                } catch ( \Throwable $e ) {
                    error_log( '⚠️ Error preparando imagen Cecotec ' . $url . ': ' . $e->getMessage() );
                }
                @unlink( $tmp );
            }
            delete_option( self::OPT_CURRENT );

            if ( $ok ) {
                $done++;
            } else {
                $errors++;
                $failed[ $url ] = time();
                error_log( '⚠️ Imagen Cecotec no preparada: ' . $url );
            }
        }

        self::queue_put( $queue );
        update_option( self::OPT_FAILED, $failed, false );

        $stats             = (array) get_option( self::OPT_STATS, [] );
        $stats['done']     = (int) ( $stats['done'] ?? 0 ) + $done;
        $stats['failed']   = count( array_filter( $failed, function ( $t ) { return ( time() - $t ) < DAY_IN_SECONDS; } ) );
        $stats['last_run'] = current_time( 'mysql' );
        $stats['remaining'] = count( $queue );
        update_option( self::OPT_STATS, $stats, false );

        delete_transient( self::LOCK );

        return [ 'processed' => $done, 'remaining' => count( $queue ), 'failed' => $errors ];
    }

    /**
     * Cron: procesa un lote y se vuelve a programar mientras quede cola. Al terminar,
     * regenera el CSV para que apunte a las copias locales.
     */
    public static function cron() {
        $result = self::process( 20 );
        if ( ! empty( $result['busy'] ) || $result['remaining'] > 0 ) {
            if ( ! wp_next_scheduled( self::EVENT ) ) {
                wp_schedule_single_event( time() + 60, self::EVENT );
            }
            return;
        }
        if ( $result['processed'] > 0 ) {
            $importer = new CecoPublisherToWooCommerce();
            $importer->build_csv();
        }
    }

    public static function describe() {
        $s = (array) get_option( self::OPT_STATS, [] );
        if ( empty( $s ) ) {
            return 'Todavía no se han preparado fotos.';
        }
        $remaining = count( self::queue_get() );
        if ( $remaining > 0 ) {
            return sprintf( '⏳ Preparando fotos: %d hechas, %d pendientes%s. Último lote: %s.',
                (int) ( $s['done'] ?? 0 ), $remaining,
                ! empty( $s['failed'] ) ? ', ' . (int) $s['failed'] . ' con error' : '',
                $s['last_run'] ?? '-' );
        }
        return sprintf( '✅ Todas las fotos del CSV están preparadas (%d en este ciclo%s). Último lote: %s.',
            (int) ( $s['done'] ?? 0 ),
            ! empty( $s['failed'] ) ? ', ' . (int) $s['failed'] . ' con error: se usa la URL original' : '',
            $s['last_run'] ?? '-' );
    }
}

add_action( CecoImageCache::EVENT, [ 'CecoImageCache', 'cron' ] );

/*******************************************************************************
 *  Miniaturas esenciales durante la importación de WP All Import
 ******************************************************************************/

function ceco_mark_wpai_import() {
    $GLOBALS['ceco_wpai_importing'] = true;
}
add_action( 'pmxi_before_xml_import', 'ceco_mark_wpai_import' );
add_action( 'pmxi_before_post_import', 'ceco_mark_wpai_import' );

add_filter( 'intermediate_image_sizes_advanced', function ( $sizes ) {
    if ( empty( $GLOBALS['ceco_wpai_importing'] ) || ! CecoImageCache::light_sizes() ) {
        return $sizes;
    }
    return array_intersect_key( $sizes, array_flip( CecoImageCache::ESSENTIAL_SIZES ) );
} );

/*******************************************************************************
 *  AJAX: preparar fotos ahora (lotes de ~20 s)
 ******************************************************************************/

function ceco_ajax_process_images() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Sin permisos.' ] );
    }
    check_ajax_referer( 'ceco_sync_stock', 'nonce' );

    $result = CecoImageCache::process( 10 );
    $result['message'] = CecoImageCache::describe();
    wp_send_json_success( $result );
}
add_action( 'wp_ajax_ceco_process_images', 'ceco_ajax_process_images' );

/*******************************************************************************
 *  Guardar ajustes
 ******************************************************************************/

function ceco_handle_save_image_settings() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Sin permisos.' );
    }
    check_admin_referer( 'ceco_save_image_settings' );

    update_option( CecoImageCache::OPT_ENABLED, empty( $_POST['ceco_img_cache'] ) ? '0' : '1' );
    update_option( CecoImageCache::OPT_ONLY_NEW, empty( $_POST['ceco_img_only_new'] ) ? '0' : '1' );
    update_option( CecoImageCache::OPT_LIGHT_SIZES, empty( $_POST['ceco_img_light_sizes'] ) ? '0' : '1' );
    update_option( CecoImageCache::OPT_MAX_PX, (int) ( $_POST['ceco_img_max_px'] ?? 1200 ) );
    update_option( CecoImageCache::OPT_PER_PRODUCT, (int) ( $_POST['ceco_img_per_product'] ?? 5 ) );

    wp_redirect( admin_url( 'admin.php?page=import-cecopublisher' ) );
    exit;
}
add_action( 'admin_post_save_image_settings', 'ceco_handle_save_image_settings' );
