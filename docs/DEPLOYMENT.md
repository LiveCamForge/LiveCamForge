# Production Deployment

## Pre-deployment checklist

Before publishing LiveCamForge verify PHP 8.2+, PDO MySQL, MySQL/MariaDB access, HTTPS, outbound HTTPS and writable runtime directories. For unattended catalog updates, a hosting control-panel scheduler/cron facility is strongly recommended.

Interactive shell/SSH access is not required for normal deployment or administration.

## Recommended deployment flow

1. Create the production database and user.
2. Upload a clean LiveCamForge release.
3. Complete the installer over HTTPS.
4. Configure provider credentials and catalog sources.
5. Validate the installation from Admin; if shell/SSH access is available, optionally run the CLI smoke test for additional technical validation.
6. Run initial provider synchronizations from **Admin → Operations**; CLI provider syncs are an optional alternative when shell access is available.
7. Configure scheduled synchronization using the hosting control panel or another server-side scheduler.
8. Verify public catalog filters, performer pages, players and affiliate redirects.
9. Optionally configure and externally test conversion/postback integrations if you want LiveCamForge to track conversions and provider performance internally.
10. Review logs and security controls.

## Postbacks and conversion integrations

This section applies only when LiveCamForge's internal conversion reporting is enabled.

Public deployment is when real callback testing becomes possible. Test every enabled provider/network conversion path that you intend to use, not only one network. Validate secret/signature handling, endpoint routing, goal mapping, click/SID attribution, deduplication and troubleshooting behavior.

CAM4 uses TUNE conversion polling rather than the same postback model as every other provider. Validate its production API credentials and reporting date windows separately when CAM4 conversion collection is enabled.

Conversion ingestion is optional; the public catalog and affiliate redirects do not depend on it.

## Scheduled synchronization

Use the scheduler supplied by the hosting environment as the primary unattended synchronization mechanism. Many shared hosts expose Cron Jobs/Scheduled Tasks without providing an interactive terminal.

Admin → Operations remains the standard manual synchronization path. Large combined catalogs should not depend on a browser tab as their unattended scheduler. See [CRON_AND_SYNC.md](CRON_AND_SYNC.md) for command-based cron, control-panel and CLI scenarios.

## Database

Review Admin → Operations after deployment. The database recommendations are advisory, but an extremely small InnoDB buffer pool or packet limit can materially reduce large-catalog performance.

## HTTPS and web-server protection

Do not expose private configuration, tests, templates, logs or CLI scripts. Apache rules are included in the distribution. Nginx users must reproduce the same restrictions; see [NGINX_SECURITY_EXAMPLE.md](NGINX_SECURITY_EXAMPLE.md).

The bundled root `.htaccess` redirects non-local HTTP requests to HTTPS. `localhost` and `127.0.0.1` are excluded so local development can continue over HTTP. Public installations should complete the installer with their final HTTPS base URL.

## Final acceptance

Before launch verify desktop/mobile behavior, public languages, canonical URLs, robots/sitemap, provider fallback behavior, geo safeguards and a complete scheduled-sync cycle. If internal conversion tracking is enabled, include its real callback/polling path in final acceptance as well.
