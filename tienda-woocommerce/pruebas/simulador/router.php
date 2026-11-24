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

/* ============================================================= QONTO OAUTH */
if ( '/oauth2/auth' === $ruta ) {
	$q = $_GET;
	if ( ( $q['client_id'] ?? '' ) !== secreto( 'qonto_oauth_client_id' ) ) {
		pagina( 'Qonto', '<h1>client_id desconocido</h1>' );
	}
	if ( 'POST' === $metodo ) {
		$codigo                        = bin2hex( random_bytes( 16 ) );
		$e['qonto_codigos'][ $codigo ] = array( 'redirect_uri' => $q['redirect_uri'], 'scope' => $q['scope'] ?? '' );
		guardar( $e );
		header( 'Location: ' . $q['redirect_uri'] . '?' . http_build_query( array( 'code' => $codigo, 'state' => $q['state'] ?? '' ) ), true, 302 );
		exit;
	}
	pagina( 'Qonto', '<h1>Autorizar a la tienda</h1><p>Permisos: ' . htmlspecialchars( $q['scope'] ?? '' ) . '</p><form method="post"><button id="autorizar" style="font-size:1.2em;padding:10px 24px">Autorizar</button></form>' );
}

if ( '/oauth2/token' === $ruta && 'POST' === $metodo ) {
	// Como el sandbox real: sin X-Qonto-Staging-Token, redirige al portal de desarrolladores.
	if ( '' !== secreto( 'qonto_staging_token' ) && ( $_SERVER['HTTP_X_QONTO_STAGING_TOKEN'] ?? '' ) !== secreto( 'qonto_staging_token' ) ) {
		header( 'Location: https://developers.qonto.com:443/sign-in?redirect_uri=oauth-sandbox.staging.qonto.co', true, 302 );
		exit;
	}
	$f = $_POST;
	if ( ( $f['client_id'] ?? '' ) !== secreto( 'qonto_oauth_client_id' ) || ( $f['client_secret'] ?? '' ) !== secreto( 'qonto_oauth_client_secret' ) ) {
		json_out( 401, array( 'error' => 'invalid_client' ) );
	}
	if ( 'authorization_code' === ( $f['grant_type'] ?? '' ) ) {
		$c = $e['qonto_codigos'][ $f['code'] ?? '' ] ?? null;
		unset( $e['qonto_codigos'][ $f['code'] ?? '' ] ); // Un solo uso.
		if ( ! $c || $c['redirect_uri'] !== ( $f['redirect_uri'] ?? '' ) ) {
			guardar( $e );
			json_out( 400, array( 'error' => 'invalid_grant' ) );
		}
		$scope = $c['scope'];
	} elseif ( 'refresh_token' === ( $f['grant_type'] ?? '' ) ) {
		$r = $f['refresh_token'] ?? '';
		if ( empty( $e['qonto_refresh'][ $r ] ) ) {
			json_out( 400, array( 'error' => 'invalid_grant' ) );
		}
		$scope = $e['qonto_refresh'][ $r ];
		unset( $e['qonto_refresh'][ $r ] ); // Rotación: el refresh token usado deja de valer.
		$e['qonto_refrescos'] = ( $e['qonto_refrescos'] ?? 0 ) + 1;
	} else {
		json_out( 400, array( 'error' => 'unsupported_grant_type' ) );
	}
	$access                         = 'acc_' . bin2hex( random_bytes( 16 ) );
	$refresh                        = 'ref_' . bin2hex( random_bytes( 16 ) );
	$e['qonto_access'][ $access ]   = time() + 3600;
	$e['qonto_refresh'][ $refresh ] = $scope;
	guardar( $e );
	json_out( 200, array( 'access_token' => $access, 'refresh_token' => $refresh, 'expires_in' => 3600, 'token_type' => 'bearer', 'scope' => $scope ) );
}

if ( '/_sim/qonto/mollie' === $ruta ) { // Solo pruebas: fija el estado de la conexión con Mollie.
	$e['mollie'] = preg_replace( '/[^a-z_]/', '', $_GET['estado'] ?? 'enabled' );
	guardar( $e );
	json_out( 200, array( 'mollie' => $e['mollie'] ) );
}

if ( '/mollie/alta' === $ruta ) { // Página de alta simulada de Mollie.
	if ( 'POST' === $metodo ) {
		$e['mollie'] = 'enabled';
		guardar( $e );
		header( 'Location: ' . ( $e['mollie_vuelta'] ?? '/' ), true, 303 );
		exit;
	}
	pagina( 'Mollie', '<h1>Mollie · Alta de cobros</h1><form method="post"><button id="completar" style="font-size:1.2em;padding:10px 24px">Completar alta</button></form>' );
}

if ( '/_sim/qonto/expirar' === $ruta ) { // Solo pruebas: caducan todos los access tokens.
	foreach ( array_keys( $e['qonto_access'] ?? array() ) as $t ) {
		$e['qonto_access'][ $t ] = 0;
	}
	guardar( $e );
	json_out( 200, array( 'refrescos' => $e['qonto_refrescos'] ?? 0 ) );
}

/* =============================================================== QONTO API */
if ( str_starts_with( $ruta, '/v2/payment_links' ) || '/v2/organization' === $ruta ) {
	$bearer   = str_starts_with( $auth, 'Bearer ' ) ? substr( $auth, 7 ) : '';
	$oauth_ok = '' !== $bearer && ( $e['qonto_access'][ $bearer ] ?? 0 ) > time();
	$clave_ok = $auth === secreto( 'qonto_login' ) . ':' . secreto( 'qonto_secret_key' );
	// Como Qonto: la clave API solo sirve para leer la organización; los links exigen OAuth.
	if ( ! $oauth_ok && ! ( $clave_ok && '/v2/organization' === $ruta ) ) {
		json_out( 401, array( 'errors' => array( array( 'code' => 'authorization_token_invalid' ) ) ) );
	}
	if ( '/v2/organization' === $ruta ) {
		json_out( 200, array( 'organization' => array( 'slug' => 'org-simulada', 'legal_name' => 'EMPRESA SIMULADA SL', 'bank_accounts' => array( array( 'id' => '11111111-2222-4333-8444-555555555555', 'status' => 'active', 'main' => true, 'is_external_account' => false ) ) ) ) );
	}
	if ( '/v2/payment_links/connections' === $ruta ) {
		$estado_mollie = $e['mollie'] ?? 'enabled';
		if ( 'POST' === $metodo ) {
			foreach ( array( 'partner_callback_url', 'user_bank_account_id', 'user_phone_number', 'user_website_url', 'business_description' ) as $campo ) {
				if ( empty( $cuerpo[ $campo ] ) ) {
					json_out( 400, array( 'errors' => array( array( 'code' => 'bad_request', 'detail' => "$campo is required" ) ) ) );
				}
			}
			if ( strlen( $cuerpo['business_description'] ) < 80 ) {
				json_out( 400, array( 'errors' => array( array( 'code' => 'bad_request', 'detail' => 'business_description too short' ) ) ) );
			}
			$e['mollie_vuelta'] = $cuerpo['partner_callback_url'];
			guardar( $e );
			json_out( 201, array( 'status' => $estado_mollie, 'bank_account_id' => $cuerpo['user_bank_account_id'], 'connection_location' => getenv( 'SIM_PUBLICO' ) . '/mollie/alta' ) );
		}
		json_out( 200, array( 'status' => $estado_mollie, 'bank_account_id' => '11111111-2222-4333-8444-555555555555', 'connection_location' => '' ) );
	}
	if ( '/v2/payment_links/payment_methods' === $ruta ) {
		json_out( 200, array( 'payment_methods' => array( array( 'name' => 'credit_card' ), array( 'name' => 'apple_pay' ) ) ) );
	}

	if ( 'POST' === $metodo && '/v2/payment_links' === $ruta ) {
		if ( 'enabled' !== ( $e['mollie'] ?? 'enabled' ) ) {
			json_out( 422, array( 'errors' => array( array( 'code' => 'connection_not_enabled', 'detail' => 'Payment links provider connection is not enabled' ) ) ) );
		}
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
if ( 'POST' === $metodo && '/api/v3/access/token' === $ruta ) {
	if ( ( $cuerpo['client_id'] ?? '' ) !== secreto( 'tropipay_client_id' ) || ( $cuerpo['client_secret'] ?? '' ) !== secreto( 'tropipay_client_secret' ) ) {
		json_out( 401, array( 'error' => 'invalid_client' ) );
	}
	$token               = bin2hex( random_bytes( 20 ) );
	$e['tpp_tokens'][]   = $token;
	guardar( $e );
	json_out( 200, array( 'access_token' => $token, 'token_type' => 'Bearer', 'expires_in' => time() + 3600, 'scope' => 'ALLOW_PAYMENT_IN ALLOW_EXTERNAL_CHARGE' ) );
}

if ( 'POST' === $metodo && '/api/v3/paymentcards' === $ruta ) {
	if ( ! in_array( substr( $auth, 7 ), $e['tpp_tokens'] ?? array(), true ) ) {
		json_out( 401, array( 'error' => 'unauthorized' ) );
	}
	$id                   = uuid();
	$card                 = $cuerpo + array( 'id' => $id );
	$card['paymentUrl']   = null; // Como la API real: el link útil es shortUrl.
	$card['shortUrl']     = getenv( 'SIM_PUBLICO' ) . '/pagar/tropipay/' . $id;
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
		$firma = hash( 'sha256', $boc . secreto( 'tropipay_client_id' ) . sha1( secreto( 'tropipay_client_secret' ) ) . $card['amount'] );
		post_json(
			interno( $card['urlNotification'] ),
			array(
				'status' => 'OK',
				'data'   => array(
					'reference'              => $card['reference'],
					'bankOrderCode'          => $boc,
					'originalCurrencyAmount' => (string) $card['amount'],
					'amount'                 => $card['amount'],
					'currency'               => $card['currency'],
					'paymentcardId'          => $m[1],
					'state'                  => 5,
					'signaturev3'            => $firma,
				),
			)
		);
		header( 'Location: ' . $card['urlSuccess'], true, 303 ); // phpcs:ignore
		exit;
	}
	pagina(
		'TropiPay',
		'<h1>TropiPay · Pago</h1><p>' . htmlspecialchars( $card['concept'] ) . '</p><p>Importe: <strong>' . number_format( $card['amount'] / 100, 2, ',', '.' ) . ' ' . htmlspecialchars( $card['currency'] ) . '</strong></p>'
		. ( 'abierto' === $card['estado'] ? '<form method="post"><button id="pagar" style="font-size:1.2em;padding:10px 24px">Pagar con saldo de prueba</button></form>' : '<p>Ya pagado.</p>' )
	);
}

json_out( 404, array( 'error' => 'ruta no simulada' ) );
