<?php
/**
 * Simulador MÍNIMO de las APIs de Qonto (payment links) y TropiPay (paymentcards)
 * para probar el flujo completo sin credenciales reales. Solo para uso local.
 *
 * Páginas para "pagar" en el navegador:
 *   /pagar/qonto/{id}      /pagar/tropipay/{id}
 */

declare(strict_types=1);

const ESTADO = '/tmp/simulador-estado.json';

function secreto( string $n ): string {
	$f = '/run/secrets/' . $n;
	return is_readable( $f ) ? trim( (string) file_get_contents( $f ) ) : '';
}

function estado(): array {
	return is_readable( ESTADO ) ? ( json_decode( (string) file_get_contents( ESTADO ), true ) ?: array() ) : array();
}

function guardar( array $e ): void {
	file_put_contents( ESTADO, json_encode( $e ), LOCK_EX );
}

function json_out( int $codigo, array $datos ): void {
	http_response_code( $codigo );
	header( 'Content-Type: application/json' );
	echo json_encode( $datos );
	exit;
}

function uuid(): string {
	$b    = random_bytes( 16 );
	$b[6] = chr( ord( $b[6] ) & 0x0f | 0x40 );
	$b[8] = chr( ord( $b[8] ) & 0x3f | 0x80 );
	return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $b ), 4 ) );
}

function interno( string $url ): string {
	// Las URLs públicas de WordPress (localhost:8080) se alcanzan dentro de Docker como http://wordpress.
	return str_replace( getenv( 'WP_PUBLICO' ), getenv( 'WP_INTERNO' ), $url );
}

function post_json( string $url, array $cuerpo ): int {
	$ch = curl_init( $url );
	curl_setopt_array(
		$ch,
		array(
			CURLOPT_POST           => true,
			CURLOPT_POSTFIELDS     => json_encode( $cuerpo ),
			CURLOPT_HTTPHEADER     => array( 'Content-Type: application/json', 'Host: ' . parse_url( (string) getenv( 'WP_PUBLICO' ), PHP_URL_HOST ) . ':' . ( parse_url( (string) getenv( 'WP_PUBLICO' ), PHP_URL_PORT ) ?: 80 ) ),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT        => 30,
		)
	);
	curl_exec( $ch );
	$codigo = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
	curl_close( $ch );
	return $codigo;
}

function pagina( string $titulo, string $html ): void {
	header( 'Content-Type: text/html; charset=utf-8' );
	echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>' . htmlspecialchars( $titulo ) . '</title>'
		. '<body style="font-family:system-ui;max-width:520px;margin:40px auto;padding:0 16px">'
		. '<p style="background:#fff3cd;padding:8px 12px;border-radius:6px">⚠️ SIMULADOR LOCAL – no es la pasarela real</p>' . $html . '</body>';
	exit;
}

$metodo = $_SERVER['REQUEST_METHOD'];
$ruta   = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
$cuerpo = json_decode( (string) file_get_contents( 'php://input' ), true ) ?: array();
$auth   = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$e      = estado();

/* =============================================================== QONTO API */
if ( str_starts_with( $ruta, '/v2/payment_links' ) ) {
	if ( $auth !== secreto( 'qonto_login' ) . ':' . secreto( 'qonto_secret_key' ) ) {
		json_out( 401, array( 'errors' => array( array( 'code' => 'authorization_token_invalid' ) ) ) );
	}

	if ( 'POST' === $metodo && '/v2/payment_links' === $ruta ) {
		$pl    = $cuerpo['payment_link'] ?? array();
		$total = 0.0;
		foreach ( $pl['items'] ?? array() as $it ) {
			$total += (float) $it['unit_price']['value'] * (float) $it['quantity'] * ( 1 + (float) $it['vat_rate'] );
		}
		$id                = uuid();
		$link              = array(
			'id'                        => $id,
			'status'                    => 'open',
			'url'                       => getenv( 'SIM_PUBLICO' ) . '/pagar/qonto/' . $id,
			'amount'                    => array(
				'value'    => number_format( $total, 2, '.', '' ),
				'currency' => $pl['items'][0]['unit_price']['currency'] ?? 'EUR',
			),
			'potential_payment_methods' => $pl['potential_payment_methods'] ?? array(),
			'reusable'                  => false,
			'resource_type'             => 'Basket',
			'expiration_date'           => gmdate( 'c', time() + 86400 * 30 ),
			'created_at'                => gmdate( 'c' ),
		);
		$e['qonto'][ $id ] = $link;
		guardar( $e );
		json_out( 201, array( 'payment_link' => $link ) );
	}

	if ( preg_match( '#^/v2/payment_links/([a-f0-9-]+)(/deactivate)?$#', $ruta, $m ) ) {
		$link = $e['qonto'][ $m[1] ] ?? null;
		if ( ! $link ) {
			json_out( 404, array( 'errors' => array( array( 'code' => 'not_found' ) ) ) );
		}
		if ( 'PATCH' === $metodo && ! empty( $m[2] ) ) {
			if ( 'open' === $link['status'] ) {
				$e['qonto'][ $m[1] ]['status'] = 'canceled';
				guardar( $e );
			}
			json_out( 200, array( 'payment_link' => $e['qonto'][ $m[1] ] ) );
		}
		json_out( 200, array( 'payment_link' => $link ) );
	}
	json_out( 404, array() );
}

/* ============================================================ TROPIPAY API */
if ( 'POST' === $metodo && '/api/v2/access/token' === $ruta ) {
	if ( ( $cuerpo['client_id'] ?? '' ) !== secreto( 'tropipay_client_id' ) || ( $cuerpo['client_secret'] ?? '' ) !== secreto( 'tropipay_client_secret' ) ) {
		json_out( 401, array( 'error' => 'invalid_client' ) );
	}
	$token               = bin2hex( random_bytes( 20 ) );
	$e['tpp_tokens'][]   = $token;
	guardar( $e );
	json_out( 200, array( 'access_token' => $token, 'token_type' => 'Bearer', 'expires_in' => 3600 ) );
}

if ( 'POST' === $metodo && '/api/v2/paymentcards' === $ruta ) {
	if ( ! in_array( substr( $auth, 7 ), $e['tpp_tokens'] ?? array(), true ) ) {
		json_out( 401, array( 'error' => 'unauthorized' ) );
	}
	$id                   = uuid();
	$card                 = $cuerpo + array( 'id' => $id );
	$card['paymentUrl']   = getenv( 'SIM_PUBLICO' ) . '/pagar/tropipay/' . $id;
	$card['shortUrl']     = $card['paymentUrl'];
	$card['estado']       = 'abierto';
	$e['tropipay'][ $id ] = $card;
	guardar( $e );
	json_out( 200, $card );
}

/* ================================================ Páginas de pago simuladas */
if ( preg_match( '#^/pagar/qonto/([a-f0-9-]+)$#', $ruta, $m ) ) {
	$link = $e['qonto'][ $m[1] ] ?? null;
	if ( ! $link ) {
		pagina( 'Qonto', '<h1>Link no encontrado</h1>' );
	}
	if ( 'POST' === $metodo && 'open' === $link['status'] ) {
		$e['qonto'][ $m[1] ]['status'] = 'paid';
		guardar( $e );
		$url = getenv( 'WP_INTERNO' ) . '/wp-json/idital-pagos/v1/qonto/webhook?token=' . rawurlencode( secreto( 'qonto_webhook_token' ) );
		$cod = post_json( $url, array( 'event_type' => 'payment_link.status_changed', 'data' => array( 'payment_link_id' => $m[1] ) ) );
		pagina( 'Qonto', '<h1>Pago realizado ✅</h1><p>Webhook enviado a la tienda (HTTP ' . $cod . '). Ya puedes cerrar esta pestaña.</p>' );
	}
	pagina(
		'Qonto',
		'<h1>Qonto · Pago</h1><p>Importe: <strong>' . htmlspecialchars( $link['amount']['value'] . ' ' . $link['amount']['currency'] ) . '</strong></p><p>Estado: ' . htmlspecialchars( $link['status'] ) . '</p>'
		. ( 'open' === $link['status'] ? '<form method="post"><button id="pagar" style="font-size:1.2em;padding:10px 24px">Pagar con tarjeta de prueba</button></form>' : '' )
	);
}

if ( preg_match( '#^/pagar/tropipay/([a-f0-9-]+)$#', $ruta, $m ) ) {
	$card = $e['tropipay'][ $m[1] ] ?? null;
	if ( ! $card ) {
		pagina( 'TropiPay', '<h1>Link no encontrado</h1>' );
	}
	if ( 'POST' === $metodo && 'abierto' === $card['estado'] ) {
		$e['tropipay'][ $m[1] ]['estado'] = 'pagado';
		guardar( $e );
		$boc   = 'BOC' . random_int( 100000, 999999 );
		$firma = hash( 'sha256', $boc . secreto( 'tropipay_client_id' ) . secreto( 'tropipay_client_secret' ) . $card['amount'] );
		post_json(
			interno( $card['urlNotification'] ),
			array(
				'status' => 'OK',
				'data'   => array(
					'reference'              => $card['reference'],
					'bankOrderCode'          => $boc,
					'originalCurrencyAmount' => $card['amount'],
					'originalCurrency'       => $card['currency'],
					'state'                  => 5,
					'signature'              => $firma,
				),
			)
		);
		header( 'Location: ' . $card['urlSuccess'], true, 303 );
		exit;
	}
	pagina(
		'TropiPay',
		'<h1>TropiPay · Pago</h1><p>' . htmlspecialchars( $card['concept'] ) . '</p><p>Importe: <strong>' . number_format( $card['amount'] / 100, 2, ',', '.' ) . ' ' . htmlspecialchars( $card['currency'] ) . '</strong></p>'
		. ( 'abierto' === $card['estado'] ? '<form method="post"><button id="pagar" style="font-size:1.2em;padding:10px 24px">Pagar con saldo de prueba</button></form>' : '<p>Ya pagado.</p>' )
	);
}

json_out( 404, array( 'error' => 'ruta no simulada' ) );
