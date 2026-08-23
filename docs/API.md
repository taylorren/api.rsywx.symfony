# RSYWX Library API — Frontend Reference

Human-friendly reference for consuming the RSYWX API from a frontend.
For the machine-readable spec, see `public/api-docs.yaml` (OpenAPI 3.0).

---

## 1. Base URL & version

```
https://api.rsywx.com/api/v1
```

- All paths below are relative to this base URL.
- API is served from its **own domain**, separate from the frontend
  (`https://api.rsywx.com` vs. the site domain). Make cross-origin requests
  against this API host with the `X-API-Key` header (CORS is open, see §2).
- API is **versioned**: the version comes from the `API_VERSION` env var
  (currently `v1`). Requests without a version will not route.

> ℹ️ **`/health` is special** — it's registered at the **root domain**, i.e.
> `https://api.rsywx.com/health`, **not** under `/api/v1`. Use it as a bare
> liveness ping (no auth, no version prefix).

### Quick example
```
GET https://api.rsywx.com/health                      # outside /api/v1 (no auth)
GET https://api.rsywx.com/api/v1/books/status         # needs X-API-Key
GET https://api.rsywx.com/api/v1/books/00666          # needs X-API-Key
```

---

## 2. Authentication

Every endpoint below **requires an API key**, transported one of two ways:

1. **Header (recommended)**
   ```
   X-API-Key: <your-api-key>
   ```
2. **Query parameter** (fallback)
   ```
   ?api_key=<your-api-key>
   ```

**Exempt from auth** (no key needed):
- `GET /` — HTML documentation UI
- `GET /health` — health check
- `GET /api-docs*` — OpenAPI docs
- `OPTIONS` requests — CORS preflight

> ℹ️ **CORS is wide open** (`Access-Control-Allow-Origin: *`) and `OPTIONS`
> preflight already works. Since your frontend and API live on **different
> domains**, add the frontend origin to your browser fetch and send the
> `X-API-Key` header — the preflight will succeed and you'll get the JSON.

**Unauthorized response** (HTTP `401`):
```json
{ "success": false, "message": "Invalid or missing API key" }
```

---

## 3. Response envelope

Most endpoints return this common shape:

```jsonc
{
  "success": true,     // bool  — request succeeded
  "data": { },         // any   — the payload (object or array)
  "cached": true       // bool  — was the response served from cache?
}
```

Some endpoints add **`message`** (on success or error) and/or **`pagination`**.

### Common conventions
- `cached: false` on first hit (data fetched + cached), `true` on subsequent hits.
- **`?refresh=true`** on any cached endpoint forces a cache refresh
  (recomputes from the database) regardless of TTL.
- `cover_uri` values are absolute URLs to book cover images.
---

## 4. Endpoint reference

### 4.1 System

#### `GET /health` — Health check *(no auth)*
> Located at the **root domain**: `https://api.rsywx.com/health`
> (outside `/api/v1`; no version prefix and no API key required).
> Calling `https://api.rsywx.com/api/v1/health` would 404.

Returns **HTTP 200** and:
```json
{ "success": true, "message": "API is running", "timestamp": "2026-08-23 07:40:00" }
```
Use this to ping liveness/uptime.

---

### 4.2 Books

#### `GET /books/status` — Collection statistics
| Param | Type | Required | Notes |
|---|---|---|---|
| `refresh` | bool | no | force cache refresh |

Returns totals across the collection (**book located** outside `na`/`--`):
```json
{
  "success": true,
  "data": {
    "total_books": 1821,
    "total_pages": 724776,
    "total_kwords": 483442,
    "total_visits": 2352777
  },
  "cached": true
}
```

---

#### `GET /books/{bookid}` — Book detail
Path params: `bookid` — 5-digit book ID (e.g. `00666`).
Query: `refresh` (optional).

Returns full book record incl. tags, reviews, cover, and visit stats:
```jsonc
{
  "success": true,
  "data": {
    "id": 666,
    "bookid": "00666",
    "title": "隐形的城市",
    "author": "卡尔维诺",
    "translated": true,
    "copyrighter": "译林出版社",           // nullable
    "region": "意大利",
    "location": "书房",
    "purchdate": "2020-05-15",
    "price": 45,
    "pubdate": "2019-03-01",
    "printdate": "2019-03-15",
    "ver": "1",
    "deco": "精装",
    "isbn": "978-7-5447-6789-0",
    "category": "文学",                     // nullable
    "ol": "cn",                              // nullable
    "kword": 120,
    "page": 280,
    "intro": "Book introduction text",
    "instock": true,
    "publisher_name": "花城出版社",
    "place_name": "上海",
    "tags": ["意大利", "散文", "文学", "经典"],
    "reviews": [],
    "cover_uri": "https://api.rsywx.com/covers/00666.jpg",
    "total_visits": 4843,
    "last_visited": "2026-08-20 14:12:00"
  },
  "cached": true
}
```

---

#### `GET /books/list[/{type}[/{value}[/{page}]]]` — Search & list
All segments optional. `type` one of `author | title | tag | misc | id`
(default `id`). `value` is the search term. `page` is 1-based (default `1`).
`per_page` is fixed at **20**.

| Example |
|---|
| `GET /books/list` — all books, page 1, newest first |
| `GET /books/list/author/卡尔维诺` |
| `GET /books/list/title/城` |
| `GET /books/list/tag/经典/2` |
| `GET /books/list/id/00666` |

```jsonc
{
  "success": true,
  "data": [
    {
      "id": 666,
      "bookid": "00666",
      "title": "隐形的城市",
      "author": "卡尔维诺",
      "translated": true,
      "copyrighter": "译林出版社",       // nullable
      "region": "意大利",
      "location": "书房",
      "purchdate": "2020-05-15",
      "tags": ["意大利", "文学", "经典"],
      "cover_uri": "https://api.rsywx.com/covers/00666.jpg"
    }
  ],
  "pagination": {
    "current_page": 1,
    "total_pages": 5,
    "total_results": 23,
    "per_page": 20
  },
  "cached": true
}
```

---

#### `GET /books/latest[/{count}]` — Latest purchased
Path: `count` (default `1`, max `50`). Ordered by newest purchase first.

List item:
```jsonc
{
  "id": 666, "bookid": "00666",
  "title": "隐形的城市", "author": "卡尔维诺",
  "translated": true, "copyrighter": "译林出版社",
  "region": "意大利", "location": "书房",
  "purchdate": "2020-05-15",
  "tags": ["意大利", "文学"],
  "cover_uri": "https://api.rsywx.com/covers/00666.jpg"
}
```
---

#### `GET /books/random[/{count}]` — Random picks
Path: `count` (default `1`). Returns books picked at random. Item shape is the
same list shape as `/books/latest`.

---

#### `GET /books/last_visited[/{count}]` — Recently visited
Path: `count` (default `1`, max 50). Ordered by most recent visit first.
```jsonc
{
  "success": true,
  "data": [
    {
      "id": 1234, "bookid": "01234",
      "title": "最近访问的书籍", "author": "作者姓名",
      "translated": false, "copyrighter": null,
      "region": "中国", "location": "书房",
      "cover_uri": "https://api.rsywx.com/covers/01234.jpg",
      "last_visited": "2025-07-27 12:30:00",
      "visit_country": "China",        // nullable — access country
      "total_visits": 15
    }
  ],
  "cached": true
}
```

---

#### `GET /books/forgotten[/{count}]` — Forgotten books
Path: `count` (default `1`, max 50). Books unvisited for the longest time, most
forgotten first.
```jsonc
{
  "success": true,
  "data": [
    {
      "id": 1234, "bookid": "01234",
      "title": "被遗忘的书籍", "author": "作者姓名",
      "translated": false, "copyrighter": null,
      "region": "中国", "location": "书房",
      "cover_uri": "https://api.rsywx.com/covers/01234.jpg",
      "last_visited": "2024-01-15 10:30:00",
      "days_since_visit": 180
    }
  ],
  "cached": true
}
```

---

#### `GET /books/popular/{count}` — Most popular
Path: `count` (**required**, 1–50). Ordered by `total_visits` descending.
```jsonc
{
  "success": true,
  "data": [
    {
      "id": 123, "bookid": "BK001",
      "title": "红楼梦", "author": "曹雪芹",
      "total_visits": 1250,
      "last_visited": "2024-01-15 14:30:00",
      "purchdate": "2023-05-20",
      "place_name": "北京书店"
    }
  ],
  "cached": false
}
```
HTTP `400` when `count` is out of range:
```json
{ "success": false, "message": "Count must be between 1 and 50" }
```

---

#### `GET /books/unpopular/{count}` — Most unpopular
Path: `count` (**required**, 1–50). Ordered by `total_visits` ascending
(least visited first). Same item shape as `/books/popular`.

---

#### `GET /books/today[/{month}/{date}]` — Books for a date
- `GET /books/today` — today's books (matching `purchdate` across years).
- `GET /books/today/{month}/{date}` — books bought on that month/day in history.

> **Verify on the wire** — the OpenAPI lists this under books; confirm the exact
> item field names before building the UI.

---

#### `GET /books/visit_history?days=N` — Visit trend
Query: `days` (default `30`, max `365`). Useful for trend charts.
```json
{
  "success": true,
  "data": [
    { "date": "2025-08-07", "visit_count": 45, "day_of_week": "Thursday" }
  ],
  "period_info": {
    "start_date": "2025-07-08",
    "end_date": "2025-08-07",
    "total_days": 30,
    "total_visits": 1250
  },
  "cached": true
}
```

---

#### `GET /books/{bookid}/related[/{count}]` — Related books
Path: `bookid` (5 digits), `count` optional. Books sharing tags/category.

> **Verify on the wire** — returns a list in `/books/list`-like shape; confirm
> exact fields.

---

#### `POST /books/{bookid}/tags` — Add tags
Body (JSON): `{ "tags": ["经典", "文学", "推荐"] }`. Duplicate tags are ignored.
```json
{ "success": true, "message": "Tags added successfully" }
```
- Dates are plain strings in `YYYY-MM-DD` / `YYYY-MM-DD HH:mm:ss` form.
---

### 4.3 Miscellaneous

#### `GET /misc/wotd` — Word of the Day
```json
{
  "success": true,
  "data": {
    "id": 42,
    "word": "serendipity",
    "meaning": "The occurrence of events by chance in a happy way",
    "sentence": "It was pure serendipity that led to their meeting.",
    "type": "noun"
  },
  "cached": false
}
```

#### `GET /misc/qotd` — Quote of the Day
```json
{
  "success": true,
  "data": { "id": 42, "quote": "The only way to do great work is to love what you do.", "source": "Steve Jobs" },
  "cached": false
}
```

#### `GET /misc/weather/current?location=beijing` — Current weather
Query: `location` (city, coords, or ID), `refresh`.
```json
{
  "success": true,
  "data": {
    "location": "beijing",
    "temperature": "25",
    "feels_like": "27",
    "condition": "Sunny",
    "humidity": "65",
    "pressure": "1013",
    "wind_speed": "15",
    "update_time": "2025-08-07T14:30+08:00"
  },
  "cached": true
}
```

#### `GET /misc/weather/forecast?location=beijing&days=3`
Query: `location`, `days` (1-7), `refresh`.
```json
{
  "success": true,
  "data": {
    "location": "beijing",
    "forecast": [
      { "date": "2025-08-07", "temp_max": "28", "temp_min": "18", "condition_day": "Sunny", "condition_night": "Clear" }
    ]
  },
  "cached": true
}
```

---

### 4.4 Reading statistics

#### `GET /readings/summary` — Reading summary
```json
{
  "success": true,
  "data": {
    "books_read": 42,
    "reviews_written": 156,
    "reading_period": { "earliest_date": "2020-01-15", "latest_date": "2025-07-28", "total_days": 1825 }
  },
  "cached": true
}
```

#### `GET /readings/latest[/{count}]` — Latest readings
Path: `count` (default `1`). Newest reading activity first.
```jsonc
{
  "success": true,
  "data": [
    {
      "title": "My Review Title",
      "datein": "2025-07-14",
      "uri": "/reviews/my-review",
      "feature": "feature-image.jpg",
      "bookid": "01234",
      "book_title": "Book Being Reviewed",
      "cover_uri": "https://api.rsywx.com/covers/01234.jpg"
    }
  ],
  "cached": true
}
```

#### `GET /readings/reviews/{page}` — Paginated reviews
Path: `page` (default `1`). Fixed at **9 reviews per page**.
```jsonc
{
  "success": true,
  "data": [
    {
      "title": "My Review Title",
      "datein": "2025-07-14",
      "uri": "https://blog.rsywx.net/2025/07/14/my-review/",
      "feature": "https://blog.rsywx.net/wp-content/uploads/2025/07/feature.jpg",
      "bookid": "01234",
      "book_title": "Book Being Reviewed",
      "cover_uri": "https://api.rsywx.com/covers/01234.jpg"
    }
  ],
  "pagination": { "current_page": 1, "total_pages": 5, "total_results": 42, "per_page": 9 },
  "cached": true
}
```

---

### 4.5 WordPress — "On this day in history"

#### `GET /wp/posts/today[/{month}/{day}]` — Posts on this day
`GET /wp/posts/today` — for today's date.
`GET /wp/posts/today/{month}/{day}` — for a specific date (previous years, excluding current year).
```jsonc
{
  "success": true,
  "data": [
    {
      "ID": 9879,
      "post_title": "三个AI做一道小题目",
      "post_name": "three-ai-small-problem",
      "post_excerpt": "Post excerpt...",
      "post_date": "2022-09-05 10:23:28",
      "post_status": "publish",
      "author": "root",
      "years_ago": 2,
      "permalink": "https://blog.rsywx.net/2022/09/05/three-ai-small-problem/"
    }
  ],
  "date_info": { "month": 9, "day": 5, "date_string": "September 5", "is_today": true },
  "count": 6
}
```
HTTP `400` for invalid month/day:
```json
{ "success": false, "message": "Invalid date parameters" }
```

---

## 5. Caching notes (affects frontend behavior)

| Endpoint | Default TTL | Notes |
|---|---|---|
| `/books/status` | 24h | `?refresh=true` to force |
| `/books/{bookid}` | 24h | visit count still increments live on each hit, but displayed `total_visits` may lag until expiry/refresh |
| `/readings/*` | 24h | `?refresh=true` to force |
| `/misc/weather/*` | 10-30 min | external API |
| `/misc/wotd`, `/misc/qotd` | short | random content |
| `/books/last_visited`, `/forgotten`, `/popular` | may be cached | varies |

For a freshly-updated value, append `?refresh=true`.

---

## 6. Typical frontend flow

1. `GET /health` — optional liveness check.
2. `GET /books/status` — top stats (with `refresh=true` on background refresh).
3. `GET /books/latest/5` and `GET /books/random/5` — homepage shelves.
4. `GET /books/list/title/{q}` or `type=author` — search results (paginate by page).
5. `GET /books/{bookid}` — detail view (fires a visit, which increments `total_visits`).
6. `GET /books/popular/10`, `GET /books/forgotten/10` — recommendation shelves.
7. `GET /readings/reviews/{page}` and `GET /readings/latest` — blog/review widgets.
8. `GET /wp/posts/today` — "on this day in history" widget.
