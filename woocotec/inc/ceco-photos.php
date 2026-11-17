<?php
/**
 * Poner foto a los productos publicados que no tienen imagen destacada.
 *
 * Algunos productos se crearon en importaciones anteriores sin imagen y, como la importación
 * de WP All Import no actualiza las imágenes de los productos existentes, siguen sin ella.
 * Esta herramienta les asigna las fotos que trae el CSV actual (las copias preparadas de
 * uploads/woocotec-img si existen; si no, la URL original). No toca productos que ya tienen foto.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CecoMissingPhotos {

    const OPT_CURRENT = 'ceco_photo_current';   // producto en proceso (para detectar caídas)
    const OPT_FAILED  = 'ceco_photo_failed';    // ID => time

    /**
     * SKU normalizado => lista de URLs de imagen, leídas del CSV generado.
     */
    private static function csv_images() {
        $file = CecoPublisherToWooCommerce::csv_path();
        if ( ! file_exists( $file ) ) {
            return new WP_Error( 'ceco_photos', 'Todavía no hay CSV generado. Pulsa antes «Importación manual».' );
        }
        $handle = fopen( $file, 'r' );
        $header = fgetcsv( $handle, 0, ',', '"', '\\' );
        $c_sku  = array_search( 'SKU', $header, true );
        $c_main = array_search( 'Imagen destacada', $header, true );
        $c_gal  = array_search( 'Galería', $header, true );

        $map = [];
        while ( ( $row = fgetcsv( $handle, 0, ',', '"', '\\' ) ) !== false ) {
            $urls = [];
            if ( $c_main !== false && ! empty( $row[ $c_main ] ) ) {
                $urls[] = trim( $row[ $c_main ] );
            }
            if ( $c_gal !== false && ! empty( $row[ $c_gal ] ) ) {
                foreach ( explode( '|', $row[ $c_gal ] ) as $url ) {
                    if ( trim( $url ) !== '' ) {
                        $urls[] = trim( $url );
                    }
                }
            }
            if ( $c_sku !== false && ! empty( $row[ $c_sku ] ) && ! empty( $urls ) ) {
                $map[ CecoStockFeed::normalize_sku( $row[ $c_sku ] ) ] = array_values( array_unique( $urls ) );
            }
        }
        fclose( $handle );
        return $map;
    }

    /**
     * Productos publicados sin imagen destacada que tienen fotos en el CSV.
     *
     * @return array|WP_Error [ [id, title, sku, images], ... ]
     */
    public static function find() {
        global $wpdb;

        $csv = self::csv_images();
        if ( is_wp_error( $csv ) ) {
            return $csv;
        }
        $failed = (array) get_option( self::OPT_FAILED, [] );

        $rows = $wpdb->get_results(
            "SELECT p.ID, p.post_title, sku.meta_value AS sku
               FROM {$wpdb->posts} p
               INNER JOIN {$wpdb->postmeta} sku ON sku.post_id = p.ID AND sku.meta_key = '_sku' AND sku.meta_value <> ''
               LEFT JOIN {$wpdb->postmeta} th ON th.post_id = p.ID AND th.meta_key = '_thumbnail_id'
              WHERE p.post_type = 'product' AND p.post_status = 'publish'
                AND ( th.meta_value IS NULL OR th.meta_value = '' OR th.meta_value = '0' )
              ORDER BY p.ID ASC"
        );

        $found = [];
        foreach ( $rows as $row ) {
            $key = CecoStockFeed::normalize_sku( $row->sku );
            if ( ! isset( $csv[ $key ] ) || isset( $failed[ (int) $row->ID ] ) ) {
                continue;
            }
            $found[] = [
                'id'     => (int) $row->ID,
                'title'  => $row->post_title,
                'sku'    => $row->sku,
                'images' => array_slice( $csv[ $key ], 0, CecoImageCache::per_product() ),
            ];
        }
        return $found;
    }

    /**
     * Fichero temporal con la imagen: copia local si está preparada, si no la descarga.
     */
    private static function fetch( $url ) {
        $base = CecoImageCache::base_url() . '/';
        if ( strpos( $url, $base ) === 0 ) {
            $local = CecoImageCache::dir() . '/' . rawurldecode( substr( $url, strlen( $base ) ) );
            if ( file_exists( $local ) ) {
                $tmp = wp_tempnam( basename( $local ) );
                if ( $tmp && @copy( $local, $tmp ) ) {
                    return [ $tmp, basename( $local ) ];
                }
            }
        }
        $tmp = download_url( $url, 30 );
        if ( is_wp_error( $tmp ) ) {
            return $tmp;
        }
        return [ $tmp, rawurldecode( basename( (string) wp_parse_url( $url, PHP_URL_PATH ) ) ) ];
    }

    /**
     * Asigna fotos a productos de la lista durante $budget segundos.
     */
    public static function process( $budget = 15 ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $failed = (array) get_option( self::OPT_FAILED, [] );

        // Si el lote anterior se cayó con un producto, se descarta para no atascarse.
        $crashed = (int) get_option( self::OPT_CURRENT, 0 );
        if ( $crashed ) {
            $failed[ $crashed ] = time();
            delete_option( self::OPT_CURRENT );
            update_option( self::OPT_FAILED, $failed, false );
        }

        $list = self::find();
        if ( is_wp_error( $list ) ) {
            return $list;
        }

        // Solo las miniaturas esenciales (mismo ajuste que durante la importación).
        $GLOBALS['ceco_wpai_importing'] = true;

        $start = microtime( true );
        $done  = 0;
        $errors = 0;

        foreach ( $list as $item ) {
            if ( ( microtime( true ) - $start ) > $budget ) {
                break;
            }
            if ( function_exists( 'set_time_limit' ) ) {
                @set_time_limit( 120 );
            }
            update_option( self::OPT_CURRENT, $item['id'], false );

            $ids = [];
            foreach ( $item['images'] as $url ) {
                $file = self::fetch( $url );
                if ( is_wp_error( $file ) ) {
                    continue;
                }
                $attachment = media_handle_sideload( [ 'name' => $file[1], 'tmp_name' => $file[0] ], $item['id'], $item['title'] );
                if ( is_wp_error( $attachment ) ) {
                    @unlink( $file[0] );
                    continue;
                }
                $ids[] = (int) $attachment;
            }

            if ( ! empty( $ids ) ) {
                set_post_thumbnail( $item['id'], array_shift( $ids ) );
                if ( ! empty( $ids ) ) {
                    update_post_meta( $item['id'], '_product_image_gallery', implode( ',', $ids ) );
                }
                if ( function_exists( 'wc_delete_product_transients' ) ) {
                    wc_delete_product_transients( $item['id'] );
                }
                $done++;
            } else {
                $errors++;
                $failed[ $item['id'] ] = time();
                error_log( '⚠️ No se ha podido poner foto al producto ' . $item['id'] . ' (' . $item['sku'] . ')' );
            }
            delete_option( self::OPT_CURRENT );
        }

        update_option( self::OPT_FAILED, $failed, false );
        $remaining = self::find();

        return [
            'done'      => $done,
            'errors'    => $errors,
            'remaining' => is_wp_error( $remaining ) ? 0 : count( $remaining ),
        ];
    }
}

/*******************************************************************************
 *  AJAX
 ******************************************************************************/

function ceco_ajax_find_missing_photos() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Sin permisos.' ] );
    }
    check_ajax_referer( 'ceco_sync_stock', 'nonce' );

    $found = CecoMissingPhotos::find();
    if ( is_wp_error( $found ) ) {
        wp_send_json_error( [ 'message' => $found->get_error_message() ] );
    }
    $items = array_map( function ( $it ) {
        return [ 'id' => $it['id'], 'title' => $it['title'], 'sku' => $it['sku'], 'n' => count( $it['images'] ) ];
    }, array_slice( $found, 0, 50 ) );
    wp_send_json_success( [ 'count' => count( $found ), 'items' => $items ] );
}
add_action( 'wp_ajax_ceco_find_missing_photos', 'ceco_ajax_find_missing_photos' );

function ceco_ajax_fix_missing_photos() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Sin permisos.' ] );
    }
    check_ajax_referer( 'ceco_sync_stock', 'nonce' );

    $result = CecoMissingPhotos::process( 15 );
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( [ 'message' => $result->get_error_message() ] );
    }
    wp_send_json_success( $result );
}
add_action( 'wp_ajax_ceco_fix_missing_photos', 'ceco_ajax_fix_missing_photos' );
