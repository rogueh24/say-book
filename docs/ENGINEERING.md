# Say-Book — règles techniques

## Organisation du dossier

| Chemin | Contenu | Versionné | Déployé |
|---|---|---|---|
| `wp-content/plugins/<maison>` | plugins écrits pour Say-Book | oui | oui (quand le déploiement existe) |
| `wp-content/mu-plugins/` | réglages et contenus versionnés (à venir) | oui | oui |
| `wp-content/themes/` | futur thème enfant Blocksy | oui | oui |
| `tools/` | `woo.py` (API WooCommerce), `sqlpeek.py` (lecture du dump) | oui | non |
| `dist/` | zips prêts à téléverser | non | non |
| `reference/` | copie des extensions et thèmes du site + dump SQL | non | **jamais** |
| `Photos/` | photos fournies par le salon | non | non |

## Déploiement

En secours (ou tant que les secrets SFTP ne sont pas saisis) : **zip téléversé à la main** (Extensions > Ajouter > Téléverser), puis purge
du cache LiteSpeed. Faire le zip du dossier du plugin (le zip contient le dossier).

Automatique (fichier `.github/workflows/deploy-wp-content.yml`) : un push sur `main` qui touche
`wp-content/**` → GitHub Actions → `lftp mirror --reverse` vers le `wp-content` du serveur,
**sans `--delete`** : seuls nos fichiers partent, les extensions tierces du serveur ne sont pas
touchées. Un push qui ne touche que la doc ou `tools/` ne déploie rien.
Lancement à la main (onglet Actions > « Run workflow ») : par défaut **essai à blanc**, qui liste
ce qui partirait sans rien envoyer — à utiliser pour tester la connexion.
Secrets GitHub (Settings > Secrets and variables > Actions), saisis par le propriétaire :
`SFTP_SERVER`, `SFTP_PORT`, `SFTP_USERNAME`, `SFTP_PASSWORD`, `SFTP_REMOTE_PATH`
(= `public_html`, relatif au dossier de connexion SFTP). Jamais dans le dépôt.
**Avant un push qui déploie : dire ce qui va partir et attendre son accord**, sauf s'il vient de
le demander. Après : vérifier le site en ligne.

## Outils

- `python tools/woo.py test` puis `get|post|put|delete <route> [fichier.json]`. `delete` va à la
  corbeille. Authentification par mot de passe d'application WordPress (marche pour
  WooCommerce) ou clé `CK`/`CS` si remplie.
- `python -I tools/sqlpeek.py reference/<dump>.sql <table> [...]` : sort les lignes en JSON.
  Les accents ressortent mal (encodage du dump), le site lui est correct.
- `python tools/wp.py get|post <route> [json]` : API REST WordPress (`/wp-json/wp/v2/`), mêmes identifiants.
- `python tools/reprise_rdv.py [--verifier]` : remise en route de la réservation après le blocage PlanetHoster (désactive la pause, contrôle /rdv/ et une étape LatePoint en multipart, remet la pause si le contrôle rate). Lancé 3 fois par jour par la tâche planifiée `saybook-verif-deblocage`, avec l'accord du propriétaire.
- MCP WordPress : outils `claudeus_wp_*` avec `site: "saybook"`.

## Leçons

- **22/10/2026, 403 sur tout envoi `multipart/form-data`** : blocage temporaire de PlanetHoster
  (faille PHP `session.upload_progress`), au niveau du serveur, indépendant du WAF de N0C.
  Diagnostic rapide : un `fetch` en `FormData` → 403, le même en `URLSearchParams` → 200.
  Touche LatePoint (front et administration), WPForms, l'envoi de médias.
- LatePoint appelle `admin-ajax.php` avec `action=latepoint_route_call` ; on peut parcourir les
  étapes du formulaire avec `route_name=steps__load_step` sans créer de rendez-vous.
- PHP (N0C) : garder `opcache.validate_timestamps = 1`, sinon un fichier modifié ou une mise à
  jour d'extension peut ne pas être prise en compte.
