# Troubleshooting

## Blank page or HTTP 500

1. Check `storage/logs/app.log`.
2. Verify PHP 8.2+ and PDO MySQL.
3. Check `config/local.php` syntax.
4. Review Admin status and the newest application log entry. If shell/SSH access is available, optionally run `php tests/smoke.php` for additional technical validation.
5. Enable debug only temporarily in a safe environment; disable it again after diagnosis.

## Smoke test fails after extracting an upgrade

Read the newest log entry, not old historical errors. If the assertion checks that a file must not exist, the upgrade may have left an obsolete file because ZIP extraction does not delete files removed by a newer release. Follow the version-specific upgrade note rather than deleting arbitrary files.

## MySQL server has gone away

Check at least:

```sql
SHOW VARIABLES WHERE Variable_name IN (
  'innodb_buffer_pool_size',
  'max_allowed_packet',
  'net_read_timeout',
  'net_write_timeout',
  'wait_timeout'
);
```

An unusually small `max_allowed_packet` can reject large batched SQL statements. LiveCamForge reports advisory database checks in Admin → Operations and uses bounded sync batches by default.

## Large-provider sync is slow

Start with **Admin → Operations**: review the provider runtime, recent sync history and database-setting checks.

If shell/SSH access is available, optional low-level profiling can provide a detailed timing breakdown:

```bash
php bin/sync.php <provider> --profile
```

If most profiled time is in provider/remote fetch, the bottleneck is upstream. If most time is in DB phases, review database configuration and the profiler breakdown before changing code or batch sizes. CLI profiling is optional and not required for routine operation.

## First catalog request is slower than repeated requests

LiveCamForge caches catalog counts/pages and invalidates cache state after synchronization. A small cold/warm difference is expected. Use `?perf=1` in a safe diagnostic environment to inspect cache HIT/MISS and query timing.

## Tag-filtered pagination looks different

This is intentional. Tag browsing uses Previous/Next navigation and, for supported popularity sorts, cursor/keyset pagination. This avoids expensive exact counts and deep OFFSET queries.

## Provider is configured but not available

Check both Admin → Integrations and Admin → Public Catalog. A provider can have valid credentials but still be disabled as a public source. Affiliate-network sources can also be unavailable because the specific affiliate account is not authorized for that commercial source.


## Public Demo: provider settings are locked

In Public Demo mode, Demo Alpha and Demo Beta are intentionally the only catalog sources. Real provider source selectors, credentials, sensitive integration tests and real recruitment destinations are unavailable or rejected server-side.

This is expected behavior, not a provider-readiness error. To configure real providers, create a separate standard LiveCamForge installation. Use **Reset demo** when you need to restore the public demonstration baseline.
