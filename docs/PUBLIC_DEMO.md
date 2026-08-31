# Public Demo mode

LiveCamForge 1.0.1 includes an opt-in **Public Demo mode** for publishing an explorable demonstration installation without connecting real cam providers or storing real affiliate/API credentials.

## When to use it

Use Public Demo mode only for a demonstration, evaluation, training, or showcase installation.

A Public Demo installation is intentionally isolated from production integrations. It is designed to let visitors explore the public catalog and Administration interface while preventing the demo from becoming a working affiliate site.

> **A Public Demo installation is not an upgrade path to production.**
>
> To deploy a live site, perform a new standard LiveCamForge installation. Do not enter real provider, affiliate, postback, or API credentials into a Public Demo installation.

There is no supported **Convert to production** action in Administration.

## Installation

During the normal installer, enable:

**Install as public demo**

When this option is selected, the normal initial-provider choices are cleared and locked. LiveCamForge configures the two Public Demo sources automatically:

- **Demo Alpha**
- **Demo Beta**

Do not configure real provider credentials for this installation.

Public Demo mode is opt-in. Standard installations keep `demo_mode.enabled` disabled and retain the normal provider/integration behavior.

## Demo catalog

The demo uses local fictional data and does not call external provider APIs.

Each demo provider contains 40 fictional performer profiles, for a total of 80 profiles. The supplied artwork is local lightweight cartoon SVG artwork rather than photographs of real performers.

Demo Alpha and Demo Beta are the only catalog sources in Public Demo mode. They remain selected and cannot be replaced with real providers through Administration.

The legacy `demo` provider remains an internal compatibility feature for older LiveCamForge behavior, but it is not part of the Public Demo interface.

## Administration

Public Demo mode is deliberately explorable. Safe presentation and editorial settings can be changed so visitors can see how LiveCamForge behaves.

Examples include appearance settings, supported catalog presentation settings, landing-page content, headings, FAQ content, and other non-sensitive presentation options.

The Administration interface displays a **Demo mode is active** notice so the environment cannot easily be mistaken for a production installation.

### Protected operations

Operations that could turn the demonstration into a real provider/affiliate installation are restricted. Real provider credentials and sensitive integration operations cannot be activated through the demo.

Protection is enforced server-side; disabled controls in the browser are not the security boundary. Attempts to bypass the interface and submit restricted operations directly are rejected or constrained by the Demo-mode policy.

In particular:

- real provider sources cannot replace Demo Alpha/Beta;
- real provider configuration/credentials cannot be saved for use by the demo;
- sensitive provider/integration tests such as postback tests are blocked;
- real recruitment destinations cannot be activated;
- Demo Alpha/Beta remain the required local catalog sources.

Because LiveCamForge is open source, a server owner can of course modify the source or local configuration directly. Such modifications are outside the supported Public Demo mode.

## Recruitment landing pages

The **Become a model** and **For webmasters** landing pages remain editable in Public Demo mode so the feature can be demonstrated.

Editorial content such as titles, descriptions and FAQ content can be changed and previewed.

External destinations are controlled by the demo:

- Demo Alpha and Demo Beta model-recruitment CTA: `https://livecamforge.com/become-a-model/`
- Webmaster CTA: `https://livecamforge.com/for-webmasters/`

These destinations are fixed in Public Demo mode. Real provider recruitment links are disabled.

This lets the demo show the complete landing-page experience without accepting visitor-supplied affiliate destinations or real provider recruitment configuration.

## Synchronization

Synchronization in Public Demo mode operates only on the local Demo Alpha and Demo Beta fixtures. No real provider API is required.

A normal demo synchronization imports:

- 40 Demo Alpha profiles
- 40 Demo Beta profiles

Because the data is local, synchronization should complete very quickly.

## Reset Demo

Administration provides **Reset demo** for returning the demonstration to its expected baseline.

The reset is intentionally destructive with respect to demo customizations. Treat a Public Demo installation as a disposable demonstration environment rather than a place for persistent production content.

Resetting also performs a fresh synchronization of Demo Alpha and Demo Beta, so the catalog is immediately repopulated with the 80 fictional profiles. These synchronization runs are recorded with the `demo-reset` trigger.

## Search-engine handling

Public Demo installations are not intended to compete with the real project/site in search results.

Demo mode therefore applies search-engine protections including:

- `X-Robots-Tag: noindex, nofollow`;
- a restrictive `robots.txt`;
- public sitemap generation disabled for the demo.

These controls are part of Demo mode and should not be copied to a normal production installation.

## Scheduled tasks

The production cron reminder is not shown in Public Demo mode. The demo providers use local fixtures and the demo can be synchronized/reset directly for demonstration purposes.

A normal production installation should instead follow the deployment documentation for its scheduled synchronization and, when applicable, conversion polling.

## Security and deployment recommendations

Even though sensitive operations are restricted, deploy a Public Demo as a separate installation and database from production.

Recommended practice:

- use a dedicated subdomain such as `demo.example.com`;
- use a separate database;
- do not reuse production secrets;
- do not paste real provider/API/affiliate credentials into the demo;
- keep the Administration password strong;
- keep LiveCamForge and the hosting runtime updated;
- use **Reset demo** whenever you need to restore the public demonstration baseline.

## Moving from evaluation to production

If the demo convinces you to deploy LiveCamForge for a real site:

1. create a separate standard LiveCamForge installation;
2. leave **Install as public demo** unchecked;
3. configure the final canonical Base URL;
4. configure only the real providers you intend to use;
5. add credentials and affiliate settings to that production installation;
6. configure and verify the required scheduled tasks;
7. complete the deployment/security checklist before launch.

Do not convert the existing Public Demo installation into the live site.
