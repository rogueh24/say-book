"""Petit client de l'API REST LatePoint de say-book.com (/wp-json/latepoint/v1/).

Clé : C:\\Users\\rogue\\Claude\\wp-sites.json, entrée "saybook", champ LATEPOINT_KEY (jamais affichée).
Authentification : en-tête « Authorization: Bearer <clé> ». Le pare-feu du serveur refuse les
requêtes sans User-Agent de navigateur, d'où l'en-tête ci-dessous.

Usage :
  python tools/lp.py test
  python tools/lp.py get services
  python tools/lp.py get bookings "per_page=20&status=approved"
  python tools/lp.py post <route> fichier.json      (création / modification : uniquement sur demande)
Routes : bookings, customers, orders, payments, services, service-categories, agents,
agents/availability, locations, availability, availability/check, coupons, system.
"""
import json
import sys
from pathlib import Path

import requests

SITES = Path(r"C:\Users\rogue\Claude\wp-sites.json")
UA = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155 Safari/537.36 say-book-lp/1.0"


def session():
    site = json.loads(SITES.read_text(encoding="utf-8"))["saybook"]
    s = requests.Session()
    s.headers.update({"Authorization": "Bearer " + site["LATEPOINT_KEY"], "User-Agent": UA})
    return site["URL"].rstrip("/") + "/wp-json/latepoint/v1/", s


def main(argv):
    if not argv:
        print(__doc__)
        return 1
    base, s = session()
    cmd = argv[0].lower()
    if cmd == "test":
        r = s.get(base + "system", timeout=30)
        if r.ok:
            d = r.json()
            print("OK, connecté. LatePoint", d.get("plugin_version"), "|", d.get("timezone"))
            return 0
        print("Échec", r.status_code, r.text[:300])
        return 1
    path = argv[1]
    if cmd == "get":
        query = argv[2] if len(argv) > 2 else ""
        r = s.get(base + path + ("?" + query if query else ""), timeout=60)
    elif cmd in ("post", "put", "patch"):
        data = json.loads(Path(argv[2]).read_text(encoding="utf-8"))
        r = s.request(cmd.upper(), base + path, json=data, timeout=60)
    else:
        print("Commande inconnue :", cmd)
        return 1
    try:
        print(json.dumps(r.json(), ensure_ascii=False, indent=1))
    except ValueError:
        print(r.text[:1000])
    return 0 if r.ok else 1


if __name__ == "__main__":
    sys.stdout.reconfigure(encoding="utf-8")
    sys.exit(main(sys.argv[1:]))
