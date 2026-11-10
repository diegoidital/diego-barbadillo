# Woocotec 2.0 — Integración con los datos nuevos de Cecotec

## Fuentes de datos

| Dato | España | Resto de zonas |
|---|---|---|
| Ficha (nombre, descripciones, familias, imágenes, medidas, EAN, estado, marca) | Catálogo Plytix XML `https://pim.plytix.com/channels/695f7b24f8956fc226436cea/feed` | Igual, en el idioma de la zona (PT, IT, FR, DE, NL; zonas 1-3 en EN) |
| Precio | Feed DROPES, campo `pvpr` | Cecopublisher, tarifa DROP INTER (credenciales en el plugin) |
| Stock | `https://cecobi-external.cecotec.cloud/ws/getstockfeedb2cNew.php?etiqueta=DROPES` | `https://cecobi-external.cecotec.cloud/ws/getstockfeedb2c.php?etiqueta=DROP{PT,IT,DE,FR,Z1,Z2,Z3}` |

Cruce: `tag_SKU` (catálogo) = `public_id` (feeds) = SKU de Cecopublisher. Las referencias numéricas
se comparan rellenando con ceros a 5 dígitos; si el producto ya existe en WooCommerce con otro
formato (p. ej. `0121`) se mantiene su SKU para no duplicarlo.

Criterios de Cecotec aplicados:

- Nombre: `Nombre Producto / Modelo`.
- Descripción corta: `Descripción corta del producto`.
- Descripción larga: `Descripción larga del producto` + `Características` (en HTML).
- Bullets, caja/caja máster/palet: no se usan (son para marketplaces).
- Categorías: `Familia padre` (gama) › `Familia` › `Subfamilia`.
- Imágenes: fotos «Máster Main Image» (fondo blanco) primero. Se pueden añadir las «Gallery 1:1»
  (producto en uso) desde los ajustes del plugin. Las «enriquecidas» (con texto) no se usan. Se
  descartan los logotipos de relleno de Plytix.
- Descatalogado / descartado / discontinuo: se venden hasta agotar el stock. Si no tienen stock no
  entran en el CSV.

## Automatismos (WP-Cron)

| Tarea | Frecuencia | Qué hace |
|---|---|---|
| `ceco_download_catalog_event` | diaria, 03:00 | Descarga el XML de Plytix (~280 MB) a `uploads/woocotec/catalogo.xml` |
| `ceco_import_products_event` | diaria | Genera `uploads/wpallimport/files/products.csv` |
| `ceco_sync_stock_event` | cada hora | Actualiza el stock de WooCommerce. Activa la gestión de inventario y quita las reservas |

## Columnas del CSV `products.csv`

Las 19 primeras son las de siempre; las 7 últimas son nuevas.

`SKU, Nombre del producto, PVPR, Categoría principal, Categorias secundaria, Categoría terciaria,
Descripción corta, Descripción larga, Peso, Largo, Ancho, Alto, Voltaje, Voltaje y capacidad de la batería,
Imagen destacada, img1, img2, img3, img4,` **`Stock, Estado stock, EAN, Estado Cecotec, Marca, Precio coste, Galería`**

## Cambios a hacer en WP All Import («Productos Cecotec»)

1. **Inventario:** «Manage stock» = Yes, «Stock Qty» = `{stock[1]}`, «Allow backorders» = No.
2. **Imágenes:**
   - Usar `{imagendestacada[1]}` + `{galera[1]}` con separador `|`, o seguir con `img1…img4`.
   - Activar «Search through the Media Library for existing images before importing new images».
   - En los productos existentes, marcar «Update images» solo si se quieren sustituir las fotos
     antiguas (`media.cecotec.cloud`) por las de Plytix. Si no, se descargan miles de imágenes
     nuevas en cada importación.
3. **Categorías:** los nombres de Plytix (p. ej. «GAE», «PAE Cocina», «Cuidado del aire») no
   coinciden con los de Cecopublisher.
   - Usar «Enable mapping for categories» para traducirlos a las categorías actuales de la web,
   - o no actualizar las categorías de los productos existentes.

   Si no se hace, se crean categorías nuevas y el menú (JetMenu) y las páginas de categoría de
   Elementor dejan de mostrar esos productos.
4. **EAN:** WooCommerce 9.2+ tiene el campo nativo GTIN/EAN (`_global_unique_id`). Asignarlo a
   `{ean[1]}` como campo personalizado. Lo usan los plugins de feeds de Google.
5. **Marca:** WooCommerce 9.6+ tiene la taxonomía `product_brand`. Asignarla a `{marca[1]}` (CECOTEC,
   CECOLCHON, EVVO…).
6. **Título y slug:** no actualizar el slug de los productos existentes, para no perder URLs (Yoast).
7. **Productos que faltan en el fichero:** no activar la opción de borrar productos. Los agotados
   los gestiona el stock.
