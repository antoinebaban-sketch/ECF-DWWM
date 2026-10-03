"""Rend les sources Mermaid (*.mmd) de ce dossier en PNG dans docs/diagrammes/.

    pip install playwright      (utilise Microsoft Edge déjà installé)
    DIR=LR php classes.php vue-ensemble > classes-vue-ensemble.mmd
    php classes.php commande     > classes-domaine-commande.mmd
    python rendu.py
"""
import pathlib
import sys

from playwright.sync_api import sync_playwright

ICI = pathlib.Path(__file__).parent
SORTIE = ICI.parent

PAGE = """<!doctype html><html><head><meta charset="utf-8">
<style>body{margin:0;background:#fff;font-family:'Segoe UI',Arial,sans-serif} #d{display:inline-block;padding:24px}</style>
<script src="https://cdn.jsdelivr.net/npm/mermaid@11/dist/mermaid.min.js"></script></head>
<body><div id="d"></div></body></html>"""

INIT = {
    "startOnLoad": False, "theme": "base", "maxTextSize": 200000, "securityLevel": "loose",
    "themeVariables": {"fontFamily": "Segoe UI, Arial, sans-serif", "fontSize": "15px",
                       "primaryColor": "#fbf7f2", "primaryBorderColor": "#5C1A1A", "primaryTextColor": "#1a1a1a",
                       "lineColor": "#5C1A1A", "secondaryColor": "#f3ece0", "tertiaryColor": "#ffffff",
                       "actorBkg": "#fbf7f2", "actorBorder": "#5C1A1A", "noteBkgColor": "#fff6dd"},
    "flowchart": {"curve": "basis", "nodeSpacing": 40, "rankSpacing": 55, "htmlLabels": True, "useMaxWidth": False},
    "class": {"hideEmptyMembersBox": True, "useMaxWidth": False},
    "sequence": {"mirrorActors": False, "messageAlign": "left", "useMaxWidth": False, "wrap": False},
}

fichiers = sys.argv[1:] or sorted(p.stem for p in ICI.glob("*.mmd"))
with sync_playwright() as pw:
    navigateur = pw.chromium.launch(channel="msedge", headless=True)
    page = navigateur.new_page(device_scale_factor=2, viewport={"width": 1600, "height": 1000})
    page.set_content(PAGE)
    page.wait_for_function("window.mermaid !== undefined")
    page.evaluate("cfg => mermaid.initialize(cfg)", INIT)
    for nom in fichiers:
        source = (ICI / f"{nom}.mmd").read_text(encoding="utf-8")
        page.evaluate("""async src => {
            const { svg } = await mermaid.render('g' + Date.now(), src);
            const d = document.getElementById('d'); d.innerHTML = svg;
            const s = d.querySelector('svg'); s.style.maxWidth = 'none';
            const vb = s.viewBox.baseVal;  // taille réelle du dessin, quel que soit le type de diagramme
            s.setAttribute('width', vb.width); s.setAttribute('height', vb.height);
        }""", source)
        page.locator("#d").screenshot(path=str(SORTIE / f"{nom}.png"))
        print("rendu :", nom)
    navigateur.close()
