# BlackFury Relay 2.2 bêta

BlackFury Relay est un fork GPL de WP Discord Post par Nicola Mustone. La bêta se concentre sur les articles WordPress et leur livraison vers Discord.

## Installer et essayer

1. Sur une copie de préproduction, faire une sauvegarde en suivant [DEPLOYMENT.md](DEPLOYMENT.md). Utiliser un webhook Discord réservé aux essais.
2. Si WP Discord Post, le fork 2.1.1 ou Relay 2.2 bêta.1 est déjà présent, désactiver cette extension **sans la désinstaller**. Installer puis activer le nouveau ZIP, qui a son propre dossier `blackfury-relay`.
3. Ouvrir **BlackFury Relay → Migration**, puis cliquer sur **Import or continue** jusqu'à confirmation. L'import copie les réglages du site courant et les marqueurs issus de l'extension historique. Les valeurs déjà configurées dans Relay priment, même si elles sont vides. Les données de l'ancienne extension restent en place. Pour un multisite, répéter par site après désactivation de l'extension d'origine aux niveaux concernés.
4. Ouvrir **BlackFury Relay**. Ajouter un salon, utiliser **Send test**, puis sélectionner un salon par défaut. **Existing webhook** utilise les réglages importés. Les nouveaux webhooks officiels Discord s'utilisent directement par HTTPS, sans jeton de bot.
5. Associer des catégories à des salons. Dans le panneau BlackFury Relay de l'éditeur de blocs ou la boîte de l'éditeur classique, choisir un salon, garder le routage automatique ou désactiver la notification de l'article.
6. Vérifier le message dans Discord et dans le journal. Tester aussi une publication programmée et une modification d'article après son envoi.

L'import et les sorties de Relay sont bloqués tant que l'ancienne extension est active sous son chemin standard `wp-discord-post/wp-discord-post.php`. Si l'ancien dossier a été renommé, vérifier sa désactivation manuellement. Suspendre les publications et les entrées d'intégrations pendant le basculement. Les hooks PHP personnalisés nommés `wp_discord_*` doivent être adaptés au nouveau préfixe `blackfury_relay_*`.

## Mises à jour

Une fois Relay installé, WordPress propose les versions stables plus récentes depuis le dépôt GitHub BlackFury. Une version doit être publiée comme release stable avec un tag `vX.Y.Z` et un fichier `blackfury-relay-X.Y.Z.zip` attaché. Un tag Git ou l'archive « Source code » ne suffit pas. Les préversions ne rejoignent pas ce canal. Le ZIP est vérifié par SHA-256 et doit porter le dossier, le fichier principal, la version et l'identité de Relay attendus.

Pour laisser WordPress installer seul les futures versions stables, activer **Mises à jour automatiques** pour BlackFury Relay dans **Extensions → Extensions installées**. La première transition depuis WP Discord Post reste une installation et un import manuels.

**Rappel version 2.5.0.** Retirer l'importeur temporaire et adapter les tests et la documentation avant publication. Sur un site où l'import est en cours, Relay retient la mise à jour vers 2.5 jusqu'à la fin de la migration. En multisite, vérifier tous les sites avant la mise à jour des fichiers partagés. Les installations ayant déjà migré continueront à utiliser les options et marqueurs propres à Relay.

## Routage et aperçu

Le choix de l'article prime sur les catégories, puis vient le salon par défaut. Si plusieurs catégories correspondent, celle dont l'identifiant WordPress est le plus bas l'emporte. Un salon supprimé demeure « indisponible » pour les règles concernées jusqu'à leur réaffectation, ce qui évite de rediriger un article vers un autre salon sans choix explicite.

L'aperçu est une représentation de la carte. Dans l'éditeur de blocs, le titre et l'extrait se mettent à jour à l'écran. Enregistrer le brouillon puis recharger pour actualiser l'image, le texte du message selon le modèle et le routage par catégorie. L'éditeur classique affiche le dernier brouillon enregistré. Le réglage historique qui désactive les cartes reste pris en compte.

## Envoi et dépannage

- La publication ajoute un travail WP-Cron. Le worker s'appuie sur WordPress pour l'échéance puis demande à Discord de renvoyer le message créé. Sur un site où WP-Cron est désactivé ou peu fréquenté, configurer une exécution régulière des tâches planifiées.
- L'état « accepté » requiert une réponse HTTP 2xx et un identifiant Discord. Le journal affiche le lien si l'identifiant du serveur est connu. Sinon il affiche l'identifiant du message.
- Un refus de Discord laisse le travail en échec. En cas de limitation HTTP 429, Relay effectue jusqu'à trois tentatives au total, avec le délai Discord. Une réponse perdue, un incident pendant l'envoi ou une acceptation sans identifiant passe en « non confirmé ». Vérifier le salon avant de relancer, car une relance peut créer un doublon.
- Les nouvelles notifications périodiques ou après réactivation ne reprennent que les tâches effectivement planifiées. Si une tâche a disparu, le journal permet de la reprogrammer. Une tentative interrompue doit d'abord repasser à « non confirmé ».
- Seuls les articles publics récemment publiés par WordPress entrent dans le flux, y compris les publications planifiées. Les articles protégés et les articles dont le marqueur d'envoi existe sont ignorés. Les mentions de masse sont désactivées par défaut. Une installation qui les avait activées garde ce réglage.

Le journal présente l'état courant des 50 derniers articles suivis. Il ne stocke ni texte envoyé ni URL de webhook. Les URL de webhook sont des secrets enregistrés dans les options WordPress, comme dans le fork d'origine. Les autres intégrations et réglages restent disponibles dans l'écran historique et demandent une validation séparée avec leurs versions réelles.

## Retour à 2.1.1

Relever les tâches en attente, désactiver Relay et réactiver l'ancienne extension si son usage est nécessaire et sécurisé. Les réglages d'origine sont préservés. Les marqueurs écrits depuis la migration restent propres à Relay. Avant de reprendre les envois avec l'ancienne extension, réconcilier ces messages avec Discord pour éviter des doublons.

Les tests de cette bêta interceptent le réseau. Ils ne valident pas la réception sur un serveur Discord réel.
