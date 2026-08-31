# Creating a LiveCamForge language pack

1. Copy `languages/en.json`.
2. Rename the copy using a locale code, for example `es.json` or `de.json`.
3. Translate the values on the right without changing the keys on the left.
4. Update `_meta.name`, `_meta.code`, `_meta.author` and `_meta.version`.
5. Validate that the file contains valid UTF-8 JSON.
6. Set `'locale' => 'es'` in `config/local.php`.

The fallback is selected with `'fallback_locale'` in `config/local.php` and defaults to English. A third-party pack may omit untranslated interface keys: LiveCamForge will display the value from the configured fallback pack.

The Appearance page stores homepage text per locale and exposes every installed language pack in a compact language selector. Site name, logo, colors, font and card style remain global.

The Landing Manager discovers every valid JSON file in `languages/` and offers one language at a time in the editor. Landing texts use the active locale, then `fallback_locale`, then English and finally the first available translation. Removing a language pack hides its fields without deleting previously saved landing content; restoring the file makes those fields available again.

Placeholders inside braces must be preserved. For example:

```json
"performer.viewers": "{count} viewers"
```

The translated value must still contain `{count}`.

## Public content editors (0.24.10+)

Installed language packs also define the languages offered by the Admin editors for public content. Adding a valid `languages/<locale>.json` pack automatically adds that locale to:

- standard and custom traffic landings;
- the model-recruitment special landing, including SEO/editorial/FAQ content;
- the webmaster-recruitment special landing and CTA label;
- localized recruitment provider titles and descriptions;
- Appearance → Homepage content.

The Admin shows one locale at a time to keep long forms manageable. Technical values such as affiliate URLs, provider enablement, colors, logo and player settings remain language-neutral. When a localized override is missing, public rendering uses the configured fallback locale before the built-in/default content where applicable.
