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

## Incidentes reales resueltos (indicadores)

Útiles para reconocer la misma familia de malware o la misma técnica en otro sitio. Las IPs de botnet cambian: úsalas para buscar en logs, no como prueba única.

| Sitio | Fecha | Vía de entrada | Qué se encontró | Indicadores |
|---|---|---|---|---|
| phonetronic.es | 16/09/2026 | Login con una cuenta de administrador válida (credencial reutilizada en otros sitios) | 14:40 acceso, 14:41 instalación de plugin, ficheros camuflados, 14:42 extracción de datos de clientes, cuenta fraudulenta | Cambiar esa credencial en todos los sitios y 2FA. Valorar notificación RGPD |
| lavahome.es | 24/07–22/09/2026 | Cuenta `admin` comprometida (creaba administradores desde varias IPs) | Administradores fraudulentos `f114145efde2` (24/07), `Vivienne` (24/08) y `Nora` (22/09). Dos instalaciones del mismo backdoor: "Locale Performance Cache" v1.5.0 (`plugins/wp-locale-perf/locale-performance-cache.php`, 24/08) y "WP Secure Backup" v1.5.0 (`plugin-30-wp-secure-backup.zip`, 22/09, el detectado por Imunify) | El backdoor muestra un aviso falso "Locale Performance Cache". Persistencia en opciones, ficheros ocultos, post-meta y cron `backup_manager_reconcile`; `*_user_level` elevado a 10. IPs: 156.0.200.134, 216.26.230.8, 97.82.74.7, 174.70.109.158, 31.128.188.204. La cadena se reconstruyó con WP Activity Log |
| kaironparticular.com | 09/2026 | Vulnerabilidad de Tutor LMS (CVE-2026-78175) vía `admin-ajax.php` | Backdoor `wp-content/a15b80c58f.php`, administrador fraudulento | Botnet (Brasil, Argentina, Perú, Rumanía): 45.179.74.228, 45.179.74.229, 190.226.146.15, 129.227.46.171, 129.227.46.142, 79.117.222.175, 186.39.175.189, 190.192.18.94; escáner 146.103.18.19. Dos WordPress en la suscripción (`httpdocs/` activo y `httpdocs/public_html/`). La IP 83.48.104.164 (residencial española, login tras la detección) era casi seguro del equipo: no se bloqueó sin confirmar |
| dspga.es | 12/09/2025 – 01/10/2026 | Cuenta fraudulenta creada en 2025 (login al primer intento el 01/10, tras intentos fallidos el 25/09) | Cuenta `Zoe` (zoe5751@yahoo.com) y otra ya eliminada (ID 2). 01/10 09:01–09:02 acceso e instalación de plugin malicioso, 09:04 detección de Imunify, 09:31 recreación de cuentas | Buscar `zoe5751@yahoo.com` o emails `@wordpress.org` en todas las BD. Se cambió la contraseña de la BD, PHP 8.0→8.3, `DISALLOW_FILE_MODS` temporal. fail2ban estaba parado |

## Plugins pirateados vistos

| Sitio | Plugin | Qué pasó |
|---|---|---|
| viajeslinguisticos.es | WP Mail SMTP Pro descargado de gplcoffee | Imunify marcó `wp_mail_smtp.php` (firma de tipo *dropper*) por un bloque que falsea el servidor de licencias con `pre_http_request`, y lo limpió (`cleanup_done`). Después dejaron de salir los correos por SMTP. Diagnóstico: proveedor y configuración en la opción `wp_mail_smtp`, último error en `wp_mail_smtp_debug`, conexión al host:puerto con `openssl s_client`. Solución rápida: WP Mail SMTP gratuito con el mismo SMTP; definitiva: Pro oficial con licencia |

## Pendientes abiertos (a fecha 05/11/2026)

- laorquideadedarwin.com: `wp-blog.php` en la raíz (4.214 bytes) existía el 30/10 y ya no está; no consta borrado por FTP. Nombre típico de webshell. Confirmar con el responsable de site-monitor si lo detectó y lo borró.
- alvarez2003.com: `subir.php` (y `old/subir.php`) suben imágenes con contraseña fija en el código y `directory` sin filtrar. Revisar el borrado de ficheros y proteger o retirar.
- 1arquitectura.com: revisar `uploads/backup/index.php`, la coincidencia en `unlimited-elements-for-elementor-premium/provider/functions_wordpress.class.php` y un POST con 200 a `/index.php`.
- gesinco.es y pdfmotomanual.com: ficheros vacíos con nombres aleatorios en mu-plugins (julio y marzo 2026). No vienen de ninguna alerta revisada; verificar si hubo infección.
