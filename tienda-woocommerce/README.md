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

## Instalación en un hosting con Plesk (sin Docker)

Es el caso de una web WordPress que ya existe (por ejemplo con LiteSpeed, Elementor y YITH Request a Quote).

1. **Copia de seguridad** de la web desde Plesk (*Copia de seguridad* o *WordPress Toolkit → Copia de seguridad*).
2. **Credenciales fuera de la carpeta pública**: en el *Administrador de archivos* de Plesk, sube
   `instalacion-plesk/idital-pagos-config.php` a la carpeta **`private`** (al mismo nivel que
   `httpdocs`) y rellena sus datos con el editor de Plesk. Permisos del fichero: **600**
   (solo el usuario de la suscripción puede leerlo).
3. **wp-config.php** (en `httpdocs`): justo antes de `/* That's all, stop editing! */` añade:

   ```php
   if ( is_readable( dirname( __DIR__ ) . '/private/idital-pagos-config.php' ) ) {
       require_once dirname( __DIR__ ) . '/private/idital-pagos-config.php';
   }
   ```

   Si el fichero no existe, la web sigue funcionando y las pasarelas simplemente no aparecen.
4. **Plugin**: *Plugins → Añadir nuevo → Subir plugin* con `idital-pagos.zip` (la carpeta
   `wp-content/plugins/idital-pagos` comprimida) y actívalo.
5. *WooCommerce → Ajustes → Pagos → Qonto* → **Probar conexión con Qonto**. Comprueba la clave, la conexión
   con Mollie y los métodos disponibles, sin crear ni cobrar nada. Copia la **URL del webhook** que aparece
   ahí para darla de alta en Qonto.
6. *WooCommerce → Ajustes → Pagos → TropiPay* → **Probar conexión con TropiPay** (pide un token y muestra
   los permisos de la credencial, sin crear ni cobrar nada).
7. Activa las pasarelas y haz un pedido de prueba de 1 € con cada una.
8. **Blindaje del servidor** (ver la lista completa en "Seguridad en producción"):
   - Pega `instalacion-plesk/htaccess-raiz.txt` al principio del `.htaccess` de `httpdocs`.
   - Guarda `instalacion-plesk/htaccess-uploads.txt` como `httpdocs/wp-content/uploads/.htaccess`.
   - *WordPress Toolkit → Seguridad*: aplica las medidas recomendadas.
   - Sube `wp-content/mu-plugins/idital-blindaje.php` a `httpdocs/wp-content/mu-plugins/`.
     Desactiva las contraseñas de aplicación: si alguna integración las usa, quita esa línea.

### TropiPay

1. En TropiPay → *Aplicaciones y credenciales*, crea una credencial **solo con los permisos necesarios**
   para crear links de pago y recibir cobros, y **restríngela a la IP del servidor** (en Plesk:
   *Sitios web y dominios → información del hosting*). Así, aunque se filtrara, no serviría desde otro sitio.
2. Prueba primero en el **sandbox** (`https://sandbox.tropipay.me`, con credenciales del sandbox y
   `IDITAL_TROPIPAY_ENTORNO = 'sandbox'`): las tarjetas de prueba están en la documentación de TropiPay
   (*Testing Card Payments*). Las credenciales de sandbox y de producción no son intercambiables.
3. Cuando todo cuadre, crea la credencial de producción y pon `IDITAL_TROPIPAY_ENTORNO = 'produccion'`.

Integración según la documentación oficial (https://doc.tropipay.com):
token `POST /api/v3/access/token`, link `POST /api/v3/paymentcards` (céntimos, devuelve `shortUrl`),
aviso de pago firmado con `signaturev3 = sha256(bankOrderCode + clientId + sha1(clientSecret) + originalCurrencyAmount)`.

**LiteSpeed Cache**: el plugin marca como "no cachear" sus respuestas y la página del pedido pendiente de pago.

**Presupuestos con YITH Request a Quote**: la versión gratuita solo envía la solicitud por email; no crea
pedidos. El presupuesto aceptado se convierte en pedido en *WooCommerce → Pedidos → Añadir*, con estado
"Pendiente de pago", y se envía al cliente con "Enviar detalles del pedido al cliente". La versión Premium
de YITH crea ese pedido automáticamente cuando el cliente acepta; el plugin es compatible con sus estados.

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

### Puntos a confirmar con un pago real de prueba

- [ ] **Qonto**: formato del cuerpo del webhook. El plugin acepta cualquier formato porque solo usa el
      aviso para volver a consultar la API.
- [ ] **TropiPay**: la documentación oficial tiene ejemplos contradictorios en dos detalles: si
      `originalCurrencyAmount` llega en céntimos y el formato de `expirationDate`. Si algo no cuadra,
      el pedido **no** se marca como pagado: queda una nota para revisarlo a mano.

## Seguridad

**Credenciales**
- Viven en `secretos/` (fuera de git, carpeta 700) y se montan como *Docker secrets* en `/run/secrets`.
- No están en la base de datos, ni en variables de entorno, ni en el panel. El panel solo muestra "✅ detectadas".
- El token de TropiPay se guarda en caché **cifrado** (libsodium).
- Los registros ocultan automáticamente claves, tokens, firmas e IBAN.

**Pagos**
- **Modo pruebas blindado**: una pasarela en sandbox en una web real solo la ven los gestores, y sus
  cobros **nunca** marcan un pedido como pagado (quedan como nota "pago de prueba").
- Cada link de Qonto y cada operación de TropiPay se registran de forma atómica y permanente
  contra un único pedido: una notificación o un cobro no pueden reutilizarse en otro pedido.
- TropiPay: además de la firma oficial, el aviso tiene que venir del link creado para ese pedido
  (`paymentcardId`), y una operación notificada como fallida no puede reenviarse luego como pagada.
- Los links de Qonto anteriores de un pedido también se vigilan: si el cliente paga uno antiguo, se detecta.
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
- Endpoints públicos con tamaño máximo de cuerpo y límite de peticiones por IP. Solo cuentan los
  intentos fallidos, así que las notificaciones legítimas nunca se frenan. Solo se confía en
  `X-Forwarded-For` si la petición llega desde un proxy declarado (`IDITAL_PROXY_CONFIANZA`,
  lista de IP/CIDR). La consulta de
  estado exige la clave secreta del pedido y solo responde "pagado sí/no".
- Fuera del entorno `local` el plugin solo habla con los hosts oficiales de Qonto y TropiPay y no se
  puede desactivar la firma.
- Limitación conocida: TropiPay no documenta cómo anular un link. Si el cliente cambia de método, el
  link anterior deja de ofrecerse pero sigue siendo pagable hasta que caduca (1 día por defecto). Si
  se pagara, el sistema lo detecta como posible pago duplicado y avisa para reembolsarlo.

**WordPress / servidor**
- Base de datos sin puerto publicado y en red interna sin salida a internet. WordPress escucha solo en `127.0.0.1`.
- Bloqueados: XML-RPC, `wp-config.php`, `readme.html`, ficheros `.log/.sql/.env/.git…`, PHP en
  `uploads/`, PHP de `wp-includes`, el código interno del plugin y el listado de directorios.
- Usuarios: sin enumeración (`?author=`, REST, sitemaps) y mensajes de login genéricos.
  Tras 5 fallos de login, esa IP queda bloqueada 15 minutos para ese usuario (sin afectar a los demás). Contraseñas de aplicación desactivadas.
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

## Seguridad en producción (lista de comprobación)

El plugin y el código ya hacen su parte. Lo siguiente depende de la configuración de la cuenta y del
servidor, y sin ello ningún código basta:

- [ ] **Doble factor (2FA) para todos los administradores** de WordPress (plugin *Two Factor* o
      *WordPress Toolkit → Seguridad*), y también en Plesk, Qonto y TropiPay.
- [ ] **Mínimos administradores**: cada persona con su usuario; nadie comparte cuentas. Revisa
      *Usuarios* y quita los que sobren.
- [ ] **Credenciales mínimas**: en TropiPay, permisos mínimos y restricción por IP del servidor.
      Regenera cualquier clave que haya pasado por email, WhatsApp o chat.
- [ ] **`private/idital-pagos-config.php` con permisos 600**, fuera de `httpdocs`, y con
      `IDITAL_PAGOS_CLAVE_HMAC` e `IDITAL_QONTO_WEBHOOK_TOKEN` rellenos con valores aleatorios.
- [ ] **HTTPS forzado** (Let's Encrypt en Plesk + "Redirección permanente SEO de HTTP a HTTPS").
- [ ] **Cortafuegos de aplicaciones web** (Plesk → *Web Application Firewall / ModSecurity*, modo
      "Activado") y **Fail2Ban** activos. Imunify360, si el proveedor lo ofrece.
- [ ] **Actualizaciones**: WordPress, plugins y PHP al día (WordPress Toolkit permite automatizarlas).
      Quita los plugins y temas que no se usen.
- [ ] **Copias de seguridad automáticas** diarias, guardadas fuera del servidor.
- [ ] **Avisos**: el email de administrador de WordPress debe ser uno que se lea. Ahí llegan los avisos
      de pagos anómalos (importe distinto, pago duplicado, firma no válida).
- [ ] Revisa de vez en cuando *WooCommerce → Estado → Registros* (fuente `idital-pagos`).
