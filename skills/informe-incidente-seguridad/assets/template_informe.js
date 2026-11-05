/**
 * PLANTILLA: Informe de actuación — incidente de seguridad en WordPress (Idital)
 *
 * Cómo usarla:
 * 1. Copia este fichero a un directorio de trabajo (no lo edites in-place
 *    dentro de la skill).
 * 2. Rellena el objeto DATA con los datos del incidente (ver comentarios
 *    junto a cada campo). Todo el contenido que ve el cliente sale de
 *    aquí — no hay texto de ejemplo "quemado" en el resto del script.
 * 3. Ejecuta: node template_informe.js
 * 4. Verifica el resultado (soffice --convert-to pdf + pdftoppm) antes de
 *    entregarlo, como en cualquier generación de docx.
 *
 * DATA.archivoSalida se calcula solo a partir de DATA.dominio si no lo
 * sobrescribes: "Informe_<NombreDelSitio>.docx".
 */

const {
  Document, Packer, Paragraph, TextRun, Table, TableRow, TableCell,
  WidthType, ShadingType, BorderStyle, HeadingLevel, AlignmentType,
  Header, Footer, PageNumber, LevelFormat, convertInchesToTwip,
} = require("docx");

// ============================================================
// DATA — rellena esto para cada informe. Es lo único que debe
// cambiar entre un informe y otro.
// ============================================================
const DATA = {
  // --- Cabecera ---
  cliente: "[Nombre del cliente]", // si no se conoce, deja el placeholder tal cual
  dominio: "coelival.com",
  fechaDeteccion: "7 de septiembre de 2026",
  estadoActual: "Resuelto — malware eliminado por completo y verificado",
  responsable: "Idital — administración de servidor",

  // --- 1. Resumen ejecutivo ---
  // Párrafo(s) narrativo(s) en lenguaje claro. Puede ser un array de
  // strings (uno por párrafo) o un único string.
  resumenParrafos: [
    "El 7 de septiembre de 2026, nuestro sistema de seguridad (Imunify360) detectó varios archivos maliciosos en el sitio web coelival.com. La investigación técnica confirmó un compromiso completo del sitio: se había instalado un código malicioso con varios mecanismos de auto-regeneración, una vía de acceso oculta que permitía entrar como administrador sin contraseña, y dos cuentas de administrador no autorizadas. Eliminamos por completo el código malicioso de los archivos y de la base de datos, cerramos la vía de acceso oculta, eliminamos las cuentas fraudulentas, rotamos todas las contraseñas y claves de seguridad, y confirmamos que el sitio ya no regenera ningún elemento malicioso.",
  ],
  resumenFrase: "El sitio sufrió una infección completa y persistente; se ha eliminado en su totalidad, se han cerrado todas las vías de reentrada y se han reforzado los accesos.",

  // --- Nota sobre la versión de WordPress (recuadro ámbar en el resumen) ---
  // estado: "confirmado" | "condiciones" | "pendiente" | "ninguno"
  //   - "confirmado":  se conoce la versión exacta y consta que estaba en
  //     el rango afectado por el aviso INCIBE-2026-552. Usa lenguaje directo.
  //     Rellena wpVersionActual (y wpActualizadoA si además se actualizó).
  //   - "condiciones": no se conoce la versión exacta, pero consta que el
  //     servidor/sitio reunía las condiciones técnicas (p. ej. Imagick y
  //     Ghostscript activos). Usa lenguaje cauto, sin inventar versión.
  //   - "pendiente": no hay ningún dato sobre la versión de WordPress en
  //     las fuentes disponibles. Pide confirmar el dato en vez de inventarlo.
  //   - "ninguno": omite esta nota por completo (incidente sin relación
  //     con esta vulnerabilidad, o no procede mencionarla).
  // NUNCA pongas "confirmado" o "condiciones" si no consta explícitamente
  // en la fuente que te ha dado el usuario — ante la duda, usa "pendiente".
  wordpress: {
    estado: "condiciones",
    wpVersionActual: null, // p. ej. "6.9" — solo si estado === "confirmado"
    wpActualizadoA: null,  // p. ej. "7.1" — si además se ha actualizado ya
  },

  // --- 2. Cronología ---
  // Cada fila: [fecha, tipo, descripción]. tipo ∈ "Ataque" | "Automático" | "Manual".
  //   - "Ataque": acciones del atacante.
  //   - "Automático": detección o neutralización automática (Imunify360, etc.).
  //   - "Manual": pasos de remediación realizados por el equipo de Idital.
  // Incluye SIEMPRE, de forma explícita, qué se ha eliminado en cada paso
  // manual (no agrupar "se limpió el sitio" en una sola fila genérica).
  cronologia: [
    ["06/09/2026 (día)", "Ataque", "Actividad de sondeo y varios intentos de acceso al panel de administración del sitio."],
    ["06/09/2026, 22:02", "Ataque", "Acceso no autorizado al panel de administración."],
    ["06/09/2026, 23:55", "Ataque", "Subida y activación de un complemento (plugin) malicioso desde el propio panel."],
    ["07/09/2026, 06:13", "Ataque", "Creación de dos cuentas de administrador no autorizadas."],
    ["07/09/2026", "Automático", "Imunify360 detecta múltiples archivos maliciosos y neutraliza automáticamente los localizados en el primer escaneo."],
    ["07/09/2026", "Manual", "Puesta del sitio en modo seguro (desactivación temporal de su ejecución) para detener la auto-regeneración durante la limpieza."],
    ["07/09/2026", "Manual", "Eliminación manual de todos los archivos maliciosos identificados: el ejecutor, el complemento señuelo, los dos componentes que se ejecutaban antes que el tema o los plugins, y la puerta trasera de acceso."],
    ["07/09/2026", "Manual", "Reinstalación manual y limpia del tema del sitio desde el repositorio oficial, eliminando las inyecciones de código."],
    ["07/09/2026", "Manual", "Desinfección manual de la configuración central de WordPress, eliminando el bloque que regeneraba el código malicioso."],
    ["07/09/2026", "Manual", "Limpieza manual de la base de datos: eliminación del código cifrado oculto y de la tarea programada maliciosa."],
    ["07/09/2026", "Manual", "Eliminación manual de las dos cuentas de administrador fraudulentas, reasignando su contenido al administrador legítimo."],
    ["07/09/2026", "Manual", "Rotación manual de todas las claves de seguridad y contraseñas: WordPress, base de datos, FTP/SFTP y panel de gestión del servidor."],
    ["07/09/2026", "Manual", "Bloqueo manual de los orígenes del ataque en el firewall del servidor."],
    ["07/09/2026 (cierre)", "Automático", "Verificación automatizada final: sin firmas de malware ni reaparición de código tras reactivar el sitio."],
  ],

  // --- 3. Qué se encontró (bullets, lenguaje sencillo) ---
  queSeEncontro: [
    "Se encontró un código malicioso que se hacía pasar por una utilidad legítima de WordPress relacionada con el rendimiento, diseñado para reaparecer automáticamente aunque se eliminara solo una parte.",
    "Ese código se escondía en varios lugares a la vez: archivos del tema activo del sitio, un complemento señuelo, dos componentes especiales de WordPress que se ejecutan antes que el tema o los plugins, la configuración central del sitio, la base de datos, y una tarea programada diaria que reconstruía todo lo que se eliminaba.",
    "Se encontró además una vía de acceso oculta que habría permitido entrar como administrador sin necesidad de contraseña, visitando una dirección concreta del sitio.",
    "Se habían creado dos cuentas de administrador que no correspondían a nadie del equipo del sitio.",
  ],

  // --- 4. Medidas aplicadas (bullets) ---
  medidasAplicadas: [
    "Puesta del sitio en modo seguro, deteniendo temporalmente su ejecución para frenar la auto-regeneración mientras se realizaba la limpieza.",
    "Eliminación completa de todos los archivos maliciosos identificados, incluidos los componentes que se ejecutaban antes que el tema o los plugins.",
    "Reinstalación limpia del tema del sitio desde el repositorio oficial, eliminando cualquier código insertado por el atacante.",
    "Limpieza de la configuración central de WordPress, eliminando el bloque que regeneraba el código malicioso y conservando la configuración legítima.",
    "Limpieza completa de la base de datos, eliminando el código oculto y la tarea programada maliciosa.",
    "Eliminación de las dos cuentas de administrador no autorizadas, reasignando su contenido al administrador legítimo.",
    "Rotación de todas las claves de seguridad internas de WordPress, y cambio de las contraseñas de administrador, base de datos y accesos de gestión del sitio (FTP/panel).",
    "Bloqueo de los orígenes del ataque en el firewall del servidor.",
    "Verificación final: confirmado que no reaparece ningún archivo ni entrada maliciosa tras reactivar el sitio.",
  ],

  // --- Recuadro verde opcional: actualización de WordPress destacada ---
  // Déjalo en null si en este incidente no se ha hecho ninguna
  // actualización de versión que merezca destacarse aparte (por ejemplo,
  // porque no se conoce la versión, o porque no se ha actualizado).
  actualizacionDestacada: null,
  // Ejemplo si aplica:
  // actualizacionDestacada: { de: "6.9", a: "7.1" },

  // --- 5. Recomendaciones (bullets) ---
  recomendaciones: [
    "Utilizar una contraseña única y robusta para el administrador, distinta de la de otros sitios o servicios, y guardada en un gestor de contraseñas.",
    "Activar la verificación en dos pasos (2FA) para las cuentas de administrador.",
    "Limitar los intentos de acceso al panel y restringir o bloquear el acceso a xmlrpc.php.",
    "Desactivar la instalación o edición de complementos desde el panel cuando no sea necesaria.",
    "Mantener WordPress, el tema y los complementos siempre actualizados a la última versión disponible (ver apartado 7).",
    "Mantener copias de seguridad periódicas y verificadas, almacenadas fuera del servidor.",
    "Mantener activa la vigilancia de seguridad (Imunify360) sobre este dominio durante las próximas semanas.",
  ],

  // --- 6. Estado del incidente (párrafo de cierre) ---
  estadoIncidenteParrafo: "Incidente cerrado. El código malicioso ha sido eliminado por completo del sitio web coelival.com, junto con todas sus vías de reentrada y las cuentas de administrador no autorizadas. Se ha verificado que no queda ningún rastro ni mecanismo de regeneración activo tras la limpieza. Quedamos a su disposición para cualquier duda o aclaración sobre este informe.",

  // --- 7. Aviso INCIBE-2026-552 / CVE-2026-65640 ---
  // true (por defecto): incluye el apartado 7 con el aviso y la tabla de
  // versiones (ver references/vulnerabilidades-wp.md). Ponlo a false solo
  // si el incidente no tiene ninguna relación con WordPress/esta familia
  // de avisos.
  incluirSeccionIncibe: true,

  // Nombre de archivo de salida. Si se deja null, se calcula solo a partir
  // de DATA.dominio: "Informe_<dominio_sin_puntos>.docx".
  archivoSalida: null,
};

// ============================================================
// A partir de aquí no hace falta tocar nada — es la maquetación
// y estructura ya validadas con el cliente.
// ============================================================

const BLUE = "1F4E79";
const LIGHT_BLUE = "DCE6F1";
const GRAY_TEXT = "555555";
const AMBER_BG = "FDF2D0";
const AMBER_BORDER = "C9971F";
const GREEN_BG = "E2F0D9";
const GREEN_BORDER = "548235";
const TYPE_COLORS = { "Ataque": "B04A4A", "Automático": "2E6B9E", "Manual": "3E7A3E" };

function run(text, opts = {}) {
  return new TextRun({ text, size: 22, ...opts });
}
function bodyText(text, opts = {}) {
  return new Paragraph({ spacing: { after: 160, line: 276 }, children: [run(text, opts)] });
}
function heading(text, level) {
  return new Paragraph({
    heading: level,
    spacing: { before: 320, after: 160 },
    children: [new TextRun({ text, bold: true, color: BLUE })],
  });
}
function bullet(text) {
  return new Paragraph({
    numbering: { reference: "bullet-list", level: 0 },
    spacing: { after: 100 },
    children: [run(text)],
  });
}
function metaRow(label, valueRuns) {
  return new TableRow({
    children: [
      new TableCell({
        width: { size: 3000, type: WidthType.DXA },
        shading: { type: ShadingType.CLEAR, fill: LIGHT_BLUE },
        margins: { top: 100, bottom: 100, left: 120, right: 120 },
        children: [new Paragraph({ children: [new TextRun({ text: label, bold: true, size: 20 })] })],
      }),
      new TableCell({
        width: { size: 6200, type: WidthType.DXA },
        margins: { top: 100, bottom: 100, left: 120, right: 120 },
        children: [new Paragraph({ children: valueRuns })],
      }),
    ],
  });
}
function amberRow(label, value) {
  return new TableRow({
    children: [
      new TableCell({
        width: { size: 3000, type: WidthType.DXA },
        shading: { type: ShadingType.CLEAR, fill: AMBER_BG },
        margins: { top: 100, bottom: 100, left: 120, right: 120 },
        children: [new Paragraph({ children: [new TextRun({ text: label, bold: true, size: 20 })] })],
      }),
      new TableCell({
        width: { size: 6200, type: WidthType.DXA },
        shading: { type: ShadingType.CLEAR, fill: AMBER_BG },
        margins: { top: 100, bottom: 100, left: 120, right: 120 },
        children: [new Paragraph({ children: [new TextRun({ text: value, size: 20 })] })],
      }),
    ],
  });
}
function tipoRun(tipo) {
  return new TextRun({ text: tipo, bold: true, size: 19, color: TYPE_COLORS[tipo] || "555555" });
}
function chronoHeader() {
  return new TableRow({
    tableHeader: true,
    children: [
      new TableCell({
        width: { size: 1700, type: WidthType.DXA }, shading: { type: ShadingType.CLEAR, fill: BLUE },
        margins: { top: 80, bottom: 80, left: 120, right: 120 },
        children: [new Paragraph({ children: [new TextRun({ text: "Fecha / hora", bold: true, color: "FFFFFF", size: 20 })] })],
      }),
      new TableCell({
        width: { size: 1300, type: WidthType.DXA }, shading: { type: ShadingType.CLEAR, fill: BLUE },
        margins: { top: 80, bottom: 80, left: 120, right: 120 },
        children: [new Paragraph({ children: [new TextRun({ text: "Tipo", bold: true, color: "FFFFFF", size: 20 })] })],
      }),
      new TableCell({
        width: { size: 6200, type: WidthType.DXA }, shading: { type: ShadingType.CLEAR, fill: BLUE },
        margins: { top: 80, bottom: 80, left: 120, right: 120 },
        children: [new Paragraph({ children: [new TextRun({ text: "Qué ocurrió / qué se hizo", bold: true, color: "FFFFFF", size: 20 })] })],
      }),
    ],
  });
}
function chronoRow(fecha, tipo, desc) {
  return new TableRow({
    children: [
      new TableCell({
        width: { size: 1700, type: WidthType.DXA }, margins: { top: 80, bottom: 80, left: 120, right: 120 },
        children: [new Paragraph({ children: [run(fecha, { size: 20 })] })],
      }),
      new TableCell({
        width: { size: 1300, type: WidthType.DXA }, margins: { top: 80, bottom: 80, left: 120, right: 120 },
        children: [new Paragraph({ children: [tipoRun(tipo)] })],
      }),
      new TableCell({
        width: { size: 6200, type: WidthType.DXA }, margins: { top: 80, bottom: 80, left: 120, right: 120 },
        children: [new Paragraph({ children: [run(desc, { size: 20 })] })],
      }),
    ],
  });
}

// Tabla de versiones INCIBE-2026-552 / CVE-2026-65640 (ver references/vulnerabilidades-wp.md)
const WP_VERSIONS = [
  ["7.0", "7.0.4"], ["6.9", "6.9.7"], ["6.8", "6.8.8"], ["6.7", "6.7.7"],
  ["6.6", "6.6.7"], ["6.5", "6.5.10"], ["6.4", "6.4.10"], ["6.3", "6.3.10"],
  ["6.2", "6.2.11"], ["6.1", "6.1.12"], ["6.0", "6.0.14"], ["5.9", "5.9.16"],
  ["5.8", "5.8.15"], ["5.7", "5.7.17"], ["5.6", "5.6.19"], ["5.5", "5.5.20"],
  ["5.4", "5.4.21"], ["5.3", "5.3.23"], ["5.2", "5.2.26"], ["5.1", "5.1.24"],
  ["5.0", "5.0.27"], ["4.9", "4.9.31"], ["4.8", "4.8.30"], ["4.7", "4.7.35"],
];
function versionCell(text, opts = {}, cellOpts = {}) {
  return new TableCell({
    width: { size: 2300, type: WidthType.DXA },
    margins: { top: 60, bottom: 60, left: 100, right: 100 },
    children: [new Paragraph({ children: [new TextRun({ text, size: 18, ...opts })] })],
    ...cellOpts,
  });
}
function buildVersionRows() {
  const headerShading = { shading: { type: ShadingType.CLEAR, fill: BLUE } };
  const rows = [new TableRow({
    tableHeader: true,
    children: [
      versionCell("Versión", { bold: true, color: "FFFFFF" }, headerShading),
      versionCell("Corregida en", { bold: true, color: "FFFFFF" }, headerShading),
      versionCell("Versión", { bold: true, color: "FFFFFF" }, headerShading),
      versionCell("Corregida en", { bold: true, color: "FFFFFF" }, headerShading),
    ],
  })];
  for (let i = 0; i < WP_VERSIONS.length; i += 2) {
    const [v1, p1] = WP_VERSIONS[i];
    const pair2 = WP_VERSIONS[i + 1];
    rows.push(new TableRow({
      children: [
        versionCell(v1), versionCell(p1),
        versionCell(pair2 ? pair2[0] : ""), versionCell(pair2 ? pair2[1] : ""),
      ],
    }));
  }
  return rows;
}

// Construye el párrafo de la nota sobre versión de WordPress según
// DATA.wordpress.estado. Devuelve null si estado === "ninguno".
function buildNotaWordpress() {
  const wp = DATA.wordpress || { estado: "ninguno" };
  if (wp.estado === "ninguno") return null;

  let texto;
  if (wp.estado === "confirmado") {
    const actualizado = wp.wpActualizadoA
      ? ` Esta vulnerabilidad ha quedado resuelta con la actualización a la versión ${wp.wpActualizadoA} (ver apartado 7).`
      : ` Recomendamos actualizar cuanto antes a la versión segura correspondiente (ver apartado 7).`;
    texto = `el sitio funcionaba con WordPress ${wp.wpVersionActual}, una versión que en el momento del incidente ya se encontraba dentro del rango afectado por el aviso de seguridad INCIBE-2026-552 (CVE-2026-65640), de importancia alta. Es decir, además del incidente descrito en este informe, el sitio presentaba una vulnerabilidad de software adicional por no estar en la última versión disponible.${actualizado}`;
  } else if (wp.estado === "condiciones") {
    texto = `el servidor donde se aloja este sitio reunía las condiciones técnicas necesarias para el aviso de seguridad INCIBE-2026-552 (CVE-2026-65640), de importancia alta, publicado el 13 de agosto de 2026 — es decir, el sitio no se encontraba en una versión de WordPress con los parches de seguridad más recientes. Aunque, como se detalla en este informe, esta vulnerabilidad concreta no fue necesariamente la vía de entrada del incidente, sí representa un riesgo de seguridad adicional que recomendamos cerrar cuanto antes actualizando WordPress (ver apartado 7).`;
  } else { // "pendiente"
    texto = `en el momento del incidente no consta documentada la versión exacta de WordPress instalada en este sitio. Dado que se ha publicado el aviso de seguridad INCIBE-2026-552 (CVE-2026-65640), de importancia alta, que afecta a un rango muy amplio de versiones de WordPress, recomendamos confirmar y documentar la versión utilizada en este sitio en el momento del incidente, para determinar si se encontraba dentro del rango afectado.`;
  }

  return new Paragraph({
    spacing: { after: 160, line: 276 },
    shading: { type: ShadingType.CLEAR, fill: AMBER_BG },
    children: [
      new TextRun({ text: "Nota sobre la versión de WordPress: ", bold: true, size: 22 }),
      new TextRun({ text: texto, size: 22, italics: true }),
    ],
  });
}

function buildActualizacionDestacada() {
  const a = DATA.actualizacionDestacada;
  if (!a) return [];
  return [
    new Table({
      width: { size: 9200, type: WidthType.DXA },
      columnWidths: [9200],
      borders: {
        top: { style: BorderStyle.SINGLE, size: 4, color: GREEN_BORDER },
        bottom: { style: BorderStyle.SINGLE, size: 4, color: GREEN_BORDER },
        left: { style: BorderStyle.SINGLE, size: 4, color: GREEN_BORDER },
        right: { style: BorderStyle.SINGLE, size: 4, color: GREEN_BORDER },
      },
      rows: [
        new TableRow({
          children: [
            new TableCell({
              width: { size: 9200, type: WidthType.DXA },
              shading: { type: ShadingType.CLEAR, fill: GREEN_BG },
              margins: { top: 140, bottom: 140, left: 160, right: 160 },
              children: [
                new Paragraph({
                  spacing: { after: 80 },
                  children: [new TextRun({ text: `✔ Sitio actualizado: WordPress ${a.de} → ${a.a}`, bold: true, size: 22 })],
                }),
                new Paragraph({
                  spacing: { after: 0, line: 276 },
                  children: [run(`El sitio se encontraba en la versión ${a.de}, y como parte de la remediación lo hemos actualizado a la versión ${a.a}. Esto cierra cualquier fallo de software conocido hasta la fecha y reduce de forma significativa el riesgo de nuevos accesos no autorizados o infecciones a través de vulnerabilidades del propio WordPress.`)],
                }),
              ],
            }),
          ],
        }),
      ],
    }),
    bodyText(" "),
  ];
}

function buildSeccionIncibe() {
  if (!DATA.incluirSeccionIncibe) return [];
  return [
    heading("7. La importancia de tener la web actualizada", HeadingLevel.HEADING_1),
    bodyText("Al margen del origen concreto de este incidente, queremos aprovechar este informe para destacar un aviso de seguridad reciente y de alta importancia, publicado por INCIBE, que refuerza por qué mantener WordPress actualizado es una de las medidas de protección más importantes que existen."),
    new Table({
      width: { size: 9200, type: WidthType.DXA },
      columnWidths: [3000, 6200],
      borders: {
        top: { style: BorderStyle.SINGLE, size: 4, color: AMBER_BORDER },
        bottom: { style: BorderStyle.SINGLE, size: 4, color: AMBER_BORDER },
        left: { style: BorderStyle.SINGLE, size: 4, color: AMBER_BORDER },
        right: { style: BorderStyle.SINGLE, size: 4, color: AMBER_BORDER },
        insideHorizontal: { style: BorderStyle.SINGLE, size: 4, color: AMBER_BORDER },
        insideVertical: { style: BorderStyle.SINGLE, size: 4, color: AMBER_BORDER },
      },
      rows: [
        amberRow("Vulnerabilidad", "Subida de archivos sin restricciones en WordPress"),
        amberRow("Identificador", "INCIBE-2026-552  ·  CVE-2026-65640"),
        amberRow("Fecha de publicación", "13 de agosto de 2026"),
        amberRow("Importancia", "4 — Alta"),
      ],
    }),
    bodyText(" "),
    bodyText("¿Qué significa esto en la práctica? Se ha detectado un fallo de seguridad grave que afecta a un rango muy amplio de versiones de WordPress (en la rama 7.0, todas las versiones anteriores a la 7.0.4; en las demás ramas, las anteriores a su versión corregida). Un atacante que consiga permisos para subir archivos al sitio podría aprovecharlo para ejecutar código en el servidor, subiendo un archivo especialmente manipulado que el sistema procesa de forma insegura."),
    bodyText("En resumen: cuantas más versiones antiguas de WordPress queden sin actualizar, más puertas quedan abiertas para este tipo de ataques. Mantener WordPress y sus complementos actualizados de manera continua — junto con contraseñas robustas y únicas — sigue siendo la medida de prevención más eficaz."),
    new Paragraph({
      spacing: { before: 120, after: 120 },
      children: [new TextRun({ text: "Versiones afectadas y versión corregida correspondiente:", bold: true, size: 22 })],
    }),
    new Table({
      width: { size: 9200, type: WidthType.DXA },
      columnWidths: [2300, 2300, 2300, 2300],
      rows: buildVersionRows(),
    }),
    new Paragraph({
      spacing: { before: 200, after: 160 },
      children: [
        new TextRun({ text: "Recomendación de Idital: ", bold: true, size: 22 }),
        run("comprobaremos la versión de WordPress instalada en este sitio y aplicaremos la actualización correspondiente de inmediato si no está ya al día."),
      ],
    }),
  ];
}

function nombreArchivoPorDefecto(dominio) {
  const limpio = dominio.replace(/^https?:\/\//, "").replace(/\/.*$/, "").replace(/\./g, "_");
  return `Informe_${limpio}.docx`;
}

const resumenParrafos = Array.isArray(DATA.resumenParrafos) ? DATA.resumenParrafos : [DATA.resumenParrafos];
const notaWordpress = buildNotaWordpress();

const doc = new Document({
  numbering: {
    config: [
      {
        reference: "bullet-list",
        levels: [
          { level: 0, format: LevelFormat.BULLET, text: "•", alignment: AlignmentType.LEFT,
            style: { paragraph: { indent: { left: convertInchesToTwip(0.3), hanging: convertInchesToTwip(0.2) } } } },
        ],
      },
    ],
  },
  sections: [
    {
      properties: {
        page: {
          size: { width: 11906, height: 16838 }, // A4
          margin: { top: 1000, bottom: 1000, left: 1100, right: 1100 },
        },
      },
      headers: {
        default: new Header({
          children: [
            new Paragraph({
              border: { bottom: { style: BorderStyle.SINGLE, size: 6, color: BLUE, space: 6 } },
              children: [
                new TextRun({ text: "Idital", bold: true, color: BLUE, size: 20 }),
                new TextRun({ text: "  ·  Informe de actuación — incidente de seguridad", color: GRAY_TEXT, size: 20 }),
              ],
            }),
          ],
        }),
      },
      footers: {
        default: new Footer({
          children: [
            new Paragraph({
              alignment: AlignmentType.CENTER,
              children: [
                new TextRun({ text: "Página ", size: 18, color: GRAY_TEXT }),
                new TextRun({ children: [PageNumber.CURRENT], size: 18, color: GRAY_TEXT }),
                new TextRun({ text: " de ", size: 18, color: GRAY_TEXT }),
                new TextRun({ children: [PageNumber.TOTAL_PAGES], size: 18, color: GRAY_TEXT }),
              ],
            }),
          ],
        }),
      },
      children: [
        new Paragraph({
          spacing: { after: 60 },
          children: [new TextRun({ text: "INFORME DE ACTUACIÓN", bold: true, size: 32, color: BLUE })],
        }),
        new Paragraph({
          spacing: { after: 300 },
          children: [new TextRun({ text: "Incidente de seguridad resuelto en sitio web", size: 24, color: GRAY_TEXT })],
        }),

        new Table({
          width: { size: 9200, type: WidthType.DXA },
          columnWidths: [3000, 6200],
          rows: [
            metaRow("Cliente", [new TextRun({ text: DATA.cliente, italics: DATA.cliente.startsWith("["), color: DATA.cliente.startsWith("[") ? "8A8A8A" : "000000" })]),
            metaRow("Dominio afectado", [run(DATA.dominio)]),
            metaRow("Fecha de detección", [run(DATA.fechaDeteccion)]),
            metaRow("Estado actual", [run(DATA.estadoActual)]),
            metaRow("Responsable de la respuesta", [run(DATA.responsable)]),
          ],
        }),

        heading("1. Resumen ejecutivo", HeadingLevel.HEADING_1),
        bodyText("Este informe resume, en lenguaje claro y sin tecnicismos innecesarios, lo ocurrido en su sitio web en relación con un incidente de seguridad, lo que hemos hecho para solucionarlo y el estado actual del sitio web."),
        ...resumenParrafos.map((p) => bodyText(p)),
        ...(notaWordpress ? [notaWordpress] : []),
        bodyText("Resumen en una frase:", { bold: true }),
        bodyText(DATA.resumenFrase),

        heading("2. Cronología de los hechos", HeadingLevel.HEADING_1),
        bodyText("Resumen, paso a paso, de cuándo ocurrió cada cosa, incluyendo qué se eliminó y si la acción fue automática o manual."),
        new Table({
          width: { size: 9200, type: WidthType.DXA },
          columnWidths: [1700, 1300, 6200],
          rows: [chronoHeader(), ...DATA.cronologia.map(([f, t, d]) => chronoRow(f, t, d))],
        }),

        heading("3. Qué se encontró", HeadingLevel.HEADING_1),
        bodyText("Descripción en términos sencillos de qué tipo de problema afectaba al sitio y cómo actuaba."),
        ...DATA.queSeEncontro.map((t) => bullet(t)),

        heading("4. Medidas aplicadas", HeadingLevel.HEADING_1),
        bodyText("Estas son las acciones que hemos llevado a cabo para resolver el incidente:"),
        ...DATA.medidasAplicadas.map((t) => bullet(t)),
        ...buildActualizacionDestacada(),

        heading("5. Recomendaciones", HeadingLevel.HEADING_1),
        bodyText("Para reforzar la seguridad del sitio de cara al futuro, recomendamos:"),
        ...DATA.recomendaciones.map((t) => bullet(t)),

        heading("6. Estado del incidente", HeadingLevel.HEADING_1),
        bodyText(DATA.estadoIncidenteParrafo),

        ...buildSeccionIncibe(),

        new Paragraph({
          spacing: { before: 300 },
          border: { top: { style: BorderStyle.SINGLE, size: 4, color: "CCCCCC", space: 8 } },
          children: [new TextRun({ text: "Informe generado por Idital — administración de servidor.", size: 18, color: GRAY_TEXT, italics: true })],
        }),
      ],
    },
  ],
});

const archivoSalida = DATA.archivoSalida || nombreArchivoPorDefecto(DATA.dominio);
Packer.toBuffer(doc).then((buffer) => {
  require("fs").writeFileSync(archivoSalida, buffer);
  console.log("Generado:", archivoSalida);
});
