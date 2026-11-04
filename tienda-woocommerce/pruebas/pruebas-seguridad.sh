#!/usr/bin/env bash
# Intentos de ataque contra la tienda (con el simulador levantado). Todos deben FALLAR.
set -uo pipefail
cd "$(dirname "$0")/.."
C=(docker compose -f docker-compose.yml -f docker-compose.pruebas.yml)
WP=http://localhost:${WP_PUERTO:-8080}
API=$WP/wp-json/idital-pagos/v1
wpe() { "${C[@]}" run --rm -T wpcli wp "$@" 2>/dev/null; }
fallos=0
comprobar() { # descripción, esperado, obtenido
  if [[ "$2" == "$3" ]]; then echo "✔ $1"; else echo "✘ $1 (esperado $2, obtenido $3)"; fallos=$((fallos+1)); fi
}
codigo() { curl -s -o /dev/null -w '%{http_code}' "$@"; }
estado_pedido() { wpe eval "echo wc_get_order($1)->get_status();"; }
nuevo_pedido() { wpe eval-file /pruebas/crear-presupuesto.php "$1" | python3 -c 'import json,sys;print(json.load(sys.stdin)["id"])'; }
TOKEN_QONTO=$(cat secretos/qonto_webhook_token.txt)

echo "== Webhook de Qonto"
P=$(nuevo_pedido 300)
# Genera un link real (en el simulador) para el pedido, sin pagarlo.
LINK=$(wpe eval "\$p=wc_get_order($P); \$g=WC()->payment_gateways()->payment_gateways()['idital_qonto']; \$p->set_payment_method(\$g); \$p->save(); \$g->process_payment($P); echo wc_get_order($P)->get_meta('_idital_qonto_link_id');")
comprobar "sin token → 401" 401 "$(codigo -X POST "$API/qonto/webhook" -H 'Content-Type: application/json' -d "{\"payment_link_id\":\"$LINK\"}")"
comprobar "token falso → 401" 401 "$(codigo -X POST "$API/qonto/webhook?token=falso" -H 'Content-Type: application/json' -d "{\"payment_link_id\":\"$LINK\"}")"
comprobar "webhook falsificado con token válido, link NO pagado → se acepta el aviso" 200 "$(codigo -X POST "$API/qonto/webhook?token=$TOKEN_QONTO" -H 'Content-Type: application/json' -d "{\"payment_link_id\":\"$LINK\",\"status\":\"paid\"}")"
comprobar "…pero el pedido sigue pendiente (se verifica contra la API)" pending "$(estado_pedido "$P")"
comprobar "cuerpo enorme → 413" 413 "$( (printf '{"x":"'; head -c 70000 /dev/zero | tr '\0' 'a'; printf '"}') | curl -s -o /dev/null -w '%{http_code}' -X POST "$API/qonto/webhook?token=$TOKEN_QONTO" -H 'Content-Type: application/json' --data-binary @-)"

echo "== Cambio de pasarela: el link de Qonto se desactiva"
wpe eval "\$p=wc_get_order($P); \$g=WC()->payment_gateways()->payment_gateways()['idital_tropipay']; \$p->set_payment_method(\$g); \$p->save(); \$g->process_payment($P);" >/dev/null
ESTADO_LINK=$(curl -s -H "Authorization: $(cat secretos/qonto_login.txt):$(cat secretos/qonto_secret_key.txt)" "http://localhost:8090/v2/payment_links/$LINK" | python3 -c 'import json,sys;print(json.load(sys.stdin)["payment_link"]["status"])')
comprobar "link de Qonto anterior cancelado" canceled "$ESTADO_LINK"

echo "== Notificación de TropiPay"
REF=$(wpe eval "echo array_key_last((array) wc_get_order($P)->get_meta('_idital_tropipay_refs'));")
TOK=$(wpe eval "echo Idital_Pagos_Gateway_Tropipay::token_notificacion(wc_get_order($P));")
ID=$(cat secretos/tropipay_client_id.txt); SEC=$(cat secretos/tropipay_client_secret.txt)
SEC_SHA1=$(printf '%s' "$SEC" | sha1sum | cut -d' ' -f1)
firma() { printf '%s' "$1$ID$SEC_SHA1$2" | sha256sum | cut -d' ' -f1; }
cuerpo() { echo "{\"status\":\"OK\",\"data\":{\"reference\":\"$1\",\"bankOrderCode\":\"$2\",\"originalCurrencyAmount\":\"$3\",\"currency\":\"EUR\",\"signaturev3\":\"$4\"}}"; }
comprobar "URL con firma de pedido falsa → 404" 404 "$(codigo -X POST "$API/tropipay/notificacion/$P/$(printf 'x%.0s' {1..64} | sha256sum | cut -d' ' -f1)" -H 'Content-Type: application/json' -d "$(cuerpo "$REF" B1 30000 "$(firma B1 30000)")")"
comprobar "referencia inventada → 400" 400 "$(codigo -X POST "$API/tropipay/notificacion/$P/$TOK" -H 'Content-Type: application/json' -d "$(cuerpo INVENTADA B1 30000 "$(firma B1 30000)")")"
comprobar "firma de TropiPay falsa → 401" 401 "$(codigo -X POST "$API/tropipay/notificacion/$P/$TOK" -H 'Content-Type: application/json' -d "$(cuerpo "$REF" B1 30000 "$(printf '0%.0s' {1..64})")")"
comprobar "…el pedido sigue pendiente" pending "$(estado_pedido "$P")"
comprobar "importe manipulado (1 €) con firma correcta → aceptado pero NO pagado" 200 "$(codigo -X POST "$API/tropipay/notificacion/$P/$TOK" -H 'Content-Type: application/json' -d "$(cuerpo "$REF" B2 100 "$(firma B2 100)")")"
comprobar "…el pedido queda en espera para revisión manual" on-hold "$(estado_pedido "$P")"
comprobar "firma calculada con la fórmula antigua (sin sha1) → 401" 401 "$(codigo -X POST "$API/tropipay/notificacion/$P/$TOK" -H 'Content-Type: application/json' -d "$(cuerpo "$REF" B5 30000 "$(printf '%s' "B5$ID$SEC""30000" | sha256sum | cut -d' ' -f1)")")"
comprobar "importe correcto → pagado" 200 "$(codigo -X POST "$API/tropipay/notificacion/$P/$TOK" -H 'Content-Type: application/json' -d "$(cuerpo "$REF" B3 30000 "$(firma B3 30000)")")"
comprobar "…pedido pagado" completed "$(estado_pedido "$P")"
comprobar "notificación repetida (otro cobro) → no rompe nada" 200 "$(codigo -X POST "$API/tropipay/notificacion/$P/$TOK" -H 'Content-Type: application/json' -d "$(cuerpo "$REF" B4 30000 "$(firma B4 30000)")")"
NOTA=$(wpe eval "foreach (wc_get_order_notes(['order_id'=>$P]) as \$n) if (str_contains(\$n->content,'DUPLICADO')) { echo 'si'; break; }")
comprobar "…y se avisa de posible pago duplicado" si "$NOTA"

echo "== Consulta de estado"
comprobar "clave de pedido incorrecta → 404" 404 "$(codigo "$API/estado?pedido=$P&clave=wc_order_falsa")"

echo "== Endurecimiento del servidor"
comprobar "xmlrpc.php bloqueado" 403 "$(codigo -X POST "$WP/xmlrpc.php")"
comprobar "wp-config.php bloqueado" 403 "$(codigo "$WP/wp-config.php")"
comprobar "readme.html bloqueado" 403 "$(codigo "$WP/readme.html")"
comprobar "código del plugin no accesible" 403 "$(codigo "$WP/wp-content/plugins/idital-pagos/includes/class-config.php")"
comprobar "PHP en uploads bloqueado" 403 "$(codigo "$WP/wp-content/uploads/x.php")"
comprobar "listado de directorios desactivado" 403 "$(codigo "$WP/wp-content/uploads/")"
comprobar "enumeración de usuarios (?author=1) → redirige" 301 "$(codigo "$WP/?author=1")"
comprobar "API REST de usuarios oculta" 404 "$(codigo "$WP/wp-json/wp/v2/users")"
comprobar "sin cabecera X-Powered-By" "" "$(curl -sI "$WP/" | grep -i '^x-powered-by' | tr -d '\r')"
comprobar "cabecera X-Frame-Options" "X-Frame-Options: SAMEORIGIN" "$(curl -sI "$WP/" | grep -i '^x-frame-options' | head -1 | tr -d '\r')"

echo "== Fuerza bruta en el login"
for _ in 1 2 3 4 5; do curl -s -o /dev/null -X POST "$WP/wp-login.php" -d 'log=gestor-tienda&pwd=mala'; done
BLOQ=$(curl -s -X POST "$WP/wp-login.php" -d "log=gestor-tienda&pwd=$(sed -n 2p secretos/admin-wordpress.txt)" | grep -c 'Demasiados intentos')
comprobar "tras 5 fallos, bloqueado aunque la contraseña sea correcta" 1 "$BLOQ"

echo "== Límite de peticiones"
for _ in $(seq 1 45); do curl -s -o /dev/null "$API/estado?pedido=1&clave=x"; done
comprobar "consulta de estado limitada" 429 "$(codigo "$API/estado?pedido=1&clave=x")"

echo; [[ $fallos -eq 0 ]] && echo "TODAS LAS PRUEBAS SUPERADAS" || { echo "$fallos PRUEBAS FALLIDAS"; exit 1; }
