<?php
/**
 * Registro permanente "clave → pedido" (links de Qonto, operaciones de TropiPay).
 *
 * No depende de búsquedas por metadatos de pedido (que WooCommerce no aplica igual con
 * el almacenamiento clásico y con HPOS) y el alta es atómica: una clave solo puede
 * pertenecer a un pedido.
 *
 * @package IditalPagos
 */

defined( 'ABSPATH' ) || exit;

final class Idital_Pagos_Registro {

	private static function nombre( string $tipo, string $clave ): string {
		return 'idital_reg_' . $tipo . '_' . md5( $clave );
	}

	/** Asocia la clave al pedido. Devuelve false si ya pertenecía a OTRO pedido. */
	public static function asociar( string $tipo, string $clave, int $pedido_id ): bool {
		global $wpdb;
		$nombre = self::nombre( $tipo, $clave );
		$wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
				$nombre,
				(string) $pedido_id
			)
		);
		return self::pedido( $tipo, $clave ) === $pedido_id;
	}

	/** ID del pedido asociado a la clave, o 0. */
	public static function pedido( string $tipo, string $clave ): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::nombre( $tipo, $clave ) ) );
	}
}
