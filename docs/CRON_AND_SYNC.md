# Cron and Synchronization

## Synchronization options

LiveCamForge can synchronize providers in three ways:

1. **Admin → Operations** — the normal path for setup, validation and occasional manual synchronization.
2. **Hosting control-panel scheduler/cron** — the recommended path for unattended production synchronization.
3. **CLI/SSH** — an optional path for manual synchronization, advanced diagnostics and hosting environments that expose shell access.

Interactive shell/SSH access is **not required** for normal LiveCamForge operation. Many shared-hosting plans provide a Cron Jobs or Scheduled Tasks page even when they do not provide an interactive terminal.

## Manual synchronization from Admin

Use **Admin → Operations** to run provider synchronizations during initial setup or when an occasional manual refresh is required. The page also exposes synchronization status and history.

For very large combined catalogs, scheduled synchronization is preferable to repeatedly starting full syncs from a browser tab. Browser requests can be subject to hosting-specific execution and proxy time limits.

## Scheduled synchronization on shared hosting

For production, configure the scheduler supplied by your hosting control panel whenever possible. Panels differ: some ask for one complete command, while others let you choose the PHP version, script path and frequency in separate fields.

A typical command-based scheduled task is conceptually:

```text
/usr/bin/php /home/account/public_html/livecamforge/bin/sync.php
```

The PHP path is host-specific. It may be `/usr/bin/php`, a versioned binary such as `php82`/`php83`, or a path shown directly by the hosting panel. Use the value documented by your hosting provider rather than assuming the example path is universal.

A provider-specific scheduled task can use:

```text
/usr/bin/php /home/account/public_html/livecamforge/bin/sync.php stripchat
```

This can be useful when a provider has a significantly different runtime or when the hosting account imposes limits on long-running tasks.


### Shared-hosting PHP CLI settings

The PHP runtime used by a hosting scheduler can use a different `php.ini` from the PHP runtime serving web requests. A setting changed in the hosting panel for the website therefore may not automatically apply to cron/CLI jobs.

If a large provider needs a known memory limit, it can be set explicitly in the cron command. For example:

```text
php -d memory_limit=256M /home/USER/domains/example.com/public_html/livecamforge/bin/sync.php >/dev/null 2>&1
```

Use a value appropriate for the hosting plan and the enabled providers. The example above is a practical shared-hosting value validated during LiveCamForge deployment testing; it is not a requirement to raise the web PHP memory limit if the normal web runtime already works correctly.

## Scheduling strategy

Do not schedule overlapping full syncs. LiveCamForge uses synchronization locking, but scheduled jobs should still be spaced so normal provider runtimes do not continuously collide.

A practical production setup can use either:

- one scheduled full sync when the hosting environment comfortably completes all enabled providers; or
- separate provider-specific schedules when runtime limits, upstream latency or catalog size make that more reliable.

A good default starting point for a full synchronization of all enabled providers is **every 10 minutes**. On a fast hosting environment, an interval around **8 minutes** can also be realistic. More conservative environments can use **15 minutes** or longer. Avoid intervals below **5 minutes** unless you have measured the complete synchronization time and are certain jobs cannot overlap.

As a practical rule, set the interval to at least **2× the typical full-sync duration**. For example, if a complete synchronization normally takes 3–4 minutes, an 8–10 minute schedule leaves reasonable headroom for upstream latency and temporary slowdowns. If it takes around 6 minutes, prefer 12–15 minutes or separate provider-specific schedules.

Choose a frequency appropriate for how current you want the catalog to be and for the upstream provider limits. Validate one complete scheduled cycle before launch, then review Admin → Operations to confirm that scheduled runs complete before the next one starts.

## If the host does not provide scheduled tasks

Admin synchronization remains available for manual operation, but a production catalog will only stay current if somebody triggers synchronization regularly. LiveCamForge does not currently rely on a public HTTP cron endpoint as a substitute for a hosting scheduler.

If unattended synchronization is required, use a hosting plan that provides cron/scheduled tasks or another server-side scheduler compatible with the PHP CLI script.

## Optional CLI commands

When shell/SSH access is available, synchronize all enabled providers with:

```bash
php bin/sync.php
```

Synchronize one provider with:

```bash
php bin/sync.php stripchat
```

These commands are alternatives to manual Admin synchronization and are also suitable for command-based hosting schedulers.

## Advanced CLI profiling

For low-level performance diagnostics on a host that provides shell/SSH access:

```bash
php bin/sync.php stripchat --profile
```

The profiler separates provider fetch time, normalization, DB persistence, geo maintenance, stale cleanup and cache invalidation. Provider-specific adapters may expose additional counters such as HTTP requests, bytes and pages received.

CLI profiling is optional and is not required for normal shared-hosting operation.

## Database batch diagnostics

The default DB batch is 200. A bounded CLI override exists only for controlled benchmarking:

```bash
php bin/sync.php stripchat --profile --db-batch=100
```

LiveCamForge clamps diagnostic values to a conservative supported range. Do not treat a larger batch as automatically faster: packet limits and SQL execution behavior vary significantly between MySQL/MariaDB environments.

## Provider runtimes

Provider runtime is not directly comparable across integrations. Some feeds deliver large payloads quickly and spend more time in local persistence; others can spend most of the total runtime waiting for the upstream feed/API.

Admin sync history is sufficient for routine monitoring. If CLI profiling is available, use it before changing database or provider code during advanced troubleshooting.

## After a sync

Successful provider syncs refresh catalog state, apply stale/offline handling and invalidate the relevant catalog cache revision. The first request after invalidation may rebuild cache entries; the performance work in 0.25.x keeps the common catalog and filtered paths bounded for large catalogs.

## Logs and output

When the hosting scheduler supports output capture, redirect command output to a protected log or use the hosting cron-log facility. Do not expose scheduler commands, private paths or secrets through public web pages.

## Conversion polling cron

Conversion polling is separate from performer synchronization. CAM4 currently uses TUNE/reporting-API polling rather than a server-to-server postback, so installations that enable CAM4 conversion reporting should schedule `bin/sync-conversions.php` separately.

A daily run is normally sufficient for affiliate reporting. Example schedule:

```cron
0 3 * * *
```

Example command on a command-based shared-hosting scheduler:

```text
php /home/USER/domains/example.com/public_html/livecamforge/bin/sync-conversions.php cam4 >/dev/null 2>&1
```

The clock used by the cron expression is the hosting server's scheduler clock. The exact hour is not important for normal CAM4 reporting; choose a quiet daily time appropriate for the account.

After a run, verify **Admin → Conversions → Conversion polling history**. `SUCCESS` with `Received = 0` is a healthy result when no conversions exist in the reporting window. LiveCamForge retains conversion-polling run metadata for 30 days and displays the latest 20 runs in Admin.
