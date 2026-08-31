# LiveCamForge Documentation

This directory contains the canonical documentation for LiveCamForge 1.0.1.

**Documentation language:** English only. The project intentionally maintains a single documentation language so configuration, troubleshooting and technical guidance do not diverge between translations.

## I want to install LiveCamForge

Read these in order:

1. [Getting Started](GETTING_STARTED.md)
2. [Installation](INSTALLATION.md)
3. [Administrator Manual](ADMIN_GUIDE.md)
4. [Cron and Synchronization](CRON_AND_SYNC.md)
5. [Deployment](DEPLOYMENT.md)

## I want to publish a Public Demo

Read **[Public Demo mode](PUBLIC_DEMO.md)** for the dedicated demo installation workflow, Demo Alpha/Beta behavior, Administration restrictions, reset/synchronization behavior, SEO protections, recruitment CTAs, and the requirement to use a separate standard installation for production.

## I want to configure providers or affiliate tracking

- [Provider Guides](providers/README.md)
- [Configuration Reference](CONFIGURATION.md)
- [Conversion Tracking](CONVERSION_TRACKING.md)
- [Troubleshooting](TROUBLESHOOTING.md)

## I operate a large catalog

- [Performance](PERFORMANCE.md)
- [Cron and Synchronization](CRON_AND_SYNC.md)
- [Troubleshooting](TROUBLESHOOTING.md)

## I want to develop or extend LiveCamForge

- [Development](DEVELOPMENT.md)
- [Provider Development](PROVIDER_DEVELOPMENT.md)
- [Security](SECURITY.md)
- [Creating language packs](TRANSLATIONS.md)
- [Contributing](../CONTRIBUTING.md)
- [Documentation Guidelines](DOCUMENTATION_GUIDE.md)

## I am upgrading an existing installation

Start with [Upgrading](UPGRADING.md). Stable release-specific notes are included when an upgrade requires additional guidance.

## Source-of-truth policy

The Markdown files in this repository are the documentation source of truth. When documentation is later published on `livecamforge.com`, the website should render or derive from this material rather than maintain a second independent manual.

## Developer integration tutorial

[Provider Development](PROVIDER_DEVELOPMENT.md) is a step-by-step direct-provider and CrakRevenue source integration tutorial using the real LiveCamForge classes, files and method signatures.
