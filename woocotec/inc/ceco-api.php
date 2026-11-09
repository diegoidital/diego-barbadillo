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
    private function request($endpoint, $data = [], $isPost = true) {
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

            if ($http_code === 401) { // Token inválido o expirado
                error_log("Token inválido o expirado. Generando un nuevo token...");
                $this->login(); // Generar un nuevo token
                return $this->request($endpoint, $data, $isPost); // Reintentar la solicitud
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
                error_log('Nuevo token generado y guardado: ' . $this->token);
            } else {
                // Manejar el error si no se obtiene un token
                error_log('Error al obtener el token: ' . json_encode($response));
                wp_die('No se pudo obtener el token. Verifica tus credenciales.');
            }
    }
    // Primera llamada a las tarifas según el idioma seleccionado. Importa los productos disponibles y con precio. Los guarda en un archivo CSV.
    private function create_file($products_to_import, $selected_zone, $stock_map = []) {
                $messages = [];
                //$route_file = plugin_dir_path(__FILE__) . 'products.csv';
                $upload_dir = wp_upload_dir();
                $route_file = $upload_dir['basedir'] . '/wpallimport/files/products.csv';

                error_log('Intentando crear el archivo CSV en: ' . $route_file);
            
                $file = fopen($route_file, 'w+');
                if ($file === false) {
                    error_log('No se pudo abrir el archivo CSV para escritura.');
                    return false;
                }
            
                // Escribir encabezados
                $column_names = [
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
                    "Stock",
                    "Estado stock",
                    "EAN",
                ];
                fputcsv($file, $column_names);
            
                    if ($selected_zone === 'Español') {
                        foreach ($products_to_import as $product) {
                            error_log("Producto: " . json_encode($product)); // Log para depuración
                            // Recuperar el SKU y el nombre del producto
                            $sku = $product['REFERENCIA'];
                            error_log("SKU: $sku"); // Log para depuración
                                if (strlen($sku) < 5 && ctype_digit((string)$sku)) {
                                    $sku = '0' . $sku;
                                }
                            // Determinar el PVPR según el idioma seleccionado
                            $pvpr = $product['PVP ESP']; // Ejemplo para Español
                            // Verificar si se encontró un PVPR válido
                            if ($pvpr === null || !is_numeric($pvpr)) {
                                $messages[] =  "❌ $sku: No disponible<br>";
                                continue; // Saltar al siguiente producto
                            } else {
                                $messages[] =  "✅ Producto guardado en csv: $sku<br>";
                            }  
                            // Escribir datos del producto en el CSV
                                $row = $this->build_row($sku, $pvpr, $stock_map, count($column_names));
                            fputcsv($file, $row);
                        }
                    }  else {
                        foreach ($products_to_import as $product) {
                            error_log("Producto: " . json_encode($product)); // Log para depuración
                            // Recuperar el SKU y el nombre del producto
                                $sku = $product['SKU'];
                                    if (strlen($sku) < 5 && ctype_digit((string)$sku)) {
                                        $sku = '0' . $sku;
                                    }
                            // Determinar el PVPR según el idioma seleccionado
                                $pvpr = null;
                                switch ($selected_zone) {              
                                    case 'Portugues':
                                        $pvpr = $product['PVP PORTUGAL']; // Ejemplo para Español
                                        break;
                                    case 'Italiano':
                                        $pvpr = $product['PVP ITALY'];
                                        break;
                                    case 'Francés':
                                        $pvpr = $product['PVP FRANCE'];
                                        break;
                                    case 'Alemán':
                                        $pvpr = $product['PVP GERMANY'];
                                        break;
                                    case 'Holandes':
                                        $pvpr = $product['PVP ZONE 1'];
                                        break;    
                                    case 'Zona 1':
                                        $pvpr = $product['PVP ZONE 1'];
                                        break;
                                    case 'Zona 2':
                                        $pvpr = $product['PVP ZONE 2'];
                                        break;
                                    case 'Zona 3':
                                        $pvpr = $product['PVP ZONE 3'];
                                        break;
                                    default:
                                        $pvpr = null; // Si no hay un caso definido
                                        break;
                                }           
                                // Verificar si se encontró un PVPR válido
                                if ($pvpr === null || !is_numeric($pvpr)) {
                                    $messages[] = "❌ $sku: No disponible<br>";
                                    continue; // Saltar al siguiente producto
                                } else {
                                    $messages[] = "✅ Producto guardado en csv: $sku<br>";
                                }    
                            // Escribir datos del producto en el CSV
                                $row = $this->build_row($sku, $pvpr, $stock_map, count($column_names));
                            fputcsv($file, $row);           
                        }                  
                    }
                fclose($file);
                error_log('Archivo CSV creado correctamente en: ' . $route_file);
            
                return ['file_path' => $route_file, 'messages' => $messages];        
    }
    // Fila inicial del CSV: SKU, precio y stock del feed de Cecotec. El resto lo rellena complement_csv_with_catalogs().
    private function build_row($sku, $pvpr, $stock_map, $num_columns) {
                $row = array_fill(0, $num_columns, ' ');
                $row[0] = $sku;
                $row[1] = '';
                $row[2] = $pvpr;

                $stock = CecoStockFeed::stock_for($stock_map, $sku);
                $row[19] = $stock;                                   // Stock
                $row[20] = ($stock > 0) ? 'instock' : 'outofstock';  // Estado stock
                $row[21] = CecoStockFeed::ean_for($stock_map, $sku); // EAN
                return $row;
    }
    // Segunda llamada a la API de catálogos. Complementa el CSV con los datos de los catálogos.
    private function complement_csv_with_catalogs($csv_file_path, $selected_zone) {
            // Obtener los catálogos desde la API
            $catalogs = $this->request("catalogs", [], true);
        
            if (empty($catalogs) || empty($catalogs['data'])) {
                error_log('❌ No se encontraron catálogos para complementar el CSV.');
                return false;
            }
        
            $all_data = $catalogs['data'];
            $additional_data = [];
        
            if($selected_zone === "Zona 1" || $selected_zone === "Zona 2" || $selected_zone === "Zona 3") {
                $selected_zone = "Ingles";
            }        
            // Buscar el catálogo en el idioma seleccionado
            foreach ($all_data as $data) {
                if ($data['language'] === $selected_zone) {
                    $catalog = $this->request("catalog/{$data['id']}", [], true);
        
                    if (empty($catalog) || empty($catalog['data'])) {
                        error_log('❌ No se encontraron productos en el catálogo seleccionado.');
                        return false;
                    }
        
                    $additional_data = $catalog['data'];
                    break;
                }
            }
        
            if (empty($additional_data)) {
                error_log('❌ No se encontraron datos adicionales para complementar el CSV.');
                return false;
            }
        
            // Leer el archivo CSV existente
            $rows = [];
            if (($handle = fopen($csv_file_path, 'r')) !== false) {
                while (($row = fgetcsv($handle, 0, ',')) !== false) {
                    $rows[] = $row;
                }
                fclose($handle);
            }
        
            // Extraer encabezados
            $headers = array_shift($rows);
        
            // Complementar los datos del CSV con los datos adicionales
            $updated_rows = [];
            foreach ($rows as $row) {
                $sku = $row[0]; // Suponiendo que el SKU está en la primera columna
                foreach ($additional_data as $product) {
                    if ($product['REFERENCIA'] === $sku) {
                        // Rellenar las columnas existentes con los datos del catálogo
                        $row[1] = $product['NOMBRE'] ?? $row[1]; // Nombre del producto
                        $row[3] = $product['CATEGORÍA PADRE'] ?? $row[3]; // Categoría principal
                        $row[4] = $product['CATEGORÍA'] ?? $row[4]; // Categoría secundaria
                        $row[5] = $product['SUBCATEGORÍA'] ?? $row[5]; // Categoría terciaria
                        $row[6] = $product['DESCRIPCIÓN'] ?? $row[6]; // Descripción corta
                        $row[7] = $product['DATOS DEL PRODUCTO'] ?? $row[7]; // Descripción larga
                        $row[8] = $product['PESO PRODUCTO (kg)'] ?? $row[8]; // Peso
                        $row[9] = $product['LARGO PRODUCTO (cm)'] ?? $row[9]; // Largo
                        $row[10] = $product['ANCHO PRODUCTO (cm)'] ?? $row[10]; // Ancho
                        $row[11] = $product['ALTO PRODUCTO (cm)'] ?? $row[11]; // Alto
                        $row[12] = $product['VOLTAJE'] ?? $row[12]; // Voltaje
                        $row[13] = $product['VOLTAJE Y CAPACIDAD DE LA BATERÍA'] ?? $row[13]; // Voltaje y capacidad de la batería
                        $row[14] = $product['IMAGE_1'] ?? $row[14]; // Imagen destacada
                        $row[15] = $product['IMAGE_2'] ?? $row[15]; // Imagen 1
                        $row[16] = $product['IMAGE_3'] ?? $row[16]; // Imagen 2
                        $row[17] = $product['IMAGE_4'] ?? $row[17]; // Imagen 3
                        $row[18] = $product['IMAGE_5'] ?? $row[18]; // Imagen 4
                        break;
                    }
                }
                $updated_rows[] = $row;
            }
        
            // Escribir los datos actualizados en el archivo CSV
            if (($handle = fopen($csv_file_path, 'w')) !== false) {
                // Escribir encabezados
                fputcsv($handle, $headers);
        
                // Escribir las filas actualizadas
                foreach ($updated_rows as $row) {
                    fputcsv($handle, $row);
                }
                fclose($handle);
            }
        
            error_log('✅ Archivo CSV complementado correctamente.');
            return true;
    }
    // Trigger para empezar la importación de productos
    public function ajax_import_products() {
                // Iniciar el buffer de salida
                ob_start();

                try {

                    // Obtener las tarifas desde la API
                    $rates = $this->request("rates", [], true);
                    error_log("Tarifas: " . json_encode($rates)); // Log para depuración
                    if (empty($rates) || empty($rates['data'])) {
                        ob_end_clean(); // Limpiar el buffer si hay un error
                        wp_send_json_error(['message' => 'No se encontraron tarifas.']);
                    }

                    // Determinar el ID de la tarifa según el idioma seleccionado
                    $selected_zone = get_option('ceco_selected_zone', 'Español'); // Valor predeterminado: Español
                    
                    //$rate_id = ($selected_zone === 'Español') ? $rates['data'][1]['id'] : $rates['data'][0]['id'];
                    $rate_id = null;

                    if ($selected_zone != 'Español') {
                        // Buscar el campo que contenga "INTER"
                        foreach ($rates['data'] as $rate) {
                            if (strpos($rate['name'], 'INTER') !== false) {
                                $rate_id = $rate['id'];
                                break;
                            }
                        }
                    } else {
                        // Buscar el campo que NO contenga "INTER"
                        foreach ($rates['data'] as $rate) {
                            if (strpos($rate['name'], 'INTER') === false) {
                                $rate_id = $rate['id'];
                                break;
                            }
                        }
                    }


                    // Obtener los detalles de la tarifa específica
                    $rate = $this->request("rate/{$rate_id}", [], true);
                    if (empty($rate) || empty($rate['data'])) {
                        ob_end_clean(); // Limpiar el buffer si no hay productos
                        wp_send_json_error(['message' => 'No se encontraron productos en la tarifa seleccionada.']);
                    }
                    $offset = 5; // Offset inicial para la paginación
                    $limit = 10; // Límite de productos por lote
                    // Obtener el lote actual de productos
                    $products = $rate['data'];
                    $products_to_import = array_slice($products, $offset, -1);

                    if (empty($products_to_import)) {
                        ob_end_clean(); // Limpiar el buffer si no hay productos
                        wp_send_json_error(['message' => 'No se encontraron productos para importar en este lote.']);
                    }

                    // Stock de la zona desde el feed de dropshipping de Cecotec.
                    // Si no se puede leer se para: un CSV sin stock dejaría todos los productos agotados.
                    $stock_map = CecoStockFeed::get_stock_map($selected_zone, true);
                    if (is_wp_error($stock_map)) {
                        ob_end_clean();
                        wp_send_json_error(['message' => 'No se pudo leer el stock de Cecotec: ' . $stock_map->get_error_message()]);
                    }

                    // Crear el archivo CSV con los datos recuperados
                    $csv_file_result = $this->create_file($products_to_import, $selected_zone, $stock_map);
                    if (!$csv_file_result || empty($csv_file_result['file_path'])) {
                        ob_end_clean();
                        wp_send_json_error(['message' => 'Error al crear el archivo CSV.']);
                    }

                    $csv_file_path = $csv_file_result['file_path'];

                    // Llamar a catalogs para complementar el CSV
                    $this->complement_csv_with_catalogs($csv_file_path, $selected_zone);

                    // Procesar productos desde el CSV
                    error_log("Iniciando importación de productos desde el archivo CSV...");
                
                    //$this->import_products_in_batches($csv_file_path, $limit);

                    // Capturar los mensajes generados por los `echo`
                    $log_messages = ob_get_clean();

                    // Verificar si hay más productos por importar
                    $has_more = count($products_to_import) == $limit;

                    wp_send_json_success([
                        'has_more' => $has_more,
                        'next_offset' => $offset + $limit, // Enviar el próximo offset
                        'message' => 'Lote procesado correctamente. ID del catalogo: ' . $rate_id . '. Stock leído de: ' . CecoStockFeed::feed_url($selected_zone),
                        'log' => $log_messages, // Incluir los mensajes en la respuesta
                        'csv_file' => $csv_file_path // Ruta del archivo CSV generado
                    ]);
                } catch (Exception $e) {
                    ob_end_clean(); // Limpiar el buffer en caso de error
                    error_log("Error en la importación: " . $e->getMessage());
                    wp_send_json_error(['message' => 'Error en la importación: ' . $e->getMessage()]);
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