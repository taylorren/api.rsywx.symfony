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
        // Try to get from cache first
        if (!$forceRefresh) {
            $cached = $this->cache->get($this->cacheKey);
            if ($cached !== null) {
                return [
                    'data' => $cached,
                    'from_cache' => true
                ];
            }
        }

        // Fetch fresh data from database
        $status = $this->fetchCollectionStatusFromDb();
        
        // Cache the result
        $this->cache->set($this->cacheKey, $status, $this->cacheTtl);
        
        return [
            'data' => $status,
            'from_cache' => false
        ];
    }

    public function clearCache(): bool
    {
        return $this->cache->delete($this->cacheKey);
    }

    private function fetchCollectionStatusFromDb(): array
    {
        // Single aggregate pass over book_book (the table is small and fully
        // indexed via idx_book_book_nl on location). Previously this issued four
        // separate scalar subqueries, each rescanning the table. total_visits
        // reads the denormalized counter column (maintained by the book_visit
        // trigger), avoiding a full scan of book_visit (~2.3M rows).
        $query = "
            SELECT
                COUNT(*)                          AS total_books,
                COALESCE(SUM(page), 0)            AS total_pages,
                COALESCE(SUM(kword), 0)           AS total_kwords,
                COALESCE(SUM(total_visits), 0)    AS total_visits
            FROM book_book
            WHERE location NOT IN ('na', '--')
        ";

        $stmt = $this->db->query($query);
        return $stmt->fetch();
    }
}
