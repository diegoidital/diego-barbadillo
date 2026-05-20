<?php
/**
 * Frontend Shortcode + Asset Handler
 *
 * @package Lezned_Maintenance_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LZND_Frontend {

    /**
     * Register hooks.
     */
    public static function init() {
        add_shortcode( 'lznd_actuacion', [ __CLASS__, 'shortcode' ] );
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
    }

    /**
     * Enqueue frontend assets only on pages containing the shortcode.
     */
    public static function enqueue() {
        global $post;

        $has_shortcode = false;

        if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'lznd_actuacion' ) ) {
            $has_shortcode = true;
        }

        if ( ! $has_shortcode ) {
            return;
        }

        wp_enqueue_style(
            'lznd-frontend',
            LZND_URL . 'frontend/css/frontend.css',
            [],
            LZND_VERSION
        );

        // Ensure UTF-8 charset is declared for the page
        add_action( 'wp_head', function() {
            // Only add if the theme hasn't already added it
            echo '<meta charset="UTF-8">' . "\n";
        }, 1 );

        wp_enqueue_script(
            'lznd-frontend',
            LZND_URL . 'frontend/js/frontend.js',
            [ 'jquery' ],
            LZND_VERSION,
            true
        );

        wp_localize_script(
            'lznd-frontend',
            'lzndData',
            [
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'lznd_frontend' ),
                'user_id'  => get_current_user_id(),
                'i18n'     => [
                    'loading'            => __( 'Cargando...', 'lezned-maintenance' ),
                    'error'              => __( 'Ha ocurrido un error. Por favor, inténtalo de nuevo.', 'lezned-maintenance' ),
                    'confirm_send_email' => __( '¿Enviar el parte por email?', 'lezned-maintenance' ),
                    'confirm_send_wp'    => __( '¿Enviar el parte por WhatsApp?', 'lezned-maintenance' ),
                    'send_success'       => __( 'Parte enviado correctamente.', 'lezned-maintenance' ),
                    'task_add_empty'     => __( 'Por favor, escribe el texto de la tarea.', 'lezned-maintenance' ),
                    'delete_foto'        => __( '¿Eliminar esta foto?', 'lezned-maintenance' ),
                    'delete_task'        => __( '¿Eliminar esta tarea?', 'lezned-maintenance' ),
                    'sign_empty'         => __( 'Firma el pad antes de guardar.', 'lezned-maintenance' ),
                    'sign_saved'         => __( 'Firma guardada.', 'lezned-maintenance' ),
                    'uploading'          => __( 'Subiendo foto...', 'lezned-maintenance' ),
                    'saving'             => __( 'Guardando...', 'lezned-maintenance' ),
                    'saved'              => __( 'Guardado', 'lezned-maintenance' ),
                    'select_project'     => __( '-- Selecciona un proyecto --', 'lezned-maintenance' ),
                    'select_tipo'        => __( '-- Selecciona el tipo de trabajo --', 'lezned-maintenance' ),
                ],
            ]
        );
    }

    /**
     * Shortcode callback.
     *
     * @param array $atts
     * @return string
     */
    public static function shortcode( $atts = [] ) {
        if ( ! is_user_logged_in() ) {
            ob_start();
            ?>
            <div class="lznd-login-wrap">
                <p class="lznd-login-message">
                    <?php esc_html_e( 'Debes iniciar sesión para acceder a esta sección.', 'lezned-maintenance' ); ?>
                </p>
                <?php
                wp_login_form( [
                    'redirect'       => get_permalink(),
                    'label_username' => __( 'Usuario', 'lezned-maintenance' ),
                    'label_password' => __( 'Contraseña', 'lezned-maintenance' ),
                    'label_remember' => __( 'Recordarme', 'lezned-maintenance' ),
                    'label_log_in'   => __( 'Entrar', 'lezned-maintenance' ),
                ] );
                ?>
            </div>
            <?php
            return ob_get_clean();
        }

        // Get actuacion_id from query string if present
        $actuacion_id = intval( $_GET['actuacion_id'] ?? 0 );

        ob_start();
        include LZND_TEMPLATES . 'frontend.php';
        return ob_get_clean();
    }
}

LZND_Frontend::init();
