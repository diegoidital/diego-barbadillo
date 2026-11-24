<?php
/**
 * Presupuestos: convierte cada solicitud de YITH Request a Quote (versión gratuita)
 * en un pedido de WooCommerce con el estado "Presupuesto solicitado".
 *
 * Flujo:
 *  1. El cliente envía la solicitud → se crea el pedido (no pagable) y el cliente recibe
 *     un email de confirmación; en la página aparece el mensaje de solicitud recibida.
 *  2. La tienda revisa precios/productos en el pedido (es editable en ese estado).
 *  3. Acción "Enviar presupuesto al cliente para pagar" → pasa a "Pendiente de pago" y se
 *     envía al cliente el email con el enlace de pago (Qonto / TropiPay).
 *
 * @package IditalPagos
 */

defined( 'ABSPATH' ) || exit;

final class Idital_Pagos_Presupuestos {

	const ESTADO      = 'presupuesto';      // Sin prefijo "wc-".
	const ESTADO_WC   = 'wc-presupuesto';
	const ACCION      = 'idital_enviar_presupuesto';
	const MAX_POR_IP  = 5;                  // Solicitudes por IP y hora (anti-spam).

	/** Número del presupuesto recién creado que se muestra en la página (una vez). */
	private static string $numero = '';

	public static function init(): void {
		// Prioridad 1: YITH procesa el formulario en "init" (prioridad 10) y el estado tiene que
		// estar registrado antes; si no, WooCommerce lo guarda sin el prefijo "wc-" y no se lista.
		add_action( 'init', array( __CLASS__, 'registrar_estado' ), 1 );
		add_action( 'admin_init', array( __CLASS__, 'reparar_estados' ) );
		add_filter( 'wc_order_statuses', array( __CLASS__, 'anadir_estado' ) );
		add_filter( 'wc_order_is_editable', array( __CLASS__, 'editable' ), 10, 2 );

		// Solo si YITH Request a Quote está activo.
		add_action( 'ywraq_process', array( __CLASS__, 'crear_desde_yith' ), 5 );
		add_filter( 'pre_do_shortcode_tag', array( __CLASS__, 'antes_de_pintar' ), 10, 2 );
		add_filter( 'do_shortcode_tag', array( __CLASS__, 'mensaje_enviado' ), 10, 2 );

		add_filter( 'woocommerce_order_actions', array( __CLASS__, 'acciones' ), 10, 2 );
		add_action( 'woocommerce_order_action_' . self::ACCION, array( __CLASS__, 'enviar_para_pagar' ) );
	}

	/* ------------------------------------------------------------ Estado */

	public static function registrar_estado(): void {
		register_post_status(
			self::ESTADO_WC,
			array(
				'label'                     => 'Presupuesto solicitado',
				'public'                    => false,
				'exclude_from_search'       => true,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: número de pedidos */
				'label_count'               => _n_noop( 'Presupuesto solicitado <span class="count">(%s)</span>', 'Presupuestos solicitados <span class="count">(%s)</span>', 'idital-pagos' ),
			)
		);
	}

	/**
	 * Repara una vez los presupuestos guardados sin prefijo por versiones anteriores (≤ 1.4.0).
	 */
	public static function reparar_estados(): void {
		if ( get_option( 'idital_presupuestos_reparado' ) || ! current_user_can( 'edit_shop_orders' ) ) {
			return;
		}
		global $wpdb;
		$tabla = $wpdb->prefix . 'wc_orders';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tabla ) ) === $tabla ) {
			$wpdb->query( $wpdb->prepare( "UPDATE {$tabla} SET status = %s WHERE status = %s", self::ESTADO_WC, self::ESTADO ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->posts} SET post_status = %s WHERE post_status = %s AND post_type = 'shop_order'", self::ESTADO_WC, self::ESTADO ) );
		wp_cache_flush_group( 'orders' );
		update_option( 'idital_presupuestos_reparado', time(), false );
	}

	public static function anadir_estado( $estados ) {
		$nuevos = array();
		foreach ( $estados as $clave => $nombre ) {
			if ( 'wc-pending' === $clave ) {
				$nuevos[ self::ESTADO_WC ] = 'Presupuesto solicitado';
			}
			$nuevos[ $clave ] = $nombre;
		}
		if ( ! isset( $nuevos[ self::ESTADO_WC ] ) ) {
			$nuevos[ self::ESTADO_WC ] = 'Presupuesto solicitado';
		}
		return $nuevos;
	}

	/** En "Presupuesto solicitado" se pueden ajustar precios y productos. */
	public static function editable( $editable, $pedido ) {
		return ( $pedido instanceof WC_Order && $pedido->has_status( self::ESTADO ) ) ? true : $editable;
	}

	/* ----------------------------------------------- Solicitud desde YITH */

	/**
	 * @param array $args user_name, user_email, user_message, raq_content (de YITH).
	 */
	public static function crear_desde_yith( $args ): void {
		if ( ! is_array( $args ) || ! function_exists( 'wc_create_order' ) ) {
			return;
		}
		$email = sanitize_email( (string) ( $args['user_email'] ?? '' ) );
		$items = (array) ( $args['raq_content'] ?? array() );
		if ( ! is_email( $email ) || ! $items ) {
			return;
		}
		// Anti-spam: el formulario es público. Si se supera el límite, YITH sigue enviando su
		// email al administrador, pero no se crea el pedido.
		if ( ! Idital_Pagos_Limite::permitir( 'presupuesto', self::MAX_POR_IP, HOUR_IN_SECONDS ) ) {
			Idital_Pagos_Log::aviso( 'Solicitud de presupuesto sin pedido: límite por IP superado', array( 'ip' => Idital_Pagos_Limite::ip() ) );
			return;
		}

		try {
			$usuario = get_current_user_id();
			$pedido  = wc_create_order(
				array(
					'customer_id' => ( $usuario && wp_get_current_user()->user_email === $email ) ? $usuario : 0,
					'created_via' => 'presupuesto',
				)
			);
			if ( is_wp_error( $pedido ) ) {
				throw new Exception( $pedido->get_error_message() );
			}

			$anadidos = 0;
			foreach ( array_slice( $items, 0, 100 ) as $item ) {
				$id       = absint( $item['variation_id'] ?? 0 ) ?: absint( $item['product_id'] ?? 0 );
				$producto = $id ? wc_get_product( $id ) : null;
				$cantidad = max( 1, min( 10000, absint( $item['quantity'] ?? 1 ) ) );
				if ( ! $producto || ! $producto->exists() || 'trash' === $producto->get_status() ) {
					continue;
				}
				$atributos = array();
				foreach ( (array) ( $item['variations'] ?? array() ) as $clave => $valor ) {
					$atributos[ sanitize_title( (string) $clave ) ] = sanitize_text_field( (string) $valor );
				}
				$pedido->add_product( $producto, $cantidad, $atributos ? array( 'variation' => $atributos ) : array() );
				++$anadidos;
			}
			if ( 0 === $anadidos ) {
				$pedido->delete( true );
				return;
			}

			$nombre = mb_substr( sanitize_text_field( (string) ( $args['user_name'] ?? '' ) ), 0, 100 );
			$pedido->set_billing_first_name( $nombre );
			$pedido->set_billing_email( $email );
			$mensaje = trim( wp_strip_all_tags( str_replace( array( '<br />', '<br>', '<br/>' ), "\n", (string) ( $args['user_message'] ?? '' ) ) ) );
			if ( '' !== $mensaje ) {
				$pedido->set_customer_note( mb_substr( $mensaje, 0, 2000 ) );
			}
			$pedido->update_meta_data( '_idital_presupuesto', time() );
			$pedido->calculate_totals();
			$pedido->set_status( self::ESTADO, 'Presupuesto solicitado desde la web. Revisa precios y pulsa "Enviar presupuesto al cliente para pagar".' );
			$pedido->save();

			if ( WC()->session ) {
				if ( ! WC()->session->has_session() ) {
					WC()->session->set_customer_session_cookie( true );
				}
				WC()->session->set( 'idital_presupuesto', $pedido->get_order_number() );
			}
			self::email_confirmacion( $pedido );

			// La solicitud ya está guardada como pedido: aunque falle el email de YITH al
			// administrador, la lista se vacía y la solicitud cuenta como enviada.
			add_filter( 'ywraq_check_send_email_request_a_quote', '__return_true' );

			Idital_Pagos_Log::info( 'Presupuesto creado desde YITH', array( 'pedido' => $pedido->get_id() ) );
		} catch ( Throwable $e ) {
			// Nunca se interrumpe la solicitud: YITH sigue enviando su email al administrador.
			Idital_Pagos_Log::error( 'No se pudo crear el presupuesto', array( 'error' => $e->getMessage() ) );
		}
	}

	/** Email de confirmación al cliente, con el diseño de los emails de WooCommerce. */
	private static function email_confirmacion( WC_Order $pedido ): void {
		$mailer = WC()->mailer();
		$tienda = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$asunto = sprintf( 'Hemos recibido tu solicitud de presupuesto (nº %s)', $pedido->get_order_number() );

		$filas = '';
		foreach ( $pedido->get_items() as $item ) {
			$filas .= '<li>' . esc_html( $item->get_name() ) . ' × ' . esc_html( (string) $item->get_quantity() ) . '</li>';
		}
		$cuerpo = '<p>' . sprintf( 'Hola %s,', esc_html( $pedido->get_billing_first_name() ) ) . '</p>'
			. '<p>' . sprintf( 'Hemos recibido tu solicitud de presupuesto <strong>nº %s</strong> con estos productos:', esc_html( $pedido->get_order_number() ) ) . '</p>'
			. '<ul>' . $filas . '</ul>'
			. '<p>La revisaremos y te enviaremos el presupuesto definitivo a este correo, con un enlace para pagarlo de forma segura con Qonto o TropiPay.</p>'
			. '<p>' . esc_html( $tienda ) . '</p>';

		$mailer->send( $pedido->get_billing_email(), $asunto, $mailer->wrap_message( 'Solicitud de presupuesto recibida', $cuerpo ) );
	}

	/**
	 * Antes de que YITH pinte la página tras enviar: si se creó el presupuesto, se retiran
	 * los avisos de YITH para que solo se vea un mensaje (el nuestro, con el número).
	 */
	public static function antes_de_pintar( $resultado, $etiqueta ) {
		if ( 'yith_ywraq_request_quote' !== $etiqueta || ! isset( $_GET['sent'] ) || ! WC()->session ) { // phpcs:ignore WordPress.Security.NonceVerification
			return $resultado;
		}
		self::$numero = (string) WC()->session->get( 'idital_presupuesto' );
		WC()->session->set( 'idital_presupuesto', null );
		if ( '' !== self::$numero && function_exists( 'YITH_Request_Quote' ) && YITH_Request_Quote()->session_class ) {
			YITH_Request_Quote()->session_class->set( 'yith_ywraq_notices', array() );
		}
		return $resultado;
	}

	/** Mensaje en la página de presupuesto después de enviar (YITH la deja vacía). */
	public static function mensaje_enviado( $salida, $etiqueta ) {
		if ( 'yith_ywraq_request_quote' !== $etiqueta || ! isset( $_GET['sent'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return $salida;
		}
		do_action( 'litespeed_control_set_nocache', 'idital-pagos: presupuesto enviado' );
		$numero = self::$numero;
		if ( '' === $numero ) {
			return $salida; // No se creó presupuesto (p. ej. límite anti-spam): se dejan los avisos de YITH.
		}
		$html = '<div class="idital-presupuesto-ok" role="status" style="border:2px solid #5a9b3a;border-radius:10px;padding:18px 22px;margin:0 0 1.5em;background:#f4faf0">'
			. '<p style="margin:0 0 .4em;font-size:1.2em;font-weight:600">✅ ' . esc_html__( 'Solicitud de presupuesto enviada correctamente', 'idital-pagos' ) . '</p>'
			. '<p style="margin:0">' . ( '' !== $numero ? sprintf( esc_html__( 'Tu número de solicitud es el %s.', 'idital-pagos' ), '<strong>' . esc_html( $numero ) . '</strong>' ) . ' ' : '' )
			. esc_html__( 'Te hemos enviado una confirmación por email. Revisaremos tu solicitud y te mandaremos el presupuesto con un enlace para pagarlo.', 'idital-pagos' ) . '</p>'
			. '</div>';
		return $html . $salida;
	}

	/* ---------------------------------------------------- Acción del pedido */

	public static function acciones( $acciones, $pedido = null ) {
		if ( ! $pedido instanceof WC_Order ) {
			global $theorder;
			$pedido = $theorder;
		}
		if ( $pedido instanceof WC_Order && ( $pedido->has_status( self::ESTADO ) || $pedido->has_status( 'pending' ) ) ) {
			$acciones[ self::ACCION ] = 'Enviar presupuesto al cliente para pagar';
		}
		return $acciones;
	}

	public static function enviar_para_pagar( $pedido ): void {
		if ( ! $pedido instanceof WC_Order || ! current_user_can( 'edit_shop_orders' ) ) {
			return;
		}
		if ( $pedido->get_total() <= 0 ) {
			$pedido->add_order_note( '⚠️ No se ha enviado el presupuesto: el total del pedido es 0.' );
			return;
		}
		if ( $pedido->has_status( self::ESTADO ) ) {
			$pedido->update_status( 'pending', 'Presupuesto aprobado.' );
		}
		WC()->payment_gateways();
		WC()->mailer()->customer_invoice( $pedido );
		$pedido->add_order_note( sprintf( 'Presupuesto enviado al cliente (%s) con el enlace de pago.', $pedido->get_billing_email() ), false, true );
	}
}
