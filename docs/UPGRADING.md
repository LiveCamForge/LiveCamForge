# Upgrading LiveCamForge

## Before upgrading

Back up:

- the database;
- `config/local.php`;
- `storage/branding` and any other installation-owned media/configuration;
- custom language packs;
- custom provider adapters or other local code changes.

Read the stable release-specific upgrade note when one exists.

## File replacement

Do not assume that extracting a new ZIP over an old directory removes files that were deleted from the release. A previous development upgrade demonstrated that obsolete scripts can remain on disk and cause smoke-test failures or unexpected exposure.

For production, prefer a clean code-directory deployment while preserving only installation-owned files and configuration, or explicitly remove obsolete files documented by the release notes.

## Database migrations

LiveCamForge applies pending migrations through the normal bootstrap/installation lifecycle. Never delete migration history manually merely to force a migration to rerun.

## Validation

After replacing the code:

```bash
php tests/smoke.php
```

Then run a provider sync and verify the public catalog and Admin before returning the site to normal operation.

## Rollback

A code rollback does not automatically reverse database migrations. Read the version-specific upgrade note before downgrading across a release that changed schema.


## Public Demo and upgrades

LiveCamForge 1.0.1 adds Public Demo mode without changing existing standard installations. See [UPGRADE_1.0.1.md](UPGRADE_1.0.1.md) for the release-specific note.

Public Demo mode is intended to be selected during a clean dedicated demo installation. It is not a supported conversion path for an existing production installation, and a Public Demo should not later be converted into production.
