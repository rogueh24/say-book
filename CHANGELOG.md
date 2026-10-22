# Journal

## À venir
- Désactiver `say-book-rdv-pause` quand PlanetHoster lève le blocage multipart, puis retester une réservation.
- Activer l'API LatePoint (Paramètres > API) + générer une clé, l'ajouter aux outils.
- Étapes de réservation : couper l'écran des catégories (`steps_show_service_categories`), envisager « n'importe quel coiffeur » (`allow_any_agent`).
- Nettoyer `latepoint-simple-french` (lourd) et le hook de `latepoint-password-sync`.
- Copie locale de test (MariaDB + WordPress, envois coupés) : pas encore installée.

## 22/10/2026
- Diagnostic du 403 sur la réservation : blocage multipart chez PlanetHoster (confirmé par leur support).
- Plugin `say-book-rdv-pause` créé et installé sur le site (message + coordonnées du salon sur /rdv/).
- Accès : MCP WordPress `saybook` et `tools/woo.py` testés et fonctionnels.
- Dépôt GitHub `rogueh24/say-book` (public) + déploiement SFTP automatique ; essai à blanc réussi.
- Vérification automatique du déblocage programmée (tâche `saybook-verif-deblocage`, 9 h / 13 h / 19 h, lecture seule, notification si débloqué).
- Dossier organisé : `wp-content/` (notre code), `reference/` (copie du site + SQL), `tools/`, `docs/`.
