# Hallazgos conocidos

Lista viva de patrones ya verificados en el servidor. Añade cada caso cerrado con su fecha y la prueba que lo confirmó. Un patrón conocido orienta, pero no sustituye a la comprobación: confirma siempre que el caso nuevo encaja de verdad.

## Falsos positivos habituales

| Patrón | Por qué salta | Cómo se confirmó |
|---|---|---|
| `access_log.webstat` con firma `SMW-INJ-19127-php.bkdr-4` | El log registra peticiones de exploit con código PHP en la URL. Ejemplo (02–03/11/2026, IP 45.61.170.128): exploit de ThinkCMF `index.php?a=fetch&...&content=<php>file_put_contents('0a30e0d6...php', ...)` contra alvarez2003.com, amspacio.com y 1arquitectura.com | Respuestas 301, el fichero `0a30e0d6...php` no existe; auditoría posterior de los sitios sin hallazgos |
| `error_log` con firma `SMW-INJ-CLOUDAV-php.upldr.upload-*` (intranet.leleman.eu) | Líneas antiguas (abril 2024) con cookies de sondeo en base64 (`echo "test";`, `echo 'Fox_';`) y peticiones a `/inputs.php` bloqueadas por el WAF de Imunify (403) | `inputs.php` no existe; los PHP con errores son de la aplicación (Dolibarr). Se archivó el log en `/root/` y se vació el 05/11/2026 |
| `wordfence/lib/wordfenceClass.php` al buscar código ofuscado | Wordfence contiene las firmas que busca | Fichero del plugin verificado por checksums |
| `google-site-kit/third-party/phpseclib/.../SymmetricKey.php` | phpseclib genera código dinámicamente de forma legítima | Librería conocida |

## mu-plugins legítimos vistos en el servidor

`elementor-safe-mode.php` (Elementor), `wp-toolkit.php` (Plesk WP Toolkit), `0-worker.php` (ManageWP), `0-modular-connector.php` (Modular DS; revisar que esté actualizado), `health-check-troubleshooting-mode.php`, `installatron_hide_status_test.php`, `1and1-subdomain.php`, `p3-profiler.php`. Hay además mu-plugins hechos a medida por el equipo (p. ej., `seguridad.php`, `__secwaf.php`, `optimizacion-scripts_agenciaSEO.php`, `savr-*`): ante la duda, mira la cabecera y pregunta al equipo.

`wp-content/maintenance.php` generado por WP Toolkit (cabecera "DO NOT MODIFY THIS FILE BECAUSE IT WAS GENERATED AUTOMATICALLY") es legítimo.

## Herramientas internas identificadas

| Herramienta | Cómo reconocerla | Notas |
|---|---|---|
| `mu-plugins/site-monitor-1030.php` (firma `SMW-BLKH-SA-CLOUDAV-php.bkdr.wpplugin.mal-WPPLUGIN1-0`) | Cabecera "Plugin Name: Site Monitor 1030 — Monitorización pasiva y de solo lectura…". Responde solo a un POST con cabecera `X-SM-Key` desde la IP autorizada 31.170.102.108. Desplegada por FTPS desde esa IP con el usuario FTP de cada suscripción (30/10–02/11/2026: autorent.es, metrolengua.com, emprendedoressostenibles.com, laorquideadedarwin.com, phonetronic.es) | No es malware. Imunify la borra. La IP 31.170.102.108 también entra por SSH como `constrprecs_k4zks` (construccionsprecises.es). Si aparece en un sitio nuevo, confirma igualmente la IP en xferlog y la cabecera |

## Pendientes abiertos (a fecha 05/11/2026)

- laorquideadedarwin.com: `wp-blog.php` en la raíz (4.214 bytes) existía el 30/10 y ya no está; no consta borrado por FTP. Nombre típico de webshell. Confirmar con el responsable de site-monitor si lo detectó y lo borró.
- alvarez2003.com: `subir.php` (y `old/subir.php`) suben imágenes con contraseña fija en el código y `directory` sin filtrar. Revisar el borrado de ficheros y proteger o retirar.
- 1arquitectura.com: revisar `uploads/backup/index.php`, la coincidencia en `unlimited-elements-for-elementor-premium/provider/functions_wordpress.class.php` y un POST con 200 a `/index.php`.
- gesinco.es y pdfmotomanual.com: ficheros vacíos con nombres aleatorios en mu-plugins (julio y marzo 2026). No vienen de ninguna alerta revisada; verificar si hubo infección.
