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

	private function cabeceras(): array {
		$cabeceras = array(
			// Autenticación por clave de API: "slug-organizacion:clave-secreta".
			'Authorization' => Idital_Pagos_Config::get( 'IDITAL_QONTO_LOGIN' ) . ':' . Idital_Pagos_Config::get( 'IDITAL_QONTO_SECRET_KEY' ),
		);
		if ( Idital_Pagos_Config::qonto_sandbox() && '' !== Idital_Pagos_Config::get( 'IDITAL_QONTO_STAGING_TOKEN' ) ) {
			$cabeceras['X-Qonto-Staging-Token'] = Idital_Pagos_Config::get( 'IDITAL_QONTO_STAGING_TOKEN' );
		}
		return $cabeceras;
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

		$respuesta = Idital_Pagos_Http::json( 'POST', Idital_Pagos_Config::qonto_base() . '/v2/payment_links', $this->cabeceras(), $cuerpo );
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
		$respuesta = Idital_Pagos_Http::json( 'GET', Idital_Pagos_Config::qonto_base() . '/v2/payment_links/' . rawurlencode( $id ), $this->cabeceras() );
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
		try {
			$r = Idital_Pagos_Http::json( 'GET', $base . '/v2/organization', $this->cabeceras() );
			if ( 200 === $r['codigo'] ) {
				$org         = $r['datos']['organization'] ?? array();
				$resultado[] = array( 'ok', 'Clave válida. Organización: ' . sanitize_text_field( (string) ( $org['legal_name'] ?? $org['slug'] ?? '?' ) ) );
			} elseif ( 401 === $r['codigo'] ) {
				return array( array( 'error', 'Qonto rechaza la clave (401). Revisa el identificador, la clave secreta y el entorno (' . ( Idital_Pagos_Config::qonto_sandbox() ? 'sandbox' : 'producción' ) . ').' ) );
			} else {
				return array( array( 'error', 'Respuesta inesperada de Qonto (HTTP ' . $r['codigo'] . ').' ) );
			}

			$r = Idital_Pagos_Http::json( 'GET', $base . '/v2/payment_links/connections', $this->cabeceras() );
			$c = $r['datos']['connection'] ?? $r['datos'];
			$e = is_array( $c ) ? strtolower( (string) ( $c['status'] ?? $c['connection_status'] ?? '' ) ) : '';
			if ( 200 === $r['codigo'] && in_array( $e, array( 'enabled', 'active', 'connected' ), true ) ) {
				$resultado[] = array( 'ok', 'Cuenta conectada a Mollie (estado: ' . sanitize_text_field( $e ) . '). Se pueden crear links de pago.' );
			} elseif ( in_array( $r['codigo'], array( 200, 404 ), true ) ) {
				$resultado[] = array( 'aviso', 'La cuenta NO está conectada a Mollie' . ( $e ? ' (estado: ' . sanitize_text_field( $e ) . ')' : '' ) . '. Hay que completar la conexión desde Qonto antes de poder cobrar.' );
			} else {
				$resultado[] = array( 'error', 'No se pudo consultar la conexión con Mollie (HTTP ' . $r['codigo'] . '). ¿La clave tiene permiso para links de pago?' );
			}

			$r = Idital_Pagos_Http::json( 'GET', $base . '/v2/payment_links/payment_methods', $this->cabeceras() );
			if ( 200 === $r['codigo'] ) {
				$metodos = $r['datos']['payment_methods'] ?? ( array_is_list( $r['datos'] ) ? $r['datos'] : array() );
				$nombres = array();
				foreach ( (array) $metodos as $m ) {
					$nombres[] = sanitize_text_field( is_array( $m ) ? (string) ( $m['name'] ?? $m['type'] ?? '' ) : (string) $m );
				}
				$resultado[] = array( 'ok', 'Métodos de pago disponibles: ' . ( implode( ', ', array_filter( $nombres ) ) ?: 'ninguno' ) );
			}
		} catch ( Idital_Pagos_Exception $e ) {
			$resultado[] = array( 'error', 'No hay conexión con Qonto: ' . $e->getMessage() );
		}
		return $resultado;
	}

	public function desactivar_link( string $id ): bool {
		if ( ! self::id_valido( $id ) ) {
			return false;
		}
		try {
			$respuesta = Idital_Pagos_Http::json( 'PATCH', Idital_Pagos_Config::qonto_base() . '/v2/payment_links/' . rawurlencode( $id ) . '/deactivate', $this->cabeceras() );
			return $respuesta['codigo'] >= 200 && $respuesta['codigo'] < 300;
		} catch ( Idital_Pagos_Exception $e ) {
			Idital_Pagos_Log::aviso( 'No se pudo desactivar un link de Qonto', array( 'error' => $e->getMessage() ) );
			return false;
		}
	}
}
