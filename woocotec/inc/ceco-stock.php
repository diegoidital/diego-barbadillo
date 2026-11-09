<?php
/**
 * Integración de stock con los feeds de dropshipping de Cecotec.
 *
 * Cecotec publica un CSV (separado por ";") por zona con, al menos, las
 * columnas public_id, title, stock y barcodes_ean_13. El campo public_id es el
 * mismo SKU/REFERENCIA que se usa en Cecopublisher.
 *
 * Esta clase:
 *  - descarga y cachea el feed de la zona seleccionada,
 *  - devuelve un mapa SKU => stock para añadirlo al CSV de importación,
 *  - sincroniza directamente el stock de WooCommerce (cron cada hora y botón manual),
 *    de modo que solo se puedan comprar productos con stock disponible.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CecoStockFeed {

    // Desde el 24/09 Cecotec solo sirve los feeds desde cecobi-external.
    const BASE_URL = 'https://cecobi-external.cecotec.cloud/ws/';

    const CACHE_KEY   = 'ceco_stock_feed_cache';
    const CACHE_TTL   = 600; // 10 minutos: evita descargar el feed dos veces seguidas (CSV + sincronización)
    const LAST_SYNC   = 'ceco_stock_last_sync';
    const URL_OPTION  = 'ceco_stock_feed_url';

    /**
     * Zona (valor guardado en ceco_selected_zone) => etiqueta del feed de Cecotec.
     */
    public static function zone_labels() {
        return [
            'Español'   => 'DROPES',
            'Portugues' => 'DROPPT',
            'Italiano'  => 'DROPIT',
            'Alemán'    => 'DROPDE',
            'Francés'   => 'DROPFR',
            'Holandes'  => 'DROPZ1', // Países Bajos pertenece a la Zona 1
            'Zona 1'    => 'DROPZ1', // At - Be - Lux - Nl
            'Zona 2'    => 'DROPZ2', // Dinamarca
            'Zona 3'    => 'DROPZ3', // Suecia - Finlandia - Irlanda
        ];
    }

    /**
     * URL por defecto del feed para una zona.
     * España usa getstockfeedb2cNew.php (incluye precios); el resto getstockfeedb2c.php.
     */
    public static function default_url( $zone ) {
        $labels = self::zone_labels();
        $label  = $labels[ $zone ] ?? 'DROPES';
        $script = ( $label === 'DROPES' ) ? 'getstockfeedb2cNew.php' : 'getstockfeedb2c.php';
        return self::BASE_URL . $script . '?etiqueta=' . $label;
    }

    /**
     * URL que se va a usar: la personalizada (si se ha guardado) o la de la zona.
     */
    public static function feed_url( $zone = null ) {
        $custom = trim( (string) get_option( self::URL_OPTION, '' ) );
        if ( $custom !== '' ) {
            return $custom;
        }
        if ( $zone === null ) {
            $zone = get_option( 'ceco_selected_zone', 'Español' );
        }
        return self::default_url( $zone );
    }

    /**
     * Normaliza un SKU para poder comparar los de Cecopublisher/WooCommerce con
     * los del feed: las referencias numéricas se rellenan con ceros hasta 5
     * dígitos ("121", "0121" y "00121" son el mismo producto).
     */
    public static function normalize_sku( $sku ) {
        $sku = trim( (string) $sku, " \t\n\r\0\x0B\"'" );
        if ( $sku !== '' && ctype_digit( $sku ) && strlen( $sku ) < 5 ) {
            $sku = str_pad( $sku, 5, '0', STR_PAD_LEFT );
        }
        return strtoupper( $sku );
    }

    /**
     * Descarga el feed y devuelve [ SKU normalizado => ['stock' => int, 'ean' => string, 'sku' => string] ].
     * Devuelve WP_Error si no se puede descargar o interpretar.
     */
    public static function get_stock_map( $zone = null, $force = false ) {
        $url = self::feed_url( $zone );

        if ( ! $force ) {
            $cached = get_transient( self::CACHE_KEY );
            if ( is_array( $cached ) && isset( $cached['url'], $cached['map'] ) && $cached['url'] === $url ) {
                return $cached['map'];
            }
        }

        $response = wp_remote_get( $url, [ 'timeout' => 120 ] );
        if ( is_wp_error( $response ) ) {
            error_log( '❌ Stock Cecotec: error al descargar ' . $url . ': ' . $response->get_error_message() );
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        if ( $code !== 200 || trim( $body ) === '' ) {
            error_log( "❌ Stock Cecotec: respuesta HTTP $code vacía o incorrecta desde $url" );
            return new WP_Error( 'ceco_stock_http', "El feed de stock respondió con HTTP $code o vacío ($url)." );
        }

        $map = self::parse_csv( $body );
        if ( is_wp_error( $map ) ) {
            return $map;
        }
        if ( empty( $map ) ) {
            // Las zonas Z1/Z2 han llegado a estar vacías: no se sigue para no dejar todo sin stock.
            return new WP_Error( 'ceco_stock_empty', "El feed de stock no contiene productos ($url)." );
        }

        set_transient( self::CACHE_KEY, [ 'url' => $url, 'map' => $map ], self::CACHE_TTL );
        return $map;
    }

    /**
     * Interpreta el CSV del feed localizando las columnas por su nombre.
     */
    private static function parse_csv( $body ) {
        $handle = fopen( 'php://temp', 'r+' );
        fwrite( $handle, $body );
        rewind( $handle );

        $header = fgetcsv( $handle, 0, ';', '"', '\\' );
        if ( ! $header ) {
            fclose( $handle );
            return new WP_Error( 'ceco_stock_format', 'El feed de stock no tiene cabecera.' );
        }
        $header = array_map( function ( $h ) {
            // Quita BOM y espacios.
            return strtolower( trim( preg_replace( '/^\xEF\xBB\xBF/', '', (string) $h ) ) );
        }, $header );

        $col_id    = array_search( 'public_id', $header, true );
        $col_stock = array_search( 'stock', $header, true );
        $col_ean   = array_search( 'barcodes_ean_13', $header, true );
        if ( $col_ean === false ) {
            $col_ean = array_search( 'barcode_ean_13', $header, true );
        }

        if ( $col_id === false || $col_stock === false ) {
            fclose( $handle );
            error_log( '❌ Stock Cecotec: columnas no encontradas. Cabecera: ' . implode( ';', $header ) );
            return new WP_Error( 'ceco_stock_format', 'El feed de stock no tiene las columnas public_id y stock.' );
        }

        $map = [];
        while ( ( $row = fgetcsv( $handle, 0, ';', '"', '\\' ) ) !== false ) {
            if ( ! isset( $row[ $col_id ], $row[ $col_stock ] ) ) {
                continue;
            }
            $sku = trim( $row[ $col_id ] );
            if ( $sku === '' ) {
                continue;
            }
            $stock = (int) preg_replace( '/[^0-9\-]/', '', $row[ $col_stock ] );
            $map[ self::normalize_sku( $sku ) ] = [
                'sku'   => $sku,
                'stock' => max( 0, $stock ),
                'ean'   => ( $col_ean !== false && isset( $row[ $col_ean ] ) ) ? trim( $row[ $col_ean ] ) : '',
            ];
        }
        fclose( $handle );

        return $map;
    }

    /**
     * Stock de un SKU según el mapa. Los productos que no aparecen en el feed
     * no están disponibles para la zona, así que se consideran con stock 0.
     */
    public static function stock_for( $map, $sku ) {
        $key = self::normalize_sku( $sku );
        return isset( $map[ $key ] ) ? (int) $map[ $key ]['stock'] : 0;
    }

    public static function ean_for( $map, $sku ) {
        $key = self::normalize_sku( $sku );
        return isset( $map[ $key ] ) ? $map[ $key ]['ean'] : '';
    }

    /**
     * Actualiza el stock de los productos de WooCommerce cuyo SKU aparece en el feed.
     * Activa la gestión de inventario y desactiva las reservas, de forma que los
     * productos sin stock quedan "Agotado" y no se pueden comprar.
     *
     * Los productos que no están en el feed (otras marcas, productos propios...) no se tocan.
     */
    public static function sync_woocommerce_stock( $force = true ) {
        if ( ! function_exists( 'wc_get_product' ) ) {
            return new WP_Error( 'ceco_no_wc', 'WooCommerce no está activo.' );
        }

        if ( function_exists( 'set_time_limit' ) ) {
            @set_time_limit( 600 );
        }

        $map = self::get_stock_map( null, $force );
        if ( is_wp_error( $map ) ) {
            update_option( self::LAST_SYNC, [
                'time'  => current_time( 'mysql' ),
                'error' => $map->get_error_message(),
            ], false );
            return $map;
        }

        global $wpdb;
        // Todos los SKU de productos y variaciones en una sola consulta.
        $rows = $wpdb->get_results(
            "SELECT pm.post_id, pm.meta_value AS sku
               FROM {$wpdb->postmeta} pm
               INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
              WHERE pm.meta_key = '_sku'
                AND pm.meta_value <> ''
                AND p.post_type IN ('product', 'product_variation')
                AND p.post_status NOT IN ('trash', 'auto-draft')"
        );

        $stats = [ 'found' => 0, 'updated' => 0, 'instock' => 0, 'outofstock' => 0, 'not_in_feed' => 0 ];

        foreach ( $rows as $row ) {
            $key = self::normalize_sku( $row->sku );
            if ( ! isset( $map[ $key ] ) ) {
                $stats['not_in_feed']++;
                continue;
            }
            $stats['found']++;

            $product = wc_get_product( $row->post_id );
            if ( ! $product || $product->is_type( 'variable' ) ) {
                continue; // El stock va en las variaciones, no en el producto padre.
            }

            $stock = (int) $map[ $key ]['stock'];
            $stock > 0 ? $stats['instock']++ : $stats['outofstock']++;

            $changed = false;
            if ( ! $product->get_manage_stock() ) {
                $product->set_manage_stock( true );
                $changed = true;
            }
            if ( $product->get_backorders() !== 'no' ) {
                $product->set_backorders( 'no' );
                $changed = true;
            }
            $current = $product->get_stock_quantity();
            if ( $current === null || $current === '' || (int) $current !== $stock ) {
                $product->set_stock_quantity( $stock );
                $changed = true;
            }

            if ( $changed ) {
                // save() recalcula el estado (instock / outofstock) a partir de la cantidad.
                $product->save();
                $stats['updated']++;
            }
        }

        $stats['feed']  = count( $map );
        $stats['url']   = self::feed_url();
        $stats['time']  = current_time( 'mysql' );
        update_option( self::LAST_SYNC, $stats, false );

        error_log( '✅ Stock Cecotec sincronizado: ' . wp_json_encode( $stats ) );
        return $stats;
    }

    /**
     * Texto para mostrar el resultado de una sincronización.
     */
    public static function describe( $stats ) {
        if ( empty( $stats ) ) {
            return 'Todavía no se ha sincronizado el stock.';
        }
        if ( ! empty( $stats['error'] ) ) {
            return sprintf( '❌ %s — %s', $stats['time'], $stats['error'] );
        }
        return sprintf(
            '✅ %s — %d productos en el feed · %d encontrados en la tienda (%d con stock, %d agotados) · %d actualizados · %d productos de la tienda no están en el feed (no se han tocado).',
            $stats['time'],
            $stats['feed'],
            $stats['found'],
            $stats['instock'],
            $stats['outofstock'],
            $stats['updated'],
            $stats['not_in_feed']
        );
    }
}

/*******************************************************************************
 *  Cron horario de stock
 ******************************************************************************/

add_action( 'ceco_sync_stock_event', [ 'CecoStockFeed', 'sync_woocommerce_stock' ] );

function ceco_register_stock_cron_event() {
    if ( ! wp_next_scheduled( 'ceco_sync_stock_event' ) ) {
        wp_schedule_event( time() + 300, 'hourly', 'ceco_sync_stock_event' );
    }
}
add_action( 'wp', 'ceco_register_stock_cron_event' );
add_action( 'admin_init', 'ceco_register_stock_cron_event' );

/*******************************************************************************
 *  AJAX: sincronizar stock manualmente
 ******************************************************************************/

function ceco_ajax_sync_stock() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Sin permisos.' ] );
    }
    check_ajax_referer( 'ceco_sync_stock', 'nonce' );

    $result = CecoStockFeed::sync_woocommerce_stock( true );
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( [ 'message' => $result->get_error_message() ] );
    }
    wp_send_json_success( [ 'message' => CecoStockFeed::describe( $result ) ] );
}
add_action( 'wp_ajax_ceco_sync_stock', 'ceco_ajax_sync_stock' );

/*******************************************************************************
 *  Guardar URL personalizada del feed
 ******************************************************************************/

function ceco_handle_save_stock_settings() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Sin permisos.' );
    }
    check_admin_referer( 'ceco_save_stock_settings' );

    $url = isset( $_POST['ceco_stock_feed_url'] ) ? esc_url_raw( trim( wp_unslash( $_POST['ceco_stock_feed_url'] ) ) ) : '';
    update_option( CecoStockFeed::URL_OPTION, $url );
    delete_transient( CecoStockFeed::CACHE_KEY );

    wp_redirect( admin_url( 'admin.php?page=import-cecopublisher' ) );
    exit;
}
add_action( 'admin_post_save_stock_settings', 'ceco_handle_save_stock_settings' );
