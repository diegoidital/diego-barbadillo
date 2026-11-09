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

    public function __construct() {
        $this->email = get_option('ceco_email', 'info@emetronix.com');
        $this->password = get_option('ceco_password', '123456');
        $this->token = get_option('ceco_token', '');
        $this->selected_zone = get_option('ceco_selected_zone', 'ES');

        add_action('wp_ajax_import_products', [$this, 'ajax_import_products']);
        add_action('admin_post_import_products', [$this, 'handle_import_products']);
        add_action('admin_post_delete_missing_products', [$this, 'handle_delete_missing_products']);
        add_action('admin_post_save_credentials', [$this, 'handle_save_credentials']);
        add_action('admin_post_save_selected_zone', [$this, 'handle_save_selected_zone']);
        add_action('admin_post_import_file', [$this, 'create_empty_file']);
    }

/*******************************************************************************
 *  Handler for the import button
 ******************************************************************************/

    public function handle_import() {
            $this->login();
            ob_start(); // Iniciar el buffer de salida
            $this->import_products();
            $output = ob_get_clean(); // Obtener el contenido del buffer y limpiar
            wp_redirect(admin_url('admin.php?page=import-cecopublisher'));
            exit;
    }

/*******************************************************************************
 *  Llamada
 ******************************************************************************/

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

/*******************************************************************************
 *  Login
 ******************************************************************************/

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

/*******************************************************************************
 *  Importar precios
 ******************************************************************************/

   /* public function import_rates($id) {
        
        $rates = $this->request("rates", [], true);
        if (empty($rates)) {
            error_log("No se encontraron tarifas.");
            return false; // No hay tarifas
        }

        if ($rates['data']) {
            foreach ($rates['data'] as $rate) {
                $rate = $this->request("rate/{$rate['id']}", [], true);
                foreach ($rate['data'] as $rate) {
                    $referencia = strval($rate['REFERENCIA']);
                    if (strlen($referencia) < 5 && ctype_digit($referencia)) {
                        $sku = '0' . $referencia;
                    } else {
                        $sku = $referencia;
                    }

                    if ($sku == $id) {
                        return $rate['PVPR']; // Devuelve el precio si se encuentra
                    }
                }
            }
        }

        return false; // No se encontró un rate válido
    }*/

    public function import_rates($id) {
        // Obtener el idioma seleccionado
        $selected_zone = get_option('ceco_selected_zone', 'Español'); // Valor predeterminado: Español

        // Obtener las tarifas desde la API
        $rates = $this->request("rates", [], true);
        if (empty($rates) || empty($rates['data'])) {
            error_log("No se encontraron tarifas.");
            return false; // No hay tarifas
        }

        // Determinar el ID de la tarifa según el idioma seleccionado
        $rate_id = null;
        if ($selected_zone === 'Español') {
            $rate_id = $rates['data'][0]['id']; // Primer ID para Español
        } else {
            $rate_id = $rates['data'][1]['id']; // Segundo ID para otros idiomas
        }

        // Obtener los detalles de la tarifa específica
        $rate = $this->request("rate/{$rate_id}", [], true);
        if (empty($rate) || empty($rate['data'])) {
            error_log("No se encontraron datos para la tarifa con ID: $rate_id");
            return false;
        }

        // Buscar el SKU en los datos de la tarifa
        foreach ($rate['data'] as $rate_item) {
            $referencia = strval($rate_item['REFERENCIA']);
            if (strlen($referencia) < 5 && ctype_digit($referencia)) {
                $sku = '0' . $referencia;
            } else {
                $sku = $referencia;
            }

            if ($sku == $id) {
                return $rate_item['PVPR']; // Devuelve el precio si se encuentra
            }
        }

        return false; // No se encontró un rate válido
    }

 /*******************************************************************************
 *  Importar productos --> Deprecado
 ******************************************************************************/

    /*public function import_products() {

            $selected_zone = get_option('ceco_selected_zone', 'Español'); // Valor predeterminado: Español
            $catalogs = $this->request("catalogs", [], true);

            if (empty($catalogs)) {
                echo 'No se encontraron catálogos.<br>';
                return;
            }

            $all_data = $catalogs['data'];
            $products_to_import = [];

            foreach ($all_data as $data) {
                if ($data['language'] === $selected_zone) { // Comparar con el idioma seleccionado
                    $catalog = $this->request("catalog/{$data['id']}", [], true);
                    $products_to_import = $catalog['data'];
                    break;
                }
            }

            if (empty($products_to_import)) {
                echo 'No se encontraron productos para importar.<br>';
                return;
            }

            $this->create_file($catalog, $catalog_details);
            $this->download_file($route_file);

            /*foreach ($products_to_import as $product) {
                $this->create_or_update_product($product);
            }*/

            /*echo 'Todos los productos han sido importados.<br>';
    }*/

 /*******************************************************************************
 *  Constructor
 ******************************************************************************/

    public function create_or_update_product($product_data) {
        if (!function_exists('wc_get_product')) {
            echo "WooCommerce no está activo.";
            return;
        }

        $sku = $product_data['REFERENCIA']; // SKU de la API
        $existing_product_id = wc_get_product_id_by_sku($sku);

        $pvp = $this->import_rates($sku);

        if ($pvp === false) {
            // Si no hay rate, elimina el producto si existe
            if ($existing_product_id) {
                wp_delete_post($existing_product_id, true);
                echo "🗑️ Producto eliminado: " . $product_data['NOMBRE'] . " (ID: $existing_product_id) porque no tiene precio.<br>";
            } else {
                echo "❌ Producto no creado: " . $product_data['NOMBRE'] . " porque no tiene precio.<br>";
            }
            return; // Salir de la función
        }

        if ($existing_product_id) {
            $product = wc_get_product($existing_product_id);
            echo "Actualizando producto existente: " . $product_data['NOMBRE'] . " (ID: $existing_product_id)<br>";
            echo "💶 El precio del producto es: " . $pvp . "<br>";
        } else {
            $product = new WC_Product_Simple();
            echo "Creando nuevo producto: " . $product_data['NOMBRE'] . "<br>";
            echo "💶 El precio del producto es: " . $pvp . "<br>";
        }

        // 1️⃣ Asignar datos del producto
        $product->set_name($product_data['NOMBRE']);
        $product->set_sku($sku);
        $product->set_short_description($product_data['DESCRIPCIÓN']);
        $product->set_description($product_data['DATOS DEL PRODUCTO']);
        $product->set_regular_price($pvp);

        // 2️⃣ Asignar dimensiones y peso
        $product->set_weight($product_data['PESO PRODUCTO (kg)'] ?? '');
        $product->set_length($product_data['LARGO PRODUCTO (cm)'] ?? '');
        $product->set_width($product_data['ANCHO PRODUCTO (cm)'] ?? '');
        $product->set_height($product_data['ALTO PRODUCTO (cm)'] ?? '');

        // 3️⃣ Guardar el producto en WooCommerce
        $product_id = $product->save();

        // Crea la marca si no existe
        $brand_name = "Cecotec";
        $brand_term = term_exists($brand_name, 'product_brand');
        if (!$brand_term) {
            $brand_term = wp_insert_term($brand_name, 'product_brand');
        }
        // Asigna la marca al producto
        wp_set_object_terms($product->get_id(), $brand_name, 'product_brand');

        // 4️⃣ Asignar categorías
        $categorias = array_filter([$product_data['CATEGORÍA PADRE'] ?? '', $product_data['CATEGORÍA'] ?? '', $product_data['SUBCATEGORÍA'] ?? '']);

        if (!empty($categorias)) {
            $parent_id = 0; // Inicialmente no hay padre

            foreach ($categorias as $categoria) {
                // Buscar la categoría
                $term = get_term_by('name', $categoria, 'product_cat');

                if (!$term) {
                    echo "📁 La categoría $categoria no existe. Creando... ✅<br>";
                    // Crear la categoría con su padre asignado
                    $created_term = wp_insert_term($categoria, 'product_cat', [
                        'parent' => $parent_id
                    ]);

                    if (!is_wp_error($created_term)) {
                        $term_id = $created_term['term_id']; // ID de la nueva categoría
                        $term = get_term($term_id, 'product_cat'); // Obtener los datos completos
                        echo "📂 Creada: $categoria (ID: $term_id)<br>";
                    } else {
                        echo "❌ Error al crear $categoria: " . $created_term->get_error_message() . "<br>";
                        continue; // Si hay error, saltar a la siguiente categoría
                    }
                } else {
                    $term_id = $term->term_id; // Si ya existe, obtener su ID
                    echo "📁 La categoría $categoria ya existe (ID: $term_id). ➡️ Asignando...<br>";
                }

                // Si la categoría es la primera, se convierte en la "padre" para las siguientes
                if ($categoria === reset($categorias)) {
                    $parent_id = $term_id;
                }
            }

            // Asignar todas las categorías al producto
            wp_set_object_terms($product_id, $categorias, 'product_cat');
        }

        // 5️⃣ Asignar Imágenes

        // 1️⃣ Asignar la imagen destacada
        if (!empty($product_data['IMAGE_1'])) {
            $attachment_id = $this->import_image_from_url($product_data['IMAGE_1'], $product_id);
            if ($attachment_id) {
                $product->set_image_id($attachment_id);
                $product->save();
            }
        }

        // 2️⃣ Asignar imágenes a la galería
        $gallery_images = [
            $product_data['IMAGE_2'] ?? '',
            $product_data['IMAGE_3'] ?? '',
            $product_data['IMAGE_4'] ?? '',
            $product_data['IMAGE_5'] ?? ''
        ];

        $gallery_ids = [];
        foreach ($gallery_images as $image_url) {
            if (!empty($image_url)) {
                $attachment_id = $this->import_image_from_url($image_url, $product_id);
                if ($attachment_id) {
                    $gallery_ids[] = $attachment_id;
                }
            }
        }
        if (!empty($gallery_ids)) {
            update_post_meta($product_id, '_product_image_gallery', implode(',', $gallery_ids));
        }

        echo "--------------------- <br>" . "Producto importado: " . $product_data['NOMBRE'] . " (ID: $product_id)<br>";

        return $product_id;
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
            'limit'   => -1,
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

/*******************************************************************************
 *  Importar imagen desde URL
 ******************************************************************************/

    private function import_image_from_url($image_url, $post_id) {
            if (!$image_url) {
                return false;
            }

            // Extraer el nombre del archivo de la URL
            $image_name = basename(parse_url($image_url, PHP_URL_PATH));

            // 1️⃣ Buscar la imagen en la biblioteca por nombre de archivo
            global $wpdb;
            $attachment_id = $wpdb->get_var($wpdb->prepare("
                SELECT ID FROM {$wpdb->posts} 
                WHERE post_type = 'attachment' 
                AND post_title = %s 
                LIMIT 1", 
                pathinfo($image_name, PATHINFO_FILENAME)
            ));

            if ($attachment_id) {
                echo "🖼️ Imagen '$image_name' ya existe en la biblioteca (ID: $attachment_id). ➡️ Asignando...<br>";
                return $attachment_id; // Devuelve el ID de la imagen existente
            }

            // 2️⃣ Si no existe, subir la imagen
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');

            $attachment_id = media_sideload_image($image_url, $post_id, null, 'id');

            if (!is_wp_error($attachment_id)) {
                echo "🖼️ Imagen '$image_name' subida correctamente (ID: $attachment_id). ✅<br>";
                return $attachment_id;
            } else {
                echo "⚠️ Error al subir la imagen '$image_name': " . $attachment_id->get_error_message() . "<br>";
                return false;
            }
    }

/*******************************************************************************
 *  Añade el menú de administración ---> No se usa
 ******************************************************************************/

    /*public function add_admin_menu() {
            add_menu_page(
                'Importar Productos CecoPublisher',
                'Importar Productos',
                'manage_options',
                'import-cecopublisher',
                [$this, 'render_import_page']
            );
    }*/

/*******************************************************************************
 *  Acción de administrador para importar productos
 ******************************************************************************/

    public function ajax_import_products() {
        // Iniciar el buffer de salida
        ob_start();
        // Obtener el offset y el límite de la solicitud AJAX
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $limit = 20; // Procesar 20 productos por lote

        // Obtener los catálogos desde la API
        $catalogs = $this->request("catalogs", [], true);
        error_log('Respuesta de la APi:' . print_r($catalogs, true));
        error_log('Offset actual: ' . $offset);
        if (empty($catalogs) || empty($catalogs['data'])) {
            ob_end_clean(); // Limpiar el buffer si hay un error
            wp_send_json_error(['message' => 'No se encontraron catálogos.']);
        }

        $selected_zone = get_option('ceco_selected_zone', 'Español'); // Valor predeterminado: Español

        $catalogs = $this->request("catalogs", [], true);

        if (empty($catalogs)) {
            echo 'No se encontraron catálogos.<br>';
            return;
        }

        $all_data = $catalogs['data'];
        $products_to_import = [];

        // Buscar el catálogo en español
        foreach ($all_data as $data) {
            if ($data['language'] === $selected_zone) {
                $catalog = $this->request("catalog/{$data['id']}", [], true);

                if (empty($catalog) || empty($catalog['data'])) {
                    ob_end_clean(); // Limpiar el buffer si no hay productos
                    wp_send_json_error(['message' => 'No se encontraron productos en el catálogo.']);
                }

                $products = $catalog['data'];
                $products_to_import = array_slice($products, $offset, $limit); // Obtener el lote actual
                $catalog_found = true;
                break;
            }
        }

        if (!$catalog_found) {
            ob_end_clean(); // Limpiar el buffer si no se encontró un catálogo
            wp_send_json_error(['message' => 'No se encontró un catálogo en español.']);
        }

        if (empty($products_to_import)) {
            ob_end_clean(); // Limpiar el buffer si no hay productos
            wp_send_json_error(['message' => 'No se encontraron productos para importar en este lote.']);
        }

        // Procesar los productos del lote actual
        foreach ($products_to_import as $product) {
            $this->create_or_update_product($product);
        }

        // Capturar los mensajes generados por los `echo`
        $log_messages = ob_get_clean();

        // Verificar si hay más productos por importar
        $has_more = count($products_to_import) == $limit;

        wp_send_json_success([
            'has_more' => $has_more,
            'next_offset' => $offset + $limit, // Enviar el próximo offset
            'message' => 'Lote procesado correctamente.',
            'log' => $log_messages // Incluir los mensajes en la respuesta
        ]);
    }

/*******************************************************************************
 *  Acción de administrador para importar productos
 ******************************************************************************/

    private function create_file($catalog_data, $catalog_details) {
        $route_file = plugin_dir_path(__FILE__) . 'file.csv';
        error_log('Intentando crear el archivo CSV en: ' . $route_file);

        $file = fopen($route_file, 'w+');
        if ($file === false) {
            error_log('No se pudo abrir el archivo CSV para escritura.');
            return false;
        }

        // Escribir encabezados
        $column_names = [
            "type",
            "sku",
            "name",
            "published",
            "short_description",
            "description",
            "stock_quantity",
            "regular_price",
            "category_ids",
            "images",
            "backorders"
        ];
        fputcsv($file, $column_names);

        // Procesar datos
        foreach ($catalog_data["response"]->data as $item) {
            // Procesar categorías e imágenes
            $categories = '';
            $imagenes = '';
            foreach ($catalog_details['col_names']['Categorías'] as $col_name) {
                $categories .= $item->$col_name . ' > ';
            }
            foreach ($catalog_details['col_names']['Imágenes'] as $col_name) {
                $imagenes .= $item->$col_name . ', ';
            }

            // Escribir datos del producto
            $item_data = [
                'simple',
                $item->{$catalog_details['col_names']['SKU']},
                $item->{$catalog_details['col_names']['Nombre']},
                1,
                $item->{$catalog_details['col_names']['Descripcion corta']},
                $item->{$catalog_details['col_names']['Descripcion']},
                $item->{$catalog_details['col_names']['Inventario']},
                $item->{$catalog_details['col_names']['Precio normal']},
                rtrim($categories, ' > '),
                rtrim($imagenes, ', '),
                'no'
            ];
            fputcsv($file, $item_data);
        }

        fclose($file);
        error_log('Archivo CSV creado correctamente en: ' . $route_file);

        return $route_file;
    }

    private function download_file($name_file) {
        header($_SERVER["SERVER_PROTOCOL"] . " 200 OK");
        header("Cache-Control: public"); // needed for internet explorer
        header("Content-Type: application/zip");
        header("Content-Transfer-Encoding: Binary");
        //header("Content-Length:".filesize($route_file));
        header("Content-Disposition: attachment; filename=file.csv");
        readfile($name_file);
        die();
    }

/*******************************************************************************
 *  Guarda el el idioma
 ******************************************************************************/

    public function handle_save_selected_zone() {
        if (isset($_POST['selected_zone'])) {
            $selected_zone = sanitize_text_field($_POST['selected_zone']);
            update_option('ceco_selected_zone', $selected_zone); // Guardar en la base de datos
            wp_redirect(admin_url('admin.php?page=import-cecopublisher')); // Redirigir de vuelta a la página
            exit;
        }
    }

}