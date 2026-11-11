# Contribuer

Les modifications sont proposées sous GPL-2.0-or-later. Conserver les crédits
de Nicola Mustone, les notices d'origine et la provenance des nouveaux fichiers.

1. Partir d'une branche dédiée; décrire le problème et le comportement attendu.
2. Écrire une régression sur données synthétiques, observer son échec, appliquer
   le correctif minimal et exécuter toute la suite du README.
3. Pour l'administration, vérifier les droits et le nonce avant tout effet;
   échapper les sorties selon leur contexte. Aucun secret dans les logs.
4. Préserver les options et `_wp_discord_post_published`; documenter toute migration.
5. Construire le ZIP, examiner son contenu, faire relire le diff, puis tester
   sur préproduction avec les versions réelles du site.

Les tests interceptent le réseau : leur réussite ne prouve pas une livraison
Discord. Les contributions de sécurité suivent SECURITY.md et ne publient pas
de nouvelles informations exploitables avant coordination privée.
