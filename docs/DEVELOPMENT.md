# Development

## Architecture

LiveCamForge separates provider-specific adapters from normalized catalog, persistence, filtering, geo, landing and tracking services. Provider peculiarities should remain behind provider interfaces rather than leak into generic catalog code.

## Local setup

A typical local stack uses PHP 8.2+, Apache and MySQL/MariaDB. Install LiveCamForge through the normal first-run installer so migrations and local configuration match a real deployment.

## Smoke test

Run after every change that can affect application behavior or packaging:

```bash
php tests/smoke.php
```

A release should also pass PHP syntax checks across all distributed PHP files.

## Database migrations

Add forward migrations under `database/migrations/`. Do not edit already released migrations merely to change current behavior. Upgrade paths must remain reproducible from older supported releases.

## Performance work

Use measurement before optimization. Public request profiling and CLI sync profiling are built into LiveCamForge specifically so changes can be compared against a repeatable baseline.

## Providers

Read [PROVIDER_DEVELOPMENT.md](PROVIDER_DEVELOPMENT.md) before adding an adapter. New providers should normalize to the common performer model and must not require provider-specific branches throughout the public catalog.

## Documentation

English Markdown under `docs/` is the documentation source of truth. New features should update the relevant guide in the same release rather than relying only on changelog entries.
