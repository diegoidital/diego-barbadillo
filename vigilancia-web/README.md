# Vigilancia web

Comprueba cada 10 minutos las páginas de `paginas.yml` y envía un email con los cambios.

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

3. **Rama:** cron-job.org lanza el workflow en `main` (`{"ref":"main"}`), así que los cambios deben estar fusionados en `main`.
4. **Prueba:** *Actions → Vigilancia web → Run workflow*, marcando **Enviar email de prueba** para comprobar que el correo llega. La primera ejecución de una página nueva solo guarda la instantánea; a partir de ahí se avisa por email de cada cambio.

## Notas

- Las instantáneas de texto se guardan en `snapshots/`, así que el historial de cambios queda en el repositorio.
- Si aparecen avisos falsos (fechas, contadores…), afina el `selector` o añade expresiones en `ignorar`.
- Si una página se genera con JavaScript, `requests` no verá el contenido; habría que usar Playwright.
- Para cambiar la frecuencia, cambia el *Schedule* del cronjob en cron-job.org.

## Si deja de lanzarse

- El workflow no tiene programación propia en GitHub (la de GitHub se retrasaba o se saltaba ejecuciones): solo lo lanza cron-job.org.
- **El token caduca el 4/11/2026.** Si la vigilancia sigue haciendo falta, genera uno nuevo antes y cámbialo en la cabecera `Authorization` del cronjob. Con el token caducado GitHub responde `401` y no se ejecuta nada, sin ningún aviso por email.
- En cron-job.org, el historial del cronjob debe mostrar `204 No Content`. Cualquier otra respuesta (`401`, `404`…) significa que no se está lanzando.
- Si una ejecución se queda esperando máquina en GitHub, la siguiente la sustituye (`cancel-in-progress`), y ninguna puede durar más de 5 minutos. Esas cancelaciones sueltas son normales.

## Lanzarlo desde fuera (cron-job.org)

La programación propia de GitHub no es fiable, así que el workflow lo lanza cron-job.org cada 10 minutos:

1. **Token de GitHub:** *GitHub → Settings → Developer settings → Personal access tokens → Fine-grained tokens → Generate new token*.
   - *Repository access:* solo `diegoidital/pruebas`.
   - *Permissions → Repository → Actions:* **Read and write**.
   - Copia el token (`github_pat_...`).
2. **cron-job.org:** crea una cuenta gratuita y un *Cronjob* nuevo:
   - *URL:* `https://api.github.com/repos/diegoidital/pruebas/actions/workflows/vigilancia-web.yml/dispatches`
   - *Schedule:* cada 10 minutos.
   - *Advanced → Request method:* `POST`
   - *Headers:*
     - `Authorization: Bearer github_pat_...`
     - `Accept: application/vnd.github+json`
     - `Content-Type: application/json`
   - *Request body:* `{"ref":"main"}`
3. Pulsa *Test run*: debe responder `204` y aparecer una ejecución nueva en *Actions*.

Prueba equivalente desde la terminal:

```bash
curl -X POST \
  -H "Authorization: Bearer github_pat_..." \
  -H "Accept: application/vnd.github+json" \
  https://api.github.com/repos/diegoidital/pruebas/actions/workflows/vigilancia-web.yml/dispatches \
  -d '{"ref":"main"}'
```
