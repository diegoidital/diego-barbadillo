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
	 * IP del cliente. X-Forwarded-For solo se tiene en cuenta si la petición llega desde
	 * un proxy declarado en IDITAL_PROXY_CONFIANZA (lista de IP o rangos CIDR separados
	 * por comas, p. ej. los de Cloudflare); se toma la IP más a la derecha que no sea un proxy.
	 */
	public static function ip(): string {
		$remota = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		if ( ! filter_var( $remota, FILTER_VALIDATE_IP ) ) {
			return '0.0.0.0';
		}
		$proxies = defined( 'IDITAL_PROXY_CONFIANZA' ) && is_string( IDITAL_PROXY_CONFIANZA ) ? array_filter( array_map( 'trim', explode( ',', IDITAL_PROXY_CONFIANZA ) ) ) : array();
		if ( ! $proxies || ! self::en_rangos( $remota, $proxies ) || empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			return $remota;
		}
		$cadena = array_reverse( array_map( 'trim', explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) ) ) );
		foreach ( $cadena as $ip ) {
			if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				break;
			}
			if ( ! self::en_rangos( $ip, $proxies ) ) {
				return $ip;
			}
		}
		return $remota;
	}

	public static function en_rangos( string $ip, array $rangos ): bool {
		$bin = @inet_pton( $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( false === $bin ) {
			return false;
		}
		foreach ( $rangos as $rango ) {
			[ $red, $bits ] = array_pad( explode( '/', $rango, 2 ), 2, null );
			$red_bin        = @inet_pton( $red ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			if ( false === $red_bin || strlen( $red_bin ) !== strlen( $bin ) ) {
				continue;
			}
			$bits = null === $bits ? strlen( $bin ) * 8 : (int) $bits;
			$bytes = intdiv( $bits, 8 );
			$resto = $bits % 8;
			if ( substr( $bin, 0, $bytes ) !== substr( $red_bin, 0, $bytes ) ) {
				continue;
			}
			if ( 0 === $resto || ( ( ord( $bin[ $bytes ] ) ^ ord( $red_bin[ $bytes ] ) ) & ( 0xFF << ( 8 - $resto ) ) & 0xFF ) === 0 ) {
				return true;
			}
		}
		return false;
	}
}
