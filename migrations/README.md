# Database migrations

Forward-only SQL migrations for the RSYWX MySQL schema.

## Layout

- One file per change, named `YYYYMMDDHHMMSS_description.sql` (filename order is
  the apply order).
- The runner tracks applied files in a `schema_migrations` table.

## Running

```bash
php bin/migrate.php
```

It creates `schema_migrations` if needed, then applies any `*.sql` file in this
directory that has not been applied yet. It is safe to run repeatedly; prefer
idempotent statements (`CREATE TABLE IF NOT EXISTS`, ...).

## Notes

- The runner splits files on `;` and skips full-line `--` comments, so avoid
  semicolons inside string literals and avoid client-only directives such as
  `DELIMITER` (write triggers as a single statement).
- `20261106000001_baseline.sql` mirrors the current production schema (tables +
  the `trg_book_visit_last_visit` trigger that maintains the denormalized
  `book_book.total_visits` / `last_visit` counters).
