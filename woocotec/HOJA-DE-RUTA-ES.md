# Hoja de ruta — Integración Cecotec España (emetronix.es)

Ámbito: **solo España**. La zona del plugin es siempre «Español».

| Dato | Fuente |
|---|---|
| Ficha de producto | Catálogo Plytix (XML) |
| Precio (PVPR) | Feed DROPES (`pvpr`) |
| Stock | Feed DROPES `https://cecobi-external.cecotec.cloud/ws/getstockfeedb2cNew.php?etiqueta=DROPES` |

Cecopublisher deja de usarse para España: ni precios ni catálogo.

---

## Fase 0 — Decisiones con el cliente (antes de tocar nada)

- [ ] **Precio de venta:** usar el `pvpr` de Cecotec tal cual, o aplicar un margen o descuento propio.
- [ ] **Precio con IVA:** confirmar que el `pvpr` lleva IVA y que WooCommerce está configurado con «precios introducidos con impuestos».
- [ ] **Productos agotados:** mostrarlos como «Agotado» u ocultarlos del catálogo (ajuste de WooCommerce).
- [ ] **Imágenes:**
  - solo fotos máster (fondo blanco), o máster + «Gallery 1:1» (producto en uso);
  - sustituir las fotos actuales de los productos existentes, o mantenerlas.
- [ ] **Categorías:** mantener el árbol actual de la web (asignando las de Plytix a las actuales) o pasar a las de Plytix (GAE, PAE Cocina, Cuidado del aire…).
- [ ] **Marcas:** importar también EVVO y CECOLCHON, o solo CECOTEC.

## Fase 1 — Preparación

- [ ] Copia de seguridad completa de producción (All-in-One WP Migration o la del hosting).
- [ ] Crear un entorno de pruebas (staging) con copia de producción.
- [ ] Exportar la plantilla actual de WP All Import «Productos Cecotec», como copia de seguridad.
- [ ] Anotar la situación actual:
  - nº de productos Cecotec;
  - formato de los SKU (`0121` / `00121` / `121`);
  - categorías usadas en el menú (JetMenu) y en las plantillas de Elementor.
- [ ] Hosting:
  - PHP con XMLReader;
  - ≥ 1 GB libre en disco;
  - `memory_limit` ≥ 256 MB;
  - `max_execution_time` ≥ 300 s.

## Fase 2 — Instalación en staging

- [ ] Subir el zip de Woocotec 2.0.0 encima del plugin actual.
- [ ] Panel del plugin: zona **Español** y guardar.
- [ ] Ajustes del catálogo: modo de imágenes según la Fase 0. Dejar las URLs vacías para usar las de Cecotec.
- [ ] Pulsar **Importación manual** y comprobar en el registro:
  - [ ] el catálogo se descarga (unos 280 MB);
  - [ ] el CSV se genera (≈ 4.150 productos, ≈ 1.600 con stock; varía cada día).

## Fase 3 — Validación del CSV

Fichero: `wp-content/uploads/wpallimport/files/products.csv`

- [ ] Revisar 10 productos al azar contra la web de Cecotec: nombre, precio, descripción, fotos y categorías.
- [ ] Comprobar que los SKU coinciden con los productos que ya existen en la web, para no crear duplicados.
- [ ] Comprobar que hay precios en todas las filas y que el stock coincide con el feed DROPES.

## Fase 4 — Configurar WP All Import (staging)

En la importación «Productos Cecotec»:

> Los nombres entre llaves son orientativos: el nombre exacto de cada columna se arrastra desde el panel derecho de WP All Import, sobre todo los que llevan tilde («Galería»).

- [ ] **Coincidencia de productos:** por SKU (`{sku[1]}`).
- [ ] **Precio:** precio normal = `{pvpr[1]}`, con el margen de la Fase 0 si se aplica.
- [ ] **Inventario:**
  - Manage stock = Yes;
  - Stock qty = `{stock[1]}`;
  - Backorders = No.
- [ ] **Imágenes:**
  - imagen destacada `{imagendestacada[1]}` y galería `{galería[1]}` con separador `|`;
  - activar «Search through the Media Library for existing images»;
  - en productos existentes, «Update images» solo si se decidió sustituir las fotos.
- [ ] **Categorías:**
  - activar «Enable mapping for categories» y asignar las de Plytix a las actuales;
  - o no actualizar categorías en los productos existentes.
- [ ] **EAN:** campo personalizado `_global_unique_id` = `{ean[1]}` (GTIN de WooCommerce).
- [ ] **Marca:** taxonomía `product_brand` = `{marca[1]}`.
- [ ] **Slug:** no actualizar el slug de los productos existentes, por SEO.
- [ ] **Productos que no están en el fichero:** no borrarlos.
- [ ] Ejecutar la importación y revisar:
  - [ ] que no aparecen productos duplicados;
  - [ ] que los productos tienen precio, stock, fotos y descripción;
  - [ ] que el menú y las páginas de categoría siguen mostrando productos.

## Fase 5 — Stock y venta solo con stock (staging)

- [ ] Pulsar **Sincronizar stock ahora** y revisar el resumen (encontrados, actualizados, agotados).
- [ ] Probar un producto con stock 0: aparece «Agotado» y no se puede añadir al carrito.
- [ ] WooCommerce › Ajustes › Productos › Inventario: aplicar «Ocultar agotados» según la Fase 0.
- [ ] Comprobar que las tareas programadas están creadas (por ejemplo con el plugin WP Crontrol):
  - `ceco_sync_stock_event` (cada hora);
  - `ceco_download_catalog_event` (diaria);
  - `ceco_import_products_event` (diaria).

## Fase 6 — Plugins relacionados

- [ ] **LiteSpeed Cache / WP-Cron:**
  - poner `define('DISABLE_WP_CRON', true);` en `wp-config.php`;
  - crear una tarea programada en el servidor que llame a `wp-cron.php` cada 5–15 min.

  Si no se hace, con la caché de página la actualización del stock puede no ejecutarse.
- [ ] **Imagify:** pausar la optimización automática durante la primera importación.
- [ ] **Plugins de feeds** (Product Feed Manager / Conversios / WP Product Feed Manager):
  - asignar el GTIN al EAN;
  - comprobar que los agotados salen como «out of stock» o se excluyen.
- [ ] **Plugin antiguo «CSV Cecotec to Woocommerce»:**
  - borrarlo;
  - quitar del servidor cualquier tarea programada que llame a `util/cronstock.php`.
- [ ] **Administrador de archivos WP:** actualizar a 8.0.7 o desactivarlo (seguridad).

## Fase 7 — Paso a producción

- [ ] Copia de seguridad de producción justo antes del cambio.
- [ ] Elegir una franja de poco tráfico.
- [ ] Repetir en producción las Fases 2, 4, 5 y 6, con la misma plantilla de WP All Import que en staging.
- [ ] Lanzar la importación manual y luego «Sincronizar stock ahora».
- [ ] Comprobar en la tienda: 5 productos con stock, 5 agotados, el menú, una compra de prueba y la caché purgada.
- [ ] Reactivar Imagify.

## Fase 8 — Seguimiento (primera semana)

- [ ] **Cada día:** revisar en el panel del plugin «Última sincronización», «Último CSV» y «Última descarga». Ninguno debe mostrar ❌.
- [ ] **Cada día:** ejecutar (o comprobar que se ejecutó) la importación de WP All Import tras generar el CSV, o programarla con su cron.
- [ ] Revisar que no entran pedidos de productos sin stock.
- [ ] Revisar la cuota de Imagify y el espacio en disco (imágenes nuevas).
- [ ] Si Cecotec vuelve a cambiar una URL: cambiarla en el panel del plugin, sin tocar el código.
