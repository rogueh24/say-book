# say-book.com

Site WordPress + WooCommerce + LatePoint de **SAY BARBERSHOP**.
Hébergé chez PlanetHoster (World Pro, N0C). **En production : ne rien changer sans demande.**
Consignes : `CLAUDE.md`. Règles techniques : `docs/ENGINEERING.md`. Historique : `CHANGELOG.md`.

## État au 23/10/2026

| Quoi | État |
|---|---|
| Réservation en ligne | ✅ remise en route le 23/10/2026 (blocage PlanetHoster levé, plugin de pause désactivé, contrôlé) |
| MCP WordPress (`saybook`) | ✅ fonctionne (connecté en tant que Suat, id 1) |
| API WooCommerce (`tools/woo.py`) | ✅ fonctionne |
| API LatePoint (`/wp-json/latepoint/v1`) | ⏳ pas encore active, maintenant possible (blocage levé) : Paramètres > API > « Enable REST API » + « Generate New Key », clé dans `wp-sites.json` (`saybook.LATEPOINT_KEY`), puis l'ajouter aux outils |
| Dépôt git | GitHub **public** `rogueh24/say-book`, branche `main` (rien de secret dedans : vérifier avant chaque commit). Déploiement automatique opérationnel (`.github/workflows/`) : 5 secrets SFTP saisis, essai à blanc réussi le 22/10 (connexion OK, rien envoyé) |

## Carte du site (copie des fichiers + dump SQL du 22/10/2026)
- WordPress 7.1.3, thème **Blocksy** (pas de thème enfant), WooCommerce 11.2.1, LatePoint 5.7.5 + Pro Features 1.7.4 et addons (Google/Apple/Outlook Calendar, Mailchimp, SMS Twilio, WhatsApp Meta, Payments WooCommerce, Short.io). Cache LiteSpeed, SEOPress, WPForms Lite, SureMails, Trustindex. PHP 8.4.
- Dump `reference/<dump>.sql` (MariaDB 10.6, préfixe `wp_`). Contient des données clients et des secrets : **ne jamais en citer le contenu**.
- Pages publiées : home, say, services, rdv, boutique, cart, checkout, mon-compte, client, contact, news, cgv, pdc. 9 articles, 47 produits, 0 commande WooCommerce (boutique « bientôt disponible »).
- LatePoint : 1 salon (Say Barbershop), 3 coiffeurs (Suat, Ryan, Ferhat), 4 catégories (Les bases, L'expérience VIP, Les détails, Les formules = désactivée), 20 services dont 4 désactivés. ~2 000 rendez-vous (fév. → nov. 2026), ~700 clients. Paiement sur place uniquement.
- Étapes LatePoint : catégories → prestation → options → coiffeur → date → coordonnées → vérification → confirmation. Réglages notables : `steps_show_service_categories = on` (se coupe dans Réglages > Étapes > Services), `allow_any_agent = off`. L'étape Vérification ne peut pas être désactivée dans cette version.

## Plugins maison (`wp-content/plugins/`)
- `say-latepoint` : onglet « Mes Rendez-vous » dans Mon compte WooCommerce (shortcode `[latepoint_customer_dashboard]`).
- `latepoint-simple-french` : traduit 2 textes en JS ; parcourt tout le DOM à chaque changement (lourd, à remplacer par Loco Translate).
- `latepoint-password-sync` : synchro des mots de passe LatePoint ↔ WordPress. Écoute `wp_ajax_latepoint_route_request` alors que LatePoint utilise `latepoint_route_call` : la capture « tôt » ne se déclenche jamais, la lecture de secours dans `$_POST` fait le travail.
- `say-book-rdv-pause` : message « réservation indisponible » + coordonnées du salon sur /rdv/. Zip dans `dist/`. **À désactiver quand le blocage est levé.**

## Accès
- Identifiants dans `C:\Users\rogue\Claude\wp-sites.json`, entrée `saybook`. Ne jamais les écrire ailleurs.
- `python tools/woo.py test` : API WooCommerce. `python -I tools/sqlpeek.py reference/<dump>.sql <table>` : lecture du dump.
- MCP WordPress : `site: "saybook"`.
