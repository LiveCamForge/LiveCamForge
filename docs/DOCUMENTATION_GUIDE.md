# Documentation Guidelines

## One canonical language

LiveCamForge maintains its project documentation in English only. Public UI language packs are a separate feature and do not imply translated project manuals.

## Source of truth

The Markdown files under `docs/` are canonical. Future documentation pages on `livecamforge.com` should render, import or otherwise derive from this content.

## Audience separation

Write user-facing operational guidance for administrators without assuming PHP knowledge. Keep implementation details in development/provider guides. Do not force a site operator to read source-code documentation to configure a normal feature.

## Stable page locations

Prefer updating an existing canonical guide over creating a new release-specific manual. Use `UPGRADE_*.md` only for version-specific upgrade actions that should remain in historical release records.

## Provider documentation

Provider pages should consistently cover:

- prerequisites and account requirements;
- where credentials come from;
- Admin fields;
- feed/sync behavior;
- player/embed behavior;
- affiliate routing;
- conversion/postback support;
- known limitations;
- validation steps;
- troubleshooting.

## Screenshots

When screenshots are added, store them under a stable `docs/assets/` path and avoid embedding secrets, account IDs or environment-specific personal data. Prefer screenshots that remain useful across minor UI changes.

## Release discipline

A feature is not documentation-complete until the relevant canonical guide has been updated. Changelog entries describe what changed; they are not a substitute for the manual.
