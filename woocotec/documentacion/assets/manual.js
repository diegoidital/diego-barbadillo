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

body.push(new Paragraph({ spacing: { after: 360 }, children: [logo(190)] }));
body.push(new Paragraph({ heading: HeadingLevel.TITLE, children: [new TextRun('Manual de uso: Importador de productos Cecotec')] }));
body.push(p([new TextRun({ text: 'emetronix.es · Versión del plugin 2.6 · Menú «Actualizador tienda» · Noviembre de 2026 · Idital', color: '595959' })], { spacing: { after: 240 } }));

body.push(h1('1. Para qué sirve'));
body.push(p('El plugin mantiene la tienda al día con los productos de Cecotec (marcas Cecotec, EVVO y Cecolchón). Crea los productos nuevos, actualiza precios y textos una vez al día y ajusta el stock cada hora, de forma que solo se puede comprar lo que Cecotec tiene disponible.'));
body.push(p('En el día a día no hay que hacer nada: todo funciona solo. Este manual explica cómo comprobar que va bien, cómo forzar una actualización y qué hacer si algo falla.'));

body.push(h1('2. Dónde está'));
body.push(p([t('En el escritorio de WordPress, menú lateral, arriba del todo: '), b('«Actualizador tienda»'), t('. Hace falta un usuario administrador.')]));
body.push(p('Además del plugin, la subida de productos a la tienda la hace WP All Import, en el menú «All Import › Gestionar Importaciones», con la importación llamada «Productos Cecotec».'));

body.push(h1('3. El panel de un vistazo'));
body.push(new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 80 }, children: [new ImageRun({ type: 'png', data: fs.readFileSync(IMG), transformation: { width: 600, height: 367 } })] }));
body.push(p([new TextRun({ text: 'Aspecto del panel «Actualizador tienda» con todas las secciones plegadas (los datos de la imagen son de ejemplo).', italics: true, color: '595959' })], { alignment: AlignmentType.CENTER }));
body.push(table([2600, 6426], ['Zona del panel', 'Para qué sirve'], [
  ['Credenciales de Cecopublisher', 'Usuario y contraseña de Cecotec. Solo se usan para precios fuera de España; en España no hacen falta.'],
  ['Selección de Idioma', 'El país o zona de la tienda. Debe estar en «Español». No cambiarlo salvo que se monte una tienda de otro país.'],
  ['Bloque de botones', 'Tres columnas con su título: «Actualizar stock», «Generar CSV» y «Actualizar tienda». Cada una muestra la última vez que se hizo y su resultado, y tiene su botón. Es lo primero que hay que mirar.'],
  ['Crear CSV Cecotec', 'Genera la lista de productos del día y descarga el catálogo si hace falta. Incluye la automatización: hora del CSV diario y actualización automática de la tienda.'],
  ['Stock', 'Estado y ajustes de la actualización de stock de cada hora.'],
  ['Ajustes de imágenes', 'De dónde salen las fotos y qué fotos se usan.'],
  ['Optimización de imágenes', 'Preparación de fotos ligeras para que la importación sea rápida.'],
  ['Otros optimizadores', 'Herramientas de limpieza: borradores antiguos, nombres con código, productos sin foto.'],
  ['Origen de datos', 'Resumen de qué dato sale de cada fuente de Cecotec.'],
]));
body.push(gap());
body.push(p('Las secciones se abren y se cierran haciendo clic en su título. Al pasar el ratón por encima de cualquier botón aparece una explicación de lo que hace.'));

body.push(h1('4. Lo que pasa solo'));
body.push(table([2400, 3926, 2700], ['Cuándo', 'Qué hace', 'Dónde se ve'], [
  ['Cada noche (03:00)', 'Descarga el catálogo completo de Cecotec (fichas, fotos, categorías).', 'Ajustes de imágenes › «Última descarga»'],
  ['Cada día a la hora elegida', 'Genera la lista de productos (CSV) con precio, stock y ficha.', 'Bloque de botones › «Último CSV»'],
  ['Cada hora', 'Copia el stock de Cecotec en la tienda.', 'Bloque de botones › «Última sincronización»'],
  ['Tras generar el CSV', 'Prepara en segundo plano las fotos de los productos nuevos.', 'Optimización de imágenes › «Estado»'],
  ['Después del CSV y de las fotos', 'Si está activada la actualización automática, el plugin lanza WP All Import: crea productos nuevos, actualiza precios y textos y, al terminar, sincroniza el stock.', 'Bloque de botones › «Actualización de la tienda»'],
]));
body.push(note([b('Importante. '), t('Si la actualización automática está desactivada, hay que lanzar la subida a mano (apartado 5.3). Sin ella, los productos nuevos y los cambios de precio no llegan a la tienda; el stock sí, porque se actualiza aparte cada hora.')]));

body.push(h1('5. Tareas habituales'));

body.push(h2('5.1 Comprobar que todo va bien (1 minuto)'));
body.push(...steps([
  'Abrir el panel «Actualizador tienda» (arriba del todo en el menú de WordPress).',
  [t('Mirar el bloque de botones: en «Actualizar stock», '), b('«Última sincronización»'), t(' debe tener menos de 2 horas; en «Generar CSV», '), b('«Último CSV»'), t(' menos de 1 día; y en «Actualizar tienda», la última actualización debe tener ✅.')],
  'Si alguna muestra ❌ o tiene una fecha antigua, ver el apartado 8 («Problemas frecuentes»).',
]));

body.push(h2('5.2 Actualizar el stock al momento'));
body.push(...steps([
  [t('Pulsar '), b('«Sincronizar stock ahora»'), t(' (en el bloque de botones o en la sección «Stock»).')],
  'Esperar uno o dos minutos hasta que el estado cambie a ✅ con la hora actual.',
]));
body.push(p('No crea productos ni cambia precios: solo copia el stock de Cecotec en la tienda.'));

body.push(h2('5.3 Subir a la tienda productos y precios nuevos (a mano)'));
body.push(p([t('Lo más sencillo: pulsar '), b('«Actualizar la tienda ahora»'), t(' en el bloque de botones. Sube el último CSV con WP All Import y sincroniza el stock al terminar; se puede cerrar la página, sigue sola. Si se quiere hacer todo a mano, paso a paso:')]));
body.push(...steps([
  [t('En el panel, pulsar '), b('«Importación manual»'), t('. Espera a que el registro diga «✅ … productos en el CSV» (puede tardar unos minutos).')],
  [t('Si hay productos nuevos, abrir «Optimización de imágenes» y pulsar '), b('«Preparar fotos ahora»'), t('. Dejar la pestaña abierta hasta que diga «✅ Fotos preparadas y CSV regenerado».')],
  [t('Ir a '), b('All Import › Gestionar Importaciones'), t(' y, en «Productos Cecotec», pulsar '), b('«Ejecutar»'), t('.')],
  'Confirmar en la pantalla de resumen y esperar a que termine (unos 15 minutos). No cerrar la pestaña.',
  'Al terminar, volver al panel y pulsar «Sincronizar stock ahora».',
]));
body.push(p('Es normal que en cada subida aparezcan unas decenas de productos creados y otros tantos «faltantes»: Cecotec añade y retira productos a diario. Los faltantes pasan a borrador y agotados, conservan su dirección web y se vuelven a publicar solos si Cecotec los vuelve a ofrecer.'));

body.push(h2('5.4 Poner foto a productos que no tienen'));
body.push(...steps([
  [t('Abrir «Otros optimizadores» › «Productos publicados sin foto» y pulsar '), b('«1. Buscar productos sin foto»'), t('. Solo muestra la lista.')],
  [t('Si hay productos, pulsar '), b('«2. Poner fotos»'), t(' y dejar la pestaña abierta hasta el ✅.')],
]));
body.push(p('Si dice que no hay productos pero en la tienda se ven productos sin foto, es que Cecotec no tiene fotos de ellos (sobre todo EVVO). Hay que pedírselas a Cecotec; cuando las añada, aparecerán solas.'));

body.push(h2('5.5 Quitar el código de barras del nombre de un producto'));
body.push(p('Algunos productos llegan con el código delante («8435743904049-Ventilador…»). La lista diaria ya lo corrige; para los productos que ya están en la tienda:'));
body.push(...steps([
  [t('«Otros optimizadores» › «Nombres con el EAN delante» › '), b('«1. Buscar nombres con EAN»'), t('.')],
  [t('Revisar la lista y pulsar '), b('«2. Corregir nombres»'), t('. La dirección web del producto no cambia.')],
]));

body.push(h2('5.6 Limpiar borradores antiguos'));
body.push(p('Solo hace falta si vuelven a aparecer fichas antiguas duplicadas en borrador.'));
body.push(...steps([
  [t('«Otros optimizadores» › «Borradores antiguos de Cecotec» › '), b('«1. Buscar borradores antiguos»'), t('.')],
  [t('Revisar que el borrador y el producto del CSV son el mismo artículo y pulsar '), b('«2. Enviar a la papelera»'), t('.')],
  'Vaciar la papelera solo después de comprobar que la tienda está bien.',
]));

body.push(h2('5.7 Si Cecotec cambia una dirección'));
body.push(p([t('Cecotec avisa por correo cuando cambia las direcciones de sus listas. Basta con pegar la nueva en el panel, sin tocar nada más:')]));
body.push(bullet([b('Stock y precios: '), t('sección «Stock» › «URL del feed de stock» › «Guardar ajustes de stock».')]));
body.push(bullet([b('Catálogo: '), t('sección «Ajustes de imágenes» › «URL del catálogo» › «Guardar ajustes del catálogo».')]));
body.push(p('Si el campo se deja vacío, se usa la dirección por defecto.'));

body.push(h1('6. Ajustes recomendados'));
body.push(p('Estos son los valores con los que funciona la tienda. No hace falta tocarlos.'));
body.push(table([2300, 3600, 3126], ['Sección', 'Ajuste', 'Valor recomendado'], [
  ['Selección de Idioma', 'Zona', 'Español'],
  ['Stock', 'Productos de Cecotec fuera del feed', 'Activado (se dejan agotados)'],
  ['Stock', 'URL del feed de stock', 'Vacío (dirección por defecto)'],
  ['Ajustes de imágenes', 'Origen de las imágenes', 'Feed de Cecotec (recomendado)'],
  ['Ajustes de imágenes', 'Fotos de Plytix', 'Solo fotos máster (fondo blanco)'],
  ['Optimización de imágenes', 'Preparar fotos antes de importar', 'Activado, solo productos nuevos'],
  ['Optimización de imágenes', 'Tamaño máximo', '1200 px'],
  ['Optimización de imágenes', 'Fotos por producto', '5'],
  ['Optimización de imágenes', 'Productos sin foto', 'Desactivado (se publican igualmente)'],
  ['Optimización de imágenes', 'Miniaturas durante la importación', 'Activado'],
  ['Crear CSV Cecotec › Automatización', 'CSV diario', 'De madrugada, p. ej. 05:30'],
  ['Crear CSV Cecotec › Automatización', 'Actualizar la tienda automáticamente', 'Activado'],
]));

body.push(h1('7. La importación en WP All Import: lo que no hay que cambiar'));
body.push(p('La importación «Productos Cecotec» está configurada para actualizar solo lo que viene de Cecotec y respetar lo que se cambia a mano en la tienda. No conviene modificarla. Si alguien la toca por error, esta es la configuración correcta:'));
body.push(table([3700, 5326], ['Opción', 'Valor'], [
  ['Identificador único', '{sku[1]}'],
  ['Datos que se actualizan', '«Choose which data to update»'],
  ['Marcados', 'Description, Short Description, Product Status, Precio Normal, Manage Stock, Stock Quantity, Peso, Length, Width, Height, SKU, GTIN, Allow Backorders, Taxonomies (solo product_brand)'],
  ['Desmarcados', 'Product Name, Images, Product Attributes, Precio De Venta y sus fechas, Slug y todo «Admin», Catalog Visibility, Featured Status, Tipo de producto, impuestos y envío, productos vinculados, Custom Fields'],
  ['Productos que faltan en el archivo', 'Cambiar estado a Borrador y stock a Agotado (no borrar)'],
  ['Registros por iteración', '5'],
  ['Imágenes (productos nuevos)', '{imagendestacada[1]}|{galera[1]} con separador |'],
]));
body.push(note([b('Por qué no se actualizan las fotos ni los nombres: '), t('para no cambiar los títulos ni las fotos que se hayan retocado a mano. Las fotos de los productos nuevos sí se importan, y a los existentes sin foto se las pone la herramienta del apartado 5.4.')]));

body.push(h1('8. Problemas frecuentes'));
body.push(table([2600, 3200, 3226], ['Qué se ve', 'Causa probable', 'Qué hacer'], [
  ['❌ en «Última sincronización»', 'La lista de Cecotec no responde o ha cambiado de dirección.', 'Pulsar «Sincronizar stock ahora» más tarde. Si sigue, revisar si Cecotec ha enviado una dirección nueva (5.7).'],
  ['«Última sincronización» con varias horas de retraso', 'Las tareas automáticas de WordPress solo arrancan con visitas a la web.', 'Pulsar «Sincronizar stock ahora». Solución definitiva: activar el aviso externo cada 10 minutos (apartado 9).'],
  ['«Último CSV» de hace más de un día', 'La tarea diaria no se ha ejecutado.', 'Pulsar «Importación manual».'],
  ['❌ en «Actualización de la tienda»', 'WP All Import no ha podido ejecutarse (clave de cron, importación lanzada a mano a la vez, corte del servidor).', 'Leer el mensaje, revisar All Import › Gestionar Importaciones y pulsar «Actualizar la tienda ahora».'],
  ['El CSV tiene muchos menos productos de lo normal', 'Cecotec ha retirado productos o su lista llegó incompleta.', 'No subir a la tienda. Esperar unas horas, repetir «Importación manual» y avisar a Idital si sigue igual.'],
  ['«Un lote ha fallado (HTTP 0)» al preparar fotos', 'Una foto demasiado grande ha cortado el proceso.', 'No hacer nada: se salta esa foto y sigue solo. Si se detiene, volver a pulsar el botón.'],
  ['Producto sin foto en la tienda', 'Cecotec no tiene fotos de ese producto.', 'Probar 5.4. Si no aparece en la lista, pedir las fotos a Cecotec.'],
  ['Producto repetido en la tienda', 'Cecotec usa dos códigos para el mismo producto.', 'El que no tiene stock pasa a borrador en la siguiente subida. Si persiste, avisar a Idital con los dos códigos.'],
  ['Un producto agotado se sigue viendo', 'Es lo normal: se muestra como «Agotado» y no se puede comprar.', 'Para ocultarlos: WooCommerce › Ajustes › Productos › Inventario › «Ocultar en el catálogo los artículos agotados».'],
]));

body.push(h1('9. Pendiente de configurar'));
body.push(bullet([b('Activar la actualización automática '), t('en «Crear CSV Cecotec › Automatización». Requiere que WP All Import tenga la «Cron Processing Key» (All Import › Ajustes); el panel indica si está.')]));
body.push(bullet([b('Aviso externo cada 10 minutos '), t('(por ejemplo con cron-job.org) a la dirección https://emetronix.es/wp-cron.php?doing_wp_cron, para que el stock se actualice cada hora aunque no haya visitas.')]));
body.push(bullet([b('Pedir a Cecotec las fotos '), t('de los unos 311 productos que no tienen ninguna (casi todos de EVVO).')]));

body.push(h1('10. Soporte'));
body.push(p('Ante cualquier duda o error que no esté en este manual, contactar con Idital indicando qué se ve en el bloque de botones del panel (una captura de pantalla es suficiente).'));

// ---------- documento ----------
const doc = new Document({
  creator: 'Idital',
  title: 'Manual de uso: Importador de productos Cecotec',
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
    footers: { default: new Footer({ children: [new Paragraph({ alignment: AlignmentType.RIGHT, children: [new TextRun({ text: 'Manual Importador de productos Cecotec · página ', color: '808080', size: 18 }), new TextRun({ children: [PageNumber.CURRENT], color: '808080', size: 18 })] })] }) },
    children: body,
  }],
});

Packer.toBuffer(doc).then(buf => { fs.writeFileSync(OUT, buf); console.log('ok', buf.length); });
