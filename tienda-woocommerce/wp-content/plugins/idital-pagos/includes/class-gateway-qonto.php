<?php
/**
 * Pasarela "Pagar vía Qonto".
 *
 * Qonto no permite indicar una URL de vuelta en sus links, así que tras generar
 * el link se lleva al cliente a la página del pedido, donde ve el botón de pago
 * (y, si se configura, los datos para transferencia) y la página se actualiza
 * sola al confirmarse el cobro.
 *
 * @package IditalPagos
 */

defined( 'ABSPATH' ) || exit;

final class Idital_Pagos_Gateway_Qonto extends Idital_Pagos_Gateway {

	const METODOS = array(
		'credit_card' => 'Tarjeta',
		'apple_pay'   => 'Apple Pay',
		'paypal'      => 'PayPal',
		'ideal'       => 'iDEAL',
	);

	public function __construct() {
		$this->id                 = 'idital_qonto';
		$this->corto              = 'qonto';
		$this->method_title       = 'Qonto';
		$this->method_description = 'Genera automáticamente un link de pago de Qonto por el total del pedido. Las credenciales se definen en wp-config.php, nunca aquí.';
		$this->monedas            = array( 'EUR' );
		parent::__construct();
	}

	public function configurado(): bool {
		return Idital_Pagos_Config::qonto_configurado();
	}

	public function init_form_fields() {
		$webhook = add_query_arg( 'token', Idital_Pagos_Config::qonto_webhook_token(), rest_url( 'idital-pagos/v1/qonto/webhook' ) );
		$extra   = array( 'URL del webhook para Qonto: <code>' . esc_html( $webhook ) . '</code>' );
		if ( $this->configurado() ) {
			$extra[] = '<a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=idital_probar_qonto' ), 'idital_probar_qonto' ) ) . '">Probar conexión con Qonto</a> <span class="description">(solo lectura: no crea ni cobra nada)</span>';
		}

		$this->form_fields = array(
			'estado'      => $this->campo_estado(
				Idital_Pagos_Config::qonto_sandbox() ? 'Sandbox (pruebas)' : 'PRODUCCIÓN',
				$extra
			),
			'enabled'     => array(
				'title'   => __( 'Activar', 'idital-pagos' ),
				'type'    => 'checkbox',
				'label'   => __( 'Mostrar "Pagar vía Qonto" a los clientes', 'idital-pagos' ),
				'default' => 'no',
			),
			'title'       => array(
				'title'   => __( 'Texto del botón', 'idital-pagos' ),
				'type'    => 'text',
				'default' => 'Pagar vía Qonto (Transferencia/Tarjeta Europea)',
			),
			'description' => array(
				'title'   => __( 'Descripción', 'idital-pagos' ),
				'type'    => 'textarea',
				'default' => 'Paga con tarjeta de forma segura a través de Qonto o, si lo prefieres, por transferencia a nuestra cuenta Qonto.',
			),
			'metodos'     => array(
				'title'       => __( 'Métodos en el link', 'idital-pagos' ),
				'type'        => 'multiselect',
				'class'       => 'wc-enhanced-select',
				'options'     => self::METODOS,
				'default'     => array( 'credit_card', 'apple_pay' ),
				'description' => __( 'Solo funcionarán los que estén activos en la cuenta de Mollie conectada a Qonto.', 'idital-pagos' ),
			),
			'transferencia' => array(
				'title'       => __( 'Datos para transferencia (opcional)', 'idital-pagos' ),
				'type'        => 'title',
				'description' => __( 'Los links de Qonto no admiten transferencia. Si rellenas estos datos, se mostrarán junto al botón de pago para quien prefiera transferir. Esos pedidos se confirman a mano al recibir el dinero.', 'idital-pagos' ),
			),
			'titular'     => array(
				'title'   => __( 'Titular', 'idital-pagos' ),
				'type'    => 'text',
				'default' => '',
			),
			'iban'        => array(
				'title'   => __( 'IBAN', 'idital-pagos' ),
				'type'    => 'text',
				'default' => '',
			),
			'bic'         => array(
				'title'   => __( 'BIC', 'idital-pagos' ),
				'type'    => 'text',
				'default' => '',
			),
		);
	}

	/** Valida el IBAN (formato y dígitos de control) antes de guardarlo. */
	public function validate_iban_field( $key, $value ) {
		$iban = strtoupper( preg_replace( '/\s+/', '', (string) $value ) );
		if ( '' === $iban ) {
			return '';
		}
		if ( ! self::iban_valido( $iban ) ) {
			WC_Admin_Settings::add_error( __( 'El IBAN no es válido; no se ha guardado.', 'idital-pagos' ) );
			return $this->get_option( 'iban' );
		}
		return $iban;
	}

	public function validate_bic_field( $key, $value ) {
		$bic = strtoupper( preg_replace( '/\s+/', '', (string) $value ) );
		if ( '' !== $bic && ! preg_match( '/^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$/', $bic ) ) {
			WC_Admin_Settings::add_error( __( 'El BIC no es válido; no se ha guardado.', 'idital-pagos' ) );
			return $this->get_option( 'bic' );
		}
		return $bic;
	}

	public static function iban_valido( string $iban ): bool {
		if ( ! preg_match( '/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/', $iban ) ) {
			return false;
		}
		$reordenado = substr( $iban, 4 ) . substr( $iban, 0, 4 );
		$numerico   = '';
		foreach ( str_split( $reordenado ) as $c ) {
			$numerico .= ctype_alpha( $c ) ? (string) ( ord( $c ) - 55 ) : $c;
		}
		$resto = 0;
		foreach ( str_split( $numerico, 7 ) as $bloque ) {
			$resto = (int) ( $resto . $bloque ) % 97;
		}
		return 1 === $resto;
	}

	private function metodos(): array {
		$elegidos = (array) $this->get_option( 'metodos', array( 'credit_card' ) );
		$validos  = array_values( array_intersect( $elegidos, array_keys( self::METODOS ) ) );
		return $validos ? $validos : array( 'credit_card' );
	}

	public function datos_transferencia(): array {
		$iban = (string) $this->get_option( 'iban' );
		if ( '' === $iban ) {
			return array();
		}
		return array(
			'titular' => (string) $this->get_option( 'titular' ),
			'iban'    => trim( chunk_split( $iban, 4, ' ' ) ),
			'bic'     => (string) $this->get_option( 'bic' ),
		);
	}

	protected function crear_link( WC_Order $pedido ): string {
		$link = ( new Idital_Pagos_Qonto_Client() )->crear_link( $pedido, $this->metodos() );

		Idital_Pagos_Registro::asociar( 'qonto_link', $link['id'], $pedido->get_id() );
		$historial   = (array) $this->meta( $pedido, 'links' );
		$historial[] = $link['id'];
		$this->guardar_meta( $pedido, 'links', array_slice( array_values( array_unique( $historial ) ), -20 ) );
		$this->guardar_meta( $pedido, 'link_id', $link['id'] );
		$this->guardar_meta( $pedido, 'link_url', $link['url'] );
		$this->guardar_meta( $pedido, 'importe', $link['importe'] );
		$this->guardar_meta( $pedido, 'moneda', $link['moneda'] );
		$this->guardar_meta( $pedido, 'creado', time() );
		return $link['url'];
	}

	protected function link_vigente( WC_Order $pedido ): string {
		$id  = (string) $this->meta( $pedido, 'link_id' );
		$url = (string) $this->meta( $pedido, 'link_url' );
		if ( '' === $id || '' === $url ) {
			return '';
		}

		// Si el total del pedido ha cambiado, el link antiguo se desactiva.
		$mismo_importe = (int) $this->meta( $pedido, 'importe' ) === Idital_Pagos_Confirmacion::centimos( $pedido->get_total() )
			&& (string) $this->meta( $pedido, 'moneda' ) === strtoupper( $pedido->get_currency() );
		if ( ! $mismo_importe ) {
			$this->desactivar_o_fallar( $pedido, $id );
			return '';
		}

		// Se pregunta a Qonto por el estado real del link. Si no se puede saber, se aborta
		// (antes que arriesgarse a crear un segundo link cobrable).
		$link   = ( new Idital_Pagos_Qonto_Client() )->obtener_link( $id );
		$estado = (string) ( $link['status'] ?? '' );
		if ( in_array( $estado, array( 'open', 'processing' ), true ) ) {
			return $url;
		}
		if ( 'paid' === $estado ) {
			self::verificar( $pedido, 'al reintentar el pago' );
			return $url;
		}
		$this->borrar_meta( $pedido, 'link_id' );
		$this->borrar_meta( $pedido, 'link_url' );
		return ''; // expired / canceled: se crea uno nuevo.
	}

	public function anular_link( WC_Order $pedido ): void {
		$id = (string) $this->meta( $pedido, 'link_id' );
		if ( '' === $id || $pedido->is_paid() ) {
			return;
		}
		$this->desactivar_o_fallar( $pedido, $id );
		$pedido->add_order_note( 'Link de Qonto desactivado (el cliente ha elegido otro método de pago).' );
	}

	/**
	 * @throws Idital_Pagos_Exception Si Qonto no confirma la desactivación.
	 */
	private function desactivar_o_fallar( WC_Order $pedido, string $id ): void {
		$cliente = new Idital_Pagos_Qonto_Client();
		if ( ! $cliente->desactivar_link( $id ) ) {
			// Puede que ya estuviera pagado/caducado: se comprueba antes de rendirse.
			$link = $cliente->obtener_link( $id );
			if ( 'paid' === ( $link['status'] ?? '' ) ) {
				// Ya estaba pagado: se confirma y no se toca más este objeto (está desactualizado).
				self::verificar( $pedido, 'al desactivar el link' );
				return;
			}
			if ( ! in_array( $link['status'] ?? '', array( 'expired', 'canceled' ), true ) ) {
				throw new Idital_Pagos_Exception( 'No se ha podido desactivar el link anterior de Qonto.' );
			}
		}
		$this->borrar_meta( $pedido, 'link_id' );
		$this->borrar_meta( $pedido, 'link_url' );
		$pedido->save();
	}

	protected function url_redireccion( WC_Order $pedido, string $url_link ): string {
		return $pedido->get_checkout_order_received_url();
	}

	/**
	 * Consulta a Qonto el estado del link del pedido y, si está pagado, lo confirma.
	 * Nunca se fía del contenido de un webhook: siempre pregunta a la API.
	 */
	public static function verificar( WC_Order $pedido, string $origen ): bool {
		if ( ! Idital_Pagos_Config::qonto_configurado() ) {
			return false;
		}
		// Se comprueban todos los links creados para el pedido (el actual primero).
		$ids = array_values( array_unique( array_filter( array_merge( array( (string) $pedido->get_meta( '_idital_qonto_link_id' ) ), array_reverse( (array) $pedido->get_meta( '_idital_qonto_links' ) ) ) ) ) );
		$ok  = false;
		foreach ( array_slice( $ids, 0, 5 ) as $id ) {
			$ok = self::verificar_link( $pedido, (string) $id, $origen ) || $ok;
		}
		return $ok;
	}

	private static function verificar_link( WC_Order $pedido, string $id, string $origen ): bool {
		try {
			$link = ( new Idital_Pagos_Qonto_Client() )->obtener_link( $id );
		} catch ( Idital_Pagos_Exception $e ) {
			Idital_Pagos_Log::aviso(
				'No se pudo verificar el link de Qonto',
				array(
					'pedido' => $pedido->get_id(),
					'error'  => $e->getMessage(),
				)
			);
			return false;
		}

		if ( ( $link['id'] ?? '' ) !== $id || 'paid' !== ( $link['status'] ?? '' ) ) {
			return false;
		}

		return Idital_Pagos_Confirmacion::confirmar(
			$pedido,
			'idital_qonto',
			$id,
			Idital_Pagos_Confirmacion::centimos( $link['amount']['value'] ?? '' ),
			(string) ( $link['amount']['currency'] ?? '' ),
			$origen
		);
	}
}
