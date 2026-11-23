<div id="woocotec" class="wrap">
    <h1>Importar Productos Cecotec</h1>

    <div class="ceco-top">
        <div class="ceco-top-col">
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
        </div>
        <div class="ceco-top-col">
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
                <button type="submit" title="Guarda la zona: decide de qué feed se leen el stock y los precios, y el idioma del catálogo.">Guardar</button>
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
                <ul style="list-style-type:disc; margin-left: 18px;">
                    <li>Los productos de las zonas se importan en inglés, pero tienen tarifas distintas.</li>
                    <li>Si se elige holandés como idioma, la tarifa es la Zona 1</li>
                </ul>
            </form>
        </div>
    </div>

    <?php
        $ceco_sync_text = CecoStockFeed::describe(get_option(CecoStockFeed::LAST_SYNC, []));
        $ceco_csv_text  = CecoPublisherToWooCommerce::describe_csv(get_option('ceco_last_csv', []));
    ?>
    <div class="ceco-actions">
        <div class="ceco-action">
            <p class="ceco-status"><strong>Última sincronización:</strong> <span class="ceco-sync-status" title="<?= esc_attr($ceco_sync_text); ?>"><?= esc_html($ceco_sync_text); ?></span></p>
            <button class="button button-secondary ceco-btn-sync" title="Lee ahora el feed de Cecotec y actualiza el stock de los productos de la tienda (lo mismo que hace cada hora el cron). No crea productos ni cambia precios.">Sincronizar stock ahora</button>
            <p class="description ceco-btn-help">Actualiza ya el stock de la tienda con el feed de Cecotec (se hace solo cada hora). No crea productos ni cambia precios.</p>
        </div>
        <div class="ceco-action">
            <p class="ceco-status"><strong>Último CSV:</strong> <span class="ceco-csv-status" title="<?= esc_attr($ceco_csv_text); ?>"><?= esc_html($ceco_csv_text); ?></span></p>
            <button class="button button-primary ceco-btn-import" title="Descarga el catálogo de Plytix si hace falta y genera el CSV (products.csv) con precios, stock y fichas. No modifica la tienda: después hay que ejecutar «Productos Cecotec» en WP All Import.">Importación manual</button>
            <p class="description ceco-btn-help">Genera ahora el CSV para WP All Import (se hace solo cada día). No cambia la tienda: después hay que ejecutar «Productos Cecotec» en All Import.</p>
        </div>
        <div class="ceco-log ceco-import-log"></div>
    </div>

    <details class="ceco-section" id="ceco-csv">
        <summary><h2>Crear CSV Cecotec</h2></summary>
        <div class="ceco-section-body">
    <p>El CSV se genera automáticamente una vez al día. Una vez generado, para crear/actualizar los productos en WooCommerce, ir a All Import › Manage Imports y ejecutar la importación «Productos Cecotec».</p>
    <p><strong>Último CSV:</strong> <span class="ceco-csv-status"><?= esc_html($ceco_csv_text); ?></span></p>
    <p><label><input type="checkbox" id="force-catalog"> Volver a descargar el catálogo aunque esté al día</label></p>
    <p><button class="button button-primary ceco-btn-import" title="Descarga el catálogo de Plytix si hace falta y genera el CSV (products.csv) con precios, stock y fichas. No modifica la tienda: después hay que ejecutar «Productos Cecotec» en WP All Import.">Importación manual</button></p>
    <div class="ceco-log ceco-import-log"></div>

        </div>
    </details>

    <details class="ceco-section" id="ceco-stock">
        <summary><h2>Stock</h2></summary>
        <div class="ceco-section-body">
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
        <li>Los productos de Cecotec (los de la importación y los de marca Cecotec, EVVO o Cecolchon) que <strong>no</strong> aparecen en el feed se dejan agotados (opción de abajo). Los de otras marcas no se tocan.</li>
        <li>Cada hora se actualiza el stock de los productos de la tienda que aparecen en el feed. Se activa la gestión de inventario y se desactivan las reservas: un producto sin stock queda <em>Agotado</em> y no se puede comprar.</li>
        <li>El CSV de productos incluye también las columnas <code>Stock</code>, <code>Estado stock</code> y <code>EAN</code>.</li>
        <li>Para que tampoco se muestren los agotados: WooCommerce › Ajustes › Productos › Inventario › «Ocultar en el catálogo los artículos agotados».</li>
    </ul>
    <p><strong>Última sincronización:</strong> <span class="ceco-sync-status"><?= esc_html($ceco_sync_text); ?></span></p>
    <p><button class="button button-secondary ceco-btn-sync" title="Lee ahora el feed de Cecotec y actualiza el stock de los productos de la tienda (lo mismo que hace cada hora el cron). No crea productos ni cambia precios.">Sincronizar stock ahora</button></p>
    <?php if ($ceco_next_sync) : ?>
        <p><strong>Próxima sincronización automática:</strong> <?= esc_html(get_date_from_gmt(gmdate('Y-m-d H:i:s', $ceco_next_sync), 'd/m/Y H:i')); ?></p>
    <?php endif; ?>

    <form method="post" action="<?= admin_url('admin-post.php'); ?>">
        <input type="hidden" name="action" value="save_stock_settings">
        <?php wp_nonce_field('ceco_save_stock_settings'); ?>
        <table class="form-table">
            <tr valign="top">
                <th scope="row">Productos de Cecotec fuera del feed</th>
                <td>
                    <label><input type="checkbox" name="ceco_stock_zero_missing" value="1" <?php checked(CecoStockFeed::zero_missing()); ?>> Marcarlos como agotados (stock 0) en cada sincronización</label>
                    <p class="description">Si Cecotec deja de servir un producto, desaparece del feed: así no se vende sin stock real. Por seguridad no se hace si el feed llega con un 20&nbsp;% menos de productos que la vez anterior.</p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">URL del feed de stock (opcional)</th>
                <td>
                    <input type="url" name="ceco_stock_feed_url" class="large-text" value="<?= esc_attr($ceco_custom_url); ?>" placeholder="<?= esc_attr(CecoStockFeed::default_url($ceco_zone)); ?>" />
                    <p class="description">Déjalo vacío para usar la URL de la zona seleccionada. Rellénalo solo si Cecotec vuelve a cambiar la dirección.</p>
                </td>
            </tr>
        </table>
        <?php submit_button('Guardar ajustes de stock', 'secondary'); ?>
    </form>
        </div>
    </details>

    <details class="ceco-section" id="ceco-catalogo">
        <summary><h2>Ajustes de imágenes</h2></summary>
        <div class="ceco-section-body">
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
        </div>
    </details>

    <details class="ceco-section" id="ceco-imagenes">
        <summary><h2>Optimización de imágenes</h2></summary>
        <div class="ceco-section-body">
    <?php
        $ceco_sizes     = function_exists('wp_get_registered_image_subsizes') ? wp_get_registered_image_subsizes() : [];
        $ceco_essential = array_intersect_key($ceco_sizes, array_flip(CecoImageCache::ESSENTIAL_SIZES));
    ?>
    <p>Lo que más tarda al importar es que WordPress genere las miniaturas de cada foto. Estas opciones lo reducen:</p>
    <form method="post" action="<?= admin_url('admin-post.php'); ?>">
        <input type="hidden" name="action" value="save_image_settings">
        <?php wp_nonce_field('ceco_save_image_settings'); ?>
        <table class="form-table">
            <tr valign="top">
                <th scope="row">Preparar fotos antes de importar</th>
                <td>
                    <label><input type="checkbox" name="ceco_img_cache" value="1" <?php checked(CecoImageCache::enabled()); ?>> Descargar y reducir las fotos en segundo plano; el CSV apunta a las copias del propio servidor (<code>uploads/woocotec-img</code>).</label><br>
                    <label><input type="checkbox" name="ceco_img_only_new" value="1" <?php checked(CecoImageCache::only_new()); ?>> Solo para productos nuevos (los existentes no actualizan imágenes).</label><br>
                    Tamaño máximo: <input type="number" name="ceco_img_max_px" min="600" max="3000" step="100" value="<?= esc_attr(CecoImageCache::max_px()); ?>" style="width:90px"> px
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">Productos sin foto</th>
                <td>
                    <label><input type="checkbox" name="ceco_skip_no_photo" value="1" <?php checked(get_option('ceco_skip_no_photo', '0'), '1'); ?>> No publicar productos que no tienen ninguna foto (ni en el feed de Cecotec ni en Plytix)</label>
                    <p class="description">Se quedan fuera del CSV; los que ya existen pasan a borrador en la siguiente importación. Se vuelven a publicar solos cuando Cecotec añada fotos.</p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">Fotos por producto</th>
                <td><input type="number" name="ceco_img_per_product" min="1" max="20" value="<?= esc_attr(CecoImageCache::per_product()); ?>" style="width:70px"> como máximo (la primera es la destacada)</td>
            </tr>
            <tr valign="top">
                <th scope="row">Miniaturas durante la importación</th>
                <td>
                    <label><input type="checkbox" name="ceco_img_light_sizes" value="1" <?php checked(CecoImageCache::light_sizes()); ?>> Generar solo las miniaturas de WooCommerce mientras importa WP All Import</label>
                    <p class="description">Hay <?= count($ceco_sizes); ?> tamaños registrados en la web; con esta opción se generan <?= count($ceco_essential); ?> (<?= esc_html(implode(', ', array_keys($ceco_essential))); ?>). Si alguna plantilla pide otro tamaño, WordPress usa la imagen más próxima.</p>
                </td>
            </tr>
        </table>
        <?php submit_button('Guardar ajustes de imágenes', 'secondary'); ?>
    </form>
    <p><strong>Estado:</strong> <span id="images-status"><?= esc_html(CecoImageCache::describe()); ?></span></p>
    <p><button id="process-images" class="button button-secondary" title="Descarga y reduce ahora las fotos pendientes del CSV; al terminar regenera el CSV para que apunte a ellas.">Preparar fotos ahora</button> <span class="description">Se preparan solas por cron; este botón lo acelera (deja la pestaña abierta). Al terminar se regenera el CSV.</span></p>
        </div>
    </details>

    <details class="ceco-section" id="ceco-limpiar">
        <summary><h2>Otros optimizadores</h2></summary>
        <div class="ceco-section-body">
    <h3>Borradores antiguos de Cecotec</h3>
    <p>Busca productos en <strong>borrador</strong> que <strong>no</strong> controla la importación de WP All Import y cuyo SKU (sin contar ceros a la izquierda) coincide con un producto del CSV actual: por ejemplo «123» o «00127» frente a «00123» / «00127». Son fichas antiguas de plugins anteriores; si se dejan, WooCommerce no permite crear la ficha nueva con el mismo SKU.</p>
    <p>No toca productos publicados, ni borradores de la importación actual, ni borradores que no estén en el CSV. Se envían a la <strong>papelera</strong>, de donde se pueden recuperar.</p>
    <p>
        <button id="find-old-drafts" class="button button-secondary" title="Solo muestra la lista; no modifica nada.">1. Buscar borradores antiguos</button>
        <button id="trash-old-drafts" class="button button-secondary" disabled title="Envía a la papelera los borradores de la lista (se pueden recuperar).">2. Enviar a la papelera</button>
    </p>
    <div id="old-drafts-result" style="margin-top: 10px; max-height: 400px; overflow-y: auto;"></div>

    <h3>Nombres con el EAN delante</h3>
    <p>Cecotec pone el EAN delante del nombre en algunos productos (p. ej. «8435743904049-Ventilador Evaporativo EVVO AC750»). El CSV ya lo quita; este botón corrige los productos que ya se crearon así. No cambia la URL.</p>
    <p>
        <button id="find-ean-names" class="button button-secondary" title="Solo muestra la lista; no modifica nada.">1. Buscar nombres con EAN</button>
        <button id="fix-ean-names" class="button button-secondary" disabled title="Quita el EAN del nombre de los productos de la lista. No cambia la URL.">2. Corregir nombres</button>
    </p>
    <div id="ean-names-result" style="margin-top: 10px; max-height: 400px; overflow-y: auto;"></div>

    <h3>Productos publicados sin foto</h3>
    <p>Algunos productos se crearon en importaciones anteriores sin imagen y la importación no actualiza las fotos de los productos existentes. Esta herramienta les pone las fotos que trae el CSV actual (las ya preparadas de 1200 px). No toca productos que ya tienen foto.</p>
    <p>
        <button id="find-missing-photos" class="button button-secondary" title="Solo muestra la lista; no modifica nada.">1. Buscar productos sin foto</button>
        <button id="fix-missing-photos" class="button button-secondary" disabled title="Asigna las fotos del CSV a los productos de la lista (imagen destacada y galería).">2. Poner fotos</button>
    </p>
    <div id="missing-photos-result" style="margin-top: 10px; max-height: 400px; overflow-y: auto;"></div>
        </div>
    </details>

    <details class="ceco-section" id="ceco-origen">
        <summary><h2>Origen de datos</h2></summary>
        <div class="ceco-section-body">
    <table class="widefat striped" style="max-width: 900px;">
        <thead><tr><th>Dato</th><th>España</th><th>Resto de zonas</th></tr></thead>
        <tbody>
            <tr><td>Ficha de producto (nombre, descripciones, familias, imágenes, medidas)</td><td colspan="2">Catálogo Plytix (XML)</td></tr>
            <tr><td>Precio (PVPR)</td><td>Feed DROPES (<code>pvpr</code>)</td><td>Cecopublisher, tarifa DROP INTER (requiere las credenciales de abajo)</td></tr>
            <tr><td>Stock</td><td>Feed DROPES</td><td>Feed de la zona (DROPPT, DROPIT, DROPDE, DROPFR, DROPZ1, DROPZ2, DROPZ3)</td></tr>
        </tbody>
    </table>
    <p class="description">Cruce entre fuentes: SKU del catálogo = <code>public_id</code> del feed = SKU de Cecopublisher.</p>
        </div>
    </details>

</div>
