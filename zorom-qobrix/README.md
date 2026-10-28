# Zorom – Promociones Qobrix

Plugin de WordPress que conecta la web de Zorom con su CRM Qobrix
(`https://zorom8334.eu1.qobrix.com`, API v2):

- Crea **una página por proyecto** (tipo de contenido «Promoción», URL `/promociones/<nombre>/`)
  y la mantiene al día: título, descripción, foto destacada, galería, datos e inmuebles.
- Todas las páginas se pintan con **una plantilla de Elementor**, elegida en Qobrix → Ajustes.
- Cada promoción tiene **campos manuales** (texto de la zona, localización, imágenes…) que la
  sincronización nunca toca.
- Cualquier dato se coloca donde quieras en Elementor con **shortcodes** `[qobrix …]` o con
  **etiquetas dinámicas** del grupo «Qobrix» (estas últimas, con Elementor Pro).

## Puesta en marcha

1. Subir `zorom-qobrix.zip` en *Plugins → Añadir nuevo → Subir plugin* y activarlo.
2. En Qobrix, crear un usuario de API y su clave.
3. En *Qobrix → Ajustes*: usuario y clave → Guardar → **Sincronizar ahora**.
4. Pulsar **Crear plantilla de ejemplo (diseño Figma)**. Crea la plantilla «Página de promoción (Qobrix)»,
   la deja asignada a todas las promociones y la abre en Elementor para ajustar el estilo.
5. Rellenar en cada promoción la caja **Contenido de la página (manual)**.
6. En la home, el bloque de pestañas: `[zorom_promociones limite="4"]` (o con `plantilla="ID"`).

## Qué viene de Qobrix y qué es manual

| Dato | Origen | Cómo usarlo |
|---|---|---|
| Título, descripción, extracto | Qobrix | `[qobrix campo="titulo"]`, `descripcion`, `descripcion_corta` |
| Precio desde, unidades, fecha, eficiencia | Qobrix | `precio`, `unidades`, `fecha`, `eficiencia` |
| Cualquier otro campo del proyecto | Qobrix | `[qobrix campo="city"]` (con el nombre de Qobrix) |
| Foto destacada y galería | Qobrix | `[qobrix_imagen]`, `[qobrix_imagen campo="galeria:2"]`, `[qobrix_galeria]` |
| Inmuebles del proyecto | Qobrix | `[qobrix_propiedades]` |
| Coordenadas y distancias | Qobrix | `[qobrix_mapa]`, `[qobrix_datos estilo="lista" campos="…"]` |
| Ubicación y frase de portada | Manual (ubicación cae a la ciudad de Qobrix) | `ubicacion`, `subtitulo` |
| Zona: título, subtítulo, texto, imagen | Manual | `zona_titulo`, `zona_subtitulo`, `zona_texto`, `[qobrix_imagen campo="zona_imagen"]` |
| Título de la descripción | Manual | `descripcion_titulo` |
| Localización: título y texto | Manual | `localizacion_titulo`, `localizacion_texto` |
| Zona 2: título, texto, imagen | Manual | `zona2_titulo`, `zona2_texto`, `[qobrix_imagen campo="zona2_imagen"]` |

La página de ajustes lista todos los campos que envía Qobrix, con un ejemplo de cada uno,
y los de los inmuebles para configurar la tabla.

## Shortcodes

| Shortcode | Qué hace | Opciones |
|---|---|---|
| `[qobrix campo="…"]` | Un campo | `formato` (precio, numero, fecha, km, si_no, texto, html), `antes`, `despues`, `vacio`, `proyecto` |
| `[qobrix_imagen]` | Imagen | `campo` (destacada, zona_imagen, zona2_imagen, galeria:N), `tamano`, `clase` |
| `[qobrix_enlace]` | Enlace a la promoción | `texto`, `flecha="no"`, `clase` |
| `[qobrix_hero]` | Portada con foto de fondo | `boton`, `enlace`, `altura` |
| `[qobrix_datos]` | Fichas con icono | `campos="campo:Etiqueta:icono:formato,…"`, `estilo` (tarjetas, lista), `columnas` |
| `[qobrix_propiedades]` | Tabla de inmuebles | `columnas="campo:Etiqueta:formato,…"` (formatos precio, m2, estado), `ocultar`, `vacio` |
| `[qobrix_galeria]` | Galería | `desde`, `limite`, `columnas`, `tamano` |
| `[qobrix_mapa]` | Mapa (Google Maps sin clave) | `zoom`, `altura` |
| `[zorom_promociones]` | Pestañas de promociones | `limite`, `destacados="si"`, `ids`, `orden` (menu, titulo, precio, fecha), `plantilla` |
| `[qobrix_listado]` | Rejilla de promociones | igual que el anterior + `columnas` |

`proyecto` (nombre, slug o ID de Qobrix) solo hace falta fuera de una página de promoción.
Dentro de la plantilla, de una pestaña o de un listado, todo se refiere a la promoción actual.
En el editor de Elementor se previsualiza con la primera promoción.

Los shortcodes funcionan dentro de los widgets Título, Editor de texto, Caja de icono, Botón y
Shortcode de Elementor (sin necesidad de Pro). Con Pro, además, en cualquier control con el
icono de etiquetas dinámicas → Qobrix: Campo, Imagen, Enlace y Galería de la promoción.

## Sincronización

- Cada hora por WP-Cron y con el botón «Sincronizar ahora» (con opción de forzar).
- Una promoción solo se reescribe si ha cambiado en Qobrix. Los inmuebles se revisan siempre,
  porque una vivienda puede venderse sin que cambie el proyecto.
- Se conservan la URL (slug), el orden, los campos manuales y el diseño propio de Elementor de cada promoción.
- Si un proyecto desaparece de Qobrix, su página pasa a borrador. Si vuelve, se publica otra vez.
- Las fotos se descargan a la Biblioteca de medios (la destacada se recorta a 880×1140).
  La clave de API no se envía al servidor de almacenamiento de las fotos.

## Pendiente de comprobar con el Qobrix real

Probado en un WordPress con Elementor y un Qobrix simulado. Con las credenciales reales hay que confirmar:

- Nombres de campos: eficiencia energética, coordenadas, distancias (y si están en km) y los de los inmuebles.
- Ruta de las fotos de galería (`/api/v2/media/{id}/large`).

Los nombres de los campos se pueden cambiar en Ajustes y en las opciones de los shortcodes sin tocar código.

## Página de promoción calcada del Figma (2.5.0)

- `[qobrix_ficha]` pinta la página completa; `[qobrix_ficha seccion="zona"]` una sola sección.
  Secciones: hero, zona, caracteristicas, propiedades, urbanizacion, galeria, localizacion, zona2, contacto.
- La plantilla de ejemplo («Crear plantilla de ejemplo») monta una sección por contenedor de Elementor.
- Medidas, tipografías (Geist e Inter), iconos y bloques negros salen del Figma (lienzo de 1440 px).
- Localización: los seis elementos son fijos (aeropuerto, playa, centro, hospital, escuela, tiendas);
  el número sale de Qobrix (`distance_from_*_amount`) o del campo manual de la promoción.
- Formulario: Qobrix → Ajustes → «Formulario de contacto» (incluye la plantilla de Contact Form 7).
