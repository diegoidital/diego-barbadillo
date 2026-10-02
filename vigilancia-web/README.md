# Vigilancia web

Comprueba cada 20 minutos las páginas de `paginas.yml` y envía un email con los cambios.

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

3. **Rama por defecto:** GitHub solo ejecuta las tareas programadas desde la rama por defecto, que debe ser `main` (*Settings → General → Default branch*).
4. **Prueba:** *Actions → Vigilancia web → Run workflow*, marcando **Enviar email de prueba** para comprobar que el correo llega. La primera ejecución de una página nueva solo guarda la instantánea; a partir de ahí se avisa por email de cada cambio.

## Notas

- Las instantáneas de texto se guardan en `snapshots/`, así que el historial de cambios queda en el repositorio.
- Si aparecen avisos falsos (fechas, contadores…), afina el `selector` o añade expresiones en `ignorar`.
- Si una página se genera con JavaScript, `requests` no verá el contenido; habría que usar Playwright.
- Para cambiar la frecuencia, edita el `cron` en `.github/workflows/vigilancia-web.yml` (hora UTC).

## Si no arranca solo

- GitHub no garantiza la hora de las tareas programadas: pueden llegar con retraso o saltarse alguna, sobre todo las primeras horas después de crearlas. Por eso se lanza cada 20 minutos.
- La programación se registra al llegar el workflow a la rama por defecto. Si no hay ninguna ejecución con evento `schedule` tras un par de horas, edita la línea `cron` (aunque sea el minuto) y fusiona el cambio en la rama por defecto para que GitHub la vuelva a registrar.
- Comprueba que el workflow no esté desactivado (*Actions → Vigilancia web → ⋯ → Enable workflow*). GitHub desactiva las programadas tras 60 días sin actividad en el repositorio.
- La rama por defecto debe llamarse `main`: con nombres raros (p. ej. `claude/...`) la programación puede no registrarse nunca.

## Lanzarlo desde fuera (cron-job.org)

La programación de GitHub es «lo mejor que puede». Para que se lance seguro cada 20 minutos:

1. **Token de GitHub:** *GitHub → Settings → Developer settings → Personal access tokens → Fine-grained tokens → Generate new token*.
   - *Repository access:* solo `diegoidital/pruebas`.
   - *Permissions → Repository → Actions:* **Read and write**.
   - Copia el token (`github_pat_...`).
2. **cron-job.org:** crea una cuenta gratuita y un *Cronjob* nuevo:
   - *URL:* `https://api.github.com/repos/diegoidital/pruebas/actions/workflows/vigilancia-web.yml/dispatches`
   - *Schedule:* cada 20 minutos.
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

Con el lanzador externo activo puedes dejar el `schedule` del workflow como respaldo: el bloque `concurrency` evita que dos ejecuciones se pisen.
