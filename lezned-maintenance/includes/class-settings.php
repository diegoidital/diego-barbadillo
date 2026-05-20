<?php
/**
 * Settings Page Class
 *
 * @package Lezned_Maintenance_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LZND_Settings {

    /**
     * Register hooks.
     */
    public static function init() {
        add_action( 'admin_post_lznd_save_settings', [ __CLASS__, 'save' ] );
    }

    /**
     * Render settings page.
     */
    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'No tienes permisos para acceder a esta página.', 'lezned-maintenance' ) );
        }

        $settings   = get_option( 'lznd_settings', [] );
        $saved      = isset( $_GET['updated'] ) && $_GET['updated'] === '1';
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Configuración – Lezned Maintenance', 'lezned-maintenance' ); ?></h1>

            <?php if ( $saved ) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e( 'Configuración guardada correctamente.', 'lezned-maintenance' ); ?></p>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="lznd-form">
                <?php wp_nonce_field( 'lznd_save_settings', 'lznd_settings_nonce' ); ?>
                <input type="hidden" name="action" value="lznd_save_settings">

                <div class="lznd-settings-section">
                    <h2><?php esc_html_e( 'Correo electrónico', 'lezned-maintenance' ); ?></h2>
                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row">
                                    <label for="lznd-admin-email"><?php esc_html_e( 'Email del administrador', 'lezned-maintenance' ); ?></label>
                                </th>
                                <td>
                                    <input
                                        type="email"
                                        id="lznd-admin-email"
                                        name="lznd_settings[admin_email]"
                                        value="<?php echo esc_attr( $settings['admin_email'] ?? get_option( 'admin_email' ) ); ?>"
                                        class="regular-text"
                                        placeholder="admin@ejemplo.com"
                                    >
                                    <p class="description"><?php esc_html_e( 'Se enviará una copia de cada parte a este email.', 'lezned-maintenance' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="lznd-from-name"><?php esc_html_e( 'Nombre del remitente', 'lezned-maintenance' ); ?></label>
                                </th>
                                <td>
                                    <input
                                        type="text"
                                        id="lznd-from-name"
                                        name="lznd_settings[from_name]"
                                        value="<?php echo esc_attr( $settings['from_name'] ?? get_bloginfo( 'name' ) ); ?>"
                                        class="regular-text"
                                        placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"
                                    >
                                    <p class="description"><?php esc_html_e( 'Nombre que aparece como remitente en los emails enviados.', 'lezned-maintenance' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="lznd-enviar-cliente">
                                        <?php esc_html_e( 'Enviar al cliente', 'lezned-maintenance' ); ?>
                                    </label>
                                </th>
                                <td>
                                    <label>
                                        <input
                                            type="checkbox"
                                            id="lznd-enviar-cliente"
                                            name="lznd_settings[enviar_cliente]"
                                            value="1"
                                            <?php checked( $settings['enviar_cliente'] ?? '', '1' ); ?>
                                        >
                                        <?php esc_html_e( 'Enviar copia del parte al email de contacto del proyecto', 'lezned-maintenance' ); ?>
                                    </label>
                                    <p class="description"><?php esc_html_e( 'Si está activo, el cliente recibirá el parte al enviar.', 'lezned-maintenance' ); ?></p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="lznd-settings-section">
                    <h2><?php esc_html_e( 'WhatsApp', 'lezned-maintenance' ); ?></h2>
                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row">
                                    <label for="lznd-whatsapp-admin"><?php esc_html_e( 'Número WhatsApp del administrador', 'lezned-maintenance' ); ?></label>
                                </th>
                                <td>
                                    <input
                                        type="text"
                                        id="lznd-whatsapp-admin"
                                        name="lznd_settings[whatsapp_admin]"
                                        value="<?php echo esc_attr( $settings['whatsapp_admin'] ?? '' ); ?>"
                                        class="regular-text"
                                        placeholder="34600000000"
                                    >
                                    <p class="description"><?php esc_html_e( 'Número en formato internacional sin + (ej: 34600000000). Se usará si el proyecto no tiene teléfono de contacto.', 'lezned-maintenance' ); ?></p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="lznd-settings-section">
                    <h2><?php esc_html_e( 'Avisos automáticos', 'lezned-maintenance' ); ?></h2>
                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row">
                                    <label for="lznd-aviso-dias"><?php esc_html_e( 'Días de aviso previo', 'lezned-maintenance' ); ?></label>
                                </th>
                                <td>
                                    <input
                                        type="number"
                                        id="lznd-aviso-dias"
                                        name="lznd_settings[aviso_dias]"
                                        value="<?php echo esc_attr( $settings['aviso_dias'] ?? 7 ); ?>"
                                        class="small-text"
                                        min="1"
                                        max="60"
                                    >
                                    <p class="description"><?php esc_html_e( 'Días de antelación con los que se enviará el aviso de mantenimiento próximo.', 'lezned-maintenance' ); ?></p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php esc_html_e( 'Guardar configuración', 'lezned-maintenance' ); ?>
                    </button>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Handle settings form submission.
     */
    public static function save() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'No tienes permisos.', 'lezned-maintenance' ) );
        }

        check_admin_referer( 'lznd_save_settings', 'lznd_settings_nonce' );

        $raw = $_POST['lznd_settings'] ?? [];

        $settings = [
            'admin_email'    => sanitize_email( $raw['admin_email'] ?? get_option( 'admin_email' ) ),
            'from_name'      => sanitize_text_field( $raw['from_name'] ?? get_bloginfo( 'name' ) ),
            'whatsapp_admin' => preg_replace( '/[^0-9]/', '', $raw['whatsapp_admin'] ?? '' ),
            'aviso_dias'     => max( 1, min( 60, intval( $raw['aviso_dias'] ?? 7 ) ) ),
            'enviar_cliente' => isset( $raw['enviar_cliente'] ) ? '1' : '0',
        ];

        update_option( 'lznd_settings', $settings );

        wp_redirect( admin_url( 'admin.php?page=lznd-settings&updated=1' ) );
        exit;
    }
}

LZND_Settings::init();
