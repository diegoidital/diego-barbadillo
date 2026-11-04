<?php
/**
 * Único punto donde un pedido pasa a "pagado".
 *
 * Reglas:
 *  - Solo se llama después de haber verificado el pago con la pasarela
 *    (Qonto: consultando su API; TropiPay: firma + token del pedido).
 *  - El importe y la moneda cobrados deben coincidir EXACTAMENTE con el pedido.
 *  - Es idempotente y está protegido con un bloqueo atómico.
 *  - Cualquier anomalía (importe distinto, pago duplicado, pedido cancelado...)
 *    no se resuelve sola: deja nota en el pedido y avisa por email al administrador.
 *
 * @package IditalPagos
 */

defined( 'ABSPATH' ) || exit;

final class Idital_Pagos_Confirmacion {

	const NOMBRES = array(
		'idital_qonto'    => 'Qonto',
		'idital_tropipay' => 'TropiPay',
	);

	/** Convierte un importe decimal ("123.45" o 123.45) a céntimos (12345) sin errores de coma flotante. */
	public static function centimos( $importe ): int {
		$texto = trim( (string) $importe );
		if ( ! preg_match( '/^-?\d+(\.\d+)?$/', $texto ) ) {
			return -1;
		}
		return (int) round( (float) $texto * 100 );
	}

	/**
	 * Estados en los que WooCommerce (y plugins como YITH Request a Quote) permiten pagar.
	 */
	public static function estados_pagables( ?WC_Order $pedido = null ): array {
		return array_values( array_unique( (array) apply_filters( 'woocommerce_valid_order_statuses_for_payment', array( 'pending', 'failed' ), $pedido ) ) );
	}

	public static function formatear( int $centimos, string $moneda ): string {
		return number_format( $centimos / 100, 2, ',', '.' ) . ' ' . $moneda;
	}

	/**
	 * @param WC_Order $pedido          Pedido.
	 * @param string   $pasarela        ID de la pasarela que cobró (idital_qonto | idital_tropipay).
	 * @param string   $transaccion     Identificador del cobro en la pasarela.
	 * @param int      $importe_cobrado Céntimos cobrados según la pasarela.
	 * @param string   $moneda_cobrada  Moneda ISO 4217 cobrada.
	 * @param string   $origen          Qué disparó la verificación (webhook, cron...).
	 * @return bool True si el pedido queda pagado con este cobro.
	 */
	public static function confirmar( WC_Order $pedido, string $pasarela, string $transaccion, int $importe_cobrado, string $moneda_cobrada, string $origen ): bool {
		$id     = $pedido->get_id();
		$nombre = self::NOMBRES[ $pasarela ] ?? $pasarela;
		$clave  = 'confirmar_' . $id;

		if ( ! Idital_Pagos_Bloqueo::adquirir( $clave, 60 ) ) {
			return false; // Otro proceso lo está confirmando ahora mismo.
		}

		try {
			$pedido = wc_get_order( $id ); // Siempre datos frescos.
			if ( ! $pedido ) {
				return false;
			}

			$transaccion = substr( sanitize_text_field( $transaccion ), 0, 100 );
			$esperado    = self::centimos( $pedido->get_total() );
			$moneda      = strtoupper( $pedido->get_currency() );

			// 1. Ya estaba pagado.
			if ( $pedido->is_paid() ) {
				if ( $pedido->get_transaction_id() !== $transaccion ) {
					self::anomalia(
						$pedido,
						'duplicado_' . md5( $pasarela . $transaccion ),
						sprintf(
							'⚠️ POSIBLE PAGO DUPLICADO: %1$s ha confirmado un cobro de %2$s (ref. %3$s) en un pedido que ya estaba pagado (ref. %4$s). Revisa ambas pasarelas y reembolsa si procede.',
							$nombre,
							self::formatear( $importe_cobrado, $moneda_cobrada ),
							$transaccion,
							$pedido->get_transaction_id()
						)
					);
				}
				return $pedido->get_transaction_id() === $transaccion;
			}

			// 2. El importe o la moneda no coinciden: nunca se marca como pagado.
			if ( $importe_cobrado !== $esperado || strtoupper( $moneda_cobrada ) !== $moneda ) {
				self::anomalia(
					$pedido,
					'importe_' . md5( $pasarela . $transaccion ),
					sprintf(
						'⚠️ %1$s ha confirmado un cobro de %2$s, pero el total del pedido es %3$s. El pedido NO se ha marcado como pagado: revísalo manualmente (ref. %4$s).',
						$nombre,
						self::formatear( $importe_cobrado, strtoupper( $moneda_cobrada ) ),
						self::formatear( $esperado, $moneda ),
						$transaccion
					),
					'on-hold'
				);
				return false;
			}

			// 3. Estado del pedido incompatible (reembolsado, borrador...).
			$estados_validos = array_merge( array( 'on-hold', 'cancelled' ), self::estados_pagables( $pedido ) );
			if ( ! $pedido->has_status( $estados_validos ) ) {
				self::anomalia(
					$pedido,
					'estado_' . md5( $pasarela . $transaccion ),
					sprintf(
						'⚠️ %1$s ha confirmado un cobro de %2$s (ref. %3$s) pero el pedido está en estado "%4$s". Revísalo manualmente.',
						$nombre,
						self::formatear( $importe_cobrado, $moneda ),
						$transaccion,
						wc_get_order_status_name( $pedido->get_status() )
					)
				);
				return false;
			}

			// 4. Todo correcto.
			$pasarelas = WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : array();
			if ( $pedido->get_payment_method() !== $pasarela && isset( $pasarelas[ $pasarela ] ) ) {
				$pedido->set_payment_method( $pasarelas[ $pasarela ] );
			}
			$pedido->add_order_note(
				sprintf(
					'✅ Pago confirmado por %1$s (%2$s). Importe: %3$s. Referencia: %4$s.',
					$nombre,
					$origen,
					self::formatear( $importe_cobrado, $moneda ),
					$transaccion
				)
			);
			$pedido->payment_complete( $transaccion );

			Idital_Pagos_Log::info(
				'Pedido pagado',
				array(
					'pedido'   => $id,
					'pasarela' => $pasarela,
					'origen'   => $origen,
				)
			);
			return true;
		} finally {
			Idital_Pagos_Bloqueo::liberar( $clave );
		}
	}

	/**
	 * Registra una anomalía una sola vez: nota en el pedido, log y email al administrador.
	 */
	private static function anomalia( WC_Order $pedido, string $marca, string $mensaje, string $nuevo_estado = '' ): void {
		$meta = '_idital_anomalia_' . substr( $marca, 0, 40 );
		if ( $pedido->get_meta( $meta ) ) {
			return;
		}
		$pedido->update_meta_data( $meta, time() );
		if ( $nuevo_estado && ! $pedido->has_status( $nuevo_estado ) ) {
			$pedido->update_status( $nuevo_estado, $mensaje );
		} else {
			$pedido->add_order_note( $mensaje );
		}
		$pedido->save();

		Idital_Pagos_Log::aviso( wp_strip_all_tags( $mensaje ), array( 'pedido' => $pedido->get_id() ) );

		wp_mail(
			get_option( 'admin_email' ),
			sprintf( '[%1$s] Revisión de pago necesaria – pedido %2$s', wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), $pedido->get_order_number() ),
			$mensaje . "\n\n" . $pedido->get_edit_order_url()
		);
	}
}
