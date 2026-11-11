# Changelog

## 2.1.1 — BlackFury — 2026-11-11

- Retire le lien `add_query_arg()` non échappé du bouton de mème.
- Introduit une action POST dédiée, réservée aux administrateurs autorisés et protégée par nonce.
- Refuse l'ancien déclenchement GET; fixe la redirection et utilise HTTPS pour Giphy.
- Remplace les journaux contenant les en-têtes, messages, embeds et erreurs privées par des statuts génériques.
- Ajoute `Update URI: false`, tests de régression, provenance SVN, ZIP reproductible et procédures.
- Conserve les clés d'options, classes, filtres et métadonnées d'origine; aucune migration de base.

## 2.1.0 — upstream

Import exact des 12 fichiers du tag SVN WordPress 2.1.0, à la révision 1928520
(2018-08-22), sous le tag Git `upstream-2.1.0`. Historique original conservé dans `readme.txt`.
