# Performance

## What LiveCamForge optimizes

The 0.25.x series focused on two areas: public catalog latency and large-provider synchronization.

Catalog optimizations include short-lived/revision-aware caches, indexed country paths, deferred exact counts for tag browsing and cursor/keyset navigation for deep tag pages.

Synchronization optimizations include batched persistence, change-aware structural vs volatile data, persisted structural fingerprints, change-aware geo-block maintenance and avoiding unnecessary media-cache scans.

## Database recommendations

For combined catalogs, LiveCamForge recommends:

```ini
innodb_buffer_pool_size=64M   # minimum recommendation; more is useful when available
max_allowed_packet=8M        # minimum recommendation
```

These values are deliberately conservative. Hosting platforms may provide much larger values. Admin → Operations reports the effective server values and displays advisory warnings when they are unusually small.

A very small buffer pool can make repeated catalog/sync benchmarks misleading because index/data pages are continuously evicted. A very small packet limit can cause large SQL statements to fail with errors such as `MySQL server has gone away`.

## DB sync batch

The validated default batch is 200. Larger batches are not guaranteed to be faster and can produce oversized packets on conservative database installations. An optional CLI diagnostic override is bounded for this reason.

## Public catalog diagnostics

In a safe diagnostic context, add:

```text
?perf=1
```

The response exposes `Server-Timing` plus LiveCamForge performance metadata. Useful measurements include cache hit/miss state, catalog count time, ID-selection time and rendering time.

## Sync diagnostics

Admin → Operations provides the synchronization status and history needed for routine monitoring.

If shell/SSH access is available, optional low-level profiling can be run with:

```bash
php bin/sync.php <provider> --profile
```

The CLI profiler helps separate upstream/provider latency from LiveCamForge DB time. It is an advanced troubleshooting tool, not a requirement for normal shared-hosting operation. Do not optimize the local persistence layer when the profile shows that nearly all runtime is spent in the remote feed.

## Tag pagination

Tag-filtered catalog pages intentionally avoid an expensive exact result count. They use lightweight Previous/Next navigation. For supported popularity sorts, deep tag navigation uses an opaque cursor/keyset rather than SQL OFFSET, preventing query time from increasing linearly with page depth.

## Performance troubleshooting order

1. Run the same request/sync twice and distinguish cold vs warm cache behavior.
2. Profile rather than infer.
3. Check `innodb_buffer_pool_size` and `max_allowed_packet`.
4. Identify whether time is upstream, SQL selection, persistence or rendering.
5. Change one variable at a time and retain a baseline.
