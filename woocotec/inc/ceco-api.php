<?php
class CecoPublisherToWooCommerce {
    private $apiUrl = "https://csv-online.cecotec.cloud/api/v1";
    private $email;
    private $password;
    private $token;
    private $selected_zone;
   
/*******************************************************************************
 *  Constructor
 ******************************************************************************/
    
    // Constructor para inicializar los métodos y las variables
    public function __construct() {
        $this->email = get_option('ceco_email', 'info@emetronix.com');
        $this->password = get_option('ceco_password', '123456');
        $this->token = get_option('ceco_token', '');
        $this->selected_zone = get_option('ceco_selected_zone', 'ES');
        
        // Registrar los hooks de WordPress
        //add_action('admin_post_import_products', [$this, 'handle_import_products']);

        add_action('wp_ajax_import_products', [$this, 'ajax_import_products']);
       //add_action('wp_ajax_process_products_from_csv', [$this, 'import_products_in_batches']);

        add_action('admin_post_delete_missing_products', [$this, 'handle_delete_missing_products']);
        //add_action('admin_post_save_selected_zone', [$this, 'handle_save_selected_zone']);
  
    }

/*******************************************************************************
 *  Llamadas a la API de Cecotec
 ******************************************************************************/
    
    // Función para realizar solicitudes a la API
    private function request($endpoint, $data = [], $isPost = true, $retry = true) {
            $url = "{$this->apiUrl}/{$endpoint}";
            $headers = [
                "Content-Type: multipart/form-data",
                "Accept: application/json"
            ];

            if (!empty($this->token)) {
                $headers[] = "Authorization: Bearer {$this->token}";
            }

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            if ($isPost) {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            }

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);

            if ($curl_error) {
                error_log("cURL error: $curl_error");
                return null;
            }

            if ($http_code === 401 && $endpoint !== 'login') { // Token inválido o expirado
                if (!$retry) {
                    return null;
                }
                error_log("Token inválido o expirado. Generando un nuevo token...");
                if (!$this->login()) {
                    return null;
                }
                return $this->request($endpoint, $data, $isPost, false); // Reintentar una sola vez
            }

            if ($http_code !== 200) {
                error_log("HTTP error: $http_code. Response: $response");
                return null;
            }

            return json_decode($response, true);
    }
    // Función para iniciar sesión y obtener el token
    public function login() {
            // Realizar la solicitud al endpoint de login
            $response = $this->request("login", [
                "email" => $this->email,
                "password" => $this->password
            ]);

            // Verificar si la API devuelve un token
            if (isset($response['token'])) {
                $this->token = $response['token'];
                update_option('ceco_token', $this->token); // Guardar el token en la base de datos
                error_log('Nuevo token de Cecopublisher generado y guardado.');
                return true;
            }
            // Sin wp_die(): cortaría el cron y la respuesta AJAX.
            error_log('Error al obtener el token de Cecopublisher: ' . json_encode($response));
            return false;
    }
/*******************************************************************************
 *  Generación del CSV para WP All Import (datos nuevos de Cecotec)
 *
 *  - Catálogo (nombre, descripciones, familias, imágenes, medidas): Plytix XML.
 *  - España:        precio (pvpr) + stock  -> feed DROPES (getstockfeedb2cNew.php).
 *  - Resto de zonas: precio -> Cecopublisher (tarifa DROP INTER),
 *                    stock  -> feed de la zona (DROPPT/IT/DE/FR/Z1/Z2/Z3).
 *  - Cruce: SKU del catálogo = public_id del feed = SKU/REFERENCIA de Cecopublisher.
 *
 *  Las columnas del CSV son las mismas que antes (más algunas nuevas al final),
 *  así la importación "Productos Cecotec" de WP All Import sigue funcionando.
 ******************************************************************************/

    public static function csv_columns() {
        return [
            "SKU",
            "Nombre del producto",
            "PVPR",
            "Categoría principal",
            "Categorias secundaria",
            "Categoría terciaria",
            "Descripción corta",
            "Descripción larga",
            "Peso",
            "Largo",
            "Ancho",
            "Alto",
            "Voltaje",
            "Voltaje y capacidad de la batería",
            "Imagen destacada",
            "img1",
            "img2",
            "img3",
            "img4",
            // Columnas nuevas
            "Stock",
            "Estado stock",
            "EAN",
            "Estado Cecotec",
            "Marca",
            "Precio coste",
            "Galería",
        ];
    }

    /**
     * Cecotec pone el EAN delante del nombre en algunos productos (sobre todo EVVO):
     * "8435743904049-Ventilador Evaporativo EVVO AC750" => "Ventilador Evaporativo EVVO AC750".
     */
    public static function clean_name($name) {
        $clean = preg_replace('/^\s*\d{8,14}\s*-\s*/u', '', (string) $name);
        return $clean !== '' ? $clean : (string) $name;
    }

    public static function csv_path() {
        $upload_dir = wp_upload_dir();
        return $upload_dir['basedir'] . '/wpallimport/files/products.csv';
    }

    // Precios de Cecopublisher para zonas fuera de España (tarifa DROP INTER). SKU normalizado => precio
    private function get_inter_prices($selected_zone) {
        $columns = [
            'Portugues' => 'PVP PORTUGAL',
            'Italiano'  => 'PVP ITALY',
            'Francés'   => 'PVP FRANCE',
            'Alemán'    => 'PVP GERMANY',
            'Holandes'  => 'PVP ZONE 1',
            'Zona 1'    => 'PVP ZONE 1',
            'Zona 2'    => 'PVP ZONE 2',
            'Zona 3'    => 'PVP ZONE 3',
        ];
        if (!isset($columns[$selected_zone])) {
            return new WP_Error('ceco_zone', "Zona desconocida: $selected_zone");
        }

        $rates = $this->request("rates", [], true);
        if (empty($rates) || empty($rates['data'])) {
            return new WP_Error('ceco_rates', 'Cecopublisher no ha devuelto tarifas. Revisa las credenciales y que la tarifa DROP INTER esté asignada.');
        }

        $rate_id = null;
        foreach ($rates['data'] as $rate) {
            if (strpos($rate['name'], 'INTER') !== false) {
                $rate_id = $rate['id'];
                break;
            }
        }
        if ($rate_id === null) {
            return new WP_Error('ceco_rates', 'No se ha encontrado la tarifa DROP INTER en Cecopublisher.');
        }

        $rate = $this->request("rate/{$rate_id}", [], true);
        if (empty($rate) || empty($rate['data'])) {
            return new WP_Error('ceco_rates', 'La tarifa DROP INTER de Cecopublisher está vacía.');
        }

        $prices = [];
        foreach ($rate['data'] as $product) {
            $sku = $product['SKU'] ?? ($product['REFERENCIA'] ?? '');
            $pvp = $product[$columns[$selected_zone]] ?? null;
            if ($sku === '' || $pvp === null) {
                continue;
            }
            $pvp = CecoStockFeed::parse_decimal((string) $pvp);
            if ($pvp !== '' && (float) $pvp > 0) {
                $prices[CecoStockFeed::normalize_sku($sku)] = $pvp;
            }
        }
        return $prices;
    }

    /**
     * Identificador con el que cada producto ya existe, para que WP All Import lo actualice
     * en lugar de crear uno nuevo (p. ej. "099" o "00099" frente al "00099" del feed).
     *
     * Se usa el identificador único que WP All Import guardó en importaciones anteriores de
     * products.csv (tabla pmxi_posts), que es con lo que WP All Import empareja.
     *
     * No se usa el SKU de WooCommerce: productos antiguos de otros plugins tienen SKU sin ceros
     * ("99") y, si no los controla esta importación, WP All Import intentaría crear otro producto
     * con ese mismo SKU (duplicado). Para los no controlados se usa el SKU del feed ("00099").
     */
    private function existing_skus() {
        global $wpdb;
        $skus = [];

        // Identificadores únicos de WP All Import.
        $posts_table   = $wpdb->prefix . 'pmxi_posts';
        $imports_table = $wpdb->prefix . 'pmxi_imports';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $posts_table)) === $posts_table) {
            $import_ids = [];
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $imports_table)) === $imports_table) {
                $import_ids = $wpdb->get_col("SELECT id FROM {$imports_table} WHERE path LIKE '%products.csv%'");
            }
            if (!empty($import_ids)) {
                $in   = implode(',', array_map('intval', $import_ids));
                $keys = $wpdb->get_col("SELECT px.unique_key FROM {$posts_table} px
                                        INNER JOIN {$wpdb->posts} p ON p.ID = px.post_id
                                        WHERE px.import_id IN ($in) AND px.unique_key <> '' AND p.post_status <> 'trash'
                                        ORDER BY px.id ASC");
                foreach ((array) $keys as $key) {
                    $skus[CecoStockFeed::normalize_sku($key)] = $key;
                }
            }
        }

        return $skus;
    }

    /**
     * Cecotec mantiene algunos productos con dos códigos (p. ej. "00099" y "A01_EU01_113689",
     * ambos "Piccolina 200 Beige", con EAN distintos). El código antiguo suele estar sin stock.
     * Para no crear fichas repetidas, de cada grupo de productos con el mismo nombre:
     *  - se quedan todos los que tienen stock;
     *  - los que no tienen stock se descartan si en el grupo hay otro con stock;
     *  - si ninguno tiene stock, se queda uno solo: el que ya controla WP All Import
     *    (para no crear nada nuevo) o, si no, el de código nuevo (no numérico).
     *
     * @return array SKU normalizado => true para los que se omiten.
     */
    private function find_duplicate_codes($prices, $stock_map, $catalog, $existing) {
        $groups = [];
        foreach ($prices as $key => $pvpr) {
            $name = $catalog[$key]['name'] ?? ($stock_map[$key]['titulo'] ?? ($stock_map[$key]['title'] ?? ''));
            $name = function_exists('mb_strtolower') ? mb_strtolower(trim($name)) : strtolower(trim($name));
            if ($name === '') {
                continue;
            }
            $groups[$name][] = $key;
        }

        $skip = [];
        foreach ($groups as $keys) {
            if (count($keys) < 2) {
                continue;
            }
            $with_stock = array_filter($keys, function ($k) use ($stock_map) {
                return (int) ($stock_map[$k]['stock'] ?? 0) > 0;
            });

            if (!empty($with_stock)) {
                foreach ($keys as $k) {
                    if (!in_array($k, $with_stock, true)) {
                        $skip[$k] = true;
                    }
                }
                continue;
            }

            // Ninguno con stock: se conserva uno. Preferencia:
            // 1) ya en WP All Import y código nuevo, 2) ya en WP All Import, 3) código nuevo, 4) el primero.
            $is_new_code = function ($k) {
                return !ctype_digit(str_replace('-', '', $k));
            };
            $keep = null;
            foreach ([
                function ($k) use ($existing, $is_new_code) { return isset($existing[$k]) && $is_new_code($k); },
                function ($k) use ($existing) { return isset($existing[$k]); },
                $is_new_code,
            ] as $rule) {
                foreach ($keys as $k) {
                    if ($rule($k)) { $keep = $k; break 2; }
                }
            }
            if ($keep === null) {
                $keep = $keys[0];
            }
            foreach ($keys as $k) {
                if ($k !== $keep) {
                    $skip[$k] = true;
                }
            }
        }
        return $skip;
    }

    /**
     * Genera el CSV. Devuelve un resumen o WP_Error.
     */
    public function build_csv() {
        if (function_exists('set_time_limit')) {
            @set_time_limit(1200);
        }
        $selected_zone = get_option('ceco_selected_zone', 'Español');
        $lang          = CecoCatalogFeed::zone_lang($selected_zone);

        // 1. Stock (y en España también precios) desde el feed de dropshipping.
        $stock_map = CecoStockFeed::get_stock_map($selected_zone, true);
        if (is_wp_error($stock_map)) {
            return new WP_Error('ceco_stock', 'No se pudo leer el feed de stock/precios: ' . $stock_map->get_error_message());
        }

        // 2. Precios.
        if ($selected_zone === 'Español') {
            $prices = [];
            foreach ($stock_map as $key => $item) {
                if (!empty($item['pvpr']) && (float) $item['pvpr'] > 0) {
                    $prices[$key] = $item['pvpr'];
                }
            }
            $price_source = 'feed DROPES (pvpr)';
        } else {
            $prices = $this->get_inter_prices($selected_zone);
            if (is_wp_error($prices)) {
                return $prices;
            }
            $price_source = 'Cecopublisher (DROP INTER)';
        }
        if (empty($prices)) {
            return new WP_Error('ceco_prices', 'No se han encontrado precios para la zona ' . $selected_zone . '.');
        }

        // 3. Catálogo de Plytix (solo los productos con precio).
        $download = CecoCatalogFeed::download(false);
        if (is_wp_error($download)) {
            return $download;
        }
        $catalog = CecoCatalogFeed::get_products(array_fill_keys(array_keys($prices), true), $lang);
        if (is_wp_error($catalog)) {
            return $catalog;
        }

        // 4. Escribir el CSV.
        $route_file = self::csv_path();
        wp_mkdir_p(dirname($route_file));
        $tmp_file = $route_file . '.tmp';
        $file = fopen($tmp_file, 'w');
        if ($file === false) {
            return new WP_Error('ceco_csv', "No se pudo crear el CSV en $route_file.");
        }

        $columns  = self::csv_columns();
        $existing = $this->existing_skus();
        fputcsv($file, $columns);

        $stats = ['rows' => 0, 'instock' => 0, 'no_catalog' => 0, 'skipped_discontinued' => 0, 'fallback_feed' => 0, 'skipped_duplicates' => 0, 'skipped_no_photo' => 0];
        $skip_no_photo = get_option('ceco_skip_no_photo', '0') === '1';

        $duplicates = $this->find_duplicate_codes($prices, $stock_map, $catalog, $existing);

        $cache_images     = CecoImageCache::enabled();
        $only_new_images  = CecoImageCache::only_new();
        $pending_images   = [];
        $stats['images_cached'] = 0;

        foreach ($prices as $key => $pvpr) {
            if (isset($duplicates[$key])) {
                $stats['skipped_duplicates']++;
                continue;
            }
            $feed    = $stock_map[$key] ?? null;
            $product = $catalog[$key] ?? null;
            $stock   = $feed ? (int) $feed['stock'] : 0;
            $status  = $product['status'] ?? '';

            // Descatalogados/discontinuos: se venden hasta agotar stock y no se reponen.
            // Sin stock no se crean ni se actualizan (la sincronización horaria los deja agotados).
            if ($stock <= 0 && in_array($status, CecoCatalogFeed::DISCONTINUED, true)) {
                $stats['skipped_discontinued']++;
                continue;
            }

            if (!$product) {
                // No está en Plytix: se usan los datos básicos del feed de España si los hay.
                if (!$feed || empty($feed['titulo'] ?? $feed['title'] ?? '')) {
                    $stats['no_catalog']++;
                    continue;
                }
                $stats['fallback_feed']++;
                $product = [
                    'name' => $feed['titulo'] ?? $feed['title'], 'short' => '', 'long' => esc_html($feed['titulo extendido'] ?? ''),
                    'cat1' => '', 'cat2' => '', 'cat3' => '', 'weight' => '', 'length' => '', 'width' => '', 'height' => '',
                    'voltage' => '', 'battery' => '', 'ean' => $feed['ean'], 'status' => '', 'brand' => '',
                    'images' => array_values(array_filter([$feed['imagen'] ?? '', $feed['imagen2'] ?? '', $feed['imagen3'] ?? ''])),
                ];
            }

            $sku    = $existing[$key] ?? ($feed['sku'] ?? $product['sku'] ?? $key);
            // Imágenes. Por defecto, las del feed de Cecotec (media.cecotec.cloud, 2048 px, ~0,5 MB):
            // las de Plytix son originales de 4.600-5.100 px y 7-10 MB, y WordPress tarda ~35 s por foto
            // en generar las miniaturas. Plytix se usa si el feed no trae fotos o si se elige en ajustes.
            $feed_images = $feed ? array_values(array_filter([$feed['imagen'] ?? '', $feed['imagen2'] ?? '', $feed['imagen3'] ?? ''])) : [];
            $feed_images = str_replace([' ', ','], ['%20', '%2C'], $feed_images);
            if (CecoCatalogFeed::image_source() === 'feed' && !empty($feed_images)) {
                $images = $feed_images;
            } else {
                $images = !empty($product['images']) ? $product['images'] : $feed_images;
            }
            $images = array_slice(array_values($images), 0, CecoImageCache::per_product());

            // Productos sin ninguna foto (ni en el feed ni en Plytix): opcionalmente no se publican.
            if ($skip_no_photo && empty($images)) {
                $stats['skipped_no_photo']++;
                continue;
            }

            // Fotos preparadas (reducidas) en el propio servidor. Solo hacen falta para los productos
            // que WP All Import va a crear: los existentes no actualizan imágenes.
            if ($cache_images && (!$only_new_images || !isset($existing[$key]))) {
                foreach ($images as $i => $url) {
                    $local = CecoImageCache::cached_url($url);
                    if ($local) {
                        $images[$i] = $local;
                        $stats['images_cached']++;
                    } else {
                        $pending_images[] = $url;
                    }
                }
            }

            $row = [
                $sku,
                self::clean_name($product['name']),
                $pvpr,
                $product['cat1'],
                $product['cat2'],
                $product['cat3'],
                $product['short'],
                $product['long'],
                $product['weight'],
                $product['length'],
                $product['width'],
                $product['height'],
                $product['voltage'],
                $product['battery'],
                $images[0] ?? '',
                $images[1] ?? '',
                $images[2] ?? '',
                $images[3] ?? '',
                $images[4] ?? '',
                $stock,
                $stock > 0 ? 'instock' : 'outofstock',
                $product['ean'] !== '' ? $product['ean'] : ($feed['ean'] ?? ''),
                $status,
                $product['brand'],
                $feed['precio'] ?? '',
                implode('|', array_slice($images, 1)), // todas las imágenes de galería, separadas por |
            ];
            fputcsv($file, $row);

            $stats['rows']++;
            if ($stock > 0) {
                $stats['instock']++;
            }
        }
        fclose($file);

        if ($stats['rows'] === 0) {
            @unlink($tmp_file);
            return new WP_Error('ceco_csv', 'No se ha generado ninguna fila: no se sobrescribe el CSV anterior.');
        }
        rename($tmp_file, $route_file);

        if ($cache_images) {
            CecoImageCache::queue_set($pending_images);
        }
        $stats['images_pending'] = count(array_unique($pending_images));

        $stats['zone']         = $selected_zone;
        $stats['price_source'] = $price_source;
        $stats['stock_url']    = CecoStockFeed::feed_url($selected_zone);
        $stats['file']         = $route_file;
        $stats['time']         = current_time('mysql');
        update_option('ceco_last_csv', $stats, false);
        error_log('✅ CSV Cecotec generado: ' . wp_json_encode($stats));

        return $stats;
    }

    public static function describe_csv($stats) {
        if (empty($stats)) {
            return 'Todavía no se ha generado el CSV con los datos nuevos.';
        }
        return sprintf(
            '✅ %s — Zona %s · %d productos en el CSV (%d con stock) · precios: %s · %d descatalogados sin stock omitidos · %d códigos duplicados sin stock omitidos · %d sin ficha en el catálogo omitidos.',
            $stats['time'], $stats['zone'], $stats['rows'], $stats['instock'], $stats['price_source'],
            $stats['skipped_discontinued'], $stats['skipped_duplicates'] ?? 0, $stats['no_catalog']
        ) . (!empty($stats['skipped_no_photo']) ? sprintf(' · %d sin foto omitidos.', $stats['skipped_no_photo']) : '') . (isset($stats['images_pending']) ? sprintf(' · Fotos: %d preparadas, %d pendientes de preparar.', $stats['images_cached'] ?? 0, $stats['images_pending']) : '');
    }

    // Botón "Importación manual" (AJAX).
    public function ajax_import_products() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Sin permisos.']);
        }
        $result = $this->build_csv();
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        wp_send_json_success([
            'message'  => self::describe_csv($result),
            'csv_file' => $result['file'],
        ]);
    }

    // Cron diario: genera el CSV sin respuesta JSON (wp_send_json cortaría el resto de tareas del cron).
    public function cron_build_csv() {
        $result = $this->build_csv();
        if (is_wp_error($result)) {
            error_log('❌ CSV Cecotec (cron): ' . $result->get_error_message());
        }
    }

/*******************************************************************************
 *  Crear productos en woocommerce desde CSV
 ******************************************************************************/
    
    /** constructor para parsear la creción de productos en wooocommerce **/
        public function import_products_in_batches() {
            set_time_limit(600); // Aumenta el límite de tiempo a 600 segundos
            $csv_file_path = plugin_dir_path(__FILE__) . 'products.csv';
            $batch_size = 2;
            error_log("Iniciando importación en lotes desde el archivo: $csv_file_path");

            if (!file_exists($csv_file_path)) {
                error_log("❌ El archivo CSV no existe: $csv_file_path");
                return false;
            }

            // Leer el archivo CSV
            $rows = [];
            if (($handle = fopen($csv_file_path, 'r')) !== false) {
                while (($row = fgetcsv($handle, 0, ',')) !== false) {
                    $rows[] = $row;
                }
                fclose($handle);
                error_log("✅ Total de filas en el archivo CSV: " . count($rows));
            } else {
                error_log("❌ No se pudo abrir el archivo CSV.");
                return false;
            }

            // Extraer encabezados
            $headers = array_shift($rows);
            error_log("✅ Encabezados del CSV: " . implode(', ', $headers));

            // Dividir los productos en lotes
            $total_products = count($rows);
            $batches = array_chunk($rows, $batch_size);

            foreach ($batches as $batch_index => $batch) {
                error_log("🔄 Procesando lote " . ($batch_index + 1) . " de " . count($batches));

                foreach ($batch as $row) {
                    $product_data = array_combine($headers, $row);
                    try {
                        $this->create_or_update_product_from_csv($product_data);
                    } catch (Exception $e) {
                        error_log("❌ Error al procesar el producto: " . $e->getMessage());
                        continue;
                    }
                }

                // Limpiar caché y liberar memoria después de cada lote
                wp_cache_flush();
                gc_collect_cycles();

                error_log("✅ Lote " . ($batch_index + 1) . " procesado correctamente.");
            }

            error_log("✅ Todos los productos han sido procesados.");
            return true;
        }
    /** Creador de productos de woocoomerce**/
    private function create_or_update_product_from_csv($product_data) {
                if (!function_exists('wc_get_product')) {
                    error_log("❌ WooCommerce no está activo.");
                    return;
                }
            
                $sku = $product_data['SKU'];
                if (empty($sku)) {
                    error_log("⚠️ Producto ignorado: El SKU está vacío.");
                    return;
                }
            
                if (!is_numeric($product_data['PVPR'])) {
                    error_log("⚠️ Producto ignorado: El precio no es válido para SKU $sku.");
                    return;
                }
            
                if (empty($product_data['Nombre del producto'])) {
                    error_log("⚠️ Producto ignorado: El nombre del producto está vacío para SKU $sku.");
                    return;
                }
            
                error_log("🔍 Buscando producto con SKU: $sku");
            
                $existing_product_id = wc_get_product_id_by_sku($sku);
            
                if ($existing_product_id) {
                    $product = wc_get_product($existing_product_id);
                    error_log("✏️ Actualizando producto existente: $sku (ID: $existing_product_id)");
                } else {
                    $product = new WC_Product_Simple();
                    error_log("🆕 Creando nuevo producto: $sku");
                }
            
                try {
                    $product->set_name($product_data['Nombre del producto']);
                    $product->set_sku($sku);
                    $product->set_regular_price(!empty($product_data['PVPR']) ? $product_data['PVPR'] : '0');
                    if (isset($product_data['Stock']) && is_numeric($product_data['Stock'])) {
                        $product->set_manage_stock(true);
                        $product->set_backorders('no');
                        $product->set_stock_quantity((int) $product_data['Stock']);
                    }
                    $product->set_weight(!empty($product_data['Peso']) ? $product_data['Peso'] : '');
                    $product->set_length(!empty($product_data['Largo']) ? $product_data['Largo'] : '');
                    $product->set_width(!empty($product_data['Ancho']) ? $product_data['Ancho'] : '');
                    $product->set_height(!empty($product_data['Alto']) ? $product_data['Alto'] : '');
                    $product->set_short_description(!empty($product_data['Descripción corta']) ? $product_data['Descripción corta'] : '');
                    $product->set_description(!empty($product_data['Descripción larga']) ? $product_data['Descripción larga'] : '');
            
                    // Crear o asignar categorías
                    $categorias = array_filter([
                        $product_data['Categoría principal'] ?? '',
                        $product_data['Categorias secundaria'] ?? '',
                        $product_data['Categoría terciaria'] ?? ''
                    ]);
            
                    if (!empty($categorias)) {
                        $parent_id = 0; // Inicialmente no hay padre
            
                        foreach ($categorias as $categoria) {
                            if (empty($categoria)) {
                                error_log("⚠️ Categoría vacía, ignorada.");
                                continue;
                            }

                            $term = get_term_by('name', $categoria, 'product_cat');

                            if (!$term) {
                                $created_term = wp_insert_term($categoria, 'product_cat', ['parent' => $parent_id]);
                                if (!is_wp_error($created_term)) {
                                    $term_id = $created_term['term_id'];
                                    $term = get_term($term_id, 'product_cat');
                                    error_log("✅ Categoría creada: $categoria (ID: $term_id)");
                                } else {
                                    error_log("❌ Error al crear la categoría $categoria: " . $created_term->get_error_message());
                                    continue;
                                }
                            } else {
                                error_log("✅ Categoría existente: $categoria (ID: " . $term->term_id . ")");
                            }

                            if ($categoria === reset($categorias)) {
                                $parent_id = $term->term_id;
                            }
                        }

                        if (!empty($categorias)) {
                            wp_set_object_terms($product->get_id(), $categorias, 'product_cat');
                            error_log("✅ Categorías asignadas al producto: " . implode(', ', $categorias));
                        }
            
                        wp_set_object_terms($product->get_id(), $categorias, 'product_cat');
                    }
            
                    $brand_name = "Cecotec";
                    $brand_term = term_exists($brand_name, 'product_brand');

                    if (!$brand_term) {
                        $brand_term = wp_insert_term($brand_name, 'product_brand');
                        if (is_wp_error($brand_term)) {
                            error_log("❌ Error al crear la marca $brand_name: " . $brand_term->get_error_message());
                        } else {
                            error_log("✅ Marca creada: $brand_name");
                        }
                    } else {
                        error_log("✅ Marca existente: $brand_name");
                    }

                    if (!is_wp_error($brand_term)) {
                        wp_set_object_terms($product->get_id(), $brand_name, 'product_brand');
                        error_log("✅ Marca asignada al producto: $brand_name");
                    }
                    // Asigna la marca al producto
                    wp_set_object_terms($product->get_id(), $brand_name, 'product_brand');
            
                    $product_id = $product->save();
                    error_log("✅ Producto guardado: $sku (ID: $product_id)");
            
                    // Asignar imágenes
                    if (!empty($product_data['Imagen destacada'])) {
                        $attachment_id = $this->import_image_from_url($product_data['Imagen destacada'], $product_id);
                        if ($attachment_id) {
                            $product->set_image_id($attachment_id);
                            $product->save();
                        }
                    }
            
                    $gallery_images = array_filter([$product_data['img1'], $product_data['img2'], $product_data['img3'], $product_data['img4']]);
                    $gallery_ids = [];
                    foreach ($gallery_images as $image_url) {
                        $attachment_id = $this->import_image_from_url($image_url, $product_id);
                        if ($attachment_id) {
                            $gallery_ids[] = $attachment_id;
                        }
                    }
                    if (!empty($gallery_ids)) {
                        update_post_meta($product_id, '_product_image_gallery', implode(',', $gallery_ids));
                    }
                                  // Limpiar caché y liberar memoria
                        wp_cache_flush(); // Limpia la caché de WordPress
                        gc_collect_cycles(); // Libera memoria del recolector de basura de PHP
                    error_log("✅ Producto procesado: $sku (ID: $product_id)");
                } catch (Exception $e) {
                    error_log("❌ Error al procesar el producto con SKU $sku: " . $e->getMessage());
                }
    }
    // Función para importar imágenes desde una URL
   /* private function import_image_from_url($image_url, $post_id) {
            if (empty($image_url)) {
                error_log("⚠️ URL de la imagen vacía. No se puede importar.");
                return false;
            }

            // Descargar la imagen
            $tmp_file = download_url($image_url);
            if (is_wp_error($tmp_file)) {
                error_log("❌ Error al descargar la imagen desde $image_url: " . $tmp_file->get_error_message());
                return false;
            }

            // Preparar el archivo para subirlo
            $file = [
                'name'     => basename($image_url),
                'type'     => mime_content_type($tmp_file),
                'tmp_name' => $tmp_file,
                'error'    => 0,
                'size'     => filesize($tmp_file),
            ];

            // Subir la imagen a la biblioteca de medios
            $attachment_id = media_handle_sideload($file, $post_id);

            // Verificar si la imagen se subió correctamente
            if (is_wp_error($attachment_id)) {
                error_log("❌ Error al subir la imagen: " . $attachment_id->get_error_message());
                @unlink($tmp_file); // Eliminar el archivo temporal
                return false;
            }

            error_log("✅ Imagen importada correctamente (ID: $attachment_id).");
            return $attachment_id;
    }*/

    private function import_image_from_url($image_url, $post_id) {
        if (empty($image_url)) {
            error_log("⚠️ URL de la imagen vacía. No se puede importar.");
            return false;
        }
    
        // Descargar la imagen
        $tmp_file = download_url($image_url);
        if (is_wp_error($tmp_file)) {
            error_log("❌ Error al descargar la imagen desde $image_url: " . $tmp_file->get_error_message());
            return false;
        }
    
        // Verificar el tamaño del archivo antes de procesarlo
        $file_size = filesize($tmp_file);
        if ($file_size > 5 * 1024 * 1024) { // Limitar a 5 MB
            error_log("⚠️ Imagen demasiado grande: $image_url ($file_size bytes).");
            @unlink($tmp_file); // Eliminar el archivo temporal
            return false;
        }
    
        // Preparar el archivo para subirlo
        $file = [
            'name'     => basename($image_url),
            'type'     => mime_content_type($tmp_file),
            'tmp_name' => $tmp_file,
            'error'    => 0,
            'size'     => $file_size,
        ];
    
        // Subir la imagen a la biblioteca de medios
        $attachment_id = media_handle_sideload($file, $post_id);
    
        // Verificar si la imagen se subió correctamente
        if (is_wp_error($attachment_id)) {
            error_log("❌ Error al subir la imagen: " . $attachment_id->get_error_message());
            @unlink($tmp_file); // Eliminar el archivo temporal
            return false;
        }
    
        error_log("✅ Imagen importada correctamente (ID: $attachment_id).");
        return $attachment_id;
    }

/*******************************************************************************
 *  Borra los productos que no están en la lista de SKUs válidos
 ******************************************************************************/
    
    public function delete_missing_products($valid_skus) {
            // Recuperar las categorías excluidas desde la base de datos
            $excluded_categories = get_option('ceco_excluded_categories', []);

            if (!is_array($valid_skus)) {
                echo "⚠️ Error: Se esperaba un array de SKUs válidos.<br>";
                return;
            }

            $args = [
                'limit'   => 5,
                'status'  => 'publish',
                'return'  => 'objects',
            ];
            $products = wc_get_products($args);

            foreach ($products as $product) {
                $product_sku = $product->get_sku();

                if (!$product_sku) {
                    echo "⚠️ Producto sin SKU (ID: " . $product->get_id() . "), no se puede comprobar.<br>";
                    continue;
                }

                // Verificar si el producto pertenece a una de las categorías excluidas
                $product_categories = wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'slugs']);
                if (!empty(array_intersect($product_categories, $excluded_categories))) {
                    echo "✅ Producto excluido de la eliminación: " . $product->get_name() . " (ID: " . $product->get_id() . ") porque pertenece a una categoría excluida.<br>";
                    continue;
                }

                if (!in_array($product_sku, $valid_skus)) {
                    $product_id = $product->get_id();

                    // Eliminar el producto y sus imágenes asociadas
                    wp_delete_post($product_id, true);
                    echo "🗑️ Producto eliminado: $product_sku (ID: $product_id)<br>";
                }
            }
    }

}