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
// Qonto solo permite crear links de pago con OAuth 2.0. Hace falta una aplicación OAuth creada en el
// portal de desarrolladores de Qonto (https://developers.qonto.com) con los permisos
// offline_access, organization.read, payment_link.read y payment_link.write, y con esta URL de redirección:
//   https://<dominio>/wp-json/idital-pagos/v1/qonto/oauth
// Después, en WooCommerce → Ajustes → Pagos → Qonto, pulsar "Conectar con Qonto".
define( 'IDITAL_QONTO_ENTORNO', 'produccion' );          // 'produccion' o 'sandbox'
define( 'IDITAL_QONTO_OAUTH_CLIENT_ID', '' );            // Client ID de la aplicación OAuth
define( 'IDITAL_QONTO_OAUTH_CLIENT_SECRET', '' );        // Client Secret de la aplicación OAuth
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
