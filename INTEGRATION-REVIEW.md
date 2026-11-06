# Backend integration investigation checklist

Originally reviewed: 2026-09-05  
Re-verified: 2026-11-06 (against `main` @ `79e6d66`)  
Projects: `api.rsywx.2026` (PHP/Slim backend) and `rsywx` (SvelteKit frontend).

> **Re-verification note.** The original review (2026-09-05) predated several
> refactors (2026-09-16, 2026-09-25, 2026-11-06), so its file/line references
> no longer match `main`. Each item below is re-stated against the current
> checkout with fresh evidence. The `rsywx` frontend was **not** present in the
> re-verification environment, so any frontend-coordination step is left open
> and unverified. Tests run green at re-verification:
> `tests/Integration/BookListEndpointTest.php` (13 tests) and
> `tests/Integration/ErrorHandlingTest.php` + `tests/Unit/ErrorHandlerMiddlewareTest.php`.
> Do not put credentials into this document or test fixtures.

Status legend: `[x]` done · `[~]` partial · `[ ]` open. Shared issue IDs
correspond to the [frontend checklist](../rsywx/INTEGRATION-REVIEW.md).

## Status summary

| ID | Topic | Status |
|----|-------|--------|
| INT-01 | Identify the deployed backend | `[x]` Done |
| INT-02 | Preserve search text through routing | `[x]` Done |
| INT-03 | Separate detail reads from visit recording | `[x]` Done |
| INT-04 | Specify embedded book-review data | `[x]` Done |
| INT-05 | Establish cover-image delivery responsibilities | `[x]` Done |
| INT-06 | Align HTTP failures and response envelopes | `[x]` Done |
| INT-07 | Reconcile wire types and metadata | `[x]` Done |

## Connection baseline

The frontend defaults to `https://api.rsywx.com/api/v1` and sends `X-API-Key`
from server-side loaders and proxies. Local API-key settings match, and the
backend version is `v1`. Core endpoint paths, tag-search mapping, and pagination
fields align. Browser health requests use the unauthenticated `/health` route.
Do not put credentials into this document or test fixtures.

## INT-01: Identify the deployed backend

**Status:** `[x]` Resolved (2026-11-06) — environment mapping decided:
production is frozen and the frontend dev-targets a local backend from this
checkout.

**Environments**

| Environment | Backend base URL | Revision | Notes |
|---|---|---|---|
| Production | `https://api.rsywx.com/api/v1` | **frozen / older** | Host `165.245.186.202`, nginx. Serves books, misc, readings and root `/health`. Does **not** serve `/api/v1/health` or `/blogs/*` (404) or `/admin/cache/clear`. Do not build frontend features that need the newer endpoints against it. |
| Local dev (frontend target) | `http://localhost:8000/api/v1` | this checkout (`main`) | Run `php -S localhost:8000 -t public public/router.php`. Verified to serve the full surface incl. `/api/v1/health` and `/blogs/*`. |

**Evidence (original):** `apache-vhost.conf:4-6` pointed to
`/home/tr/www/api.rsywx.2025/public`.
**Evidence (now):** `apache-vhost.conf:4` points to
`/home/tr/www/api.rsywx.2026/public`. The live site is **nginx/1.28.3 on
`165.245.186.202`** — not this dev box (public IP `49.73.237.20`). Live probing
with a valid API key shows production is an **older revision**: root `/health`
→ 200, but `/api/v1/health`, `/blogs/today`, `/blogs/today/{month}/{day}`,
`/blogs/latest[/{count}]` and `/admin/cache/clear` → **404**, while the
books/misc/readings set and the `/books/{bookid}` payload shape match this
checkout.

- [x] Correct the supplied Apache example to the intended directory. Do not
  change live routing before establishing the current deployment.
- [x] Inspect the active virtual host/reverse proxy and identify the document
  root and deployed revision behind `api.rsywx.com`. (Live = nginx on
  `165.245.186.202`, older revision; see table above.)
- [x] Agree on explicit development/staging targets with the frontend.
  (Decision: production frozen; local backend from this checkout.)

**Acceptance:** each environment maps to a documented backend deployment,
and local integration work does not accidentally target production.

**Resolution:** the frontend dev points at `http://localhost:8000/api/v1` (this
checkout). Production stays frozen; the newer endpoints (`/blogs/*`, versioned
`/health`) are treated as a future deploy, not a frontend dependency.

## INT-02: Preserve search text through routing

**Status:** `[x]` Resolved (2026-11-06) — search values now reach the model
unchanged, including `/`, `%` and `+`, and pagination is preserved.

**Evidence (original):** `src/Controllers/BookController.php:944` applied
`urldecode()` after Slim had already decoded the route argument, turning
`C%2B%2B` into `C  `; `a%2Fb` failed route matching.
**Evidence (now):** the redundant decode is gone. The deeper cause of the
`a%2Fb` 404 is that **`Slim\Routing\RouteResolver:42` runs `rawurldecode()` on
the path before routing**, so a literal `/` can never be a normal
single-segment placeholder. The route is now a catch-all
(`config/routes.php:40`: `/books/list[/{type}[/{value:.*}]]`) and the controller
splits an optional trailing `/<page>`, so `C++`, `a/b` and `a%b` all reach the
model verbatim while `/books/list/title/-/2` still paginates. Coverage in
`tests/Integration/BookListEndpointTest.php`: `testEncodedPlusSearchReachesModelUnchanged`,
`testEncodedSlashReachesModelUnchanged`, `testEncodedPercentReachesModelUnchanged`,
`testEncodedSpacesAndChineseReachModelUnchanged`, `testTagsSearch`,
`testPagination` / `testPaginationStillParsesWithCatchAllValue`.

- [x] Remove redundant controller decoding.
- [x] Preserve arbitrary search text through routing (catch-all path segment,
  keeping the legacy `/books/list/{type}/{value}/{page}` form working).
- [x] Add route-level cases for `C++`, slashes, literal percent sequences,
  Chinese text, spaces, tag search, and pagination.

**Acceptance:** search values reach the model unchanged and encoded separators
do not produce unexpected 404 responses. ✅

**Contract note for the frontend:** the value must still be URL-encoded
(`/` → `%2F`, `%` → `%25`, `+` → `%2B`). One inherent ambiguity of the path
form: a search value that literally ends in `/<digits>` (e.g. `24/7`) is read as
value `24` + page `7`. Values without a trailing `/<digits>` are unaffected.

## INT-03: Separate detail reads from visit recording

**Status:** `[x]` Resolved (2026-11-06) — reads no longer force a visit, and the
visitor address now uses a trusted-proxy allow-list.

**Evidence (original):** `src/Models/Book.php:44-48` recorded every detail GET
as a visit; `getClientIpAddress()` trusted any forwarded header.
**Evidence (now):**
- `Book::getBookDetail()` takes `$recordVisit` and `BookController::show()`
  reads `?visit=` / `?record_visit=` with `RECORD_VISITS` as the env default
  (`Book.php`, `BookController.php`, `Config.php`); a detail read returns the
  count as of *before* the request, so the increment shows on the next read.
- Visitor address now goes through `App\Support\ClientIp::resolve($_SERVER,
  Config::trustedProxies())`: forwarding headers (`X-Forwarded-For`,
  `X-Real-IP`, `CF-Connecting-IP`, ...) are honoured **only** when `REMOTE_ADDR`
  is a trusted proxy (new `TRUSTED_PROXIES` env — comma-separated IPs/CIDRs);
  otherwise only `REMOTE_ADDR` is used. Documented in `.env.example`.

- [x] Define actual-view semantics, including refreshes, retries, bots, and
  duplicate events. (Record-on-read with an explicit `?visit=false` opt-out;
  one visit recorded per recording request.)
- [x] Coordinate explicit view recording with the frontend. (Contract: send
  `?visit=false` for hover-preloads and post-tag-refresh detail fetches.)
- [x] Define trusted proxy/address handling; do not blindly trust arbitrary
  forwarded-IP headers. (`TRUSTED_PROXIES` allow-list; empty = trust nobody.)
- [x] Cover ordinary detail reads, actual views, prefetches, tag refreshes,
  and trusted/untrusted address sources.
  (`tests/Integration/VisitRecordingTest.php`, `tests/Unit/ClientIpTest.php`.)

**Acceptance:** prefetch and tag refreshes do not inflate visits; actual views
use the agreed counting policy and intended visitor address. ✅

**Frontend contract:** `GET /books/{id}` records a visit by default — send
`?visit=false` when fetching for a prefetch/hover preload or right after adding
tags. Put the reverse proxy's IPs/CIDRs in `TRUSTED_PROXIES` so the real client
address is recorded (leave it empty when the API is hit directly).

## INT-04: Specify embedded book-review data

**Status:** `[x]` Resolved (2026-11-06) — embedded reviews keep their own minimal
schema; a review's image is its own `feature` (not the book cover), and the
schema is now documented and contract-tested.

**Evidence (original):** `src/Models/Book.php:125-138` returned review fields
`id`, `title`, `datein`, `uri`, `feature`; the frontend assumed the reading-list
shape (`bookid`, `book_title`, `cover_uri`), yielding `/covers/undefined.jpg`.
**Evidence (now):** the agreed embedded schema is exactly
`id, title, datein, uri, feature` (`Book::getBookReviews()`), where:
- `uri` → link to the review;
- `feature` → the **review's own** image, a full URL (e.g.
  `https://blog.rsywx.net/wp-content/uploads/...` or `https://rsywx.net/covers/….webp`)
  — a review may have its own photo, so this can differ from the book cover;
- the book cover comes from the parent book, so the review does **not** carry
  `bookid`/`book_title`/`cover_uri` (those belong to the reading-list shape used
  by `/readings/*`).

Documented in `docs/API.md` (book-detail section) and typed in the OpenAPI
annotation (`BookController.php`); `docs/api-docs.*` regenerated.

- [x] Agree whether embedded reviews remain a separate schema or gain explicit
  book/cover fields. (Separate minimal schema; review image = its own `feature`.)
- [x] Document the chosen schema independently of reading-list responses.
- [x] Add contract coverage for detail responses with and without reviews.
  (`tests/Integration/BookDetailReviewsTest.php`.)

**Acceptance:** frontend review links and images work using only fields
guaranteed by the agreed response. ✅

**Frontend contract:** render a review's image from `feature` (may equal or
differ from the book cover) and its link from `uri`; take the book cover from the
parent book. `reviews` is `[]` when a book has none.

## INT-05: Establish cover-image delivery responsibilities

**Status:** `[x]` Resolved (2026-11-06) — the frontend owns cover delivery
(decision recorded here; the frontend is being rewritten, so no existing
frontend asset pipeline is assumed).

**Evidence (original):** models generated public API cover URLs while the
frontend hard-coded local `/covers/` paths; no `public/covers` directory was
present in the backend checkout.
**Evidence (now):** the agreed model is that covers are image files named
`{bookid}.jpg` served by the **frontend origin at `/covers/{bookid}.jpg`** — the
frontend owns the asset pipeline and the missing-cover placeholder. The backend
still has **no** `public/covers` and no covers route; the API-generated
`cover_uri` (`Config::coverUri()` → `https://api.rsywx.com/covers/{bookid}.jpg`,
`Config.php:40-51`) is **intentionally ignored** and may remain for other API
consumers.

- [x] Identify cover storage and the actual public `/covers/` mapping.
  (Frontend serves `/covers/{bookid}.jpg`.)
- [x] Confirm generated `cover_uri` URLs work in browser image requests without
  an API-key header. (Resolved by decision: the frontend does **not** use the API
  `cover_uri`; it serves covers from its own static assets.)
- [x] Document asset upload/synchronization and missing-cover behavior.
  (Frontend responsibility: `{bookid}.jpg` assets ship/deploy with the frontend
  and missing files are handled by the frontend's placeholder.)
- [x] Coordinate the frontend's local-to-remote image fallback. (Resolved by
  decision: covers are frontend-local; no remote API fallback is used.)

**Acceptance:** a book displays its cover from the frontend's `/covers/` assets;
the API `cover_uri` is out of scope.

**Frontend contract:** serve `/covers/{bookid}.jpg` (with a placeholder for
missing files); do not depend on the API `cover_uri`.

## INT-06: Align HTTP failures and response envelopes

**Status:** `[x]` Done (backend) — controllers raise typed exceptions; a global
middleware renders envelopes with correct statuses; docs and tests added.

**Evidence (original):** `src/Controllers/BlogController.php` returned
`success: false` on dependency failure without setting a failure HTTP status.
**Evidence (now):** `BlogController.php:38` throws `UpstreamException` (502) on
dependency failure and `ValidationException` (400) on bad input.
`ErrorHandlerMiddleware` maps `ApiException`→its own status, `HttpException`→its
status (routing 404/405), `InvalidArgumentException`→400, `Throwable`→500, always
emitting `{success:false,message[,detail]}`. A repo-wide grep shows the only
`success:false` emitters are the two middlewares (`ErrorHandlerMiddleware`,
`ApiKeyMiddleware`) — no controller returns 2xx with `success:false`. Envelopes
and the intentionally unwrapped `GET /health` (`SystemController.php:31-40`) are
documented in `docs/API.md`. Covered by `tests/Integration/ErrorHandlingTest.php`
(404/400/502/500, CORS-on-error) and `tests/Unit/ErrorHandlerMiddlewareTest.php`.

- [x] Document success/error envelopes and appropriate HTTP statuses for
  consumed endpoints, including the intentionally unwrapped health response.
- [x] Ensure controller failures use appropriate non-2xx statuses.
- [x] Cover invalid input, missing records, unavailable dependencies, and
  successful endpoint-specific metadata.
- [~] Coordinate frontend handling of infrastructure errors that bypass
  application JSON envelopes. (Backend now always emits a JSON envelope; frontend
  handling unverified — frontend not present.)

**Acceptance:** application failures have meaningful statuses and envelopes,
and infrastructure failures are not interpreted as successful empty data.

**Remaining:** frontend-side handling confirmation only.

## INT-07: Reconcile wire types and metadata

**Status:** `[x]` Resolved (2026-11-06) — the list/detail type drift is
normalized, the endpoint-specific date metadata is documented, and contract
tests lock the shapes.

**Evidence (original):** detail responses cast `translated` to boolean and
`price` to float in `src/Models/Book.php:91-96`; WordPress date metadata used
`month`, `day`, `date_string`, `is_today`, unlike book date metadata.
**Evidence (now):**
- **Normalized:** list-type responses (built via `BookQueryBuilder` →
  `BookResponse`) returned `translated` as an integer (`0/1`) and `price` as a
  **string**, while detail cast them to bool/number. `BookResponse` now coerces
  known fields (`normalizeTypes()`): `translated`/`instock` → bool, `price` →
  number|null, `id`/`total_visits`/`page`/`kword` → int, `bookid` → string — so
  list and detail agree (verified: list `translated`=bool, `price`=number).
- **Documented (endpoint-specific, intentionally not unified):** book
  `date_info` = `requested_date`, `month_day`, `is_today` (`Book.php`) vs
  WordPress `date_info` = `month`, `day`, `date_string`, `is_today`
  (`BlogController.php`). `is_today` is a **computed** bool in both — it is not a
  database column. Documented at `docs/API.md` §3 "Wire types".

- [x] Inventory actual types, nullability, and optional fields for consumed
  list, detail, reading, and WordPress responses. (`docs/API.md` §3 + the
  contract tests below.)
- [x] Agree whether to normalize backend types or document endpoint-specific
  differences; avoid unannounced breaking changes. (Normalize the list/detail
  type drift; document the book-vs-WordPress `date_info` shape.)
- [x] Update relevant OpenAPI annotations/reference files and coordinate
  frontend type changes. (OpenAPI + `docs/API.md` updated; the frontend is being
  rewritten against this contract.)
- [x] Add response-contract assertions to detect future drift.
  (`tests/Integration/ResponseContractTest.php`, plus `ApiEndpointsTest.php`.)

**Acceptance:** backend documentation and the (rewritten) frontend models agree
with actual response shapes, including separate book and WordPress date
metadata. ✅

**Note:** the legacy frontend's types were unreliable (it typed list
`translated` as boolean and `price` as number, which the API did not honor).
That mismatch is now fixed server-side and pinned by tests.
