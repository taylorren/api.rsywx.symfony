<?php

namespace App\Models;

use App\Database\Connection;
use App\Cache\ApcuCache;
use PDO;

class BookStatus
{
    protected PDO $db;
    protected ApcuCache $cache;
    protected string $cacheKey = 'book_collection_status';
    protected int $cacheTtl = 86400; // 24 hours

    public function __construct()
    {
        $this->db = Connection::getInstance()->getConnection();
        $this->cache = new ApcuCache();
    }

    public function getCollectionStatus(bool $forceRefresh = false): array
    {
        // The static totals (books/pages/keywords) change rarely and are cached
        // for 24h. total_visits changes constantly, so it is computed fresh on
        // every call from the denormalized counter column (a cheap SUM over the
        // ~2k-row book_book table) instead of being served stale from cache.
        $static = null;
        if ($forceRefresh === false) {
            $static = $this->cache->get($this->cacheKey);
        }

        $fromCache = ($static !== null);

        if ($static === null) {
            $static = $this->fetchStaticStatsFromDb();
            $this->cache->set($this->cacheKey, $static, $this->cacheTtl);
        }

        return [
            'data' => [
                'total_books' => (int) $static['total_books'],
                'total_pages' => (int) $static['total_pages'],
                'total_kwords' => (int) $static['total_kwords'],
                'total_visits' => $this->fetchTotalVisitsFromDb(),
            ],
            'from_cache' => $fromCache,
        ];
    }

    public function clearCache(): bool
    {
        return $this->cache->delete($this->cacheKey);
    }

    private function fetchStaticStatsFromDb(): array
    {
        // Single aggregate pass over the small, fully indexed book_book table.
        $query = "
            SELECT
                COUNT(*)                AS total_books,
                COALESCE(SUM(page), 0)  AS total_pages,
                COALESCE(SUM(kword), 0) AS total_kwords
            FROM book_book
            WHERE location NOT IN ('na', '--')
        ";

        return $this->db->query($query)->fetch();
    }

    private function fetchTotalVisitsFromDb(): int
    {
        // Reads the denormalized counter (maintained by the book_visit trigger)
        // instead of scanning the multi-million-row book_visit table.
        $query = "
            SELECT COALESCE(SUM(total_visits), 0) AS total_visits
            FROM book_book
            WHERE location NOT IN ('na', '--')
        ";

        return (int) $this->db->query($query)->fetch()['total_visits'];
    }
}
