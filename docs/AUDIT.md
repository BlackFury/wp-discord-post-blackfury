# Audit ciblé — 11 novembre 2026

## Sources et portée

Source examinée : tag SVN officiel 2.1.0, révision 1928520, 12 fichiers.
Relecture du PHP d'administration, de l'action mème, du transport, du formatage,
des publications et des intégrations de formulaires/WooCommerce.
Inventaire des entrées HTTP, sorties HTML, Settings API, droits/nonces,
appels réseau, options et métadonnées. Aucun site tiers n'a été testé.

[Patchstack](https://patchstack.com/database/wordpress/plugin/wp-discord-post/vulnerability/wordpress-wp-discord-post-plugin-2-1-0-reflectef-cross-site-scripting-xss-vulnerability)
référence CVE-2025-26554 pour les versions jusqu'à 2.1.0. L'avis consulté ne
précise pas le fichier ni un reproducer public permettant de certifier la
correspondance exacte. L'attaquant peut préparer un lien sans compte; le chemin
administratif identifié exige cependant que la victime ait accès aux réglages.

## Défauts reproduits et corrections

| Défaut | Preuve locale | Correction |
| --- | --- | --- |
| Reflected XSS : `class-wp-discord-post-admin.php`, ligne 589 upstream, `add_query_arg('dank_meme','yes')` sans base ni échappement | Un marqueur inerte issu de l'URI devient un attribut HTML inattendu dans le DOM du rendu local | Suppression du lien, bouton associé à un formulaire POST séparé et action `admin_url('admin-post.php')` échappée |
| Action mème exécutée depuis `admin_init` sur GET, sans droit ni nonce | Appels HTTP observés avec nonce absent/invalide, abonné, utilisateur anonyme via appel direct du gestionnaire, et ancien GET | Route `admin_post_wp_discord_post_send_dank_meme`; POST + `manage_options` + nonce scalaire valide avant le réseau; pas de route nopriv |
| Clé Giphy transmise en HTTP | URL construite en clair dans le gestionnaire | HTTPS et `esc_url_raw` pour la requête |
| Jeton et contenu privés dans les logs | Marqueurs synthétiques retrouvés dans le journal lors d'un envoi/échec | Statuts fixes, aucune impression des en-têtes, du message, des embeds ou du détail d'erreur |

La reproduction XSS démontre une injection d'attribut dans le HTML généré; aucun
navigateur réel ni serveur de production n'a exécuté un script d'attaque. La
normalisation d'URI par le navigateur, le serveur ou un pare-feu peut changer
la forme exploitable. Le correctif supprime la source dangereuse elle-même.

La documentation WordPress indique que
[add_query_arg ne réalise pas l'échappement HTML](https://developer.wordpress.org/reference/functions/add_query_arg/).
L'action d'envoi vérifie la méthode (405), puis les droits et le nonce (403),
avant toute requête. La redirection POST utilise une destination fixe et HTTP 303.
Les nonces WordPress ne sont pas à usage unique; deux clics autorisés peuvent
envoyer deux mèmes. Le bouton n'enregistre pas les modifications non sauvegardées
du formulaire de réglages, comme l'ancien lien.

## Réglages et interfaces conservés

Le formulaire de réglages reste géré par `options.php`, `settings_fields` et
`register_setting`, avec `manage_options`. Les sorties de champs utilisent
`esc_attr`, `esc_url` ou `esc_textarea`. Aucun nouveau stockage, aucune suppression
de réglage, aucun hook d'activation/désinstallation destructif n'est ajouté.
Les noms `wp_discord_*`, `_wp_discord_post_published`, classes, filtres, et le
chemin `wp-discord-post/wp-discord-post.php` sont conservés.

Le mécanisme [Update URI introduit dans WordPress 5.8](https://make.wordpress.org/core/2021/06/29/introducing-update-uri-plugin-header-in-wordpress-5-8/)
est fixé à `false`, sans inventer d'URL GitHub. Il ne fournit pas de mises à jour automatiques du fork.

## Limites héritées, non corrigées dans cette version ciblée

- `WP_Discord_Post_HTTP::process()` considère toute réponse non-`WP_Error` comme
  un envoi réussi, même un HTTP 400/401/429, puis inscrit le marqueur anti-doublon.
  Une présence en base ou un log « Request sent » ne prouve pas la réception.
- Le transport transforme le bot token avec `sanitize_key`; le webhook global
  non vide prend priorité sur les webhooks spécifiques. Ces comportements anciens
  sont conservés; vérifier le canal et les éventuels besoins d'authentification.
- Les URL de webhook sont des destinations configurables par un administrateur,
  sans liste de domaines autorisés; le correctif n'est pas un durcissement SSRF
  complet. Les options n'ont pas de validation de type systématique à l'écriture.
- L'affichage du webhook et du jeton aux administrateurs est conservé. Les secrets
  sont stockés comme auparavant dans la base, et les anciens logs ne sont pas purgés.
- WooCommerce, CF7, Jetpack et Gravity Forms restent hérités, sans validation
  complète avec leurs versions actuelles. La condition historique du champ GF
  utilise `wp_discord_post_gf_settings` au lieu du drapeau d'activation.
- La logique historique date/heure et les filtres tiers peuvent affecter les
  notifications. Les envois planifiés, erreurs Discord, multisite, serveurs
  MySQL/MariaDB et navigateurs doivent être vérifiés sur l'environnement cible.

Ce périmètre justifie la validation en préproduction avant le remplacement réel.
Il ne justifie pas de masquer globalement une alerte de scanner par numéro de version.
