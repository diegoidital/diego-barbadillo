<?php
/**
 * Recuadro "Enlaces de pago" en la ficha del pedido: genera el link de TropiPay o Qonto
 * desde el panel para enviárselo al cliente directamente (WhatsApp, email…).
 *
 * @package IditalPagos
 */

defined( 'ABSPATH' ) || exit;

final class Idital_Pagos_Enlaces_Admin {

	const ACCION = 'idital_generar_link';

	public static function init(): void {
		add_action( 'add_meta_boxes', array( __CLASS__, 'registrar' ) );
		add_action( 'admin_post_' . self::ACCION, array( __CLASS__, 'generar' ) );
	}

	public static function registrar(): void {
		foreach ( array( 'shop_order', function_exists( 'wc_get_page_screen_id' ) ? wc_get_page_screen_id( 'shop-order' ) : 'woocommerce_page_wc-orders' ) as $pantalla ) {
			add_meta_box( 'idital-enlaces-pago', 'Enlaces de pago', array( __CLASS__, 'pintar' ), $pantalla, 'side', 'high' );
		}
	}

	/**
	 * @param WP_Post|WC_Order $objeto Pedido (HPOS) o entrada (almacenamiento clásico).
	 */
	public static function pintar( $objeto ): void {
		$pedido = $objeto instanceof WP_Post ? wc_get_order( $objeto->ID ) : $objeto;
		if ( ! $pedido instanceof WC_Order ) {
			return;
		}

		if ( $pedido->is_paid() ) {
			echo '<p>✅ ' . esc_html__( 'Pedido pagado.', 'idital-pagos' ) . '</p>';
			return;
		}
		if ( ! $pedido->needs_payment() ) {
			echo '<p>' . esc_html__( 'El pedido no está pendiente de pago. Si es un presupuesto, usa antes "Enviar presupuesto al cliente para pagar" o pon el estado "Pendiente de pago" y guarda.', 'idital-pagos' ) . '</p>';
			return;
		}

		$hay = false;
		foreach ( Idital_Pagos_Plugin::pasarelas() as $id => $pasarela ) {
			if ( ! $pasarela->configurado() ) {
				continue;
			}
			$hay   = true;
			$corto = 'idital_qonto' === $id ? 'qonto' : 'tropipay';
			$url   = (string) $pedido->get_meta( '_idital_' . $corto . '_link_url' );
			$boton = wp_nonce_url(
				add_query_arg(
					array(
						'action'   => self::ACCION,
						'pedido'   => $pedido->get_id(),
						'pasarela' => $id,
					),
					admin_url( 'admin-post.php' )
				),
				self::ACCION . '_' . $pedido->get_id()
			);

			echo '<div style="margin:0 0 14px">';
			echo '<p style="margin:0 0 6px"><strong>' . esc_html( $pasarela->method_title ) . '</strong></p>';
			if ( '' !== $url ) {
				echo '<input type="text" readonly class="widefat idital-link" value="' . esc_attr( $url ) . '" onclick="this.select()" style="margin-bottom:6px">';
				echo '<button type="button" class="button button-small" onclick="var i=this.parentNode.querySelector(\'.idital-link\');i.select();navigator.clipboard&&navigator.clipboard.writeText(i.value);this.textContent=\'Copiado ✓\'">Copiar</button> ';
				echo '<a class="button button-small" target="_blank" rel="noopener noreferrer" href="' . esc_url( $url ) . '">Abrir</a> ';
			}
			echo '<a class="button button-small' . ( '' === $url ? ' button-primary' : '' ) . '" href="' . esc_url( $boton ) . '">' . esc_html( '' === $url ? sprintf( 'Generar link de %s', $pasarela->method_title ) : 'Comprobar / regenerar' ) . '</a>';
			echo '</div>';
		}

		if ( ! $hay ) {
			echo '<p>' . esc_html__( 'No hay ninguna pasarela configurada.', 'idital-pagos' ) . '</p>';
			return;
		}
		echo '<p class="description">' . esc_html__( 'Solo puede haber un link activo por pedido: al generar uno, el de la otra pasarela se anula. El importe es siempre el total del pedido.', 'idital-pagos' ) . '</p>';
	}

	public static function generar(): void {
		$id = isset( $_GET['pedido'] ) ? absint( $_GET['pedido'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		check_admin_referer( self::ACCION . '_' . $id );
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( esc_html__( 'No tienes permiso para hacer esto.', 'idital-pagos' ), 403 );
		}

		$pedido    = wc_get_order( $id );
		$pasarelas = Idital_Pagos_Plugin::pasarelas();
		$pid       = isset( $_GET['pasarela'] ) ? sanitize_key( wp_unslash( $_GET['pasarela'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! $pedido instanceof WC_Order || ! isset( $pasarelas[ $pid ] ) ) {
			wp_die( esc_html__( 'Pedido o pasarela no válidos.', 'idital-pagos' ), 400 );
		}

		try {
			$url    = $pasarelas[ $pid ]->generar_link_gestion( $pedido );
			$lineas = array( array( 'ok', sprintf( 'Link de %1$s listo para el pedido %2$s. Cópialo en el recuadro "Enlaces de pago": %3$s', $pasarelas[ $pid ]->method_title, $pedido->get_order_number(), $url ) ) );
		} catch ( Throwable $e ) {
			Idital_Pagos_Log::error(
				'No se pudo generar el link desde el panel',
				array(
					'pedido'   => $id,
					'pasarela' => $pid,
					'error'    => $e->getMessage(),
				)
			);
			$lineas = array( array( 'error', 'No se pudo generar el link: ' . $e->getMessage() ) );
		}

		set_transient(
			'idital_diag_' . get_current_user_id(),
			array(
				'titulo'   => $pasarelas[ $pid ]->method_title,
				'cabecera' => 'Enlace de pago de ' . $pasarelas[ $pid ]->method_title,
				'lineas'   => $lineas,
			),
			5 * MINUTE_IN_SECONDS
		);
		wp_safe_redirect( $pedido->get_edit_order_url() );
		exit;
	}
}
