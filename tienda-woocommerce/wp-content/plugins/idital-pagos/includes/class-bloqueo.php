<?php
/**
 * Bloqueos atómicos en base de datos para evitar procesar dos veces lo mismo
 * (doble clic del cliente, webhook repetido llegando a la vez, etc.).
 *
 * @package IditalPagos
 */

defined( 'ABSPATH' ) || exit;

final class Idital_Pagos_Bloqueo {

	/** Tokens de los bloqueos que tiene este proceso. */
	private static array $propios = array();

	private static function nombre( string $clave ): string {
		return 'idital_lock_' . md5( $clave );
	}

	public static function adquirir( string $clave, int $ttl = 60 ): bool {
		global $wpdb;
		$nombre = self::nombre( $clave );
		$token  = ( time() + $ttl ) . ':' . bin2hex( random_bytes( 8 ) );

		// INSERT IGNORE sobre la clave única option_name: solo un proceso puede ganar.
		$insertado = $wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
				$nombre,
				$token
			)
		);
		if ( 1 !== (int) $insertado ) {
			// Bloqueo caducado (proceso muerto): se reclama de forma atómica.
			$insertado = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND CAST(SUBSTRING_INDEX(option_value, ':', 1) AS UNSIGNED) < %d",
					$token,
					$nombre,
					time()
				)
			);
		}
		if ( 1 === (int) $insertado ) {
			self::$propios[ $nombre ] = $token;
			return true;
		}
		return false;
	}

	/** Libera el bloqueo solo si sigue siendo nuestro. */
	public static function liberar( string $clave ): void {
		global $wpdb;
		$nombre = self::nombre( $clave );
		if ( ! isset( self::$propios[ $nombre ] ) ) {
			return;
		}
		$wpdb->delete(
			$wpdb->options,
			array(
				'option_name'  => $nombre,
				'option_value' => self::$propios[ $nombre ],
			)
		);
		unset( self::$propios[ $nombre ] );
	}
}
