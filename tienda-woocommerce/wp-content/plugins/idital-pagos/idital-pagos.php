<?php
/**
 * Plugin Name:       Idital Pagos (Qonto + TropiPay)
 * Description:       Pasarelas de pago para WooCommerce que generan en el servidor links de pago de Qonto y TropiPay y confirman el cobro consultando a la propia pasarela.
 * Version:           1.5.2
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Idital
 * Text Domain:       idital-pagos
 * License:           GPL-2.0-or-later
 * WC requires at least: 8.0
 *
 * @package IditalPagos
 */

defined( 'ABSPATH' ) || exit;

define( 'IDITAL_PAGOS_VERSION', '1.5.2' );
define( 'IDITAL_PAGOS_FILE', __FILE__ );
define( 'IDITAL_PAGOS_DIR', plugin_dir_path( __FILE__ ) );
define( 'IDITAL_PAGOS_URL', plugin_dir_url( __FILE__ ) );

// Compatibilidad con HPOS (tablas de pedidos propias) y con el checkout por bloques.
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', IDITAL_PAGOS_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', IDITAL_PAGOS_FILE, true );
		}
	}
);

add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
			add_action(
				'admin_notices',
				static function () {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'Idital Pagos necesita WooCommerce activo.', 'idital-pagos' ) . '</p></div>';
				}
			);
			return;
		}

		$archivos = array(
			'class-config.php',
			'class-log.php',
			'class-http.php',
			'class-bloqueo.php',
			'class-limite.php',
			'class-registro.php',
			'class-confirmacion.php',
			'class-qonto-oauth.php',
			'class-qonto-client.php',
			'class-tropipay-client.php',
			'abstract-class-gateway.php',
			'class-gateway-qonto.php',
			'class-gateway-tropipay.php',
			'class-rest.php',
			'class-vista.php',
			'class-presupuestos.php',
			'class-enlaces-admin.php',
			'class-plugin.php',
		);
		foreach ( $archivos as $archivo ) {
			require_once IDITAL_PAGOS_DIR . 'includes/' . $archivo;
		}

		Idital_Pagos_Plugin::init();
	},
	11
);

register_deactivation_hook(
	__FILE__,
	static function () {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( 'idital_pagos_revisar_qonto' );
			as_unschedule_all_actions( 'idital_pagos_qonto_token' );
		}
	}
);
