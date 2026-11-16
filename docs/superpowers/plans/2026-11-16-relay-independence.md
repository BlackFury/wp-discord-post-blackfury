# BlackFury Relay standalone distribution

- Install under `blackfury-relay/blackfury-relay.php`, keeping upstream licence and attribution. Use independent option, metadata, class, hook and translation names.
- Provide a protected, explicit same-site migration. Copy supported historical options and old delivery markers in batches without overwriting Relay values. Keep the existing installation and all source data for rollback. Prevent both senders from operating together under their supported basenames.
- Discover newer stable GitHub releases through the native WordPress update UI. Validate the official ZIP URL and SHA-256 from release metadata and honor WordPress's automatic-update preference.
- Keep the migration code isolated and make CI reject a 2.5 release until it has been retired. Verify migration, no duplicate sends, update acceptance/rejection and archive installation on disposable WordPress.
