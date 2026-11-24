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
	const HOOK_TOKEN      = 'idital_pagos_qonto_token';
	const DIAS_RESERVA    = 7;

	public static function init(): void {
		Idital_Pagos_Presupuestos::init();
		Idital_Pagos_Enlaces_Admin::init();

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

		// Botón "Probar conexión con Qonto".
		add_action( 'admin_post_idital_probar_qonto', array( __CLASS__, 'probar_qonto' ) );
		add_action( 'admin_post_idital_probar_tropipay', array( __CLASS__, 'probar_tropipay' ) );
		add_action( 'admin_post_idital_qonto_conectar', array( __CLASS__, 'qonto_conectar' ) );
		add_action( 'admin_post_idital_qonto_desconectar', array( __CLASS__, 'qonto_desconectar' ) );
		add_action( 'admin_post_idital_qonto_mollie', array( __CLASS__, 'qonto_mollie' ) );

		// Mantiene viva la conexión OAuth con Qonto aunque no haya ventas.
		add_action( self::HOOK_TOKEN, array( 'Idital_Pagos_Qonto_Oauth', 'mantener' ) );
		add_action( 'admin_notices', array( __CLASS__, 'aviso_diagnostico' ) );

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
		if ( ! function_exists( 'as_has_scheduled_action' ) ) {
			return;
		}
		if ( ! as_has_scheduled_action( self::HOOK_REVISION ) ) {
			as_schedule_recurring_action( time() + 300, 10 * MINUTE_IN_SECONDS, self::HOOK_REVISION, array(), 'idital-pagos' );
		}
		if ( ! as_has_scheduled_action( self::HOOK_TOKEN ) ) {
			as_schedule_recurring_action( time() + 600, DAY_IN_SECONDS, self::HOOK_TOKEN, array(), 'idital-pagos' );
		}
	}

	private static function exigir_gestor( string $accion ): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'No tienes permiso para hacer esto.', 'idital-pagos' ), 403 );
		}
		check_admin_referer( $accion );
	}

	private static function volver_a_qonto(): void {
		wp_safe_redirect( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=idital_qonto' ) );
		exit;
	}

	public static function qonto_conectar(): void {
		self::exigir_gestor( 'idital_qonto_conectar' );
		if ( ! Idital_Pagos_Qonto_Oauth::cliente_configurado() ) {
			set_transient( 'idital_diag_' . get_current_user_id(), array( 'titulo' => 'Qonto', 'lineas' => array( array( 'error', 'Falta la aplicación OAuth de Qonto en el fichero de configuración.' ) ) ), 5 * MINUTE_IN_SECONDS );
			self::volver_a_qonto();
		}
		// Redirección externa a Qonto (dominio fijo, no controlado por el usuario).
		wp_redirect( Idital_Pagos_Qonto_Oauth::url_autorizacion() ); // phpcs:ignore WordPress.Security.SafeRedirect
		exit;
	}

	public static function qonto_mollie(): void {
		self::exigir_gestor( 'idital_qonto_mollie' );
		$aviso     = static function ( string $tipo, string $texto ) {
			set_transient( 'idital_diag_' . get_current_user_id(), array( 'titulo' => 'Qonto', 'lineas' => array( array( $tipo, $texto ) ) ), 5 * MINUTE_IN_SECONDS );
			self::volver_a_qonto();
		};
		$pasarelas = self::pasarelas();
		$qonto     = $pasarelas['idital_qonto'] ?? null;
		if ( ! $qonto || ! Idital_Pagos_Qonto_Oauth::conectado() ) {
			$aviso( 'error', 'Primero hay que conectar la tienda con Qonto.' );
		}
		$telefono    = (string) $qonto->get_option( 'telefono' );
		$descripcion = (string) $qonto->get_option( 'descripcion_negocio' );
		if ( '' === $telefono || mb_strlen( $descripcion ) < 80 ) {
			$aviso( 'error', 'Rellena y guarda "Teléfono de la empresa" y "Descripción del negocio" (mínimo 80 caracteres) antes de conectar los cobros.' );
		}
		try {
			$r = ( new Idital_Pagos_Qonto_Client() )->conectar_proveedor( $telefono, $descripcion, admin_url( 'admin.php?page=wc-settings&tab=checkout&section=idital_qonto' ) );
		} catch ( Idital_Pagos_Exception $e ) {
			Idital_Pagos_Log::error( 'No se pudo iniciar la conexión con Mollie', array( 'error' => $e->getMessage() ) );
			$aviso( 'error', $e->getMessage() );
		}
		if ( 'enabled' === $r['estado'] ) {
			$aviso( 'ok', 'Los cobros ya están activos (Mollie conectado). No hay que hacer nada más.' );
		}
		if ( '' === $r['url'] ) {
			$aviso( 'aviso', 'Ya existe una conexión con Mollie en estado "' . ( $r['estado'] ?: 'desconocido' ) . '". Si es "pending", Mollie la está revisando: vuelve a pulsar "Probar conexión con Qonto" más tarde.' );
		}
		Idital_Pagos_Log::info( 'Iniciada la conexión de cobros con Mollie', array( 'usuario' => get_current_user_id() ) );
		wp_redirect( $r['url'] ); // phpcs:ignore WordPress.Security.SafeRedirect -- dominio validado (Mollie/Qonto).
		exit;
	}

	public static function qonto_desconectar(): void {
		self::exigir_gestor( 'idital_qonto_desconectar' );
		Idital_Pagos_Qonto_Oauth::desconectar();
		set_transient( 'idital_diag_' . get_current_user_id(), array( 'titulo' => 'Qonto', 'lineas' => array( array( 'aviso', 'Tienda desconectada de Qonto. El pago con Qonto ya no se muestra a los clientes.' ) ) ), 5 * MINUTE_IN_SECONDS );
		self::volver_a_qonto();
	}

	public static function revisar_pendientes_qonto(): void {
		if ( ! Idital_Pagos_Config::qonto_configurado() ) {
			return;
		}
		for ( $pagina = 1; $pagina <= 8; $pagina++ ) {
			$pedidos = wc_get_orders(
				array(
					'status'       => array_merge( array( 'on-hold' ), Idital_Pagos_Confirmacion::estados_pagables() ),
					'date_created' => '>' . ( time() - self::DIAS_RESERVA * DAY_IN_SECONDS ),
					'limit'        => 25,
					'paged'        => $pagina,
					'orderby'      => 'date',
					'order'        => 'DESC',
				)
			);
			foreach ( $pedidos as $pedido ) {
				if ( $pedido instanceof WC_Order && $pedido->get_meta( '_idital_qonto_links' ) ) {
					Idital_Pagos_Gateway_Qonto::verificar( $pedido, 'revisión automática' );
				}
			}
			if ( count( $pedidos ) < 25 ) {
				break;
			}
		}
	}

	public static function probar_qonto(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'No tienes permiso para hacer esto.', 'idital-pagos' ), 403 );
		}
		check_admin_referer( 'idital_probar_qonto' );
		$resultado = Idital_Pagos_Qonto_Oauth::cliente_configurado()
			? ( new Idital_Pagos_Qonto_Client() )->diagnostico()
			: array( array( 'error', 'Falta la aplicación OAuth de Qonto en el fichero de configuración.' ) );
		set_transient( 'idital_diag_' . get_current_user_id(), array( 'titulo' => 'Qonto', 'lineas' => $resultado ), 5 * MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=idital_qonto' ) );
		exit;
	}

	public static function probar_tropipay(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'No tienes permiso para hacer esto.', 'idital-pagos' ), 403 );
		}
		check_admin_referer( 'idital_probar_tropipay' );
		$resultado = Idital_Pagos_Config::tropipay_configurado()
			? ( new Idital_Pagos_Tropipay_Client() )->diagnostico()
			: array( array( 'error', 'Faltan las credenciales de TropiPay en la configuración.' ) );
		set_transient( 'idital_diag_' . get_current_user_id(), array( 'titulo' => 'TropiPay', 'lineas' => $resultado ), 5 * MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=idital_tropipay' ) );
		exit;
	}

	public static function aviso_diagnostico(): void {
		$clave     = 'idital_diag_' . get_current_user_id();
		$datos = get_transient( $clave );
		if ( ! is_array( $datos ) || ! isset( $datos['lineas'] ) || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		delete_transient( $clave );
		$resultado = (array) $datos['lineas'];
		$iconos = array(
			'ok'    => '✅',
			'aviso' => '⚠️',
			'error' => '❌',
		);
		$tipo   = in_array( 'error', array_column( $resultado, 0 ), true ) ? 'error' : ( in_array( 'aviso', array_column( $resultado, 0 ), true ) ? 'warning' : 'success' );
		echo '<div class="notice notice-' . esc_attr( $tipo ) . '"><p><strong>' . esc_html( (string) ( $datos['cabecera'] ?? 'Prueba de conexión con ' . $datos['titulo'] ) ) . '</strong></p><ul>';
		foreach ( $resultado as $linea ) {
			echo '<li>' . esc_html( ( $iconos[ $linea[0] ] ?? '' ) . ' ' . $linea[1] ) . '</li>';
		}
		echo '</ul></div>';
	}

	public static function registrar_bloques( $registro ): void {
		require_once IDITAL_PAGOS_DIR . 'includes/class-blocks.php';
		foreach ( self::IDS as $id ) {
			$registro->register( new Idital_Pagos_Blocks( $id ) );
		}
	}
}
