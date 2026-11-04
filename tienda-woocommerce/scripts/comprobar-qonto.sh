#!/usr/bin/env bash
# Comprueba la conexión con Qonto usando las credenciales de ./secretos.
#
#   ./scripts/comprobar-qonto.sh               solo lectura: no crea ni cobra nada
#   ./scripts/comprobar-qonto.sh --link-prueba además crea desde la tienda un link de 1 €,
#                                              te deja abrirlo y lo desactiva (sin pagar)
#
# La clave nunca se muestra ni aparece en la lista de procesos.
set -uo pipefail
cd "$(dirname "$0")/.."
[[ -f .env ]] && { set -a; source .env; set +a; }

LOGIN="$(cat secretos/qonto_login.txt 2>/dev/null)"
SECRETO="$(cat secretos/qonto_secret_key.txt 2>/dev/null)"
[[ -n "$LOGIN" && -n "$SECRETO" ]] || { echo "✘ Faltan secretos/qonto_login.txt o secretos/qonto_secret_key.txt (usa ./scripts/guardar-secreto.sh)"; exit 1; }

CABECERAS="$(mktemp)"; chmod 600 "$CABECERAS"; trap 'rm -f "$CABECERAS" "$CABECERAS.json"' EXIT
printf 'Authorization: %s:%s\nAccept: application/json\n' "$LOGIN" "$SECRETO" > "$CABECERAS"
unset SECRETO

if [[ "${IDITAL_QONTO_ENTORNO:-sandbox}" == "produccion" ]]; then
  BASE=https://thirdparty.qonto.com
  echo "Entorno: PRODUCCIÓN ($BASE)"
else
  BASE=https://thirdparty-sandbox.staging.qonto.co
  STAGING="$(cat secretos/qonto_staging_token.txt 2>/dev/null)"
  [[ -n "$STAGING" ]] && printf 'X-Qonto-Staging-Token: %s\n' "$STAGING" >> "$CABECERAS"
  echo "Entorno: sandbox ($BASE)  ← si la clave es de la app normal de Qonto, pon IDITAL_QONTO_ENTORNO=produccion en .env"
fi
echo

llamar() { # ruta → deja el cuerpo en $CABECERAS.json y devuelve el código HTTP
  curl -sS -o "$CABECERAS.json" -w '%{http_code}' -H @"$CABECERAS" "$BASE$1" 2>/dev/null || echo 000
}
json() { python3 -I -c "import json,sys
try: d=json.load(open(sys.argv[1]))
except Exception: d={}
$2" "$CABECERAS.json"; }

fallos=0

echo "1. ¿La clave es válida?"
c=$(llamar /v2/organization)
case "$c" in
  200) json 'o=d.get("organization",{}); print("   ✔ Sí. Organización:", o.get("legal_name") or o.get("slug","?"), "| cuentas:", len(o.get("bank_accounts",[])))' ;;
  401) echo "   ✘ Qonto rechaza la clave (401). Revisa identificador/clave o el entorno."; fallos=$((fallos+1)) ;;
  000) echo "   ✘ No hay conexión con $BASE"; fallos=$((fallos+1)) ;;
  *)   echo "   ✘ Respuesta inesperada (HTTP $c)"; fallos=$((fallos+1)) ;;
esac

echo "2. ¿Está conectada a Mollie (requisito para los links de pago)?"
c=$(llamar /v2/payment_links/connections)
case "$c" in
  200) json 'c=d.get("connection",d) if isinstance(d,dict) else {}; e=str(c.get("status") or c.get("connection_status") or "desconocido"); print("   Estado de la conexión:", e); print("   ✔ Lista para crear links" if e.lower() in ("enabled","active","connected") else "   ⚠ Hay que completar la conexión con Mollie desde Qonto")' ;;
  404) echo "   ⚠ Sin conexión todavía: hay que conectar la cuenta con Mollie desde Qonto." ;;
  401|403) echo "   ✘ La clave no tiene permiso para links de pago (HTTP $c)."; fallos=$((fallos+1)) ;;
  *)   echo "   ✘ Respuesta inesperada (HTTP $c)"; fallos=$((fallos+1)) ;;
esac

echo "3. Métodos de pago disponibles en los links"
c=$(llamar /v2/payment_links/payment_methods)
if [[ "$c" == 200 ]]; then
  json 'm=d.get("payment_methods",d if isinstance(d,list) else []); print("   ✔", ", ".join((x.get("name") or x.get("type") or str(x)) if isinstance(x,dict) else str(x) for x in m) or "ninguno")'
else
  echo "   ⚠ No disponible (HTTP $c) — normal si aún no hay conexión con Mollie."
fi

if [[ "${1:-}" == "--link-prueba" ]]; then
  echo
  echo "4. Link de prueba de 1 € creado por la tienda (no se cobra nada)"
  WP=(docker compose run --rm -T wpcli wp)
  SALIDA=$("${WP[@]}" eval '
    $p = wc_create_order();
    $f = new WC_Order_Item_Fee(); $f->set_name("Prueba de conexión Qonto"); $f->set_total(1); $f->set_tax_status("none");
    $p->add_item($f); $p->set_currency("EUR"); $p->calculate_totals(false);
    $g = WC()->payment_gateways()->payment_gateways()["idital_qonto"];
    $p->set_payment_method($g); $p->set_status("pending"); $p->save();
    $r = $g->process_payment($p->get_id());
    $p = wc_get_order($p->get_id());
    echo $p->get_id(), " ", $p->get_meta("_idital_qonto_link_id"), " ", $p->get_meta("_idital_qonto_link_url");
  ' 2>/dev/null | tail -1)
  read -r PEDIDO LINK URL <<<"$SALIDA"
  if [[ -z "${URL:-}" ]]; then
    echo "   ✘ La tienda no pudo crear el link. Mira WooCommerce → Estado → Registros (idital-pagos)."; exit 1
  fi
  echo "   ✔ Pedido de prueba nº $PEDIDO — abre este link y comprueba la página de pago (NO pagues):"
  echo "     $URL"
  read -rp "   Pulsa Enter para desactivar el link y cancelar el pedido de prueba… " _
  "${WP[@]}" eval "\$c=new Idital_Pagos_Qonto_Client(); echo \$c->desactivar_link('$LINK') ? 'ok' : 'error';
    \$p=wc_get_order($PEDIDO); \$p->update_status('cancelled','Pedido de prueba de conexión con Qonto.');" 2>/dev/null | grep -q ok \
    && echo "   ✔ Link desactivado y pedido cancelado." || { echo "   ✘ No se pudo desactivar: desactívalo desde la app de Qonto."; fallos=$((fallos+1)); }
fi

echo
[[ $fallos -eq 0 ]] && echo "Todo correcto." || { echo "$fallos comprobación(es) con problemas."; exit 1; }
