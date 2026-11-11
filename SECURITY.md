# Sécurité

Ce fork corrige les chemins identifiés dans [l'audit](docs/AUDIT.md). Il ne fournit
pas de garantie d'absence d'autres vulnérabilités, de certification CVE, ni de
compatibilité avec toutes les versions des intégrations tierces.

Version maintenue dans ce projet : 2.1.1. La version upstream 2.1.0 est conservée
uniquement comme référence historique et ne doit pas être déployée.

Avant d'ouvrir le dépôt public, le mainteneur doit activer **Private vulnerability
reporting** dans les réglages de sécurité GitHub et vérifier que le canal est
opérationnel. L'adresse du dépôt et ce canal ne sont pas encore établis dans
cette livraison locale. Ne pas envoyer de secrets, de dumps ou de nouvelles
preuves d'exploitation via une issue publique; attendre un canal privé vérifié.

Fournir en privé : version exacte, environnement, chemin affecté, reproduction
sur données synthétiques, impact et éventuel correctif. Ne jamais joindre les
webhooks ou jetons d'un site réel. Aucune durée de traitement n'est promise ici.

La journalisation historique peut avoir exposé des secrets avant la migration.
Examiner les anciens journaux en privé; si un secret a été exposé, le remplacer
dans Discord/Giphy puis dans WordPress. Cette version ne purge pas ces journaux.
