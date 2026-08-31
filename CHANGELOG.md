# Changelog

All notable public release changes to LiveCamForge are documented here.

## 1.0.1 - 2026-08-31

- Completed the canonical documentation audit against the 1.0.1 codebase.
- Added the dedicated Public Demo mode documentation and lifecycle guidance.
- Added opt-in Public Demo mode with two fictional local providers and 80 fictional performer profiles.
- Public Demo blocks real provider credentials, postbacks and recruitment integrations server-side.
- Public Demo disables sitemap output, applies noindex safeguards and provides an Admin reset action.
- Normal installations remain unchanged when Demo mode is disabled.
- No database migration is required from 1.0.0.

## 1.0.0 - 2026-08-31

- First stable LiveCamForge release.
- Includes the multi-provider discovery engine, provider integrations, public performer pages, configurable player modes and affiliate redirects.
- Includes SEO landings, performer recruitment, optional webmaster recruitment, sitemap/robots handling and multilingual public UI.
- Includes Admin configuration, manual and scheduled synchronization, conversion/postback tooling, security hardening, caching and performance diagnostics.
- Includes the browser installer and shared-hosting deployment workflow.
- Validated on a real shared-hosting deployment before promotion to stable.
- No database migration is required from 0.99.0 RC3.

## Pre-1.0 development

LiveCamForge went through an internal 0.x development and release-candidate cycle before the first public stable release. Historical internal micro-release upgrade notes are intentionally not shipped with the public repository; Git history and stable release notes are the public source of change history from 1.0 onward.
