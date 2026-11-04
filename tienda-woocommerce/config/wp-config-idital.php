<?php
/**
 * Configuración adicional de wp-config.php (se incluye desde WORDPRESS_CONFIG_EXTRA).
 *
 * Las credenciales se leen de ficheros de secretos montados en /run/secrets,
 * nunca de variables de entorno ni de la base de datos.
 */

if ( ! defined( 'DB_NAME' ) ) {
	exit;
}

if ( ! function_exists( 'idital_secreto' ) ) {
	function idital_secreto( string $nombre ): string {
		$fichero = '/run/secrets/' . $nombre;
		return is_readable( $fichero ) ? trim( (string) file_get_contents( $fichero ) ) : '';
	}
}

// Entorno: production | staging | development | local.
$idital_entorno = getenv( 'WP_ENVIRONMENT_TYPE' ) ?: 'production';
if ( ! in_array( $idital_entorno, array( 'production', 'staging', 'development', 'local' ), true ) ) {
	$idital_entorno = 'production';
}
define( 'WP_ENVIRONMENT_TYPE', $idital_entorno );

// Endurecimiento general.
define( 'DISALLOW_FILE_EDIT', true );                       // Sin editor de código en el panel.
define( 'DISALLOW_FILE_MODS', 'true' === getenv( 'WP_BLOQUEAR_INSTALACIONES' ) ); // true = nadie instala plugins desde el panel.
define( 'WP_AUTO_UPDATE_CORE', 'minor' );                   // Parches de seguridad automáticos.
define( 'FORCE_SSL_ADMIN', 'true' === getenv( 'WP_FORZAR_SSL' ) );
define( 'WP_POST_REVISIONS', 10 );
define( 'EMPTY_TRASH_DAYS', 30 );
if ( ! defined( 'WP_DEBUG_DISPLAY' ) ) {
	define( 'WP_DEBUG_DISPLAY', false );
}

// Detrás de un proxy/Cloudflare: lista de IP o rangos CIDR del proxy (vacío = no hay proxy).
define( 'IDITAL_PROXY_CONFIANZA', (string) getenv( 'IDITAL_PROXY_CONFIANZA' ) );

// ------------------------------------------------------------------ Pagos: Qonto
define( 'IDITAL_QONTO_ENTORNO', getenv( 'IDITAL_QONTO_ENTORNO' ) ?: 'sandbox' ); // sandbox | produccion
define( 'IDITAL_QONTO_LOGIN', idital_secreto( 'qonto_login' ) );
define( 'IDITAL_QONTO_SECRET_KEY', idital_secreto( 'qonto_secret_key' ) );
define( 'IDITAL_QONTO_STAGING_TOKEN', idital_secreto( 'qonto_staging_token' ) );
define( 'IDITAL_QONTO_WEBHOOK_TOKEN', idital_secreto( 'qonto_webhook_token' ) );

// --------------------------------------------------------------- Pagos: TropiPay
define( 'IDITAL_TROPIPAY_ENTORNO', getenv( 'IDITAL_TROPIPAY_ENTORNO' ) ?: 'sandbox' ); // sandbox | produccion
define( 'IDITAL_TROPIPAY_CLIENT_ID', idital_secreto( 'tropipay_client_id' ) );
define( 'IDITAL_TROPIPAY_CLIENT_SECRET', idital_secreto( 'tropipay_client_secret' ) );

// Clave para firmar las URLs de notificación.
define( 'IDITAL_PAGOS_CLAVE_HMAC', idital_secreto( 'pagos_clave_hmac' ) );

// Solo para pruebas locales con el simulador (el plugin las ignora fuera de local/development).
define( 'IDITAL_QONTO_API_BASE', getenv( 'IDITAL_QONTO_API_BASE' ) ?: '' );
define( 'IDITAL_TROPIPAY_API_BASE', getenv( 'IDITAL_TROPIPAY_API_BASE' ) ?: '' );
