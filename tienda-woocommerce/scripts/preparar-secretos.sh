#!/usr/bin/env bash
# Crea ./secretos con contraseñas aleatorias y ficheros vacíos para las credenciales
# de Qonto y TropiPay (se rellenan cuando las tengamos). No sobrescribe nada existente.
set -euo pipefail
cd "$(dirname "$0")/.."

umask 077
mkdir -p secretos
chmod 700 secretos

aleatorio() { openssl rand -base64 48 | tr -d '\n/+=' | cut -c1-"$1"; }

crear() { # nombre, contenido
  local f="secretos/$1.txt"
  if [[ ! -e "$f" ]]; then
    printf '%s' "$2" > "$f"
    echo "  creado   $f"
  else
    echo "  existe   $f (no se toca)"
  fi
  # Legible por el usuario www-data del contenedor; la carpeta sigue siendo privada (700).
  chmod 644 "$f"
}

echo "Generando secretos en ./secretos"
crear db_password          "$(aleatorio 40)"
crear db_root_password     "$(aleatorio 40)"
crear pagos_clave_hmac     "$(aleatorio 64)"
crear qonto_webhook_token  "$(aleatorio 40)"
# Pendientes de recibir (vacíos = pasarela desactivada automáticamente):
crear qonto_login            ""
crear qonto_secret_key       ""
crear qonto_staging_token    ""
crear tropipay_client_id     ""
crear tropipay_client_secret ""

cat <<'TXT'

Listo. Cuando lleguen las credenciales, escríbelas (sin salto de línea) en:
  secretos/qonto_login.txt            slug de la organización Qonto
  secretos/qonto_secret_key.txt       clave secreta de la API de Qonto
  secretos/qonto_staging_token.txt    solo para el sandbox de Qonto
  secretos/tropipay_client_id.txt
  secretos/tropipay_client_secret.txt
y reinicia:  docker compose restart wordpress
TXT
