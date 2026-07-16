# Auditoría estratégica SEO · GEO · Copy · UX

**Cliente:** Idital
**Página analizada:** Landing "Posicionamiento SEO" — `idital.com/servicio/agencia-posicionamiento-seo/`
**Fecha:** 16 de julio de 2026
**Metodología:** análisis sobre el contenido y estructura HTML públicos de la URL, el listado de encabezados exportado, y la captura de metadatos (title, description, robots, canonical, Core Web Vitals, recuento de headings/links/imágenes). Las métricas de rendimiento (LCP, INP, CLS) proceden de la extensión de medición y se recomienda contrastarlas con PageSpeed Insights y Search Console antes de priorizar cambios técnicos.

### Premisas del análisis (fijadas por el cliente)

1. **Landing genérica, sin posicionamiento local.** No se persigue geolocalización: la página compite por el término nacional "posicionamiento SEO / agencia SEO", no por búsquedas con ciudad. Por tanto **no se recomienda añadir topónimos** (Valencia) al title, H1 ni encabezados, y el marcado `LocalBusiness` queda fuera del alcance.
2. **No se publica precio.** No se dará ninguna cifra ni rango de tarifas. La objeción de precio no se resuelve con un número, sino **reencuadrando la respuesta** para que deje de sonar evasiva y canalice hacia la auditoría gratuita.

Estas dos premisas condicionan las recomendaciones de todo el documento: la diferenciación se apoya en los **activos de autoridad** (15 años, +500 clientes, +100.000 keywords, +3.000 webs, caso 200→8.000) y en la **segmentación por tipo de negocio** (pyme / servicios / ecommerce), no en la ubicación ni en el precio.

---

## Nota importante sobre el punto de partida

Esta auditoría **no parte de cero**: la landing ya ha incorporado buena parte de las recomendaciones de la auditoría anterior. Antes de listar problemas, conviene reconocer lo que **ya está resuelto**, porque cambia radicalmente dónde está ahora el techo de mejora.

**Ya implementado (respecto al informe previo):**

- ✅ **Beneficios segmentados por audiencia** — los tres bloques ahora hablan explícitamente a *pyme local*, *servicios* y *ecommerce*.
- ✅ **FAQ reescrito en primera persona de marca** — las 8 respuestas empiezan por "En Idital…" con ejemplos propios (hub pages, afiliación, WordPress/Plesk, RankMath). Mucho más citables.
- ✅ **Menciones de prensa con contexto textual** — cada medio (Que.es, Marketing Directo, Siglo XXI, El Confidencial Digital) lleva ahora una frase de qué dijo sobre Idital.
- ✅ **FAQ adelantado** — ya no está al final; aparece en la parte alta-media de la página.
- ✅ **Enlazado interno a servicios** — "Ver auditoría SEO →", "Ver consultoría SEO →", "Ver SEO local →" enlazan al clúster temático.
- ✅ **Subcopy del hero afilado** — "Auditoría, contenido y arquitectura web pensados para pymes y ecommerce… no promesas vacías de «ser el número 1»".

El trabajo pendiente es de **coherencia y remate**, no de reconstrucción. Y aparece un problema nuevo derivado de la mejora parcial: **el cuerpo de la landing evolucionó, pero el `<title>`, la meta description y el titular del hero se quedaron atrás**, generando una contradicción visible.

---

## Revisión de seguimiento (16 jul 2026)

Segunda pasada sobre la página en vivo (HTML crudo, `<head>` incluido) para verificar qué recomendaciones se han implementado y medir la mejora.

**Puntuación global: 68 → 73/100.** Se han aplicado los cambios de **cuerpo/contenido**, pero **no los de metadatos** (title, description, og:title), que eran los quick wins de mayor visibilidad en la SERP. El patrón sugiere que se editó el contenido (Elementor) sin tocar los campos SEO del plugin (RankMath/Yoast).

| Elemento | Antes | Ahora | Estado |
|---|---|---|:--:|
| **H1** | "Posicionamiento SEO ¡Consigue ser el 1 en Google!" | "Agencia de posicionamiento SEO para pymes y ecommerce" | ✅ Implementado (exacto) |
| **FAQ P8 (precio)** | Evasivo, sin salida | Reencuadrado hacia auditoría gratuita | ✅ Implementado |
| **Alt text imágenes** | 0 con alt | ~13 de 17 `<img>` con alt | ✅ Casi completo |
| **Enlace "SEO para IA y ChatGPT"** | Inexistente en el cuerpo | Añadido | ✅ Implementado |
| **`<title>`** | "…¡Consigue ser el 1 en Google!" | *Sin cambios* | ❌ Pendiente |
| **Meta description** | Genérica | *Sin cambios* | ❌ Pendiente |
| **og:title / schema WebPage** | "…el 1 en Google" | *Sin cambios* | ❌ Pendiente |
| **CTAs unificados** | 6 variantes | *Sin cambios* (siguen "¡EMPECEMOS!", "Pide tu presupuesto", "¿HABLAMOS?") | ❌ Pendiente |
| **"Para qué sirve" → bullets** | Párrafo largo | *Sin cambios* | ❌ Pendiente |
| **Historia 200→8.000** | 3 apariciones | 3 apariciones | ❌ Pendiente |
| **JSON-LD FAQPage + Service** | Ausente | Ausente (hay Organization/WebSite/Breadcrumb, pero no FAQPage ni Service) | ❌ Pendiente |
| **LCP 4,9 s** | Rojo | No verificable por HTML — medir en PageSpeed | ⏳ Sin datos |

### Matiz importante sobre la contradicción

La contradicción "ser el 1 en Google" **ya no está en el cuerpo visible**: el hero (H1 + subcopy) es ahora coherente y honesto. Las 4 apariciones que quedan de esa frase están **solo en metadatos** (`<title>`, `og:title`, `name` del schema WebPage y un campo oculto). El problema, por tanto, se ha desplazado: el usuario ya no ve la contradicción dentro de la página, **pero el snippet de Google seguirá mostrando "¡Consigue ser el 1 en Google!"** y contradirá a la propia landing en cuanto el usuario entre. Sigue siendo el quick win nº1 pendiente.

### Hallazgos nuevos de esta pasada

- **Typo en un CTA:** el botón enlaza correctamente a SEO para IA, pero el texto dice *"SEO par IA →"* (falta la "a" de "para").
- **Alt text mejorable:** ~4 imágenes siguen sin alt (una con `alt=""`) y algunos alt son débiles o duplicados ("logo que", "Posicionamiento seo", "ACE Posicionamiento SEO"). Conviene completarlos y hacerlos descriptivos.

### Prioridad inmediata restante

1. **Actualizar `<title>`, meta description y `og:title`** en el plugin SEO (es el cambio pendiente de mayor impacto y menor esfuerzo — ver textos en el anexo).
2. **Corregir el typo** "SEO par IA → SEO para IA".
3. **Unificar CTAs** y **convertir "Para qué sirve" en bullets** (textos en el anexo).
4. **Implementar JSON-LD `FAQPage` + `Service`.**
5. **Medir LCP** en PageSpeed Insights y optimizar imágenes si sigue > 2,5 s.

---

## 01 · Resumen ejecutivo

**Puntuación global: 68/100** — una landing que ya ha subido de nivel en contenido y segmentación, pero con tres frenos que le impiden convertir esa base en resultados: una **incoherencia de mensaje** entre el titular y el propio cuerpo, un **problema técnico de rendimiento** (LCP 4,9 s), y una **respuesta de precio que suena evasiva**.

| Capa | Antes | Ahora | Tendencia |
|------|:-----:|:-----:|:---------:|
| SEO  | 6/10  | **7/10** | ↗ |
| GEO  | 5/10  | **7/10** | ↗ |
| Copy | 6/10  | **7/10** | ↗ |
| UX   | 6/10  | **6,5/10** | ↗ |

La landing dispone de activos de autoridad reales y poco frecuentes en el sector (+500 clientes, 15 años, 100.000 keywords posicionadas, +3.000 webs, un caso de 200→8.000 visitas/mes) y de un FAQ ya reescrito con voz de marca. El problema ya no es de contenido, ni de orden general —que ha mejorado—, sino de tres cosas concretas: **rematar la coherencia del mensaje principal, reencuadrar la respuesta de precio, y arreglar el rendimiento y la semántica técnica**.

### Top 3 fortalezas

1. **Cifras de autoridad reales y verificables** (500 clientes, 15 años, 100k keywords, 3.000 webs) + caso de éxito con cifra concreta.
2. **FAQ profundo y con voz de marca** — 8 preguntas en primera persona, material de primera para featured snippets y para ser citado por IA generativa.
3. **Beneficios segmentados y enlazado interno al clúster SEO** ya operativos.

### Top 3 problemas críticos

1. **Contradicción de mensaje:** el `<title>` y el titular del hero siguen prometiendo "¡Consigue ser el 1 en Google!" mientras el subcopy de la misma pantalla dice literalmente "no promesas vacías de «ser el número 1»". La página se desmiente a sí misma.
2. **LCP de 4,9 s** (rango rojo; el objetivo es < 2,5 s). Es el mayor riesgo técnico y afecta a SEO, UX y conversión, especialmente en móvil.
3. **La respuesta de precio suena a evasiva:** dado que no se publicará cifra (premisa del cliente), el problema no es la ausencia de precio, sino que la P8 se limita a decir "no aplicamos tarifas cerradas" sin ofrecer una salida. Hay que **reencuadrarla hacia la auditoría gratuita** para que deje de leerse como un "no te lo digo".

---

## 02 · Análisis SEO — 7/10

Buena base y mejoras reales en enlazado y encabezado, pero los metadatos y el rendimiento técnico lastran la puntuación. (Recordatorio de premisa: se compite por el término genérico, sin geolocalización.)

### Lo que ya funciona

- **H1 mejorado:** "Agencia Posicionamiento SEO" ya incluye *agencia* + keyword principal (antes era solo "Posicionamiento SEO").
- **Enlazado interno activo** hacia Auditoría, Consultoría y SEO Local — clúster temático conectado.
- **FAQ con 8 preguntas** bien estructuradas: base sólida para featured snippets y long-tail informativo.
- **Indexabilidad correcta:** `index, follow`, canonical autorreferencial correcto, directivas `max-image-preview:large` / `max-snippet:-1` en su sitio.

### Problemas pendientes

**1. El `<title>` no se actualizó con el resto de la página (crítico).**
El title sigue siendo *"Posicionamiento SEO ¡Consigue ser el 1 en Google!"* (49 caracteres): mantiene una promesa genérica que Google desaconseja garantizar y **no incluye diferenciador ni segmentación** (agencia, pymes/ecommerce, +500 clientes), pese a que el H1 ya se corrigió. Es el elemento de mayor peso on-page y el que se ve en la SERP; hoy no aporta ningún motivo de clic frente a la competencia.

**2. Meta description genérica y desconectada del nuevo posicionamiento.**
*"Campañas de posicionamiento SEO. Consigue tráfico de calidad hacia tu página web, personas interesadas en tus productos o servicios."* (132 caracteres). No incluye diferenciador (500 clientes / 15 años) ni los activos que ya están en la página. Desaprovecha el espacio de CTR en la SERP.

**3. LCP de 4,9 s (rojo).** Rendimiento pobre. Probables causas: imágenes del hero/ebook sin optimizar (se detectan recursos como el ebook 642×1024 y resultados 800×478), ausencia de `width/height` explícitos o lazy-load mal configurado. CLS 0,03 es bueno; INP sin datos.

**4. Alt text ausente en las 18 imágenes.** Pérdida de accesibilidad y de una señal SEO gratuita (imágenes de resultados, logos, ebook).

**5. Repetición literal de "Posicionamiento SEO" en H2** en lugar de cubrir variantes semánticas (SEO técnico, SEO para pymes, SEO para ecommerce, ROI del SEO, link building). Diversificar capta long-tail sin depender de topónimos.

**6. Uso semántico incorrecto de encabezados.** "+8.000" está marcado como `H2` (una cifra no es un encabezado), hay etiquetas de sección estilizadas como H2 ("- Beneficios", "- Inversión", "- Cómo trabajamos"…) que inflan a **15 H2** sin aportar valor de keyword, y la jerarquía se rompe en el pie (H5 "¡Estás en el sitio correcto!" bajo H4, "Política de cookies" como H4). También hay **texto de encabezado duplicado** ("SEO On Page" aparece dos veces).

**7. 270 enlaces en la página.** Menú y pie muy densos; conviene revisar que no diluyan el crawl ni el reparto de autoridad interna.

### Recomendaciones priorizadas (SEO)

1. **Reescribir el `<title>`** con diferenciador y segmentación (sin topónimo), quitando la promesa "el 1 en Google". Propuesta: *"Agencia de Posicionamiento SEO | Pymes y Ecommerce · Idital"* (≤ 60 car.).
2. **Reescribir la meta description** con diferenciador + CTA. Propuesta: *"Agencia de posicionamiento SEO con 15 años y +500 clientes. Auditoría, contenido y SEO técnico para pymes y ecommerce. Pide tu auditoría gratuita."* (≤ 155 car.).
3. **Optimizar el LCP:** comprimir/convertir a WebP el recurso del hero, definir `width/height`, precargar la imagen LCP y revisar lazy-load. Objetivo < 2,5 s.
4. **Añadir alt text descriptivo** a las 18 imágenes.
5. **Diversificar los H2** con variantes semánticas y limpiar los encabezados de etiqueta / la cifra usada como H2.
6. **Verificar e implementar datos estructurados** `FAQPage` y `Service` (ver sección GEO). `LocalBusiness` queda fuera de alcance por la premisa de landing genérica.

---

## 03 · Análisis GEO (posicionamiento en IA generativa) — 7/10

Gran salto respecto al informe anterior: el FAQ y la prensa ya son citables. Quedan tres huecos que impiden que un modelo cite a Idital como *la* fuente.

### Lo que ya funciona

- **FAQ en primera persona de marca:** cada respuesta ancla la definición a Idital y a su método ("En Idital no perseguimos backlinks a cualquier precio…"). Convierte definiciones de manual en material atribuible a la marca.
- **Prensa con contexto textual:** los medios ya no son solo logos; cada uno tiene una frase citable ("Marketing Directo analizó el uso de IA de Idital…").
- **Cifras concretas y verificables** repartidas por la página, justo el tipo de dato que priorizan los motores generativos.

### Problemas pendientes

**1. El hero no es citable.** Ante "¿qué agencia SEO recomiendas para una pyme / un ecommerce?", el titular ("¡Consigue ser el 1 en Google!") no ofrece nada extraíble: no dice qué es Idital, para quién trabaja ni qué la diferencia en la primera frase. El subcopy ya es bueno, pero el titular —lo primero que se indexa como entidad— sigue vacío. (La frase citable debe apoyarse en *qué* y *para quién*, no en *dónde*.)

**2. La respuesta de precio suena a evasiva.** "¿Cuánto cuesta el SEO?" es una de las consultas más habituales vía IA generativa. Dado que **no se publicará cifra** (premisa), no se puede aspirar a ser la fuente citada del *precio*; pero la respuesta actual, tal cual, tampoco aporta nada citable. Reencuadrarla —explicando **de qué depende** la inversión y **cómo se obtiene un presupuesto** (auditoría gratuita, sin compromiso)— da al modelo algo útil que atribuir a Idital, aunque no sea un número.

**3. Datos estructurados sin verificar.** Con 8 preguntas ya redactadas, la página es candidata ideal a **FAQPage** y **Service** en JSON-LD. Si no están implementados, se pierde la vía más directa de comprensión por parte de buscadores e IA. (`LocalBusiness` se descarta por la premisa de landing genérica.)

**4. Falta cerrar el clúster de IA.** Existe una página "SEO para IA y ChatGPT" en el menú; enlazarla desde el cuerpo reforzaría la señal de entidad experta en GEO.

### Recomendaciones priorizadas (GEO)

1. **Reescribir el titular del hero** para que en una frase quede clara la entidad: qué es, para quién y qué la diferencia. Ej.: *"Agencia de posicionamiento SEO para pymes y ecommerce, con +500 clientes en 15 años."*
2. **Reencuadrar la P8 (precio)** sin dar cifra: explicar de qué depende la inversión y canalizar a la auditoría gratuita como forma de obtener un presupuesto real (ver texto en el anexo).
3. **Implementar y verificar JSON-LD:** `FAQPage` (las 8 Q) y `Service`.
4. **Enlazar "SEO para IA y ChatGPT"** desde el cuerpo para cerrar el clúster GEO.

---

## 04 · Análisis Copy — 7/10

El copy ya está segmentado y orientado a beneficio. Lo que queda es una **contradicción de marca** y un par de bloques por afilar.

### Lo que ya funciona

- **Beneficios segmentados** por pyme local / servicios / ecommerce, en clave de beneficio (no característica).
- **Subcopy del hero excelente**, con diferenciador ("15 años", "+500 clientes") y una promesa honesta que se distancia del "número 1".

### Problemas pendientes

**1. Contradicción entre titular y subcopy (crítico).** El titular grita *"¡Consigue ser el 1 en Google!"* y tres líneas más abajo el propio subcopy promete *"no promesas vacías de «ser el número 1»"*. La página se contradice en la misma pantalla: es la peor señal de confianza posible y, además, es la promesa que Google desaconseja garantizar.

**2. La respuesta de precio suena a evasiva.** No se trata de dar un número (premisa del cliente), sino de que quien llega a la P8 no se vaya con la sensación de "no me lo quieren decir". La respuesta debe **reencuadrar**: explicar por qué el precio es a medida, qué lo determina, y ofrecer la auditoría gratuita como camino claro y sin fricción para conocerlo.

**3. Bloque explicativo con ritmo de blog.** "Para qué sirve el posicionamiento SEO" es un párrafo largo tipo artículo que rompe la lectura rápida esperable en una landing de conversión.

**4. Proliferación e inconsistencia de CTAs.** Conviven al menos seis textos distintos: "Pide tu auditoría SEO gratuita", "Ver casos de éxito", "Pide tu presupuesto", "¡EMPECEMOS!", "Descargar ebook gratis", "¿HABLAMOS?". Además, la oferta principal cambia según el bloque (auditoría gratuita vs. presupuesto), diluyendo la acción principal.

### Recomendaciones priorizadas (Copy)

1. **Eliminar la contradicción:** sustituir el titular "el 1 en Google" por una promesa anclada en el diferenciador real, coherente con el subcopy que ya existe.
2. **Reencuadrar la respuesta de precio** hacia la auditoría gratuita (sin cifra), apoyándose en el compromiso mínimo de un año que ya aparece en la P5.
3. **Convertir el bloque "Para qué sirve" en 3-4 bullets escaneables.**
4. **Unificar los CTAs** en un único verbo y una única oferta primaria (recomendado: "Pide tu auditoría SEO gratuita"), relegando el resto a secundario.

---

## 05 · Análisis UX — 6,5/10

El hero funciona y el orden general ya mejoró (FAQ adelantado, beneficios segmentados). Los frenos que quedan son de foco de conversión, redundancia y rendimiento.

### Lo que ya funciona

- **Hero resuelto sin scroll:** H1, CTA, sello "Top agencias SEO 2026", rating de estrellas y Google Partner visibles en primera pantalla.
- **FAQ ya no está enterrado** al final.
- **Enlaces a servicios** accesibles desde su bloque.

### Problemas pendientes

**1. Objetivos de conversión enfrentados.** Hoy compiten hasta tres peticiones sin jerarquía clara: *auditoría gratuita*, *presupuesto* y *descarga del ebook*. Ninguna está subordinada a las demás, lo que confunde la intención principal de la página.

**2. La historia "200 → 8.000 visitas" aparece tres veces** (testimonio de estrellas, título del caso de éxito y métrica destacada "+8.000"). La repetición diluye el impacto en lugar de reforzarlo; además "+8.000" está montado como encabezado.

**3. LCP 4,9 s** penaliza la percepción de velocidad, sobre todo en móvil: el usuario ve la pantalla incompleta demasiado tiempo antes de poder interactuar.

**4. Jerarquía de encabezados rota** en la zona de testimonios/contacto/pie (H4 → H5 mal anidados, "Política de cookies" como H4), lo que también afecta a la navegación con lectores de pantalla.

**5. Menú y pie muy densos** (270 enlaces) pueden sobrecargar la decisión en móvil.

### Recomendaciones priorizadas (UX)

1. **Definir un único objetivo de conversión principal** (auditoría gratuita) y relegar el ebook a conversión secundaria, sin competir visualmente.
2. **Consolidar la historia 200→8.000** en un solo bloque de caso de éxito potente, evitando la triple repetición.
3. **Priorizar el arreglo del LCP** como mejora de experiencia percibida.
4. **Corregir la jerarquía de encabezados** de la mitad inferior.

---

## 06 · Propuesta de reorganización de la landing

El orden general ya es bastante correcto tras las mejoras. La propuesta consolida lo que hay y corrige las fricciones que quedan (foco de conversión, redundancia del caso, coherencia del mensaje), respetando las dos premisas (sin topónimo, sin precio). De arriba a abajo:

| # | Bloque | Qué cambia | Por qué (capa) |
|---|--------|-----------|----------------|
| 1 | **Hero** | Nuevo titular coherente y citable: *"Agencia de posicionamiento SEO para pymes y ecommerce, con +500 clientes en 15 años"*. CTA **único**: "Pide tu auditoría SEO gratuita". Mantener subcopy actual. | Resuelve la contradicción (Copy), da entidad en la línea 1 (GEO) y refuerza keyword + segmentación (SEO). |
| 2 | **Barra de prueba social** | Fusionar en una sola fila: 500 clientes · 15 años · 100.000 keywords en Top · 3.000 webs. Justo tras el hero. | Sube la autoridad (GEO) y elimina redundancia (UX). |
| 3 | **Beneficios segmentados** | Mantener tal cual (pyme / servicios / ecommerce). | Ya resuelto; buena resonancia (Copy). |
| 4 | **Servicios + enlaces internos** | Mantener las 4 tarjetas con sus enlaces; añadir enlace a "SEO para IA y ChatGPT". | Cierra clúster temático (SEO/GEO). |
| 5 | **FAQ** | Mantener adelantado. **Reencuadrar la P8 (precio)** hacia la auditoría gratuita, sin cifra. Añadir JSON-LD `FAQPage`. | Frena el abandono por objeción; bloque más citable (GEO/Copy). |
| 6 | **Proceso en 6 pasos** | Copy más corto por paso. | Refuerza confianza (UX/Copy). |
| 7 | **"Para qué sirve"** | Convertir el párrafo largo en 3-4 bullets. | Skimmability (Copy/UX). |
| 8 | **Prensa con contexto** | Mantener (ya tiene frases citables). Añadir alt text a los logos. | Texto citable por IA (GEO/SEO). |
| 9 | **Caso de éxito + testimonios** | **Unificar** la historia 200→8.000 en un solo bloque; corregir la jerarquía de encabezados. Enlazar al portfolio. | Elimina la triple repetición (UX). |
| 10 | **Ebook (conversión secundaria)** | Mantener formulario, etiquetado como "recurso adicional", sin competir con el CTA principal. | Evita el conflicto entre objetivos (UX). |
| 11 | **Contacto** | Mantener datos de contacto (NAP, WhatsApp, horario) como cierre. Sin explotarlos como señal de SEO local (premisa). | Cierre natural de conversión. |

---

## 07 · Plan de acción priorizado

### 🟢 Quick wins (impacto inmediato, coste bajo)

1. **Reescribir el `<title>`** con diferenciador + segmentación, quitando "el 1 en Google".
2. **Reescribir la meta description** alineada con el nuevo posicionamiento.
3. **Cambiar el titular del hero** para eliminar la contradicción con el subcopy.
4. **Reencuadrar la P8 (precio)** hacia la auditoría gratuita, sin dar cifra.
5. **Añadir alt text** a las 18 imágenes.
6. **Unificar el texto de los CTAs** y definir la oferta principal única.

### 🟡 Medio plazo (esfuerzo moderado)

7. **Optimizar el LCP** (imágenes WebP + `width/height` + preload de la imagen LCP; objetivo < 2,5 s).
8. **Convertir "Para qué sirve"** en 3-4 bullets escaneables.
9. **Consolidar la historia 200→8.000** en un único bloque de caso de éxito.
10. **Enlazar "SEO para IA y ChatGPT"** y reforzar variantes semánticas en los H2.
11. **Añadir la barra de prueba social fusionada** tras el hero.

### 🔴 Estructural (mayor esfuerzo / diseño)

12. **Definir un único objetivo de conversión** (auditoría gratuita) y relegar el ebook a secundario.
13. **Implementar y verificar JSON-LD** (`FAQPage` + `Service`).
14. **Corregir la semántica de encabezados** (quitar la cifra como H2, arreglar H4/H5 del pie, eliminar duplicados) y revisar la densidad de enlaces (270).

---

## 08 · Conclusión y próximos pasos

La landing ha dado un salto real desde la auditoría anterior: el contenido ya está segmentado, el FAQ tiene voz de marca, la prensa aporta contexto y el clúster interno está enlazado. El techo de mejora ya **no está en producir contenido**, sino en **rematar tres cosas concretas** (dentro de las premisas de landing genérica y sin precio):

1. **Coherencia de mensaje** — que el titular deje de contradecir a su propio subcopy y pase a ser una frase citable y diferenciada por autoridad y segmentación (no por ubicación).
2. **Reencuadrar el precio** — que la respuesta deje de sonar evasiva y canalice hacia la auditoría gratuita, sin publicar cifra.
3. **Rendimiento y semántica técnica** — bajar el LCP de 4,9 s y limpiar la jerarquía de encabezados y los metadatos.

### Siguiente paso propuesto

1. Ejecutar los **quick wins** en la próxima ventana de mantenimiento (title, description, titular, P8, alt text, CTAs): son cambios de texto, de riesgo bajo e impacto inmediato en CTR y coherencia.
2. Abordar en paralelo el **LCP** con el equipo técnico (es lo único que no es texto).
3. Medir a las **4-6 semanas** en Search Console (impresiones, CTR, posición media para "agencia posicionamiento SEO" y variantes) y en la tasa de conversión del formulario, antes de acometer los cambios estructurales.

---

## Anexo · Textos finales listos para pegar

**`<title>` (59 car.):**
`Agencia de Posicionamiento SEO | Pymes y Ecommerce · Idital`

**Meta description (145 car.):**
`Agencia de posicionamiento SEO con 15 años y +500 clientes. Auditoría, contenido y SEO técnico para pymes y ecommerce. Pide tu auditoría gratuita.`

**H1:**
`Agencia de posicionamiento SEO para pymes y ecommerce`

**Subtitular del hero:**
`15 años posicionando en Google a más de 500 clientes. Auditoría, contenido y arquitectura web para conseguir tráfico orgánico de calidad, no promesas vacías de «ser el número 1».`

**FAQ · P8 "¿Cuánto cuesta el SEO con Idital?" (reencuadre sin cifra):**
`En Idital no trabajamos con tarifas cerradas de catálogo, y hay un motivo: el coste depende de lo que necesite cada proyecto (estado técnico de partida, autoridad del dominio, volumen de contenido y competencia del sector). Un paquete estándar te haría pagar por lo que no necesitas o quedarte corto en lo que sí. Por eso empezamos siempre con una auditoría gratuita y sin compromiso: analizamos tu punto de partida real y te presentamos un presupuesto ajustado y un plan de trabajo concreto. Trabajamos con un compromiso mínimo de un año, que es el tiempo que necesitamos para consolidar autoridad y ver resultados sostenibles.`

**CTAs unificados:** principal → `Pide tu auditoría SEO gratuita` · secundario (ebook) → `Descargar ebook gratis`. Se eliminan "¡EMPECEMOS!", "¿HABLAMOS?" y "Pide tu presupuesto" como variantes sueltas.

**Bloque "Para qué sirve" en bullets:**
```
Con la estrategia SEO adecuada conseguimos que tu negocio:
• Aparezca en las primeras posiciones para las palabras clave que mueven tu sector.
• Reciba tráfico orgánico de calidad: usuarios con intención real de compra, no curiosos.
• Convierta más, dirigiendo ese tráfico hacia donde interesa a tu negocio.
• Crezca de forma sostenible, adaptándonos a cada actualización de Google.
No es un camino fácil, pero merece la pena: nuestro equipo se encarga de toda la estrategia para que tú solo veas los resultados.
```
