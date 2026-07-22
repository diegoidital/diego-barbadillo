#!/usr/bin/env python3
"""Construye el Excel de enlaces (4 hojas, con colores) desde zorom_data.json,
sin hacer peticiones nuevas."""
import json
from openpyxl import Workbook
from openpyxl.styles import Font, PatternFill, Alignment

d = json.load(open("zorom_data.json"))

GREEN = PatternFill("solid", fgColor="C6EFCE")
YELLOW = PatternFill("solid", fgColor="FFEB9C")
RED = PatternFill("solid", fgColor="FFC7CE")
HDR = PatternFill("solid", fgColor="305496")
HDRF = Font(color="FFFFFF", bold=True)

def categoria(r):
    st = str(r.get("status"))
    if st == "200":
        return "OK", GREEN
    if st in ("301", "302", "307", "308"):
        return "Redirección existente", YELLOW
    if st == "404":
        return "404 / No encontrada", RED
    if st.startswith("4"):
        return "Error cliente (4xx)", RED
    if st.startswith("5"):
        return "Error servidor (5xx)", RED
    return "Error de comprobación", RED

for r in d:
    r["_cat"], r["_fill"] = categoria(r)

wb = Workbook()

# --- Resumen ---
ws = wb.active
ws.title = "Resumen"
from collections import Counter
cnt = Counter(r["_cat"] for r in d)
ws.append(["Categoría", "Nº de URLs"])
for c in ws[1]:
    c.fill = HDR; c.font = HDRF
for cat, n in sorted(cnt.items(), key=lambda x: -x[1]):
    ws.append([cat, n])
ws.append(["TOTAL", len(d)])
ws.column_dimensions["A"].width = 30
ws.column_dimensions["B"].width = 14

def sheet(title, rows):
    w = wb.create_sheet(title)
    cols = ["URL", "Status", "Categoría", "Destino / Redirección", "Título", "Nota"]
    w.append(cols)
    for c in w[1]:
        c.fill = HDR; c.font = HDRF
    for r in rows:
        dest = r.get("location") or r.get("final_url") or ""
        w.append([r["url"], str(r.get("status")), r["_cat"], dest,
                  (r.get("title") or "")[:90], r.get("error", "")])
        for c in w[w.max_row]:
            c.fill = r["_fill"]
            c.alignment = Alignment(vertical="top", wrap_text=True)
    w.column_dimensions["A"].width = 55
    w.column_dimensions["B"].width = 8
    w.column_dimensions["C"].width = 22
    w.column_dimensions["D"].width = 45
    w.column_dimensions["E"].width = 40
    w.freeze_panes = "A2"

sheet("404 y errores", [r for r in d if r["_cat"] not in ("OK", "Redirección existente")])
sheet("Redirecciones", [r for r in d if r["_cat"] == "Redirección existente"])
sheet("Todas las URLs", d)

wb.save("informe_enlaces_zorom.xlsx")
print("OK -> informe_enlaces_zorom.xlsx")
