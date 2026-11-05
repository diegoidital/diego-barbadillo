# Vulnerabilidades conocidas de WordPress y versión segura por rama

Esta tabla se usa para decidir, en cada informe, si hay que avisar al
cliente de que su versión actual de WordPress tiene una vulnerabilidad
de seguridad conocida y cuál es la versión mínima a la que debe
actualizar dentro de su misma rama.

## Cómo usar esta tabla

1. Identifica la rama (major.minor) de la versión actual del sitio.
   Ej: si la versión actual es `7.0.4`, la rama es `7.0`. Si es `6.4.2`,
   la rama es `6.4`.
2. Busca esa rama en la tabla de abajo.
3. Si la rama aparece en la tabla, el sitio pertenece a una rama con una
   vulnerabilidad de seguridad conocida (CVE-2026-65640) — **incluye
   siempre** el bloque de aviso en el informe, dentro del bloque de
   Seguridad. Hay dos redacciones posibles según el caso (ver plantilla
   más abajo):
   - Si la versión actual es **anterior** a la versión segura de su
     rama: el sitio está afectado ahora mismo. Usa la redacción de
     "pendiente de corregir" y márcalo como prioridad alta.
   - Si la versión actual es **igual o posterior** a la versión segura
     de su rama: el sitio ya está parcheado frente a esta
     vulnerabilidad concreta. Usa igualmente el aviso, pero con la
     redacción de "ya corregido" — es información relevante para el
     cliente (por qué actualizar más allá de esta versión sigue
     importando) aunque no sea urgente.
4. Si la rama del sitio no aparece en la tabla (por ser más antigua que
   4.7 o más reciente que la última fila), no apliques este aviso
   concreto — sigue el resto del informe con normalidad.

## Tabla de ramas y versión segura

| Rama WordPress | Versión segura (con el parche aplicado) |
|---|---|
| 7.0 | 7.0.4 |
| 6.9 | 6.9.7 |
| 6.8 | 6.8.8 |
| 6.7 | 6.7.7 |
| 6.6 | 6.6.7 |
| 6.5 | 6.5.10 |
| 6.4 | 6.4.10 |
| 6.3 | 6.3.10 |
| 6.2 | 6.2.11 |
| 6.1 | 6.1.12 |
| 6.0 | 6.0.14 |
| 5.9 | 5.9.16 |
| 5.8 | 5.8.15 |
| 5.7 | 5.7.17 |
| 5.6 | 5.6.19 |
| 5.5 | 5.5.20 |
| 5.4 | 5.4.21 |
| 5.3 | 5.3.23 |
| 5.2 | 5.2.26 |
| 5.1 | 5.1.24 |
| 5.0 | 5.0.27 |
| 4.9 | 4.9.31 |
| 4.8 | 4.8.30 |
| 4.7 | 4.7.35 |

## Vulnerabilidad de referencia (CVE-2026-65640)

- **Severidad:** Alta.
- **Tipo de impacto:** Ejecución remota de código (RCE).
- **Descripción:** Ghostscript no maneja de forma segura ciertos
  archivos incrustados. Un atacante con la capacidad `upload_files`
  podría subir un archivo Postscript malicioso, lo que puede resultar
  en ejecución remota de código.
- **Solución:** Actualizar a la versión segura de la tabla anterior
  correspondiente a la rama del sitio (o, preferiblemente, a la última
  versión disponible de WordPress si el mantenimiento del sitio lo
  permite).

## Plantilla de texto para el aviso

Incluye siempre, en el bloque de Seguridad, un recuadro de aviso (mismo
estilo visual que el resto de avisos del informe: fondo rojo suave,
borde izquierdo grueso en rojo) cuando la rama del sitio esté en la
tabla. Adapta `{RAMA}`, `{VERSION_ACTUAL}` y `{VERSION_SEGURA}` a cada
caso, y elige la redacción según si el sitio ya está parcheado o no.

**Caso A — pendiente de corregir** (versión actual anterior a la
versión segura):

> ⚠ Aviso importante: vulnerabilidad de seguridad en WordPress {RAMA}
>
> WordPress ha publicado una vulnerabilidad de severidad alta que, de
> ser explotada, podría permitir a un atacante la ejecución remota de
> código. Afecta a las versiones de WordPress {RAMA} anteriores a la
> {VERSION_SEGURA}.
>
> **Vulnerabilidad detectada:**
> - **CVE-2026-65640:** Ghostscript no maneja de forma segura ciertos
>   archivos incrustados. Un atacante con la capacidad de subir
>   archivos podría subir un archivo Postscript malicioso, lo que
>   puede resultar en ejecución remota de código.
>
> *El sitio se encuentra actualmente en la versión {VERSION_ACTUAL},
> anterior a la versión segura {VERSION_SEGURA} de su rama, por lo que
> recomendamos priorizar esta actualización en el mantenimiento.*

**Caso B — ya corregido** (versión actual igual o posterior a la
versión segura, pero la rama tuvo la vulnerabilidad):

> ⚠ Aviso de seguridad: vulnerabilidad ya corregida en WordPress {RAMA}
>
> La rama {RAMA} de WordPress sufrió una vulnerabilidad de severidad
> alta que podía permitir a un atacante la ejecución remota de código,
> solucionada a partir de la versión {VERSION_SEGURA}.
>
> **Vulnerabilidad:**
> - **CVE-2026-65640:** Ghostscript no maneja de forma segura ciertos
>   archivos incrustados. Un atacante con la capacidad de subir
>   archivos podría subir un archivo Postscript malicioso, lo que
>   puede resultar en ejecución remota de código.
>
> *El sitio ya se encuentra en la versión {VERSION_ACTUAL}, que
> incluye la corrección de esta vulnerabilidad. Aun así, recomendamos
> continuar con la actualización a la última versión disponible de
> WordPress como buena práctica de seguridad continuada.*

En el caso B, no marques la actualización como "prioridad alta" en las
notas finales del informe (ya está resuelto) — basta con mencionar que
se sigue recomendando mantenerse actualizado.

Añade también, en la sección "Fuentes" (crearla si no existe), una
entrada con la fuente de este aviso si el usuario ha aportado un
enlace o boletín de origen (CERT, INCIBE, fabricante, etc.). Si no se
ha aportado ninguna fuente concreta en la conversación, no inventes
enlaces — omite las fuentes de este aviso en particular en vez de
fabricarlas.

## Caso especial: rama 7.0

Además de CVE-2026-65640, la rama 7.0 tuvo, en versiones anteriores a
la 7.0.4, dos vulnerabilidades adicionales de RCE e inyección SQL sin
autenticación en la API REST (CVE-2026-63030 y CVE-2026-60137). Si el
usuario ha proporcionado esos datos en la conversación (o los aporta de
nuevo), inclúyelos como vulnerabilidades adicionales dentro del mismo
recuadro de aviso cuando el sitio esté en la rama 7.0 anterior a 7.0.4.
Si no se han aportado, no los inventes — usa solo CVE-2026-65640.

---

## Uso específico en la skill `informe-incidente-seguridad`

El informe de actuación de incidentes (a diferencia del informe de
mantenimiento) no usa el recuadro rojo "⚠ Aviso importante" de más
arriba, sino un recuadro ámbar más breve dentro del resumen ejecutivo,
con tres redacciones posibles según cuánto conste en la fuente sobre
la versión de WordPress del sitio. `assets/template_informe.js` ya
genera el texto exacto de cada modo a partir de
`DATA.wordpress.estado` — esta tabla es solo para decidir qué modo
corresponde en cada caso:

| `DATA.wordpress.estado` | Cuándo usarlo | Qué exige de la fuente |
|---|---|---|
| `"confirmado"` | La fuente da la versión exacta de WordPress del sitio y esa versión está en el rango de la tabla de arriba. | `wpVersionActual` (obligatorio) y `wpActualizadoA` (si además ya se actualizó). |
| `"condiciones"` | La fuente no da la versión exacta, pero sí confirma que el servidor/sitio reunía las condiciones técnicas (p. ej. Imagick y Ghostscript activos). | Nada más — no inventar versión. |
| `"pendiente"` | No hay ningún dato sobre la versión de WordPress en la fuente. | Nada — el propio texto generado pide confirmar el dato. |
| `"ninguno"` | El incidente no tiene relación con WordPress, o no procede mencionar este aviso. | — |

Regla general: ante la duda entre dos modos, usa siempre el más
conservador (el que menos afirma) — es preferible pedir confirmar un
dato a afirmar una versión o una causalidad que no consta en la
fuente.

