"""Petit client de l'API REST WordPress de say-book.com (mêmes identifiants que woo.py).

Usage :
  python tools/wp.py get plugins/say-book-rdv-pause/say-book-rdv-pause
  python tools/wp.py post plugins/say-book-rdv-pause/say-book-rdv-pause '{"status":"inactive"}'
  python tools/wp.py post <route> fichier.json
La route est relative à /wp-json/wp/v2/. Rien n'est jamais affiché des identifiants.
"""
import json
import sys
from pathlib import Path

from woo import session as woo_session

API = "/wp-json/wp/v2/"


def main(argv):
    if len(argv) < 2:
        print(__doc__)
        return 1
    wc_base, s = woo_session()
    base = wc_base.split("/wp-json/")[0] + API
    cmd, path = argv[0].lower(), argv[1]
    if cmd == "get":
        r = s.get(base + path, timeout=60)
    elif cmd in ("post", "put"):
        raw = argv[2]
        data = json.loads(raw) if raw.lstrip().startswith("{") else json.loads(Path(raw).read_text(encoding="utf-8"))
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
