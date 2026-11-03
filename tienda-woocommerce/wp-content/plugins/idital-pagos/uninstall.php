<?php
/**
 * Desinstalación: borra los ajustes del plugin. Los datos de los pedidos se conservan
 * (son registros contables).
 *
 * @package IditalPagos
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'woocommerce_idital_qonto_settings' );
delete_option( 'woocommerce_idital_tropipay_settings' );
delete_transient( 'idital_tpp_token' );

global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'idital\\_lock\\_%'" ); // phpcs:ignore
