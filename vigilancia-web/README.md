# Vigilancia web

Comprueba cada hora las páginas de `paginas.yml` y envía un email con los cambios.

## Puesta en marcha

1. **Páginas:** edita `paginas.yml` con la URL y, si puede ser, un `selector` CSS de la zona a vigilar.
2. **Secrets:** en GitHub ve a *Settings → Secrets and variables → Actions → New repository secret* y crea:

   | Secret | Ejemplo |
   |---|---|
   | `SMTP_HOST` | `smtp.tuservidor.com` / `smtp-relay.brevo.com` |
   | `SMTP_PORT` | `465` (SSL) o `587` (STARTTLS) |
   | `SMTP_USER` | `avisos@idital.com` |
   | `SMTP_PASS` | contraseña o clave SMTP |
   | `MAIL_FROM` | (opcional) remitente; por defecto `SMTP_USER` |
   | `MAIL_TO`   | `soporte@idital.com` (varios separados por comas) |

3. **Rama por defecto:** GitHub solo ejecuta las tareas programadas desde la rama por defecto, así que hay que fusionar esto en ella.
4. **Prueba:** *Actions → Vigilancia web → Run workflow*, marcando **Enviar email de prueba** para comprobar que el correo llega. La primera ejecución de una página nueva solo guarda la instantánea; a partir de ahí se avisa por email de cada cambio.

## Notas

- Las instantáneas de texto se guardan en `snapshots/`, así que el historial de cambios queda en el repositorio.
- Si aparecen avisos falsos (fechas, contadores…), afina el `selector` o añade expresiones en `ignorar`.
- Si una página se genera con JavaScript, `requests` no verá el contenido; habría que usar Playwright.
- Para cambiar la frecuencia, edita el `cron` en `.github/workflows/vigilancia-web.yml` (hora UTC).
