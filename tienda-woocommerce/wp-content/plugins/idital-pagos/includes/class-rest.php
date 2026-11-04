<?php
/**
 * Endpoints públicos: webhooks de las pasarelas y consulta de estado del pago.
 *
 * Son públicos por necesidad (los llama Qonto/TropiPay o el navegador del
 * cliente), así que NINGUNO confía en lo que recibe:
 *  - Qonto: el webhook solo sirve de "aviso"; el pago se comprueba preguntando a la API de Qonto.
 *  - TropiPay: URL con firma HMAC única por pedido + firma de TropiPay + referencia + importe.
 *  - Estado: exige la clave secreta del pedido y solo devuelve "pagado sí/no".
 *  - Todos con límite de peticiones por IP y tamaño máximo de cuerpo.
 *
 * @package IditalPagos
 */

defined( 'ABSPATH' ) || exit;

final class Idital_Pagos_Rest {

	const NS         = 'idital-pagos/v1';
	const MAX_CUERPO = 65536;

	public static function registrar(): void {
		register_rest_route(
			self::NS,
			'/qonto/webhook',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'qonto_webhook' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NS,
			'/tropipay/notificacion/(?P<pedido>\d{1,12})/(?P<firma>[a-f0-9]{64})',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'tropipay_notificacion' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NS,
			'/estado',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'estado' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'pedido' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'clave'  => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	private static function respuesta( array $datos, int $codigo = 200 ): WP_REST_Response {
		do_action( 'litespeed_control_set_nocache', 'idital-pagos' );
		$r = new WP_REST_Response( $datos, $codigo );
		$r->header( 'Cache-Control', 'no-store, private' );
		$r->header( 'X-Robots-Tag', 'noindex' );
		return $r;
	}

	/* --------------------------------------------------------------- Qonto */

	public static function qonto_webhook( WP_REST_Request $peticion ): WP_REST_Response {
		if ( ! Idital_Pagos_Limite::permitir( 'qonto_webhook', 120, 60 ) ) {
			return self::respuesta( array( 'ok' => false ), 429 );
		}

		// Capa extra: token secreto en la URL del webhook.
		if ( ! hash_equals( Idital_Pagos_Config::qonto_webhook_token(), (string) $peticion->get_param( 'token' ) ) ) {
			Idital_Pagos_Log::aviso( 'Webhook de Qonto rechazado: token incorrecto', array( 'ip' => Idital_Pagos_Limite::ip() ) );
			return self::respuesta( array( 'ok' => false ), 401 );
		}

		$cuerpo = $peticion->get_body();
		if ( strlen( $cuerpo ) > self::MAX_CUERPO ) {
			return self::respuesta( array( 'ok' => false ), 413 );
		}
		$json = json_decode( $cuerpo, true );
		if ( ! is_array( $json ) ) {
			return self::respuesta( array( 'ok' => false ), 400 );
		}

		$verificados = 0;
		foreach ( array_slice( self::extraer_ids( $json ), 0, 5 ) as $id ) {
			$pedidos = wc_get_orders(
				array(
					'limit'      => 1,
					'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery
						array(
							'key'   => '_idital_qonto_link_id',
							'value' => $id,
						),
					),
				)
			);
			if ( $pedidos ) {
				Idital_Pagos_Gateway_Qonto::verificar( $pedidos[0], 'webhook de Qonto' );
				++$verificados;
			}
		}

		Idital_Pagos_Log::info( 'Webhook de Qonto recibido', array( 'pedidos_comprobados' => $verificados ) );
		return self::respuesta( array( 'ok' => true ) );
	}

	/**
	 * Busca identificadores en el cuerpo del webhook (el formato exacto da igual:
	 * solo se usan para saber qué pedido hay que volver a comprobar contra la API).
	 */
	private static function extraer_ids( array $datos, int $nivel = 0 ): array {
		$ids = array();
		if ( $nivel > 5 ) {
			return $ids;
		}
		foreach ( $datos as $clave => $valor ) {
			if ( is_array( $valor ) ) {
				$ids = array_merge( $ids, self::extraer_ids( $valor, $nivel + 1 ) );
			} elseif ( is_string( $valor ) && in_array( $clave, array( 'id', 'payment_link_id', 'resource_id', 'object_id' ), true ) && preg_match( '/^[A-Za-z0-9-]{8,64}$/', $valor ) ) {
				$ids[] = $valor;
			}
		}
		return array_values( array_unique( $ids ) );
	}

	/* ------------------------------------------------------------ TropiPay */

	public static function tropipay_notificacion( WP_REST_Request $peticion ): WP_REST_Response {
		if ( ! Idital_Pagos_Limite::permitir( 'tropipay_notif', 60, 60 ) ) {
			return self::respuesta( array( 'ok' => false ), 429 );
		}

		$pedido = wc_get_order( absint( $peticion['pedido'] ) );
		if ( ! $pedido || ! hash_equals( Idital_Pagos_Gateway_Tropipay::token_notificacion( $pedido ), (string) $peticion['firma'] ) ) {
			Idital_Pagos_Log::aviso( 'Notificación de TropiPay rechazada: URL no válida', array( 'ip' => Idital_Pagos_Limite::ip() ) );
			return self::respuesta( array( 'ok' => false ), 404 );
		}

		$cuerpo = $peticion->get_body();
		if ( strlen( $cuerpo ) > self::MAX_CUERPO ) {
			return self::respuesta( array( 'ok' => false ), 413 );
		}
		$json = json_decode( $cuerpo, true );
		if ( ! is_array( $json ) ) {
			return self::respuesta( array( 'ok' => false ), 400 );
		}
		$datos  = isset( $json['data'] ) && is_array( $json['data'] ) ? $json['data'] : $json;
		$estado = strtoupper( (string) ( $json['status'] ?? $datos['status'] ?? '' ) );

		// La referencia tiene que ser una de las generadas para ESTE pedido.
		$referencia = (string) ( $datos['reference'] ?? '' );
		$refs       = (array) $pedido->get_meta( '_idital_tropipay_refs' );
		if ( '' === $referencia || ! isset( $refs[ $referencia ] ) ) {
			Idital_Pagos_Log::aviso( 'Notificación de TropiPay con referencia desconocida', array( 'pedido' => $pedido->get_id() ) );
			return self::respuesta( array( 'ok' => false ), 400 );
		}

		if ( ! Idital_Pagos_Tropipay_Client::firma_valida( $datos ) ) {
			Idital_Pagos_Log::aviso( 'Notificación de TropiPay con firma no válida', array( 'pedido' => $pedido->get_id() ) );
			$pedido->add_order_note( '⚠️ Se ha recibido una notificación de TropiPay con firma no válida. NO se ha marcado como pagado: comprueba el cobro en el panel de TropiPay.' );
			return self::respuesta( array( 'ok' => false ), 401 );
		}

		if ( 'OK' !== $estado ) {
			$pedido->add_order_note( sprintf( 'TropiPay ha notificado un pago no completado (estado: %s).', sanitize_text_field( $estado ) ) );
			return self::respuesta( array( 'ok' => true ) );
		}

		// El cobro tiene que corresponder al link que creamos para esta referencia.
		$link_id = (string) ( $refs[ $referencia ]['id'] ?? '' );
		$card_id = (string) ( $datos['paymentcardId'] ?? $datos['paymentcard']['id'] ?? '' );
		if ( '' !== $link_id && '' !== $card_id && ! hash_equals( $link_id, $card_id ) ) {
			Idital_Pagos_Log::aviso( 'Notificación de TropiPay de un link distinto al del pedido', array( 'pedido' => $pedido->get_id() ) );
			return self::respuesta( array( 'ok' => false ), 400 );
		}

		$importe_txt = (string) ( $datos['originalCurrencyAmount'] ?? '' );
		$importe     = preg_match( '/^\d+$/', $importe_txt ) ? (int) $importe_txt : -1; // Céntimos.
		$moneda      = (string) ( $datos['currency'] ?? $datos['originalCurrency'] ?? '' );

		Idital_Pagos_Confirmacion::confirmar(
			$pedido,
			'idital_tropipay',
			(string) ( $datos['bankOrderCode'] ?? $referencia ),
			$importe,
			$moneda,
			'notificación de TropiPay'
		);
		return self::respuesta( array( 'ok' => true ) );
	}

	/* -------------------------------------------------------------- Estado */

	public static function estado( WP_REST_Request $peticion ): WP_REST_Response {
		if ( ! Idital_Pagos_Limite::permitir( 'estado', 40, 60 ) ) {
			return self::respuesta( array( 'pagado' => false ), 429 );
		}
		$pedido = wc_get_order( (int) $peticion['pedido'] );
		if ( ! $pedido || ! hash_equals( $pedido->get_order_key(), (string) $peticion['clave'] ) ) {
			return self::respuesta( array( 'pagado' => false ), 404 );
		}

		// Si el webhook no ha llegado aún, se pregunta a Qonto (como mucho cada 15 s por pedido).
		if ( ! $pedido->is_paid() && '' !== (string) $pedido->get_meta( '_idital_qonto_link_id' ) ) {
			$marca = 'idital_qonto_check_' . $pedido->get_id();
			if ( false === get_transient( $marca ) ) {
				set_transient( $marca, 1, 15 );
				Idital_Pagos_Gateway_Qonto::verificar( $pedido, 'comprobación desde la página del pedido' );
				$pedido = wc_get_order( $pedido->get_id() );
			}
		}

		return self::respuesta( array( 'pagado' => $pedido->is_paid() ) );
	}
}
