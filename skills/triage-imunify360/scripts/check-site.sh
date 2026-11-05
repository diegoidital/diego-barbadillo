#!/bin/bash
# Auditoria de solo lectura de un sitio WordPress en Plesk.
# Uso: bash check-site.sh <raiz-web> <dominio>
#   La raiz web se obtiene con:
#   plesk db -Ne "SELECT h.www_root FROM domains d JOIN hosting h ON h.dom_id=d.id WHERE d.name='<dominio>'"
# Recomendado: nohup bash check-site.sh <raiz> <dominio> > /root/chk-<dominio>.txt 2>&1 &
set +H
P="$1"; D="$2"
[ -d "$P" ] && [ -n "$D" ] || { echo "Uso: $0 <raiz-web> <dominio>"; exit 1; }
U=$(stat -c %U "$P"); LOGS=/var/www/vhosts/system/$D/logs
H=$(plesk db -Ne "SELECT h.php_handler_id FROM hosting h JOIN domains d ON d.id=h.dom_id WHERE d.name='$D'" 2>/dev/null)
V=$(echo "$H" | grep -oE 'php[0-9]+' | head -1 | grep -oE '[0-9]+' | sed -E 's/^([0-9])([0-9]+)$/\1.\2/')
PHPBIN=/opt/plesk/php/$V/bin/php; [ -x "$PHPBIN" ] || PHPBIN=$(ls -d /opt/plesk/php/*/bin/php | sort -V | tail -1)
# wp-cli como el usuario del sitio, con el PHP de Plesk y sin cargar plugins ni temas
WP() { runuser -u "$U" -- env WP_CLI_PHP="$PHPBIN" "$PHPBIN" "$(command -v wp)" --path="$P" --skip-plugins --skip-themes "$@" 2>&1; }

echo "=== $D | usuario $U | PHP $PHPBIN | WP $(WP core version) ==="
echo; echo "## mu-plugins"; ls -la --time-style=long-iso "$P/wp-content/mu-plugins" 2>&1 | grep -v '^total'
for f in "$P"/wp-content/mu-plugins/*.php; do [ -f "$f" ] && { echo "-- $f"; head -c 300 "$f" | cat -v; echo; }; done
echo; echo "## PHP no estandar en la raiz"
ls "$P"/*.php | grep -vE '/(index|wp-activate|wp-blog-header|wp-comments-post|wp-config|wp-config-sample|wp-cron|wp-links-opml|wp-load|wp-login|wp-mail|wp-settings|wp-signup|wp-trackback|xmlrpc)\.php$'
echo; echo "## PHP en uploads"; find "$P/wp-content/uploads" -type f -iname '*.ph*' | head -20
echo; echo "## Codigo ofuscado en wp-content (wordfence excluido)"
grep -rlE --include='*.php' 'eval *\( *(base64_decode|gzinflate|gzuncompress|str_rot13)|assert *\( *\$_|create_function|base64_decode *\( *\$_' "$P/wp-content" 2>/dev/null | grep -v wordfence | head -20
echo; echo "## PHP cambiados en 30 dias por ctime (fuera de cache, ultimos 25)"
find "$P" -type f -name '*.ph*' -ctime -30 -not -path '*/cache/*' -printf '%CY-%Cm-%Cd %CH:%CM  %p\n' | sort | tail -25
echo; echo "## auto_prepend y wp-config"
grep -rnE 'auto_prepend_file|auto_append_file' "$P" --include='.htaccess' --include='.user.ini' --include='php.ini' 2>/dev/null
for c in "$P/wp-config.php" "$P/../wp-config.php"; do [ -f "$c" ] && { echo "-- $c"; grep -nE 'include|require|eval|base64|\$_(GET|POST|COOKIE)' "$c"; }; done
echo; echo "## Integridad"; WP core verify-checksums | tail -15; WP plugin verify-checksums --all | grep -v '^Warning' | tail -15
echo; echo "## Administradores (incluye ocultos)"; PFX=$(WP db prefix | tail -1)
WP db query "SELECT u.ID,u.user_login,u.user_email,u.user_registered FROM ${PFX}users u JOIN ${PFX}usermeta m ON m.user_id=u.ID WHERE m.meta_key='${PFX}capabilities' AND m.meta_value LIKE '%administrator%'"
echo; echo "## Usuarios creados en 90 dias"
WP db query "SELECT ID,user_login,user_email,user_registered FROM ${PFX}users WHERE user_registered > NOW() - INTERVAL 90 DAY"
echo; echo "## Opciones con codigo"
WP db query "SELECT option_id,option_name,LENGTH(option_value) len FROM ${PFX}options WHERE option_value REGEXP 'eval[(]|fromCharCode|atob[(]' ORDER BY len DESC LIMIT 20"
echo; echo "## Plugins"; WP plugin list --fields=name,status,version,update_version
echo; echo "## Subidas por FTP del usuario (ultimas 15)"
cat /var/log/plesk/xferlog* 2>/dev/null | awk -v u="$U" '$14==u && $12=="i" {print $1,$2,$3,$4,$5,$7,$9}' | tail -15
echo; echo "## Logins correctos en wp-login (ultimos 15)"
zcat -f "$LOGS"/access*log* 2>/dev/null | awk '$6=="\"POST" && $7 ~ /wp-login\.php/ && $9==302 {print $1, $4}' | tail -15
echo; echo "## POST con exito a PHP no estandar (top 15)"
zcat -f "$LOGS"/access*log* 2>/dev/null | awk '$6=="\"POST" && $9==200 && $7 ~ /\.php/ && $7 !~ /(wp-login|admin-ajax|wp-cron|xmlrpc|wp-comments-post|guest\.vary)\.php/ {split($7,a,"?"); print a[1]}' | sort | uniq -c | sort -rn | head -15
echo "=== FIN ==="
