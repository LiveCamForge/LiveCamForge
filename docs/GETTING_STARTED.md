# Getting Started

## Choose standard or Public Demo

For a real site, use the normal installer path and configure the providers you intend to operate.

For an explorable showcase without real provider credentials, use the dedicated **Public Demo mode**. It installs Demo Alpha and Demo Beta with local fictional profiles and applies server-side restrictions to real integrations.

A Public Demo is not a staging path to production. Deploy a new standard installation when you are ready to run a live site.

See [Public Demo mode](PUBLIC_DEMO.md).

## What LiveCamForge is

LiveCamForge is a PHP/MySQL application that collects available performers from supported live-cam providers, normalizes them into a common catalog and sends visitors to tracked affiliate destinations. It includes a public discovery site and an administrator interface.

It is designed for site owners who want to operate their own catalog without building separate feed, routing, SEO and tracking logic for every provider.

## What LiveCamForge does not do

LiveCamForge is not a streaming host, billing platform or performer platform. Provider video, chat, images and affiliate destinations remain controlled by the relevant provider. Availability and capabilities therefore differ by integration.

## Before you install

You should have:

- a PHP 8.2+ web hosting environment;
- a MySQL/MariaDB database;
- HTTPS for production;
- at least one provider or affiliate-network account you are permitted to use;
- preferably a scheduled-task/cron facility in the hosting control panel so production synchronization can run automatically.

Interactive shell or SSH access is **not required** for normal LiveCamForge operation. Shared-hosting users can install and manage the application from the browser/Admin interface. Shell access is useful for advanced diagnostics and optional CLI workflows when the hosting plan provides it.

## Direct providers and affiliate networks

A **direct integration** communicates with the provider's own feed/API and uses that provider's affiliate credentials.

An **affiliate-network source** is supplied through a network such as CrakRevenue. The public brand can still be displayed as the commercial service while the technical source and credentials are managed in Admin.

For networks offered through both direct and network integrations, LiveCamForge lets the administrator select the source used by the public catalog.

## Recommended first installation path

1. Install LiveCamForge using the browser installer.
2. Configure only one provider initially.
3. Run its first synchronization from **Admin → Operations**.
4. Verify the public catalog, a performer page and the affiliate redirect.
5. Add the remaining providers one at a time.
6. Optionally configure conversion tracking/postbacks if you want LiveCamForge to report conversions and provider performance internally.
7. Configure scheduled synchronization from the hosting control panel after manual synchronization is working correctly.
8. Complete the production deployment checklist.

If shell/SSH access is available, the CLI can also be used for manual synchronization, smoke tests and advanced diagnostics. See [Cron and Synchronization](CRON_AND_SYNC.md).

## Next steps

Continue with [Installation](INSTALLATION.md). After installation, use the [Administrator Manual](ADMIN_GUIDE.md) as the main operational guide.
