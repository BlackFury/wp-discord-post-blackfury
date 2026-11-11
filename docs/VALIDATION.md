# Validation locale — 2026-11-11

## Environnement effectivement exécuté

- PHP CLI **8.3.36**, distribution Windows officielle, SHA-256 de l'archive
  `1a097d52b7a68622cdc723073b61cc70caf79c941218ff65b2d8441dbc6b10d4`.
- WordPress **7.1.4**, archive officielle figée dans le script de préparation.
- SQLite Database Integration **3.1.3**, avec une base jetable locale.
- Fonctions WordPress réelles pour échappement, hooks, utilisateurs, permissions,
  nonces, sauvegarde des réglages, publications et métadonnées.
- Filtre `pre_http_request` : aucune émission réelle vers Discord/Giphy; fixtures
  synthétiques. Arrêts et redirections interceptés après les contrôles du plugin.

## Résultats

La suite initiale de 14 cas a été exécutée **avant** le correctif : 12 échecs
attendus sur le rendu et l'action vulnérable, 2 tests de non-régression réussis.
Le test de confidentialité des logs a également échoué avant sa correction.
La suite étendue, relancée sur les sources upstream intactes, détecte 14 échecs
sur 18; les 4 cas de conservation/sauvegarde/publication restent fonctionnels.
La suite étendue passe désormais **18 cas, 0 échec** :

- Rendu avec marqueurs inertes dans le chemin et les paramètres de l'URI.
  Aucun attribut ni élément inattendu. Deux formulaires distincts et nonce valide.
- Ancien GET inerte; méthode incorrecte refusée; nonces absent, faux, tableau,
  mauvaise action, expiré ou présent uniquement dans l'URL refusés sans réseau.
- Abonné et anonyme refusés même avec un nonce valide pour leur identité.
- POST administrateur : Giphy HTTPS, message synthétique transmis une fois,
  redirection fixe 303; enregistrement explicite de la route et absence de nopriv.
- Conservation des 22 options synthétiques à l'initialisation, échappement d'un
  réglage hostile et absence d'affichage des réglages pour un abonné.
- **Sauvegarde réelle via `wp-admin/options.php`** : changement attendu persisté,
  autres options inchangées, aucune notification déclenchée.
- Publication WordPress : un envoi synthétique et marqueur créé; modification
  ultérieure sans nouvel envoi.
- Logs : aucun jeton, message, embed ou détail d'erreur privé dans le fichier testé.
- Empreinte : identique après chargement; un changement d'option d'intégration
  est détecté sans exposer les valeurs.

Une relecture indépendante a réexécuté les 15 premiers cas, sans défaut bloquant
dans les quatre fichiers PHP corrigés. Ses remarques sur le routage et la sauvegarde
des réglages ont été couvertes par les tests supplémentaires.

Le ZIP a aussi été extrait dans le répertoire d'extensions du WordPress jetable :
lecture de la version et de `Update URI` par WordPress, deux cycles
activation/désactivation, sans requête HTTP ni modification de l'empreinte des
options et d'un marqueur de publication préexistant. Ce test d'installation
par l'API WordPress est distinct des 18 cas de la suite; il ne simule pas un clic
dans l'écran de téléversement.

Le script de préparation a été exécuté depuis un répertoire neuf : téléchargements
officiels vérifiés par SHA-256, installation WordPress et suite complète sans
avertissement. La syntaxe de tous les 17 fichiers PHP du projet (extension, tests
et script d'empreinte) a été vérifiée avec `php -l`.

## Reproduire

Suivre le README. `php tests/run.php` doit terminer avec code 0; chaque cas doit
afficher PASS. `python3 scripts/build.py`, puis `python3 tests/check_package.py`
contrôlent la racine installable, les 23 fichiers autorisés, leur égalité avec les
sources, les notices, versions et l'empreinte. Le ZIP exclut tests, outils, Git,
bases, sauvegardes et configurations. La construction normalise les fins de ligne.

## Ce qui reste à valider avant production

Pas de test navigateur interactif, de livraison Discord/Giphy réelle, de test
MySQL/MariaDB, multisite, ou d'intégrations facultatives actuelles. Les versions
minimales déclarées (WP 5.8/PHP 7.4) ne sont pas une matrice de compatibilité testée.
Le workflow GitHub est préparé mais n'a pas tourné sur GitHub. Le scanner de
sécurité et l'auteur de la CVE n'ont pas validé ce fork. Aucun déploiement sur
BlackFury.me n'est effectué ou revendiqué.

La réussite des tests HTTP simulés ne signifie pas qu'un message arrivera sur
Discord : voir le risque hérité des réponses 4xx/429 dans AUDIT.md.
