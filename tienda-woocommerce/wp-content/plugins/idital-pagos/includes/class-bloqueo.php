<?php
/**
 * Bloqueos atómicos en base de datos para evitar procesar dos veces lo mismo
 * (doble clic del cliente, webhook repetido llegando a la vez, etc.).
 *
 * @package IditalPagos
 */

defined( 'ABSPATH' ) || exit;

final class Idital_Pagos_Bloqueo {

	private static function nombre( string $clave ): string {
		return 'idital_lock_' . md5( $clave );
	}

	public static function adquirir( string $clave, int $ttl = 60 ): bool {
		global $wpdb;
		$nombre = self::nombre( $clave );
		$expira = time() + $ttl;

		// INSERT IGNORE sobre la clave única option_name: solo un proceso puede ganar.
		$insertado = $wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
				$nombre,
				(string) $expira
			)
		);
		if ( 1 === (int) $insertado ) {
			return true;
		}

		// Bloqueo caducado (proceso muerto): se reclama de forma atómica.
		$reclamado = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND CAST(option_value AS UNSIGNED) < %d",
				(string) $expira,
				$nombre,
				time()
			)
		);
		return 1 === (int) $reclamado;
	}

	public static function liberar( string $clave ): void {
		global $wpdb;
		$wpdb->delete( $wpdb->options, array( 'option_name' => self::nombre( $clave ) ) );
	}
}
