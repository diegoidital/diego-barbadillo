"""Vigila páginas web públicas y envía un email cuando cambian.

Para cada página de paginas.yml:
  1. Descarga la página.
  2. Extrae el texto de la zona indicada (selector CSS) y lo normaliza.
  3. Compara su hash con el de la última instantánea guardada en snapshots/.
  4. Si ha cambiado, guarda la nueva instantánea y añade la página al aviso.
Al final envía un único email con todos los cambios (con el diff de texto).

La primera vez que se vigila una página solo se guarda la instantánea, sin avisar.

Configuración por variables de entorno (secrets en GitHub):
  SMTP_HOST, SMTP_PORT (465 = SSL, otro = STARTTLS), SMTP_USER, SMTP_PASS,
  MAIL_FROM (por defecto SMTP_USER), MAIL_TO (varios separados por comas).
"""

import difflib
import hashlib
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


def comprobar(pagina):
    """Devuelve el diff si la página ha cambiado, o None si no."""
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

    fichero = SNAPSHOTS / f"{nombre}.txt"
    anterior = fichero.read_text(encoding="utf-8") if fichero.exists() else None
    if anterior is not None and _hash(anterior) == _hash(texto):
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
    return "\n".join(diff)


def _hash(texto):
    return hashlib.sha256(texto.encode("utf-8")).hexdigest()


def enviar_email(cambios):
    host = os.environ.get("SMTP_HOST")
    destinatarios = os.environ.get("MAIL_TO")
    if not host or not destinatarios:
        print("Faltan SMTP_HOST o MAIL_TO: no se envía email.", file=sys.stderr)
        return False

    usuario = os.environ.get("SMTP_USER", "")
    puerto = int(os.environ.get("SMTP_PORT") or 465)

    msg = EmailMessage()
    nombres = ", ".join(p["nombre"] for p, _ in cambios)
    msg["Subject"] = f"[Vigilancia web] Cambios en: {nombres}"
    msg["From"] = os.environ.get("MAIL_FROM") or usuario
    msg["To"] = destinatarios
    cuerpo = []
    for pagina, diff in cambios:
        cuerpo.append(f"=== {pagina['nombre']} ===\n{pagina['url']}\n\n{diff}\n")
    msg.set_content("\n".join(cuerpo))

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


def main():
    SNAPSHOTS.mkdir(exist_ok=True)
    paginas = yaml.safe_load(CONFIG.read_text(encoding="utf-8"))["paginas"]

    cambios, errores = [], []
    for pagina in paginas:
        try:
            diff = comprobar(pagina)
            if diff is not None:
                cambios.append((pagina, diff))
        except Exception as e:  # una página caída no debe parar las demás
            print(f"[{pagina.get('nombre')}] ERROR: {e}", file=sys.stderr)
            errores.append(pagina.get("nombre"))

    if cambios and not enviar_email(cambios):
        return 1
    # Los errores de descarga se registran en el log pero no hacen fallar la
    # ejecución, para no recibir avisos de GitHub por caídas puntuales.
    return 0


if __name__ == "__main__":
    sys.exit(main())
