---
name: triage-imunify360
description: Investiga y clasifica alertas de Imunify360 (correos "Imunify360 Security Events Digest", "Malicious files detected", detecciones SMW-*, ficheros limpiados o en cuarentena) en servidores Plesk con sitios WordPress y PHP, y decide con pruebas si cada detección es una infección real, un falso positivo (por ejemplo, un ataque que solo quedó anotado en un log) o una herramienta interna. Úsala siempre que el usuario pegue un aviso de Imunify360, mencione ficheros maliciosos detectados en un dominio del servidor, pregunte si una web está infectada tras una alerta, o quiera revisar mu-plugins, backdoors, webshells o logs marcados como maliciosos, aunque no nombre Imunify. No la uses para redactar el informe al cliente de un incidente ya resuelto (para eso está informe-incidente-seguridad).
---

# Triage de alertas de Imunify360 (Plesk + WordPress)

El objetivo es llegar, para cada fichero detectado, a un veredicto **respaldado por pruebas**: infección real, falso positivo, herramienta interna o pendiente. Imunify detecta firmas, no intrusiones; una detección no demuestra una infección y que Imunify haya "limpiado" un fichero no demuestra que el sitio esté limpio.

No tienes acceso al servidor. Trabajas pidiendo al usuario que ejecute comandos como root y te pegue la salida. Por eso el diseño de los comandos importa tanto como el análisis (ver "Cómo pedir comandos").

## Principios (aprendidos en casos reales)

- **No afirmes nada que no hayas comprobado.** Distingue siempre en la respuesta entre *comprobado*, *descartado* y *sin revisar*. "El exploit no funcionó" no equivale a "el sitio está limpio": esto último exige auditar el sitio.
- **No inventes casos.** No conviertas en "infección" un fichero vacío o un nombre raro sin una detección o una prueba que lo respalde; menciónalo como algo a verificar.
- **Corrige rápido tus hipótesis.** Si una pista (p. ej., "fue el plugin X") no se sostiene con los datos (códigos 301/403, versión no vulnerable), dilo explícitamente y descártala.
- **Pide decisiones al humano cuando sean suyas**: reconocer una IP, un usuario SSH o una herramienta propia, o confirmar administradores con el cliente.
- **Primero pruebas, luego cambios.** Hasta tener el veredicto, solo comandos de lectura. No borres ficheros vacíos, `.tmp` ni copias de cuarentena: son las pruebas.

## Flujo de trabajo

### 1. Inventario de las detecciones
De los avisos pegados, extrae una tabla: fecha, tipo de escaneo, dominio, ruta y acción de Imunify. Después pide el detalle real de Imunify, que es más fiable que el correo:

```bash
bash /root/triage/imunify-detections.sh 'dominio1|dominio2'
```
(script en `scripts/imunify-detections.sh`; muestra fecha, tipo de escaneo, estado, firma, tamaño y ruta usando `--json`; no parsees columnas de la salida en texto, cambian según la fila).

Lee la firma: `bkdr` = backdoor, `upldr` = uploader, `INJ` = código inyectado dentro de un fichero. El nombre orienta, pero no digas qué patrón exacto busca si no lo sabes.

### 2. Clasifica por tipo de fichero
- **Ficheros de log** (`/var/www/vhosts/*/logs/...`: `access_log.webstat`, `error_log`, `access_ssl_log`): casi siempre son peticiones de ataque registradas en texto. Ve al paso 3A.
- **Ficheros dentro de la raíz web** (`httpdocs`, `public_html`, `wp-content/...`): ve al paso 3B.

### 3A. Detección en un log
```bash
bash /root/triage/log-detection.sh /ruta/al/log/detectado
```
El script busca en los logs de acceso reales las peticiones con código, saca los nombres de fichero que intentan crear (`file_put_contents(...)`), comprueba si existen y con qué código respondió el servidor.

Veredicto **falso positivo** solo si: el fichero que intentaba crear el exploit no existe, las respuestas son 301/403/404 (ningún 200 al fichero creado) y en los `error_log` los PHP que generan errores son de la propia aplicación. Si hay un 200 al fichero creado o el fichero existe, es un sitio comprometido: ve a 3B.

`access_log.webstat` es una copia temporal que Plesk vacía cada día al generar estadísticas; si ya tiene 0 bytes, el contenido sigue en `/var/www/vhosts/system/<dominio>/logs/access*log*`.

### 3B. Fichero en la raíz web
1. **¿Cómo llegó?** Mira las subidas por FTP del usuario del sitio (`/var/log/plesk/xferlog*`: campo 12 `i`=subida, `o`=descarga, `d`=borrado; campo 14 = usuario; campo 7 = IP) y los accesos SSH (`/var/log/secure*`). Si el fichero aparece subido por FTP, la vía de entrada son credenciales o una herramienta, no una vulnerabilidad web.
2. **¿Qué es?** Imunify guarda los originales limpiados en `/var/imunify360/cleanup_storage/` (nombre = hash). Localízalo por tamaño (`find /var/imunify360 -type f -size <bytes>c`) y muestra solo la cabecera y un resumen con `cat -v` / `grep -ao`; no lo ejecutes ni lo copies a la web.
3. **Audita el sitio** con `scripts/check-site.sh` (WordPress): mu-plugins, PHP fuera de lugar, código ofuscado, cambios en 30 días, integridad del núcleo y plugins, administradores (incluidos ocultos), plugins desactualizados, subidas FTP y POST a PHP no estándar.
4. **¿Afecta a más sitios?** Revisa los `mu-plugins` de todo el servidor con globs (rápido), nunca con un `find` sobre todo `/var/www/vhosts` (tarda muchísimo):
   ```bash
   ls -la --time-style=long-iso /var/www/vhosts/*/httpdocs/wp-content/mu-plugins /var/www/vhosts/*/*/wp-content/mu-plugins /var/www/vhosts/*/domains/*/public_html/wp-content/mu-plugins 2>/dev/null | grep -E '^/|\.php$'
   ```

Antes de calificar algo de malicioso, contrasta con `references/hallazgos-conocidos.md` (falsos positivos habituales, mu-plugins legítimos y herramientas internas ya identificadas).

### 4. Veredicto y acciones
Para cada detección:

| Fichero | Firma | Veredicto | Evidencia | Acción |
|---|---|---|---|---|

Veredictos posibles: **Infección real**, **Falso positivo**, **Herramienta interna**, **Pendiente** (indica qué falta).

Acciones típicas, siempre después del veredicto y avisando de lo que modifican:
- Log con falso positivo que se repite en cada escaneo: archivarlo y vaciarlo sin cambiar propietario ni permisos (`gzip -c $F > /root/<nombre>-$(date +%F).gz && : > $F`), y revisar la rotación de logs del dominio en Plesk. Alternativa: lista de ignorados (`imunify360-agent malware ignore --help` para confirmar sintaxis).
- IP atacante persistente: `imunify360-agent blacklist ip add <IP> --comment "..."`.
- Herramienta interna: avisar a su responsable de que Imunify la seguirá borrando y de que la máquina que la despliega guarda credenciales de clientes.
- Infección real: contención (bloquear IP, cambiar credenciales FTP/WP/BD, regenerar claves con `wp config shuffle-salts`), limpieza, actualización y, una vez resuelto, el informe al cliente con la skill `informe-incidente-seguridad`.

## Cómo pedir comandos (el usuario pega en una consola SSH o web)

- **Historial de bash:** `!` dentro de comillas dobles rompe el pegado (`!!` = último comando). Empieza los bloques largos con `set +H` y evita `!` en comillas dobles.
- **Pegados largos se cortan:** escribe scripts con heredoc en partes de ~15 líneas (`cat > f <<'EOF'` y luego `cat >> f <<'EOF'`), o pide subir los scripts de `scripts/` a `/root/triage/` con el gestor de archivos de Plesk.
- **Salidas largas se cortan:** que los scripts escriban en un fichero, ejecútalos con `nohup ... &` y pide `cat`, `tail` o `sed -n '/## Sección/,$p'` por partes.
- **wp-cli:** el PHP de consola puede ser demasiado antiguo para WordPress. Usa el PHP de Plesk del sitio (`/opt/plesk/php/X.Y/bin/php`, versión desde `plesk db` → `hosting.php_handler_id`) y ejecuta como el usuario del sitio con `runuser -u <usuario>`, siempre con `--skip-plugins --skip-themes` para no ejecutar código del sitio comprometido. Nunca como root.
- **wp-config.php** puede estar un nivel por encima de la raíz web; saca el prefijo de tablas con `wp db prefix`.
- **Rutas en este tipo de Plesk:** raíz web en `/var/www/vhosts/<sub>/httpdocs`, `/var/www/vhosts/<sub>/domains/<dom>/public_html` o `/var/www/vhosts/<sub>/<dom>` (obtenla con `plesk db -Ne "SELECT h.www_root FROM domains d JOIN hosting h ON h.dom_id=d.id WHERE d.name='<dom>'"`); logs web en `/var/www/vhosts/system/<dom>/logs/`. `/X/public_html` y `/X/domains/X/public_html` suelen ser la misma carpeta enlazada.
- Las fechas `epoch` de Imunify se convierten con `date -d @<epoch>`; son de detección o limpieza, no de creación del fichero. `ctime` (no `mtime`) es la fecha de cambio no falsificable.

## Formato de la respuesta
En español, directo, con tablas para hallazgos. Cada mensaje termina con los siguientes comandos numerados (uno por bloque, cortos) y qué se espera ver en cada uno para decidir. Cuando se cierre un caso, resume en una tabla todas las detecciones con su veredicto y lista lo pendiente.
