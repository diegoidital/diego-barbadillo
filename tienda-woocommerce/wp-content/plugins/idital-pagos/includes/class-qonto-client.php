<?php
/**
 * Cliente de la API de Payment Links de Qonto.
 *
 * Documentación: https://docs.qonto.com/api-reference/business-api/getting-paid/payment-links/create.md
 *
 * @package IditalPagos
 */

defined( 'ABSPATH' ) || exit;

final class Idital_Pagos_Qonto_Client {

	const DOMINIOS_LINK = array( 'qonto.com', 'qonto.co' );

	/**
	 * @throws Idital_Pagos_Exception Si la tienda no está conectada con Qonto.
	 */
	private function cabeceras(): array {
		$cabeceras = array(
			// Los links de pago exigen OAuth 2.0 (la clave API solo permite lectura).
			'Authorization' => 'Bearer ' . Idital_Pagos_Qonto_Oauth::token(),
		);
		if ( Idital_Pagos_Config::qonto_sandbox() && '' !== Idital_Pagos_Config::get( 'IDITAL_QONTO_STAGING_TOKEN' ) ) {
			$cabeceras['X-Qonto-Staging-Token'] = Idital_Pagos_Config::get( 'IDITAL_QONTO_STAGING_TOKEN' );
		}
		return $cabeceras;
	}

	/**
	 * Petición autenticada. Si Qonto rechaza el token (401), lo renueva y reintenta una vez.
	 *
	 * @throws Idital_Pagos_Exception Si no hay conexión o no se puede renovar.
	 */
	private function pedir( string $metodo, string $url, ?array $cuerpo = null ): array {
		$respuesta = Idital_Pagos_Http::json( $metodo, $url, $this->cabeceras(), $cuerpo );
		if ( 401 === $respuesta['codigo'] ) {
			Idital_Pagos_Qonto_Oauth::renovar( true );
			$respuesta = Idital_Pagos_Http::json( $metodo, $url, $this->cabeceras(), $cuerpo );
		}
		return $respuesta;
	}

	private static function id_valido( string $id ): bool {
		return (bool) preg_match( '/^[A-Za-z0-9-]{8,64}$/', $id );
	}

	/**
	 * Crea un link de pago de un solo uso por el total del pedido.
	 *
	 * @param WC_Order $pedido  Pedido.
	 * @param string[] $metodos Métodos de pago permitidos.
	 * @return array{id:string,url:string,importe:int,moneda:string}
	 * @throws Idital_Pagos_Exception Si Qonto no crea el link o la respuesta no es coherente.
	 */
	public function crear_link( WC_Order $pedido, array $metodos ): array {
		$moneda  = strtoupper( $pedido->get_currency() );
		$importe = Idital_Pagos_Confirmacion::centimos( $pedido->get_total() );

		$cuerpo = array(
			'payment_link' => array(
				'potential_payment_methods' => array_values( $metodos ),
				'reusable'                  => false,
				'items'                     => array(
					array(
						// Una sola línea por el total (IVA incluido) para que el importe sea exacto.
						'title'       => sprintf( 'Pedido nº %s', $pedido->get_order_number() ),
						'description' => mb_substr( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), 0, 120 ),
						'type'        => 'service',
						'quantity'    => 1,
						'unit_price'  => array(
							'value'    => number_format( $importe / 100, 2, '.', '' ),
							'currency' => $moneda,
						),
						'vat_rate'    => '0',
					),
				),
			),
		);

		$respuesta = $this->pedir( 'POST', Idital_Pagos_Config::qonto_base() . '/v2/payment_links', $cuerpo );
		$link      = $respuesta['datos']['payment_link'] ?? null;

		if ( ! in_array( $respuesta['codigo'], array( 200, 201 ), true ) || ! is_array( $link ) ) {
			throw new Idital_Pagos_Exception( 'Qonto no ha creado el link (HTTP ' . $respuesta['codigo'] . '): ' . wp_json_encode( $respuesta['datos']['errors'] ?? array() ) );
		}

		$id  = (string) ( $link['id'] ?? '' );
		$url = (string) ( $link['url'] ?? '' );
		if ( ! self::id_valido( $id ) || ! Idital_Pagos_Config::url_permitida( $url, self::DOMINIOS_LINK ) ) {
			throw new Idital_Pagos_Exception( 'Respuesta de Qonto no válida (id o url inesperados).' );
		}

		$importe_link = Idital_Pagos_Confirmacion::centimos( $link['amount']['value'] ?? '' );
		$moneda_link  = strtoupper( (string) ( $link['amount']['currency'] ?? '' ) );
		if ( $importe_link !== $importe || $moneda_link !== $moneda ) {
			// No se enseña al cliente un link por un importe distinto al del pedido.
			$this->desactivar_link( $id );
			throw new Idital_Pagos_Exception( 'El importe del link de Qonto no coincide con el del pedido.' );
		}

		return array(
			'id'      => $id,
			'url'     => $url,
			'importe' => $importe,
			'moneda'  => $moneda,
		);
	}

	/**
	 * @throws Idital_Pagos_Exception Si no se puede leer el link.
	 */
	public function obtener_link( string $id ): array {
		if ( ! self::id_valido( $id ) ) {
			throw new Idital_Pagos_Exception( 'ID de link no válido.' );
		}
		$respuesta = $this->pedir( 'GET', Idital_Pagos_Config::qonto_base() . '/v2/payment_links/' . rawurlencode( $id ) );
		if ( 200 !== $respuesta['codigo'] || ! is_array( $respuesta['datos']['payment_link'] ?? null ) ) {
			throw new Idital_Pagos_Exception( 'No se ha podido consultar el link en Qonto (HTTP ' . $respuesta['codigo'] . ').' );
		}
		return $respuesta['datos']['payment_link'];
	}

	/**
	 * Diagnóstico de solo lectura para el botón "Probar conexión".
	 *
	 * @return array<int, array{0: string, 1: string}> Lista de [ 'ok'|'aviso'|'error', mensaje ].
	 */
	public function diagnostico(): array {
		$base      = Idital_Pagos_Config::qonto_base();
		$resultado = array();
		$estado    = Idital_Pagos_Qonto_Oauth::estado();
		if ( ! $estado['conectado'] ) {
			return array( array( 'error', $estado['roto'] ? 'Qonto ha retirado el acceso de la tienda: vuelve a pulsar "Conectar con Qonto".' : 'La tienda aún no está conectada: pulsa "Conectar con Qonto".' ) );
		}
		$resultado[] = array( 'ok', 'Tienda conectada por OAuth' . ( $estado['alcance'] ? ' (permisos: ' . sanitize_text_field( $estado['alcance'] ) . ')' : '' ) . '.' );
		try {
			$r = $this->pedir( 'GET', $base . '/v2/organization' );
			if ( 200 === $r['codigo'] ) {
				$org         = $r['datos']['organization'] ?? array();
				$resultado[] = array( 'ok', 'Acceso válido. Organización: ' . sanitize_text_field( (string) ( $org['legal_name'] ?? $org['slug'] ?? '?' ) ) );
			} elseif ( in_array( $r['codigo'], array( 401, 403 ), true ) ) {
				$resultado[] = array( 'error', 'Qonto rechaza el acceso (HTTP ' . $r['codigo'] . '). Vuelve a conectar y revisa el entorno (' . ( Idital_Pagos_Config::qonto_sandbox() ? 'sandbox' : 'producción' ) . ').' );
				return $resultado;
			} else {
				$resultado[] = array( 'error', 'Respuesta inesperada de Qonto (HTTP ' . $r['codigo'] . ').' );
				return $resultado;
			}

			$r = $this->pedir( 'GET', $base . '/v2/payment_links/connections' );
			$c = $r['datos']['connection'] ?? $r['datos'];
			$e = is_array( $c ) ? strtolower( (string) ( $c['status'] ?? $c['connection_status'] ?? '' ) ) : '';
			if ( 200 === $r['codigo'] && in_array( $e, array( 'enabled', 'active', 'connected' ), true ) ) {
				$resultado[] = array( 'ok', 'Cuenta conectada a Mollie (estado: ' . sanitize_text_field( $e ) . '). Se pueden crear links de pago.' );
			} elseif ( 200 === $r['codigo'] && 'pending' === $e ) {
				$resultado[] = array( 'aviso', 'Conexión con Mollie en revisión (estado: pending). Mollie está terminando de verificar la cuenta; no hay que hacer nada más, vuelve a probar más tarde.' );
			} elseif ( in_array( $r['codigo'], array( 200, 404 ), true ) ) {
				$resultado[] = array( 'aviso', 'La cuenta NO está conectada a Mollie' . ( $e ? ' (estado: ' . sanitize_text_field( $e ) . ')' : '' ) . '. Rellena teléfono y descripción del negocio más abajo, guarda y pulsa "Conectar cobros (Mollie)".' );
			} else {
				$resultado[] = array( 'error', 'No se pudo consultar la conexión con Mollie (HTTP ' . $r['codigo'] . '). ¿La clave tiene permiso para links de pago?' );
			}

			$r = $this->pedir( 'GET', $base . '/v2/payment_links/payment_methods' );
			if ( 200 === $r['codigo'] ) {
				$metodos = $r['datos']['payment_methods'] ?? ( array_is_list( $r['datos'] ) ? $r['datos'] : array() );
				$nombres = array();
				foreach ( (array) $metodos as $m ) {
					$nombres[] = sanitize_text_field( is_array( $m ) ? (string) ( $m['name'] ?? $m['type'] ?? '' ) : (string) $m );
				}
				$nombres     = array_filter( $nombres );
				$resultado[] = $nombres
					? array( 'ok', 'Métodos de pago disponibles: ' . implode( ', ', $nombres ) )
					: array( 'aviso', 'Métodos de pago disponibles: ninguno (aparecerán cuando la conexión con Mollie esté activa).' );
			}
		} catch ( Idital_Pagos_Exception $e ) {
			$resultado[] = array( 'error', 'No hay conexión con Qonto: ' . $e->getMessage() );
		}
		return $resultado;
	}

	/**
	 * Inicia la conexión de la cuenta con el proveedor de links de pago (Mollie).
	 * Devuelve la URL a la que hay que llevar al gestor para completarla.
	 *
	 * @throws Idital_Pagos_Exception Si falta algún dato o Qonto lo rechaza.
	 */
	public function conectar_proveedor( string $telefono, string $descripcion, string $url_vuelta ): array {
		$base = Idital_Pagos_Config::qonto_base();

		// Cuenta bancaria donde se abonarán los cobros: la principal activa de la organización.
		$r = $this->pedir( 'GET', $base . '/v2/organization' );
		if ( 200 !== $r['codigo'] ) {
			throw new Idital_Pagos_Exception( 'No se pudieron leer las cuentas de Qonto (HTTP ' . $r['codigo'] . ').' );
		}
		$cuenta = '';
		foreach ( (array) ( $r['datos']['organization']['bank_accounts'] ?? array() ) as $c ) {
			if ( ! is_array( $c ) || 'active' !== ( $c['status'] ?? 'active' ) || ! empty( $c['is_external_account'] ) || empty( $c['id'] ) ) {
				continue;
			}
			if ( '' === $cuenta || ! empty( $c['main'] ) ) {
				$cuenta = (string) $c['id'];
			}
			if ( ! empty( $c['main'] ) ) {
				break;
			}
		}
		if ( ! self::id_valido( $cuenta ) ) {
			throw new Idital_Pagos_Exception( 'La organización de Qonto no tiene ninguna cuenta bancaria activa.' );
		}

		$r = $this->pedir(
			'POST',
			$base . '/v2/payment_links/connections',
			array(
				'partner_callback_url' => $url_vuelta,
				'user_bank_account_id' => $cuenta,
				'user_phone_number'    => $telefono,
				'user_website_url'     => home_url( '/' ),
				'business_description' => $descripcion,
			)
		);
		if ( ! in_array( $r['codigo'], array( 200, 201 ), true ) ) {
			$errores = array();
			foreach ( (array) ( $r['datos']['errors'] ?? array() ) as $err ) {
				$errores[] = sanitize_text_field( (string) ( $err['detail'] ?? $err['code'] ?? '' ) );
			}
			throw new Idital_Pagos_Exception( 'Qonto no ha iniciado la conexión (HTTP ' . $r['codigo'] . ')' . ( $errores ? ': ' . implode( '; ', array_filter( $errores ) ) : '.' ) );
		}
		$estado = strtolower( (string) ( $r['datos']['status'] ?? '' ) );
		$url    = (string) ( $r['datos']['connection_location'] ?? '' );
		if ( 'enabled' !== $estado && ! Idital_Pagos_Config::url_permitida( $url, array( 'mollie.com', 'qonto.com', 'qonto.co' ) ) ) {
			throw new Idital_Pagos_Exception( 'Qonto ha devuelto una dirección de conexión inesperada.' );
		}
		return array(
			'estado' => sanitize_key( $estado ),
			'url'    => $url,
		);
	}

	public function desactivar_link( string $id ): bool {
		if ( ! self::id_valido( $id ) ) {
			return false;
		}
		try {
			$respuesta = $this->pedir( 'PATCH', Idital_Pagos_Config::qonto_base() . '/v2/payment_links/' . rawurlencode( $id ) . '/deactivate' );
			return $respuesta['codigo'] >= 200 && $respuesta['codigo'] < 300;
		} catch ( Idital_Pagos_Exception $e ) {
			Idital_Pagos_Log::aviso( 'No se pudo desactivar un link de Qonto', array( 'error' => $e->getMessage() ) );
			return false;
		}
	}
}
