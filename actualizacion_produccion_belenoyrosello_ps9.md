# Actualización de producción: belenoyrosello.com a PrestaShop 9.1.5 + PHP 8.3

**Cuándo:** 27/10/2026 a las 8:00. **Duración estimada:** 60–90 min, de ellos ~45 min con la tienda cerrada.

**Camino:** 1.7.8.11 → 8.2.8 → 9.1.5. Es el mismo que se probó en staging. Las correcciones de código se copian desde staging, que ya las tiene aplicadas y probadas.

**Reglas para pegar en el terminal:**
- Un bloque cada vez. Espera a que termine antes de pegar el siguiente.
- Si un bloque no da lo que dice "✅ Esperado", **para** y avisa.
- Los bloques empiezan definiendo sus variables, así que se pueden pegar aunque se haya cerrado el terminal. Las únicas excepciones son los bucles de actualización: **2.2b** y **2.3b** tienen que ir en la **misma ventana** que el bloque anterior.

---

## Fase 1 · Cerrar la tienda y hacer copias (~5 min)

### 1.1 Pausar el vigilante y poner modo mantenimiento
Cambia la IP si no es la vuestra. Puedes comprobarla en https://www.cual-es-mi-ip.net.
```bash
IP=83.48.104.164
mv /etc/cron.d/vigila_beleno /root/vigila_beleno.cron.pausado
export MYSQL_PWD="$(cat /etc/psa/.psa.shadow)"
mysql -u admin beleno_shop -e "UPDATE ps_configuration SET value='0' WHERE name='PS_SHOP_ENABLE'; UPDATE ps_configuration SET value=CONCAT_WS(',', NULLIF(value,''), '$IP', '127.0.0.1') WHERE name='PS_MAINTENANCE_IP'; SELECT name, value FROM ps_configuration WHERE name IN ('PS_SHOP_ENABLE','PS_MAINTENANCE_IP');"
unset MYSQL_PWD; rm -rf /var/www/vhosts/belenoyrosello.com/domains/belenoyrosello.com/public_html/var/cache/*
```
✅ Esperado:
- `PS_SHOP_ENABLE` = 0, y vuestra IP en `PS_MAINTENANCE_IP`.
- En una ventana de incógnito (sin vuestra IP), la tienda muestra "mantenimiento".

### 1.2 Copias de ficheros y base de datos
```bash
TS=$(date +%F_%H%M); echo $TS > /root/marca_prod_pre9.txt; echo "Marca: $TS"
cd /var/www/vhosts/belenoyrosello.com/domains/belenoyrosello.com && tar czf /root/prod_pre9_$TS.tgz public_html
MYSQL_PWD="$(cat /etc/psa/.psa.shadow)" mysqldump -u admin --single-transaction --routines --triggers beleno_shop | gzip > /root/beleno_shop_pre9_$TS.sql.gz
ls -lh /root/*pre9_$TS*; zcat /root/beleno_shop_pre9_$TS.sql.gz | grep -c "CREATE TABLE"
```
✅ Esperado:
- El `.tgz` (unos 2,5 GB) y el `.sql.gz` (unos 210 MB).
- Unas **390** tablas.

---

## Fase 2 · Actualizar PrestaShop (~25 min)

### 2.1 Preparar las descargas (desde staging)
```bash
P=/var/www/vhosts/belenoyrosello.com/domains/belenoyrosello.com/public_html; S=/var/www/vhosts/belenoyrosello.com/staging.belenoyrosello.com; D=admin1910hvr3w/autoupgrade/download
OWN=$(stat -c '%U:%G' $P/index.php); echo "Propietario: $OWN"
cp -p $S/$D/prestashop_8.2.8.zip $S/$D/prestashop_8.2.8.xml $S/$D/prestashop_9.1.5.zip $S/$D/prestashop_9.1.5.xml $P/$D/
chown $OWN $P/$D/*; ls -lh $P/$D/
grep -oP "version\s*=\s*'\K[^']+" $P/modules/autoupgrade/autoupgrade.php | head -1
md5sum $P/$D/prestashop_9.1.5.zip | cut -c1-32; echo "debe ser 9ca30e89018f50cfaa84b31d3d890592"
```
✅ Esperado:
- Los 4 ficheros: los dos zips, de unos 79 MB y 117 MB, y sus xml.
- El asistente en la versión **7.6.6**.
- Los dos md5 iguales.

### 2.2a Primer salto (a 8.2.8): preparar
```bash
P=/var/www/vhosts/belenoyrosello.com/domains/belenoyrosello.com/public_html
cd $P; rm -f cache/class_index.php; rm -rf var/cache/*
cd $P/modules/autoupgrade
PHP="/opt/alt/php74/usr/bin/php -d display_startup_errors=0 -d extension=zip.so -d memory_limit=1024M -d max_execution_time=0"
OPTS="--channel=local --zip=prestashop_8.2.8.zip --xml=prestashop_8.2.8.xml --disable-non-native-modules=0 --uninstall-incompatible-modules=0 --regenerate-email-templates=0 --disable-all-overrides=0 -n -v --no-chain"
LOG=/root/upgrade_prod_828.log; ACTION=UpdateInitialization; echo "Listo: $ACTION"
```

### 2.2b Primer salto: lanzar (en la MISMA ventana)
```bash
for i in $(seq 1 400); do
  echo "=== $i: $ACTION" | tee -a $LOG
  $PHP bin/console update:start admin1910hvr3w $OPTS --action=$ACTION > /tmp/paso.log 2>&1; RC=$?
  grep -vE '^NOTICE|^PHP Warning|^#[0-9]' /tmp/paso.log >> $LOG
  grep -E 'INFO|ERROR|CRITICAL' /tmp/paso.log | grep -v '^NOTICE' | tail -2
  if grep -q CRITICAL /tmp/paso.log; then echo ">>> ERROR en $ACTION"; break; fi
  NEXT=$(grep -oP -- '--action=\K\w+' /tmp/paso.log | tail -1)
  if ! grep -q 'Restart requested' /tmp/paso.log || [ -z "$NEXT" ]; then echo ">>> Fin (rc=$RC)"; break; fi
  ACTION=$NEXT
done
chown -R $(stat -c '%U:%G' $P/index.php) $P; rm -rf $P/var/cache/*
MYSQL_PWD="$(cat /etc/psa/.psa.shadow)" mysql -u admin beleno_shop -N -e "SELECT value FROM ps_configuration WHERE name='PS_VERSION_DB'"
```
✅ Esperado: `>>> Fin (rc=0)` y **8.2.8**.

❌ Si se para en `UpdateDatabase` (en staging pasó una vez por la caché de clases), limpia la caché con `cd $P; rm -f cache/class_index.php; rm -rf var/cache/*; cd $P/modules/autoupgrade; ACTION=UpdateDatabase` y vuelve a pegar el bucle 2.2b.

### 2.3a Segundo salto (a 9.1.5): preparar
El asistente usa el `php` del sistema (PHP 7.2) para generar la caché, y con él falla. Por eso se pone delante un `php` provisional que apunta a 8.1.
```bash
P=/var/www/vhosts/belenoyrosello.com/domains/belenoyrosello.com/public_html
mkdir -p /root/php81bin; printf '#!/bin/sh\nexec /opt/alt/php81/usr/bin/php -d display_startup_errors=0 -d memory_limit=1024M "$@"\n' > /root/php81bin/php; chmod +x /root/php81bin/php
export PATH=/root/php81bin:$PATH; php -v 2>/dev/null | head -1
cd $P; rm -f cache/class_index.php; rm -rf var/cache/*
cd $P/modules/autoupgrade
PHP="/opt/alt/php81/usr/bin/php -d display_startup_errors=0 -d extension=zip.so -d memory_limit=1024M -d max_execution_time=0"
OPTS="--channel=local --zip=prestashop_9.1.5.zip --xml=prestashop_9.1.5.xml --disable-non-native-modules=0 --uninstall-incompatible-modules=0 --regenerate-email-templates=0 --disable-all-overrides=0 -n -v --no-chain"
LOG=/root/upgrade_prod_915.log; ACTION=UpdateInitialization; echo "Listo: $ACTION"
```
✅ Esperado: **PHP 8.1.x**.

### 2.3b Segundo salto: lanzar (en la MISMA ventana)
Es el mismo bucle que el 2.2b. Cópialo de ahí.

✅ Esperado: `>>> Fin (rc=0)` y **9.1.5**.

❌ Si falla con "error al calentar la caché", confirma que `php -v` dice 8.1 (si no, `export PATH=/root/php81bin:$PATH`). Después: `cd $P; rm -f cache/class_index.php; rm -rf var/cache/*; cd $P/modules/autoupgrade; ACTION=UpdateDatabase`, y repite el bucle.

### 2.4 Quitar el `php` provisional
```bash
rm -rf /root/php81bin; hash -r; php -v 2>/dev/null | head -1
```
✅ Esperado: vuelve a salir PHP 7.2 (el del sistema), que es lo normal.

---

## Fase 3 · Aplicar las correcciones desde staging (~5 min)

### 3.1 Ficheros del núcleo y configuración
```bash
P=/var/www/vhosts/belenoyrosello.com/domains/belenoyrosello.com/public_html; S=/var/www/vhosts/belenoyrosello.com/staging.belenoyrosello.com
cp -p $S/config/defines_custom.inc.php $P/config/
cp -p $S/themes/core.js $P/themes/core.js
cp -p $S/src/Adapter/Cache/Clearer/Symfony/ExecKernelCacheClearer.php $P/src/Adapter/Cache/Clearer/Symfony/
cp -p $S/themes/theme1511/assets/js/custom.js $P/themes/theme1511/assets/js/custom.js
grep -c "function mp_count" $P/config/defines_custom.inc.php; grep -c 'e.resp&&(r().cart' $P/themes/core.js; grep -c PHP_BINDIR $P/src/Adapter/Cache/Clearer/Symfony/ExecKernelCacheClearer.php
grep -n "_PS_MODE_DEV_'," $P/config/defines.inc.php | head -1
```
✅ Esperado: tres `1` y `_PS_MODE_DEV_` en **false**.

### 3.2 Overrides (se copian tal cual desde staging)
```bash
P=/var/www/vhosts/belenoyrosello.com/domains/belenoyrosello.com/public_html; S=/var/www/vhosts/belenoyrosello.com/staging.belenoyrosello.com
mkdir -p /root/overrides_retirados_prod; cp -a $P/override /root/overrides_retirados_prod/override_antes_ps9
rsync -a --delete --exclude='*.bak*' $S/override/ $P/override/
(cd $P && find override -name "*.php" ! -name index.php | sort | tr '\n' ' '); echo
```
✅ Esperado: los mismos overrides que staging, **sin** `CartC.php` ni `AdminProductsController.php`, **con** `Tools.php`, `ImageType.php`, `Configuration.php`, `controller/AdminController.php` y `stock/StockAvailable.php`.

### 3.3 Módulos: primero una simulación
Solo se actualizan los `.php` que **ya existen** en producción y que son distintos. No se crea ni se borra nada, y no se tocan imágenes ni datos.
```bash
P=/var/www/vhosts/belenoyrosello.com/domains/belenoyrosello.com/public_html; S=/var/www/vhosts/belenoyrosello.com/staging.belenoyrosello.com
rsync -a --existing --checksum --dry-run --itemize-changes --exclude='*.bak*' --include='*/' --include='*.php' --exclude='*' $S/modules/ $P/modules/ | grep '^>f' | awk '{print $2}' | cut -d/ -f1 | sort | uniq -c
```
✅ Esperado: una lista de módulos con su número de ficheros (megaproduct, megadesigner, jx*, smartblog*, cookiesplus, redsyspur, netreviews, paypal, algunos ps_*…). Si aparece algo raro, **para y avisa**.

### 3.4 Módulos: aplicar y retirar `welcome`
```bash
P=/var/www/vhosts/belenoyrosello.com/domains/belenoyrosello.com/public_html; S=/var/www/vhosts/belenoyrosello.com/staging.belenoyrosello.com
tar czf /root/modulos_prod_antes_ps9fix.tgz --exclude='*/uploads/*' --exclude='*/img/*' -C $P modules 2>/dev/null
rsync -a --existing --checksum --exclude='*.bak*' --include='*/' --include='*.php' --exclude='*' $S/modules/ $P/modules/
mkdir -p /root/modulos_retirados_prod; [ -d $P/modules/welcome ] && mv $P/modules/welcome /root/modulos_retirados_prod/ && echo "welcome retirado"
rsync -a --existing --checksum --dry-run --itemize-changes --include='*/' --include='*.php' --exclude='*' $S/modules/ $P/modules/ | grep -c '^>f'
```
✅ Esperado: "welcome retirado" y un **0** al final (ya no queda ninguna diferencia).

### 3.5 Base de datos, widgets de smartblog y limpieza
```bash
P=/var/www/vhosts/belenoyrosello.com/domains/belenoyrosello.com/public_html; OWN=$(stat -c '%U:%G' $P/index.php)
MYSQL_PWD="$(cat /etc/psa/.psa.shadow)" mysql -u admin beleno_shop -e "INSERT INTO ps_configuration (name, value, date_add, date_upd) SELECT 'PS_SSL_ENABLED_EVERYWHERE', '1', NOW(), NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM ps_configuration WHERE name='PS_SSL_ENABLED_EVERYWHERE'); SELECT name, value FROM ps_configuration WHERE name LIKE 'PS_SSL%';"
find $P/modules $P/override $P/themes $P/config -type f -name "*.bak*" > /tmp/bak_prod.txt; wc -l < /tmp/bak_prod.txt
[ -s /tmp/bak_prod.txt ] && tar czf /root/copias_bak_prod_ps9.tgz -T /tmp/bak_prod.txt && xargs -d '\n' rm -f < /tmp/bak_prod.txt
chown -R $OWN $P; rm -rf $P/var/cache/*
```
✅ Esperado: `PS_SSL_ENABLED` y `PS_SSL_ENABLED_EVERYWHERE` en **1**, y el número de copias `.bak` retiradas.

### 3.6 Desactivar los 10 widgets de smartblog (el módulo principal no está instalado)
```bash
cd /var/www/vhosts/belenoyrosello.com/domains/belenoyrosello.com/public_html; U=$(stat -c '%U' index.php)
for m in smartblogaddthisbutton smartblogarchive smartblogcategories smartblogfeed smartbloghomelatestnews smartbloglatestcomments smartblogpopularposts smartblogrecentposts smartblogsearch smartblogtag; do
  printf "%-26s " $m; runuser -u $U -- /opt/plesk/php/8.3/bin/php -d display_startup_errors=0 -d error_reporting=0 -d memory_limit=512M bin/console prestashop:module disable $m 2>&1 | grep -oiE "correctamente|successful|error.{0,100}" | head -1
done
rm -rf var/cache/*; chown -R $(stat -c '%U:%G' index.php) .
```
✅ Esperado: "correctamente" en los 10.

---

## Fase 4 · PHP 8.3 en Plesk (~2 min)

*Sitios web y dominios* → **belenoyrosello.com** → **Configuración de PHP**:
- Versión: **8.3.36**, ejecutar como **Aplicación FastCGI**.
- `memory_limit` **512M**, `max_execution_time` **600**, `max_input_time` **600**, `post_max_size` **64M**, `upload_max_filesize` **64M**.
- En "Directivas adicionales": `max_input_vars = 10000`.
- **Aceptar**.

Después:
```bash
rm -rf /var/www/vhosts/belenoyrosello.com/domains/belenoyrosello.com/public_html/var/cache/*
sed "s#/var/www/vhosts/belenoyrosello.com/staging.belenoyrosello.com#/var/www/vhosts/belenoyrosello.com/domains/belenoyrosello.com/public_html#; s#staging.belenoyrosello.com#belenoyrosello.com#g" /root/probar.sh > /root/probar_prod.sh; chmod +x /root/probar_prod.sh
for u in / /62-marcos-a-medida /espejos-a-medida/218-espejo-marco-1-4.html; do echo "== $u"; /root/probar_prod.sh $u; done
```
✅ Esperado: las tres páginas con **Status: 200**, muchos KB y su título.

---

## Fase 5 · Pruebas en el navegador (con vuestra IP, la tienda sigue cerrada al público)

- [ ] **Portada** y **buscador** ("marco"), sin aviso de "información no segura".
- [ ] **Menú**: los enlaces van a belenoyrosello.com.
- [ ] **Categoría**: el lateral se ve como antes, sin mensajes rojos de smartblog.
- [ ] **Ficha del espejo**: con 80 × 80 tiene que salir **89,18 €** y "82 x 82".
- [ ] **Personalizador**: añadir, "seguir comprando", cambiar medidas y añadir otra vez.
- [ ] **Carrito**: añadir y borrar.
- [ ] **Pedido por transferencia** con un cliente vuestro: tiene que salir la confirmación y llegar el correo. Después, **cancelarlo** en el back office.
- [ ] **Back office**:
  - [ ] Panel, Pedidos y **factura PDF**.
  - [ ] Clientes.
  - [ ] Gestor de módulos.
  - [ ] Abrir y guardar una ficha de producto.
- [ ] **Redsys**: no se puede probar sin acceso, así que el primer pedido real con tarjeta lo vigilaremos con el vigilante y la consulta de pedidos.

Si algo falla: `grep -aE "PHP (Fatal|Parse)|Uncaught" /var/www/vhosts/belenoyrosello.com/logs/error_log | tail -1 | sed 's/\\n/\n/g' | head -6 | cut -c1-400`

---

## Fase 6 · Abrir la tienda y reactivar el vigilante
```bash
P=/var/www/vhosts/belenoyrosello.com/domains/belenoyrosello.com/public_html
MYSQL_PWD="$(cat /etc/psa/.psa.shadow)" mysql -u admin beleno_shop -e "UPDATE ps_configuration SET value='1' WHERE name='PS_SHOP_ENABLE'; SELECT value FROM ps_configuration WHERE name='PS_SHOP_ENABLE';"
rm -rf $P/var/cache/*; mv /root/vigila_beleno.cron.pausado /etc/cron.d/vigila_beleno; ls /etc/cron.d/vigila_beleno
for u in / /carrito /62-marcos-a-medida; do curl -sL -o /dev/null -w "%{http_code} $u\n" -m 30 https://belenoyrosello.com$u; done
```
✅ Esperado: `PS_SHOP_ENABLE` = 1, el cron restaurado y tres **200**.

---

## Vuelta atrás (si algo sale mal y no se puede arreglar en el momento)
```bash
TS=$(cat /root/marca_prod_pre9.txt); B=/var/www/vhosts/belenoyrosello.com/domains/belenoyrosello.com
mv $B/public_html $B/public_html_fallido_ps9 && cd $B && tar xzf /root/prod_pre9_$TS.tgz && ls -ld $B/public_html
zcat /root/beleno_shop_pre9_$TS.sql.gz | MYSQL_PWD="$(cat /etc/psa/.psa.shadow)" mysql -u admin beleno_shop && echo "BD restaurada"
rm -rf $B/public_html/var/cache/*
```
Después, en Plesk, vuelve a poner la versión de PHP que tenía la web (**alt-php74**). La tienda queda exactamente como antes de empezar, todavía en mantenimiento. Para abrirla, usa la Fase 6.

---

## Resumen de las correcciones de compatibilidad (ya hechas en staging)

| Tipo | Qué se hizo |
|---|---|
| Overrides | Firmas de `Cart`, `PaymentModule` y `CartController` (con `: void`) adaptadas a PS9; se recupera `ONLY_PRODUCTS_WITHOUT_SHIPPING`; se quitan `CartC.php` y `AdminProductsController.php` |
| Métodos eliminados en PS9 | Repuestos con overrides: `Tools::displayPrice/link_rewrite/stripslashes`, `Order::getOrderByCartId`, `ImageType::getFormatedName`, `StockAvailable::dependsOnStock`, `Configuration::getInt`, `AdminController::l`, `Product::getProductAttributePrice`, `Module::displayFlags` (megaproduct) |
| Constantes eliminadas | Repuestas en `config/defines_custom.inc.php` (`_CAN_LOAD_FILES_`, `_PS_PRICE_COMPUTE_PRECISION_`… 13 en total) |
| Clases movidas | `ProductListingPresenter`, `ProductPresenter`, `ObjectPresenter`, `SortOrderFactory` → sus nuevas rutas en 17 módulos |
| Módulos | `Tools::encrypt` → `md5(_COOKIE_KEY_ …)`; `addJquery()` eliminado; definiciones `$definition` en 14 clases antiguas (megaproduct, megadesigner, netreviews); `Tools::displayDate(…, null, 1)` → `(…, true)` |
| PHP 8 | `each()` → `foreach`/`reset()`; `count/sizeof` → `mp_count()` (comportamiento de PHP 7.4) en megaproduct y megadesigner; `implode()` con los argumentos en el orden correcto; propiedad `secure_key` declarada |
| Núcleo | `themes/core.js` (refresco del carrito al borrar); `ExecKernelCacheClearer` usa el PHP de la web en vez del `php` 7.2 del sistema |
| Tema | `custom.js`: el botón "Subiendo imagen…" ya no depende del dominio |
| Base de datos | `PS_SSL_ENABLED_EVERYWHERE` = 1 (lo usan módulos antiguos); países sin DNI obligatorio (ya hecho en producción el 26/10); 10 widgets de smartblog desactivados; módulo `welcome` retirado |

---

## Resultado de la intervención en producción (27/10/2026)

- **Mantenimiento:** de 7:54 a ~9:05. Copia previa: `/root/prod_pre9_2026-10-27_0754.tgz` y `/root/beleno_shop_pre9_2026-10-27_0754.sql.gz` (391 tablas).
- **Versiones:** 1.7.8.11 → 8.2.8 → **9.1.5**; la web pasa a **PHP 8.3.36 (FastCGI de Plesk)**.

**Incidencias del día y cómo se resolvieron:**
- **Salto a 8.2.8:** se paró en `UpdateDatabase` por el índice de clases antiguo. Se borró `cache/class_index.php` y se reanudó desde `UpdateDatabase`, como en staging.
- **`cp` del servidor:** tiene el alias `-i` y pregunta antes de sobrescribir. Hay que usar `\cp`.
- **netreviews:** producción tenía la 10.4.8 y staging la 10.4.7, así que no se copió desde staging. Se le aplicaron las correcciones (json, ajaxDie, implode y `$definition`) directamente en producción.
- **gamification:** staging tenía la 2.5.1 y producción la 2.3.2. Es un módulo sin uso y se retiró a `/root/modulos_retirados_prod/`, igual que `welcome`.
- **Buscador `jxsearch` y `ps_linklist`:** `Symfony\Component\Translation\TranslatorInterface` → `Symfony\Contracts\Translation\TranslatorInterface`. Corregido en producción y staging.
- **"Pasar por caja" gris con productos del personalizador:** los productos base de megaproduct están inactivos y el JS del tema desactiva el botón. `custom.js` ahora lo reactiva también al cargar la página. Corregido en producción y staging.

- **Buscador instantáneo (`jxsearch/ajaxsearch.php`):** en PS9 los productos de los listados ya traen `manufacturer_name` y no admiten sobrescribirlo, así que ahora solo se añade si no existe. Corregido en producción y staging.
- **Vigilante:** actualizado para PS9; ahora lee también `var/logs/prod-AAAA-MM-DD.log` (errores críticos del back office).

**Pendiente:**
- Comprobar el primer pago real con Redsys (tarjeta o Bizum).
- `robots.txt`: no rastrear las URLs de filtros (`?q=`, `?order=`).
- Dentro de una semana, si todo va bien: borrar las copias de `/root`, las descargas y, si ya no se necesita, staging.
