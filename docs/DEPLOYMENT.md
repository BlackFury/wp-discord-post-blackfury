# Déploiement avec conservation des réglages

Cette procédure est préparée, pas exécutée sur BlackFury.me. Aucun accès au site
ni secret de production n'est contenu dans ce projet. Commencer sur préproduction.

## 1. Préparer et sauvegarder

1. Relever WordPress, PHP et les versions des intégrations actives. Exiger au
   minimum WordPress 5.8 pour `Update URI`; préférer une version PHP maintenue.
2. Vérifier que l'extension active est bien `wp-discord-post/wp-discord-post.php`.
   Si le dossier diffère ou si le code installé est personnalisé, comparer avant
   remplacement. Ne pas activer deux copies qui déclarent les mêmes classes.
3. Faire une sauvegarde privée, restaurable et datée des **fichiers d'origine et
   de la base complète**. La base contient les secrets et les marqueurs anti-doublon.
   Conserver les sauvegardes hors de la racine web et hors du dépôt GitHub.
4. Suspendre temporairement publications, publications programmées et actions
   des intégrations pendant la bascule et les comparaisons. Relever les éléments
   en attente : un événement survenu pendant la désactivation peut être perdu.
5. Avec WP-CLI, capturer une empreinte sans afficher les valeurs :

   ```sh
   wp eval-file /chemin/prive/settings-fingerprint.php > /chemin/prive/discord-avant.json
   ```

   Le script est dans `scripts/`. Il ne modifie rien. Il couvre tous les noms
   commençant par `wp_discord_` (y compris `wp_discord_enabled_for_*`) et les
   métadonnées `_wp_discord_post_published`. Ce fichier est une preuve de comparaison,
   **pas une sauvegarde permettant de restaurer les valeurs**.

Sans WP-CLI, utiliser l'outil de sauvegarde de l'hébergeur et comparer les options
dans une session privée; ne jamais publier les captures contenant les webhooks.

## 2. Vérifier l'archive

Comparer son SHA-256 avec le fichier `.zip.sha256` livré. Sous PowerShell :

```powershell
Get-FileHash .\wp-discord-post-blackfury-2.1.1.zip -Algorithm SHA256
```

Elle doit contenir `wp-discord-post/wp-discord-post.php`, pas un dossier nommé
`wp-discord-post-blackfury-main`. Ne pas utiliser le téléchargement automatique
du code source GitHub comme paquet WordPress.

## 3. Remplacer sur préproduction, puis sur production autorisée

Sur la copie de préproduction, bloquer d'abord le trafic vers les canaux réels
et utiliser un webhook réservé aux essais. Conserver en privé la configuration
originale; ne jamais la copier dans ce dépôt.

1. Désactiver WP Discord Post, **sans cliquer sur Supprimer**.
2. Dans Extensions → Ajouter → Téléverser, envoyer le ZIP et choisir le
   remplacement de l'extension existante après avoir vérifié le nom du dossier.
   Si le remplacement n'est pas proposé, arrêter pour vérifier le chemin; ne pas
   installer une seconde copie sous un autre nom.
3. Réactiver `WP Discord Post - BlackFury` version 2.1.1.
4. Sans enregistrer de nouveaux réglages, refaire l'empreinte et la comparer.
   Elle doit être identique si aucune publication ni configuration n'a changé.
   Vérifier aussi visuellement les formats, cases et champs des intégrations.

Alternative opérateur : remplacer uniquement le dossier `wp-discord-post` par
celui extrait du ZIP, après désactivation et sauvegarde; garder exactement le
nom du dossier/fichier principal. Ne pas utiliser une commande de suppression
récursive construite avec un chemin non vérifié.

## 4. Recette avant réouverture

- La page des réglages s'ouvre sans erreur; sauvegarder une modification sans
  importance, recharger, puis restaurer sa valeur. Vérifier les cases et formats.
- Un abonné ne peut pas administrer l'extension. L'ancienne URL `dank_meme=yes`
  ne doit rien envoyer. Le bouton de test doit demander une session administrateur
  et envoyer uniquement après clic avec un nonce valide.
- Sur le canal d'essai, publier un article identifié comme test, **constater sa
  réception dans Discord**, puis le modifier : aucun doublon attendu. Un log ou
  le marqueur en base ne constitue pas cette preuve, notamment en cas de HTTP 429.
- Tester un article planifié et chaque intégration réellement utilisée. Examiner
  les erreurs PHP. Pour Giphy, utiliser une clé d'essai et vérifier sa réception.
- Refaire la recette sous les versions réelles de PHP, WordPress et MySQL/MariaDB.
  Le laboratoire fourni utilise SQLite et des réponses HTTP synthétiques.

Appliquer ensuite la même séquence en production **après autorisation**, avec les
réglages originaux. Reprendre les publications suspendues et contrôler celles
survenues pendant la bascule. Ne pas les renvoyer toutes à l'aveugle.

## 5. Retour arrière

En cas d'échec, laisser les publications suspendues et désactiver le fork.
Restaurer les fichiers sauvegardés dans le dossier initial, sans supprimer les
options ni les marqueurs. Comparer l'empreinte. La version 2.1.0 étant vulnérable,
la laisser désactivée tant que sa protection n'est pas assurée; le retour arrière
sert au rétablissement contrôlé, pas à une solution de sécurité durable.

Si les réglages ont changé, restaurer uniquement les valeurs concernées depuis
la sauvegarde privée avec l'aide de l'opérateur. Restaurer toute la base n'est
acceptable que si cela ne détruit aucune publication, commande ou donnée créée
depuis la sauvegarde. Ne jamais supprimer globalement les marqueurs d'envoi.

## Mises à jour

L'en-tête empêche les mises à jour WordPress.org sur WordPress 5.8+. Les nouvelles
versions du fork doivent être suivies et installées manuellement selon cette
procédure. Les scanners peuvent continuer à signaler le slug historique : leur
fournir le diff et l'audit; ne pas désactiver globalement leurs alertes.
