# Upgrade to LiveCamForge 1.0.1

No database migration is required.

1.0.1 adds an opt-in public Demo mode. Existing installations continue to behave like 1.0.0 because `demo_mode.enabled` defaults to `false`.

For a dedicated demo installation, enable **Install as public demo** during a clean install. The installer uses `demo_alpha` and `demo_beta`, with 40 fictional local profiles each. Real provider integrations are not required and protected Admin operations are blocked server-side.


## Public Demo lifecycle

Public Demo mode is intended for a **new, dedicated demo installation**. Do not convert an existing live installation into a Public Demo merely to test the feature, and do not convert a Public Demo into production later.

For a live site, use a separate standard installation and configure its real provider/affiliate credentials there.

See [Public Demo mode](PUBLIC_DEMO.md) for the complete installation, security, reset, SEO and recruitment behavior.
