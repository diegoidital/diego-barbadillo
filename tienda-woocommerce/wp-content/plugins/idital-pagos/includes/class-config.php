<?php
/**
 * Lectura de la configuración sensible.
 *
 * Las credenciales NUNCA se guardan en la base de datos ni se muestran en el
 * panel: se definen como constantes en wp-config.php (que a su vez las lee de
 * ficheros de secretos fuera del código).
 *
 * @package IditalPagos
 */

defined( 'ABSPATH' ) || exit;

final class Idital_Pagos_Config {

	/** Hosts base (Qonto: docs.qonto.com · TropiPay: doc.tropipay.com). */
	const QONTO_BASE_PRODUCCION    = 'https://thirdparty.qonto.com';
	const QONTO_BASE_SANDBOX       = 'https://thirdparty-sandbox.staging.qonto.co';
	const TROPIPAY_BASE_PRODUCCION = 'https://www.tropipay.com';
	const TROPIPAY_BASE_SANDBOX    = 'https://sandbox.tropipay.me';

	public static function get( string $nombre ): string {
		return defined( $nombre ) ? trim( (string) constant( $nombre ) ) : '';
	}

	/** Entorno local de pruebas: permite http y el simulador. Nunca en una web real. */
	public static function entorno_local(): bool {
		return 'local' === wp_get_environment_type();
	}

	/** ¿Esta pasarela funciona en modo pruebas (sandbox)? */
	public static function sandbox( string $pasarela ): bool {
		return 'idital_qonto' === $pasarela ? self::qonto_sandbox() : self::tropipay_sandbox();
	}

	/* ------------------------------------------------------------------ Qonto */

	/** Por seguridad, todo funciona en sandbox salvo que se indique "produccion" explícitamente. */
	public static function qonto_sandbox(): bool {
		return 'produccion' !== self::get( 'IDITAL_QONTO_ENTORNO' );
	}

	public static function qonto_base(): string {
		return self::base( 'IDITAL_QONTO_API_BASE', self::qonto_sandbox() ? self::QONTO_BASE_SANDBOX : self::QONTO_BASE_PRODUCCION );
	}

	public static function qonto_configurado(): bool {
		if ( '' === self::get( 'IDITAL_QONTO_LOGIN' ) || '' === self::get( 'IDITAL_QONTO_SECRET_KEY' ) ) {
			return false;
		}
		// El sandbox de Qonto exige además el token de staging (salvo simulador local).
		if ( self::qonto_sandbox() && '' === self::get( 'IDITAL_QONTO_STAGING_TOKEN' ) && ! self::override_activo( 'IDITAL_QONTO_API_BASE' ) ) {
			return false;
		}
		return true;
	}

	/** Token secreto que debe llevar la URL del webhook de Qonto. */
	public static function qonto_webhook_token(): string {
		$token = self::get( 'IDITAL_QONTO_WEBHOOK_TOKEN' );
		if ( strlen( $token ) >= 20 ) {
			return $token;
		}
		return substr( hash_hmac( 'sha256', 'qonto-webhook', self::clave_hmac() ), 0, 40 );
	}

	/* --------------------------------------------------------------- TropiPay */

	public static function tropipay_sandbox(): bool {
		return 'produccion' !== self::get( 'IDITAL_TROPIPAY_ENTORNO' );
	}

	public static function tropipay_base(): string {
		return self::base( 'IDITAL_TROPIPAY_API_BASE', self::tropipay_sandbox() ? self::TROPIPAY_BASE_SANDBOX : self::TROPIPAY_BASE_PRODUCCION );
	}

	public static function tropipay_configurado(): bool {
		return '' !== self::get( 'IDITAL_TROPIPAY_CLIENT_ID' ) && '' !== self::get( 'IDITAL_TROPIPAY_CLIENT_SECRET' );
	}

	/**
	 * La verificación de firma de TropiPay solo puede desactivarse en local.
	 */
	public static function tropipay_verificar_firma(): bool {
		return ! ( self::entorno_local() && 'no' === self::get( 'IDITAL_TROPIPAY_VERIFICAR_FIRMA' ) );
	}

	/* ----------------------------------------------------------------- Comunes */

	/**
	 * Clave para firmar las URLs de notificación. Se recomienda definir
	 * IDITAL_PAGOS_CLAVE_HMAC (>= 32 caracteres aleatorios); si no, se deriva de las sales de WordPress.
	 */
	public static function clave_hmac(): string {
		$clave = self::get( 'IDITAL_PAGOS_CLAVE_HMAC' );
		if ( strlen( $clave ) >= 32 ) {
			return $clave;
		}
		return wp_salt( 'auth' ) . '|idital-pagos';
	}

	/**
	 * URL base de la API. Solo se puede sobrescribir (simulador) en entorno local:
	 * en una web real siempre se usan los hosts oficiales.
	 */
	private static function base( string $constante, string $por_defecto ): string {
		$override = self::get( $constante );
		if ( '' === $override || ! self::entorno_local() ) {
			return $por_defecto;
		}
		$esquema = wp_parse_url( $override, PHP_URL_SCHEME );
		return in_array( $esquema, array( 'http', 'https' ), true ) ? untrailingslashit( $override ) : $por_defecto;
	}

	public static function override_activo( string $constante ): bool {
		return '' !== self::get( $constante ) && self::entorno_local();
	}

	/**
	 * Comprueba que una URL devuelta por la pasarela pertenece a un dominio esperado
	 * (evita mandar al cliente a un dominio ajeno si la respuesta se manipulase).
	 *
	 * @param string   $url      URL a validar.
	 * @param string[] $dominios Dominios permitidos (se aceptan también sus subdominios).
	 */
	public static function url_permitida( string $url, array $dominios ): bool {
		$partes = wp_parse_url( $url );
		if ( empty( $partes['host'] ) || empty( $partes['scheme'] ) ) {
			return false;
		}
		if ( self::entorno_local() && in_array( $partes['scheme'], array( 'http', 'https' ), true ) ) {
			return true;
		}
		if ( 'https' !== $partes['scheme'] ) {
			return false;
		}
		$host = strtolower( $partes['host'] );
		foreach ( $dominios as $dominio ) {
			$dominio = strtolower( $dominio );
			if ( $host === $dominio || str_ends_with( $host, '.' . $dominio ) ) {
				return true;
			}
		}
		return false;
	}
}
