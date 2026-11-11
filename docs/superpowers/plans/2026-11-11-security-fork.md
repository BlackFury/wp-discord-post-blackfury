# Security fork Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox syntax for tracking.

**Goal:** Livrer le fork local 2.1.1, testé et installable, avec provenance et migration.

**Architecture:** Conserver le plugin original à la racine du dépôt. Isoler le changement d'action administrative; WordPress réel et HTTP intercepté pour les tests.

**Tech Stack:** PHP, WordPress, PowerShell, Python 3 pour la distribution.

**Spec:** `docs/superpowers/specs/2026-11-11-security-design.md`

## Global Constraints

- Aucun accès en écriture au site, aucun envoi Discord, aucun push GitHub.
- Conserver `wp-discord-post/wp-discord-post.php`, options et métadonnées.
- Version 2.1.1; GPL-2.0-or-later; crédits de Nicola Mustone conservés.
- Import exact de SVN r1928520, tag upstream-2.1.0.

## Review Focus

- URI hostile dans le chemin et dans les paramètres : aucun attribut injecté.
- Nonces tableau, absent, expiré et mauvaise action : aucune requête externe.
- Abonné avec nonce valide : aucune requête externe.
- POST autorisé : une requête Giphy puis un envoi Discord synthétique.
- Réglages optionnels, métadonnées et actualisation d'un article : aucune perte ni doublon.

### Task 1: Reproduction et correctif

**Files:** `tests/`, `includes/class-wp-discord-post-admin.php`, `includes/class-wp-discord-post-dank-meme.php`.

**Interfaces:** Conserver les classes; action `admin_post_wp_discord_post_send_dank_meme`; nonce `wp_discord_post_send_dank_meme`.

- [x] Écrire les tests de rendu, autorisation et comportement réseau en WordPress isolé.
- [x] Exécuter sur 2.1.0 et conserver les échecs attendus.
- [x] Remplacer le lien par un formulaire POST indépendant, vérifier méthode/droit/nonce, fixer URL et HTTPS.
- [x] Exécuter toute la suite, corriger les régressions, enregistrer le commit.

### Task 2: Distribution et preuves

**Files:** en-tête du plugin, `readme.txt`, `LICENSE`, README, SECURITY, CONTRIBUTING, CHANGELOG, docs, `scripts/`, `.github/workflows/`.

**Interfaces:** ZIP avec racine `wp-discord-post/`, script de construction déterministe; aucune configuration embarquée.

- [x] Ajouter version/Update URI, documentation de provenance/audit/déploiement/retour arrière et publication.
- [x] Tester empreinte, sauvegarde via options.php et préservation des options synthétiques à l'activation; documenter le retour arrière sans prétendre à une restauration réelle de base.
- [x] Construire et vérifier le ZIP, lint PHP, contrôle des secrets et comparaison upstream.
- [x] Relecture indépendante du diff et des limites. Contrôles de routage et de sauvegarde ajoutés.
- [x] Vérification finale sur les sources et le ZIP installé. Livraison locale du ZIP, du patch et du projet Git.
