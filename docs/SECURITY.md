# Security

## Trust boundaries

LiveCamForge processes untrusted public input and remote provider data. Provider feeds, browser parameters, postback requests and embedded third-party content must not be treated as trusted solely because they come from an expected integration.

## Secrets

Keep `config/local.php` private. Do not commit database passwords, API keys, affiliate validation salts, bearer tokens or postback secrets. Admin secret fields are write-only by design.

## Administrator sessions

Use HTTPS in production, strong administrator passwords and secure cookies where supported by the deployment. The bundled Apache root `.htaccess` redirects non-local HTTP requests to HTTPS while leaving `localhost` and `127.0.0.1` available for local HTTP development. LiveCamForge includes session timeout and login-rate protection; do not weaken these controls for convenience on a public site.

## Postbacks

Use independent strong secrets, validate the expected provider/network and reject oversized or malformed requests. Conversion endpoints should not expose secret material in error messages or logs.

## Outbound URLs and media

Remote URLs are subject to provider/adaptor validation and SSRF-oriented restrictions. Do not bypass host/scheme allowlists to make an unsupported provider URL work.

## Web-server access

Private/internal paths such as `config/`, `storage/`, `tests/`, `templates/` and CLI scripts must not be publicly browsable. Apache protection is bundled; Nginx deployments need equivalent explicit rules.

## Debugging

Production errors should remain generic to visitors. Use protected logs for diagnostic detail. Disable debug mode after troubleshooting.

## Responsible deployment

The operator is responsible for provider terms, affiliate-network requirements, privacy disclosures, age/access requirements and applicable law in the deployment jurisdiction. LiveCamForge provides technical controls; it does not replace legal or compliance review.

## Release-candidate deployment checks

LiveCamForge applies its application-level protections independently of the hosting provider where practical. The Admin uses strict, cookie-only sessions, CSRF protection, secure cookie attributes when HTTPS is detected, login throttling and anti-clickjacking headers. Public application responses also send SAMEORIGIN / `frame-ancestors 'self'` protection.

Server-level behavior still varies between Apache, LiteSpeed, Nginx, reverse proxies and shared hosting. Before production use, verify HTTPS redirects, HSTS policy if desired, Host-header handling, and that private paths such as `config/`, `storage/`, `database/`, `tests/` and `app/` cannot be downloaded. Treat HSTS as recommended deployment hardening rather than a universal application requirement.


## Public Demo isolation

Public Demo mode blocks sensitive provider/integration operations server-side, not only by disabling browser controls. Nevertheless, treat a public demo as a disposable, isolated environment: use a separate database/configuration, never reuse production secrets and never enter real provider/API/affiliate credentials.

A Public Demo is not a production staging state and should not be converted into a live installation. See [PUBLIC_DEMO.md](PUBLIC_DEMO.md).
