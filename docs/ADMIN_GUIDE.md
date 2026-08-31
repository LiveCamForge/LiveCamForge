# Administrator Manual

This is the canonical user-facing manual for the LiveCamForge administrator interface.

## Operations

Use **Admin → Operations** for day-to-day health and synchronization information. It includes provider sync status/history, operational actions, geo-safety status and checks for recommended database settings.

For standard installations, Operations also surfaces the scheduled-task reminder until the administrator confirms that the hosting scheduler/cron jobs have actually been created. LiveCamForge stores that acknowledgement; it does not claim to detect the host control panel's scheduler automatically.

For large catalogs, review the reported `innodb_buffer_pool_size` and `max_allowed_packet` values. These checks are advisory and do not disable the application.

## Integrations

**Admin → Integrations** is the central location for provider credentials and technical integration settings. It is divided into focused sub-sections rather than one long configuration page.

Provider secrets are write-only in the UI. Leaving a secret field blank keeps the stored value; use the explicit clear action when a secret must be removed. Sensitive values are stored in `config/local.php`, not copied into the operational-settings database layer.

The page also contains provider-specific player options, postback enablement/secrets and advanced sync/media policies where applicable.

## Configuration and canonical Base URL

Deployment-specific public URL settings remain separate from ordinary catalog/editorial settings. The canonical Base URL is editable from authenticated Admin and is persisted to `config/local.php`.

If an installation is moved between a root path, subdirectory, staging host or final hostname, review the Base URL before launch because it is used for canonical URLs, sitemap links and other absolute public URLs.

Admin session cookie scope is derived from the physical request path rather than the editable Base URL, allowing root and subdirectory installations on the same hostname to keep isolated administrator sessions.

## Public Catalog

Use **Admin → Public Catalog** to decide what visitors can discover.

The main concepts are:

- **Disabled**: the commercial source is not available in the public catalog.
- **Direct**: use the provider's direct integration.
- **Network source**: use the configured affiliate-network integration for that commercial brand.
- **Combined catalog**: merge enabled sources into one discovery catalog.
- **Fallback provider**: used where a provider choice is required by a feature and the catalog itself is combined.

This area also controls performer types, public languages, provider filter visibility and provider-specific offline behavior exposed by the UI.

## Landings

**Admin → Landings** manages standard and custom SEO/discovery landing pages plus the special performer- and webmaster-recruitment pages.

Public editorial content follows the installed language packs. The editor exposes one language at a time and falls back to the configured public fallback locale when a translation is missing.

Each managed catalog landing can define a concise SEO title, a separate visible page heading (H1), meta description, eyebrow, introduction, long-form Markdown body and FAQ content. The editor includes a lightweight search-result preview and character counters; these are writing aids rather than hard ranking rules because search engines may rewrite titles and snippets.

The bundled standard landings ship with distinct editorial copy, explanatory body sections and FAQs so a fresh installation starts with useful discovery pages instead of thin filter-only pages. Standard presets can still be customized per installation and restored from the Landing Manager.

### Model recruitment

The optional `/become-a-model/` page can list configured performer signup destinations for individual providers. Its SEO title, H1, meta description, introduction, Safe Markdown body and FAQs are localized from the Landing Manager. Provider signup URLs must be HTTPS. Registration remains external to LiveCamForge and the public page makes no earnings guarantee.

### Webmaster recruitment

The optional `/for-webmasters/` page is a neutral bridge from a runtime site to a webmaster/project resource page. It has localized SEO title, H1, meta description, introduction, Safe Markdown body, CTA label and FAQs. The CTA destination is a single configurable HTTPS URL. LiveCamForge deliberately does not hard-code project referral links into runtime installations; a site owner can point the CTA to their own project/resource page and manage any affiliate disclosures there.

### Public Demo behavior

When Public Demo mode is active, Administration remains intentionally explorable but real integrations are protected server-side. Demo Alpha and Demo Beta remain the catalog sources; real provider credential, source, postback/test and recruitment-destination operations are blocked or constrained by the demo policy.

The model- and webmaster-recruitment editors remain available for demonstration, but their outbound CTA destinations are fixed to the official LiveCamForge project pages. Use **Reset demo** to restore the demonstration baseline and repopulate the local demo catalog.

See [Public Demo mode](PUBLIC_DEMO.md) for the complete policy.

## Conversions

**Admin → Conversions** is the operational dashboard for affiliate conversion tracking. It shows endpoint state, click/conversion KPIs, attribution data and provider/network event summaries.

Testing/simulator tools are separated from normal reporting and are intended for setup validation rather than production metrics.

See [Conversion Tracking](CONVERSION_TRACKING.md) for the full tracking model.

## Appearance

**Admin → Appearance** controls site identity, logo/favicon, themes, colors, typography, card appearance and localized homepage editorial content.

Visual settings are global. Homepage text is localized by installed language pack. Use the responsive preview to check desktop, tablet and mobile layouts before publishing changes.

## Saving and resets

Each major Admin area owns its own settings and has one primary save action. Reset actions restore that area's defaults and should be treated as destructive configuration operations; confirmation is required where appropriate.

## Provider status language

Public/catalog configuration uses user-oriented readiness states such as **Ready** rather than exposing lower-level network terminology. Technical authorization details remain available in Integrations where they are useful for troubleshooting.

## Related guides

- [Provider Guides](providers/README.md)
- [Configuration Reference](CONFIGURATION.md)
- [Cron and Synchronization](CRON_AND_SYNC.md)
- [Conversion Tracking](CONVERSION_TRACKING.md)
- [Troubleshooting](TROUBLESHOOTING.md)
