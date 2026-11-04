<?php
/**
 * Plugin Name: Idital Blindaje
 * Description: Endurecimiento de seguridad de WordPress (siempre activo, no se puede desactivar desde el panel).
 * Version:     1.0.0
 * Author:      Idital
 *
 * @package IditalBlindaje
 */

defined( 'ABSPATH' ) || exit;

/* ---------------------------------------------------------------- 1. XML-RPC fuera */
add_filter( 'xmlrpc_enabled', '__return_false' );
add_filter( 'xmlrpc_methods', '__return_empty_array' );
add_filter(
	'wp_headers',
	static function ( $cabeceras ) {
		unset( $cabeceras['X-Pingback'] );
		return $cabeceras;
	}
);

/* ------------------------------------------------- 2. No revelar versiones ni usuarios */
remove_action( 'wp_head', 'wp_generator' );
add_filter( 'the_generator', '__return_empty_string' );
remove_action( 'wp_head', 'rsd_link' );
remove_action( 'wp_head', 'wlwmanifest_link' );

// ?author=N redirige a la URL del autor y revela el nombre de usuario.
add_action(
	'template_redirect',
	static function () {
		if ( ! is_user_logged_in() && ( isset( $_GET['author'] ) || is_author() ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}
	}
);

// La API REST no lista usuarios a visitantes anónimos.
add_filter(
	'rest_endpoints',
	static function ( $endpoints ) {
		if ( ! is_user_logged_in() ) {
			foreach ( array_keys( $endpoints ) as $ruta ) {
				if ( preg_match( '#^/wp/v2/users#', $ruta ) ) {
					unset( $endpoints[ $ruta ] );
				}
			}
		}
		return $endpoints;
	}
);

// Los sitemaps no publican usuarios.
add_filter(
	'wp_sitemaps_add_provider',
	static fn( $proveedor, $nombre ) => 'users' === $nombre ? false : $proveedor,
	10,
	2
);

// Contraseñas de aplicación desactivadas (si se necesitan para una integración, quitar esta línea).
add_filter( 'wp_is_application_passwords_available', '__return_false' );

/* ---------------------------------------------- 3. Inicio de sesión: límite de intentos */
function idital_blindaje_ip(): string {
	// Misma lógica que el plugin de pagos (proxies de confianza por lista de IP/CIDR).
	if ( class_exists( 'Idital_Pagos_Limite' ) ) {
		return Idital_Pagos_Limite::ip();
	}
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
}

/** Clave del contador: IP + usuario, para que un atacante no pueda bloquear a todo el mundo. */
function idital_blindaje_clave( string $usuario ): string {
	return 'idital_login_' . md5( idital_blindaje_ip() . '|' . strtolower( trim( $usuario ) ) );
}

const IDITAL_BLINDAJE_MAX_FALLOS = 5;
const IDITAL_BLINDAJE_BLOQUEO    = 15 * MINUTE_IN_SECONDS;

add_filter(
	'authenticate',
	static function ( $usuario, $nombre ) {
		$datos = get_transient( idital_blindaje_clave( (string) $nombre ) );
		if ( is_array( $datos ) && (int) $datos['n'] >= IDITAL_BLINDAJE_MAX_FALLOS ) {
			return new WP_Error( 'idital_bloqueado', __( 'Demasiados intentos fallidos. Inténtalo de nuevo dentro de 15 minutos.' ) );
		}
		return $usuario;
	},
	99, // Después de la comprobación de contraseña de WordPress, para que prevalezca el bloqueo.
	2
);

add_action(
	'wp_login_failed',
	static function ( $nombre ) {
		$clave = idital_blindaje_clave( (string) $nombre );
		$datos = get_transient( $clave );
		if ( ! is_array( $datos ) ) {
			$datos = array(
				'n'      => 0,
				'inicio' => time(),
			);
		}
		++$datos['n'];
		// Ventana fija: los intentos nuevos no alargan el bloqueo indefinidamente.
		$restante = max( 60, IDITAL_BLINDAJE_BLOQUEO - ( time() - (int) $datos['inicio'] ) );
		set_transient( $clave, $datos, $restante );
	}
);

add_action(
	'wp_login',
	static function ( $nombre ) {
		delete_transient( idital_blindaje_clave( (string) $nombre ) );
	}
);

// Mensaje de error genérico: no revela si el usuario existe.
add_filter(
	'login_errors',
	static function ( $error ) {
		return str_contains( (string) $error, 'Demasiados intentos' ) ? $error : __( 'Usuario o contraseña incorrectos.' );
	}
);

/* ------------------------------------------------------- 4. Cabeceras de seguridad */
add_action(
	'send_headers',
	static function () {
		if ( headers_sent() ) {
			return;
		}
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Frame-Options: SAMEORIGIN' );
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );
		header( 'Permissions-Policy: camera=(), microphone=(), geolocation=(), interest-cohort=()' );
		header( "Content-Security-Policy: frame-ancestors 'self'; base-uri 'self'; object-src 'none'; form-action 'self' https:" );
		if ( is_ssl() ) {
			header( 'Strict-Transport-Security: max-age=31536000' );
		}
	}
);

/* ----------------------------------------------- 5. Cookies y sesión más estrictas */
add_filter( 'auth_cookie_expiration', static fn() => 2 * DAY_IN_SECONDS );

/* --------------------------------------- 6. Evitar registro abierto y roles peligrosos */
add_filter( 'pre_option_users_can_register', '__return_zero' );
add_filter(
	'pre_option_default_role',
	static fn() => class_exists( 'WooCommerce' ) ? 'customer' : 'subscriber'
);
