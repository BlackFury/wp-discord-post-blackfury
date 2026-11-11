# Provenance

- Projet : WP Discord Post, version **2.1.0**, auteur **Nicola Mustone**.
- Dépôt : https://plugins.svn.wordpress.org/wp-discord-post/
- Tag : https://plugins.svn.wordpress.org/wp-discord-post/tags/2.1.0/
- Révision figée : **1928520**, dernière modification du tag annoncée le 2018-08-22.
- Acquisition : **2026-11-11**, par parcours récursif HTTP du répertoire SVN
  `https://plugins.svn.wordpress.org/!svn/bc/1928520/wp-discord-post/tags/2.1.0/`.
- Import : **12 fichiers**, byte-for-byte, puis commit et tag Git `upstream-2.1.0`.
  Le répertoire `languages` est vide dans cette version; Git ne conserve pas les dossiers vides.
- Vérification indépendante : les 12 fichiers concordent octet pour octet avec
  `https://downloads.wordpress.org/plugin/wp-discord-post.2.1.0.zip`, téléchargé séparément.
- Empreintes SHA-256 par chemin : [upstream-sha256.json](upstream-sha256.json).

Il s'agit d'un import de contenu, **pas d'une conversion complète de l'historique
SVN en Git**. Les auteurs et dates des commits SVN ne sont pas reconstruits.
Les propriétés SVN et les ressources du répertoire `assets` hors du tag ne sont
pas incluses. Aucun fichier d'un site BlackFury n'a servi de source.

Équivalent de récupération avec un client SVN :

```sh
svn export -r 1928520 https://plugins.svn.wordpress.org/wp-discord-post/tags/2.1.0@1928520 wp-discord-post-upstream
```

Le tag Git upstream reste strictement original; les modifications du fork sont
visibles par `git diff upstream-2.1.0 -- wp-discord-post.php includes readme.txt`.
Les en-têtes PHP d'origine déclarent GPL-2.0+; le README original disait GPLv2.
Le fork conserve les notices et utilise GPL-2.0-or-later conformément aux en-têtes.
Le texte intégral GPLv2 figure dans LICENSE, acquis sur le site GNU officiel.
