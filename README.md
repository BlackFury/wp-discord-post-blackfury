# BlackFury Relay

**2.2.0-beta.2** ajoute une identité indépendante, un import depuis WP Discord Post et les mises à jour via GitHub. Relay comprend aussi le routage des articles, l'aperçu Discord, une file de livraison et un journal. Voir le [guide de la bêta](docs/RELAY.md).

Relay est basé sur WP Discord Post 2.1.0 de **Nicola Mustone**, sous licence
**GPL-2.0-or-later**. Le correctif historique date de la version 2.1.1.

## Correctif

- Suppression du lien de mème qui reflétait une URL non échappée dans le HTML.
- Envoi par POST uniquement, avec droit `manage_options` et nonce spécifique.
- URL d'action et redirection fixes; Giphy en HTTPS.
- Jeton, contenu des messages et détails privés retirés des journaux de requêtes.
- Options et marqueurs anti-doublon importables depuis la version historique.

Le défaut local est reproduit et corrigé. Le lien exact avec **CVE-2025-26554**
reste une hypothèse cohérente avec l'avis public, sans validation du déclarant.
Ne pas présenter cette version comme certifiée ou comme une remise à niveau
complète des intégrations historiques.

## Installer

Lire **[la procédure de déploiement et de retour arrière](docs/DEPLOYMENT.md)**.
Utiliser `dist/blackfury-relay-2.2.0-beta.2.zip`. Sa racine est désormais
`blackfury-relay/`, avec `blackfury-relay.php` directement dedans.
Ne pas utiliser le ZIP automatique « Source code » de GitHub comme extension.

Minimum déclaré : WordPress 5.8 et PHP 7.4. Les versions effectivement testées,
et les limites de ces tests, sont dans [VALIDATION.md](docs/VALIDATION.md).
Le canal de mise à jour natif de WordPress propose les nouvelles versions stables
publiées sur GitHub Releases. L'installation automatique suit le réglage WordPress
de l'extension. L'installateur vérifie l'empreinte du ZIP et son identité.

**Rappel pour la 2.5.** Retirer l'importeur WP Discord Post avant de publier la
2.5.0. Le script de construction refusera de fabriquer ce ZIP tant que le module
de migration existe. Les données déjà importées doivent rester utilisables.

## Développer et vérifier

Prérequis : Python **3.8+**, Git, PHP 8.3 avec PDO SQLite, SQLite3, DOM, mbstring,
OpenSSL et cURL. Créer un environnement jetable, jamais une copie de production
avec ses secrets. Ne pas exposer `.test-env` sur un serveur web public.

```sh
python3 scripts/setup_test_env.py
export WP_TEST_ROOT="$PWD/.test-env/wordpress"
php tests/install.php
php tests/run.php
python3 scripts/build.py
python3 tests/check_package.py
```

PowerShell : remplacer `export` par `$env:WP_TEST_ROOT = (Resolve-Path '.test-env/wordpress').Path`
et utiliser `python` à la place de `python3` si nécessaire.
Le script refuse d'écraser `.test-env` lorsqu'il existe; on peut le réutiliser.
Les archives de dépendances officielles sont figées avec SHA-256.
Les tests chargent WordPress réel; les sorties HTTP sont interceptées et utilisent
uniquement des adresses `example.invalid` et des données synthétiques.

Après construction, extraire le ZIP dans `.test-env/wordpress/wp-content/plugins`,
puis lancer `php tests/install-package.php` : WordPress vérifie la version et
l'activation/réactivation sans perte des réglages ni des marqueurs d'envoi.
Les scripts et tests sont livrés dans le projet Git; ils sont exclus du ZIP installable.

Pour vérifier que les tests détectent la version vulnérable, utiliser une copie
du tag `upstream-2.1.0` et définir `WPDISCORD_PLUGIN_DIR` vers cette copie.
Ne jamais activer cette copie vulnérable sur un site accessible.

## Publier et maintenir

- [Provenance SVN et empreintes](docs/UPSTREAM.md)
- [Audit, sources et risques résiduels](docs/AUDIT.md)
- [Résultats de vérification](docs/VALIDATION.md)
- [Publication GitHub et maintenance](docs/PUBLISHING.md)
- [Contribution](CONTRIBUTING.md), [sécurité](SECURITY.md), [historique](CHANGELOG.md)

Les clés, webhooks, bases et sauvegardes restent hors du dépôt et de l'archive.
Les workflows GitHub fournis n'ont pas été exécutés sur GitHub tant que le projet
n'y a pas été publié.
