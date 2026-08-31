# Conversion Tracking

LiveCamForge can associate outbound affiliate clicks with later conversion events when the provider or affiliate network exposes an appropriate postback/reporting mechanism.

Conversion tracking inside LiveCamForge is **optional**. It is useful when you want the Admin dashboard to report conversions, attribution and provider/network performance, but it is not required for the public catalog, players or affiliate redirects to operate.

## Tracking flow

The typical flow is:

```text
visitor → performer page → affiliate redirect → LiveCamForge click/SID
        → provider or affiliate network → conversion callback/report
        → LiveCamForge attribution → Admin reporting
```

## Affiliate redirects vs conversion reporting

These are separate concerns:

- **Affiliate redirects/tracking parameters** are part of sending visitors to the configured affiliate destination.
- **Postbacks or conversion polling** bring later conversion events back into LiveCamForge for internal reporting and comparison.
- **Provider/network dashboards** remain available as the external reporting source even when LiveCamForge conversion ingestion is not configured.

## Provider differences

Tracking capabilities are provider-specific. Some integrations use server-to-server postbacks; CAM4 conversion collection uses TUNE reporting/polling. Affiliate networks can expose several commercial sources through one technical network configuration.

## Secrets

Postback endpoints must use strong secrets. Configure supported secrets and enablement from Admin → Integrations. Sensitive values are stored in `config/local.php`.

## Deduplication

LiveCamForge protects conversion ingestion against duplicate events when a stable provider/network event identifier is available. Testing tools may intentionally send more than one request to verify deduplication behavior.

## Testing tools

Admin → Conversions separates normal reporting from testing/simulation controls. Simulated events are for validation and should not be interpreted as real affiliate performance.

## Production deployment

If you choose to enable LiveCamForge conversion tracking, do not consider it complete until public hosting is available and the real provider/network callback or polling path has been tested. Local development can validate signatures, parsing, mapping and deduplication, but an external affiliate network cannot normally call a localhost endpoint.

Provider-specific credentials, capabilities and conversion notes belong in the individual provider guides. The [Deployment](DEPLOYMENT.md) guide includes the production validation steps for installations that enable conversion tracking.

## Conversion polling observability

Providers that use a reporting API instead of server-to-server postbacks need a scheduled polling command. CAM4/TUNE is the current implementation.

Each polling execution is recorded in `conversion_sync_runs` and is visible in **Admin → Conversions → Conversion polling history**. This makes a zero-result run observable: `SUCCESS` with `Received = 0` means the API request completed correctly but no conversions were available in the configured lookback window.

CAM4 polling should normally be scheduled separately from performer synchronization. A daily cron is usually sufficient:

```text
php /home/USER/domains/example.com/public_html/livecamforge/bin/sync-conversions.php cam4 >/dev/null 2>&1
```

See [Cron and Synchronization](CRON_AND_SYNC.md) for a complete scheduler example and shared-hosting notes.

A failed polling request is recorded as `FAILED`; the stored error message can be used for troubleshooting. Runs left in `RUNNING` because a PHP process was terminated unexpectedly are marked `INTERRUPTED` after a conservative stale-run window when LiveCamForge next performs operational maintenance. Polling-run metadata older than 30 days is deleted automatically. The polling history is operational metadata and is separate from actual rows in `affiliate_conversions`.

