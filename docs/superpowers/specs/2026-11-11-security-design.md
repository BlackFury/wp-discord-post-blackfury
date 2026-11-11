# WP Discord Post BlackFury 2.1.1

## Demande et limites

Préparer localement un fork public, un correctif de sécurité, une archive WordPress
et une procédure de migration réversible. Aucune publication GitHub, modification
du site BlackFury ni émission réelle vers Discord n'est incluse.

## Approche retenue

Le périmètre demandé et la discussion précédente retiennent une version minimale
basée sur le tag SVN 2.1.0. Conserver `wp-discord-post/wp-discord-post.php`, les
classes, filtres, options et métadonnées. Import exact et tag `upstream-2.1.0`.

Choix d'implémentation : remplacer le lien GET vulnérable par un bouton lié à un
formulaire POST séparé (pas de formulaires imbriqués), vers `admin-post.php`.
Vérifier POST, `manage_options` et un nonce spécifique avant tout appel réseau.
Redirection vers une URL fixe, Giphy en HTTPS. Cette voie supprime la réflexion
de REQUEST_URI et les envois GET involontaires. Un simple `esc_url` aurait laissé
l'action sans protection; une refonte de toutes les intégrations dépasse le correctif.

L'audit a aussi reproduit une fuite de jeton et de contenu dans les logs : remplacer
ces impressions par des statuts sans données privées, en gardant le réglage de logging.

`Update URI: false` évite l'écrasement par WordPress.org à partir de WordPress 5.8,
sans inventer une adresse GitHub ni ajouter un service de mise à jour.

## Preuves attendues

Tests exécutés sur le code réel et les fonctions WordPress réelles, réseau
intercepté à la frontière HTTP avec données synthétiques : injection dans le
chemin/paramètres, GET historique, utilisateurs non autorisés, nonce invalide ou
malformé, POST autorisé, absence de mutations des réglages, publication et absence
de doublon. Archive limitée aux fichiers nécessaires; pas de secrets ni de base.

La relation exacte avec CVE-2025-26554 ne peut être certifiée sans le reproducer
du déclarant. Rapporter les preuves du défaut identifié, les risques résiduels et
les contrôles sur préproduction encore nécessaires. L'identifiant de dépôt GitHub
et l'identité de commit définitive restent à choisir avant publication.
