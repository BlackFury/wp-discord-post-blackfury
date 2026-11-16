# BlackFury Relay 2.2 bêta

BlackFury Relay est un fork GPL de WP Discord Post par Nicola Mustone. La bêta se concentre sur les articles WordPress et leur livraison vers Discord.

## Installer et essayer

1. Sur une copie de préproduction, faire une sauvegarde en suivant [DEPLOYMENT.md](DEPLOYMENT.md). Utiliser un webhook Discord réservé aux essais.
2. Installer le ZIP BlackFury Relay. Si WP Discord Post ou sa version BlackFury 2.1.1 est déjà présent, le désactiver puis remplacer son dossier `wp-discord-post` sans désinstaller. Le fichier principal, les options existantes et les anciens marqueurs d'envoi gardent la même identité.
3. Ouvrir **BlackFury Relay** dans l'administration. Ajouter un salon, utiliser **Send test**, puis sélectionner un salon par défaut. Le choix **Existing webhook** utilise les réglages hérités. Les nouveaux webhooks officiels Discord s'utilisent directement par HTTPS, sans jeton de bot.
4. Associer des catégories à des salons. Dans le panneau BlackFury Relay de l'éditeur de blocs ou la boîte de l'éditeur classique, choisir un salon, garder le routage automatique ou désactiver la notification de l'article.
5. Vérifier le message dans Discord et dans le journal. Tester aussi une publication programmée et une modification d'article après son envoi.

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

Relever les tâches en attente, désactiver la bêta et suivre le [retour arrière](DEPLOYMENT.md). Les marqueurs des notifications acceptées restent en base. La version 2.1.1 ne traite pas les tâches Relay. Vérifier chaque tâche en attente ou incertaine dans Discord avant de décider de la relancer.

Les tests de cette bêta interceptent le réseau. Ils ne valident pas la réception sur un serveur Discord réel.
