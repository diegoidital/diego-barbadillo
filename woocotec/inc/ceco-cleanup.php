<?php
/**
 * Limpieza de borradores antiguos de Cecotec.
 *
 * La web tiene miles de productos en borrador creados por plugins anteriores (2023-2024) con SKU
 * en otros formatos ("123", "127", "00127"...). Cuando el CSV actual trae ese mismo producto
 * (SKU "00123"), WP All Import crea una ficha nueva; si el borrador antiguo tiene exactamente el
 * mismo SKU, WooCommerce no deja repetirlo y la importación falla en ese producto.
 *
 * Esta herramienta localiza los borradores que:
 *  - están en estado "borrador",
 *  - NO los controla la importación de products.csv de WP All Import,
 *  - y su SKU (sin contar los ceros a la izquierda) coincide con un producto del CSV actual.
 * Primero solo los lista; al confirmar, los envía a la papelera (recuperables).
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CecoDraftCleanup {

    const BATCH = 100;

    /**
     * SKU normalizado => nombre, leídos del CSV generado.
     */
    private static function csv_products() {
        $file = CecoPublisherToWooCommerce::csv_path();
        if ( ! file_exists( $file ) ) {
            return new WP_Error( 'ceco_cleanup', 'Todavía no hay CSV generado. Pulsa antes «Importación manual».' );
        }
        $handle = fopen( $file, 'r' );
        if ( ! $handle ) {
            return new WP_Error( 'ceco_cleanup', 'No se puede leer el CSV.' );
        }
        fgetcsv( $handle, 0, ',', '"', '\\' ); // cabecera
        $products = [];
        while ( ( $row = fgetcsv( $handle, 0, ',', '"', '\\' ) ) !== false ) {
            if ( ! empty( $row[0] ) ) {
                $products[ CecoStockFeed::normalize_sku( $row[0] ) ] = $row[1] ?? '';
            }
        }
        fclose( $handle );
        return $products;
    }

    /**
     * IDs de los productos que controla la importación de products.csv.
     */
    public static function tracked_ids() {
        global $wpdb;
        $posts_table   = $wpdb->prefix . 'pmxi_posts';
        $imports_table = $wpdb->prefix . 'pmxi_imports';
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $posts_table ) ) !== $posts_table
            || $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $imports_table ) ) !== $imports_table ) {
            return [];
        }
        $import_ids = $wpdb->get_col( "SELECT id FROM {$imports_table} WHERE path LIKE '%products.csv%'" );
        if ( empty( $import_ids ) ) {
            return [];
        }
        $in  = implode( ',', array_map( 'intval', $import_ids ) );
        $ids = $wpdb->get_col( "SELECT post_id FROM {$posts_table} WHERE import_id IN ($in)" );
        return array_fill_keys( array_map( 'intval', $ids ), true );
    }

    /**
     * Borradores antiguos sustituidos por un producto del CSV.
     *
     * @return array|WP_Error Lista de [id, title, sku, modified, csv_name]
     */
    public static function find() {
        global $wpdb;

        $csv = self::csv_products();
        if ( is_wp_error( $csv ) ) {
            return $csv;
        }
        $tracked = self::tracked_ids();
        if ( empty( $tracked ) ) {
            // Sin datos de WP All Import no se puede saber qué borradores son antiguos: no se toca nada.
            return new WP_Error( 'ceco_cleanup', 'No se han encontrado los productos de la importación de WP All Import (products.csv). Por seguridad no se marca ningún borrador.' );
        }

        $rows = $wpdb->get_results(
            "SELECT p.ID, p.post_title, p.post_modified, pm.meta_value AS sku
               FROM {$wpdb->posts} p
               INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_sku'
              WHERE p.post_type = 'product' AND p.post_status = 'draft' AND pm.meta_value <> ''
              ORDER BY p.ID ASC"
        );

        $found = [];
        foreach ( $rows as $row ) {
            if ( isset( $tracked[ (int) $row->ID ] ) ) {
                continue;
            }
            $key = CecoStockFeed::normalize_sku( $row->sku );
            if ( ! isset( $csv[ $key ] ) ) {
                continue;
            }
            $found[] = [
                'id'       => (int) $row->ID,
                'title'    => $row->post_title,
                'sku'      => $row->sku,
                'modified' => $row->post_modified,
                'csv_name' => $csv[ $key ],
            ];
        }
        return $found;
    }
}

/*******************************************************************************
 *  AJAX
 ******************************************************************************/

function ceco_ajax_find_old_drafts() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Sin permisos.' ] );
    }
    check_ajax_referer( 'ceco_sync_stock', 'nonce' );

    $found = CecoDraftCleanup::find();
    if ( is_wp_error( $found ) ) {
        wp_send_json_error( [ 'message' => $found->get_error_message() ] );
    }
    wp_send_json_success( [
        'count' => count( $found ),
        'items' => $found,
    ] );
}
add_action( 'wp_ajax_ceco_find_old_drafts', 'ceco_ajax_find_old_drafts' );

/**
 * Envía a la papelera un lote. Vuelve a calcular la lista en el servidor (no se fía de los IDs
 * del navegador) y solo trata los IDs pedidos que siguen cumpliendo los criterios.
 */
function ceco_ajax_trash_old_drafts() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Sin permisos.' ] );
    }
    check_ajax_referer( 'ceco_sync_stock', 'nonce' );

    $found = CecoDraftCleanup::find();
    if ( is_wp_error( $found ) ) {
        wp_send_json_error( [ 'message' => $found->get_error_message() ] );
    }

    $requested = isset( $_POST['ids'] ) ? array_map( 'intval', (array) $_POST['ids'] ) : [];
    $allowed   = array_fill_keys( array_column( $found, 'id' ), true );
    $batch     = array_slice( array_values( array_filter( $requested, function ( $id ) use ( $allowed ) {
        return isset( $allowed[ $id ] );
    } ) ), 0, CecoDraftCleanup::BATCH );

    $trashed = 0;
    foreach ( $batch as $id ) {
        if ( wp_trash_post( $id ) ) {
            $trashed++;
        }
    }
    wp_send_json_success( [ 'trashed' => $trashed, 'processed' => $batch ] );
}
add_action( 'wp_ajax_ceco_trash_old_drafts', 'ceco_ajax_trash_old_drafts' );

/*******************************************************************************
 *  Corregir nombres con el EAN delante ("8435743904049-Ventilador ...")
 ******************************************************************************/

function ceco_ean_name_rows( $limit = 0 ) {
    global $wpdb;
    $sql = "SELECT ID, post_title FROM {$wpdb->posts}
             WHERE post_type = 'product' AND post_status IN ('publish', 'draft', 'private', 'pending')
               AND post_title REGEXP '^[[:space:]]*[0-9]{8,14}[[:space:]]*-'
             ORDER BY ID ASC";
    if ( $limit > 0 ) {
        $sql .= ' LIMIT ' . (int) $limit;
    }
    return $wpdb->get_results( $sql );
}

function ceco_ajax_find_ean_names() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Sin permisos.' ] );
    }
    check_ajax_referer( 'ceco_sync_stock', 'nonce' );

    $rows  = ceco_ean_name_rows();
    $items = array_map( function ( $r ) {
        return [ 'id' => (int) $r->ID, 'title' => $r->post_title, 'new' => CecoPublisherToWooCommerce::clean_name( $r->post_title ) ];
    }, array_slice( $rows, 0, 50 ) );
    wp_send_json_success( [ 'count' => count( $rows ), 'items' => $items ] );
}
add_action( 'wp_ajax_ceco_find_ean_names', 'ceco_ajax_find_ean_names' );

function ceco_ajax_fix_ean_names() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Sin permisos.' ] );
    }
    check_ajax_referer( 'ceco_sync_stock', 'nonce' );

    $fixed = 0;
    foreach ( ceco_ean_name_rows( 100 ) as $row ) {
        $clean = CecoPublisherToWooCommerce::clean_name( $row->post_title );
        if ( $clean !== $row->post_title ) {
            // wp_update_post conserva el slug (post_name) existente.
            $result = wp_update_post( [ 'ID' => (int) $row->ID, 'post_title' => $clean ], true );
            if ( ! is_wp_error( $result ) ) {
                $fixed++;
            }
        }
    }
    wp_send_json_success( [ 'fixed' => $fixed, 'remaining' => count( ceco_ean_name_rows() ) ] );
}
add_action( 'wp_ajax_ceco_fix_ean_names', 'ceco_ajax_fix_ean_names' );
