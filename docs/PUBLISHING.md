# Publication GitHub et maintenance

## État de cette livraison

Dépôt Git local, sans remote configuré et sans push. Le nom de dépôt suggéré est
`wp-discord-post-blackfury`; le propriétaire GitHub doit être choisi parmi les
comptes effectivement accessibles. Aucune existence ni propriété de
`github.com/BlackFury/...` n'est présumée.

Les commits locaux utilisent `BlackFury <maintainer@example.invalid>` comme
identité de préparation, sans prétendre à une identité GitHub vérifiée. Avant
publication, choisir l'identité de commit souhaitée. Modifier `git config` ne
réécrit pas les commits déjà créés; leur réattribution éventuelle doit être
volontaire et implique de recréer les tags et l'archive du dépôt Git.

## Publier après vérification de l'accès et accord

1. Lire l'audit et les résultats, relancer la suite, inspecter `git status`,
   `git log`, l'ensemble des fichiers suivis et l'historique pour exclure secrets
   et données de site. Vérifier également le ZIP de distribution.
2. Créer un dépôt **public vide** sous le propriétaire choisi, sans initialiser
   README ni licence, puis copier son URL HTTPS exacte. Par exemple avec GitHub
   CLI, après connexion vérifiée :

   ```sh
   gh auth status
   gh repo create PROPRIETAIRE/wp-discord-post-blackfury --public --source=. --remote=origin
   git push -u origin main
   git push origin upstream-2.1.0 v2.1.1
   ```

   `PROPRIETAIRE` est un paramètre à remplacer. Ces commandes **publient** le
   contenu; elles ne sont pas exécutées dans cette livraison. Si un remote existe,
   en vérifier l'URL au lieu d'en ajouter un autre ou de forcer un push.
3. Activer le signalement privé des vulnérabilités et la protection de `main`;
   exiger une revue et le workflow de vérification. Examiner son résultat réel.
4. Créer une release `v2.1.1` et y joindre **le ZIP construit** et son SHA-256.
   Décrire exactement le correctif et les limites de VALIDATION.md. Aucun jeton,
   webhook, journal de site ou sauvegarde ne doit y être ajouté.
5. Vérifier la page publique, le tag, les pièces jointes et leur empreinte après
   téléchargement. La réussite locale des tests ne prouve pas celle de GitHub Actions.

## Maintenance

Pour chaque version : branche dédiée, test reproduisant le problème, correctif
minimal, revue, tests WordPress/PHP et préproduction, mise à jour du changelog,
du numéro dans le fichier principal et du stable tag, puis ZIP et empreinte.
Conserver le tag upstream original. Comparer les évolutions SVN sur une copie;
ne jamais écraser le fork par une synchronisation automatique.

Les versions de dépendances de test sont figées; les mettre à jour explicitement
avec leur origine et de nouveaux résultats. Priorités futures : traitement des
codes HTTP/rate limits, routage des webhooks, jetons, compatibilité des intégrations
et validation des types de réglages. Ne pas modifier leur comportement en silence.
