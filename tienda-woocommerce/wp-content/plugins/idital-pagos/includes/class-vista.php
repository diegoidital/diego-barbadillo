<?php
/**
 * Caja "Completa tu pago" en la página del pedido (gracias / Mi cuenta).
 *
 * @package IditalPagos
 */

defined( 'ABSPATH' ) || exit;

final class Idital_Pagos_Vista {

	public static function caja( $order_id ): void {
		$pedido = wc_get_order( $order_id );
		if ( ! $pedido || $pedido->is_paid() || ! ( $pedido->needs_payment() || $pedido->has_status( 'on-hold' ) ) ) {
			return;
		}
		// Esta página nunca debe salir de la caché (LiteSpeed Cache).
		do_action( 'litespeed_control_set_nocache', 'idital-pagos: pedido pendiente de pago' );

		$metodo = $pedido->get_payment_method();
		if ( ! in_array( $metodo, array( 'idital_qonto', 'idital_tropipay' ), true ) ) {
			return;
		}
		$corto  = 'idital_qonto' === $metodo ? 'qonto' : 'tropipay';
		$nombre = 'qonto' === $corto ? 'Qonto' : 'TropiPay';
		$url    = (string) $pedido->get_meta( '_idital_' . $corto . '_link_url' );

		$transferencia = array();
		$pasarelas     = Idital_Pagos_Plugin::pasarelas();
		if ( 'qonto' === $corto && isset( $pasarelas['idital_qonto'] ) ) {
			$transferencia = $pasarelas['idital_qonto']->datos_transferencia();
		}
		if ( '' === $url && ! $transferencia ) {
			return;
		}

		wp_enqueue_script( 'idital-pagos-estado', IDITAL_PAGOS_URL . 'assets/js/estado-pago.js', array(), IDITAL_PAGOS_VERSION, true );
		?>
		<section class="idital-pago"
			data-endpoint="<?php echo esc_url( rest_url( 'idital-pagos/v1/estado' ) ); ?>"
			data-pedido="<?php echo esc_attr( (string) $pedido->get_id() ); ?>"
			data-clave="<?php echo esc_attr( $pedido->get_order_key() ); ?>">
			<h2 class="idital-pago__titulo"><?php esc_html_e( 'Completa tu pago', 'idital-pagos' ); ?></h2>

			<?php if ( '' !== $url ) : ?>
				<p>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: Qonto o TropiPay */
							__( 'Hemos generado tu enlace de pago seguro de %s. Se abrirá en una pestaña nueva; esta página se actualizará sola cuando recibamos el pago.', 'idital-pagos' ),
							$nombre
						)
					);
					?>
				</p>
				<p>
					<a class="button alt idital-pago__boton" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer">
						<?php echo esc_html( sprintf( /* translators: %s: pasarela */ __( 'Pagar ahora con %s', 'idital-pagos' ), $nombre ) ); ?>
					</a>
				</p>
				<p class="idital-pago__importe">
					<?php echo esc_html__( 'Importe:', 'idital-pagos' ) . ' ' . wp_kses_post( $pedido->get_formatted_order_total() ); ?>
				</p>
			<?php endif; ?>

			<?php if ( $transferencia ) : ?>
				<div class="idital-pago__transferencia">
					<h3><?php esc_html_e( '¿Prefieres pagar por transferencia?', 'idital-pagos' ); ?></h3>
					<dl>
						<?php if ( $transferencia['titular'] ) : ?>
							<dt><?php esc_html_e( 'Titular', 'idital-pagos' ); ?></dt><dd><?php echo esc_html( $transferencia['titular'] ); ?></dd>
						<?php endif; ?>
						<dt>IBAN</dt><dd><code><?php echo esc_html( $transferencia['iban'] ); ?></code></dd>
						<?php if ( $transferencia['bic'] ) : ?>
							<dt>BIC</dt><dd><code><?php echo esc_html( $transferencia['bic'] ); ?></code></dd>
						<?php endif; ?>
						<dt><?php esc_html_e( 'Concepto', 'idital-pagos' ); ?></dt><dd><code><?php echo esc_html( sprintf( 'Pedido %s', $pedido->get_order_number() ) ); ?></code></dd>
						<dt><?php esc_html_e( 'Importe', 'idital-pagos' ); ?></dt><dd><?php echo wp_kses_post( $pedido->get_formatted_order_total() ); ?></dd>
					</dl>
					<p><?php esc_html_e( 'Confirmaremos tu pedido en cuanto recibamos la transferencia (normalmente 1-2 días hábiles).', 'idital-pagos' ); ?></p>
				</div>
			<?php endif; ?>

			<p class="idital-pago__estado" role="status" aria-live="polite"><?php esc_html_e( 'Esperando la confirmación del pago…', 'idital-pagos' ); ?></p>
		</section>
		<?php
	}
}
