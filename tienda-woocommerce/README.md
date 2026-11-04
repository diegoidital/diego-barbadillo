# Tienda WooCommerce con pago por Qonto y TropiPay

WordPress 7 + WooCommerce, endurecido, con un plugin propio (`idital-pagos`).
Cuando el cliente acepta un presupuesto, el plugin genera **en el servidor** un
link de pago de Qonto o de TropiPay. El pedido se marca como pagado **solo después
de verificar el cobro con la propia pasarela**.

Todo está montado y probado con un simulador de las dos APIs. Para empezar a
cobrar faltan solo las credenciales reales y una ronda de pruebas en sus entornos sandbox.

```
tienda-woocommerce/
├── docker-compose.yml            WordPress + MariaDB endurecidos
├── docker-compose.pruebas.yml    añade el simulador de Qonto/TropiPay (solo local)
├── .env.example                  ajustes no secretos
├── secretos/                     contraseñas y credenciales (NO se sube a git)
├── config/                       wp-config extra, Apache y PHP endurecidos
├── scripts/                      preparar-secretos.sh, instalar.sh
├── wp-content/plugins/idital-pagos/   el plugin de pagos
├── wp-content/mu-plugins/idital-blindaje.php   endurecimiento de WordPress
└── pruebas/                      simulador, prueba en navegador y pruebas de ataque
```

## Cómo funciona el pago

1. **El presupuesto es un pedido de WooCommerce.** Se crea en *WooCommerce → Pedidos → Añadir*
   con estado "Pendiente de pago". Con "Enviar detalles del pedido al cliente", el cliente recibe
   un email con el enlace **Pagar este pedido**. También vale para compras normales desde el carrito.
2. En la página de pago el cliente ve dos opciones:
   - **Pagar vía Qonto (Transferencia/Tarjeta Europea)**
   - **Pagar vía TropiPay (Fondos TropiPay/Tarjetas Internacionales)**
3. Al pulsar *Pagar*, el servidor llama a la API correspondiente y crea el link
   (las claves nunca llegan al navegador).
   - **TropiPay**: el cliente va directo a la página de pago de TropiPay y vuelve a la tienda al terminar.
   - **Qonto**: sus links no permiten URL de vuelta. Por eso el cliente aterriza en la página
     de su pedido, que muestra el botón **Pagar ahora con Qonto** y, si se configuran, los datos
     para pagar por **transferencia**. La página se actualiza sola cuando entra el pago.
4. **Confirmación del pago:**
   - **Qonto** avisa por webhook. El aviso no se cree tal cual: el plugin pregunta a la API de Qonto
     el estado real del link. Si el webhook se pierde, una tarea revisa los pendientes cada 10 minutos.
   - **TropiPay** llama a una URL de notificación con una firma secreta distinta para cada pedido.
     Además se comprueban la firma de TropiPay, la referencia y el importe.
5. Si el importe o la moneda no cuadran, o llega un segundo cobro del mismo pedido, el pedido
   **no** se marca como pagado. Queda "En espera" con una nota y se envía un email al administrador.

> **Transferencias:** la API de links de Qonto solo admite tarjeta, Apple Pay, PayPal e iDEAL
> (a través de Mollie). La transferencia se ofrece mostrando IBAN + concepto (*WooCommerce → Ajustes →
> Pagos → Qonto*, sección "Datos para transferencia"). Esos pedidos se confirman a mano al ver el
> ingreso en Qonto.

## Puesta en marcha

Requisitos: Docker con Compose v2.

```bash
cd tienda-woocommerce
```

```bash
cp .env.example .env
```

```bash
./scripts/preparar-secretos.sh
```

```bash
./scripts/instalar.sh
```

La contraseña del administrador queda en `secretos/admin-wordpress.txt`. Mientras no haya
credenciales, **las pasarelas no se muestran a los clientes** (se desactivan solas).

### Probarlo sin credenciales (simulador)

Rellena con cualquier valor `secretos/qonto_login.txt`, `qonto_secret_key.txt`,
`tropipay_client_id.txt` y `tropipay_client_secret.txt`, y después:

```bash
PRUEBAS=1 ./scripts/instalar.sh
```

```bash
docker compose -f docker-compose.yml -f docker-compose.pruebas.yml run --rm wpcli wp eval-file /pruebas/crear-presupuesto.php 1250.50
```

Abre la URL `pay` que devuelve, elige una pasarela y paga en la página del simulador (`localhost:8090`).
Pruebas automáticas:

```bash
./pruebas/pruebas-seguridad.sh
```

## Cuando lleguen las credenciales

1. Guárdalas con el script, que las pide por teclado sin mostrarlas ni dejarlas en el historial:

   ```bash
   ./scripts/guardar-secreto.sh qonto_login
   ```

   ```bash
   ./scripts/guardar-secreto.sh qonto_secret_key
   ```

   (o escríbelas a mano, sin salto de línea final). Cada credencial va en:

   | Fichero | Contenido |
   |---|---|
   | `secretos/qonto_login.txt` | slug de la organización (Qonto → Ajustes → Integraciones → API) |
   | `secretos/qonto_secret_key.txt` | clave secreta de la API |
   | `secretos/qonto_staging_token.txt` | token de staging (solo para el sandbox de Qonto) |
   | `secretos/tropipay_client_id.txt` | Client ID de la cuenta de empresa |
   | `secretos/tropipay_client_secret.txt` | Client Secret |

2. Reinicia WordPress:

   ```bash
   docker compose restart wordpress
   ```

3. **Qonto:** conecta la cuenta con Mollie una vez (desde la app de Qonto o con
   `POST /v2/payment_links/connections`). Después da de alta el webhook con la URL que aparece en
   *WooCommerce → Ajustes → Pagos → Qonto* (ya incluye su token secreto).
4. Comprueba la conexión con Qonto. Primero solo lectura (no crea ni cobra nada):

   ```bash
   ./scripts/comprobar-qonto.sh
   ```

   y después un link real de 1 € creado por la tienda, que se desactiva sin pagarlo:

   ```bash
   ./scripts/comprobar-qonto.sh --link-prueba
   ```

   Una clave API sacada de la app normal de Qonto es de **producción**: requiere
   `IDITAL_QONTO_ENTORNO=produccion` en `.env`.
5. Prueba en **sandbox** (por defecto) los dos flujos y revisa *WooCommerce → Estado → Registros*
   (fuente `idital-pagos`).
6. Cuando todo cuadre, en `.env` pon `IDITAL_QONTO_ENTORNO=produccion` y/o
   `IDITAL_TROPIPAY_ENTORNO=produccion` y reinicia. **Hasta entonces no se mueve dinero real.**

### Puntos a confirmar en el sandbox

Se han hecho siguiendo la documentación y SDKs disponibles, pero hay que confirmarlos con las cuentas reales:

- [ ] **Qonto**: URL del sandbox (`thirdparty-sandbox.staging.qonto.co`) y formato del cuerpo del webhook.
      El plugin acepta cualquier formato porque solo usa el aviso para volver a consultar la API.
- [ ] **TropiPay**: URL del sandbox (`tropipay-dev.herokuapp.com`), endpoint del token
      (`/api/v2/access/token`) y que los importes van **en céntimos**.
- [ ] **TropiPay – firma de la notificación**: el plugin calcula
      `sha256(bankOrderCode + client_id + client_secret + originalCurrencyAmount)`. Si TropiPay firma de
      otra forma, los pagos **no** se confirmarán solos (aparecerá una nota "firma no válida" en el
      pedido). Es un fallo seguro: nunca da por pagado algo sin verificar. Se ajusta en
      `Idital_Pagos_Tropipay_Client::firma_valida()`.

## Seguridad

**Credenciales**
- Viven en `secretos/` (fuera de git, carpeta 700) y se montan como *Docker secrets* en `/run/secrets`.
- No están en la base de datos, ni en variables de entorno, ni en el panel. El panel solo muestra "✅ detectadas".
- El token de TropiPay se guarda en caché **cifrado** (libsodium).
- Los registros ocultan automáticamente claves, tokens, firmas e IBAN.

**Pagos**
- El pedido solo se marca como pagado en un punto: `Idital_Pagos_Confirmacion`. Allí se exige
  coincidencia exacta de importe (en céntimos) y moneda, hay un bloqueo atómico en base de datos
  contra procesos simultáneos y la operación es idempotente.
- Los webhooks de Qonto no se creen tal cual: siempre se consulta la API.
- TropiPay pasa por cuatro comprobaciones: URL con HMAC por pedido, firma de TropiPay, referencia
  generada para ese pedido e importe.
- Solo hay un link activo por pedido. Si el cliente cambia de pasarela, el link de Qonto se desactiva
  en Qonto y el de TropiPay se retira. Si aun así llegaran dos cobros, se detecta y se avisa.
- Solo se aceptan URLs de pago de dominios esperados (`*.qonto.com`, `*.tropipay.com`…).
  Todas las peticiones van por HTTPS con verificación TLS y sin seguir redirecciones.
- Endpoints públicos con límite de peticiones por IP y tamaño máximo de cuerpo. La consulta de
  estado exige la clave secreta del pedido y solo responde "pagado sí/no".
- Fuera de `local`/`development` el plugin ignora las URLs del simulador y no se puede desactivar la firma.

**WordPress / servidor**
- Base de datos sin puerto publicado y en red interna sin salida a internet. WordPress escucha solo en `127.0.0.1`.
- Bloqueados: XML-RPC, `wp-config.php`, `readme.html`, ficheros `.log/.sql/.env/.git…`, PHP en
  `uploads/`, PHP de `wp-includes`, el código interno del plugin y el listado de directorios.
- Usuarios: sin enumeración (`?author=`, REST, sitemaps) y mensajes de login genéricos.
  Tras 5 fallos de login, la IP queda bloqueada 15 minutos. Contraseñas de aplicación desactivadas.
- Cabeceras de seguridad: `X-Frame-Options`, `nosniff`, `Referrer-Policy`, `Permissions-Policy`,
  CSP `frame-ancestors` y HSTS con HTTPS.
- Sin editor de ficheros en el panel. Actualizaciones menores de WordPress automáticas.
  PHP sin `exec/system/shell_exec…`. Contenedores con `no-new-privileges`.
- Plugin de pagos y mu-plugin montados en **solo lectura**.

## Producción

- Pon delante un proxy con HTTPS (Caddy, Traefik, nginx o Cloudflare) que apunte a `127.0.0.1:8080`.
- En `.env`: `WP_ENVIRONMENT_TYPE=production`, `WP_FORZAR_SSL=true` y, si hay proxy que añada
  `X-Forwarded-For`, `IDITAL_PROXY_CONFIANZA=true`.
- Recomendado: `WP_BLOQUEAR_INSTALACIONES=true` (los plugins se actualizan con `wp-cli`),
  doble factor para administradores (plugin *Two Factor*), copias de seguridad diarias de
  los volúmenes `db_datos` y `wp_datos`, y un WAF (Cloudflare o similar).
- **En un hosting WordPress ya existente** (sin Docker): copia `wp-content/plugins/idital-pagos`
  y `wp-content/mu-plugins/idital-blindaje.php`, y define en `wp-config.php` las constantes de
  `config/wp-config-idital.php`. Lo ideal es leerlas de un fichero fuera de `public_html`.
  Las reglas de `config/apache/seguridad.conf` sirven como `.htaccess` adaptándolas.
