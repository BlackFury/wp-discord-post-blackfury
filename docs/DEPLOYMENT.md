# Déployer BlackFury Relay

Tester d’abord le ZIP en préproduction sur les mêmes versions de WordPress, PHP et de base qu’en production. Préparer un webhook vers un salon Discord d’essai.

1. Sauvegarder les fichiers de l’ancienne extension et la base de données. Les sauvegardes contiennent les secrets. Les conserver dans un emplacement privé. Suspendre les publications et les flux concernés pendant la bascule.
2. Facultatif avec WP-CLI : sauvegarder une empreinte de contrôle avec `wp eval-file /chemin/prive/settings-fingerprint.php > /chemin/prive/relay-avant.json`. Le script répertorie les options des deux extensions et les anciens et nouveaux marqueurs. Cette empreinte complète une sauvegarde et ne contient aucune valeur brute.
3. Désactiver WP Discord Post, le fork 2.1.1 ou la première bêta sans cliquer sur **Supprimer**. Installer le ZIP Relay via **Extensions → Ajouter → Téléverser une extension**, puis l’activer. WordPress doit l’identifier par `blackfury-relay/blackfury-relay.php`.
4. Ouvrir **BlackFury Relay → Migration** et cliquer sur **Import or continue**. Répéter jusqu’à la confirmation. Les réglages déjà présents dans Relay priment. L’ancienne extension et ses données restent disponibles pour le retour arrière. Si elle reste active sous son chemin d’origine, Relay suspend ses propres envois.
5. Contrôler les réglages et l’empreinte. Comparer séparément les anciennes valeurs avec le même préfixe converti dans Relay. Les valeurs déjà configurées dans Relay peuvent différer après l’import.
6. Envoyer le message de test, puis publier un article programmé et vérifier qu’il arrive une seule fois dans le bon salon. Modifier l’article et vérifier l’absence de doublon. Tester séparément les autres intégrations utilisées.
7. Après validation sur la copie, appliquer cette séquence sur le site cible avec une nouvelle sauvegarde. Le [guide Relay](RELAY.md) décrit le canal GitHub et les mises à jour automatiques de WordPress.

## Retour arrière

Suspendre les envois, rendre compte des messages déjà reçus dans Discord, puis désactiver Relay. L’ancienne extension et ses options d’origine restent disponibles. Les confirmations d’envoi produites par Relay restent dans ses propres marqueurs : vérifier le salon et le journal avant de remettre en service les annonces de l’ancienne extension. Une version d’origine vulnérable ne doit pas être réactivée en production sans protection.

## Rappel 2.5

Le module de migration sort du produit à partir de la 2.5. Terminer la migration avec une version antérieure si nécessaire. Conserver les options et les marqueurs Relay déjà copiés.
