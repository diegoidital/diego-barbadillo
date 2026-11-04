<?php
/**
 * Cliente de la API de TropiPay (paymentcards = links de pago).
 *
 * Según la documentación oficial (https://doc.tropipay.com):
 *  - Token:  POST /api/v3/access/token  (client_credentials)
 *  - Link:   POST /api/v3/paymentcards  (importes en céntimos; devuelve shortUrl)
 *  - Aviso:  POST a urlNotification con status OK/KO y data.signaturev3 =
 *            sha256( bankOrderCode + apiKey + sha1(apiSecret) + originalCurrencyAmount )
 *
 * @package IditalPagos
 */

defined( 'ABSPATH' ) || exit;

final class Idital_Pagos_Tropipay_Client {

	const DOMINIOS_LINK = array( 'tropipay.com', 'tropipay.me', 'tppay.me' );
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
			Idital_Pagos_Config::tropipay_base() . '/api/v3/access/token',
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
		// expires_in puede venir en segundos o como marca de tiempo: se acota a una hora como máximo.
		$expira   = (int) ( $respuesta['datos']['expires_in'] ?? 0 );
		$segundos = $expira > 1000000000 ? $expira - time() : $expira;
		$duracion = min( HOUR_IN_SECONDS, max( 60, $segundos - 120 ) );
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
			'expirationDate'  => gmdate( 'Y-m-d\\TH:i:s\\Z', time() + $dias_caducidad * DAY_IN_SECONDS ), // Caducidad del link.
			'lang'            => 'es',
			'urlSuccess'      => $pedido->get_checkout_order_received_url(),
			'urlFailed'       => $pedido->get_checkout_payment_url(),
			'urlNotification' => $url_notificacion,
			'favorite'        => false,
		);

		$respuesta = $this->llamar( 'POST', '/api/v3/paymentcards', $cuerpo );
		$datos     = $respuesta['datos'];
		if ( ! in_array( $respuesta['codigo'], array( 200, 201 ), true ) ) {
			throw new Idital_Pagos_Exception( 'TropiPay no ha creado el link (HTTP ' . $respuesta['codigo'] . ').' );
		}

		$url = (string) ( ! empty( $datos['shortUrl'] ) ? $datos['shortUrl'] : ( $datos['paymentUrl'] ?? '' ) );
		if ( ! Idital_Pagos_Config::url_permitida( $url, $this->dominios_permitidos() ) ) {
			throw new Idital_Pagos_Exception( 'Respuesta de TropiPay no válida (url inesperada).' );
		}
		if ( ! empty( $datos['reference'] ) && (string) $datos['reference'] !== $referencia ) {
			throw new Idital_Pagos_Exception( 'La referencia del link de TropiPay no coincide.' );
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
	 * Verifica la firma oficial (signaturev3) de la notificación de pago:
	 *   sha256( bankOrderCode + apiKey + sha1(apiSecret) + originalCurrencyAmount )
	 * Solo TropiPay y nosotros conocemos apiSecret. Si no coincide, el pedido NO se marca como pagado.
	 */
	public static function firma_valida( array $datos ): bool {
		if ( ! Idital_Pagos_Config::tropipay_verificar_firma() ) {
			return true;
		}
		$firma = '';
		foreach ( $datos as $clave => $valor ) {
			if ( is_string( $clave ) && 'signaturev3' === strtolower( $clave ) && is_string( $valor ) ) {
				$firma = strtolower( trim( $valor ) );
			}
		}
		$codigo  = (string) ( $datos['bankOrderCode'] ?? '' );
		$importe = (string) ( $datos['originalCurrencyAmount'] ?? '' );
		if ( ! preg_match( '/^[a-f0-9]{64}$/', $firma ) || '' === $codigo || '' === $importe ) {
			return false;
		}
		$esperada = hash(
			'sha256',
			$codigo
			. Idital_Pagos_Config::get( 'IDITAL_TROPIPAY_CLIENT_ID' )
			. sha1( Idital_Pagos_Config::get( 'IDITAL_TROPIPAY_CLIENT_SECRET' ) )
			. $importe
		);
		return hash_equals( $esperada, $firma );
	}

	/**
	 * Diagnóstico de solo lectura: pide un token y muestra los permisos concedidos.
	 *
	 * @return array<int, array{0: string, 1: string}>
	 */
	public function diagnostico(): array {
		delete_transient( self::TRANSIENT );
		try {
			$respuesta = Idital_Pagos_Http::json(
				'POST',
				Idital_Pagos_Config::tropipay_base() . '/api/v3/access/token',
				array(),
				array(
					'grant_type'    => 'client_credentials',
					'client_id'     => Idital_Pagos_Config::get( 'IDITAL_TROPIPAY_CLIENT_ID' ),
					'client_secret' => Idital_Pagos_Config::get( 'IDITAL_TROPIPAY_CLIENT_SECRET' ),
				)
			);
		} catch ( Idital_Pagos_Exception $e ) {
			return array( array( 'error', 'No hay conexión con TropiPay: ' . $e->getMessage() ) );
		}
		if ( 200 !== $respuesta['codigo'] || empty( $respuesta['datos']['access_token'] ) ) {
			return array( array( 'error', 'TropiPay rechaza las credenciales (HTTP ' . $respuesta['codigo'] . '). Revisa Client ID, Client Secret, entorno (' . ( Idital_Pagos_Config::tropipay_sandbox() ? 'sandbox' : 'producción' ) . ') y la restricción por IP de la credencial.' ) );
		}
		$permisos  = preg_split( '/\s+/', trim( (string) ( $respuesta['datos']['scope'] ?? '' ) ) );
		$resultado = array( array( 'ok', 'Credenciales válidas (' . ( Idital_Pagos_Config::tropipay_sandbox() ? 'sandbox' : 'producción' ) . ').' ) );
		$resultado[] = array( 'ok', 'Permisos de la credencial: ' . ( implode( ', ', array_map( 'sanitize_text_field', array_filter( $permisos ) ) ) ?: 'no indicados' ) );
		if ( count( array_filter( $permisos ) ) > 6 ) {
			$resultado[] = array( 'aviso', 'La credencial tiene muchos permisos. Por seguridad, deja solo los necesarios para crear links de pago y recibir cobros.' );
		}
		return $resultado;
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
