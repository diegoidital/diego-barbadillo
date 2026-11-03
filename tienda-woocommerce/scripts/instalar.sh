#!/usr/bin/env bash
# Levanta los contenedores e instala WordPress + WooCommerce + plugin de pagos, endurecido.
# Uso:  ./scripts/instalar.sh            (normal)
#       PRUEBAS=1 ./scripts/instalar.sh  (añade el simulador de Qonto/TropiPay)
set -euo pipefail
cd "$(dirname "$0")/.."

[[ -f .env ]] || { echo "Falta .env (cp .env.example .env)"; exit 1; }
[[ -d secretos ]] || ./scripts/preparar-secretos.sh
set -a; source .env; set +a

COMPOSE=(docker compose -f docker-compose.yml)
[[ "${PRUEBAS:-0}" == "1" ]] && COMPOSE+=(-f docker-compose.pruebas.yml)
wp() { "${COMPOSE[@]}" run --rm -T wpcli wp "$@"; }

echo "→ Arrancando contenedores"
"${COMPOSE[@]}" up -d
echo "→ Esperando a WordPress"
for _ in $(seq 1 60); do
  "${COMPOSE[@]}" exec -T wordpress test -f /var/www/html/wp-config.php && break
  sleep 2
done

if wp core is-installed 2>/dev/null; then
  echo "WordPress ya estaba instalado; solo se aplican ajustes."
else
  PASS="$(openssl rand -base64 30 | tr -d '\n/+=')"
  wp core install --url="$WP_URL" --title="$WP_TITULO" \
    --admin_user="$WP_ADMIN_USUARIO" --admin_password="$PASS" --admin_email="$WP_ADMIN_EMAIL" --skip-email
  umask 077; printf '%s\n%s\n' "$WP_ADMIN_USUARIO" "$PASS" > secretos/admin-wordpress.txt
  echo "  Usuario admin: $WP_ADMIN_USUARIO  (contraseña guardada en secretos/admin-wordpress.txt)"
fi

echo "→ Idioma, zona horaria y enlaces"
wp language core install es_ES --activate || true
wp option update timezone_string Europe/Madrid
wp rewrite structure '/%postname%/' --hard

echo "→ Plugins: fuera los que no se usan, WooCommerce dentro"
wp plugin delete akismet hello 2>/dev/null || true
if ! wp plugin is-installed woocommerce; then
  if [[ -n "${WOOCOMMERCE_ZIP:-}" ]]; then
    # Servidor sin salida a wordpress.org: instalar desde un zip descargado aparte.
    "${COMPOSE[@]}" cp "$WOOCOMMERCE_ZIP" wordpress:/var/www/html/wp-content/woocommerce.zip
    "${COMPOSE[@]}" exec -T wordpress chown www-data:www-data /var/www/html/wp-content/woocommerce.zip
    wp plugin install /var/www/html/wp-content/woocommerce.zip
    "${COMPOSE[@]}" exec -T wordpress rm -f /var/www/html/wp-content/woocommerce.zip
  else
    wp plugin install woocommerce
  fi
fi
wp plugin activate woocommerce
wp language plugin install woocommerce es_ES || true
wp plugin activate idital-pagos

echo "→ Ajustes de WooCommerce"
wp option update woocommerce_currency EUR
wp option update woocommerce_default_country ES
wp option update woocommerce_price_decimal_sep ","
wp option update woocommerce_price_thousand_sep "."
wp option update woocommerce_onboarding_profile '{"skipped":true}' --format=json
wp option update woocommerce_enable_signup_and_login_from_checkout no
wp option update woocommerce_registration_generate_password yes
wp option update woocommerce_allow_tracking no
wp option update woocommerce_show_marketplace_suggestions no
wp wc tool run install_pages --user="$WP_ADMIN_USUARIO" >/dev/null || true

echo "→ Pasarelas (solo se muestran si tienen credenciales)"
wp option update woocommerce_idital_qonto_settings \
  '{"enabled":"yes","title":"Pagar vía Qonto (Transferencia/Tarjeta Europea)","description":"Paga con tarjeta de forma segura a través de Qonto o, si lo prefieres, por transferencia a nuestra cuenta Qonto.","metodos":["credit_card","apple_pay"],"titular":"","iban":"","bic":""}' --format=json
wp option update woocommerce_idital_tropipay_settings \
  '{"enabled":"yes","title":"Pagar vía TropiPay (Fondos TropiPay/Tarjetas Internacionales)","description":"Paga con tu saldo TropiPay o con tarjeta internacional. Te llevaremos a la página segura de TropiPay.","caducidad":"1"}' --format=json

echo "→ Endurecimiento"
wp option update users_can_register 0
wp option update default_comment_status closed
wp option update default_ping_status closed
[[ "$WP_ENVIRONMENT_TYPE" != "production" ]] && wp option update blog_public 0   # No indexar copias de prueba.

echo
echo "✔ Tienda lista en $WP_URL  (panel: $WP_URL/wp-admin)"
