<?php
/**
 * Registro de eventos (WooCommerce > Estado > Registros, fuente "idital-pagos")
 * con ocultación automática de datos sensibles.
 *
 * @package IditalPagos
 */

defined( 'ABSPATH' ) || exit;

final class Idital_Pagos_Log {

	const FUENTE = 'idital-pagos';

	public static function info( string $mensaje, array $contexto = array() ): void {
		self::escribir( 'info', $mensaje, $contexto );
	}

	public static function aviso( string $mensaje, array $contexto = array() ): void {
		self::escribir( 'warning', $mensaje, $contexto );
	}

	public static function error( string $mensaje, array $contexto = array() ): void {
		self::escribir( 'error', $mensaje, $contexto );
	}

	private static function escribir( string $nivel, string $mensaje, array $contexto ): void {
		if ( ! function_exists( 'wc_get_logger' ) ) {
			return;
		}
		$contexto['source'] = self::FUENTE;
		$contexto           = self::ocultar( $contexto );
		wc_get_logger()->log( $nivel, $mensaje, $contexto );
	}

	/**
	 * Sustituye por "***" cualquier valor cuya clave parezca sensible.
	 *
	 * @param mixed $datos Datos a limpiar.
	 * @param int   $nivel Profundidad actual.
	 * @return mixed
	 */
	public static function ocultar( $datos, int $nivel = 0 ) {
		if ( ! is_array( $datos ) || $nivel > 6 ) {
			return $datos;
		}
		foreach ( $datos as $clave => $valor ) {
			if ( is_string( $clave ) && preg_match( '/secret|token|password|passwd|authorization|signature|firma|iban|api_?key|client_?id|card|cvv|pan/i', $clave ) ) {
				$datos[ $clave ] = '***';
			} elseif ( is_array( $valor ) ) {
				$datos[ $clave ] = self::ocultar( $valor, $nivel + 1 );
			}
		}
		return $datos;
	}
}
