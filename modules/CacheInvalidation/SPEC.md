# Cache Invalidation Specification

## Axioms

These are the immutable truths about the problem space. They don't change with implementation choices.

1. **Three cache layers exist, ordered inside-out:**
   - Object Cache (Redis/Memcached) - serves data to PHP
   - Page Cache (WP Rocket) - serves rendered HTML
   - Edge Cache (CDN: CloudFront or Cloudflare) - serves to the world

2. **Stale inner layers poison outer layers.** If page cache rebuilds while object cache holds stale data, freshly cached pages contain stale content. If CDN refetches while page cache is stale, CDN serves stale pages. Flush order must respect the layer hierarchy: inside-out.

3. **Two operating modes exist — Delegated and Standalone — determined by whether a page cache plugin (currently WP Rocket) is active.** This is a runtime condition, not a configuration choice. When Rocket is present, it owns content-change detection (50+ hooks). When it's absent, we must detect content changes ourselves.

4. **Four invalidators exist, each conditionally present:**

   | Invalidator   | Present when                          |
   |---------------|---------------------------------------|
   | Object Cache  | Always (wp_cache_flush is core WP)    |
   | WP Rocket     | `rocket_clean_domain` function exists  |
   | CloudFront    | CloudFront Clear Cache plugin active   |
   | Cloudflare    | `SITCHCO_CLOUDFLARE_API_TOKEN` and `SITCHCO_CLOUDFLARE_ZONE_ID` defined |

   Any combination is possible. The system must work correctly with any subset.

5. **WordPress fires noisy hooks.** Post saves fire on drafts, revisions, autosaves, and non-content metadata changes. ACF's `acf/save_post` fires for posts, options pages, user fields, widgets, and more. Raw hooks cannot be trusted as cache invalidation signals.

6. **CDN invalidation is expensive.** API calls, rate limits, propagation time. Rapid successive events (bulk edits, quick re-saves) must be debounced into a single invalidation cycle.

7. **All invalidation we control should be deferred.** Object cache flush with Redis takes noticeable time. CDN purges are API calls. None of this should run during the editor's save request. Everything goes through cron. The one exception is the Delegated Mode sync hook on `before_rocket_clean_domain` - ordering requires object cache to flush before Rocket rebuilds, and Rocket controls that timing, not us.

## Signals

Clean, filtered events derived from noisy WordPress hooks. These are the primary inputs to the cache invalidation system from our signal-filtering layer.

| Signal | Source Module | Fires when |
|--------|--------------|------------|
| `sitchco/post/content_updated` | PostLifecycle | A published post's content is saved (excludes drafts, revisions, autosaves) |
| `sitchco/post/visibility_changed` | PostLifecycle | A post transitions to or from published status |
| `sitchco/acf/fields_saved` | AcfLifecycle | ACF fields saved on a front-end-visible entity. Passes entity type (`options`, `user`, `term`) and raw `$post_id` as context. Excludes numeric post IDs (handled by PostLifecycle), comments, and widgets. |
| `sitchco/deploy/complete` | PostDeployment | A deployment completes (`.clear-cache` trigger file) or a WP Migrate DB Pro migration finishes (`wpmdb_migration_complete`) |
| `sitchco/cache/clear_all` | CacheInvalidation | Programmatic full cache clear. Developer extension point — no UI is provided. Intended for scripts or custom code that perform bulk data changes outside normal content workflows. |

These signals are the contract between the signal-filtering layer and the invalidation system. Adding a new trigger means adding a new signal, not hooking raw WordPress actions.

**Which signals feed the queue depends on mode:**

| Signal | Delegated Mode (Rocket active) | Standalone Mode (Rocket inactive) |
|--------|----------------------|------------------------|
| `content_updated` | Does not feed queue (Rocket + editors handle) | Feeds queue |
| `visibility_changed` | Feeds queue (unattended transitions) | Feeds queue |
| `fields_saved` | Does not feed queue (Rocket + editors handle) | Feeds queue |
| `deploy/complete` | Feeds queue | Feeds queue |
| `cache/clear_all` | Feeds queue | Feeds queue |

**Delegated Mode also reacts to WP Rocket's own lifecycle hooks.** When Rocket clears its domain cache (for any reason — native detection, editor action, or our queued trigger), `before_rocket_clean_domain` triggers the synchronous object cache flush and `after_rocket_clean_domain` queues CDN invalidators. These are not signals in the above sense — they're direct hooks from Rocket's clearing sequence. They're listed here because they produce queue entries, but they bypass the signal-filtering layer entirely.

**Queue composition by input:**

| Queue composition | Mode | Inputs |
|---|---|---|
| WP Rocket → CDN | Delegated | `visibility_changed`, `deploy/complete`, `cache/clear_all` |
| CDN only | Delegated | `after_rocket_clean_domain` |
| Object Cache → CDN | Standalone | `content_updated`, `visibility_changed`, `fields_saved`, `deploy/complete`, `cache/clear_all` |

CDN means whichever CDN invalidators pass their condition checks, processed in priority order.

## Design Decisions

### 1. Mode determination happens once, at the orchestration level

The orchestrator (CacheInvalidation module) checks the WP Rocket invalidator's `isAvailable()` at initialization and configures the system accordingly. Individual invalidators do not branch on mode. They declare their capabilities (condition, delay, flush method) and the orchestrator decides when to invoke them.

**Why:** Distributing mode logic across every invalidator created branching complexity that was hard to reason about and test. Centralizing it means each invalidator is simple and stateless.

### 2. Delegated Mode object cache flush is a standalone sync hook, not a queued invalidator

In Delegated Mode, `wp_cache_flush()` is called synchronously on `before_rocket_clean_domain`. This is a direct hook registration - not part of the invalidator/queue system.

**Why:** This behavior is fundamentally different from every other invalidation action. It's synchronous (must complete before Rocket rebuilds), it's reactive to Rocket's lifecycle (not to our signals), and it needs a single-execution guard (Rocket fires per-URL on multilingual sites). Forcing it into the invalidator abstraction required modal branching that made the abstraction worse at its actual job.

### 3. The serial queue provides debounce, dynamic stagger, and relative timing

The queue processes invalidators one at a time, in priority order. When an item is processed, remaining items' timers reset to `now + their_delay`. This achieves three things:

- **Debounce:** A new event overwrites the queue with fresh timestamps. Rapid events collapse into one cycle.
- **Dynamic stagger:** Only active invalidators are in the queue. Missing layers don't create dead gaps.
- **Relative timing:** Each delay represents settling time after the previous layer, not an absolute offset. Adding or removing an invalidator naturally adjusts the total cycle time.

**Why:** Fixed per-invalidator timestamps would require cross-checking which other invalidators are active to avoid unnecessary delays. The serial queue handles this implicitly.

### 4. Global debounce - any event restarts all pending timers

When a new trigger event fires while invalidation is pending, the entire queue is rewritten with fresh timestamps. All invalidators restart their clocks.

**Why:** The goal is "wait for changes to settle, then invalidate once." If a CDN purge is pending and a new post is saved, the CDN purge should wait for the new change rather than firing with stale timing. Partial restarts would create overlapping cycles.

### 5. Invalidators are simple value objects

An invalidator declares:
- **Condition:** Is the backing service present? (e.g., are Cloudflare API credentials defined?)
- **Priority:** Processing order within the queue (lower = earlier)
- **Delay:** Minimum settling time in seconds (used by the queue for relative timing). Actual processing depends on cron tick frequency (~60s), so effective delay is `delay + (0 to cron_interval)`
- **Flush:** The actual invalidation action

An invalidator does NOT declare what triggers it. The orchestrator decides what feeds the queue based on mode and event type.

**Why:** Triggers are a property of the operating mode, not of the invalidator. CloudFront doesn't "know" it should listen to `AfterRocketClean` in Delegated Mode but `ContentChange` in Standalone Mode. That's an orchestration concern.

Each invalidator's `isAvailable()` applies a WordPress filter `sitchco/cache/condition/{slug}` where `{slug}` is the invalidator's slug (`object_cache`, `wp_rocket`, `cloudfront`, `cloudflare`). This provides a uniform extension point for enabling or disabling any invalidator at runtime. The default value is the result of the invalidator's own availability check. Object cache defaults to `true` (always available) but is filterable like the rest.

### 6. Request-local write buffer eliminates redundant DB writes

When multiple signals fire in the same request (e.g., draft→publish fires both `visibility_changed` and `content_updated`), the queue writer buffers writes in memory and flushes once at shutdown via a `register_shutdown_function` hook. Each signal handler still calls the writer normally — the writer just defers the `update_option` call. Since last-writer-wins is the queue's debounce mechanism, only the final write matters anyway.

**Why:** Without the buffer, each signal produces a separate `update_option` call with the same result. The buffer is ~10 lines, follows an existing pattern (`BackgroundProcessing`), and eliminates redundant DB writes without changing queue semantics.

## Architecture

The system has three concerns with clear boundaries: an orchestrator that owns mode logic and signal routing, value-object invalidators that know only how to flush their backing service, and a queue that mechanically processes work via cron.

### Orchestrator

The CacheInvalidation module is the single entry point. At initialization it determines the operating mode (Delegated or Standalone) once, then registers hooks based on a declarative routing configuration.

The routing config is a literal data structure that maps inputs to queue compositions — the same three shapes from the queue composition table:

```
Delegated Mode routes:
  visibility_changed, deploy/complete, cache/clear_all  →  [wp_rocket, cloudfront, cloudflare]
  after_rocket_clean_domain                                 →  [cloudfront, cloudflare]

Standalone Mode routes:
  content_updated, visibility_changed, fields_saved,
  deploy/complete, cache/clear_all                      →  [object_cache, cloudfront, cloudflare]
```

The orchestrator selects the appropriate route map at init, registers a WordPress hook for each entry, and every hook handler does the same thing: look up the invalidator list for that input, filter by condition checks (is the backing service available?), sort by priority, and pass the result to the queue writer.

In Delegated Mode, the orchestrator also registers the standalone sync hook: `wp_cache_flush()` on `before_rocket_clean_domain` with a single-execution guard. This is a direct `add_action` call — five lines of code, completely outside the invalidator/queue system.

The Cloudflare invalidator calls the Cloudflare API directly using `SITCHCO_CLOUDFLARE_API_TOKEN` and `SITCHCO_CLOUDFLARE_ZONE_ID` environment constants. No plugin integration is needed — `flush()` sends a purge request to the Cloudflare zone endpoint with the derived host list.

### Invalidators

Each invalidator is a value object with five properties:

- **`slug(): string`** — Unique identifier used for queue storage and filter keys
- **`isAvailable(): bool`** — Is the backing service present? (Template method: calls `checkAvailability()` then applies `sitchco/cache/condition/{slug}` filter)
- **`priority(): int`** — Processing order (lower = earlier)
- **`delay(): int`** — Settling time in seconds (relative to previous item)
- **`flush(): void`** — The actual invalidation action

No trigger declarations, no state, no initialization logic. The orchestrator decides when to invoke them; the invalidator just knows how to check availability and flush.

### Queue

The queue has two independent sides that share no state beyond a single `wp_options` row:

**Writer** — Called by the orchestrator's signal handlers. Takes a list of invalidator slugs, resolves to instances, filters by `isAvailable()`, sorts by priority, computes timestamps (`now + delay` for each item), and writes to `wp_options`. Last writer wins — overwriting any existing queue provides debounce (Design Decision 4). If all invalidators are filtered out (none available), the writer no-ops — it does not clear or overwrite an existing queue. Writes are buffered per-request and flushed once at shutdown (see Design Decision 6).

**Processor** — Called by minutely cron. Reads the queue, checks if the first item's timestamp has expired, calls `flush()`, removes the item, resets remaining items' timestamps to `now + their_delay`, and writes back. When the queue is empty, fires the completion hook and deletes the option. The processor wraps each `flush()` call in a try/catch — exceptions are logged and the item is removed, allowing the cascade to continue with the next item. A void return from `flush()` is treated as success. No retry — a subsequent content event will re-queue the full cascade if needed.

**Processing guard** — Before calling `flush()`, the processor signals that processing is active. The orchestrator's signal handlers check this flag and skip queue writes while processing is in progress. This prevents hooks fired as side effects of a flush (e.g., `after_rocket_clean_domain` when WP Rocket processes) from re-queuing invalidators that are already in the cascade (see D5). The processing flag is a runtime variable (static or instance property), not persisted state — it only suppresses re-entrancy within the same PHP process.

**Concurrency model** — The writer and processor run in separate PHP processes (web requests vs cron). Both read-modify-write the same `wp_options` row without locking. Last `update_option` wins at the DB level. This means a web request writing a new queue can race with a cron tick processing the existing queue. This is an intentional simplicity trade-off: both writes produce valid queue states, and any invalidation lost to a race will be re-triggered by the next content event.

## Operating Modes

### Delegated Mode: WP Rocket Active

**Mental model:** Rocket + trained editors handle day-to-day content changes. We handle correct ordering around Rocket's actions, and we intervene for unattended events where no human is present to notice stale caches.

Content editors are trained to use the WP Rocket admin bar dropdown to clear caches when they expect to see changes. This means we do NOT need to proactively catch every possible content change in Delegated Mode — doing so would add noise and unnecessary invalidations. Instead, we trust the existing workflow and focus on the gaps.

**Attended content changes** (editor saves a post, updates ACF fields):
- Rocket detects it natively and calls `rocket_clean_domain()`
- `before_rocket_clean_domain` fires → sync `wp_cache_flush()`
- Rocket clears page cache
- `after_rocket_clean_domain` fires → queue CDN invalidators
- If Rocket misses something, the editor clears via admin bar

**Unattended visibility changes** (scheduled post publishes, programmatic status transitions):
- No editor present to notice or intervene
- `sitchco/post/visibility_changed` feeds the queue: WP Rocket → CDN invalidators
- Object cache flush happens via Rocket's `before_rocket_clean_domain` when Rocket processes

**Deploy / manual clear:**
- Queue: WP Rocket → CDN invalidators (object cache flush happens via Rocket's `before_rocket_clean_domain` when Rocket processes)

**What does NOT happen in Delegated Mode:**
- `content_updated` does not feed the queue (Rocket + editors handle normal saves)
- Object cache is never in the queue (handled by sync hook)
- ACF field saves do not feed the queue (Rocket handles options pages; editors handle exceptions)

### Standalone Mode: WP Rocket Inactive

We own content-change detection. All invalidation goes through the queue.

**On content change / deploy / manual clear:**
- Queue: Object Cache → CDN invalidators (in priority order)

**What does NOT happen in Standalone Mode:**
- WP Rocket invalidator is excluded (condition check fails)
- No synchronous hooks needed (no mid-request page cache rebuild to race against; the queue handles inside-out ordering via priority)

## Queue Lifecycle

```
Event fires
  → Orchestrator determines active invalidators (condition checks)
  → Queue is written to wp_options with computed timestamps
    (overwrites any existing queue = debounce)
  → Nothing else happens in this request

Minutely cron fires
  → Check first queue item
  → If not expired: do nothing, wait for next tick
  → If expired: process it (call flush), remove from queue,
    reset remaining items to now + their_delay
  → If queue empty: fire completion hook, clean up
```

## User Stories

Each story states the trigger, preconditions, expected behavior, and negative assertions (what must NOT happen). Stories are grouped by mode, then by cross-cutting concerns.

---

### Delegated Mode: WP Rocket Active

#### A1. Attended content change — editor saves a published post

**Trigger:** Editor saves a published post (fires `sitchco/post/content_updated`).

**Expected:**
- Rocket detects the change natively and calls `rocket_clean_domain()`
- `before_rocket_clean_domain` fires → sync `wp_cache_flush()`
- Rocket clears page cache
- `after_rocket_clean_domain` fires → CDN invalidators are queued

**Must NOT happen:**
- `content_updated` does not feed the queue (Rocket + editor handle this)
- Object cache does not appear in the queue

#### A2. Attended content change — ACF fields saved

**Trigger:** Editor saves ACF fields on options page, user profile, or term (fires `sitchco/acf/fields_saved`).

**Expected:**
- Rocket detects ACF saves and handles page cache
- Sync `wp_cache_flush()` via `before_rocket_clean_domain`
- CDN invalidators queued via `after_rocket_clean_domain`
- If Rocket misses it (e.g., user/term fields), editor clears via admin bar

**Must NOT happen:**
- `fields_saved` does not feed the queue (attended workflow)

#### A3. Unattended visibility change — scheduled post publishes

**Trigger:** WP Cron transitions a scheduled post to published (fires `sitchco/post/visibility_changed`). No editor present.

**Expected:**
- `visibility_changed` feeds the queue: WP Rocket → CDN invalidators
- When WP Rocket processes, `before_rocket_clean_domain` fires → sync `wp_cache_flush()`

**Why this is different from A1/A2:** No human is watching. A scheduled post going live with stale CDN cache could go unnoticed for hours.

#### A4. Unattended visibility change — programmatic status transition

**Trigger:** Code or plugin changes a post's published status (fires `sitchco/post/visibility_changed`). No editor present.

**Expected:** Same as A3.

#### A5. Deployment complete

**Trigger:** Deploy finishes or WP Migrate DB migration completes (fires `sitchco/deploy/complete`).

**Expected:**
- Queue: WP Rocket → CDN invalidators (in priority order, with delays)
- When WP Rocket processes and calls `rocket_clean_domain()`, `before_rocket_clean_domain` fires → sync `wp_cache_flush()`

**Must NOT happen:**
- Object cache does not appear directly in the queue (it flushes via the sync hook when Rocket processes)

#### A6. Programmatic bulk operation with manual clear

**Trigger:** Developer runs a script that modifies many posts. They remove the CacheInvalidation hooks before the bulk work, perform the operation, then fire `do_action('sitchco/cache/clear_all')` when done.

**Expected:** Same as A5. The single manual trigger replaces what would have been dozens of individual invalidation cycles.

**Note:** `sitchco/cache/clear_all` is a developer extension point — no admin UI is provided. WP Rocket's admin bar Clear Cache button is a separate workflow (see A7).

#### A7. Rocket admin UI clear (Clear Cache button)

**Trigger:** Editor clicks WP Rocket's own Clear Cache button in the admin bar. Rocket fires `before/after_rocket_clean_domain` on its own.

**Expected:**
- `before_rocket_clean_domain` → sync `wp_cache_flush()`
- `after_rocket_clean_domain` → CDN invalidators are queued

**Must NOT happen:**
- WP Rocket is not in the queue (it already did its own clear)
- Our system does not call `rocket_clean_domain()` (Rocket initiated this, not us)

**Note:** This is the trained editor workflow for handling any cache staleness Rocket missed.

#### A8. Attended content change — no CDN plugins present

**Trigger:** Editor saves a post, but neither CloudFront nor Cloudflare is installed.

**Expected:**
- Rocket handles page cache. Sync `wp_cache_flush()` via `before_rocket_clean_domain`
- Nothing is queued (no CDN invalidators to schedule)

#### A9. Unattended visibility change — no CDN plugins present

**Trigger:** Scheduled post publishes, but neither CloudFront nor Cloudflare is installed.

**Expected:**
- `visibility_changed` feeds the queue: WP Rocket only
- When Rocket processes, sync `wp_cache_flush()` via the before hook
- Queue completes after Rocket

#### A10. Deployment — no CDN plugins present

**Trigger:** Deployment complete, but neither CloudFront nor Cloudflare is installed.

**Expected:** Same as A9.

#### A11. Deployment — only one CDN present

**Trigger:** Deployment complete, only CloudFront installed (no Cloudflare), or vice versa.

**Expected:**
- Queue: WP Rocket → the one CDN present
- No delay gap for the missing CDN invalidator
- Total cascade time is shorter than with both CDNs

---

### Standalone Mode: WP Rocket Inactive

#### B1. Content change — published post saved

**Trigger:** Editor saves a published post (fires `sitchco/post/content_updated`).

**Expected:**
- Queue: Object Cache → CDN invalidators (whichever are present, in priority order)
- All processing happens via cron, nothing synchronous

**Must NOT happen:**
- No synchronous flush during the editor's save request
- WP Rocket does not appear in the queue

#### B2. Content change — post published or unpublished

**Trigger:** Post transitions to or from published status (fires `sitchco/post/visibility_changed`).

**Expected:** Same as B1.

#### B3. Content change — ACF fields saved (options/user/term)

**Trigger:** ACF options page, user profile, or term saved (fires `sitchco/acf/fields_saved`).

**Expected:** Same as B1.

#### B4. Deployment complete

**Trigger:** Deploy finishes or WP Migrate DB migration completes.

**Expected:** Same as B1.

#### B5. Manual clear

**Trigger:** Developer fires `do_action('sitchco/cache/clear_all')` after a bulk operation.

**Expected:** Same as B1.

#### B6. Content change — no CDN plugins present

**Trigger:** Any content change, neither CloudFront nor Cloudflare installed.

**Expected:**
- Queue: Object Cache only
- Minimal total cycle time (just the object cache delay)

#### B7. Content change — only one CDN present

**Trigger:** Content change, only CloudFront installed (no Cloudflare), or vice versa.

**Expected:**
- Queue: Object Cache → the one CDN present
- No delay gap for the missing CDN
- Total cascade time adjusts accordingly

---

### No-Op Scenarios (Mode-Independent)

These events must NOT trigger any cache invalidation in either mode.

#### N1. Draft saved

**Trigger:** Editor saves a draft post (draft → draft).

**Expected:** No signals fire. No queue created. No flush.

#### N2. Autosave

**Trigger:** WordPress autosave fires on a published post.

**Expected:** PostLifecycle filters this out. No signals fire.

#### N3. Revision created

**Trigger:** WordPress creates a revision during post save.

**Expected:** PostLifecycle filters this out. No signals fire.

#### N4. ACF fields saved on post (numeric post ID)

**Trigger:** ACF fields saved on a regular post/page (numeric `$post_id`).

**Expected:** AcfLifecycle skips numeric IDs. PostLifecycle handles the post save separately (only if published).

#### N5. ACF fields saved on comment or widget

**Trigger:** ACF fires `acf/save_post` with `comment_*` or `widget_*` post ID.

**Expected:** AcfLifecycle skips these entity types. No signal fires.

#### N6. Post metadata updated (non-content)

**Trigger:** Non-content metadata changes on a published post (e.g., custom field that doesn't affect rendering).

**Expected:** PostLifecycle's `content_updated` only fires on actual post saves via `wp_after_insert_post`, not arbitrary meta updates.

---

### Debounce and Queue Behavior

#### D1. Rapid successive content changes

**Trigger:** Editor publishes Post A, then immediately publishes Post B (within the debounce window).

**Expected:**
- First publish creates the queue with timestamps
- Second publish overwrites the queue with fresh timestamps
- Only one invalidation cycle occurs, timed from Post B

**Note:** A single WordPress action can fire multiple signals in the same request (e.g., draft→publish fires both `visibility_changed` and `content_updated`). Each signal writes the queue independently; the last write wins. No special coalescing is needed — the overwrite behavior handles this naturally. A request-local write buffer collects queue writes and flushes once at shutdown (similar to the pattern in `BackgroundProcessing`), eliminating redundant `update_option` calls when multiple signals fire in the same request (see Design Decision 6).

#### D2. New event during active cascade

**Trigger:** Post A starts a cascade. Before it completes, Post B is published.

**Expected:**
- Queue is fully replaced with fresh timestamps from Post B
- Any already-processed invalidators are re-queued
- The cascade restarts from the beginning

#### D3. New event after partial cascade processing

**Trigger:** Cascade from Post A has processed Object Cache and WP Rocket. CloudFront/Cloudflare still pending. Post B is published.

**Expected:**
- Queue is fully replaced — all invalidators re-enter with fresh timestamps
- Object Cache and WP Rocket (already processed for Post A) will process again for Post B's changes

#### D4. Cascade completes without interruption

**Trigger:** Single content change, no further events during the cascade.

**Expected:**
- Each invalidator processes in priority order
- After each processing, remaining items' timers reset to `now + their_delay`
- When queue is empty, completion hook fires and queue option is deleted

#### D5. Hooks fired during flush do not re-trigger

**Trigger:** WP Rocket's `flush()` calls `rocket_clean_domain()`, which fires `before/after_rocket_clean_domain`.

**Expected:**
- These hooks do NOT cause new items to be added to the queue
- The sync `wp_cache_flush()` on `before_rocket_clean_domain` still fires (it's a direct hook, not queue-based)
- CDN invalidators are not re-queued by `after_rocket_clean_domain` during cron processing

The queue processor must suppress signal and hook handling during flush execution. Any hooks fired as a side effect of a flush (e.g., `after_rocket_clean_domain` when WP Rocket processes) are ignored by the orchestrator for the duration of that processing step.
