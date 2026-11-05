#!/bin/bash
# Lista los administradores de TODAS las instalaciones WordPress del servidor Plesk
# que se dieron de alta en los ultimos N dias o cuyo email coincide con un patron.
# Solo lectura. Usa el usuario 'admin' de MySQL de Plesk.
# Uso: bash admins-servidor.sh [dias] ['regex-email']
#   ej.: bash admins-servidor.sh 60
#        bash admins-servidor.sh 0 'zoe5751@yahoo.com|@wordpress\.org$'
DIAS="${1:-30}"; PATRON="${2:-}"
export MYSQL_PWD="$(cat /etc/psa/.psa.shadow)"
M="mysql -uadmin -N -B"
MAPA=$(plesk db -Ne "SELECT db.name, d.name FROM data_bases db JOIN domains d ON d.id=db.dom_id" 2>/dev/null)
COND="u.user_registered > NOW() - INTERVAL $DIAS DAY"
[ -n "$PATRON" ] && COND="$COND OR u.user_email REGEXP '$PATRON'"
printf 'BD\tDOMINIO\tID\tUSUARIO\tEMAIL\tALTA\n'
$M -e "SELECT table_schema, LEFT(table_name, LENGTH(table_name)-8) FROM information_schema.tables WHERE table_name LIKE '%\_usermeta'" \
  | while read -r db pfx; do
      dom=$(echo "$MAPA" | awk -v b="$db" '$1==b {print $2; exit}')
      $M "$db" -e "SELECT u.ID, u.user_login, u.user_email, u.user_registered FROM ${pfx}users u JOIN ${pfx}usermeta m ON m.user_id=u.ID WHERE m.meta_key='${pfx}capabilities' AND m.meta_value LIKE '%administrator%' AND ($COND)" 2>/dev/null \
        | sed "s/^/$db\t${dom:-?}\t/"
    done
unset MYSQL_PWD
