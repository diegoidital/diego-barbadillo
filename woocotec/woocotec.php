<?php
/**
 * Plugin Name: Importador de productos Cecotec.
 * Description: Crea el CSV de productos de Cecotec para WP All Import: catálogo de Plytix, precios y stock de los feeds de dropshipping (España) o de Cecopublisher (resto de zonas), y sincroniza el stock cada hora.
 * Version: 2.0.3
 * Author: Idital
 * Author URI: https://idital.com
 * License: GPL2
 * Text Domain: woocotec
 */

// Exit if accessed directly
    if ( ! defined( 'ABSPATH' ) ) { exit; }

    define('WOOCOTEC_VERSION', '2.0.3');

/*******************************************************************************
 *  add plugin files
 ******************************************************************************/
    
    include plugin_dir_path(__FILE__) . '/inc/ceco-api.php';
    include plugin_dir_path(__FILE__) . '/inc/ceco-stock.php';
    include plugin_dir_path(__FILE__) . '/inc/ceco-catalog.php';
    
    if (is_admin()) {
        function enqueue_admin_styles() {
            wp_enqueue_style('woocotec-admin-style', plugin_dir_url(__FILE__) . 'css/style.css', [], null);
        }
        add_action('admin_enqueue_scripts', 'enqueue_admin_styles');

        //wp_localize_script('ceco-api-js', 'ajaxurl', admin_url('admin-ajax.php'));
    }


    
/*******************************************************************************
 *  enqueue js files
 ******************************************************************************/
    
    function enqueue_import_script() {
            if (is_admin()) {
                wp_enqueue_script('import-products', plugin_dir_url(__FILE__) . 'js/ceco-api.js', ['jquery'], WOOCOTEC_VERSION, true);
                wp_localize_script('import-products', 'cecoStock', ['nonce' => wp_create_nonce('ceco_sync_stock')]);
                //wp_localize_script('import-products', 'ajaxurl', admin_url('admin-ajax.php'));
            }
        }
        add_action('admin_enqueue_scripts', 'enqueue_import_script');

/*******************************************************************************
 *  create admin menu
 ******************************************************************************/
    
    function add_admin_menu() {
            add_menu_page(
                'Generar CSV de productos de Cecotec',
                'Generador CSV Cecotec',
                'manage_options',
                'import-cecopublisher',
                'render_import_page'
            );
        }
        add_action('admin_menu', 'add_admin_menu');
    
/*******************************************************************************
 *  render import page
 ******************************************************************************/
    
    function render_import_page() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ceco_email']) && isset($_POST['ceco_password'])) {
            $email = sanitize_email($_POST['ceco_email']);
            $password = sanitize_text_field($_POST['ceco_password']);

            // Guardar las credenciales en las opciones de WordPress
            update_option('ceco_email', $email);
            update_option('ceco_password', $password);
            update_option('ceco_token', ''); // Resetear el token

            echo '<div class="updated"><p>Credenciales guardadas.</p></div>';
            
        } else {
            // Obtener las credenciales almacenadas en las opciones de WordPress
            $email = get_option('ceco_email', '');
            $password = get_option('ceco_password', '');
        }

        require plugin_dir_path(__FILE__) . 'view.php';
    }

/*******************************************************************************
 *  register settings
 ******************************************************************************/
    
    function handle_save_credentials() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ceco_email']) && isset($_POST['ceco_password'])) {
            // Guardar las credenciales en la base de datos
            $email = sanitize_email($_POST['ceco_email']);
            $password = sanitize_text_field($_POST['ceco_password']);
            update_option('ceco_email', $email);
            update_option('ceco_password', $password);

            // Instanciar la clase y generar el token
            update_option('ceco_token', '');
            $ceco = new CecoPublisherToWooCommerce();
            $ceco->login(); // Generar y guardar el token (solo se usa fuera de España)

            // Redirigir al usuario de vuelta a la página de configuración
            wp_redirect(admin_url('admin.php?page=import-cecopublisher'));
            exit;
        }
    }
    add_action('admin_post_save_credentials', 'handle_save_credentials');

/*******************************************************************************
 *  save selected zone
 ******************************************************************************/

/*function handle_save_selected_zone() {
    if (isset($_POST['selected_zone'])) {
        $selected_zone = sanitize_text_field($_POST['selected_zone']);
        update_option('ceco_selected_zone', $selected_zone); // Guardar en la base de datos
        wp_redirect(admin_url('admin.php?page=import-cecopublisher')); // Redirigir de vuelta a la página
        exit;
    }
}*/

/*******************************************************************************
 *  register cron event
 ******************************************************************************/

    // Register hook para el cron
    add_action('ceco_import_products_event', [new CecoPublisherToWooCommerce(),'cron_build_csv']);


    function ceco_register_cron_event() {
        if (!wp_next_scheduled('ceco_import_products_event')) {
            wp_schedule_event(time(), 'daily', 'ceco_import_products_event');
        }
    }
    add_action('wp', 'ceco_register_cron_event');       


/*******************************************************************************
 *  excepciones de borrado de productos
 ******************************************************************************/

    function handle_save_import_settings() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['excluded_categories'])) {
            // Sanitizar y guardar las categorías seleccionadas
            $excluded_categories = array_map('sanitize_text_field', $_POST['excluded_categories']);
            update_option('ceco_excluded_categories', $excluded_categories);

            // Redirigir al usuario de vuelta a la página de configuración
            wp_redirect(admin_url('admin.php?page=import-cecopublisher&settings_saved=true'));
            exit;
        }
    }
    add_action('admin_post_save_import_settings', 'handle_save_import_settings');

/*******************************************************************************
 *  Guarda el idioma
 ******************************************************************************/
    function handle_save_selected_zone() {
        if (isset($_POST['selected_zone'])) {
            $selected_zone = sanitize_text_field($_POST['selected_zone']);
            update_option('ceco_selected_zone', $selected_zone); // Guardar en la base de datos
            delete_transient('ceco_stock_feed_cache'); // La zona cambia el feed de stock
            wp_redirect(admin_url('admin.php?page=import-cecopublisher')); // Redirigir de vuelta a la página
            exit;
        }
    }
    // Registrar la acción de WordPress
    add_action('admin_post_save_selected_zone', 'handle_save_selected_zone');

/*******************************************************************************
 *  Controlador de ajax para importar productos
 ******************************************************************************/
    // La acción AJAX import_products la registra CecoPublisherToWooCommerce::__construct().


/*******************************************************************************
 *  Al desactivar el plugin se quita el cron de stock
 ******************************************************************************/
    function ceco_clear_stock_cron() {
        wp_clear_scheduled_hook('ceco_sync_stock_event');
        wp_clear_scheduled_hook('ceco_download_catalog_event');
    }
    register_deactivation_hook(__FILE__, 'ceco_clear_stock_cron');
