#!/usr/bin/env python3
"""
check_sitemap.py

Lee un sitemap.xml (o sitemap index con varios sub-sitemaps), comprueba el
estado HTTP de cada URL, detecta 404/errores y redirecciones existentes
(siguiendo toda la cadena hasta el destino final), y exporta todo a un
archivo Excel legible.

Uso:
    python check_sitemap.py <sitemap_url_o_ruta> [salida.xlsx] [opciones]

Ejemplos:
    python check_sitemap.py https://midominio.com/sitemap.xml
    python check_sitemap.py https://midominio.com/sitemap_index.xml informe.xlsx --workers 15
    python check_sitemap.py ./sitemap.xml salida.xlsx --delay 0.2 --timeout 15

Requisitos: requests, openpyxl  (pip install requests openpyxl --break-system-packages)
"""

import argparse
import concurrent.futures
import sys
import time
import xml.etree.ElementTree as ET
from datetime import datetime
from urllib.parse import urlparse

import requests
from openpyxl import Workbook
from openpyxl.styles import Font, PatternFill, Alignment
from openpyxl.utils import get_column_letter
from openpyxl.worksheet.table import Table, TableStyleInfo

NS = {"sm": "http://www.sitemaps.org/schemas/sitemap/0.9"}
USER_AGENT = "Mozilla/5.0 (compatible; SitemapChecker/1.0; +https://idital.com)"
MAX_REDIRECT_HOPS = 10


# --------------------------------------------------------------------------- #
# 1. Lectura y parseo del sitemap (soporta sitemap index anidado)
# --------------------------------------------------------------------------- #

def fetch_xml(source, session, timeout):
    """Descarga o lee en local el contenido XML de un sitemap."""
    parsed = urlparse(source)
    if parsed.scheme in ("http", "https"):
        resp = session.get(source, timeout=timeout, headers={"User-Agent": USER_AGENT})
        resp.raise_for_status()
        return resp.content
    with open(source, "rb") as f:
        return f.read()


def extract_urls(xml_bytes):
    """Devuelve (lista_de_urls, lista_de_sub_sitemaps) a partir de un XML."""
    root = ET.fromstring(xml_bytes)
    tag = root.tag.lower()

    urls = []
    sub_sitemaps = []

    if tag.endswith("sitemapindex"):
        for sm in root.findall("sm:sitemap", NS):
            loc = sm.find("sm:loc", NS)
            if loc is not None and loc.text:
                sub_sitemaps.append(loc.text.strip())
    elif tag.endswith("urlset"):
        for url_el in root.findall("sm:url", NS):
            loc = url_el.find("sm:loc", NS)
            lastmod = url_el.find("sm:lastmod", NS)
            if loc is not None and loc.text:
                urls.append({
                    "url": loc.text.strip(),
                    "lastmod": lastmod.text.strip() if lastmod is not None and lastmod.text else "",
                })
    return urls, sub_sitemaps


def collect_all_urls(source, session, timeout, seen_sitemaps=None):
    """Recorre recursivamente sitemap index -> sub-sitemaps -> urls."""
    if seen_sitemaps is None:
        seen_sitemaps = set()
    if source in seen_sitemaps:
        return []
    seen_sitemaps.add(source)

    print(f"  Leyendo sitemap: {source}")
    xml_bytes = fetch_xml(source, session, timeout)
    urls, sub_sitemaps = extract_urls(xml_bytes)

    all_urls = list(urls)
    for sub in sub_sitemaps:
        all_urls.extend(collect_all_urls(sub, session, timeout, seen_sitemaps))
    return all_urls


# --------------------------------------------------------------------------- #
# 2. Comprobación de cada URL (sigue la cadena de redirecciones manualmente)
# --------------------------------------------------------------------------- #

def check_url(entry, timeout):
    """
    Comprueba una URL sin seguir redirecciones automáticamente, para poder
    reconstruir la cadena completa (origen -> redirect 1 -> redirect 2 -> final).
    """
    url = entry["url"]
    chain = []
    current = url
    session = requests.Session()

    try:
        for hop in range(MAX_REDIRECT_HOPS):
            resp = session.head(
                current, timeout=timeout, allow_redirects=False,
                headers={"User-Agent": USER_AGENT},
            )
            # Algunos servidores no soportan HEAD correctamente -> reintentar con GET
            if resp.status_code in (405, 501) or resp.status_code == 403:
                resp = session.get(
                    current, timeout=timeout, allow_redirects=False,
                    headers={"User-Agent": USER_AGENT}, stream=True,
                )

            chain.append((current, resp.status_code))

            if resp.is_redirect or (300 <= resp.status_code < 400):
                location = resp.headers.get("Location")
                if not location:
                    break
                # Resolver URLs relativas
                if location.startswith("/"):
                    parsed = urlparse(current)
                    location = f"{parsed.scheme}://{parsed.netloc}{location}"
                elif not location.startswith("http"):
                    parsed = urlparse(current)
                    base = f"{parsed.scheme}://{parsed.netloc}{parsed.path.rsplit('/', 1)[0]}/"
                    location = base + location
                current = location
                continue
            else:
                break

        final_url, final_status = chain[-1]
        first_status = chain[0][1]
        is_redirect = len(chain) > 1
        chain_str = " -> ".join(f"{u} [{s}]" for u, s in chain)

        if final_status == 404 or final_status == 410:
            category = "404 / No encontrada"
        elif final_status >= 500:
            category = "Error servidor (5xx)"
        elif final_status >= 400:
            category = "Error cliente (4xx)"
        elif is_redirect:
            category = "Redirección existente"
        elif final_status == 200:
            category = "OK"
        else:
            category = f"Otro ({final_status})"

        return {
            "url": url,
            "lastmod": entry.get("lastmod", ""),
            "status_inicial": first_status,
            "num_saltos": len(chain) - 1,
            "url_final": final_url if is_redirect else "",
            "status_final": final_status,
            "categoria": category,
            "cadena": chain_str if is_redirect else "",
            "error": "",
        }

    except requests.exceptions.Timeout:
        return _error_row(entry, "Timeout (sin respuesta)")
    except requests.exceptions.ConnectionError:
        return _error_row(entry, "Error de conexión")
    except requests.exceptions.RequestException as e:
        return _error_row(entry, f"Error: {e}")
    finally:
        session.close()


def _error_row(entry, msg):
    return {
        "url": entry["url"],
        "lastmod": entry.get("lastmod", ""),
        "status_inicial": "",
        "num_saltos": "",
        "url_final": "",
        "status_final": "",
        "categoria": "Error de comprobación",
        "cadena": "",
        "error": msg,
    }


def check_all_urls(entries, workers, timeout, delay):
    results = []
    total = len(entries)

    def worker(entry):
        if delay:
            time.sleep(delay)
        return check_url(entry, timeout)

    with concurrent.futures.ThreadPoolExecutor(max_workers=workers) as executor:
        futures = {executor.submit(worker, e): e for e in entries}
        done = 0
        for future in concurrent.futures.as_completed(futures):
            results.append(future.result())
            done += 1
            if done % 25 == 0 or done == total:
                print(f"  Comprobadas {done}/{total} URLs...")

    # Mantener el orden original del sitemap
    order = {e["url"]: i for i, e in enumerate(entries)}
    results.sort(key=lambda r: order.get(r["url"], 0))
    return results


# --------------------------------------------------------------------------- #
# 3. Exportación a Excel
# --------------------------------------------------------------------------- #

HEADERS = [
    "URL", "Última modificación", "Status inicial", "Nº de saltos",
    "URL final", "Status final", "Categoría", "Cadena de redirección", "Detalle del error",
]

FIELD_ORDER = [
    "url", "lastmod", "status_inicial", "num_saltos",
    "url_final", "status_final", "categoria", "cadena", "error",
]

COLOR_BY_CATEGORY = {
    "OK": "C6EFCE",
    "Redirección existente": "FFEB9C",
    "404 / No encontrada": "FFC7CE",
    "Error cliente (4xx)": "FFC7CE",
    "Error servidor (5xx)": "FFC7CE",
    "Error de comprobación": "D9D9D9",
}

FONT_COLOR_BY_CATEGORY = {
    "OK": "006100",
    "Redirección existente": "9C6500",
    "404 / No encontrada": "9C0006",
    "Error cliente (4xx)": "9C0006",
    "Error servidor (5xx)": "9C0006",
    "Error de comprobación": "3F3F3F",
}


def autosize_columns(ws, max_width=80):
    for col_cells in ws.columns:
        length = max((len(str(c.value)) for c in col_cells if c.value is not None), default=10)
        col_letter = get_column_letter(col_cells[0].column)
        ws.column_dimensions[col_letter].width = min(max(length + 2, 10), max_width)


def write_sheet(ws, rows):
    ws.append(HEADERS)
    header_fill = PatternFill("solid", fgColor="305496")
    for cell in ws[1]:
        cell.font = Font(bold=True, color="FFFFFF")
        cell.fill = header_fill
        cell.alignment = Alignment(vertical="center")
    ws.freeze_panes = "A2"

    for row in rows:
        values = [row.get(f, "") for f in FIELD_ORDER]
        ws.append(values)
        categoria = row.get("categoria", "")
        fill_color = COLOR_BY_CATEGORY.get(categoria)
        font_color = FONT_COLOR_BY_CATEGORY.get(categoria)
        if fill_color:
            r = ws.max_row
            for cell in ws[r]:
                cell.fill = PatternFill("solid", fgColor=fill_color)
                if font_color:
                    cell.font = Font(color=font_color)

    if ws.max_row > 1:
        last_col_letter = get_column_letter(len(HEADERS))
        table_ref = f"A1:{last_col_letter}{ws.max_row}"
        table_name = ws.title.replace(" ", "_")
        table = Table(displayName=table_name, ref=table_ref)
        table.tableStyleInfo = TableStyleInfo(
            name="TableStyleLight1", showRowStripes=False,
            showFirstColumn=False, showLastColumn=False,
        )
        ws.add_table(table)

    autosize_columns(ws)


def write_summary_sheet(ws, results, source, generated_at):
    ws.append(["Informe de comprobación de sitemap"])
    ws["A1"].font = Font(bold=True, size=14)
    ws.append(["Sitemap analizado", source])
    ws.append(["Fecha de generación", generated_at])
    ws.append(["Total de URLs", len(results)])
    ws.append([])
    ws.append(["Categoría", "Nº de URLs"])
    ws["A6"].font = Font(bold=True)
    ws["B6"].font = Font(bold=True)

    counts = {}
    for r in results:
        counts[r["categoria"]] = counts.get(r["categoria"], 0) + 1

    row_idx = 7
    for categoria, count in sorted(counts.items(), key=lambda x: -x[1]):
        ws.append([categoria, count])
        fill_color = COLOR_BY_CATEGORY.get(categoria)
        if fill_color:
            ws.cell(row=row_idx, column=1).fill = PatternFill("solid", fgColor=fill_color)
        row_idx += 1

    autosize_columns(ws)


def export_to_excel(results, source, output_path):
    wb = Workbook()
    generated_at = datetime.now().strftime("%Y-%m-%d %H:%M")

    summary_ws = wb.active
    summary_ws.title = "Resumen"
    write_summary_sheet(summary_ws, results, source, generated_at)

    problems = [r for r in results if r["categoria"] not in ("OK",)]
    errores_404 = [r for r in results if "404" in r["categoria"] or r["categoria"].startswith("Error")]
    redirecciones = [r for r in results if r["categoria"] == "Redirección existente"]

    ws_errores = wb.create_sheet("404 y errores")
    write_sheet(ws_errores, errores_404)

    ws_redirs = wb.create_sheet("Redirecciones")
    write_sheet(ws_redirs, redirecciones)

    ws_all = wb.create_sheet("Todas las URLs")
    write_sheet(ws_all, results)

    wb.save(output_path)


# --------------------------------------------------------------------------- #
# 4. CLI
# --------------------------------------------------------------------------- #

def main():
    parser = argparse.ArgumentParser(description="Comprueba un sitemap: 404, redirecciones y errores -> Excel")
    parser.add_argument("sitemap", help="URL o ruta local del sitemap.xml (o sitemap index)")
    parser.add_argument("output", nargs="?", default="informe_sitemap.xlsx", help="Ruta del Excel de salida")
    parser.add_argument("--workers", type=int, default=10, help="Peticiones en paralelo (por defecto 10)")
    parser.add_argument("--timeout", type=int, default=10, help="Timeout por petición en segundos (por defecto 10)")
    parser.add_argument("--delay", type=float, default=0.0, help="Retraso en segundos entre peticiones por hilo (útil para no saturar el servidor)")
    args = parser.parse_args()

    session = requests.Session()

    print(f"Paso 1/3: leyendo sitemap desde {args.sitemap}")
    entries = collect_all_urls(args.sitemap, session, args.timeout)
    # Eliminar duplicados manteniendo orden
    seen = set()
    unique_entries = []
    for e in entries:
        if e["url"] not in seen:
            seen.add(e["url"])
            unique_entries.append(e)
    entries = unique_entries
    print(f"  Total de URLs encontradas: {len(entries)}")

    if not entries:
        print("No se han encontrado URLs en el sitemap. Revisa la ruta/URL indicada.")
        sys.exit(1)

    print(f"Paso 2/3: comprobando {len(entries)} URLs (workers={args.workers}, timeout={args.timeout}s)")
    results = check_all_urls(entries, args.workers, args.timeout, args.delay)

    print(f"Paso 3/3: exportando a {args.output}")
    export_to_excel(results, args.sitemap, args.output)

    counts = {}
    for r in results:
        counts[r["categoria"]] = counts.get(r["categoria"], 0) + 1
    print("\nResumen:")
    for categoria, count in sorted(counts.items(), key=lambda x: -x[1]):
        print(f"  {categoria}: {count}")

    print(f"\nHecho. Informe guardado en: {args.output}")


if __name__ == "__main__":
    main()
