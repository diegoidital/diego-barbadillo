<div id="woocotec" class="wrap">
    <h1>Importar Productos Cecotec</h1>

    <h2>Origen de los datos</h2>
    <table class="widefat striped" style="max-width: 900px;">
        <thead><tr><th>Dato</th><th>España</th><th>Resto de zonas</th></tr></thead>
        <tbody>
            <tr><td>Ficha de producto (nombre, descripciones, familias, imágenes, medidas)</td><td colspan="2">Catálogo Plytix (XML)</td></tr>
            <tr><td>Precio (PVPR)</td><td>Feed DROPES (<code>pvpr</code>)</td><td>Cecopublisher, tarifa DROP INTER (requiere las credenciales de abajo)</td></tr>
            <tr><td>Stock</td><td>Feed DROPES</td><td>Feed de la zona (DROPPT, DROPIT, DROPDE, DROPFR, DROPZ1, DROPZ2, DROPZ3)</td></tr>
        </tbody>
    </table>
    <p class="description">Cruce entre fuentes: SKU del catálogo = <code>public_id</code> del feed = SKU de Cecopublisher.</p>

    <hr>

    <h2>Credenciales de Cecopublisher <small>(solo para precios fuera de España)</small></h2>

    <form method="post" action="<?= admin_url('admin-post.php'); ?>">
        <input type="hidden" name="action" value="save_credentials">
        <table class="form-table">
            <tr valign="top">
                <th scope="row">Email</th>
                <td><input type="email" name="ceco_email" value="<?= esc_attr(get_option('ceco_email', '')); ?>" required /></td>
            </tr>
            <tr valign="top">
                <th scope="row">Password</th>
                <td><input type="password" name="ceco_password" value="<?= esc_attr(get_option('ceco_password', '')); ?>" required /></td>
            </tr>
        </table>
        <?php submit_button('Guardar Credenciales'); ?>
    </form>

    <hr>

    <div class="flexotec">

            <?php $selected_zone = get_option('ceco_selected_zone', 'Español'); ?>
            <form method="post" action="<?= admin_url('admin-post.php'); ?>">
                    <h2>Selección de Idioma</h2>   
            <input type="hidden" name="action" value="save_selected_zone">
                <select name="selected_zone" id="selected_zone">
                    <option value="Español" <?php selected($selected_zone, 'Español'); ?>>Español</option>
                    <option value="Italiano" <?php selected($selected_zone, 'Italiano'); ?>>Italiano</option>
                    <option value="Francés" <?php selected($selected_zone, 'Francés'); ?>>Francés</option>
                    <option value="Alemán" <?php selected($selected_zone, 'Alemán'); ?>>Alemán</option>
                    <option value="Portugues" <?php selected($selected_zone, 'Portugues'); ?>>Portugues</option>
                    <option value="Holandes" <?php selected($selected_zone, 'Holandes'); ?>>Holandés</option>
                    <option value="Zona 1" <?php selected($selected_zone, 'Zona 1'); ?>>Zona 1</option>
                    <option value="Zona 2" <?php selected($selected_zone, 'Zona 2'); ?>>Zona 2</option>
                    <option value="Zona 3" <?php selected($selected_zone, 'Zona 3'); ?>>Zona 3</option>
                </select>
                <button type="submit">Guardar</button>
                <table class="zones">
                    <tr>
                        <th>Zona 1</th>
                        <th>Zona 2</th>
                        <th>Zona 3</th>
                    </tr>
                    <tr>
                        <td>Austria</td> 
                        <td>Dinamarca</td>
                        <td>Suecia</td>
                    </tr>
                    <tr>
                        <td>Belgica</td>
                        <td></td>
                        <td>Finlandia</td>
                    </tr>
                    <tr>
                        <td>Luxemburgo</td>
                        <td></td>
                        <td>Irlanda</td>
                    </tr>
                    <tr>
                        <td>Países Bajos</td>
                        <td></td>
                        <td></td>
                    </tr>
                </table>
                <ul style="list-style-type:disc;">
                    <li>Los produtos de las zonas se importan en inglés,<br>
                    pero tienen tarifas distintas.</li>
                    <li>Si se elige holandés como idioma, la tarifa es la Zona 1</li>
                </ul>
            </form>

    <!--<form method="post" action="<?= admin_url('admin-post.php'); ?>">
            <input type="hidden" name="action" value="save_import_settings">

        
            <h2>Seleccionar categorías para excluir de la eliminación</h2>
            
            <select name="excluded_categories[]" multiple style="width: 100%; height: 150px;">
                <?php
                $categories = get_terms([
                    'taxonomy' => 'product_cat',
                    'hide_empty' => false,
                ]);

                $selected_categories = get_option('ceco_excluded_categories', []); // Recuperar categorías seleccionadas previamente

                foreach ($categories as $category) {
                    $selected = in_array($category->slug, $selected_categories) ? 'selected' : '';
                    echo "<option value='{$category->slug}' {$selected}>{$category->name}</option>";
                }
                ?>
            </select>
            <p>Usa Ctrl (Windows) o Cmd (Mac) para seleccionar múltiples categorías.</p>

            <?php submit_button('Guardar Configuración'); ?>
        </form>-->
    </div>

    <hr>

    <h2>Stock de Cecotec</h2>
    <?php
        $ceco_zone       = get_option('ceco_selected_zone', 'Español');
        $ceco_custom_url = get_option(CecoStockFeed::URL_OPTION, '');
        $ceco_next_sync  = wp_next_scheduled('ceco_sync_stock_event');
    ?>
    <p>
        El stock se lee del feed de dropshipping de Cecotec de la zona seleccionada
        (<strong><?= esc_html($ceco_zone); ?></strong>):<br>
        <code><?= esc_html(CecoStockFeed::feed_url()); ?></code>
    </p>
    <ul style="list-style-type:disc; margin-left: 20px;">
        <li>Se cruza el <code>public_id</code> del feed con el SKU del producto.</li>
        <li>Cada hora se actualiza el stock de los productos de la tienda que aparecen en el feed. Se activa la gestión de inventario y se desactivan las reservas: un producto sin stock queda <em>Agotado</em> y no se puede comprar.</li>
        <li>El CSV de productos incluye también las columnas <code>Stock</code>, <code>Estado stock</code> y <code>EAN</code>.</li>
        <li>Para que tampoco se muestren los agotados: WooCommerce › Ajustes › Productos › Inventario › «Ocultar en el catálogo los artículos agotados».</li>
    </ul>
    <p><strong>Última sincronización:</strong> <span id="stock-last-sync"><?= esc_html(CecoStockFeed::describe(get_option(CecoStockFeed::LAST_SYNC, []))); ?></span></p>
    <?php if ($ceco_next_sync) : ?>
        <p><strong>Próxima sincronización automática:</strong> <?= esc_html(get_date_from_gmt(gmdate('Y-m-d H:i:s', $ceco_next_sync), 'd/m/Y H:i')); ?></p>
    <?php endif; ?>
    <p><button id="start-stock-sync" class="button button-secondary">Sincronizar stock ahora</button></p>

    <form method="post" action="<?= admin_url('admin-post.php'); ?>">
        <input type="hidden" name="action" value="save_stock_settings">
        <?php wp_nonce_field('ceco_save_stock_settings'); ?>
        <table class="form-table">
            <tr valign="top">
                <th scope="row">URL del feed de stock (opcional)</th>
                <td>
                    <input type="url" name="ceco_stock_feed_url" class="large-text" value="<?= esc_attr($ceco_custom_url); ?>" placeholder="<?= esc_attr(CecoStockFeed::default_url($ceco_zone)); ?>" />
                    <p class="description">Déjalo vacío para usar la URL de la zona seleccionada. Rellénalo solo si Cecotec vuelve a cambiar la dirección.</p>
                </td>
            </tr>
        </table>
        <?php submit_button('Guardar URL de stock', 'secondary'); ?>
    </form>

    <hr>

    <h2>Catálogo de Plytix</h2>
    <?php
        $ceco_catalog_url  = get_option(CecoCatalogFeed::URL_OPTION, '');
        $ceco_gallery_mode = get_option(CecoCatalogFeed::GALLERY_OPTION, 'master_gallery');
    ?>
    <p><strong>Feed:</strong> <code><?= esc_html(CecoCatalogFeed::feed_url()); ?></code></p>
    <p><strong>Última descarga:</strong> <?= esc_html(CecoCatalogFeed::describe_download(get_option(CecoCatalogFeed::LAST_DOWNLOAD, []))); ?></p>
    <form method="post" action="<?= admin_url('admin-post.php'); ?>">
        <input type="hidden" name="action" value="save_catalog_settings">
        <?php wp_nonce_field('ceco_save_catalog_settings'); ?>
        <table class="form-table">
            <tr valign="top">
                <th scope="row">URL del catálogo (opcional)</th>
                <td>
                    <input type="url" name="ceco_catalog_feed_url" class="large-text" value="<?= esc_attr($ceco_catalog_url); ?>" placeholder="<?= esc_attr(CecoCatalogFeed::DEFAULT_URL); ?>" />
                    <p class="description">Vacío = feed XML «Integración Avanzada» de Cecotec. Tiene que ser el XML, no el Excel.</p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">Origen de las imágenes</th>
                <td>
                    <?php $ceco_image_source = CecoCatalogFeed::image_source(); ?>
                    <label><input type="radio" name="ceco_image_source" value="feed" <?php checked($ceco_image_source, 'feed'); ?>> Feed de Cecotec (media.cecotec.cloud): hasta 3 fotos de 2048 px (~0,5 MB). <strong>Recomendado</strong></label><br>
                    <label><input type="radio" name="ceco_image_source" value="plytix" <?php checked($ceco_image_source, 'plytix'); ?>> Plytix: más fotos, pero originales de 4.600-5.100 px y 7-10 MB (~35 s por foto al importar)</label>
                    <p class="description">Si el producto no tiene fotos en el origen elegido, se usan las del otro.</p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">Fotos de Plytix</th>
                <td>
                    <label><input type="radio" name="ceco_catalog_gallery_mode" value="master_gallery" <?php checked($ceco_gallery_mode, 'master_gallery'); ?>> Fotos máster (fondo blanco) + fotos «Gallery 1:1» (producto en uso)</label><br>
                    <label><input type="radio" name="ceco_catalog_gallery_mode" value="master" <?php checked($ceco_gallery_mode, 'master'); ?>> Solo fotos máster (fondo blanco)</label>
                    <p class="description">La imagen destacada es siempre la primera foto máster. Las fotos «enriquecidas» (con texto) no se usan.</p>
                </td>
            </tr>
        </table>
        <?php submit_button('Guardar ajustes del catálogo', 'secondary'); ?>
    </form>

    <hr>

    <h2>Crea el csv con los productos de Cecotec</h2>
    <p>Último CSV: <?= esc_html(CecoPublisherToWooCommerce::describe_csv(get_option('ceco_last_csv', []))); ?></p>
    <p>El CSV se genera automáticamente una vez al día. Una vez generado, para crear/actualizar los productos en WooCommerce, ir a All Import › Manage Imports y ejecutar la importación «Productos Cecotec».</p>
    <p><label><input type="checkbox" id="force-catalog"> Volver a descargar el catálogo aunque esté al día</label></p>
    <p><button id="start-import" class="button button-primary">Importación manual</button></p>

  
    
    <div id="import-log" style="margin-top: 20px; padding: 10px; border: 1px solid #ddd; background-color: #f9f9f9; max-height: 300px; overflow-y: auto;"></div>

    <hr>

    <h2>Limpiar borradores antiguos de Cecotec</h2>
    <p>Busca productos en <strong>borrador</strong> que <strong>no</strong> controla la importación de WP All Import y cuyo SKU (sin contar ceros a la izquierda) coincide con un producto del CSV actual: por ejemplo «123» o «00127» frente a «00123» / «00127». Son fichas antiguas de plugins anteriores; si se dejan, WooCommerce no permite crear la ficha nueva con el mismo SKU.</p>
    <p>No toca productos publicados, ni borradores de la importación actual, ni borradores que no estén en el CSV. Se envían a la <strong>papelera</strong>, de donde se pueden recuperar.</p>
    <p>
        <button id="find-old-drafts" class="button button-secondary">1. Buscar borradores antiguos</button>
        <button id="trash-old-drafts" class="button button-secondary" disabled>2. Enviar a la papelera</button>
    </p>
    <div id="old-drafts-result" style="margin-top: 10px; max-height: 400px; overflow-y: auto;"></div>

</div>