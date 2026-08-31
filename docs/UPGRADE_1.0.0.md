# Upgrade to LiveCamForge 1.0.0

LiveCamForge 1.0.0 promotes the validated 0.99.0 RC3 codebase to the first stable release.

## From 0.99.0 RC3

No database migration is required.

1. Back up the current application files and database.
2. Replace the application files with the 1.0.0 release.
3. Preserve your deployment-specific `config/local.php`.
4. Confirm that the Admin reports version `1.0.0`.
5. Run the normal smoke checks for the public catalog, Admin login, provider sync, affiliate redirects, recruitment flows, cron configuration, robots.txt, sitemap.xml, canonical URLs, and security headers.

There are no intentional functional changes between 0.99.0 RC3 and 1.0.0.
