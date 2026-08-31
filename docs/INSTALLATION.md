# Installation

## Requirements

LiveCamForge requires PHP 8.2+, PDO MySQL and MySQL/MariaDB. The web process must be able to write to `config/` during installation and to the required locations under `storage/` during operation. Production installations should use HTTPS.

The database may be pre-created by a hosting control panel. LiveCamForge can install into an existing empty database and does not require the application account to have global `CREATE DATABASE` privileges.

Interactive shell/SSH access is not required for the browser installer or normal administration.

## Recommended database settings for large catalogs

These are performance recommendations, not installer blockers:

```ini
innodb_buffer_pool_size=64M   # or higher when memory allows
max_allowed_packet=8M        # or higher
```

Admin → Operations reports the current values after installation. Shared-hosting customers usually cannot change these variables directly; that is acceptable when the host already provides suitable values.

## Browser installer

1. Extract the release into the web directory.
2. Open the application root in a browser. If `config/local.php` does not exist, LiveCamForge redirects to `install/`.
3. Review the preflight checks.
4. Enter database host, port, database name and credentials.
5. Configure site name, public URL and timezone.
6. Choose the installation type:
   - for a normal/live installation, leave **Install as public demo** unchecked and select one or more initial providers;
   - for a dedicated public demonstration, enable **Install as public demo**. Demo Alpha and Demo Beta are configured automatically and real provider choices are locked.
7. On a normal installation, enter the credentials requested for the selected providers. Do not enter real provider/API/affiliate credentials into a Public Demo installation.
8. Create the administrator account. The password must contain at least 12 characters; the strength indicator provides additional guidance.
9. Start the installation.

The installer creates the base schema, applies all migrations included in the release, stores the initial operational settings, creates the administrator account and writes `config/local.php` atomically.

## Public Demo installations

Public Demo mode is a separate installation profile for evaluation/showcase environments. It uses two local fictional sources, Demo Alpha and Demo Beta, and does not require external provider APIs.

A Public Demo installation is **not intended to be converted into production**. If you later want a live affiliate site, create a new standard installation with its own database/configuration and real provider credentials.

See [Public Demo mode](PUBLIC_DEMO.md) for the full behavior, security restrictions, reset process and SEO handling.

## Multi-provider first run

When more than one real provider is selected, the initial public catalog is configured in combined mode. The Demo provider is not used as a live source when real providers are selected.

Provider source choices can be changed later from Admin → Public Catalog and credentials can be changed from Admin → Integrations.

## First validation

For a typical shared-hosting installation, validation can be completed from the browser:

1. Sign in to Admin.
2. Open **Admin → Integrations** and confirm the configured provider is ready.
3. Open **Admin → Public Catalog** and confirm that the intended source is enabled.
4. Run the first provider synchronization from **Admin → Operations**.
5. Open the public catalog, a performer page and the affiliate redirect.

If shell/SSH access is available, an optional CLI smoke test provides additional technical validation:

```bash
php tests/smoke.php
```

The CLI can also be used as an alternative manual synchronization method:

```bash
php bin/sync.php              # all enabled providers
php bin/sync.php stripchat    # one provider
```

CLI access is optional for normal shared-hosting operation. Provider identifiers are shown in Admin and in CLI sync output when the CLI is used.

## After installation

Before opening the site to visitors:

- review Admin → Integrations;
- confirm the intended catalog sources in Admin → Public Catalog;
- configure public languages and fallback language;
- review Appearance and homepage content;
- test performer pages, players and affiliate redirects;
- configure scheduled synchronization using the hosting control panel or scheduler;
- optionally configure postbacks/conversion polling if you want to monitor conversions and provider performance inside LiveCamForge;
- run the deployment and security checks.

Conversion tracking inside LiveCamForge is optional. Affiliate redirects and the public catalog can operate without postback/conversion polling configuration; provider/network dashboards can still be used as the external source of conversion reporting.

## Protecting local configuration

`config/local.php` contains host-specific credentials and secrets. Do not commit or publish it. The installer is locked after installation by the presence of the local configuration.

For Nginx, review [NGINX_SECURITY_EXAMPLE.md](NGINX_SECURITY_EXAMPLE.md), because `.htaccess` files are Apache-specific.

## Next steps

- [Administrator Manual](ADMIN_GUIDE.md)
- [Configuration Reference](CONFIGURATION.md)
- [Cron and Synchronization](CRON_AND_SYNC.md)
- [Deployment](DEPLOYMENT.md)
