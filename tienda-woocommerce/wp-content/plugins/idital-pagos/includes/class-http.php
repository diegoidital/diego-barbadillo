<?php
/**
 * Cliente HTTP endurecido para hablar con las pasarelas.
 *
 * @package IditalPagos
 */

defined( 'ABSPATH' ) || exit;

final class Idital_Pagos_Exception extends RuntimeException {}

final class Idital_Pagos_Http {

	/**
	 * Hace una petición JSON.
	 *
	 * @param string     $metodo   GET, POST, PATCH...
	 * @param string     $url      URL completa.
	 * @param array      $cabeceras Cabeceras adicionales.
	 * @param array|null $cuerpo   Cuerpo a enviar (JSON por defecto).
	 * @param bool       $formulario Enviar el cuerpo como application/x-www-form-urlencoded.
	 * @return array{codigo:int, datos:array}
	 * @throws Idital_Pagos_Exception Si la conexión falla o la URL no es segura.
	 */
	public static function json( string $metodo, string $url, array $cabeceras = array(), ?array $cuerpo = null, bool $formulario = false ): array {
		$local   = Idital_Pagos_Config::entorno_local();
		$esquema = wp_parse_url( $url, PHP_URL_SCHEME );
		if ( 'https' !== $esquema && ! ( $local && 'http' === $esquema ) ) {
			throw new Idital_Pagos_Exception( 'Se ha bloqueado una petición no cifrada.' );
		}

		$args = array(
			'method'             => $metodo,
			'timeout'            => 20,
			'redirection'        => 0,      // Nunca seguir redirecciones con credenciales.
			'sslverify'          => true,   // Nunca desactivar la verificación TLS.
			'reject_unsafe_urls' => ! $local,
			'httpversion'        => '1.1',
			'user-agent'         => 'IditalPagos/' . IDITAL_PAGOS_VERSION . '; WordPress',
			'headers'            => array_merge( array( 'Accept' => 'application/json' ), $cabeceras ),
		);
		if ( null !== $cuerpo && $formulario ) {
			$args['headers']['Content-Type'] = 'application/x-www-form-urlencoded';
			$args['body']                    = http_build_query( $cuerpo, '', '&' );
		} elseif ( null !== $cuerpo ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = wp_json_encode( $cuerpo );
		}

		$respuesta = wp_remote_request( $url, $args );
		if ( is_wp_error( $respuesta ) ) {
			throw new Idital_Pagos_Exception( 'Error de conexión: ' . $respuesta->get_error_message() );
		}

		$codigo = (int) wp_remote_retrieve_response_code( $respuesta );
		$cuerpo = (string) wp_remote_retrieve_body( $respuesta );
		$datos  = json_decode( $cuerpo, true );

		return array(
			'codigo' => $codigo,
			'datos'  => is_array( $datos ) ? $datos : array(),
		);
	}
}
