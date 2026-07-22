# Análisis web y propuesta de arquitectura SEO — zorom.es

**Sitio analizado:** https://www.zorom.es/ (ZOROM, promotora inmobiliaria en la Costa Blanca)
**Fecha:** 22/07/2026
**Método:** rastreo lento del `sitemap_index.xml` (89 URLs) siguiendo redirecciones y extrayendo estado HTTP, títulos, H1, meta-descriptions, hreflang y enlazado interno.

> Nota de acceso: el sitio está detrás de Cloudflare con un WAF agresivo que corta las ráfagas de peticiones. Los datos se obtuvieron con un rastreo de 1–2 conexiones y pausas. Es un aviso a tener en cuenta para futuras auditorías (usar siempre ritmo lento).

---

## 1. Informe de enlaces

**Resumen del sitemap (89 URLs declaradas):**

| Categoría | Nº URLs | % |
|---|---|---|
| ✅ OK (200) | 39 | 44 % |
| 🟡 Redirección (302) | 2 | 2 % |
| 🔴 404 / No encontrada | 48 | **54 %** |

*(Detalle completo y coloreado en `informe_enlaces_zorom.xlsx`.)*

### 🔴 Problema grave: más de la mitad del sitemap son 404

Las **48 URLs rotas** son páginas de taxonomía que el tema inmobiliario (WP Residence / similar) genera automáticamente y mete en el sitemap, pero que **no existen**. Todas siguen el patrón:

```
/properties/{tipo}/for_sale/     ← 404
/properties/{tipo}/for_rent/     ← 404
/en/properties/{tipo}/for_sale/  ← 404
/en/properties/{tipo}/for_rent/  ← 404
```
(tipos: house, apartment, land, retail, office, industrial, investment, building, parking_lot, hotel, y los agregados `for_sale` / `for_rent`).

**Impacto SEO:** Google gasta *crawl budget* en 48 URLs muertas, el sitemap pierde credibilidad y se diluye la señal de las páginas buenas. Es lo primero que hay que corregir.

**Acción recomendada:**
- Desactivar esos tipos/taxonomías en el generador de sitemap (Yoast/RankMath → excluir CPT `properties` y sus taxonomías), o
- Configurar el tema para que no publique en el sitemap las combinaciones vacías.

### 🟡 Redirecciones (302) — slugs de proyecto inconsistentes ES/EN

| URL en sitemap | Redirige a (302) |
|---|---|
| `/en/project/gran-sol-villas/114/` | `/en/project/gran-sol/114/` |
| `/en/project/la-merced-calpe/115/` | `/en/project/la-merced/115/` |

El slug **en inglés** de estos dos proyectos se cambió (gran-sol-villas → gran-sol, la-merced-calpe → la-merced) pero **el sitemap sigue apuntando al antiguo** y la versión española conserva el slug largo (`/project/gran-sol-villas/114/` en ES sí da 200). Resultado: incoherencia ES/EN y redirecciones innecesarias indexables.

**Acción:** actualizar el sitemap al slug nuevo y unificar el slug entre idiomas. Un 302 además debería ser 301 (permanente) si el cambio es definitivo.

### Páginas válidas (39 × 200)

19 páginas ES + 20 EN (más las fichas de proyecto/propiedad). Estructura de contenido real más abajo.

---

## 2. Informe de arquitectura web actual

### Idiomas
- **Español** en la raíz (`/…`) — sin prefijo `/es/`.
- **Inglés** bajo `/en/…`.
- Traducción aparentemente completa de las páginas principales.

### Tipos de contenido detectados
1. **Páginas** (institucionales): home, sobre nosotros, sobre Costa Blanca, colaboración, contacto, desarrollos próximos, wishlist, legales.
2. **Projects** (CPT `project`): 4 promociones → `/project/{slug}/{id}/`.
3. **Properties** (CPT `property`): 6 unidades individuales → `/property/{slug}/{id}/`.

### 🔴 Problemas de arquitectura y SEO detectados

| # | Problema | Gravedad | Detalle |
|---|---|---|---|
| 1 | **48 URLs 404 en sitemap** | 🔴 Alta | Taxonomías inmobiliarias vacías (ver informe de enlaces). |
| 2 | **0 etiquetas hreflang** | 🔴 Alta | Ninguna de las 39 páginas declara `hreflang`. En una web bilingüe es crítico: Google no sabe que `/sobre-nosotros/` y `/en/about-us/` son la misma página en dos idiomas → canibalización y mala segmentación por país/idioma. |
| 3 | **Home sin H1** | 🟠 Media | Ni `/` ni `/en/` tienen encabezado H1. Pérdida de la señal semántica más importante de la página. |
| 4 | **Meta descriptions ausentes** | 🟠 Media | Faltan en `/desarrollos-proximos/`, `/en/upcoming-developments/` y `/wishlist/`. |
| 5 | **URLs con ID numérico** | 🟠 Media | `/project/amiral/116/`, `/property/…/1072/`. El `/{id}/` final ensucia la URL y no aporta a SEO; mejor slug limpio. |
| 6 | **Slug de contacto inconsistente** | 🟡 Baja | ES `/contactenos/` vs EN `/en/contact-us/`. Conviene unificar criterio (`/contacto/`). |
| 7 | **`/wishlist/` indexable** | 🟡 Baja | Página funcional (favoritos) sin valor SEO; debería ser `noindex`. |
| 8 | **Sin sección de blog/contenidos** | 🟠 Media | No existe blog, noticias ni contenido editorial → se pierde tráfico orgánico de long-tail (justo lo que resuelve la nueva arquitectura). |

---

## 3. Árbol de enlaces (estructura actual)

Reconstruido a partir del enlazado interno real (menú + enlaces en página):

```
zorom.es/  (Inicio ES)  ── hreflang ✗ ──  /en/  (Home EN)
│
├── /sobre-nosotros/                        └── /en/about-us/
├── /sobre-costa-blanca/                     └── /en/about-costa-blanca/
├── /colaboracion/                           └── /en/collaboration/
├── /contactenos/                            └── /en/contact-us/
├── /desarrollos-proximos/                   └── /en/upcoming-developments/
│
├── /projects/  (listado de proyectos)       └── /en/projects/
│   ├── /project/amiral/116/                 └── /en/project/amiral/116/
│   ├── /project/el-paraiso/113/             └── /en/project/el-paraiso/113/
│   ├── /project/gran-sol-villas/114/        └── /en/project/gran-sol/114/     (302 desde slug viejo)
│   └── /project/la-merced-calpe/115/        └── /en/project/la-merced/115/    (302 desde slug viejo)
│
│   └── Propiedades / unidades (CPT property):
│       ├── /property/la-merced-villa-12b/1072/
│       ├── /property/la-merced-villa-13b/1071/
│       ├── /property/gran-sol-villa-1l/1068/
│       ├── /property/gran-sol-villa-2l/1069/
│       ├── /property/gran-sol-villa-3l/1070/
│       └── /property/el-paraiso-unit-17/1067/     (+ versiones /en/property/…)
│
├── /wishlist/   (favoritos — debería ser noindex)
│
├── Legales:  /aviso-legal/ · /politica-de-privacidad/ · /politica-de-cookies/
│             /en/legal-notice/ · /en/privacy-policy/ · /en/cookie-policy/
│
└── 🔴 48 URLs 404 colgando del sitemap (/properties/{tipo}/for_sale|for_rent/)  ← eliminar
```

**Observaciones del enlazado interno:**
- El menú principal enlaza bien las páginas institucionales y `/projects/`.
- Las fichas de proyecto enlazan entre sí y a `/desarrollos-proximos/` → buen *silo* de proyectos.
- **No hay enlaces ES↔EN por hreflang** (solo un selector de idioma en el header), y aparece algún enlace cruzado suelto (la home ES enlaza a `/privacy-policy` sin prefijo `/en/`).
- No existe ningún nodo de blog/contenidos → oportunidad de tráfico desaprovechada.

---

## 4. Nueva propuesta de arquitectura web optimizada para SEO

Reorganización sobre las **7 secciones** solicitadas, bilingüe **ES (raíz) / EN (`/en/`)**, con URLs limpias, hreflang completo y silos temáticos claros.

### Mapa de la nueva arquitectura

```
INICIO  /                                   EN  /en/
│
├── QUIÉNES SOMOS            /quienes-somos/            → /en/about-us/
│      ├── (Responsabilidad corporativa puede colgar aquí o ser sección propia)
│
├── PROYECTOS               /proyectos/                 → /en/projects/
│      ├── landing proyecto  /proyectos/{slug}/         → /en/projects/{slug}/
│      │     (Amiral, El Paraíso, Gran Sol, La Merced…)
│      └── (unidades/propiedades como bloques dentro de cada landing)
│
├── ACTUALIDAD              /actualidad/                → /en/news/
│      └── entrada noticia   /actualidad/{slug}/        → /en/news/{slug}/
│            (curación: enlaces a noticias externas del sector)
│
├── BLOG                    /blog/                      → /en/blog/
│      └── post propio       /blog/{slug}/              → /en/blog/{slug}/
│            (contenido editorial propio, SEO long-tail)
│
├── DESTINO: COSTA BLANCA   /destino-costa-blanca/      → /en/costa-blanca/
│      └── artículo destino  /destino-costa-blanca/{slug}/  → /en/costa-blanca/{slug}/
│            (texto + imagen sobre la Costa Blanca, guía turística/de estilo de vida)
│
├── RESPONSABILIDAD          /responsabilidad-corporativa/  → /en/corporate-responsibility/
│      CORPORATIVA
│
└── CONTACTO                /contacto/                   → /en/contact/
       └── (legales en footer: aviso legal, privacidad, cookies)
```

### Estructura por secciones (ES ↔ EN)

| Sección | URL ES | URL EN | Tipo | Objetivo SEO |
|---|---|---|---|---|
| Inicio | `/` | `/en/` | Página | Marca + captación, enlaza a los silos |
| Quiénes somos | `/quienes-somos/` | `/en/about-us/` | Página | Confianza, E-E-A-T, marca "ZOROM promotora Costa Blanca" |
| Proyectos | `/proyectos/` | `/en/projects/` | Archivo | Hub comercial; long-tail "villas obra nueva Calpe/Costa Blanca" |
| — Landing proyecto | `/proyectos/{slug}/` | `/en/projects/{slug}/` | CPT | Página de conversión por promoción |
| Actualidad | `/actualidad/` | `/en/news/` | Blog (curación) | Frescura, enlaces salientes de autoridad |
| Blog | `/blog/` | `/en/blog/` | Blog (propio) | Tráfico informacional long-tail → embudo |
| Destino Costa Blanca | `/destino-costa-blanca/` | `/en/costa-blanca/` | Blog temático | Captación turística/inversora; "vivir en la Costa Blanca" |
| Responsabilidad corporativa | `/responsabilidad-corporativa/` | `/en/corporate-responsibility/` | Página | Marca, ESG, diferenciación |
| Contacto | `/contacto/` | `/en/contact/` | Página | Conversión (lead) |

### Principios SEO aplicados en la propuesta

1. **hreflang obligatorio y recíproco** en todas las páginas:
   ```html
   <link rel="alternate" hreflang="es" href="https://www.zorom.es/quienes-somos/">
   <link rel="alternate" hreflang="en" href="https://www.zorom.es/en/about-us/">
   <link rel="alternate" hreflang="x-default" href="https://www.zorom.es/quienes-somos/">
   ```
   Esto resuelve el problema #2 (el más importante para un sitio bilingüe).

2. **URLs limpias, sin ID numérico**: `/proyectos/gran-sol/` en vez de `/project/gran-sol-villas/114/`. Mantener **301** desde las antiguas para no perder posicionamiento.

3. **Slugs idénticos en concepto entre idiomas** y coherentes (`/contacto/` ↔ `/en/contact/`), traducidos donde aporta ("about-us", "news", "blog", "costa-blanca").

4. **Tres silos de contenido** (Actualidad / Blog / Destino) claramente separados por intención de búsqueda:
   - *Actualidad* = noticias curadas (frescura + autoridad saliente).
   - *Blog* = contenido propio (posicionamiento informacional).
   - *Destino* = contenido de estilo de vida sobre la Costa Blanca (captación turística/inversora, muy vinculable a los proyectos).
   Enlazar cruzado: cada artículo de *Destino* enlaza al proyecto relevante y viceversa → refuerza el silo comercial.

5. **Cada sección con H1 único** (corrige #3), meta-description propia (corrige #4) y breadcrumbs con datos estructurados (`BreadcrumbList`).

6. **`noindex`** en wishlist, resultados de búsqueda y páginas funcionales.

7. **Sitemap limpio**: solo URLs 200 reales, sin las 48 taxonomías 404 (corrige #1). Idealmente un sitemap por tipo (páginas, proyectos, actualidad, blog, destino) dentro del índice.

8. **Datos estructurados**: `Organization` + `RealEstateAgent` en home, `Product`/`Residence` en landings de proyecto, `Article` en blog/actualidad/destino, `BreadcrumbList` global.

### Plan de migración recomendado (orden)

1. **Limpiar el sitemap** (quitar las 48 404) — impacto inmediato, riesgo cero.
2. **Implementar hreflang** en todo el sitio.
3. **Añadir H1 y meta-descriptions** que faltan.
4. **Crear las 3 secciones de contenido** (Actualidad, Blog, Destino) y Responsabilidad Corporativa.
5. **Migrar URLs a slugs limpios** con redirecciones **301** desde las antiguas (proyectos y propiedades).
6. **Unificar slugs ES/EN** de proyectos y pasar los 302 a 301.
7. Revisar `noindex` de páginas funcionales y añadir datos estructurados.

---

## Ficheros entregados

- `informe_enlaces_zorom.xlsx` — informe de enlaces completo (4 hojas coloreadas: Resumen, 404 y errores, Redirecciones, Todas las URLs).
- `informe_zorom.md` — este documento (los 4 entregables).
- `zorom_data.json` — datos brutos del rastreo (status, títulos, hreflang, enlaces) por si quieres reutilizarlos.
- `check_sitemap.py` / `crawl_zorom.py` — scripts usados (reutilizables para reauditar).
