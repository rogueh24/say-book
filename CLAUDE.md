# Say-Book — consignes pour l'assistant

Lu au début de chaque conversation, quel que soit le compte. L'état du site est dans
`README.md`, l'historique dans `CHANGELOG.md`, les règles techniques dans `docs/ENGINEERING.md`.
**Les lire avant d'agir.**

## Le projet

Site WordPress de **SAY BARBERSHOP** (barbier) : <https://www.say-book.com>. Réservation en ligne avec **LatePoint**, boutique
**WooCommerce** (pas encore ouverte), thème **Blocksy**, hébergement **PlanetHoster** (World Pro, N0C).
**Site en production, utilisé tous les jours par de vrais clients.**

## Le propriétaire

- Il écrit en français, vite, avec des fautes de frappe : répondre **en français**, simplement.
- « Fait au mieux », « go », « continue » : décider et avancer, puis dire ce qui a été fait.
- Outils gratuits seulement.
- Il change de compte Claude : **tout l'état doit être dans ce dossier** (README, CHANGELOG,
  ENGINEERING à jour) à la fin de chaque passe.

## Avant de toucher à quoi que ce soit

1. **Production.** Ne rien modifier sur le site (contenu, produits, réglages, extensions)
   sans qu'il l'ait demandé. Pour une action qui casse ou supprime : dire ce qui va changer et
   attendre son accord.
2. **Trois façons d'agir sur le site** :
   - MCP WordPress, site `saybook` (pages, articles, médias, extensions, réglages, menus) ;
   - `tools/woo.py` : API WooCommerce (produits, catégories, commandes, codes promo) ;
   - API LatePoint `/wp-json/latepoint/v1` : **pas encore active** (voir README).
   **Tester d'abord sur la copie locale** (http://saybook.localhost:8080, `tools/local/demarrer.ps1`).
   Le code maison (`wp-content/`) se déploie à la main (zip à téléverser) tant que le
   déploiement automatique n'est pas branché (voir `docs/ENGINEERING.md`).
3. **Seul notre code est dans `wp-content/`** : plugins maison, futurs mu-plugins et thème
   enfant. Les extensions tierces sont dans `reference/` comme documentation : ne jamais les
   modifier ni les déployer (une mise à jour les écraserait).
4. **Aucun identifiant** dans ce dossier ni dans une réponse. Les accès sont dans
   `C:\Users\rogue\Claude\wp-sites.json` (entrée `saybook`) : lus par les outils, jamais
   affichés. Le dump `reference/*.sql` contient des données clients et des secrets : on peut
   l'interroger (`tools/sqlpeek.py`), jamais en recopier le contenu.
5. Données clients (rendez-vous, noms, téléphones) : ne les lire que si la tâche l'exige, ne
   jamais les recopier dans un fichier ou une réponse au-delà du nécessaire.
6. Dire vrai : ce qui n'a pas été vérifié sur le site en ligne n'est pas « fait ».

## Fin de passe

`CHANGELOG.md` (une entrée datée), `README.md` si l'état change, `docs/ENGINEERING.md` si une
règle ou une leçon s'ajoute, puis commit et push sur `rogueh24/say-book`.
**Le dépôt est public** : jamais d'identifiant, de nom de compte d'hébergement, de nom de
fichier de base ni de donnée client dans un fichier versionné ou un message de commit.
**Un push qui touche `wp-content/**` déploie sur le site** : annoncer ce qui part et attendre
son accord (sauf s'il vient de le demander), puis vérifier le site.
