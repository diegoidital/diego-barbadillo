#!/bin/bash
# Analiza una detección de Imunify360 en un fichero de log de Plesk. Solo lectura.
# Uso: bash log-detection.sh /var/www/vhosts/<sub>/logs/[<dominio>/]<fichero>  [dias]
# Escribe el resultado en /root/triage-log-<dominio>.txt y lo muestra.
set +H
F="$1"; DIAS="${2:-3}"
[ -n "$F" ] || { echo "Uso: $0 /ruta/al/log [dias]"; exit 1; }
SUB=$(echo "$F" | sed -n 's#^/var/www/vhosts/\([^/]*\)/logs/.*#\1#p')
DOM=$(echo "$F" | sed -n 's#^/var/www/vhosts/[^/]*/logs/\([^/]*\)/[^/]*$#\1#p')
[ -n "$DOM" ] || DOM="$SUB"
LOGS=/var/www/vhosts/system/$DOM/logs
OUT=/root/triage-log-$DOM.txt
PAT='<\?|%3C%3F|base64|eval\(|system\(|shell_exec|passthru|file_put_contents|%24_|\$_(GET|POST|FILES|REQUEST)|cmd=|wget |/bin/sh'
FECHAS=$(for i in $(seq 0 "$DIAS"); do date -d "-$i day" '+%d/%b/%Y'; done | paste -sd'|')
{
echo "=== Deteccion en log: $F (suscripcion $SUB, dominio $DOM) ==="
echo; echo "## Registro en Imunify"
imunify360-agent malware malicious list --limit 5000 --json 2>/dev/null | tr '{' '\n' | grep -F "\"$F\"" | cut -c1-500
echo; echo "## Estado actual del fichero"; ls -la --time-style=long-iso "$F"
echo; echo "## Lineas con codigo en el propio fichero (si aun lo contiene)"
grep -caE "$PAT" "$F"; grep -aE "$PAT" "$F" | tail -3 | cut -c1-300
echo; echo "## Peticiones con codigo en los logs de acceso (ultimos $DIAS dias): IP, fecha, codigo, URL"
zcat -f "$LOGS"/access*log* 2>/dev/null | grep -aE "$FECHAS" | grep -aiE "$PAT" \
  | awk '{print $1, $4, $9, substr($7,1,120)}' | sort -k2 | tail -20
echo; echo "## Ficheros que el ataque intenta crear y si existen"
for n in $(zcat -f "$LOGS"/access*log* 2>/dev/null | grep -aE "$FECHAS" \
            | grep -aoE "file_put_contents\(['\"][^'\"]+" | sed -E "s/.*\(['\"]//" | sort -u); do
  echo "-- $n"
  echo "   En disco:"; ls -la /var/www/vhosts/"$SUB"/*/"$n" /var/www/vhosts/"$SUB"/domains/*/public_html/"$n" 2>/dev/null | sed 's/^/   /'
  echo "   Peticiones a ese fichero (codigo):"
  zcat -f "$LOGS"/access*log* 2>/dev/null | grep -aF "/$n" | awk '{print "   ", $1, $4, $9}' | tail -5
done
echo; echo "## (solo error_log) PHP que generan errores"
case "$F" in *error_log*)
  grep -aoE '/var/www/vhosts/[^ :,]+\.php' "$F" | sort | uniq -c | sort -rn | head -15;;
esac
echo "=== FIN ==="
} > "$OUT" 2>&1
echo "Resultado en $OUT"; cat "$OUT"
