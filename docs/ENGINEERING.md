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
| `local/` | copie locale de test (WordPress, MariaDB, base avec données clients) | non | **jamais** |
| `tools/local/` | scripts et réglages pour (re)monter la copie locale | oui | non |

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
  Les accents peuvent ressortir mal dans la console Windows : c'est l'affichage, les données sont en UTF-8 correct.
- `python tools/wp.py get|post <route> [json]` : API REST WordPress (`/wp-json/wp/v2/`), mêmes identifiants.
- `python tools/reprise_rdv.py [--verifier]` : remise en route de la réservation après le blocage PlanetHoster (désactive la pause, contrôle /rdv/ et une étape LatePoint en multipart, remet la pause si le contrôle rate). Lancé toutes les 30 min (9 h, 13 h, 19 h-23 h 30) par la tâche planifiée `saybook-verif-deblocage`, avec l'accord du propriétaire.
- `python tools/lp.py test|get|post <route>` : API REST LatePoint (`/wp-json/latepoint/v1/`), clé `LATEPOINT_KEY` dans `wp-sites.json`, en-tête `Authorization: Bearer`. Écriture (rendez-vous, clients…) seulement sur demande : ce sont de vrais clients.
- MCP WordPress : outils `claudeus_wp_*` avec `site: "saybook"`.

## Copie locale de test

Pour essayer plugins, thème enfant, réglages **avant** le vrai site. Installée le 23/10/2026.

- Démarrer : `powershell -File tools/local/demarrer.ps1` → <http://saybook.localhost:8080> ;
  arrêter : `tools/local/arreter.ps1`. Admin : `/wp-admin`, compte de test dans
  `local/ACCES-LOCAL.txt` (jamais versionné, ne sert qu'en local).
- Composants (gratuits, portables) : MariaDB 10.6.28 dans `local/mariadb` (port 3307, données
  `local/data`, root sans mot de passe, écoute seulement 127.0.0.1) ; PHP 8.4 NTS (winget
  `PHP.PHP.NTS.8.4`) avec `local/php.ini` ; serveur PHP intégré + `local/router.php` ;
  WordPress 7.1.3 dans `local/site` ; WP-CLI `local/dl/wp-cli.phar`.
- `local/site/wp-content/plugins/<nos plugins>` sont des **jonctions** vers `wp-content/plugins/`
  du projet : une modification se voit aussitôt en local. Les extensions tierces sont des copies
  de `reference/plugins`.
- Base : import du dump `reference/` puis `wp search-replace` de `https://www.say-book.com` vers
  `http://saybook.localhost:8080`. Les médias (`uploads`) ne sont pas copiés : pas d'images.
- **Rien ne sort** (la base contient les vrais clients et les vrais accès) :
  1. `mu-plugins/00-local-securite.php` (copie dans `tools/local/`) : toute requête HTTP bloquée,
     aucun e-mail envoyé (noté dans `debug.log`), extensions qui contactent l'extérieur forcées
     inactives (SMS Twilio, WhatsApp, agendas Google/Outlook/Apple, Mailchimp, Short.io,
     SureMails, Facebook, Site Kit, WooPayments, LiteSpeed, avis Google), bandeau « COPIE LOCALE » ;
  2. ces extensions aussi désactivées dans la base locale ;
  3. `php.ini` local : `curl_exec`, `fsockopen`, `stream_socket_client`, `mail` désactivées,
     `allow_url_fopen = Off` (certaines extensions ouvrent leurs propres connexions).
  `DISABLE_WP_CRON` et `WP_ENVIRONMENT_TYPE = local` dans `wp-config.php`.
- WP-CLI : `php -c local/php.ini local/dl/wp-cli.phar --path=local/site --skip-plugins --skip-themes …`
- Rafraîchir la base avec un nouveau dump : réimporter, refaire le search-replace, relancer la
  désactivation des extensions (voir le mu-plugin pour la liste).

## Leçons

- Le pare-feu de N0C renvoie un 403 (page LiteSpeed) aux requêtes sans User-Agent de navigateur ou avec celui de `python-requests` : toujours fixer un User-Agent dans les outils.

- **22/10/2026, 403 sur tout envoi `multipart/form-data`** : blocage temporaire de PlanetHoster
  (faille PHP `session.upload_progress`), au niveau du serveur, indépendant du WAF de N0C.
  Diagnostic rapide : un `fetch` en `FormData` → 403, le même en `URLSearchParams` → 200.
  Touche LatePoint (front et administration), WPForms, l'envoi de médias.
- LatePoint appelle `admin-ajax.php` avec `action=latepoint_route_call` ; on peut parcourir les
  étapes du formulaire avec `route_name=steps__load_step` sans créer de rendez-vous.
- PHP (N0C) : garder `opcache.validate_timestamps = 1`, sinon un fichier modifié ou une mise à
  jour d'extension peut ne pas être prise en compte.
