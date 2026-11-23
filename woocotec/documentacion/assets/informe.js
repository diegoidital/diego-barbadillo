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

body.push(new Paragraph({ spacing: { after: 360 }, children: [logo(190)] }));
body.push(new Paragraph({ heading: HeadingLevel.TITLE, children: [new TextRun('Integración Cecotec en emetronix.es: qué hace y cómo funciona')] }));
body.push(p([new TextRun({ text: '23 de noviembre de 2026 · Idital', color: '595959' })], { spacing: { after: 240 } }));

body.push(h1('En resumen'));
body.push(p([t('La tienda se mantiene sola al día con los productos de Cecotec: crea los productos nuevos, actualiza precios y descripciones cada día y ajusta el stock cada hora. Así '), b('solo se puede comprar lo que Cecotec tiene disponible'), t('.')]));
body.push(p('Hoy son unos 3.950 productos de las marcas Cecotec, EVVO y Cecolchón. Todo lo hace un complemento de WordPress (el plugin «Importador de productos Cecotec», menú «Actualizador tienda») junto con la herramienta de importación que ya tenía la web (WP All Import).'));

body.push(h1('De dónde salen los datos'));
body.push(new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 80 }, children: [new ImageRun({ type: 'png', data: fs.readFileSync(DIAG), transformation: { width: 600, height: 243 } })] }));
body.push(p([t('Cecotec da dos listas: el '), b('catálogo'), t(' (fichas, fotos y categorías de cada producto) y la '), b('lista de precios y stock'), t(' de España. El plugin las une por el código de cada producto; la ficha y el precio se suben a la tienda una vez al día, y el stock se copia cada hora.')]));

body.push(h1('Qué pasa cada día, sin que nadie toque nada'));
body.push(table([2500, 3626, 2900], ['Cuándo', 'Qué hace', 'Para qué'], [
  ['Cada noche (03:00)', 'Descarga el catálogo completo de Cecotec', 'Tener las fichas y fotos al día'],
  ['Cada día a la hora elegida (p. ej. 05:30)', 'Prepara la lista de productos con precio, stock y ficha', 'Dejar listo lo que se va a subir a la tienda'],
  ['Después, cuando las fotos están listas', 'Sube la lista a la tienda: crea productos nuevos, actualiza precios y textos, y sincroniza el stock', 'Que la tienda tenga los mismos productos y precios que Cecotec'],
  ['Cada hora', 'Comprueba el stock de Cecotec y lo copia en la tienda', 'Que no se venda nada que no haya en el almacén'],
  ['Cuando hay productos nuevos', 'Descarga sus fotos y las reduce de tamaño', 'Que la web cargue rápido'],
]));
body.push(gap());
body.push(p('La subida diaria a la tienda (tercera fila) se activa con la casilla «Actualizar la tienda automáticamente» del panel. También se puede lanzar en cualquier momento con el botón «Actualizar la tienda ahora».'));

body.push(h1('Las reglas que aplica'));
body.push(bullet([b('Sin stock, no se vende. '), t('Un producto con stock 0 aparece como «Agotado» y no se puede añadir al carrito.')]));
body.push(bullet([b('Descatalogados. '), t('Se venden mientras quede stock; cuando se agota, salen de la tienda, porque Cecotec ya no los repone.')]));
body.push(bullet([b('Productos que Cecotec retira. '), t('Pasan a borrador: dejan de verse, pero conservan su dirección web. Si Cecotec los vuelve a ofrecer, se publican solos.')]));
body.push(bullet([b('Productos con dos códigos. '), t('Cecotec tiene algunos productos repetidos con un código antiguo y otro nuevo. Se publica solo el que tiene stock, para no mostrar fichas duplicadas.')]));
body.push(bullet([b('Nombres limpios. '), t('Si Cecotec pone el código de barras delante del nombre («8435743904049-Ventilador…»), se quita.')]));
body.push(bullet([b('Fotos de fondo blanco. '), t('Se usan las fotos de producto de Cecotec, reducidas a 1.200 píxeles para que la web vaya rápida.')]));
body.push(bullet([b('No se toca lo que se cambia a mano. '), t('Los títulos, las direcciones web, las categorías, las ofertas y los productos destacados que se ajusten en la tienda se respetan.')]));

body.push(h1('Qué tenéis que hacer vosotros'));
body.push(p([t('En el día a día, nada: todo va solo. El panel está en el escritorio de WordPress, menú '), b('«Actualizador tienda»'), t(' (arriba del todo), por si queréis forzar algo o mirar cómo va.')]));
body.push(table([1900, 2200, 2700, 2226], ['Bloque', 'Botón', 'Qué hace', 'Cuándo usarlo'], [
  ['Actualizar stock', 'Sincronizar stock ahora', 'Copia ya el stock de Cecotec en la tienda', 'Si queréis el stock al minuto, sin esperar a la hora'],
  ['Generar CSV', 'Importación manual', 'Prepara ya la lista de productos del día', 'Si la lista del día no se ha generado'],
  ['Actualizar tienda', 'Actualizar la tienda ahora', 'Sube la lista a la tienda y sincroniza el stock', 'Para ver ya en la tienda productos nuevos o cambios de precio'],
  ['Optimización de imágenes', 'Preparar fotos ahora', 'Descarga y reduce las fotos pendientes', 'Si se añaden muchos productos de golpe'],
  ['Otros optimizadores', 'Varios', 'Limpieza: borradores antiguos, nombres con código, productos sin foto', 'Solo de forma puntual'],
]));
body.push(gap());
body.push(p('En la parte de arriba del panel, bajo los títulos «Actualizar stock», «Generar CSV» y «Actualizar tienda», se ve siempre la última vez que se hizo cada cosa y su resultado. Si alguna muestra ❌, avisadnos.'));

body.push(h1('¿Sirve para otros países?'));
body.push(p([t('Sí, con matices: el plugin ya está preparado para Portugal, Italia, Francia, Alemania, Países Bajos y las Zonas 1, 2 y 3 de Cecotec, pero '), b('una tienda trabaja con un solo país a la vez'), t('. Hasta ahora solo se ha puesto en marcha y probado España.')]));
body.push(table([1900, 3200, 3926], ['Dato', 'España', 'Otros países'], [
  ['Fichas y fotos', 'Catálogo de Cecotec en español', 'El mismo catálogo en el idioma del país (las Zonas 1, 2 y 3 en inglés)'],
  ['Precio', 'Lista de Cecotec para España', 'Tarifa internacional de Cecotec (Cecopublisher), con usuario y contraseña'],
  ['Stock', 'Stock de España', 'Stock propio de cada país o zona'],
  ['Estado', 'En marcha', 'Preparado, sin probar con datos reales'],
]));
body.push(gap());
body.push(p('Para vender en otro país haría falta:'));
body.push(...steps([
  'Una tienda aparte para ese país (otra web o una copia de esta), con el plugin instalado y el país elegido en el panel.',
  'Una prueba completa antes de abrir: precios de la tarifa internacional, stock y textos en el idioma.',
  'Ajustar en la tienda lo que el plugin no hace: IVA, envíos y textos legales del país.',
]));
body.push(p('En otros países Cecotec no da fotos en la lista de stock, así que se usan las del catálogo, que son más pesadas; el plugin las reduce igualmente.'));
body.push(h2('Las webs de emetronix'));
body.push(table([2000, 2300, 1400, 1300, 2026], ['Web', 'Zona en el plugin', 'Idioma', 'Moneda', 'Estado'], [
  ['emetronix.es', 'España', 'español', '€', 'En marcha'],
  ['emetronix.de', 'Alemania', 'alemán', '€', 'En pausa'],
  ['emetronix.it', 'Italia', 'italiano', '€', 'Pendiente'],
  ['emetronix.nl', 'Holandés (Zona 1)', 'neerlandés', '€', 'Pendiente'],
  ['emetronix.ie', 'Zona 3', 'inglés', '€', 'Pendiente'],
  ['emetronix.com', 'Zona 1', 'inglés', '€', 'Pendiente'],
  ['emetronix.co.uk', 'Zona 3 (provisional)', 'inglés', '£', 'Pendiente de decidir'],
]));
body.push(gap());
body.push(p('Cecotec no tiene zona para Reino Unido y sus precios van en euros. Hoy la web .co.uk muestra en libras los mismos números que en euros (1.299 € = £1.299): hay que decidir cómo fijar el precio en libras antes de conectarla.'));

body.push(h1('Lo que queda por cerrar'));
body.push(bullet([b('Fotos que faltan. '), t('Unos 311 productos, casi todos de EVVO, no tienen ninguna foto en los datos de Cecotec. Se venden con la imagen genérica hasta que Cecotec las añada; entonces aparecen solas. Conviene pedírselas a Cecotec.')]));
body.push(bullet([b('Activar la subida automática. '), t('La opción ya está en el plugin: basta con marcar «Actualizar la tienda automáticamente» y elegir la hora.')]));
body.push(bullet([b('Un aviso externo cada 10 minutos. '), t('Las tareas automáticas de WordPress solo arrancan cuando alguien visita la web. Un servicio gratuito que visite la web cada 10 minutos garantiza que todo se ejecute a su hora aunque no haya visitas.')]));
body.push(bullet([b('Aviso técnico a Cecotec. '), t('Sus listas envían una cabecera técnica incorrecta que impide descargarlas con algunos programas. El plugin ya lo tiene resuelto; se les ha pedido que lo corrijan para evitar problemas futuros.')]));
body.push(bullet([b('Precios de emetronix.co.uk. '), t('Decidir cómo pasar a libras los precios de Cecotec.')]));
body.push(p('Los productos entran y salen cada día: es normal ver unas decenas de altas y bajas, porque Cecotec retira productos y deja sin stock los descatalogados.', { spacing: { before: 120, after: 120 } }));

// ---------- documento ----------
const doc = new Document({
  creator: 'Idital',
  title: 'Integración Cecotec en emetronix.es',
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
    footers: { default: new Footer({ children: [new Paragraph({ alignment: AlignmentType.RIGHT, children: [new TextRun({ text: 'Integración Cecotec en emetronix.es · página ', color: '808080', size: 18 }), new TextRun({ children: [PageNumber.CURRENT], color: '808080', size: 18 })] })] }) },
    children: body,
  }],
});

Packer.toBuffer(doc).then(buf => { fs.writeFileSync(OUT, buf); console.log('ok', buf.length); });
