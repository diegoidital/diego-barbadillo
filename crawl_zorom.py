#!/usr/bin/env python3
"""Crawler lento y respetuoso para zorom.es.
Recoge: URLs del sitemap, status de cada una, enlaces internos (para arbol),
y metadatos basicos (title, h1, meta description, hreflang) para arquitectura/SEO.
Guarda todo en un JSON para analisis posterior.
"""
import json, time, re, sys
from urllib.parse import urljoin, urlparse
import requests
from xml.etree import ElementTree as ET

BASE = "https://www.zorom.es"
UA = "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
HDRS = {"User-Agent": UA, "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
        "Accept-Language": "es-ES,es;q=0.9,en;q=0.8"}
TIMEOUT = 25
DELAY = 1.2  # pausa entre peticiones (lento a proposito)

s = requests.Session()
s.headers.update(HDRS)

def get(url, allow_redirects=True):
    return s.get(url, timeout=TIMEOUT, allow_redirects=allow_redirects)

def sitemap_urls(idx):
    """Recorre sitemap index recursivamente y devuelve lista de (loc, lastmod)."""
    out = []
    r = get(idx)
    time.sleep(DELAY)
    root = ET.fromstring(r.content)
    ns = {"sm": "http://www.sitemaps.org/schemas/sitemap/0.9"}
    subs = root.findall(".//sm:sitemap/sm:loc", ns)
    if subs:
        for loc in subs:
            print("  sub-sitemap:", loc.text, file=sys.stderr)
            out += sitemap_urls(loc.text.strip())
    else:
        for u in root.findall(".//sm:url", ns):
            loc = u.find("sm:loc", ns)
            lm = u.find("sm:lastmod", ns)
            out.append((loc.text.strip() if loc is not None else "",
                        lm.text.strip() if lm is not None else ""))
    return out

def meta(html):
    def find(pat):
        m = re.search(pat, html, re.I | re.S)
        return re.sub(r"\s+", " ", m.group(1)).strip() if m else ""
    title = find(r"<title[^>]*>(.*?)</title>")
    h1 = find(r"<h1[^>]*>(.*?)</h1>")
    h1 = re.sub(r"<[^>]+>", "", h1)
    desc = find(r'<meta[^>]+name=["\']description["\'][^>]+content=["\'](.*?)["\']')
    # hreflang
    hl = re.findall(r'<link[^>]+rel=["\']alternate["\'][^>]+hreflang=["\']([^"\']+)["\'][^>]+href=["\']([^"\']+)["\']', html, re.I)
    # nav / menu links
    return title, h1, desc, hl

def internal_links(html, base_url):
    links = re.findall(r'<a\b[^>]*href=["\']([^"\']+)["\']', html, re.I)
    out = set()
    for l in links:
        if l.startswith(("mailto:", "tel:", "#", "javascript:")):
            continue
        full = urljoin(base_url, l)
        p = urlparse(full)
        if p.netloc.replace("www.", "") == "zorom.es":
            out.add(full.split("#")[0].rstrip("/") or full)
    return sorted(out)

def main():
    print("Leyendo sitemap...", file=sys.stderr)
    urls = sitemap_urls(BASE + "/sitemap_index.xml")
    # dedup
    seen = {}
    for loc, lm in urls:
        if loc and loc not in seen:
            seen[loc] = lm
    urls = list(seen.items())
    print(f"Total URLs en sitemap: {len(urls)}", file=sys.stderr)

    results = []
    for i, (loc, lm) in enumerate(urls, 1):
        rec = {"url": loc, "lastmod": lm}
        try:
            # sin redirect primero para detectar cadenas
            r0 = get(loc, allow_redirects=False)
            rec["status"] = r0.status_code
            rec["location"] = r0.headers.get("Location", "")
            time.sleep(DELAY)
            if r0.status_code == 200 and "text/html" in r0.headers.get("Content-Type", ""):
                html = r0.text
                t, h1, d, hl = meta(html)
                rec.update({"title": t, "h1": h1, "desc": d, "hreflang": hl,
                            "links": internal_links(html, loc),
                            "html_len": len(html)})
            elif r0.is_redirect:
                # seguir cadena
                rf = get(loc, allow_redirects=True)
                rec["final_url"] = rf.url
                rec["final_status"] = rf.status_code
                time.sleep(DELAY)
        except Exception as e:
            rec["status"] = "ERR"
            rec["error"] = f"{type(e).__name__}: {str(e)[:80]}"
        results.append(rec)
        print(f"  [{i}/{len(urls)}] {rec.get('status')} {loc}", file=sys.stderr)

    with open("zorom_data.json", "w") as f:
        json.dump(results, f, ensure_ascii=False, indent=2)
    print(f"Guardado zorom_data.json ({len(results)} URLs)", file=sys.stderr)

if __name__ == "__main__":
    main()
