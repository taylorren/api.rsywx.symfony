# Backend integration investigation checklist

Reviewed: 2026-09-05  
Projects: `api.rsywx.2026` (PHP/Slim backend) and `rsywx` (SvelteKit frontend).

These tasks describe findings from the current working trees, including
uncommitted changes. They are not completed fixes or confirmation of the live
deployment. Shared issue IDs correspond to the
[frontend checklist](../rsywx/INTEGRATION-REVIEW.md).

## Connection baseline

The frontend defaults to `https://api.rsywx.com/api/v1` and sends `X-API-Key`
from server-side loaders and proxies. Local API-key settings match, and the
backend version is `v1`. Core endpoint paths, tag-search mapping, and pagination
fields align. Browser health requests use the unauthenticated `/health` route.
Do not put credentials into this document or test fixtures.

## INT-01: Identify the deployed backend

**Evidence:** `apache-vhost.conf:4-6` still points to
`/home/tr/www/api.rsywx.2025/public`. Calling the public domain does not establish
that the frontend is using this 2026 checkout.

- [ ] Inspect the active virtual host/reverse proxy and identify the document
  root and deployed revision behind `api.rsywx.com`.
- [ ] Correct the supplied Apache example to the intended directory. Do not
  change live routing before establishing the current deployment.
- [ ] Agree on explicit development/staging targets with the frontend.

**Acceptance:** each environment maps to a documented backend deployment,
and local integration work does not accidentally target production.

## INT-02: Preserve search text through routing

**Evidence:** `src/Controllers/BookController.php:944` applies `urldecode()`
after Slim has already decoded the route argument. `C%2B%2B` becomes `C  `;
`a%2Fb` fails route matching. The frontend encodes values correctly.

- [ ] Remove redundant controller decoding.
- [ ] Coordinate a transport that preserves arbitrary search text, preferably
  a query parameter for the value, while retaining legacy route compatibility.
- [ ] Add route-level cases for `C++`, slashes, literal percent sequences,
  Chinese text, spaces, tag search, and pagination.

**Acceptance:** search values reach the model unchanged and encoded separators
do not produce unexpected 404 responses.

## INT-03: Separate detail reads from visit recording

**Evidence:** `src/Models/Book.php:44-48` records every detail GET as a visit.
The frontend preloads on hover and fetches detail after adding tags, so both
can create visits without a new page view. Server-side frontend fetches also
omit visitor-address forwarding, producing frontend-server IP/geolocation.

- [ ] Define actual-view semantics, including refreshes, retries, bots, and
  duplicate events.
- [ ] Coordinate explicit view recording with the frontend, accounting for
  other API consumers before changing existing GET behavior.
- [ ] Define trusted proxy/address handling; do not blindly trust arbitrary
  forwarded-IP headers.
- [ ] Cover ordinary detail reads, actual views, prefetches, tag refreshes,
  and trusted/untrusted address sources.

**Acceptance:** prefetch and tag refreshes do not inflate visits; actual views
use the agreed counting policy and intended visitor address.

## INT-04: Specify embedded book-review data

**Evidence:** `src/Models/Book.php:125-138` returns review fields `id`, `title`,
`datein`, `uri`, and `feature`. The frontend assumes the reading-list shape,
including `bookid`, `book_title`, and `cover_uri`, resulting in image requests
to `/covers/undefined.jpg`.

- [ ] Agree whether embedded reviews remain a separate schema or gain explicit
  book/cover fields.
- [ ] Document the chosen schema independently of reading-list responses.
- [ ] Add contract coverage for detail responses with and without reviews.

**Acceptance:** frontend review links and images work using only fields
guaranteed by the agreed response.

## INT-05: Establish cover-image delivery responsibilities

**Evidence:** `src/Models/Book.php` and `src/Models/BookResponse.php` generate
public API cover URLs, but the frontend hard-codes local `/covers/` paths.
No `public/covers` directory was present in this checkout during review;
an external server mapping may exist.

- [ ] Identify cover storage and the actual public `/covers/` mapping.
- [ ] Confirm generated `cover_uri` URLs work in browser image requests without
  an API-key header.
- [ ] Document asset upload/synchronization and missing-cover behavior.
- [ ] Coordinate the frontend's local-to-remote image fallback.

**Acceptance:** a book with a valid API cover URL can display its cover even
when no corresponding frontend-local file exists.

## INT-06: Align HTTP failures and response envelopes

**Evidence:** several frontend helpers ignore HTTP status and only reject
`success: false`. Non-envelope JSON failures can become empty results or
undefined pagination. `src/Controllers/WordPressController.php` also returns
`success: false` on dependency failure without setting a failure HTTP status.

- [ ] Document success/error envelopes and appropriate HTTP statuses for
  consumed endpoints, including the intentionally unwrapped health response.
- [ ] Ensure controller failures use appropriate non-2xx statuses.
- [ ] Cover invalid input, missing records, unavailable dependencies, and
  successful endpoint-specific metadata.
- [ ] Coordinate frontend handling of infrastructure errors that bypass
  application JSON envelopes.

**Acceptance:** application failures have meaningful statuses and envelopes,
and infrastructure failures are not interpreted as successful empty data.

## INT-07: Reconcile wire types and metadata

**Evidence:** detail responses cast `translated` to boolean and `price` to
float in `src/Models/Book.php:91-96`; frontend detail types inherit numeric
and string list fields respectively. WordPress date metadata uses `month`,
`day`, `date_string`, and `is_today`, unlike book date metadata.

- [ ] Inventory actual types, nullability, and optional fields for consumed
  list, detail, reading, and WordPress responses.
- [ ] Agree whether to normalize backend types or document endpoint-specific
  differences; avoid unannounced breaking changes.
- [ ] Update relevant OpenAPI annotations/reference files and coordinate
  frontend type changes.
- [ ] Add response-contract assertions to detect future drift.

**Acceptance:** backend documentation and frontend models agree with actual
response shapes, including separate book and WordPress date metadata.
