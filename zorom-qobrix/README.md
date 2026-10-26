# Zorom – Promociones Qobrix

Plugin de WordPress que lee los **proyectos (promociones)** del CRM Qobrix de Zorom
(`https://zorom8334.eu1.qobrix.com`, API v2) y los muestra en el bloque
«Promociones destacadas»: una pestaña por proyecto y, dentro de cada una, foto destacada,
título, descripción, precio desde, unidades, fecha y enlace «Ver promoción».

## Instalación

1. Subir `zorom-qobrix.zip` en *Plugins → Añadir nuevo → Subir plugin* y activarlo.
2. En Qobrix, crear un usuario de API y generar su clave (ver «How to generate the Qobrix API key» en el Help Center de Qobrix).
3. En *Ajustes → Qobrix*, rellenar el usuario y la clave de la API, guardar y pulsar **Sincronizar ahora**.
4. Insertar el shortcode en la página (por ejemplo, en un módulo de código o texto del maquetador):

   ```
   [zorom_promociones limite="4"]
   ```

## Ajustes

| Ajuste | Para qué sirve |
|---|---|
| Solo destacados | Muestra solo los proyectos con la casilla «featured» de Qobrix. |
| IDs y orden manual | Lista de IDs de Qobrix, uno por línea, con el orden de las pestañas. |
| Patrón de URL | Enlace de «Ver promoción». Variables: `{slug}`, `{id}`, `{ref}`. Por defecto `/promociones/{slug}/`. |
| Enlaces concretos | `Nombre, ID o ref | URL`, una línea por proyecto, para casos especiales. |
| Campos de Qobrix | Nombre del campo de la API para descripción, precio, unidades y fecha. Al final de la página se listan los campos reales del primer proyecto para comprobarlo. |

Campos por defecto: `short_description`/`description`, `starting_price_from`,
`number_of_units` y `completion_date`.

## Cómo funciona

- Se sincroniza cada hora por WP-Cron y también con el botón «Sincronizar ahora». La web no llama a Qobrix en cada visita, así que no le afecta la caché de página (WP Rocket).
- La foto destacada (`/api/v2/media/icon/projects/{id}/featured_photo/huge`) se descarga a la Biblioteca de medios y WordPress genera el tamaño `zorom-promocion` (880×1140, recortada). Solo se vuelve a descargar si el proyecto cambia en Qobrix.
- Autenticación con las cabeceras `X-Api-User` y `X-Api-Key`. La clave no se vuelve a mostrar en el panel.

## Shortcode

`[zorom_promociones]` admite: `limite`, `texto_enlace`, `etiq_precio`, `etiq_unidades` y `etiq_fecha`.

Los estilos (`assets/promociones.css`) son una base. Se pueden ajustar desde el tema con las
clases `.zq-tab`, `.zq-panel`, `.zq-title`, `.zq-desc`, `.zq-datos` y `.zq-link`.
