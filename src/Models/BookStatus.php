<?php

namespace App\Models;

use App\Database\Connection;
use App\Cache\MemoryCache;

class BookStatus
{
    protected $db;
    protected $cache;
    protected $cacheKey = 'book_collection_status';
    protected $cacheTtl = 86400; // 24 hours

    public function __construct()
    {
        $this->db = Connection::getInstance()->getConnection();
        $this->cache = new MemoryCache();
    }

    public function getCollectionStatus($forceRefresh = false)
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

    public function clearCache()
    {
        return $this->cache->delete($this->cacheKey);
    }

    private function fetchCollectionStatusFromDb()
    {
        $query = "
            SELECT 
                (SELECT COUNT(*) FROM book_book WHERE location NOT IN ('na', '--')) as total_books,
                (SELECT COALESCE(SUM(page), 0) FROM book_book WHERE location NOT IN ('na', '--')) as total_pages,
                (SELECT COALESCE(SUM(kword), 0) FROM book_book WHERE location NOT IN ('na', '--')) as total_kwords,
                (SELECT COUNT(*) FROM book_visit) as total_visits
        ";

        $stmt = $this->db->query($query);
        return $stmt->fetch();
    }
}
