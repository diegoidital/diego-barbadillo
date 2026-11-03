<?php
/**
 * Cliente de la API de TropiPay (paymentcards = links de pago).
 *
 * PENDIENTE DE CONFIRMAR con la documentación oficial / sandbox al recibir credenciales:
 *  - URL base y endpoint del token.
 *  - Que los importes van en céntimos.
 *  - Formato exacto de la notificación y de su firma (ver firma_valida()).
 *
 * @package IditalPagos
 */

defined( 'ABSPATH' ) || exit;

final class Idital_Pagos_Tropipay_Client {

	const DOMINIOS_LINK = array( 'tropipay.com', 'tropipay.me', 'tropipay-dev.herokuapp.com' );
	const TRANSIENT     = 'idital_tpp_token';

	/**
	 * Token OAuth (client_credentials), cacheado cifrado.
	 *
	 * @throws Idital_Pagos_Exception Si TropiPay no devuelve token.
	 */
	private function token(): string {
		$cache = get_transient( self::TRANSIENT );
		if ( is_string( $cache ) ) {
			$token = self::descifrar( $cache );
			if ( '' !== $token ) {
				return $token;
			}
		}

		$respuesta = Idital_Pagos_Http::json(
			'POST',
			Idital_Pagos_Config::tropipay_base() . '/api/v2/access/token',
			array(),
			array(
				'grant_type'    => 'client_credentials',
				'client_id'     => Idital_Pagos_Config::get( 'IDITAL_TROPIPAY_CLIENT_ID' ),
				'client_secret' => Idital_Pagos_Config::get( 'IDITAL_TROPIPAY_CLIENT_SECRET' ),
			)
		);
		$token = (string) ( $respuesta['datos']['access_token'] ?? '' );
		if ( 200 !== $respuesta['codigo'] || '' === $token ) {
			throw new Idital_Pagos_Exception( 'TropiPay no ha devuelto un token (HTTP ' . $respuesta['codigo'] . ').' );
		}
		$duracion = max( 60, (int) ( $respuesta['datos']['expires_in'] ?? 3600 ) - 120 );
		set_transient( self::TRANSIENT, self::cifrar( $token ), $duracion );
		return $token;
	}

	/**
	 * Crea un link de pago de un solo uso.
	 *
	 * @return array{id:string,url:string,importe:int,moneda:string,referencia:string}
	 * @throws Idital_Pagos_Exception Si falla.
	 */
	public function crear_link( WC_Order $pedido, string $referencia, string $url_notificacion, int $dias_caducidad ): array {
		$moneda  = strtoupper( $pedido->get_currency() );
		$importe = Idital_Pagos_Confirmacion::centimos( $pedido->get_total() );
		$tienda  = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

		$cuerpo = array(
			'reference'       => $referencia,
			'concept'         => mb_substr( sprintf( 'Pedido nº %s', $pedido->get_order_number() ), 0, 100 ),
			'description'     => mb_substr( sprintf( 'Pedido nº %1$s en %2$s', $pedido->get_order_number(), $tienda ), 0, 250 ),
			'amount'          => $importe, // En céntimos.
			'currency'        => $moneda,
			'singleUse'       => true,
			'reasonId'        => 4,
			'expirationDays'  => $dias_caducidad,
			'lang'            => 'es',
			'urlSuccess'      => $pedido->get_checkout_order_received_url(),
			'urlFailed'       => $pedido->get_checkout_payment_url(),
			'urlNotification' => $url_notificacion,
			'serviceDate'     => gmdate( 'Y-m-d' ),
			'directPayment'   => true,
			'favorite'        => false,
		);

		$respuesta = $this->llamar( 'POST', '/api/v2/paymentcards', $cuerpo );
		$datos     = $respuesta['datos'];
		if ( ! in_array( $respuesta['codigo'], array( 200, 201 ), true ) ) {
			throw new Idital_Pagos_Exception( 'TropiPay no ha creado el link (HTTP ' . $respuesta['codigo'] . ').' );
		}

		$url = (string) ( $datos['paymentUrl'] ?? $datos['shortUrl'] ?? '' );
		if ( ! Idital_Pagos_Config::url_permitida( $url, $this->dominios_permitidos() ) ) {
			throw new Idital_Pagos_Exception( 'Respuesta de TropiPay no válida (url inesperada).' );
		}
		if ( isset( $datos['amount'] ) && (int) $datos['amount'] !== $importe ) {
			throw new Idital_Pagos_Exception( 'El importe del link de TropiPay no coincide con el del pedido.' );
		}

		return array(
			'id'         => substr( sanitize_text_field( (string) ( $datos['id'] ?? '' ) ), 0, 64 ),
			'url'        => $url,
			'importe'    => $importe,
			'moneda'     => $moneda,
			'referencia' => $referencia,
		);
	}

	/**
	 * Verifica la firma de la notificación de pago.
	 *
	 * Esquema usado por las integraciones conocidas de TropiPay:
	 *   sha256( bankOrderCode . client_id . client_secret . originalCurrencyAmount )
	 * CONFIRMAR en el sandbox. Si no coincidiera, el pedido NO se marca como pagado
	 * (queda una nota para revisión manual): el fallo es siempre "cerrado".
	 */
	public static function firma_valida( array $datos ): bool {
		if ( ! Idital_Pagos_Config::tropipay_verificar_firma() ) {
			return true;
		}
		$firma = (string) ( $datos['signature'] ?? '' );
		if ( '' === $firma ) {
			return false;
		}
		$esperada = hash(
			'sha256',
			(string) ( $datos['bankOrderCode'] ?? '' )
			. Idital_Pagos_Config::get( 'IDITAL_TROPIPAY_CLIENT_ID' )
			. Idital_Pagos_Config::get( 'IDITAL_TROPIPAY_CLIENT_SECRET' )
			. (string) ( $datos['originalCurrencyAmount'] ?? '' )
		);
		return hash_equals( $esperada, strtolower( $firma ) );
	}

	private function dominios_permitidos(): array {
		$dominios = self::DOMINIOS_LINK;
		$host     = wp_parse_url( Idital_Pagos_Config::tropipay_base(), PHP_URL_HOST );
		if ( $host ) {
			$dominios[] = $host;
		}
		return $dominios;
	}

	/**
	 * Llamada autenticada; si el token ha caducado lo renueva una vez.
	 */
	private function llamar( string $metodo, string $ruta, ?array $cuerpo = null ): array {
		for ( $intento = 0; $intento < 2; $intento++ ) {
			$respuesta = Idital_Pagos_Http::json(
				$metodo,
				Idital_Pagos_Config::tropipay_base() . $ruta,
				array( 'Authorization' => 'Bearer ' . $this->token() ),
				$cuerpo
			);
			if ( 401 !== $respuesta['codigo'] ) {
				return $respuesta;
			}
			delete_transient( self::TRANSIENT );
		}
		return $respuesta;
	}

	/* ------------- Cifrado del token en caché (libsodium + clave derivada de las sales). */

	private static function clave(): string {
		return sodium_crypto_generichash( Idital_Pagos_Config::clave_hmac() . '|tpp-token', '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
	}

	private static function cifrar( string $texto ): string {
		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		return base64_encode( $nonce . sodium_crypto_secretbox( $texto, $nonce, self::clave() ) ); // phpcs:ignore
	}

	private static function descifrar( string $cifrado ): string {
		$bruto = base64_decode( $cifrado, true ); // phpcs:ignore
		if ( false === $bruto || strlen( $bruto ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return '';
		}
		$texto = sodium_crypto_secretbox_open(
			substr( $bruto, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ),
			substr( $bruto, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ),
			self::clave()
		);
		return false === $texto ? '' : $texto;
	}
}
