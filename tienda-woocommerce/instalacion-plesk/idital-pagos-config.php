<?php
/**
 * Credenciales de las pasarelas de pago (plugin Idital Pagos).
 *
 * DÓNDE VA ESTE FICHERO: FUERA de la carpeta pública de la web.
 * En Plesk:  /var/www/vhosts/<dominio>/private/idital-pagos-config.php
 * (carpeta "private", al mismo nivel que "httpdocs").
 *
 * Y en wp-config.php, justo ANTES de la línea "That's all, stop editing!":
 *
 *   if ( is_readable( dirname( __DIR__ ) . '/private/idital-pagos-config.php' ) ) {
 *       require_once dirname( __DIR__ ) . '/private/idital-pagos-config.php';
 *   }
 *
 * Mientras un dato esté vacío, la pasarela correspondiente NO se muestra a los clientes.
 */

if ( ! defined( 'DB_NAME' ) ) {
	exit;
}

// ---------------------------------------------------------------- Qonto
// Clave API de la app de Qonto (Ajustes → Integraciones y conexiones → Clave API).
define( 'IDITAL_QONTO_ENTORNO', 'produccion' );          // 'produccion' para claves de la app; 'sandbox' para el portal de desarrolladores
define( 'IDITAL_QONTO_LOGIN', '' );                      // identificador de la organización, p. ej. empresa-sl-1234
define( 'IDITAL_QONTO_SECRET_KEY', '' );                 // clave secreta
define( 'IDITAL_QONTO_STAGING_TOKEN', '' );              // solo sandbox

// ------------------------------------------------------------- TropiPay
define( 'IDITAL_TROPIPAY_ENTORNO', 'sandbox' );          // 'produccion' cuando esté probado
define( 'IDITAL_TROPIPAY_CLIENT_ID', '' );
define( 'IDITAL_TROPIPAY_CLIENT_SECRET', '' );

// ------------------------------------------------- Claves internas (OBLIGATORIAS)
// Dos cadenas aleatorias largas y distintas (p. ej. de https://api.wordpress.org/secret-key/1.1/salt/,
// copiando solo el texto entre comillas). No cambiarlas después: invalidan los avisos de pago pendientes.
define( 'IDITAL_PAGOS_CLAVE_HMAC', '' );                 // mínimo 32 caracteres
define( 'IDITAL_QONTO_WEBHOOK_TOKEN', '' );              // mínimo 20 caracteres (letras y números)

// Opcional: solo si la web está detrás de Cloudflare u otro proxy. IP o rangos CIDR del proxy,
// separados por comas. Vacío = sin proxy (lo normal en Plesk).
define( 'IDITAL_PROXY_CONFIANZA', '' );
