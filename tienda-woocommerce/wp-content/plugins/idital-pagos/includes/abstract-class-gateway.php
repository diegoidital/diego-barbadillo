<?php
/**
 * Base común de las dos pasarelas.
 *
 * @package IditalPagos
 */

defined( 'ABSPATH' ) || exit;

abstract class Idital_Pagos_Gateway extends WC_Payment_Gateway {

	/** Prefijo corto para los metadatos del pedido (qonto | tropipay). */
	protected string $corto = '';

	/** Monedas admitidas. */
	protected array $monedas = array( 'EUR' );

	public function __construct() {
		$this->has_fields = false;
		$this->supports   = array( 'products' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/** ¿Están definidas las credenciales en wp-config.php? */
	abstract public function configurado(): bool;

	/** Crea el link en la pasarela, lo guarda en el pedido y devuelve la URL. */
	abstract protected function crear_link( WC_Order $pedido ): string;

	/** URL de un link ya creado y todavía válido para este pedido, o '' si no lo hay. */
	abstract protected function link_vigente( WC_Order $pedido ): string;

	/** Invalida el link de esta pasarela (se llama cuando el cliente elige la otra). */
	abstract public function anular_link( WC_Order $pedido ): void;

	/** A dónde se envía al cliente tras generar el link. */
	abstract protected function url_redireccion( WC_Order $pedido, string $url_link ): string;

	public function meta( WC_Order $pedido, string $campo ) {
		return $pedido->get_meta( '_idital_' . $this->corto . '_' . $campo );
	}

	protected function guardar_meta( WC_Order $pedido, string $campo, $valor ): void {
		$pedido->update_meta_data( '_idital_' . $this->corto . '_' . $campo, $valor );
	}

	protected function borrar_meta( WC_Order $pedido, string $campo ): void {
		$pedido->delete_meta_data( '_idital_' . $this->corto . '_' . $campo );
	}

	public function is_available() {
		if ( ! parent::is_available() || ! $this->configurado() ) {
			return false;
		}
		// Modo pruebas en una web real: solo la ven los gestores de la tienda (para probar);
		// los clientes nunca pueden pagar con tarjetas de prueba.
		if ( Idital_Pagos_Config::sandbox( $this->id ) && ! Idital_Pagos_Config::entorno_local() && ! current_user_can( 'manage_woocommerce' ) ) {
			return false;
		}
		return in_array( $this->moneda_actual(), $this->monedas, true );
	}

	private function moneda_actual(): string {
		global $wp;
		if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-pay' ) && ! empty( $wp->query_vars['order-pay'] ) ) {
			$pedido = wc_get_order( absint( $wp->query_vars['order-pay'] ) );
			if ( $pedido ) {
				return strtoupper( $pedido->get_currency() );
			}
		}
		return strtoupper( get_woocommerce_currency() );
	}

	/**
	 * El cliente ha pulsado "Pagar": se genera (o reutiliza) el link en el servidor.
	 *
	 * @param int $order_id ID del pedido.
	 */
	public function process_payment( $order_id ) {
		$fallo  = array( 'result' => 'failure' );
		$pedido = wc_get_order( $order_id );

		if ( ! $pedido || ! $pedido->needs_payment() ) {
			wc_add_notice( __( 'Este pedido no está pendiente de pago.', 'idital-pagos' ), 'error' );
			return $fallo;
		}
		if ( ! in_array( strtoupper( $pedido->get_currency() ), $this->monedas, true ) ) {
			wc_add_notice( __( 'Este método de pago no admite la moneda del pedido.', 'idital-pagos' ), 'error' );
			return $fallo;
		}

		try {
			$r = $this->preparar_link( $pedido );
		} catch ( Throwable $e ) {
			if ( self::OCUPADO === $e->getCode() ) {
				wc_add_notice( __( 'Ya estamos generando tu enlace de pago. Espera unos segundos y vuelve a intentarlo.', 'idital-pagos' ), 'error' );
				return $fallo;
			}
			Idital_Pagos_Log::error(
				'No se pudo generar el link de pago',
				array(
					'pedido'   => $pedido->get_id(),
					'pasarela' => $this->id,
					'error'    => $e->getMessage(),
				)
			);
			// Al cliente nunca se le muestran detalles técnicos.
			wc_add_notice( __( 'No hemos podido generar el enlace de pago. Inténtalo de nuevo en unos minutos o elige otro método de pago.', 'idital-pagos' ), 'error' );
			return $fallo;
		}

		if ( $r['pagado'] ) {
			return array(
				'result'   => 'success',
				'redirect' => $pedido->get_checkout_order_received_url(),
			);
		}

		if ( function_exists( 'WC' ) && WC()->cart ) {
			WC()->cart->empty_cart();
		}

		return array(
			'result'   => 'success',
			'redirect' => $this->url_redireccion( $pedido, $r['url'] ),
		);
	}

	/** Código de excepción: otro proceso está generando el link del mismo pedido. */
	const OCUPADO = 409;

	/**
	 * Núcleo común (checkout del cliente y botón del panel): un solo link activo por pedido,
	 * reutiliza el vigente o crea uno nuevo, bajo bloqueo.
	 *
	 * @return array{url:string,pagado:bool}
	 * @throws Idital_Pagos_Exception Si no se puede generar.
	 */
	private function preparar_link( WC_Order $pedido ): array {
		$clave = 'link_' . $pedido->get_id();
		if ( ! Idital_Pagos_Bloqueo::adquirir( $clave, 180 ) ) {
			throw new Idital_Pagos_Exception( 'Ya se está generando un enlace para este pedido.', self::OCUPADO );
		}
		try {
			// Solo puede existir un link activo por pedido: se anula el de la otra pasarela.
			foreach ( Idital_Pagos_Plugin::pasarelas() as $otra ) {
				if ( $otra->id !== $this->id ) {
					$otra->anular_link( $pedido );
				}
			}

			$url = $this->link_vigente( $pedido );

			// La comprobación del link puede haber descubierto que ya está pagado.
			$fresco = wc_get_order( $pedido->get_id() );
			if ( $fresco && $fresco->is_paid() ) {
				return array(
					'url'    => '',
					'pagado' => true,
				);
			}

			if ( '' === $url ) {
				$url = $this->crear_link( $pedido );
				$pedido->add_order_note( sprintf( 'Link de pago de %s generado.', $this->method_title ) );
			}
			$pedido->save();
			return array(
				'url'    => $url,
				'pagado' => false,
			);
		} finally {
			Idital_Pagos_Bloqueo::liberar( $clave );
		}
	}

	/**
	 * Genera (o reutiliza) el link desde el panel, para enviárselo al cliente directamente.
	 *
	 * @throws Idital_Pagos_Exception Con un mensaje apto para el gestor.
	 */
	public function generar_link_gestion( WC_Order $pedido ): string {
		if ( ! $pedido->needs_payment() ) {
			throw new Idital_Pagos_Exception( 'El pedido no está pendiente de pago. Si es un presupuesto, usa antes "Enviar presupuesto al cliente para pagar" o cambia el estado a "Pendiente de pago".' );
		}
		if ( ! $this->configurado() ) {
			throw new Idital_Pagos_Exception( sprintf( '%s no está configurado o conectado.', $this->method_title ) );
		}
		if ( ! in_array( strtoupper( $pedido->get_currency() ), $this->monedas, true ) ) {
			throw new Idital_Pagos_Exception( sprintf( '%1$s no admite la moneda del pedido (%2$s).', $this->method_title, $pedido->get_currency() ) );
		}
		$pedido->set_payment_method( $this );
		$pedido->save();

		$r = $this->preparar_link( $pedido );
		if ( $r['pagado'] ) {
			throw new Idital_Pagos_Exception( 'El pedido ya está pagado.' );
		}
		return $r['url'];
	}

	/** Bloque informativo del panel: estado de credenciales y entorno. */
	protected function campo_estado( string $entorno, array $extra = array() ): array {
		$ok    = $this->configurado();
		$lineas = array(
			$ok ? '✅ Credenciales detectadas en wp-config.php.' : '❌ Faltan credenciales en wp-config.php: la pasarela no se mostrará a los clientes.',
			'Entorno: <strong>' . esc_html( $entorno ) . '</strong>',
		);
		return array(
			'title'       => __( 'Estado de la conexión', 'idital-pagos' ),
			'type'        => 'title',
			'description' => implode( '<br>', array_merge( $lineas, $extra ) ),
		);
	}
}
