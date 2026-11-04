#!/usr/bin/env bash
# Guarda un secreto pidiéndolo por teclado SIN mostrarlo y sin dejarlo en el historial.
# Uso: ./scripts/guardar-secreto.sh qonto_secret_key
set -euo pipefail
cd "$(dirname "$0")/.."

PERMITIDOS="qonto_login qonto_secret_key qonto_staging_token qonto_webhook_token tropipay_client_id tropipay_client_secret"
nombre="${1:-}"
if [[ -z "$nombre" || " $PERMITIDOS " != *" $nombre "* ]]; then
  echo "Uso: $0 <nombre>   (uno de: $PERMITIDOS)"; exit 1
fi

[[ -d secretos ]] || ./scripts/preparar-secretos.sh >/dev/null
read -rsp "Pega el valor de '$nombre' (no se verá) y pulsa Enter: " valor; echo
valor="$(printf '%s' "$valor" | tr -d '[:space:]')"
[[ -n "$valor" ]] || { echo "Valor vacío: no se guarda nada."; exit 1; }

umask 077
printf '%s' "$valor" > "secretos/$nombre.txt"
chmod 644 "secretos/$nombre.txt"   # legible por el contenedor; la carpeta sigue siendo privada (700)
unset valor
echo "✔ Guardado en secretos/$nombre.txt."

if docker compose ps --status running --services 2>/dev/null | grep -qx wordpress; then
  docker compose restart wordpress >/dev/null && echo "✔ WordPress reiniciado para cargar el nuevo valor."
fi
