# Provider Guides

Provider documentation is organized by integration rather than by code class. Provider guides document credentials, feed behavior, player capabilities, affiliate routing, conversion tracking where available and known limitations.

## Direct integrations

- Chaturbate
- BongaCams
- CAM4
- LiveJasmin
- Stripchat / Stripcash

## Affiliate-network integrations

### CrakRevenue

LiveCamForge supports multiple commercial sources through one CrakRevenue account configuration. Availability depends on what the affiliate account is authorized to access. A source that is unavailable to one account may be available to another.

Supported source adapters include MyFreeCams, Jerkmate/Streamate, Chaturbate, LiveJasmin/AWEmpire, Stripchat, ImLive and BongaCams where authorized.

## Public brand vs technical source

The public frontend should normally present the commercial brand. Technical adapter/network details belong in Admin because they are implementation and affiliate-routing concerns rather than visitor-facing product names.

## Common provider setup flow

1. Obtain the provider/network affiliate credentials.
2. Configure the credentials in Admin → Integrations.
3. Confirm that the integration reports **Ready** or the appropriate technical authorization state.
4. Select the intended source in Admin → Public Catalog.
5. Run a provider synchronization from **Admin → Operations**. If shell/SSH access is available, the provider-specific CLI command can be used instead.
6. Verify a performer page and affiliate redirect.
7. Optionally configure conversion tracking if you want to monitor conversions and provider performance inside LiveCamForge.

For synchronization options, including Admin, hosting schedulers and optional CLI workflows, see [Cron and Synchronization](../CRON_AND_SYNC.md). For optional postbacks and attribution see [Conversion Tracking](../CONVERSION_TRACKING.md).
