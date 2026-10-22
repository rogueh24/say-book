# Journal

## À venir
- Étapes de réservation (**en attente de l'avis du gérant du salon**, ne rien changer avant) : couper l'écran des catégories (`steps_show_service_categories`), envisager « n'importe quel coiffeur » (`allow_any_agent`).
- Nettoyer `latepoint-simple-french` (lourd) et le hook de `latepoint-password-sync`.

## 23/10/2026
- Copie locale de test installée (WordPress 7.1.3 + PHP 8.4 + MariaDB 10.6, base du 22/10) : pages et étape LatePoint OK, aucune requête ni e-mail ne sort (3 protections). Scripts `tools/local/`.
- API LatePoint activée (clé « Claude »), outil `tools/lp.py` testé en lecture (20 services, 3 coiffeurs). Une ancienne clé « Revoked » reste affichée : LatePoint ne propose pas de la supprimer.
- Blocage multipart levé par PlanetHoster (dans la nuit du 22 au 23). Plugin de pause désactivé par la tâche automatique ; contrôlé le 23 au matin : /rdv/ sans pause, étape LatePoint en multipart OK. Tâche `saybook-verif-deblocage` désactivée.

## 22/10/2026
- Diagnostic du 403 sur la réservation : blocage multipart chez PlanetHoster (confirmé par leur support).
- Plugin `say-book-rdv-pause` créé et installé sur le site (message + coordonnées du salon sur /rdv/).
- Accès : MCP WordPress `saybook` et `tools/woo.py` testés et fonctionnels.
- Dépôt GitHub `rogueh24/say-book` (public) + déploiement SFTP automatique ; essai à blanc réussi.
- Vérification automatique du déblocage programmée (tâche `saybook-verif-deblocage`, toutes les 30 min à 9 h, 13 h et de 19 h à 23 h 30) : quand c'est débloqué, `tools/reprise_rdv.py` désactive la pause et contrôle la réservation (accord du propriétaire).
- Pause RDV 1.0.1 : téléphone et ville sur une seule ligne. Premier déploiement automatique GitHub → site, réussi et vérifié en ligne.
- Pause RDV 1.0.2 : icônes téléphone / e-mail à la place du texte (déployé, vérifié en largeur téléphone).
- Dossier organisé : `wp-content/` (notre code), `reference/` (copie du site + SQL), `tools/`, `docs/`.
