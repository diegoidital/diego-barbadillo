#!/bin/bash
# Lista las detecciones de Imunify360 con sus datos reales (fecha, tipo de escaneo,
# estado, firma, tamaño y ruta) a partir de la salida JSON. Solo lectura.
# Uso: bash imunify-detections.sh ['patron-regex-de-rutas']   (ej. 'autorent|leleman')
FILTRO="${1:-.}"
imunify360-agent malware malicious list --limit "${LIMIT:-5000}" --json 2>/dev/null \
  | tr '{' '\n' | grep '"file"' | grep -E -- "$FILTRO" \
  | while IFS= read -r l; do
      campo() { echo "$l" | sed -n "s/.*\"$1\": \"\{0,1\}\([^\",]*\)\"\{0,1\}[,}].*/\1/p"; }
      c=$(campo created); c=${c%%.*}
      [ -n "$c" ] && [ "$c" != "null" ] && fecha=$(date -d "@$c" '+%F %H:%M') || fecha="?"
      printf '%s | %-10s | %-15s | %s | %s bytes | %s\n' "$fecha" "$(campo scan_type)" \
        "$(campo status)" "$(campo type)" "$(campo size)" "$(campo file)"
    done | sort
