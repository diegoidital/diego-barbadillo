<?php
/**
 * Pasarela "Pagar vía TropiPay".
 *
 * @package IditalPagos
 */

defined( 'ABSPATH' ) || exit;

final class Idital_Pagos_Gateway_Tropipay extends Idital_Pagos_Gateway {

	public function __construct() {
		$this->id                 = 'idital_tropipay';
		$this->corto              = 'tropipay';
		$this->method_title       = 'TropiPay';
		$this->method_description = 'Genera automáticamente un link de pago de TropiPay por el total del pedido. Las credenciales se definen en wp-config.php, nunca aquí.';
		$this->monedas            = array( 'EUR', 'USD' );
		parent::__construct();
	}

	public function configurado(): bool {
		return Idital_Pagos_Config::tropipay_configurado();
	}

	public function init_form_fields() {
		$this->form_fields = array(
			'estado'      => $this->campo_estado(
				Idital_Pagos_Config::tropipay_sandbox() ? 'Sandbox (pruebas)' : 'PRODUCCIÓN',
				array_merge(
					array( 'Las notificaciones de pago llegan a <code>' . esc_html( rest_url( 'idital-pagos/v1/tropipay/notificacion/' ) ) . '…</code> con una firma única por pedido (no hay que configurar nada en TropiPay).' ),
					$this->configurado() ? array( '<a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=idital_probar_tropipay' ), 'idital_probar_tropipay' ) ) . '">Probar conexión con TropiPay</a> <span class="description">(solo lectura: no crea ni cobra nada)</span>' ) : array()
				)
			),
			'enabled'     => array(
				'title'   => __( 'Activar', 'idital-pagos' ),
				'type'    => 'checkbox',
				'label'   => __( 'Mostrar "Pagar vía TropiPay" a los clientes', 'idital-pagos' ),
				'default' => 'no',
			),
			'title'       => array(
				'title'   => __( 'Texto del botón', 'idital-pagos' ),
				'type'    => 'text',
				'default' => 'Pagar vía TropiPay (Fondos TropiPay/Tarjetas Internacionales)',
			),
			'description' => array(
				'title'   => __( 'Descripción', 'idital-pagos' ),
				'type'    => 'textarea',
				'default' => 'Paga con tu saldo TropiPay o con tarjeta internacional. Te llevaremos a la página segura de TropiPay.',
			),
			'caducidad'   => array(
				'title'             => __( 'Caducidad del link (días)', 'idital-pagos' ),
				'type'              => 'number',
				'default'           => 1,
				'custom_attributes' => array(
					'min'  => 1,
					'max'  => 7,
					'step' => 1,
				),
			),
		);
	}

	public function validate_caducidad_field( $key, $value ) {
		return (string) min( 7, max( 1, absint( $value ) ) );
	}

	private function dias_caducidad(): int {
		return min( 7, max( 1, absint( $this->get_option( 'caducidad', 1 ) ) ) );
	}

	/** Firma secreta incluida en la URL de notificación de cada pedido. */
	public static function token_notificacion( WC_Order $pedido ): string {
		return hash_hmac( 'sha256', 'tropipay|' . $pedido->get_id() . '|' . $pedido->get_order_key(), Idital_Pagos_Config::clave_hmac() );
	}

	protected function crear_link( WC_Order $pedido ): string {
		$referencia = 'P' . $pedido->get_id() . '-' . strtoupper( wp_generate_password( 8, false, false ) );
		$url_notif  = rest_url( 'idital-pagos/v1/tropipay/notificacion/' . $pedido->get_id() . '/' . self::token_notificacion( $pedido ) );
		$dias       = $this->dias_caducidad();

		$link = ( new Idital_Pagos_Tropipay_Client() )->crear_link( $pedido, $referencia, $url_notif, $dias );

		// Historial de referencias: si llega el pago de un link antiguo, se reconoce igualmente.
		$refs                = (array) $this->meta( $pedido, 'refs' );
		$refs[ $referencia ] = array(
			'importe' => $link['importe'],
			'moneda'  => $link['moneda'],
			'creado'  => time(),
			'id'      => $link['id'],
		);
		$refs = array_slice( $refs, -20, null, true ); // Historial acotado.
		$this->guardar_meta( $pedido, 'refs', $refs );
		$this->guardar_meta( $pedido, 'link_url', $link['url'] );
		$this->guardar_meta( $pedido, 'importe', $link['importe'] );
		$this->guardar_meta( $pedido, 'moneda', $link['moneda'] );
		$this->guardar_meta( $pedido, 'caduca', time() + $dias * DAY_IN_SECONDS - HOUR_IN_SECONDS );
		return $link['url'];
	}

	protected function link_vigente( WC_Order $pedido ): string {
		$url = (string) $this->meta( $pedido, 'link_url' );
		if ( '' === $url ) {
			return '';
		}
		$vigente = (int) $this->meta( $pedido, 'caduca' ) > time()
			&& (int) $this->meta( $pedido, 'importe' ) === Idital_Pagos_Confirmacion::centimos( $pedido->get_total() )
			&& (string) $this->meta( $pedido, 'moneda' ) === strtoupper( $pedido->get_currency() );
		if ( ! $vigente ) {
			$this->borrar_meta( $pedido, 'link_url' );
			return '';
		}
		return $url;
	}

	public function anular_link( WC_Order $pedido ): void {
		if ( '' === (string) $this->meta( $pedido, 'link_url' ) ) {
			return;
		}
		// El link deja de ofrecerse al cliente. Si aun así se pagara, la referencia sigue
		// registrada y el sistema avisará del posible pago duplicado.
		$this->borrar_meta( $pedido, 'link_url' );
		$pedido->add_order_note( 'Link de TropiPay retirado (el cliente ha elegido otro método de pago).' );
	}

	protected function url_redireccion( WC_Order $pedido, string $url_link ): string {
		return $url_link; // TropiPay sí devuelve al cliente a la tienda (urlSuccess).
	}
}
