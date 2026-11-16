# Publication et mises à jour BlackFury Relay

Le code de développement reste sur GitHub. Les installations WordPress utilisent les pièces jointes aux releases stables du dépôt BlackFury/wp-discord-post-blackfury. La bêta est distribuée comme ZIP à installer manuellement.

Pour préparer une release stable, aligner la version du fichier principal et le Stable tag du readme, puis exécuter les vérifications du dépôt et tester l’installation sur une préproduction. Publier la release avec un tag de la forme vX.Y.Z et le ZIP construit par scripts/build.py sous le nom blackfury-relay-X.Y.Z.zip. Joindre son fichier SHA-256. L’archive doit contenir blackfury-relay/blackfury-relay.php.

Le canal stable consulte la dernière release stable de GitHub. Un tag seul, une release draft ou prerelease, ou une archive source ne déclenche pas de mise à jour. Après publication autorisée, vérifier la release, son fichier ZIP, le digest exposé par GitHub et l’apparition de la mise à jour dans WordPress. Vérifier la réception Discord lors de la recette avant mise en production.

**Jalon 2.5** — retirer le module de migration temporaire, son écran et ses hooks. Mettre à jour le guide, retirer le contrôle du fichier dans la liste du paquet et adapter les tests. Conserver les réglages et marqueurs propres à Relay. Le script de construction bloque la 2.5 ou une version ultérieure tant que le module est présent.
