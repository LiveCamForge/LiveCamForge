# LiveCamForge 1.0.1

> Build your own multi-provider live cam discovery and affiliate site.


LiveCamForge is an open-source PHP/MySQL application for building a multi-provider live-cam discovery website. It normalizes different provider feeds into one catalog and provides public discovery pages, provider-specific players, affiliate redirects, optional conversion tracking, SEO landing pages, geographic safeguards and an administrator interface.

LiveCamForge does not host or retransmit provider content. It uses the feeds, images, streams, widgets and destination URLs made available by the integrations you configure.

## Included integrations

Direct integrations currently include Chaturbate, BongaCams, CAM4, LiveJasmin and Stripchat. LiveCamForge also supports multiple commercial sources delivered through CrakRevenue, including MyFreeCams, Jerkmate/Streamate, Chaturbate, LiveJasmin/AWEmpire, Stripchat, ImLive and BongaCams where the affiliate account is authorized for them.

## Highlights

- multi-provider or single-provider public catalog;
- filters for provider, performer type, country, age, tags and room state;
- provider-specific player modes and affiliate redirects;
- manual Admin synchronization plus scheduled/cron and optional CLI workflows, with locking and history;
- optional conversion attribution, postback endpoints and CAM4/TUNE polling;
- multilingual public UI and localized editorial content;
- SEO landing manager, optimized bundled discovery pages, performer recruitment and optional webmaster recruitment, plus sitemap, robots and canonical URLs;
- configurable retention, geo restrictions and media policies;
- themes, branding and homepage content from Admin;
- performance diagnostics for catalog requests and provider syncs.

## Requirements

- PHP 8.2 or newer;
- MySQL or MariaDB;
- PDO MySQL;
- outbound HTTPS access for provider feeds and APIs;
- Apache with `mod_rewrite` for the bundled `.htaccess` rules, or equivalent Nginx configuration;
- HTTPS in production.

Shell/SSH access is **not required** for normal installation or administration. A hosting control-panel scheduler/cron facility is recommended for unattended production synchronization.

For large catalogs, LiveCamForge recommends at least a 64 MiB InnoDB buffer pool and an 8 MiB `max_allowed_packet`. These are recommendations rather than hard installation requirements. See [Performance](docs/PERFORMANCE.md).

## Quick start

1. Extract the release into the web root.
2. Create a MySQL/MariaDB database and user if your hosting panel requires it.
3. Open LiveCamForge in the browser and complete the first-run installer.
4. Sign in to Admin and review the provider, catalog and public-site settings.
5. Run the first provider synchronization from **Admin → Operations**.
6. Verify the public catalog, performer page and affiliate redirect.
7. Configure scheduled synchronization using the hosting control panel or scheduler.
8. Optionally configure LiveCamForge conversion tracking if you want internal conversion/provider-performance reporting.

If shell/SSH access is available, optional CLI commands can be used for smoke testing, manual synchronization and advanced diagnostics. They are documented in [Installation](docs/INSTALLATION.md) and [Cron and Synchronization](docs/CRON_AND_SYNC.md).

## Public Demo mode

LiveCamForge 1.0.1 can be installed as a separate **Public Demo** with two local fictional providers (Demo Alpha and Demo Beta), 80 fictional profiles, an explorable Administration interface, server-side restrictions on real integrations, controlled recruitment CTAs, noindex handling, and a one-click demo reset.

A Public Demo is **not intended to be converted into production**. Create a new standard installation for a live site and never store real provider/API/affiliate credentials in the demo.

See [`docs/PUBLIC_DEMO.md`](docs/PUBLIC_DEMO.md) for installation, behavior, security restrictions, reset and production-migration guidance.

## Documentation

The developer documentation includes a file-by-file provider integration tutorial covering direct providers, postbacks/conversion polling, and CrakRevenue-backed sources.


The canonical LiveCamForge documentation is maintained **in English only** and versioned with the source code.

Start here: **[LiveCamForge Documentation](docs/README.md)**.

Key guides:

- [Getting Started](docs/GETTING_STARTED.md)
- [Installation](docs/INSTALLATION.md)
- [Administrator Manual](docs/ADMIN_GUIDE.md)
- [Configuration Reference](docs/CONFIGURATION.md)
- [Providers](docs/providers/README.md)
- [Cron and Synchronization](docs/CRON_AND_SYNC.md)
- [Conversion Tracking](docs/CONVERSION_TRACKING.md)
- [Performance](docs/PERFORMANCE.md)
- [Deployment](docs/DEPLOYMENT.md)
- [Security](docs/SECURITY.md)
- [Troubleshooting](docs/TROUBLESHOOTING.md)
- [Upgrading](docs/UPGRADING.md)
- [Development](docs/DEVELOPMENT.md)

## Documentation policy

The repository is the documentation source of truth. A future documentation section on `livecamforge.com` should publish the same content rather than maintain a separate manual. Documentation changes should therefore be reviewed and released together with the code they describe.

## Release status

LiveCamForge 1.0.1 is the current stable release. See [CHANGELOG.md](CHANGELOG.md) for release details.
