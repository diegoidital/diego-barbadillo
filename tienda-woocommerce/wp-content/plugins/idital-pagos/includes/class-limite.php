<?php
/**
 * Limitación de peticiones por IP para los endpoints públicos.
 *
 * @package IditalPagos
 */

defined( 'ABSPATH' ) || exit;

final class Idital_Pagos_Limite {

	public static function permitir( string $grupo, int $maximo, int $ventana_segundos ): bool {
		$clave = 'idital_rl_' . md5( $grupo . '|' . self::ip() );
		$datos = get_transient( $clave );
		$ahora = time();

		if ( ! is_array( $datos ) || ( $datos['inicio'] ?? 0 ) + $ventana_segundos < $ahora ) {
			$datos = array(
				'inicio' => $ahora,
				'n'      => 0,
			);
		}
		if ( $datos['n'] >= $maximo ) {
			return false;
		}
		++$datos['n'];
		set_transient( $clave, $datos, $ventana_segundos );
		return true;
	}

	/**
	 * IP del cliente. Solo se confía en X-Forwarded-For si se declara
	 * IDITAL_PROXY_CONFIANZA (p. ej. detrás de Cloudflare o un proxy propio).
	 */
	public static function ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		if ( defined( 'IDITAL_PROXY_CONFIANZA' ) && IDITAL_PROXY_CONFIANZA && ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$lista = array_map( 'trim', explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) ) );
			$ip    = (string) end( $lista ); // La última entrada es la que añadió nuestro proxy.
		}
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
	}
}
