# Contributing to LiveCamForge

## Before you start

Describe the problem, expected behavior and impact on existing providers. For a new provider, reference public/official provider documentation without including private credentials, tokens or feed payloads.

## Project rules

- PHP 8.2+ and `declare(strict_types=1)`.
- Keep the `LiveCamForge\\` namespace aligned with the source path.
- Use parameterized SQL for dynamic values.
- Never commit secrets, personal IP addresses or real private payloads in tests.
- Validate remote URLs through provider-specific allowlists and security helpers.
- Keep retention, caching and indexing defaults conservative.
- New commercial/affiliate capabilities must remain optional.
- Public UI strings belong in language packs.
- Canonical project documentation is written in English only.
- Update documentation and changelog together with behavior changes.

## Workflow

1. Start from the latest release/source state.
2. Keep the change focused.
3. Add or update tests.
4. Run `php tests/smoke.php`.
5. Test clean installation or upgrade paths when touching database/configuration.
6. Test the public catalog, Admin and affected providers.
7. Inspect the final archive for credentials, `config/local.php`, logs and generated cache files.

## Database migrations

Do not rewrite already released migrations. Add a new numbered migration and update `database/schema.sql` where appropriate. Mention migration/sync requirements in the changelog and upgrade notes.

## Providers

Read [Provider Development](docs/PROVIDER_DEVELOPMENT.md). An adapter must expose real capabilities, normalize upstream data and validate remote URLs. Avoid provider-specific exceptions in generic public controllers or repositories.

## Security

Do not publish credentials or sensitive exploit details in normal bug reports. Follow the project [Security Policy](SECURITY.md) for vulnerability reporting and [deployment security guidance](docs/SECURITY.md) for production hardening.

## Documentation

Read [Documentation Guidelines](docs/DOCUMENTATION_GUIDE.md). The repository Markdown is the source of truth; future web documentation should derive from it rather than fork it.

## Pull request checklist

- [ ] problem and solution described
- [ ] compatibility reviewed
- [ ] tests executed
- [ ] language-pack changes included when UI strings changed
- [ ] English documentation updated
- [ ] no released migration rewritten
- [ ] no secret/private data included
