"""Vigila páginas web públicas y envía un email cuando cambian.

Para cada página de paginas.yml:
  1. Descarga la página.
  2. Extrae el texto de la zona indicada (selector CSS) y lo normaliza.
  3. Compara su hash con el de la última instantánea guardada en snapshots/.
  4. Si ha cambiado, guarda la nueva instantánea y añade la página al aviso.
  5. Con `pdfs: true`, descarga además los PDF enlazados y compara su hash, de
     modo que detecta que sustituyan el PDF aunque el enlace no cambie.
Al final envía un único email con todos los cambios (con el diff de texto y,
adjuntos, los PDF nuevos o modificados).

La primera vez que se vigila una página solo se guarda la instantánea, sin avisar.

Configuración por variables de entorno (secrets en GitHub):
  SMTP_HOST, SMTP_PORT (465 = SSL, otro = STARTTLS), SMTP_USER, SMTP_PASS,
  MAIL_FROM (por defecto SMTP_USER), MAIL_TO (varios separados por comas).
"""

import difflib
import hashlib
import json
import os
import re
import smtplib
import sys
from email.message import EmailMessage
from pathlib import Path
from urllib.parse import urljoin

import requests
import yaml
from bs4 import BeautifulSoup

BASE = Path(__file__).resolve().parent
CONFIG = BASE / "paginas.yml"
SNAPSHOTS = BASE / "snapshots"
USER_AGENT = (
    "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 "
    "(KHTML, like Gecko) Chrome/124.0 Safari/537.36 vigilancia-web"
)
MAX_LINEAS_DIFF = 200
MAX_ADJUNTOS = 15 * 1024 * 1024  # bytes; los PDF que no quepan se avisan con su enlace


def extraer_texto(html, selector, ignorar, enlaces=False, url=""):
    soup = BeautifulSoup(html, "html.parser")
    for etiqueta in soup(["script", "style", "noscript", "template"]):
        etiqueta.decompose()

    nodos = soup.select(selector) if selector else [soup.body or soup]
    if not nodos:
        raise ValueError(f"el selector '{selector}' no encuentra ningún elemento")

    if enlaces:
        # Añade la dirección de cada enlace a su texto, para detectar también
        # que se cambie el fichero enlazado aunque el texto siga igual.
        for nodo in nodos:
            anclas = [nodo] if nodo.name == "a" else nodo.select("a[href]")
            for a in anclas:
                if a.get("href"):
                    a.append(f" [{urljoin(url, a['href'])}]")

    patrones = [re.compile(p) for p in ignorar]
    lineas = []
    for nodo in nodos:
        for linea in nodo.get_text("\n").splitlines():
            linea = " ".join(linea.split())
            if linea and not any(p.search(linea) for p in patrones):
                lineas.append(linea)
    return "\n".join(lineas) + "\n"


def urls_enlaces(html, selector, url):
    """Direcciones absolutas (sin repetir) de los enlaces de la zona vigilada."""
    soup = BeautifulSoup(html, "html.parser")
    nodos = soup.select(selector) if selector else [soup.body or soup]
    urls = []
    for nodo in nodos:
        for a in [nodo] if nodo.name == "a" else nodo.select("a[href]"):
            if a.get("href"):
                absoluta = urljoin(url, a["href"])
                if absoluta not in urls:
                    urls.append(absoluta)
    return urls


def descargar_pdf(url):
    """Devuelve (nombre de fichero, contenido) si la URL es un PDF, o None si no lo es."""
    resp = requests.get(url, headers={"User-Agent": USER_AGENT}, timeout=60)
    resp.raise_for_status()
    if not resp.content.startswith(b"%PDF"):
        return None
    m = re.search(r'filename\*?=(?:UTF-8\'\')?"?([^";]+)"?', resp.headers.get("Content-Disposition", ""))
    nombre = m.group(1).strip() if m else url.rstrip("/").rsplit("/", 1)[-1]
    if not nombre.lower().endswith(".pdf"):
        nombre += ".pdf"
    return nombre, resp.content


def comprobar_pdfs(nombre, urls):
    """Compara los PDF enlazados con sus hashes guardados.

    Devuelve la lista de PDF nuevos o modificados como (fichero, contenido, motivo).
    La primera vez solo guarda los hashes, sin avisar.
    """
    fichero = SNAPSHOTS / f"{nombre}.pdfs.json"
    anteriores = json.loads(fichero.read_text(encoding="utf-8")) if fichero.exists() else None
    actuales, cambiados = {}, []
    for url in urls:
        try:
            pdf = descargar_pdf(url)
        except Exception as e:  # si falla, se conserva el hash anterior y se reintenta
            print(f"[{nombre}] ERROR al descargar {url}: {e}", file=sys.stderr)
            if anteriores and url in anteriores:
                actuales[url] = anteriores[url]
            continue
        if pdf is None:
            continue
        fichero_pdf, contenido = pdf
        actuales[url] = {"fichero": fichero_pdf, "sha256": hashlib.sha256(contenido).hexdigest()}
        if anteriores is None:
            continue
        previo = anteriores.get(url)
        if previo is None:
            cambiados.append((fichero_pdf, contenido, "PDF nuevo"))
        elif previo["sha256"] != actuales[url]["sha256"]:
            cambiados.append((fichero_pdf, contenido, "PDF modificado"))

    if actuales != anteriores:
        fichero.write_text(json.dumps(actuales, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    if anteriores is None:
        print(f"[{nombre}] primeros hashes de PDF guardados (no se avisa)")
    for fichero_pdf, _, motivo in cambiados:
        print(f"[{nombre}] {motivo}: {fichero_pdf}")
    return cambiados


def comprobar(pagina):
    """Devuelve (diff, PDF cambiados) si la página ha cambiado, o None si no."""
    nombre = pagina["nombre"]
    resp = requests.get(
        pagina["url"], headers={"User-Agent": USER_AGENT}, timeout=30
    )
    resp.raise_for_status()
    texto = extraer_texto(
        resp.text,
        pagina.get("selector"),
        pagina.get("ignorar") or [],
        enlaces=pagina.get("enlaces", False),
        url=resp.url,
    )
    pdfs = []
    if pagina.get("pdfs"):
        pdfs = comprobar_pdfs(nombre, urls_enlaces(resp.text, pagina.get("selector"), resp.url))

    fichero = SNAPSHOTS / f"{nombre}.txt"
    anterior = fichero.read_text(encoding="utf-8") if fichero.exists() else None
    if anterior is not None and _hash(anterior) == _hash(texto):
        if pdfs:
            return "(el texto y los enlaces de la página no han cambiado)", pdfs
        print(f"[{nombre}] sin cambios")
        return None

    fichero.write_text(texto, encoding="utf-8")
    if anterior is None:
        print(f"[{nombre}] primera instantánea guardada (no se avisa)")
        return None

    print(f"[{nombre}] HA CAMBIADO")
    diff = list(
        difflib.unified_diff(
            anterior.splitlines(),
            texto.splitlines(),
            fromfile="antes",
            tofile="ahora",
            lineterm="",
        )
    )
    if len(diff) > MAX_LINEAS_DIFF:
        diff = diff[:MAX_LINEAS_DIFF] + [f"... ({len(diff) - MAX_LINEAS_DIFF} líneas más)"]
    return "\n".join(diff), pdfs


def _hash(texto):
    return hashlib.sha256(texto.encode("utf-8")).hexdigest()


def enviar_email(asunto, cuerpo, adjuntos=()):
    host = os.environ.get("SMTP_HOST")
    destinatarios = os.environ.get("MAIL_TO")
    if not host or not destinatarios:
        print("Faltan SMTP_HOST o MAIL_TO: no se envía email.", file=sys.stderr)
        return False

    usuario = os.environ.get("SMTP_USER", "")
    puerto = int(os.environ.get("SMTP_PORT") or 465)

    msg = EmailMessage()
    msg["Subject"] = asunto
    msg["From"] = os.environ.get("MAIL_FROM") or usuario
    msg["To"] = destinatarios
    msg.set_content(cuerpo)
    for fichero_pdf, contenido in adjuntos:
        msg.add_attachment(contenido, maintype="application", subtype="pdf", filename=fichero_pdf)

    if puerto == 465:
        servidor = smtplib.SMTP_SSL(host, puerto, timeout=30)
    else:
        servidor = smtplib.SMTP(host, puerto, timeout=30)
        servidor.starttls()
    with servidor:
        if usuario:
            servidor.login(usuario, os.environ.get("SMTP_PASS", ""))
        servidor.send_message(msg)
    print(f"Email enviado a {destinatarios}")
    return True


def cuerpo_prueba(paginas):
    """Email de prueba: confirma que el envío funciona y muestra lo vigilado."""
    partes = [
        "Este es un email de prueba de la vigilancia web.",
        "Si lo recibes, el envío de avisos funciona correctamente.",
        "",
        "Contenido vigilado actualmente:",
        "",
    ]
    for pagina in paginas:
        fichero = SNAPSHOTS / f"{pagina['nombre']}.txt"
        texto = fichero.read_text(encoding="utf-8") if fichero.exists() else "(sin instantánea)\n"
        partes.append(f"=== {pagina['nombre']} ===\n{pagina['url']}\n\n{texto}")
    return "\n".join(partes)


def main():
    SNAPSHOTS.mkdir(exist_ok=True)
    paginas = yaml.safe_load(CONFIG.read_text(encoding="utf-8"))["paginas"]

    cambios, errores = [], []
    for pagina in paginas:
        try:
            cambio = comprobar(pagina)
            if cambio is not None:
                cambios.append((pagina, *cambio))
        except Exception as e:  # una página caída no debe parar las demás
            print(f"[{pagina.get('nombre')}] ERROR: {e}", file=sys.stderr)
            errores.append(pagina.get("nombre"))

    if cambios:
        nombres = ", ".join(p["nombre"] for p, _, _ in cambios)
        partes, adjuntos, tamano = [], [], 0
        for p, diff, pdfs in cambios:
            partes.append(f"=== {p['nombre']} ===\n{p['url']}\n\n{diff}\n")
            for fichero_pdf, contenido, motivo in pdfs:
                if tamano + len(contenido) <= MAX_ADJUNTOS:
                    adjuntos.append((fichero_pdf, contenido))
                    tamano += len(contenido)
                    partes.append(f"{motivo}: {fichero_pdf} (adjunto)")
                else:
                    partes.append(f"{motivo}: {fichero_pdf} (demasiado grande para adjuntar; descárgalo de la página)")
            partes.append("")
        if not enviar_email(f"[Vigilancia web] Cambios en: {nombres}", "\n".join(partes), adjuntos):
            return 1

    if os.environ.get("EMAIL_PRUEBA") == "true":
        if not enviar_email("[Vigilancia web] Email de prueba", cuerpo_prueba(paginas)):
            return 1
    # Los errores de descarga se registran en el log pero no hacen fallar la
    # ejecución, para no recibir avisos de GitHub por caídas puntuales.
    return 0


if __name__ == "__main__":
    sys.exit(main())
