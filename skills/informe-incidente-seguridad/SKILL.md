---
name: informe-incidente-seguridad
description: Genera, en .docx, el informe de actuación que Idital entrega al cliente tras resolver un incidente de seguridad en un sitio WordPress (malware, cuentas de administrador fraudulentas, puertas traseras, compromisos de credenciales, etc.). Usa esta skill siempre que el usuario pida un "informe de actuación", un "informe para el cliente" sobre un incidente de seguridad ya resuelto, o adjunte/pegue un informe técnico interno (de idital.com o de cualquier origen) sobre un ataque a un sitio WordPress pidiendo que se convierta en un documento entregable al cliente — aunque no mencione explícitamente "Word" o ".docx". También dispara si el usuario pide comprobar si la versión de WordPress de un sitio afectado está dentro del rango de la vulnerabilidad INCIBE-2026-552 (CVE-2026-65640) antes de redactar el informe.
---

# Informe de actuación — incidente de seguridad en WordPress (formato cliente)

Skill para generar, en `.docx`, el informe de actuación que Idital
entrega a sus clientes tras resolver un incidente de seguridad en un
sitio WordPress alojado en su servidor Plesk. Convierte un informe
técnico interno (a menudo denso, con IPs, nombres de archivo y
detalles forenses) en un documento en lenguaje claro, pensado para que
lo lea el cliente sin conocimientos técnicos, siguiendo el formato y
tono ya validados.

Antes de escribir cualquier código, lee también el skill `docx`
(`/mnt/skills/public/docx/SKILL.md`) para las gotchas de docx-js y el
proceso de verificación con `soffice`/`pdftoppm`.

## Flujo de trabajo

1. **Reúne los datos del incidente.** A partir del informe técnico
   interno que el usuario adjunte o pegue (o de lo que describa en la
   conversación), extrae:
   - Cliente (si no se conoce, dilo con el placeholder `[Nombre del
     cliente]` — no lo inventes) y dominio afectado.
   - Fecha y hora de detección, estado final, quién ha respondido
     (normalmente "Idital — administración de servidor").
   - La narrativa completa de lo ocurrido: qué se instaló/comprometió,
     qué hallazgos hubo, qué se hizo para remediarlo.
   Si falta algo imprescindible, pregúntalo — no lo inventes.

2. **Reescribe en lenguaje llano, sin tecnicismos.** El cliente no
   necesita saber qué es un drop-in de WordPress o un mu-plugin; sí
   necesita saber que había "un componente especial que se ejecutaba
   antes que el resto del sitio y reconstruía el problema". Traduce
   cada hallazgo y cada medida técnica a una frase que describa qué
   pasó y qué se hizo, no cómo funciona por dentro.

3. **Omite siempre los detalles de credenciales robadas/reales.** No
   menciones que el acceso se produjo mediante credenciales de
   administrador filtradas, reutilizadas o mediante fuerza bruta, ni
   IPs de origen, ni nombres de usuarios de administradores legítimos.
   Usa en su lugar una redacción neutra tipo "acceso no autorizado al
   panel de administración". Esto aplica en el resumen ejecutivo, la
   cronología y cualquier otra sección — es una política fija del
   cliente para estos informes, no una decisión caso por caso.

4. **Construye la cronología con el detalle que pide el cliente.**
   Cada fila de `DATA.cronologia` debe indicar explícitamente **qué se
   eliminó** en cada paso de la remediación (no agrupar "se limpió el
   sitio" en una única fila) y su **tipo**: `"Ataque"` (acción del
   atacante), `"Automático"` (detección/neutralización del propio
   sistema de seguridad, p. ej. Imunify360) o `"Manual"` (paso de
   remediación realizado por el equipo). Desglosa la fase de limpieza
   en tantas filas "Manual" como acciones distintas hubo.

5. **Decide el tratamiento de la versión de WordPress / aviso
   INCIBE-2026-552.** Rellena `DATA.wordpress.estado` según lo que
   conste explícitamente en la fuente — nunca inventes una versión ni
   afirmes que un sitio estaba en el rango afectado si no consta:
   - `"confirmado"`: la fuente indica la versión exacta de WordPress
     del sitio y esa versión está en el rango de
     `references/vulnerabilidades-wp.md`. Usa lenguaje directo.
   - `"condiciones"`: la fuente no da la versión exacta, pero sí dice
     que el servidor/sitio reunía las condiciones técnicas (p. ej.
     Imagick y Ghostscript activos). Usa lenguaje cauto.
   - `"pendiente"`: no hay ningún dato sobre la versión de WordPress.
     Pide confirmarlo en el propio informe en vez de inventarlo, y
     dilo también al usuario en tu respuesta.
   - `"ninguno"`: el incidente no tiene relación con WordPress o no
     procede mencionar este aviso.
   Consulta `references/vulnerabilidades-wp.md` para la tabla de
   ramas/versión segura y el texto de referencia del CVE.

6. **Si el sitio se ha actualizado de versión como parte de la
   remediación**, rellena `DATA.actualizacionDestacada = { de, a }`
   para que aparezca el recuadro verde destacado en el apartado 4. Si
   no se ha actualizado o no se conoce la versión, déjalo en `null` —
   no lo inventes.

7. **Genera el documento.** Copia
   `assets/template_informe.js` a un directorio de trabajo y rellena
   el objeto `DATA` con los datos del incidente (cada campo está
   comentado en el propio fichero). Ejecútalo con `node
   template_informe.js`. No construyas el documento desde cero — este
   script ya tiene la maquetación, colores y estructura validados.
   - No hace falta tocar `DATA.archivoSalida`: se calcula solo a
     partir de `DATA.dominio` con el formato
     `Informe_<dominio_sin_puntos>.docx` (p. ej. `coelival.com` →
     `Informe_coelival_com.docx`). Solo sobrescríbelo si el usuario
     pide explícitamente otro nombre.

8. **Verifica visualmente** el resultado con
   `soffice --headless --convert-to pdf` + `pdftoppm`, como en
   cualquier generación de docx, antes de darlo por bueno. Presta
   especial atención a que no haya quedado ningún dato de credenciales
   o IPs colado en el texto reescrito.

9. **Entrega el archivo** copiándolo a `/mnt/user-data/outputs/` y
   usando `present_files`.

## Estructura del informe (fija — no añadir ni quitar secciones)

1. Portada + tabla de cabecera: Cliente, Dominio afectado, Fecha de
   detección, Estado actual, Responsable de la respuesta.
2. **1. Resumen ejecutivo**: 1-2 párrafos en lenguaje claro +
   "Resumen en una frase". Si `DATA.wordpress.estado !== "ninguno"`,
   incluye aquí el recuadro ámbar con la nota sobre la versión de
   WordPress (ver punto 5 del flujo).
3. **2. Cronología de los hechos**: tabla con columnas Fecha/hora,
   Tipo (Ataque/Automático/Manual) y Qué ocurrió/qué se hizo — con el
   detalle de cada eliminación (ver punto 4 del flujo).
4. **3. Qué se encontró**: bullets en lenguaje sencillo de los
   hallazgos técnicos.
5. **4. Medidas aplicadas**: bullets de las acciones de remediación.
   Si aplica, el recuadro verde de "Sitio actualizado: WordPress X →
   Y" justo después de los bullets.
6. **5. Recomendaciones**: bullets orientados al cliente (contraseñas,
   2FA, backups, actualizaciones, vigilancia).
7. **6. Estado del incidente**: párrafo de cierre.
8. **7. La importancia de tener la web actualizada**: aviso
   INCIBE-2026-552/CVE-2026-65640 con tabla completa de versiones
   afectadas (omite esta sección solo si `DATA.incluirSeccionIncibe =
   false`).

## Tono

Dirigido al cliente final, no al equipo técnico: lenguaje llano, sin
jerga de WordPress interna (drop-ins, mu-plugins, hooks, cron, etc. se
explican por su función, no por su nombre técnico). El objetivo es que
el cliente entienda qué pasó, qué se hizo y qué debe hacer él a
partir de ahora, sin necesitar ayuda para interpretar el informe.

## Referencias

- `references/vulnerabilidades-wp.md` — tabla de ramas de WordPress
  vulnerables y versión segura correspondiente para INCIBE-2026-552 /
  CVE-2026-65640, y las tres redacciones de referencia (confirmado /
  condiciones técnicas / pendiente de confirmar) para la nota sobre
  versión de WordPress. Consúltalo siempre antes de rellenar
  `DATA.wordpress` y antes de construir la sección 7.

## Recursos

- `assets/template_informe.js` — script docx-js parametrizado (objeto
  `DATA` al principio, completamente comentado) que genera el
  documento final con el estilo ya validado. Cópialo y edítalo por
  cada informe; no lo modifiques dentro de la carpeta de la skill.
