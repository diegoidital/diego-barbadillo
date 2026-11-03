<?php
/**
 * Arranque del plugin.
 *
 * @package IditalPagos
 */

defined( 'ABSPATH' ) || exit;

final class Idital_Pagos_Plugin {

	const IDS             = array( 'idital_qonto', 'idital_tropipay' );
	const HOOK_REVISION   = 'idital_pagos_revisar_qonto';
	const DIAS_RESERVA    = 7;

	public static function init(): void {
		add_filter(
			'woocommerce_payment_gateways',
			static function ( $pasarelas ) {
				$pasarelas[] = 'Idital_Pagos_Gateway_Qonto';
				$pasarelas[] = 'Idital_Pagos_Gateway_Tropipay';
				return $pasarelas;
			}
		);

		add_action( 'rest_api_init', array( 'Idital_Pagos_Rest', 'registrar' ) );

		add_action( 'woocommerce_thankyou_idital_qonto', array( 'Idital_Pagos_Vista', 'caja' ), 5 );
		add_action( 'woocommerce_thankyou_idital_tropipay', array( 'Idital_Pagos_Vista', 'caja' ), 5 );
		add_action( 'woocommerce_view_order', array( 'Idital_Pagos_Vista', 'caja' ), 5 );

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'estilos' ) );

		// Los pedidos con link de pago no se cancelan a los 60 minutos (por defecto en WooCommerce):
		// se reservan unos días para dar tiempo a pagar o a que llegue una transferencia.
		add_filter( 'woocommerce_cancel_unpaid_order', array( __CLASS__, 'no_cancelar' ), 10, 2 );

		// Red de seguridad: si un webhook de Qonto se pierde, una tarea revisa los pendientes cada 10 min.
		add_action( self::HOOK_REVISION, array( __CLASS__, 'revisar_pendientes_qonto' ) );
		add_action( 'action_scheduler_init', array( __CLASS__, 'programar_revision' ) );

		// Checkout por bloques.
		add_action( 'woocommerce_blocks_payment_method_type_registration', array( __CLASS__, 'registrar_bloques' ) );
	}

	/**
	 * @return array<string, Idital_Pagos_Gateway>
	 */
	public static function pasarelas(): array {
		if ( ! function_exists( 'WC' ) || ! WC()->payment_gateways() ) {
			return array();
		}
		$todas = WC()->payment_gateways()->payment_gateways();
		return array_filter(
			$todas,
			static fn( $p, $id ) => in_array( $id, self::IDS, true ) && $p instanceof Idital_Pagos_Gateway,
			ARRAY_FILTER_USE_BOTH
		);
	}

	public static function estilos(): void {
		if ( function_exists( 'is_checkout' ) && ( is_checkout() || is_account_page() ) ) {
			wp_enqueue_style( 'idital-pagos', IDITAL_PAGOS_URL . 'assets/css/pagos.css', array(), IDITAL_PAGOS_VERSION );
		}
	}

	public static function no_cancelar( $cancelar, $pedido ) {
		if ( $pedido instanceof WC_Order && in_array( $pedido->get_payment_method(), self::IDS, true ) ) {
			$creado = $pedido->get_date_created();
			if ( $creado && $creado->getTimestamp() > time() - self::DIAS_RESERVA * DAY_IN_SECONDS ) {
				return false;
			}
		}
		return $cancelar;
	}

	public static function programar_revision(): void {
		if ( function_exists( 'as_has_scheduled_action' ) && ! as_has_scheduled_action( self::HOOK_REVISION ) ) {
			as_schedule_recurring_action( time() + 300, 10 * MINUTE_IN_SECONDS, self::HOOK_REVISION, array(), 'idital-pagos' );
		}
	}

	public static function revisar_pendientes_qonto(): void {
		if ( ! Idital_Pagos_Config::qonto_configurado() ) {
			return;
		}
		$pedidos = wc_get_orders(
			array(
				'status'         => array( 'pending', 'on-hold', 'failed' ),
				'payment_method' => 'idital_qonto',
				'date_created'   => '>' . ( time() - self::DIAS_RESERVA * DAY_IN_SECONDS ),
				'limit'          => 25,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		foreach ( $pedidos as $pedido ) {
			if ( '' !== (string) $pedido->get_meta( '_idital_qonto_link_id' ) ) {
				Idital_Pagos_Gateway_Qonto::verificar( $pedido, 'revisión automática' );
			}
		}
	}

	public static function registrar_bloques( $registro ): void {
		require_once IDITAL_PAGOS_DIR . 'includes/class-blocks.php';
		foreach ( self::IDS as $id ) {
			$registro->register( new Idital_Pagos_Blocks( $id ) );
		}
	}
}
