"""Remise en route de la réservation après le blocage multipart de PlanetHoster.

  python tools/reprise_rdv.py            vérifie, et si c'est débloqué : désactive le plugin de pause,
                                         contrôle /rdv/ et une étape LatePoint ; en cas d'échec, réactive la pause.
  python tools/reprise_rdv.py --verifier  vérifie seulement, ne change rien.

Codes de sortie : 0 = réservation remise en route et contrôlée, 3 = toujours bloqué (rien changé),
4 = vérification seule : débloqué, 2 = échec après désactivation (pause réactivée), 1 = autre erreur (rien changé).
Aucun rendez-vous n'est créé : le test LatePoint charge seulement l'étape après le choix d'une prestation.
"""
import sys
import time

import requests

from woo import session as woo_session

SITE = "https://www.say-book.com"
PAUSE = "plugins/say-book-rdv-pause/say-book-rdv-pause"
UA = {"User-Agent": "say-book-reprise/1.0"}


def multipart_status():
    r = requests.post(SITE + "/wp-admin/admin-ajax.php", files={"action": (None, "foo_test")}, headers=UA, timeout=30)
    t = requests.post(SITE + "/wp-admin/admin-ajax.php", data={"action": "foo_test"}, headers=UA, timeout=30)
    return r.status_code, t.status_code


def etape_latepoint():
    """Charge l'étape qui suit le choix d'une prestation, en multipart comme le vrai formulaire."""
    item = '{"id":null,"customer_id":false,"agent_id":null,"location_id":"1","service_id":14,"total_attendees":1}'
    params = requests.models.RequestEncodingMixin._encode_params({
        "booking[service_id]": "14",
        "current_step_code": "booking__services",
        "step_direction": "next",
        "active_cart_item[variant]": "booking",
        "active_cart_item[item_data]": item,
        "timezone_name": "Europe/Paris",
        "restrictions[service_display_mode]": "all",
    })
    champs = {"params": params, "action": "latepoint_route_call", "route_name": "steps__load_step",
              "layout": "none", "return_format": "json"}
    r = requests.post(SITE + "/wp-admin/admin-ajax.php?t=%d" % time.time(),
                      files={k: (None, v) for k, v in champs.items()}, headers=UA, timeout=30)
    try:
        ok = r.status_code == 200 and r.json().get("status") == "success"
    except ValueError:
        ok = False
    return ok, r.status_code


def page_rdv_sans_pause():
    r = requests.get(SITE + "/rdv/?nocache=%d" % time.time(), headers=UA, timeout=30)
    return r.status_code == 200 and "sbrp-voile" not in r.text, r.status_code


def statut_pause(s, base):
    r = s.get(base + PAUSE, timeout=30)
    return r.json().get("status") if r.ok else "erreur %d" % r.status_code


def changer_pause(s, base, statut):
    r = s.post(base + PAUSE, json={"status": statut}, timeout=60)
    return r.ok and r.json().get("status") == statut


def main(argv):
    mp, temoin = multipart_status()
    print("Test multipart :", mp, "| témoin :", temoin)
    if mp == 403:
        print("Toujours bloqué par l'hébergeur, rien n'a été changé.")
        return 3
    if mp != temoin:
        print("Résultat inattendu, rien n'a été changé.")
        return 1
    if "--verifier" in argv:
        print("Débloqué (vérification seule, rien n'a été changé).")
        return 4

    wc_base, s = woo_session()
    base = wc_base.split("/wp-json/")[0] + "/wp-json/wp/v2/"
    avant = statut_pause(s, base)
    print("Plugin de pause :", avant)
    if avant == "active":
        if not changer_pause(s, base, "inactive"):
            print("Impossible de désactiver le plugin de pause, rien n'a été changé.")
            return 1
        print("Plugin de pause désactivé.")
        time.sleep(5)

    ok_page, code_page = page_rdv_sans_pause()
    ok_lp, code_lp = etape_latepoint()
    print("Page /rdv/ sans message de pause :", ok_page, "(HTTP %s)" % code_page)
    print("Étape LatePoint en multipart :", ok_lp, "(HTTP %s)" % code_lp)
    if ok_page and ok_lp:
        print("Réservation remise en route et contrôlée.")
        return 0

    if avant == "active":
        remis = changer_pause(s, base, "active")
        print("Contrôle raté : plugin de pause réactivé." if remis else "Contrôle raté ET réactivation de la pause impossible : à faire à la main.")
    return 2


if __name__ == "__main__":
    sys.stdout.reconfigure(encoding="utf-8")
    sys.exit(main(sys.argv[1:]))
