# Cache Invalidation Testing Plan

## Overview

The `CacheInvalidation` module operates in two modes depending on whether WP Rocket is active:

- **Delegated Mode** (Rocket active): Rocket + editors handle day-to-day content changes. We handle correct ordering around Rocket's lifecycle hooks, and we intervene for unattended events (scheduled posts, deploys, manual clears).
- **Standalone Mode** (Rocket inactive): We own all content-change detection. Everything goes through the queue.

The queue processes invalidators serially in priority order. Each new trigger overwrites the queue (debounce). After each item processes, remaining items' timers reset to `now + their_delay`.

This plan is organized to match the [SPEC](SPEC.md) user stories.

### Log Files

| File | What it captures | How to start |
|------|-----------------|--------------|
| `wp-content/uploads/logs/YYYY-MM-DD.log` | All `[Cache]` debug messages — queue writes, processing, suppression | Always active (enabled by `ddev enable-cron`) |
| `redis-monitor.log` (project root) | Redis commands — DEL, FLUSH, EXPIRE, SCAN operations | `ddev redis-cli monitor \| grep -E "DEL\|FLUSH\|EXPIRE\|SCAN" > redis-monitor.log &` |

### Key Code Locations

| File | Purpose |
|------|---------|
| `wp-content/mu-plugins/sitchco-core/modules/CacheInvalidation/CacheInvalidation.php` | Orchestrator — mode determination, signal routing, sync hooks |
| `wp-content/mu-plugins/sitchco-core/modules/CacheInvalidation/CacheQueue.php` | Queue writer (with write buffer) and processor (cron-driven) |
| `wp-content/mu-plugins/sitchco-core/modules/CacheInvalidation/Invalidator.php` | Abstract base class — `slug()`, `isAvailable()`, `checkAvailability()`, `priority()`, `delay()`, `flush()` |
| `wp-content/mu-plugins/sitchco-core/modules/CacheInvalidation/ObjectCacheInvalidator.php` | `wp_cache_flush()` |
| `wp-content/mu-plugins/sitchco-core/modules/CacheInvalidation/WPRocketInvalidator.php` | `rocket_clean_domain()` |
| `wp-content/mu-plugins/sitchco-core/modules/CacheInvalidation/CloudFrontInvalidator.php` | CloudFront Clear Cache plugin |
| `wp-content/mu-plugins/sitchco-core/modules/CacheInvalidation/CloudflareInvalidator.php` | Cloudflare API cache purge (direct) |
| `wp-content/mu-plugins/sitchco-core/modules/PostLifecycle.php` | Fires `sitchco/post/content_updated` and `sitchco/post/visibility_changed` |
| `wp-content/mu-plugins/sitchco-core/modules/AcfLifecycle.php` | Fires `sitchco/acf/fields_saved` |
| `wp-content/mu-plugins/sitchco-core/modules/PostDeployment.php` | Fires `sitchco/deploy/complete` (`.clear-cache` file or WP Migrate DB) |

### Invalidator Properties

| Slug | Priority | Delay | Condition |
|------|----------|-------|-----------|
| `object_cache` | 0 | 10s | Always available |
| `wp_rocket` | 10 | 50s | `function_exists('rocket_clean_domain')` |
| `cloudfront` | 50 | 100s | CloudFront Clear Cache plugin active |
| `cloudflare` | 100 | 100s | `SITCHCO_CLOUDFLARE_API_TOKEN` and `SITCHCO_CLOUDFLARE_ZONE_ID` defined |

Queue is stored in: `wp_options` → `sitchco_cache_queue`

### Expected Log Messages

These are the exact log strings from `CacheQueue.php` and `CacheInvalidation.php`:

| Message | When |
|---------|------|
| `[Cache] Sync object cache flush before Rocket clean` | Delegated Mode: `before_rocket_clean_domain` fires |
| `[Cache] Queue written: {slugs}` | Queue flushed to DB at shutdown |
| `[Cache] Queue write suppressed during processing` | Processing guard prevented re-queue during flush |
| `[Cache] Processing {slug}, N remaining` | Cron processes a queue item |
| `[Cache] {slug} flushed successfully` | Flush completed without error |
| `[Cache] Flush failed for {slug}: {message}` | Flush threw an exception |
| `[Cache] Cascade complete` | Queue empty, all items processed |

### Queue Composition by Mode

| Mode | Signal | Queue composition |
|------|--------|-------------------|
| Delegated | `visibility_changed`, `deploy/complete`, `cache/clear_all` | `wp_rocket, cloudfront, cloudflare` |
| Delegated | `after_rocket_clean_domain` (Rocket UI clear) | `cloudfront, cloudflare` |
| Standalone | Any signal | `object_cache, cloudfront, cloudflare` |

## Helper Commands

```bash
# Check current queue state
ddev wp option get sitchco_cache_queue --format=json

# Manually fire the minutely cron (advance the cascade)
ddev wp cron event run sitchco_cron_minutely

# Clear the queue entirely (abort a cascade)
ddev wp option delete sitchco_cache_queue

# Check Redis key count
ddev redis-cli DBSIZE

# Tail the debug log
tail -f wp-content/uploads/logs/$(date +%Y-%m-%d).log

# Tail the Redis monitor log
tail -f redis-monitor.log

# Create the deployment trigger file
ddev exec touch /var/www/html/wp-content/uploads/.clear-cache

# Check if trigger file exists
ddev exec ls -la /var/www/html/wp-content/uploads/.clear-cache

# Fire manual clear
ddev wp eval "do_action('sitchco/cache/clear_all');"
```

## Pre-Test Setup

1. Enable server-side cron: `ddev enable-cron`
2. Enable Redis: `ddev enable-redis`
3. Confirm Redis is connected: `ddev wp redis status`
4. Confirm cron is running: `ddev wp cron event list`
5. Check WP Rocket status (determines which mode you're testing):
   ```bash
   ddev wp plugin list --status=active | grep wp-rocket
   ```
6. Start the Redis monitor in a separate terminal:
   ```bash
   ddev redis-cli monitor | grep -E "DEL|FLUSH|EXPIRE|SCAN" > redis-monitor.log &
   ```
7. Clear any existing queue: `ddev wp option delete sitchco_cache_queue`
8. Note the current time for correlating log entries.

---

## Delegated Mode Tests (WP Rocket Active)

**Precondition**: WP Rocket is active (`ddev wp plugin list --status=active | grep wp-rocket`).

### A1. Attended content change — editor saves a published post

**Spec ref**: A1 — `content_updated` does NOT feed the queue in Delegated Mode.

**Signal**: `sitchco/post/content_updated`

**Action**: Edit an already-published post and click Update.

**Verify**:
- [x] No `[Cache]` messages in the debug log — our system does not intervene
- [x] No queue created (`sitchco_cache_queue` option does not exist)
- [x] No FLUSH in Redis monitor

**Why no activity**: Rocket handles attended post saves with per-URL purges (not `rocket_clean_domain()`), so our `before/after_rocket_clean_domain` hooks do not fire. `content_updated` does not feed the queue in Delegated Mode. The editor is responsible for using the WP Rocket admin bar "Clear Cache" if they need a full domain purge (see A7).

**Note**: The SPEC's A1 expected behavior describes Rocket calling `rocket_clean_domain()`. In practice, Rocket performs targeted per-URL purges on post saves, which don't trigger the domain-wide hooks. The design intent is unchanged: attended content changes are handled by Rocket + editors, not our queue.

### A2. Attended content change — ACF fields saved

**Spec ref**: A2 — `fields_saved` does NOT feed the queue in Delegated Mode.

**Signal**: `sitchco/acf/fields_saved`

**Action**: Edit an ACF options page and save.

**Verify in debug log**:
- [x] `[Cache] Sync object cache flush before Rocket clean` — Rocket fires `rocket_clean_domain()` for options page saves
- [x] `[Cache] Queue written: cloudfront, cloudflare` — CDN invalidators queued via `after_rocket_clean_domain`

**Verify queue**:
- [x] Only `cloudfront` and `cloudflare` in queue (same as A7 — Rocket UI clear)
- [x] `wp_rocket` is NOT in queue (Rocket already cleared its own cache)
- [x] `object_cache` is NOT in queue (handled by sync hook)

**Must NOT happen**:
- [x] No queue entry created by `fields_saved` itself

**Note**: Unlike regular post saves (A1), Rocket fires `rocket_clean_domain()` for ACF options page saves, triggering our `before/after_rocket_clean_domain` hooks. Behavior matches A7 (Rocket UI clear).

### A3. Unattended visibility change — publish a scheduled post

**Spec ref**: A3 — `visibility_changed` feeds the queue (no editor present).

**Signal**: `sitchco/post/visibility_changed`

**Action**: Create a post with a publish date 1-2 minutes in the future, then advance cron:
```bash
ddev wp cron event run sitchco_cron_minutely
```
Or simulate programmatically:
```bash
ddev wp eval "wp_publish_post(POST_ID);"
```

**Verify in debug log**:
- [ ] `[Cache] Queue written: wp_rocket, cloudfront, cloudflare`

**Verify queue**:
- [ ] Queue contains `wp_rocket`, `cloudfront`, `cloudflare`
- [ ] `wp_rocket` expires ~50s from now, CDNs ~100s from now

**Must NOT happen**:
- [ ] `object_cache` does NOT appear in the queue (flushes via sync hook when Rocket later processes)

### A4. Unattended visibility change — unpublish a post programmatically

**Spec ref**: A4 — same behavior as A3.

**Action**:
```bash
ddev wp post update POST_ID --post_status=draft
```

**Verify**: Same as A3.

### A5. Deployment complete

**Spec ref**: A5

**Signal**: `sitchco/deploy/complete`

**Action**:
1. Clear any existing queue: `ddev wp option delete sitchco_cache_queue`
2. Create the trigger file:
   ```bash
   ddev exec touch /var/www/html/wp-content/uploads/.clear-cache
   ```
3. Run the minutely cron:
   ```bash
   ddev wp cron event run sitchco_cron_minutely
   ```

**Verify**:
- [ ] Trigger file deleted: `ddev exec ls /var/www/html/wp-content/uploads/.clear-cache` returns "No such file"
- [ ] `[Cache] Queue written: wp_rocket, cloudfront, cloudflare`
- [ ] Queue contains `wp_rocket`, `cloudfront`, `cloudflare`

**Must NOT happen**:
- [ ] `object_cache` does NOT appear in queue (flushes via sync hook when Rocket processes)

### A6. Programmatic manual clear

**Spec ref**: A6

**Signal**: `sitchco/cache/clear_all`

**Action**:
```bash
ddev wp eval "do_action('sitchco/cache/clear_all');"
```

**Verify**: Same queue composition as A5 — `wp_rocket, cloudfront, cloudflare`.

### A7. Rocket admin UI clear (Clear Cache button)

**Spec ref**: A7 — Rocket initiates the clear, not us.

**Trigger**: `before_rocket_clean_domain` and `after_rocket_clean_domain` (fired by Rocket)

**Action**: In the WordPress admin, click WP Rocket's "Clear Cache" button (admin bar or settings page).

**Verify in debug log**:
- [ ] `[Cache] Sync object cache flush before Rocket clean` — sync `wp_cache_flush()`
- [ ] `[Cache] Queue written: cloudfront, cloudflare` — CDN invalidators queued

**Verify in Redis monitor**:
- [ ] FLUSH command appears

**Verify queue**:
- [ ] Only `cloudfront` and `cloudflare` in queue
- [ ] `wp_rocket` is NOT in the queue (it already cleared its own cache)
- [ ] `object_cache` is NOT in the queue (handled by sync hook)

### A8. Publish new post (draft → publish)

**Spec ref**: Combination of A1 + A3 — `visibility_changed` feeds queue, `content_updated` does not.

**Signal**: Both `sitchco/post/visibility_changed` and `sitchco/post/content_updated` fire. Rocket performs per-URL purges (not `rocket_clean_domain()`).

**Action**: Create a new post and click Publish.

**Verify in debug log**:
- [ ] `[Cache] Queue written: wp_rocket, cloudfront, cloudflare` — from `visibility_changed`

**Verify queue**:
- [ ] `wp_rocket`, `cloudfront`, and `cloudflare` in queue
- [ ] `object_cache` does NOT appear (Delegated Mode — flushes via sync hook when Rocket later processes)

**Must NOT happen**:
- [ ] No queue entry from `content_updated` (does not feed queue in Delegated Mode)

**Note**: Rocket does per-URL purges on publish, not `rocket_clean_domain()`, so our `before/after_rocket_clean_domain` hooks do not fire during the publish request. The `visibility_changed` write is the only write, and the queue contains the full Delegated set.

---

## Standalone Mode Tests (WP Rocket Inactive)

**Precondition**: Deactivate WP Rocket before running these tests:
```bash
ddev wp plugin deactivate wp-rocket-no-activation
```
Confirm: `ddev wp plugin list --status=active | grep wp-rocket` returns nothing.

**Re-activate after**: `ddev wp plugin activate wp-rocket-no-activation`

### B1. Content change — published post saved

**Spec ref**: B1

**Signal**: `sitchco/post/content_updated`

**Action**: Edit an already-published post and click Update.

**Verify in debug log**:
- [ ] `[Cache] Queue written: object_cache, cloudfront, cloudflare`

**Verify queue**:
- [ ] Queue contains `object_cache`, `cloudfront`, `cloudflare`
- [ ] `object_cache` expires ~10s from now, CDNs ~100s from now
- [ ] `wp_rocket` does NOT appear (condition check fails)

**Must NOT happen**:
- [ ] No synchronous flush during the editor's save request (everything deferred to cron)

### B2. Content change — post published or unpublished

**Spec ref**: B2

**Signal**: `sitchco/post/visibility_changed`

**Action**: Set a published post to Draft, or publish a draft.

**Verify**: Same queue composition as B1 — `object_cache, cloudfront, cloudflare`.

### B3. Content change — ACF fields saved

**Spec ref**: B3

**Signal**: `sitchco/acf/fields_saved`

**Action**: Edit an ACF options page and save.

**Verify**: Same queue composition as B1 — `object_cache, cloudfront, cloudflare`.

### B4. Deployment complete

**Spec ref**: B4

**Signal**: `sitchco/deploy/complete`

**Action**:
1. Clear queue: `ddev wp option delete sitchco_cache_queue`
2. Create trigger: `ddev exec touch /var/www/html/wp-content/uploads/.clear-cache`
3. Run cron: `ddev wp cron event run sitchco_cron_minutely`

**Verify**: Same queue composition as B1.

### B5. Manual clear

**Spec ref**: B5

**Action**:
```bash
ddev wp eval "do_action('sitchco/cache/clear_all');"
```

**Verify**: Same queue composition as B1.

### B6. Full cascade — Standalone Mode

**Spec ref**: B1 + D4

**Purpose**: Verify the full Standalone cascade processes in order.

**Action**:
1. Edit a published post and save (triggers the cascade).
2. Advance cron and check queue after each run:
   ```bash
   ddev wp cron event run sitchco_cron_minutely
   ddev wp option get sitchco_cache_queue --format=json
   ```

**Verify in debug log** (over successive cron runs):
- [ ] `[Cache] Queue written: object_cache, cloudfront, cloudflare`
- [ ] **Cron run 1** (after ~10s): `[Cache] Processing object_cache, 2 remaining` → `[Cache] object_cache flushed successfully`
- [ ] **Cron run 2** (after ~100s from object_cache): `[Cache] Processing cloudfront, 1 remaining` → `[Cache] cloudfront flushed successfully`
- [ ] **Cron run 3** (after ~100s from cloudfront): `[Cache] Processing cloudflare, 0 remaining` → `[Cache] cloudflare flushed successfully`
- [ ] `[Cache] Cascade complete`

**Verify in Redis monitor**:
- [ ] FLUSH command appears when `object_cache` processes (during cron, not during the save)

**Verify queue**:
- [ ] Option `sitchco_cache_queue` no longer exists after completion

---

## No-Op Scenarios (Mode-Independent)

These tests apply in both modes. Run them in whichever mode is currently active.

### N1. Draft saved

**Spec ref**: N1

**Action**: Edit a draft post and click "Save Draft".

**Verify**:
- [ ] No `[Cache]` messages in debug log
- [ ] No FLUSH in Redis monitor
- [ ] Queue is not created/modified

### N2. Autosave

**Spec ref**: N2

**Action**: Edit a published post and wait for autosave (type something and wait ~60s).

**Verify**:
- [ ] No `[Cache]` messages from the autosave
- [ ] PostLifecycle filters out autosaves

### N3. Revision created

**Spec ref**: N3

**Action**: Save a published post (a revision is created automatically).

**Verify**:
- [ ] No `[Cache]` messages triggered by the revision itself (PostLifecycle filters revisions)
- [ ] Only the `content_updated` or `visibility_changed` signal fires (for the main post), not the revision

### N4. ACF fields on a regular post (numeric post ID)

**Spec ref**: N4

**Action**: Edit a post that has ACF fields and save.

**Verify**:
- [ ] `sitchco/acf/fields_saved` does NOT fire (AcfLifecycle skips numeric post IDs)
- [ ] PostLifecycle handles the post save separately

### N5. ACF fields on comment or widget

**Spec ref**: N5

**Action**: If ACF fields exist on comments or widgets, save them.

**Verify**:
- [ ] `sitchco/acf/fields_saved` does NOT fire (AcfLifecycle skips `comment_*` and `widget_*` IDs)

### N6. Non-content metadata update

**Spec ref**: N6

**Action**: Update a custom field or meta value on a published post via code:
```bash
ddev wp eval "update_post_meta(POST_ID, 'some_meta_key', 'new_value');"
```

**Verify**:
- [ ] No `[Cache]` messages in debug log
- [ ] `content_updated` does not fire for arbitrary meta changes

---

## Debounce and Queue Behavior

### D1. Rapid successive content changes (debounce)

**Spec ref**: D1

**Purpose**: Second event overwrites the queue — only one cascade runs.

**Action**:
1. Trigger a cache event (publish Post A or fire manual clear).
2. Note the queue timestamps.
3. Within a few seconds, trigger another event (publish Post B or fire manual clear again).

**Verify**:
- [ ] Queue has been **replaced** — `expires` values recalculated from the second event
- [ ] Only one `[Cache] Queue written:` line per request (write buffer deduplicates within a request)
- [ ] Only one cascade runs, timed from the second event

### D2. New event during active cascade (before any processing)

**Spec ref**: D2

**Purpose**: Queue fully replaced, cascade restarts from the beginning.

**Action**:
1. Trigger event A to start the cascade.
2. Note the `expires` timestamps.
3. Wait ~30 seconds (before the first item expires).
4. Trigger event B.
5. Check the queue.

**Verify**:
- [ ] All `expires` values recalculated from event B's time
- [ ] First item's expiry is ~delay from event B (NOT from event A)

### D3. New event after partial cascade processing

**Spec ref**: D3

**Purpose**: Already-processed invalidators re-enter the queue.

**Action** (Delegated Mode):
1. Trigger event A to start cascade: `wp_rocket, cloudfront, cloudflare`.
2. Advance cron until `wp_rocket` has been processed.
3. Confirm queue has `cloudfront` and `cloudflare` remaining.
4. Trigger event B (e.g., publish another post via `ddev wp eval "wp_publish_post(POST_ID);"`).
5. Check the queue.

**Verify**:
- [ ] Queue fully replaced: `wp_rocket, cloudfront, cloudflare` with fresh timestamps
- [ ] `wp_rocket` is back in the queue (will process again for event B's changes)

### D4. Cascade completes without interruption

**Spec ref**: D4

**Purpose**: Full cascade processes to completion.

**Verify** (covered by A-series and B6 cascade tests):
- [ ] Each item processes in priority order
- [ ] After each processing, remaining items' timers reset
- [ ] When empty: `[Cache] Cascade complete` log message
- [ ] `sitchco_cache_queue` option deleted

### D5. Processing guard — hooks during flush do not re-trigger

**Spec ref**: D5

**Purpose**: When WP Rocket's `flush()` calls `rocket_clean_domain()`, the resulting `before/after_rocket_clean_domain` hooks must not re-queue.

**Action** (Delegated Mode):
1. Start a cascade with `wp_rocket` in the queue (e.g., via deploy trigger).
2. Advance cron until `wp_rocket` processes.

**Verify in debug log**:
- [ ] `[Cache] Processing wp_rocket, N remaining` — wp_rocket is processing
- [ ] `[Cache] Sync object cache flush before Rocket clean` — sync hook fires (this is a direct hook, unaffected by the guard)
- [ ] `[Cache] Queue write suppressed during processing` — the `after_rocket_clean_domain` handler tried to write but was blocked
- [ ] `[Cache] wp_rocket flushed successfully`

**Must NOT happen**:
- [ ] No additional `[Cache] Queue written:` during the wp_rocket flush
- [ ] CDN invalidators are NOT re-queued by `after_rocket_clean_domain` — they're already in the cascade

---

## Test Execution Workflow

For each test scenario:

1. **Clear state**: Delete any existing queue and note the current time.
   ```bash
   ddev wp option delete sitchco_cache_queue
   echo "--- TEST ID START $(date) ---"
   ```
2. **Perform the action** described in the scenario.
3. **Check the debug log**: Search for `[Cache]` entries after your noted timestamp.
   ```bash
   tail -20 wp-content/uploads/logs/$(date +%Y-%m-%d).log
   ```
4. **Check Redis monitor**: Look for FLUSH/DEL commands.
   ```bash
   tail -10 redis-monitor.log
   ```
5. **Check queue state**:
   ```bash
   ddev wp option get sitchco_cache_queue --format=json
   ```
6. **For cascade tests**: Advance cron manually and re-check after each step.
   ```bash
   ddev wp cron event run sitchco_cron_minutely
   ```

## Agent-Assisted Testing

When handing off to an agent for interactive testing:

- The **human** performs browser actions (publishing posts, clicking WP Rocket buttons, saving ACF fields)
- The **agent** runs helper commands to verify log output, Redis activity, and queue state after each action
- The agent can advance the cron (`ddev wp cron event run sitchco_cron_minutely`) to step through the cascade
- The agent can create the `.clear-cache` file for deployment simulation
- The agent can fire `sitchco/cache/clear_all` via `ddev wp eval` for manual clear testing
- The agent can simulate programmatic status changes via `ddev wp post update` or `ddev wp eval "wp_publish_post(...)"`
- The agent should check the debug log and queue state after every action and report whether the behavior matches expectations
- For **Standalone Mode tests**, the agent should deactivate WP Rocket first and re-activate it when done
