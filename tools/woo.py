"""Petit client de l'API WooCommerce de say-book.com.

Identifiants lus dans C:\\Users\\rogue\\Claude\\wp-sites.json, entrée "saybook" :
  - soit CK / CS (clé API WooCommerce, ck_... / cs_...)
  - soit USER / PASS (mot de passe d'application WordPress, marche aussi pour WooCommerce)
Rien n'est jamais affiché ni écrit ailleurs.

Usage :
  python tools/woo.py test
  python tools/woo.py get products "per_page=20&status=any"
  python tools/woo.py post products produit.json
  python tools/woo.py put products/123 modif.json
  python tools/woo.py delete products/123          (va dans la corbeille, pas de suppression définitive)
"""
import json
import sys
from pathlib import Path

import requests

SITES = Path(r"C:\Users\rogue\Claude\wp-sites.json")
API = "/wp-json/wc/v3/"


def session():
    site = json.loads(SITES.read_text(encoding="utf-8"))["saybook"]
    s = requests.Session()
    ck, cs = site.get("CK", ""), site.get("CS", "")
    if ck.startswith("ck_") and cs.startswith("cs_"):
        s.auth = (ck, cs)
    else:
        s.auth = (site["USER"], site["PASS"])
    s.headers["User-Agent"] = "say-book-woo/1.0"
    return site["URL"].rstrip("/") + API, s


def main(argv):
    if not argv:
        print(__doc__)
        return 1
    base, s = session()
    cmd = argv[0].lower()
    if cmd == "test":
        r = s.get(base + "system_status", timeout=30)
        if r.ok:
            env = r.json().get("environment", {})
            print("OK, connecté. WooCommerce", env.get("version"), "| WordPress", env.get("wp_version"))
            return 0
        print("Échec", r.status_code, r.text[:300])
        return 1

    path = argv[1]
    if cmd == "get":
        query = argv[2] if len(argv) > 2 else ""
        r = s.get(base + path + ("?" + query if query else ""), timeout=60)
    elif cmd in ("post", "put"):
        data = json.loads(Path(argv[2]).read_text(encoding="utf-8"))
        r = s.request(cmd.upper(), base + path, json=data, timeout=60)
    elif cmd == "delete":
        r = s.delete(base + path, timeout=60)  # sans force=true : corbeille
    else:
        print("Commande inconnue :", cmd)
        return 1

    if "x-wp-total" in r.headers:
        print("# total :", r.headers["x-wp-total"], file=sys.stderr)
    try:
        print(json.dumps(r.json(), ensure_ascii=False, indent=1))
    except ValueError:
        print(r.text[:1000])
    return 0 if r.ok else 1


if __name__ == "__main__":
    sys.stdout.reconfigure(encoding="utf-8")
    sys.exit(main(sys.argv[1:]))
