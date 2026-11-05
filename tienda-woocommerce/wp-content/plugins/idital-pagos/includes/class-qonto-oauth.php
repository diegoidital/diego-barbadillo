<?php
/**
 * Conexión OAuth 2.0 con Qonto (obligatoria para crear links de pago).
 *
 * Flujo (https://docs.qonto.com/get-started/business-api/authentication/oauth/oauth-flow):
 *  1. Un gestor de la tienda pulsa "Conectar con Qonto" y autoriza en la web de Qonto.
 *  2. Qonto vuelve a /wp-json/idital-pagos/v1/qonto/oauth con un código de un solo uso.
 *  3. El código se canjea por un access token (1 h) y un refresh token (90 días, de un solo uso).
 *
 * Seguridad:
 *  - "state" aleatorio, de un solo uso, ligado al usuario que inició la conexión y válido 10 min.
 *  - Los tokens se guardan CIFRADOS (libsodium) con una clave que solo está en el fichero de
 *    configuración (fuera de la web): una copia de la base de datos no basta para usarlos.
 *  - La renovación se hace bajo bloqueo: los refresh tokens son de un solo uso y dos procesos
 *    renovando a la vez dejarían la conexión rota.
 *
 * @package IditalPagos
 */

defined( 'ABSPATH' ) || exit;

final class Idital_Pagos_Qonto_Oauth {

	const OPCION  = 'idital_qonto_oauth';
	const ALCANCE = 'offline_access organization.read payment_link.read payment_link.write';

	const BASE_PRODUCCION = 'https://oauth.qonto.com';
	const BASE_SANDBOX    = 'https://oauth-sandbox.staging.qonto.co';

	/* ------------------------------------------------------------ Configuración */

	public static function cliente_configurado(): bool {
		return '' !== Idital_Pagos_Config::get( 'IDITAL_QONTO_OAUTH_CLIENT_ID' ) && '' !== Idital_Pagos_Config::get( 'IDITAL_QONTO_OAUTH_CLIENT_SECRET' );
	}

	public static function base(): string {
		$override = Idital_Pagos_Config::get( 'IDITAL_QONTO_OAUTH_BASE' );
		if ( '' !== $override && Idital_Pagos_Config::entorno_local() ) {
			return untrailingslashit( $override );
		}
		return Idital_Pagos_Config::qonto_sandbox() ? self::BASE_SANDBOX : self::BASE_PRODUCCION;
	}

	/** Base para el navegador del gestor (en local el simulador se ve con otra dirección). */
	private static function base_navegador(): string {
		$publica = Idital_Pagos_Config::get( 'IDITAL_QONTO_OAUTH_BASE_PUBLICA' );
		return ( '' !== $publica && Idital_Pagos_Config::entorno_local() ) ? untrailingslashit( $publica ) : self::base();
	}

	/** URL de vuelta que hay que registrar EXACTAMENTE así en la aplicación OAuth de Qonto. */
	public static function redirect_uri(): string {
		return rest_url( 'idital-pagos/v1/qonto/oauth' );
	}

	public static function conectado(): bool {
		$datos = self::leer();
		return ! empty( $datos['refresh'] ) && empty( $datos['roto'] );
	}

	/** Información para el panel (sin tokens). */
	public static function estado(): array {
		$datos = self::leer();
		return array(
			'conectado'     => self::conectado(),
			'roto'          => ! empty( $datos['roto'] ),
			'conectado_el'  => (int) ( $datos['conectado_el'] ?? 0 ),
			'refresh_desde' => (int) ( $datos['refresh_desde'] ?? 0 ),
			'alcance'       => (string) ( $datos['alcance'] ?? '' ),
		);
	}

	/* ------------------------------------------------------------ Autorización */

	/** URL de Qonto a la que se envía al gestor para autorizar. */
	public static function url_autorizacion(): string {
		$state = bin2hex( random_bytes( 32 ) );
		set_transient( self::clave_state( $state ), get_current_user_id(), 10 * MINUTE_IN_SECONDS );

		return self::base_navegador() . '/oauth2/auth?' . http_build_query(
			array(
				'client_id'     => Idital_Pagos_Config::get( 'IDITAL_QONTO_OAUTH_CLIENT_ID' ),
				'redirect_uri'  => self::redirect_uri(),
				'response_type' => 'code',
				'scope'         => self::ALCANCE,
				'state'         => $state,
			),
			'',
			'&',
			PHP_QUERY_RFC3986
		);
	}

	private static function clave_state( string $state ): string {
		return 'idital_qonto_state_' . hash( 'sha256', $state );
	}

	/**
	 * Vuelta desde Qonto: valida "state" y canjea el código.
	 *
	 * @return array{0: int, 1: string} [ ID del usuario que inició la conexión (0 si state no válido), mensaje de error o '' ]
	 */
	public static function completar( string $codigo, string $state ): array {
		if ( ! preg_match( '/^[a-f0-9]{64}$/', $state ) ) {
			return array( 0, 'Petición de conexión no válida.' );
		}
		$clave   = self::clave_state( $state );
		$usuario = (int) get_transient( $clave );
		delete_transient( $clave ); // Un solo uso, pase lo que pase.

		if ( $usuario <= 0 || ! user_can( $usuario, 'manage_woocommerce' ) ) {
			return array( 0, 'La petición de conexión ha caducado o no es válida. Vuelve a pulsar "Conectar con Qonto".' );
		}
		if ( '' === $codigo || strlen( $codigo ) > 2048 ) {
			return array( $usuario, 'Qonto no ha devuelto un código de autorización.' );
		}

		try {
			$respuesta = Idital_Pagos_Http::json(
				'POST',
				self::base() . '/oauth2/token',
				array(),
				array(
					'grant_type'    => 'authorization_code',
					'code'          => $codigo,
					'client_id'     => Idital_Pagos_Config::get( 'IDITAL_QONTO_OAUTH_CLIENT_ID' ),
					'client_secret' => Idital_Pagos_Config::get( 'IDITAL_QONTO_OAUTH_CLIENT_SECRET' ),
					'redirect_uri'  => self::redirect_uri(),
				),
				true
			);
		} catch ( Idital_Pagos_Exception $e ) {
			return array( $usuario, 'No hay conexión con Qonto: ' . $e->getMessage() );
		}

		$error = self::guardar_respuesta( $respuesta, true );
		if ( '' !== $error ) {
			return array( $usuario, $error );
		}
		Idital_Pagos_Log::info( 'Tienda conectada con Qonto por OAuth', array( 'usuario' => $usuario ) );
		return array( $usuario, '' );
	}

	public static function desconectar(): void {
		delete_option( self::OPCION );
		Idital_Pagos_Log::info( 'Conexión OAuth con Qonto eliminada', array( 'usuario' => get_current_user_id() ) );
	}

	/* ----------------------------------------------------------------- Tokens */

	/**
	 * Access token válido (renovándolo si hace falta).
	 *
	 * @throws Idital_Pagos_Exception Si la tienda no está conectada o la renovación falla.
	 */
	public static function token(): string {
		$datos = self::leer();
		if ( empty( $datos['refresh'] ) || ! empty( $datos['roto'] ) ) {
			throw new Idital_Pagos_Exception( 'La tienda no está conectada con Qonto (falta autorizar con "Conectar con Qonto").' );
		}
		if ( ! empty( $datos['access'] ) && (int) ( $datos['access_hasta'] ?? 0 ) > time() + 120 ) {
			return (string) $datos['access'];
		}
		return self::renovar( false );
	}

	/**
	 * Renueva el access token usando el refresh token (que rota en cada uso).
	 *
	 * @param bool $forzar Renovar aunque el access token siga vigente (mantenimiento).
	 * @throws Idital_Pagos_Exception Si no se puede renovar.
	 */
	public static function renovar( bool $forzar ): string {
		$bloqueo = 'qonto_oauth_renovar';
		$tengo   = false;
		for ( $i = 0; $i < 10 && ! $tengo; $i++ ) {
			$tengo = Idital_Pagos_Bloqueo::adquirir( $bloqueo, 60 );
			if ( ! $tengo ) {
				usleep( 500000 );
				// Otro proceso puede haberlo renovado mientras tanto.
				$datos = self::leer();
				if ( ! $forzar && ! empty( $datos['access'] ) && (int) ( $datos['access_hasta'] ?? 0 ) > time() + 120 ) {
					return (string) $datos['access'];
				}
			}
		}
		if ( ! $tengo ) {
			throw new Idital_Pagos_Exception( 'No se pudo renovar el acceso a Qonto (renovación en curso en otro proceso).' );
		}

		try {
			$datos = self::leer(); // Releer dentro del bloqueo.
			if ( ! $forzar && ! empty( $datos['access'] ) && (int) ( $datos['access_hasta'] ?? 0 ) > time() + 120 ) {
				return (string) $datos['access'];
			}
			if ( empty( $datos['refresh'] ) || ! empty( $datos['roto'] ) ) {
				throw new Idital_Pagos_Exception( 'La tienda no está conectada con Qonto.' );
			}

			$respuesta = Idital_Pagos_Http::json(
				'POST',
				self::base() . '/oauth2/token',
				array(),
				array(
					'grant_type'    => 'refresh_token',
					'refresh_token' => (string) $datos['refresh'],
					'client_id'     => Idital_Pagos_Config::get( 'IDITAL_QONTO_OAUTH_CLIENT_ID' ),
					'client_secret' => Idital_Pagos_Config::get( 'IDITAL_QONTO_OAUTH_CLIENT_SECRET' ),
				),
				true
			);

			$error = self::guardar_respuesta( $respuesta, false );
			if ( '' !== $error ) {
				// Refresh token rechazado (caducado o revocado): hay que volver a conectar.
				if ( in_array( (string) ( $respuesta['datos']['error'] ?? '' ), array( 'invalid_grant', 'invalid_client', 'unauthorized_client' ), true ) ) {
					self::marcar_roto( $error );
				}
				throw new Idital_Pagos_Exception( $error );
			}
			return (string) self::leer()['access'];
		} finally {
			Idital_Pagos_Bloqueo::liberar( $bloqueo );
		}
	}

	/** Mantiene viva la conexión aunque no haya ventas (el refresh token caduca a los 90 días). */
	public static function mantener(): void {
		$datos = self::leer();
		if ( empty( $datos['refresh'] ) || ! empty( $datos['roto'] ) ) {
			return;
		}
		if ( (int) ( $datos['refresh_desde'] ?? 0 ) < time() - 20 * DAY_IN_SECONDS ) {
			try {
				self::renovar( true );
			} catch ( Idital_Pagos_Exception $e ) {
				Idital_Pagos_Log::error( 'No se pudo renovar la conexión con Qonto', array( 'error' => $e->getMessage() ) );
			}
		}
	}

	/**
	 * Valida y guarda una respuesta del endpoint de tokens. Devuelve '' o el mensaje de error.
	 */
	private static function guardar_respuesta( array $respuesta, bool $nueva ): string {
		$d = $respuesta['datos'];
		if ( 200 !== $respuesta['codigo'] || empty( $d['access_token'] ) ) {
			$codigo = sanitize_key( (string) ( $d['error'] ?? 'http_' . $respuesta['codigo'] ) );
			return 'Qonto no ha concedido el acceso (' . $codigo . ').';
		}
		if ( empty( $d['refresh_token'] ) ) {
			return 'Qonto no ha devuelto un refresh token: activa el permiso "offline_access" en la aplicación OAuth.';
		}
		$alcance = (string) ( $d['scope'] ?? '' );
		if ( '' !== $alcance && ! in_array( 'payment_link.write', preg_split( '/\s+/', $alcance ), true ) ) {
			return 'La autorización no incluye el permiso "payment_link.write". Actívalo en la aplicación OAuth de Qonto y vuelve a conectar.';
		}

		$anterior = self::leer();
		self::escribir(
			array(
				'access'        => (string) $d['access_token'],
				'access_hasta'  => time() + max( 60, min( 3600, (int) ( $d['expires_in'] ?? 3600 ) ) ),
				'refresh'       => (string) $d['refresh_token'],
				'refresh_desde' => time(),
				'alcance'       => sanitize_text_field( $alcance ),
				'conectado_el'  => $nueva ? time() : (int) ( $anterior['conectado_el'] ?? time() ),
			)
		);
		return '';
	}

	private static function marcar_roto( string $motivo ): void {
		$datos = self::leer();
		if ( ! empty( $datos['roto'] ) ) {
			return;
		}
		$datos['roto']   = time();
		$datos['access'] = '';
		self::escribir( $datos );
		Idital_Pagos_Log::error( 'Conexión con Qonto perdida', array( 'motivo' => $motivo ) );
		wp_mail(
			get_option( 'admin_email' ),
			sprintf( '[%s] Hay que volver a conectar Qonto', wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ),
			"Qonto ha rechazado la renovación del acceso de la tienda ($motivo).\n\n"
			. "Mientras no se vuelva a conectar, el pago con Qonto no se mostrará a los clientes.\n"
			. "Para reconectar: WooCommerce → Ajustes → Pagos → Qonto → \"Conectar con Qonto\".\n\n"
			. admin_url( 'admin.php?page=wc-settings&tab=checkout&section=idital_qonto' )
		);
	}

	/* --------------------------------------------- Almacenamiento cifrado */

	private static function clave(): string {
		return sodium_crypto_generichash( Idital_Pagos_Config::clave_hmac() . '|qonto-oauth', '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
	}

	private static function leer(): array {
		$cifrado = get_option( self::OPCION, '' );
		if ( ! is_string( $cifrado ) || '' === $cifrado ) {
			return array();
		}
		$bruto = base64_decode( $cifrado, true ); // phpcs:ignore
		if ( false === $bruto || strlen( $bruto ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return array();
		}
		$json = sodium_crypto_secretbox_open(
			substr( $bruto, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ),
			substr( $bruto, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ),
			self::clave()
		);
		$datos = false === $json ? null : json_decode( $json, true );
		return is_array( $datos ) ? $datos : array();
	}

	private static function escribir( array $datos ): void {
		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$valor = base64_encode( $nonce . sodium_crypto_secretbox( (string) wp_json_encode( $datos ), $nonce, self::clave() ) ); // phpcs:ignore
		update_option( self::OPCION, $valor, false );
	}
}
