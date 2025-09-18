<?php

namespace App\Models;

use App\Database\Connection;
use App\Cache\MemoryCache;

class Reading
{
    private $db;
    private $cache;

    public function __construct()
    {
        $this->db = Connection::getInstance()->getConnection();
        $this->cache = new MemoryCache();
    }

    public function getReadingSummary($forceRefresh = false)
    {
        $cacheKey = "reading_summary";

        // Cache for 24 hours since reading stats don't change frequently
        $readingCacheTtl = 86400; // 24 hours

        // Get cached data
        $summaryData = null;
        if (!$forceRefresh) {
            $summaryData = $this->cache->get($cacheKey);
        }

        $fromCache = ($summaryData !== null);

        // If not cached, fetch from database
        if ($summaryData === null) {
            $summaryData = $this->fetchReadingSummaryFromDb();

            // Cache the result
            $this->cache->set($cacheKey, $summaryData, $readingCacheTtl);
        }

        return [
            'data' => $summaryData,
            'from_cache' => $fromCache
        ];
    }

    private function fetchReadingSummaryFromDb()
    {
        // Get books read count (headlines with display = 1)
        $booksReadQuery = "SELECT COUNT(bid) as books_read FROM book_headline WHERE display = 1";
        $stmt = $this->db->prepare($booksReadQuery);
        $stmt->execute();
        $booksRead = $stmt->fetch()['books_read'];

        // Get reviews written count (reviews linked to displayed headlines)
        $reviewsQuery = "
            SELECT COUNT(r.id) as reviews_written
            FROM book_review r 
            INNER JOIN book_headline h ON r.hid = h.hid 
            WHERE h.display = 1
        ";
        $stmt = $this->db->prepare($reviewsQuery);
        $stmt->execute();
        $reviewsWritten = $stmt->fetch()['reviews_written'];

        // Get reading period (date range from displayed headlines)
        $dateRangeQuery = "
            SELECT 
                MIN(create_at) as earliest_date,
                MAX(create_at) as latest_date
            FROM book_headline 
            WHERE display = 1
        ";
        $stmt = $this->db->prepare($dateRangeQuery);
        $stmt->execute();
        $dateRange = $stmt->fetch();

        // Calculate total days between earliest and latest dates
        $totalDays = 0;
        if ($dateRange['earliest_date'] && $dateRange['latest_date']) {
            $earliest = new \DateTime($dateRange['earliest_date']);
            $latest = new \DateTime($dateRange['latest_date']);
            $totalDays = $latest->diff($earliest)->days;
        }

        return [
            'books_read' => (int)$booksRead,
            'reviews_written' => (int)$reviewsWritten,
            'reading_period' => [
                'earliest_date' => $dateRange['earliest_date'],
                'latest_date' => $dateRange['latest_date'],
                'total_days' => $totalDays
            ]
        ];
    }

    public function getLatestReadings($count = 1, $forceRefresh = false)
    {
        $cacheKey = "latest_readings_{$count}";

        // Cache for 2 hours since reading activities don't change very frequently
        $latestReadingsCacheTtl = 7200; // 2 hours

        // Get cached data
        $readingsData = null;
        if (!$forceRefresh) {
            $readingsData = $this->cache->get($cacheKey);
        }

        $fromCache = ($readingsData !== null);

        // If not cached, fetch from database
        if ($readingsData === null) {
            $readingsData = $this->fetchLatestReadingsFromDb($count);

            // Cache the result
            $this->cache->set($cacheKey, $readingsData, $latestReadingsCacheTtl);
        }

        return [
            'data' => $readingsData,
            'from_cache' => $fromCache
        ];
    }

    private function fetchLatestReadingsFromDb($count)
    {
        $query = "
            SELECT r.title, r.datein, r.uri, r.feature,
                   b.bookid, b.title as book_title
            FROM book_review r
            INNER JOIN book_headline h ON r.hid = h.hid
            INNER JOIN book_book b ON h.bid = b.id
            WHERE h.display = 1
            ORDER BY r.datein DESC
            LIMIT ?
        ";

        $stmt = $this->db->prepare($query);
        $stmt->execute([$count]);
        $readings = $stmt->fetchAll();

        // Add cover URI for the book being reviewed
        foreach ($readings as &$reading) {
            $reading['cover_uri'] = "https://api.rsywx.com/covers/{$reading['bookid']}.jpg";
        }

        return $readings;
    }

    public function clearReadingSummaryCache()
    {
        $cacheKey = "reading_summary";
        return $this->cache->delete($cacheKey);
    }

    public function clearLatestReadingsCache($count = null)
    {
        if ($count !== null) {
            $cacheKey = "latest_readings_{$count}";
            return $this->cache->delete($cacheKey);
        }

        // For clearing all latest readings cache, we'd need to clear all cache
        return $this->cache->clear();
    }

    public function getReviewsPaginated($page = 1, $perPage = 9, $forceRefresh = false)
    {
        $cacheKey = "reviews_paginated_{$page}_{$perPage}";

        // Cache for 1 hour since reviews don't change very frequently
        $reviewsCacheTtl = 3600; // 1 hour

        // Get cached data
        $reviewsData = null;
        if (!$forceRefresh) {
            $reviewsData = $this->cache->get($cacheKey);
        }

        $fromCache = ($reviewsData !== null);

        // If not cached, fetch from database
        if ($reviewsData === null) {
            $reviewsData = $this->fetchReviewsPaginatedFromDb($page, $perPage);

            // Cache the result
            $this->cache->set($cacheKey, $reviewsData, $reviewsCacheTtl);
        }

        return [
            'data' => $reviewsData['reviews'],
            'pagination' => $reviewsData['pagination'],
            'from_cache' => $fromCache
        ];
    }

    private function fetchReviewsPaginatedFromDb($page, $perPage)
    {
        // First, get total count for pagination
        $countQuery = "
            SELECT COUNT(r.id) as total_count
            FROM book_review r
            INNER JOIN book_headline h ON r.hid = h.hid
            WHERE h.display = 1
        ";

        $stmt = $this->db->prepare($countQuery);
        $stmt->execute();
        $totalCount = (int)$stmt->fetch()['total_count'];

        // Calculate pagination info
        $totalPages = ceil($totalCount / $perPage);
        $offset = ($page - 1) * $perPage;

        // Get the reviews for current page
        $reviewsQuery = "
            SELECT r.title, r.datein, r.uri, r.feature,
                   b.bookid, b.title as book_title
            FROM book_review r
            INNER JOIN book_headline h ON r.hid = h.hid
            INNER JOIN book_book b ON h.bid = b.id
            WHERE h.display = 1
            ORDER BY r.datein DESC
            LIMIT ? OFFSET ?
        ";

        $stmt = $this->db->prepare($reviewsQuery);
        $stmt->execute([$perPage, $offset]);
        $reviews = $stmt->fetchAll();

        // Add cover URI for each book being reviewed
        foreach ($reviews as &$review) {
            $review['cover_uri'] = "https://api.rsywx.com/covers/{$review['bookid']}.jpg";
        }

        return [
            'reviews' => $reviews,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_results' => $totalCount,
                'per_page' => $perPage
            ]
        ];
    }

    public function clearReviewsPaginatedCache($page = null, $perPage = null)
    {
        if ($page !== null && $perPage !== null) {
            $cacheKey = "reviews_paginated_{$page}_{$perPage}";
            return $this->cache->delete($cacheKey);
        }

        // For clearing all paginated reviews cache, we'd need to clear all cache
        return $this->cache->clear();
    }
}
