<div id="woocotec" class="wrap">
    <h1>Importar Productos CecoPublisher</h1>

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

    <h2>Crea el csv con los productos de Cecotec</h2>
    <table>
        <tr >
            <td>
                  <span style="margin-bottom: 20px;">Una vez generado el CSV, para crear los productos en woocommerce, ir a All Import y ejecutar la importación: "Productos Cecotec" en el apartado "Manage Imports"</span>  
            </td>
        </tr>
        <tr>
            <td>
                <button id="start-import" class="button button-primary">Importación manual</button>
            </td>
        </tr>
    </table>

  
    
    <div id="import-log" style="margin-top: 20px; padding: 10px; border: 1px solid #ddd; background-color: #f9f9f9; max-height: 300px; overflow-y: auto;"></div>
</div>