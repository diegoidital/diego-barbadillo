<?php
/**
 * Catálogo de Cecotec en Plytix (PIM).
 *
 * Cecotec publica el catálogo completo (fichas de producto: nombre, descripciones,
 * familias, imágenes, medidas...) en un feed XML de Plytix. No incluye ni precio
 * ni stock: esos datos salen de los feeds de dropshipping (ver ceco-stock.php).
 *
 * El XML pesa ~280 MB, así que:
 *  - se descarga a disco (wp-content/uploads/woocotec/catalogo.xml) como mucho una vez al día,
 *  - se lee en streaming con XMLReader, extrayendo solo los productos y campos que se necesitan.
 *
 * Cruce de datos (indicado por Cecotec): campo "SKU" (tag_SKU) del catálogo = "public_id" del feed.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CecoCatalogFeed {

    // Integración Avanzada (XML) - Formato Premium.
    const DEFAULT_URL = 'https://pim.plytix.com/channels/695f7b24f8956fc226436cea/feed';

    const URL_OPTION      = 'ceco_catalog_feed_url';
    const GALLERY_OPTION  = 'ceco_catalog_gallery_mode'; // 'master' | 'master_gallery'
    const LAST_DOWNLOAD   = 'ceco_catalog_last_download';
    const MAX_AGE         = 20 * HOUR_IN_SECONDS;        // se vuelve a descargar si el fichero tiene más de 20 h

    // Imágenes de relleno que Plytix pone cuando no hay foto.
    const PLACEHOLDER_IMAGES = [ 'Cecotec_Logo' ];

    // Valores que en el catálogo significan "sin dato".
    const EMPTY_VALUES = [ '', '0', 'N/A', 'n/a', 'NA', '-' ];

    // Estados del producto en los que Cecotec no repone stock.
    const DISCONTINUED = [ 'DESCATALOGADO', 'DESCARTADO', 'DISCONTINUO' ];

    /**
     * Zona seleccionada => sufijo de idioma de los campos de Plytix ('' = español).
     */
    public static function zone_lang( $zone ) {
        $map = [
            'Español'   => '',
            'Portugues' => 'PT',
            'Italiano'  => 'IT',
            'Francés'   => 'FR',
            'Alemán'    => 'DE',
            'Holandes'  => 'NL',
            'Zona 1'    => 'EN', // Las zonas se importan en inglés (igual que antes).
            'Zona 2'    => 'EN',
            'Zona 3'    => 'EN',
        ];
        return $map[ $zone ] ?? '';
    }

    public static function feed_url() {
        $custom = trim( (string) get_option( self::URL_OPTION, '' ) );
        return $custom !== '' ? $custom : self::DEFAULT_URL;
    }

    public static function file_path() {
        $upload = wp_upload_dir();
        return trailingslashit( $upload['basedir'] ) . 'woocotec/catalogo.xml';
    }

    public static function is_fresh() {
        $file = self::file_path();
        return file_exists( $file ) && ( time() - filemtime( $file ) ) < self::MAX_AGE && filesize( $file ) > 0;
    }

    /**
     * Descarga el XML de Plytix a disco. Se descarga a un fichero temporal y solo
     * se sustituye el anterior si la descarga es correcta.
     *
     * @return true|WP_Error
     */
    public static function download( $force = false ) {
        if ( ! $force && self::is_fresh() ) {
            return true;
        }

        if ( function_exists( 'set_time_limit' ) ) {
            @set_time_limit( 1200 );
        }
        ignore_user_abort( true );

        $file = self::file_path();
        $dir  = dirname( $file );
        if ( ! wp_mkdir_p( $dir ) ) {
            return self::fail( "No se puede crear la carpeta $dir." );
        }
        // Que no se pueda descargar el catálogo desde fuera.
        if ( ! file_exists( $dir . '/index.php' ) ) {
            @file_put_contents( $dir . '/index.php', '<?php // Silence is golden' );
        }
        if ( ! file_exists( $dir . '/.htaccess' ) ) {
            @file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" );
        }

        $tmp      = $file . '.part';
        $url      = self::feed_url();
        $started  = time();
        $response = wp_remote_get( $url, [
            'timeout'  => 900,
            'stream'   => true,
            'filename' => $tmp,
        ] );

        if ( is_wp_error( $response ) ) {
            @unlink( $tmp );
            return self::fail( 'Error al descargar el catálogo: ' . $response->get_error_message() );
        }
        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            @unlink( $tmp );
            return self::fail( "El catálogo respondió con HTTP $code." );
        }

        // Comprobación básica: tiene que ser el XML de productos.
        $head = (string) @file_get_contents( $tmp, false, null, 0, 512 );
        if ( strpos( $head, '<products' ) === false ) {
            @unlink( $tmp );
            return self::fail( 'El fichero descargado no es el XML de catálogo de Plytix (¿URL del feed CSV/Excel en lugar del XML?).' );
        }

        if ( ! @rename( $tmp, $file ) ) {
            @unlink( $tmp );
            return self::fail( "No se pudo guardar el catálogo en $file." );
        }

        update_option( self::LAST_DOWNLOAD, [
            'time'    => current_time( 'mysql' ),
            'size'    => filesize( $file ),
            'seconds' => time() - $started,
            'url'     => $url,
        ], false );

        return true;
    }

    private static function fail( $message ) {
        error_log( '❌ Catálogo Cecotec: ' . $message );
        update_option( self::LAST_DOWNLOAD, [
            'time'  => current_time( 'mysql' ),
            'error' => $message,
        ], false );
        return new WP_Error( 'ceco_catalog', $message );
    }

    /**
     * Lee el XML y devuelve los datos de los productos pedidos, ya en el idioma de la zona.
     *
     * @param array  $wanted Mapa SKU normalizado => true (si está vacío se leen todos).
     * @param string $lang   Sufijo de idioma ('' español, 'IT', 'EN'...).
     * @return array|WP_Error SKU normalizado => datos del producto
     */
    public static function get_products( $wanted, $lang ) {
        $file = self::file_path();
        if ( ! file_exists( $file ) ) {
            return new WP_Error( 'ceco_catalog', 'No hay catálogo descargado.' );
        }
        if ( ! class_exists( 'XMLReader' ) ) {
            return new WP_Error( 'ceco_catalog', 'La extensión XMLReader de PHP no está disponible en el servidor.' );
        }
        if ( function_exists( 'set_time_limit' ) ) {
            @set_time_limit( 900 );
        }

        // Algunas etiquetas de Plytix tienen ":" (p. ej. Foto_Image_Gallery_16:9_JPG_01) y
        // libxml las avisa como errores de espacio de nombres: no son fatales, se ignoran.
        $previous = libxml_use_internal_errors( true );

        $reader = new XMLReader();
        if ( ! $reader->open( $file, 'UTF-8', LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE ) ) {
            libxml_use_internal_errors( $previous );
            return new WP_Error( 'ceco_catalog', 'No se pudo abrir el catálogo descargado.' );
        }

        $products = [];

        // Avanza hasta el primer <product>.
        while ( $reader->read() && ! ( $reader->nodeType === XMLReader::ELEMENT && $reader->name === 'product' ) ) {}

        while ( $reader->nodeType === XMLReader::ELEMENT && $reader->name === 'product' ) {
            $fields = self::read_product( $reader, $wanted );
            if ( $fields !== null ) {
                $key = CecoStockFeed::normalize_sku( $fields['tag_SKU'] );
                $products[ $key ] = self::map_product( $fields, $lang );
            }
            // Siguiente <product> (salta lo que quede del actual).
            if ( ! $reader->next( 'product' ) ) {
                break;
            }
        }

        $reader->close();
        libxml_clear_errors();
        libxml_use_internal_errors( $previous );

        return $products;
    }

    /**
     * Lee los campos de un <product>. Devuelve null si el SKU no interesa
     * (tag_SKU es el primer campo, así que se descarta sin leer el resto).
     */
    private static function read_product( XMLReader $reader, $wanted ) {
        if ( $reader->isEmptyElement ) {
            return null;
        }

        $fields = [];
        $depth  = $reader->depth;
        $ok     = $reader->read(); // primer nodo dentro de <product>

        while ( $ok ) {
            if ( $reader->nodeType === XMLReader::END_ELEMENT && $reader->depth === $depth ) {
                break; // </product>
            }
            if ( $reader->nodeType === XMLReader::ELEMENT && $reader->depth === $depth + 1 ) {
                $name  = $reader->name;
                $value = $reader->isEmptyElement ? '' : trim( $reader->readString() );
                $fields[ $name ] = $value;

                // tag_SKU es el primer campo: si el producto no interesa no se lee el resto.
                if ( $name === 'tag_SKU' && ! empty( $wanted ) && ! isset( $wanted[ CecoStockFeed::normalize_sku( $value ) ] ) ) {
                    // Se deja el cursor en </product> para que next('product') funcione.
                    while ( $reader->next() ) {
                        if ( $reader->nodeType === XMLReader::END_ELEMENT && $reader->depth === $depth ) {
                            break;
                        }
                    }
                    return null;
                }
                $ok = $reader->next(); // siguiente hermano, sin recorrer el contenido del campo
                continue;
            }
            $ok = $reader->read();
        }

        return isset( $fields['tag_SKU'] ) ? $fields : null;
    }

    /**
     * Valor de un campo en el idioma pedido. Plytix no es uniforme con las mayúsculas
     * (Familia_Padre__IT_ / Familia_padre__HU_), por eso se busca sin distinguirlas.
     */
    private static function field( $lower, $base, $lang = '', $fallback_langs = [] ) {
        foreach ( array_merge( [ $lang ], $fallback_langs ) as $l ) {
            $key = strtolower( $l === '' ? $base : $base . '__' . $l . '_' );
            if ( isset( $lower[ $key ] ) && ! in_array( trim( $lower[ $key ] ), self::EMPTY_VALUES, true ) ) {
                return trim( $lower[ $key ] );
            }
        }
        return '';
    }

    private static function images( $fields, $prefix ) {
        $urls = [];
        foreach ( $fields as $name => $value ) {
            if ( strpos( $name, $prefix ) !== 0 || $value === '' ) {
                continue;
            }
            foreach ( self::PLACEHOLDER_IMAGES as $placeholder ) {
                if ( stripos( $value, $placeholder ) !== false ) {
                    continue 2;
                }
            }
            // Algunas URLs de Plytix llevan espacios ("..._1 copia.jpg"): WP All Import no las descarga bien.
            $urls[] = str_replace( ' ', '%20', $value );
        }
        return array_values( array_unique( $urls ) );
    }

    /**
     * Texto con saltos de línea => HTML sencillo (párrafos).
     */
    private static function to_html( $text ) {
        $text = trim( str_replace( [ "\r\n", "\r" ], "\n", $text ) );
        if ( $text === '' ) {
            return '';
        }
        if ( strpos( $text, '<' ) !== false && preg_match( '/<(p|ul|li|br|strong|b)\b/i', $text ) ) {
            return $text; // Ya viene en HTML.
        }
        $lines = array_filter( array_map( 'trim', explode( "\n", $text ) ), 'strlen' );
        return '<p>' . implode( "</p>\n<p>", array_map( 'esc_html', $lines ) ) . '</p>';
    }

    /**
     * Convierte los campos de Plytix en los datos que usa el CSV de importación.
     *
     * Criterios (respuestas de Cecotec):
     *  - Nombre:            "Nombre Producto / Modelo".
     *  - Descripción corta: "Descripción corta del producto".
     *  - Descripción larga: "Descripción larga del producto" + "Características" (texto completo).
     *    Los bullets y los datos de caja / caja máster / palet son para marketplaces: no se usan.
     *  - Categorías:        Familia padre (gama) > Familia > Subfamilia.
     *  - Imágenes:          fotos "Máster Main Image" (fondo blanco); opcionalmente se completa
     *                       la galería con las fotos "Gallery 1:1".
     */
    private static function map_product( $raw, $lang ) {
        $fallback = ( $lang === '' ) ? [] : [ 'EN' ];
        $f        = array_change_key_case( $raw, CASE_LOWER ); // para field()

        $name = self::field( $f, 'Nombre_Producto___Modelo', $lang, $fallback );
        if ( $name === '' ) {
            $name = self::field( $f, 'Nombre_Producto___Modelo' ); // último recurso: español
        }

        $long  = self::field( $f, 'Descripci_n_larga_del_producto', $lang, $fallback );
        $chars = self::field( $f, 'Caracter_sticas', $lang, $fallback );
        $long_html = trim( self::to_html( $long ) . "\n" . self::to_html( $chars ) );

        $main    = self::images( $raw, 'Foto_M_ster_Main_Image' );
        $gallery = self::images( $raw, 'Foto_Image_Gallery_1:1' );
        $images  = $main;
        if ( get_option( self::GALLERY_OPTION, 'master_gallery' ) === 'master_gallery' || empty( $images ) ) {
            $images = array_values( array_unique( array_merge( $images, $gallery ) ) );
        }

        return [
            'sku'          => $raw['tag_SKU'],
            'name'         => $name,
            'short'        => self::field( $f, 'Descripci_n_corta_del_producto', $lang, $fallback ),
            'long'         => $long_html,
            'cat1'         => self::field( $f, 'Familia_padre', $lang, $fallback ),
            'cat2'         => self::field( $f, 'Familia', $lang, $fallback ),
            'cat3'         => self::field( $f, 'Subfamilia', $lang, $fallback ),
            'weight'       => self::field( $f, 'Product_weight__Kg_' ),
            'length'       => self::field( $f, 'Product_depth__cm_' ),
            'width'        => self::field( $f, 'Product_width__cm_' ),
            'height'       => self::field( $f, 'Product_height__cm_' ),
            'voltage'      => self::field( $f, 'Fuente_de_alimentaci_n' ),
            'battery'      => self::field( $f, 'Voltaje_y_capacidad_de_la_bater_a' ),
            'images'       => $images,
            'ean'          => self::field( $f, 'EAN_13' ),
            'status'       => strtoupper( self::field( $f, 'Estado_del_producto' ) ),
            'brand'        => self::field( $f, 'Propietario' ),
            'color'        => self::field( $f, 'Color', $lang ),
            'manual'       => self::field( $f, 'Manual_de_instrucciones__Comercial_' ),
        ];
    }

    public static function describe_download( $info ) {
        if ( empty( $info ) ) {
            return 'Todavía no se ha descargado el catálogo.';
        }
        if ( ! empty( $info['error'] ) ) {
            return sprintf( '❌ %s — %s', $info['time'], $info['error'] );
        }
        return sprintf( '✅ %s — %s MB en %d s', $info['time'], number_format_i18n( $info['size'] / 1048576, 1 ), $info['seconds'] );
    }
}

/*******************************************************************************
 *  Cron diario de descarga del catálogo
 ******************************************************************************/

add_action( 'ceco_download_catalog_event', function () {
    CecoCatalogFeed::download( true );
} );

function ceco_register_catalog_cron_event() {
    if ( ! wp_next_scheduled( 'ceco_download_catalog_event' ) ) {
        // De madrugada, antes de la generación diaria del CSV.
        $next = strtotime( 'tomorrow 03:00', current_time( 'timestamp' ) ) - ( (int) get_option( 'gmt_offset', 0 ) * HOUR_IN_SECONDS );
        wp_schedule_event( $next, 'daily', 'ceco_download_catalog_event' );
    }
}
add_action( 'wp', 'ceco_register_catalog_cron_event' );
add_action( 'admin_init', 'ceco_register_catalog_cron_event' );

/*******************************************************************************
 *  AJAX: descargar catálogo (paso 1 de la importación manual)
 ******************************************************************************/

function ceco_ajax_download_catalog() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Sin permisos.' ] );
    }
    check_ajax_referer( 'ceco_sync_stock', 'nonce' );

    $force = ! empty( $_POST['force'] );
    if ( ! $force && CecoCatalogFeed::is_fresh() ) {
        wp_send_json_success( [ 'message' => 'Catálogo de Plytix al día (descargado hace menos de 20 h).' ] );
    }

    $result = CecoCatalogFeed::download( true );
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( [ 'message' => $result->get_error_message() ] );
    }
    wp_send_json_success( [ 'message' => 'Catálogo descargado: ' . CecoCatalogFeed::describe_download( get_option( CecoCatalogFeed::LAST_DOWNLOAD ) ) ] );
}
add_action( 'wp_ajax_ceco_download_catalog', 'ceco_ajax_download_catalog' );

/*******************************************************************************
 *  Guardar ajustes del catálogo
 ******************************************************************************/

function ceco_handle_save_catalog_settings() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Sin permisos.' );
    }
    check_admin_referer( 'ceco_save_catalog_settings' );

    $url = isset( $_POST['ceco_catalog_feed_url'] ) ? esc_url_raw( trim( wp_unslash( $_POST['ceco_catalog_feed_url'] ) ) ) : '';
    update_option( CecoCatalogFeed::URL_OPTION, $url );

    $mode = ( isset( $_POST['ceco_catalog_gallery_mode'] ) && $_POST['ceco_catalog_gallery_mode'] === 'master' ) ? 'master' : 'master_gallery';
    update_option( CecoCatalogFeed::GALLERY_OPTION, $mode );

    wp_redirect( admin_url( 'admin.php?page=import-cecopublisher' ) );
    exit;
}
add_action( 'admin_post_save_catalog_settings', 'ceco_handle_save_catalog_settings' );

/*******************************************************************************
 *  No incluir el XML del catálogo (~280 MB) en las copias de All-in-One WP Migration
 ******************************************************************************/

add_filter( 'ai1wm_exclude_media_from_export', function ( $exclude ) {
    $exclude   = (array) $exclude;
    $exclude[] = 'woocotec';
    return $exclude;
} );
add_filter( 'ai1wm_exclude_content_from_export', function ( $exclude ) {
    $exclude   = (array) $exclude;
    $exclude[] = 'uploads/woocotec';
    return $exclude;
} );
