const fs = require('fs');
const {
  Document, Packer, Paragraph, TextRun, Table, TableRow, TableCell, WidthType, ShadingType,
  AlignmentType, LevelFormat, ImageRun, Footer, Header, PageNumber, BorderStyle, HeadingLevel,
} = require('docx');

const OUT = process.argv[2];
const IMG = process.argv[3];
const LOGO = process.argv[4];
const logo = (w) => new ImageRun({ type: 'png', data: fs.readFileSync(LOGO), transformation: { width: w, height: Math.round(w * 331 / 768) } });

const FONT = 'Arial';
const ACCENT = '1F4E79';

// ---------- helpers ----------
const p = (text, opts = {}) => new Paragraph({
  spacing: { after: 120 },
  ...opts,
  children: Array.isArray(text) ? text : [new TextRun(text)],
});
const b = (t) => new TextRun({ text: t, bold: true });
const t = (s) => new TextRun(s);
const h1 = (s) => new Paragraph({ heading: HeadingLevel.HEADING_1, children: [new TextRun(s)] });
const h2 = (s) => new Paragraph({ heading: HeadingLevel.HEADING_2, children: [new TextRun(s)] });
const bullet = (runs) => new Paragraph({ numbering: { reference: 'bullets', level: 0 }, spacing: { after: 60 }, children: Array.isArray(runs) ? runs : [t(runs)] });
let listId = 0;
const steps = (items) => {
  const ref = 'steps' + (listId++);
  numberingConfigs.push({ reference: ref, levels: [{ level: 0, format: LevelFormat.DECIMAL, text: '%1.', alignment: AlignmentType.LEFT, style: { paragraph: { indent: { left: 360, hanging: 360 } } } }] });
  return items.map(it => new Paragraph({ numbering: { reference: ref, level: 0 }, spacing: { after: 60 }, children: Array.isArray(it) ? it : [t(it)] }));
};
const note = (runs) => new Paragraph({
  spacing: { before: 120, after: 160 },
  shading: { type: ShadingType.CLEAR, fill: 'EAF1F8', color: 'auto' },
  border: { left: { style: BorderStyle.SINGLE, size: 18, color: ACCENT, space: 8 } },
  indent: { left: 120 },
  children: Array.isArray(runs) ? runs : [t(runs)],
});

const TABLE_W = 9026; // A4 con márgenes de 1 pulgada
const cellBorder = { style: BorderStyle.SINGLE, size: 4, color: 'BFBFBF' };
const borders = { top: cellBorder, bottom: cellBorder, left: cellBorder, right: cellBorder };
const table = (widths, header, rows) => new Table({
  width: { size: TABLE_W, type: WidthType.DXA },
  columnWidths: widths,
  rows: [
    new TableRow({
      tableHeader: true,
      children: header.map((hd, i) => new TableCell({
        width: { size: widths[i], type: WidthType.DXA }, borders,
        shading: { type: ShadingType.CLEAR, fill: ACCENT, color: 'auto' },
        margins: { top: 80, bottom: 80, left: 100, right: 100 },
        children: [new Paragraph({ children: [new TextRun({ text: hd, bold: true, color: 'FFFFFF' })] })],
      })),
    }),
    ...rows.map((r, ri) => new TableRow({
      cantSplit: true,
      children: r.map((c, i) => new TableCell({
        width: { size: widths[i], type: WidthType.DXA }, borders,
        shading: ri % 2 ? { type: ShadingType.CLEAR, fill: 'F5F7FA', color: 'auto' } : undefined,
        margins: { top: 80, bottom: 80, left: 100, right: 100 },
        children: [new Paragraph({ children: Array.isArray(c) ? c : [t(c)] })],
      })),
    })),
  ],
});
const gap = () => new Paragraph({ spacing: { after: 60 }, children: [] });

const numberingConfigs = [
  { reference: 'bullets', levels: [{ level: 0, format: LevelFormat.BULLET, text: '•', alignment: AlignmentType.LEFT, style: { paragraph: { indent: { left: 360, hanging: 260 } } } }] },
];

// ---------- contenido ----------
const body = [];
const DIAG = process.argv[5];
const code = (s) => new TextRun({ text: s, font: 'Consolas', size: 19 });

body.push(new Paragraph({ spacing: { after: 360 }, children: [logo(190)] }));
body.push(new Paragraph({ heading: HeadingLevel.TITLE, children: [new TextRun('Informe técnico: integración Cecotec en emetronix.es')] }));
body.push(p([new TextRun({ text: 'Plugin «Importador de productos Cecotec» (Woocotec) 1.0.2 → 2.6.2 · Trabajos del 9 al 23 de noviembre de 2026 · Idital', color: '595959' })], { spacing: { after: 240 } }));

body.push(h1('1. Resumen'));
body.push(p('Se ha rehecho la integración de emetronix.es con Cecotec para trabajar con las fuentes de datos nuevas que Cecotec ha puesto a disposición de sus distribuidores, añadir el stock en tiempo casi real y automatizar todo el proceso.'));
body.push(bullet([b('Catálogo: '), t('fichas, descripciones, categorías, medidas y fotos desde el feed XML de Plytix (PIM de Cecotec).')]));
body.push(bullet([b('Precio y stock (España): '), t('feed de dropshipping DROPES de Cecotec; ya no se depende de Cecopublisher para España.')]));
body.push(bullet([b('Stock: '), t('sincronización horaria directa en WooCommerce; solo se puede comprar lo que tiene stock.')]));
body.push(bullet([b('Automatización: '), t('catálogo cada noche, CSV diario a hora fija y subida automática a la tienda mediante WP All Import (requiere su «Cron Processing Key»).')]));
body.push(bullet([b('Resultado: '), t('3.946–4.083 productos sincronizados según el día, 1.627 productos nuevos creados y 2.402 actualizados en la importación completa; 0 discrepancias de datos en la validación contra el feed.')]));

body.push(h1('2. Situación inicial'));
body.push(table([2600, 6426], ['Elemento', 'Estado encontrado'], [
  ['Plugin Woocotec 1.0.2', 'Generaba products.csv para WP All Import con tarifas y catálogos de la API de Cecopublisher (csv-online.cecotec.cloud). No incluía stock. Relleno de SKU defectuoso (añadía un solo cero), variable $messages sin definir y filas de España con una columna de más.'],
  ['Plugin «CSV Cecotec to Woocommerce»', 'Desactivado. Su script de stock (util/cronstock.php) apuntaba a cecotec.gesio.be, ya inexistente, y dejaba los productos sin stock «en reserva» (comprables).'],
  ['WP All Import Pro 4.9.3', 'Importación «Productos Cecotec» (ID 4) leyendo uploads/wpallimport/files/products.csv, con «Update all data».'],
  ['Catálogo de la tienda', '≈4.230 productos publicados y 4.286 borradores antiguos (2023–2024) con SKU en formatos distintos (99, 123, 00127…), sin foto ni marca.'],
  ['Cecotec', 'Anuncia nuevas fuentes (Plytix + feeds DROP*) y el cambio de dominio de los feeds a cecobi-external.cecotec.cloud a partir del 24/09.'],
]));

body.push(h1('3. Fuentes de datos'));
body.push(new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 120 }, children: [new ImageRun({ type: 'png', data: fs.readFileSync(DIAG), transformation: { width: 600, height: 243 } })] }));
body.push(table([2200, 3300, 3526], ['Fuente', 'Contenido', 'Notas técnicas'], [
  ['Plytix XML (pim.plytix.com/channels/695f7b24…/feed)', '7.152 productos; nombre, descripciones, familias, fotos, medidas, EAN, estado y marca en 13 idiomas', '≈270 MB. Etiquetas con «:» (Foto_Image_Gallery_16:9…), inválidas en XML con espacios de nombres. Fotos máster de 4.600–5.100 px y 7–10 MB.'],
  ['Feed DROPES (getstockfeedb2cNew.php?etiqueta=DROPES)', 'public_id, stock, EAN, imágenes, título, coste (precio), PVPR, portes, peso', 'CSV «;», decimales con coma y miles con punto, filas en UTF-8 y Windows-1252 mezcladas. Cabecera HTTP inválida «Content-Encoding: UTF-8».'],
  ['Feeds de zona (getstockfeedb2c.php?etiqueta=DROPPT/IT/DE/FR/Z1/Z2/Z3)', 'public_id, título, stock, manual, EAN', 'Sin precios ni fotos. Misma cabecera inválida.'],
  ['Cecopublisher (API)', 'Tarifa DROP INTER: PVP por país/zona', 'Solo se usa fuera de España; requiere credenciales.'],
]));
body.push(gap());
body.push(p([t('Cruce entre fuentes: '), code('tag_SKU'), t(' (Plytix) = '), code('public_id'), t(' (feeds) = SKU de Cecopublisher. Validado: los 3.542 productos del feed DROPES de la primera muestra estaban en Plytix y el EAN coincidía en 3.541.')]));

body.push(h1('4. Arquitectura del plugin (versión 2.6.2)'));
body.push(table([2400, 6626], ['Archivo', 'Responsabilidad'], [
  ['woocotec.php', 'Carga de módulos, menú «Actualizador tienda» (posición 1), registro de tareas WP-Cron y desactivación.'],
  ['inc/ceco-stock.php', 'CecoStockFeed: URL por zona, descarga con cURL sin decodificación de contenido, lectura del CSV por nombre de columna, normalización de SKU, conversión de decimales y codificación, caché de 10 min, sincronización horaria de stock y «agotar los de Cecotec fuera del feed».'],
  ['inc/ceco-catalog.php', 'CecoCatalogFeed: descarga del XML a disco (atómica), lectura en streaming con XMLReader filtrando por SKU, mapeo de campos por idioma con respaldo en inglés, selección de fotos, exclusión del XML de las copias de All-in-One WP Migration.'],
  ['inc/ceco-api.php', 'Generación de products.csv: precios (DROPES o Cecopublisher), cruce con catálogo, reglas de negocio, identificador de WP All Import, columnas nuevas y escritura atómica.'],
  ['inc/ceco-images.php', 'CecoImageCache: cola de fotos, descarga y reducción a 1.200 px en uploads/woocotec-img, protección ante caídas y fotos gigantes, miniaturas esenciales durante la importación.'],
  ['inc/ceco-photos.php', 'Herramienta «Productos publicados sin foto».'],
  ['inc/ceco-cleanup.php', 'Herramientas «Borradores antiguos de Cecotec» y «Nombres con el EAN delante».'],
  ['inc/ceco-auto.php', 'CecoAutomation: hora del CSV diario y subida automática a la tienda con las URL de cron de WP All Import (trigger + processing).'],
  ['view.php, js/ceco-api.js, css/style.css', 'Panel: credenciales e idioma, bloque de botones (Actualizar stock, Generar CSV, Actualizar tienda) y seis secciones plegables.'],
]));

body.push(h1('5. Reglas de negocio implementadas'));
body.push(bullet([b('Normalización de SKU: '), t('referencias numéricas a 5 dígitos con ceros a la izquierda para comparar («121» = «0121» = «00121»).')]));
body.push(bullet([b('Identificador de WP All Import: '), t('el CSV reutiliza el unique_key que WP All Import guardó para cada producto (tabla pmxi_posts), para actualizar en lugar de duplicar. No se reutiliza el SKU de WooCommerce para evitar colisiones con borradores antiguos.')]));
body.push(bullet([b('Precio España: '), t('PVPR del feed DROPES. Coste (precio) en la columna «Precio coste».')]));
body.push(bullet([b('Descatalogados: '), t('DESCATALOGADO, DESCARTADO o DISCONTINUO sin stock quedan fuera del CSV; con stock se venden.')]));
body.push(bullet([b('Códigos duplicados: '), t('Cecotec mantiene productos con código antiguo y nuevo (p. ej. 00099 y A01_EU01_113689). Por nombre: se publican los que tienen stock; si ninguno, solo uno (el ya importado y, preferentemente, el código nuevo). 75 filas omitidas en la primera ejecución.')]));
body.push(bullet([b('Textos: '), t('nombre de «Nombre Producto / Modelo» sin el EAN delante (259 casos); descripción corta con respaldo en la descripción larga o en «Características» cuando viene vacía o es solo la categoría (de 1.573 a 294 casos sin texto útil); descripción larga = descripción larga + características en HTML.')]));
body.push(bullet([b('Fotos: '), t('por defecto las del feed DROPES (2.048 px, ≈0,5 MB); Plytix como respaldo (fotos máster, opcionalmente Gallery 1:1), descartando los logotipos de relleno; URL con espacios y comas codificadas (%20, %2C).')]));
body.push(bullet([b('Stock: '), t('gestión de inventario activada y reservas desactivadas en cada sincronización. Los productos publicados de Cecotec (importación o marca Cecotec/EVVO/Cecolchon) que no están en el feed pasan a stock 0; protección si el feed llega con un 20 % menos de productos que la vez anterior.')]));

body.push(h1('6. Procesos automáticos (WP-Cron)'));
body.push(table([3000, 2200, 3826], ['Tarea', 'Frecuencia', 'Qué hace'], [
  ['ceco_download_catalog_event', 'Diaria, 03:00', 'Descarga el XML de Plytix si tiene más de 20 h.'],
  ['ceco_import_products_event', 'Diaria, hora configurable (por defecto 05:00)', 'Genera products.csv y, si está activada, inicia la subida automática.'],
  ['ceco_process_images_event', 'Cada minuto mientras haya cola', 'Prepara las fotos pendientes en lotes de 20 s y regenera el CSV al terminar.'],
  ['ceco_auto_step', 'Cada 1–5 min durante una subida', 'Espera a las fotos (máx. 4 h), llama a trigger y processing de WP All Import hasta «complete» (máx. 10 h) y sincroniza el stock.'],
  ['ceco_sync_stock_event', 'Cada hora', 'Sincroniza el stock de los productos publicados.'],
]));
body.push(note([b('Dependencias: '), t('WP-Cron solo se ejecuta con visitas que no salen de caché (LiteSpeed). Se recomienda un aviso externo cada 10 min a wp-cron.php. La subida automática requiere la «Cron Processing Key» de WP All Import Pro, hoy no configurada.')]));

body.push(h1('7. Configuración de WP All Import'));
body.push(table([3200, 5826], ['Opción', 'Valor'], [
  ['Identificador único', '{sku[1]} — «Create new products for each record»'],
  ['Datos que se actualizan', 'Description, Short Description, Product Status, Precio Normal, Manage Stock, Stock Quantity, Peso, Length, Width, Height, SKU, GTIN, Allow Backorders, Taxonomies (solo product_brand)'],
  ['Datos que no se actualizan', 'Product Name, Images, Attributes, Precio de venta y fechas, Slug y Admin, visibilidad y destacado, tipo de producto, impuestos y envío, productos vinculados, Custom Fields'],
  ['Inventario', 'Manejo de stock = Sí; cantidad {stock[1]}; estado automático; sin reservas'],
  ['Imágenes (productos nuevos)', '{imagendestacada[1]}|{galera[1]} con separador «|»; búsqueda en biblioteca por nombre de archivo'],
  ['Marca / EAN', 'product_brand = {marca[1]}; GTIN = {ean[1]}'],
  ['Productos que faltan', 'Solo los de esta importación → Borrador y Agotado'],
  ['Rendimiento', '5 registros por iteración; omitir registros sin cambios'],
]));

body.push(h1('8. Intervenciones sobre los datos de la tienda'));
body.push(table([4000, 5026], ['Acción', 'Resultado'], [
  ['Pruebas de importación con 20 registros', 'Detectados y corregidos: identificador de SKU, duplicados de código, coste de las fotos.'],
  ['Limpieza de borradores antiguos sustituidos', '2.162 borradores enviados a la papelera y eliminados.'],
  ['Importación completa', '4.029 registros: 1.627 creados, 2.402 actualizados, 918 retirados a borrador.'],
  ['Re-importación con la plantilla final', '4.028 actualizados, 0 creados.'],
  ['Eliminación de borradores retirados', 'Tienda con 4.231 publicados; en la siguiente importación 34 altas y 116 bajas (rotación normal del feed).'],
  ['Preparación de fotos', '3.647 fotos reducidas a 1.200 px para productos nuevos.'],
  ['Productos sin foto', '0 productos publicados sin foto con fotos disponibles; ≈311 (sobre todo EVVO) sin ninguna foto en Cecotec.'],
]));

body.push(h1('9. Incidencias resueltas'));
body.push(table([2800, 3300, 2926], ['Incidencia', 'Causa', 'Solución'], [
  ['«cURL error 61: Unrecognized content encoding type»', 'Cecotec envía «Content-Encoding: UTF-8»', 'Descarga con CURLOPT_HTTP_CONTENT_DECODING = false (2.0.1). Comunicado a Cecotec.'],
  ['Primera lectura del XML: solo 14 productos', 'next("product") desde dentro de un nodo descartado', 'Avance hasta el cierre de </product> antes de saltar (2.0.0).'],
  ['WP All Import no separaba las fotos', 'Separador «,» con plantilla «|» y 56 URL con comas', 'Separador «|» y comas codificadas como %2C (2.0.3).'],
  ['El CSV enviaba SKU «99» (borrador antiguo)', 'Se reutilizaba el SKU de WooCommerce', 'Uso del unique_key de WP All Import (2.0.4).'],
  ['Importación de ~35 s por foto', 'Originales de Plytix de 25 MP', 'Fotos del feed de 2.048 px, caché de 1.200 px y miniaturas esenciales (2.0.6–2.2.0).'],
  ['HTTP 500 / HTTP 0 al preparar fotos', 'Memoria/tiempo de PHP con fotos gigantes; el lote se repetía', 'Detección de caída, omisión de fotos > 16 MP, límite de tiempo por foto (2.2.1–2.2.2).'],
  ['Borradores que volvían de la papelera', 'WP All Import marca como borrador sus productos aunque estén en la papelera', 'Eliminación definitiva; documentado en el manual.'],
  ['Fechas de borradores modificadas cada hora', 'La sincronización tocaba borradores', 'Solo productos publicados (2.1.1).'],
]));

body.push(h1('10. Validación'));
body.push(bullet([b('Datos de stock y precio: '), t('feed DROPES del 23/11 (4.102 filas) leído con el código del plugin y cotejado con una lectura independiente: 0 discrepancias en stock, PVPR, coste y EAN; 0 textos con codificación rota.')]));
body.push(bullet([b('URL: '), t('las nueve URL del plugin coinciden con el comunicado de Cecotec; la antigua (cecobi.cecotec.cloud) responde con redirección 307.')]));
body.push(bullet([b('Pruebas del código: '), t('generación completa del CSV con datos reales (≈22 s, 55 MB de memoria), lectura por idioma (IT/EN/NL), sincronización de stock, limpieza de borradores, fotos, automatización con respuestas simuladas de WP All Import y renderizado del panel.')]));

body.push(h1('11. Historial de versiones'));
body.push(table([1300, 1500, 6226], ['Versión', 'Fecha', 'Cambios'], [
  ['1.1.0', '09/11', 'Stock de los feeds de Cecotec y sincronización horaria.'],
  ['2.0.0–2.0.6', '10/11', 'Catálogo Plytix, precios DROPES, cURL 61, descripción corta de respaldo, URL de fotos, identificador de WP All Import, duplicados, fotos del feed.'],
  ['2.1.0–2.1.1', '11/11', 'Limpieza de borradores antiguos; el stock solo toca publicados.'],
  ['2.2.0–2.2.2', '11–16/11', 'Caché de fotos, miniaturas esenciales y robustez ante fotos gigantes.'],
  ['2.3.0–2.3.2', '16/11', 'Agotar productos de Cecotec fuera del feed; panel con secciones plegables y barra de acciones.'],
  ['2.4.0–2.4.1', '16/11', 'Nombres sin EAN, opción de productos sin foto, explicación de botones.'],
  ['2.5.0–2.5.2', '17–23/11', 'Poner foto a publicados sin imagen; botones en barra y secciones; nombres y orden de secciones.'],
  ['2.6.0–2.6.2', '23/11', 'CSV diario a hora fija, subida automática a la tienda, títulos del bloque de botones, menú «Actualizador tienda».'],
]));

body.push(h1('12. Pendientes y recomendaciones'));
body.push(bullet([b('Cron Processing Key de WP All Import Pro (cliente): '), t('imprescindible para la subida automática; hoy no está configurada.')]));
body.push(bullet([b('Aviso externo cada 10 minutos '), t('a https://emetronix.es/wp-cron.php?doing_wp_cron (cron-job.org o similar), sin acceso al panel del hosting.')]));
body.push(bullet([b('Fotos de EVVO: '), t('pedir a Cecotec las fotos de los ≈311 productos sin imagen.')]));
body.push(bullet([b('Cabecera HTTP de Cecotec: '), t('comunicada; el plugin ya la evita.')]));
body.push(bullet([b('Otras webs: '), t('.it, .nl, .ie y .com encajan con las zonas del plugin; .de en pausa; .co.uk pendiente de decidir el precio en libras (Cecotec no tiene zona para Reino Unido).')]));
body.push(bullet([b('Mantenimiento: '), t('licencia de WP All Import Pro caducada; varios plugins con actualizaciones pendientes; «Administrador de archivos WP» conviene actualizarlo o desactivarlo.')]));

body.push(h1('13. Anexo: rutas y datos guardados'));
body.push(table([3800, 5226], ['Ruta u opción', 'Contenido'], [
  ['wp-content/uploads/woocotec/catalogo.xml', 'Catálogo de Plytix descargado (acceso web denegado).'],
  ['wp-content/uploads/woocotec/image-queue.json', 'Cola de fotos pendientes.'],
  ['wp-content/uploads/woocotec-img/', 'Fotos preparadas (1.200 px).'],
  ['wp-content/uploads/wpallimport/files/products.csv', 'CSV para WP All Import (26 columnas).'],
  ['Opciones ceco_* (wp_options)', 'Ajustes del panel, estados (ceco_stock_last_sync, ceco_last_csv, ceco_auto_state) y cachés.'],
  ['Repositorio', 'diegoidital/pruebas, rama claude/stoic-cannon-jwi36h, carpeta woocotec/ (código y documentación).'],
]));
// ---------- documento ----------
const doc = new Document({
  creator: 'Idital',
  title: 'Informe técnico: integración Cecotec en emetronix.es',
  styles: {
    default: { document: { run: { font: FONT, size: 22 } } },
    paragraphStyles: [
      { id: 'Title', name: 'Title', basedOn: 'Normal', next: 'Normal', run: { font: FONT, size: 40, bold: true, color: ACCENT }, paragraph: { spacing: { before: 0, after: 120 }, keepNext: true } },
      { id: 'Heading1', name: 'Heading 1', basedOn: 'Normal', next: 'Normal', quickFormat: true, run: { font: FONT, size: 30, bold: true, color: ACCENT }, paragraph: { spacing: { before: 360, after: 120 }, keepNext: true, outlineLevel: 0 } },
      { id: 'Heading2', name: 'Heading 2', basedOn: 'Normal', next: 'Normal', quickFormat: true, run: { font: FONT, size: 24, bold: true, color: '262626' }, paragraph: { spacing: { before: 240, after: 100 }, keepNext: true, outlineLevel: 1 } },
    ],
  },
  numbering: { config: numberingConfigs },
  sections: [{
    properties: { titlePage: true, page: { margin: { top: 1440, bottom: 1440, left: 1440, right: 1440, header: 600 } } },
    headers: { default: new Header({ children: [new Paragraph({ alignment: AlignmentType.RIGHT, children: [logo(90)] })] }) },
    footers: { default: new Footer({ children: [new Paragraph({ alignment: AlignmentType.RIGHT, children: [new TextRun({ text: 'Informe técnico integración Cecotec · página ', color: '808080', size: 18 }), new TextRun({ children: [PageNumber.CURRENT], color: '808080', size: 18 })] })] }) },
    children: body,
  }],
});

Packer.toBuffer(doc).then(buf => { fs.writeFileSync(OUT, buf); console.log('ok', buf.length); });
