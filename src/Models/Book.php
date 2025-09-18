<?php

namespace App\Models;

use App\Database\Connection;
use App\Cache\MemoryCache;

class Book
{
    private $db;
    private $cache;
    private $cacheTtl = 86400; // 24 hours

    public function __construct()
    {
        $this->db = Connection::getInstance()->getConnection();
        $this->cache = new MemoryCache();
    }

    public function getBookDetail($bookid, $forceRefresh = false)
    {
        $cacheKey = "book_detail_{$bookid}";

        // Get cached book data
        $bookData = null;
        if (!$forceRefresh) {
            $bookData = $this->cache->get($cacheKey);
        }

        $fromCache = ($bookData !== null);

        // If not cached, fetch from database
        if ($bookData === null) {
            $bookData = $this->fetchBookDataFromDb($bookid);

            if ($bookData === null) {
                return null; // Book not found
            }

            // Cache the book data
            $this->cache->set($cacheKey, $bookData, $this->cacheTtl);
        }

        // Always get fresh visit data
        $visitData = $this->getBookVisitData($bookData['id']);

        // Update visit record when book is accessed
        $this->updateVisit($bookData['id']);

        // Merge cached book data with fresh visit data
        return [
            'data' => array_merge($bookData, $visitData),
            'from_cache' => $fromCache
        ];
    }

    private function fetchBookDataFromDb($bookid)
    {
        // Get complete book information with all related data - FULL COVERAGE
        $query = "
            SELECT b.id, b.bookid, b.title, b.author, b.region, b.copyrighter, 
                   b.translated, b.purchdate, b.price, b.pubdate, b.printdate,
                   b.ver, b.deco, b.kword, b.page, b.isbn, b.category, 
                   b.ol, b.intro, b.instock, b.location,
                   p.name as place_name, 
                   pub.name as publisher_name
            FROM book_book b
            LEFT JOIN book_place p ON b.place = p.id
            LEFT JOIN book_publisher pub ON b.publisher = pub.id
            WHERE b.bookid = ? AND b.location NOT IN ('na', '--')
        ";

        $stmt = $this->db->prepare($query);
        $stmt->execute([$bookid]);
        $book = $stmt->fetch();

        if (!$book) {
            return null;
        }

        // Get tags for this book
        $book['tags'] = $this->getBookTags($book['id']);

        // Get reviews for this book
        $book['reviews'] = $this->getBookReviews($book['id']);

        // Add generated cover URI
        $book['cover_uri'] = "https://api.rsywx.com/covers/{$book['bookid']}.jpg";

        // Convert fields to proper types for complete coverage
        $book['id'] = (int)$book['id'];
        $book['translated'] = (bool)$book['translated'];
        $book['instock'] = (bool)$book['instock'];

        // Numeric fields with null handling
        $book['price'] = $book['price'] !== null ? (float)$book['price'] : null;
        $book['kword'] = $book['kword'] !== null ? (int)$book['kword'] : null;
        $book['page'] = $book['page'] !== null ? (int)$book['page'] : null;

        // String fields with null handling (ensure consistent null values)
        $book['copyrighter'] = $book['copyrighter'] ?: null;
        $book['category'] = $book['category'] ?: null;
        $book['ol'] = $book['ol'] ?: null;
        $book['intro'] = $book['intro'] ?: null;
        $book['ver'] = $book['ver'] ?: null;
        $book['deco'] = $book['deco'] ?: null;

        return $book;
    }

    private function getBookTags($bookId)
    {
        $query = "SELECT tag FROM book_taglist WHERE bid = ? ORDER BY tag";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$bookId]);

        $tags = [];
        while ($row = $stmt->fetch()) {
            $tags[] = $row['tag'];
        }

        return $tags;
    }

    private function getBookReviews($bookId)
    {
        $query = "
            SELECT r.id, r.title, r.datein, r.uri, r.feature
            FROM book_review r
            INNER JOIN book_headline h ON r.hid = h.hid
            WHERE h.bid = ? AND h.display = 1
            ORDER BY r.datein DESC
        ";

        $stmt = $this->db->prepare($query);
        $stmt->execute([$bookId]);

        return $stmt->fetchAll();
    }

    private function getBookVisitData($bookId)
    {
        $query = "
            SELECT 
                COUNT(*) as total_visits,
                MAX(visitwhen) as last_visited
            FROM book_visit 
            WHERE bookid = ?
        ";

        $stmt = $this->db->prepare($query);
        $stmt->execute([$bookId]);
        $result = $stmt->fetch();

        return [
            'total_visits' => (int)$result['total_visits'],
            'last_visited' => $result['last_visited']
        ];
    }

    public function getLatestBooks($count = 1, $forceRefresh = false)
    {
        $cacheKey = "latest_books_{$count}";

        // Get cached data
        $booksData = null;
        if (!$forceRefresh) {
            $booksData = $this->cache->get($cacheKey);
        }

        $fromCache = ($booksData !== null);

        // If not cached, fetch from database using new unified system
        if ($booksData === null) {
            $queryBuilder = new BookQueryBuilder();
            $books = $queryBuilder
                ->includeFields(['purchase'])
                ->latest($count)
                ->execute();

            // Convert to array format for caching
            $booksData = array_map(function ($book) {
                return $book->toArray();
            }, $books);

            // Cache the result
            $this->cache->set($cacheKey, $booksData, $this->cacheTtl);
        }

        return [
            'data' => $booksData,
            'from_cache' => $fromCache
        ];
    }

    public function clearBookCache($bookid)
    {
        $cacheKey = "book_detail_{$bookid}";
        return $this->cache->delete($cacheKey);
    }

    public function clearLatestBooksCache($count = null)
    {
        if ($count !== null) {
            $cacheKey = "latest_books_{$count}";
            return $this->cache->delete($cacheKey);
        }

        // For Symfony Cache, we can't easily iterate over keys, so clear all cache
        return $this->cache->clear();
    }

    public function getRandomBooks($count = 1, $forceRefresh = false)
    {
        $cacheKey = "random_books_{$count}";

        // Random books shouldn't be cached for too long, use shorter TTL
        $randomCacheTtl = 3600; // 1 hour

        // Get cached data
        $booksData = null;
        if (!$forceRefresh) {
            $booksData = $this->cache->get($cacheKey);
        }

        $fromCache = ($booksData !== null);

        // If not cached, fetch from database using unified system
        if ($booksData === null) {
            $queryBuilder = new BookQueryBuilder();
            $books = $queryBuilder
                ->includeFields(['purchase', 'visit_stats'])
                ->random($count)
                ->execute();

            // Convert to array format for caching
            $booksData = array_map(function ($book) {
                return $book->toArray();
            }, $books);

            // Cache the result with shorter TTL
            $this->cache->set($cacheKey, $booksData, $randomCacheTtl);
        }

        return [
            'data' => $booksData,
            'from_cache' => $fromCache
        ];
    }

    public function clearRandomBooksCache($count = null)
    {
        if ($count !== null) {
            $cacheKey = "random_books_{$count}";
            return $this->cache->delete($cacheKey);
        }

        // For Symfony Cache, we can't easily iterate over keys, so clear all cache
        return $this->cache->clear();
    }

    public function getLastVisitedBooks($count = 1, $forceRefresh = false)
    {
        $cacheKey = "last_visited_books_{$count}";

        // Get cached data
        $booksData = null;
        if (!$forceRefresh) {
            $booksData = $this->cache->get($cacheKey);
        }

        $fromCache = ($booksData !== null);

        // If not cached, fetch from database using unified system
        if ($booksData === null) {
            $queryBuilder = new BookQueryBuilder();
            $books = $queryBuilder
                ->lastVisited($count)
                ->execute();

            // Convert to array format for caching
            $booksData = array_map(function ($book) {
                return $book->toArray();
            }, $books);

            // Cache the result with data-driven TTL based on actual visit patterns
            // Analysis shows 80% of visits have <5min gaps, so 2min cache captures most activity
            $lastVisitedCacheTtl = 120; // 2 minutes - optimal for active reading sessions
            $this->cache->set($cacheKey, $booksData, $lastVisitedCacheTtl);
        }

        return [
            'data' => $booksData,
            'from_cache' => $fromCache
        ];
    }

    public function clearLastVisitedBooksCache($count = null)
    {
        if ($count !== null) {
            $cacheKey = "last_visited_books_{$count}";
            return $this->cache->delete($cacheKey);
        }

        // For Symfony Cache, we can't easily iterate over keys, so clear all cache
        return $this->cache->clear();
    }

    public function getForgottenBooks($count = 1, $forceRefresh = false)
    {
        $cacheKey = "forgotten_books_{$count}";

        // Forgotten books can have longer cache time (1 hour) since they change slowly
        $forgottenCacheTtl = 3600; // 1 hour

        // Get cached data
        $booksData = null;
        if (!$forceRefresh) {
            $booksData = $this->cache->get($cacheKey);
        }

        $fromCache = ($booksData !== null);

        // If not cached, fetch from database using unified system
        if ($booksData === null) {
            $queryBuilder = new BookQueryBuilder();
            $books = $queryBuilder
                ->includeFields(['computed'])
                ->forgotten($count)
                ->execute();

            // Convert to array format for caching
            $booksData = array_map(function ($book) {
                return $book->toArray();
            }, $books);

            // Cache the result with longer TTL
            $this->cache->set($cacheKey, $booksData, $forgottenCacheTtl);
        }

        return [
            'data' => $booksData,
            'from_cache' => $fromCache
        ];
    }
    // TODO: Based on SQL performance analysis, maybe we should add a new filed in Book_Book to capture its latest visit date as a redundancy and quick SQL

    public function clearForgottenBooksCache($count = null)
    {
        if ($count !== null) {
            $cacheKey = "forgotten_books_{$count}";
            return $this->cache->delete($cacheKey);
        }

        // For Symfony Cache, we can't easily iterate over keys, so clear all cache
        return $this->cache->clear();
    }

    public function getTodaysBooks($month = null, $date = null, $forceRefresh = false)
    {
        // Default to today's date if not provided
        $month = $month ?? (int)date('n');
        $date = $date ?? (int)date('j');
        $currentYear = date('Y');

        // Format month and date with leading zeros for consistency
        $monthDay = sprintf('%02d-%02d', $month, $date);
        $requestedDate = sprintf('%04d-%02d-%02d', $currentYear, $month, $date);
        $todayMonthDay = date('m-d');
        $isToday = ($monthDay === $todayMonthDay);

        $cacheKey = "todays_books_{$monthDay}";

        // Today's books can be cached for a full day since they only change once per day
        $todaysCacheTtl = 86400; // 24 hours

        // Get cached data
        $booksData = null;
        if (!$forceRefresh) {
            $booksData = $this->cache->get($cacheKey);
        }

        $fromCache = ($booksData !== null);

        // If not cached, fetch from database using unified system
        if ($booksData === null) {
            $queryBuilder = new BookQueryBuilder();
            $books = $queryBuilder
                ->includeFields(['purchase'])
                ->todaysBooks($month, $date)
                ->execute();

            // Convert to array format for caching
            $booksData = array_map(function ($book) {
                return $book->toArray();
            }, $books);

            // Cache the result with full day TTL
            $this->cache->set($cacheKey, $booksData, $todaysCacheTtl);
        }

        return [
            'data' => $booksData,
            'from_cache' => $fromCache,
            'date_info' => [
                'requested_date' => $requestedDate,
                'month_day' => $monthDay,
                'is_today' => $isToday
            ]
        ];
    }

    public function clearTodaysBooksCache()
    {
        $monthDay = date('m-d');
        $cacheKey = "todays_books_{$monthDay}";
        return $this->cache->delete($cacheKey);
    }

    public function getVisitHistory($days = 30, $forceRefresh = false)
    {
        $cacheKey = "visit_history_{$days}";

        // Visit history can be cached for a few hours since it's for trend analysis
        $visitHistoryCacheTtl = 3600; // 1 hour

        // Get cached data
        $historyData = null;
        if (!$forceRefresh) {
            $historyData = $this->cache->get($cacheKey);
        }

        $fromCache = ($historyData !== null);

        // If not cached, fetch from database
        if ($historyData === null) {
            $historyData = $this->fetchVisitHistoryFromDb($days);

            // Cache the result
            $this->cache->set($cacheKey, $historyData, $visitHistoryCacheTtl);
        }

        return [
            'data' => $historyData['daily_counts'],
            'from_cache' => $fromCache,
            'period_info' => $historyData['period_info']
        ];
    }

    private function fetchVisitHistoryFromDb($days)
    {
        $endDate = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime("-{$days} days"));

        $query = "
            SELECT 
                DATE(visitwhen) as visit_date,
                COUNT(*) as visit_count
            FROM book_visit
            WHERE DATE(visitwhen) >= ?
              AND DATE(visitwhen) <= ?
            GROUP BY DATE(visitwhen)
            ORDER BY visit_date ASC
        ";

        $stmt = $this->db->prepare($query);
        $stmt->execute([$startDate, $endDate]);
        $results = $stmt->fetchAll();

        // Create a complete date range with zero counts for missing days
        $dailyCounts = [];
        $totalVisits = 0;
        $currentDate = new \DateTime($startDate);
        $endDateTime = new \DateTime($endDate);

        // Create array indexed by date for quick lookup
        $visitsByDate = [];
        foreach ($results as $row) {
            $visitsByDate[$row['visit_date']] = [
                'visit_count' => (int)$row['visit_count'],
                'day_of_week' => $row['day_of_week']
            ];
            $totalVisits += (int)$row['visit_count'];
        }

        // Fill in all dates in the range
        while ($currentDate <= $endDateTime) {
            $dateStr = $currentDate->format('Y-m-d');
            $dayOfWeek = $currentDate->format('l'); // Full day name

            if (isset($visitsByDate[$dateStr])) {
                $dailyCounts[] = [
                    'date' => $dateStr,
                    'visit_count' => $visitsByDate[$dateStr]['visit_count'],
                    'day_of_week' => $visitsByDate[$dateStr]['day_of_week']
                ];
            } else {
                $dailyCounts[] = [
                    'date' => $dateStr,
                    'visit_count' => 0,
                    'day_of_week' => $dayOfWeek
                ];
            }

            $currentDate->add(new \DateInterval('P1D'));
        }

        return [
            'daily_counts' => $dailyCounts,
            'period_info' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'total_days' => $days + 1, // Include both start and end dates
                'total_visits' => $totalVisits
            ]
        ];
    }

    public function clearVisitHistoryCache($days = null)
    {
        if ($days !== null) {
            $cacheKey = "visit_history_{$days}";
            return $this->cache->delete($cacheKey);
        }

        // Clear all visit history cache entries (this is a simplified approach)
        return $this->cache->clear();
    }

    public function listBooks($type = 'title', $value = '-', $page = 1, $perPage = null)
    {
        if ($perPage === null) {
            $perPage = (int)($_ENV['LIST_PER_PAGE'] ?? 10);
        }

        $queryBuilder = new BookQueryBuilder();
        $queryBuilder->includeFields(['purchase', 'rich']);

        // Always order by ID desc (latest books first)
        $queryBuilder->orderBy('b.id DESC');

        // Apply search filter if value is not a wildcard
        if ($value !== '-') {
            switch ($type) {
                case 'author':
                    $queryBuilder->searchByAuthor($value);
                    break;
                case 'title':
                    $queryBuilder->searchByTitle($value);
                    break;
                case 'tags':
                    $queryBuilder->searchByTag($value);
                    break;
                case 'misc':
                    $queryBuilder->searchMisc($value);
                    break;
            }
        }

        $result = $queryBuilder->paginate($page, $perPage)->execute();
        $total = $queryBuilder->count();

        // Load tags for each book
        foreach ($result as $book) {
            $book->tags = $this->getBookTags($book->id);
        }

        $books = array_map(function ($book) {
            return $book->toArray();
        }, $result);

        return [
            'data' => $books,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => ceil($total / $perPage),
                'total_results' => $total,
                'per_page' => $perPage
            ]
        ];
    }

    private function updateVisit($bookId)
    {
        // Get client IP address
        $ipAddress = $this->getClientIpAddress();

        // Get geolocation data for the IP
        $geoData = $this->getIpGeolocation($ipAddress);

        $query = "
            INSERT INTO book_visit (bookid, visitwhen, ip_address, country, city, region)
            VALUES (?, NOW(), ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            visitwhen = NOW(),
            ip_address = VALUES(ip_address),
            country = VALUES(country),
            city = VALUES(city),
            region = VALUES(region)
        ";

        $stmt = $this->db->prepare($query);
        $stmt->execute([
            $bookId,
            $ipAddress,
            $geoData['country'],
            $geoData['city'],
            $geoData['region']
        ]);
    }

    private function getClientIpAddress()
    {
        // Check for various headers that might contain the real IP
        $ipHeaders = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($ipHeaders as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]); // Take the first IP if multiple

                // Validate IP address
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        // Fallback to REMOTE_ADDR even if it's private/reserved
        return $_SERVER['REMOTE_ADDR'] ?? null;
    }

    private function getIpGeolocation($ipAddress)
    {
        // Default values
        $defaultGeo = [
            'country' => null,
            'city' => null,
            'region' => null
        ];

        // Skip geolocation for local/private IPs
        if (
            !$ipAddress ||
            filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false
        ) {
            return $defaultGeo;
        }

        try {
            // Use ip-api.com (free service, 1000 requests/month limit)
            // Alternative services: ipinfo.io, ipgeolocation.io, etc.
            $url = "http://ip-api.com/json/{$ipAddress}?fields=status,country,regionName,city";

            $context = stream_context_create([
                'http' => [
                    'timeout' => 3, // 3 second timeout
                    'user_agent' => 'RSYWX-Library-API/1.0'
                ]
            ]);

            $response = @file_get_contents($url, false, $context);

            if ($response === false) {
                return $defaultGeo;
            }

            $data = json_decode($response, true);

            if (!$data || $data['status'] !== 'success') {
                return $defaultGeo;
            }

            return [
                'country' => $data['country'] ?? null,
                'city' => $data['city'] ?? null,
                'region' => $data['regionName'] ?? null
            ];
        } catch (\Exception $e) {
            // Log error if needed, but don't fail the visit tracking
            error_log("IP Geolocation failed for {$ipAddress}: " . $e->getMessage());
            return $defaultGeo;
        }
    }

    public function addBookTags($bookid, $tags)
    {
        // First, get the book's internal ID
        $query = "SELECT id FROM book_book WHERE bookid = ? AND location NOT IN ('na', '--')";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$bookid]);
        $book = $stmt->fetch();

        if (!$book) {
            throw new \InvalidArgumentException('Book not found');
        }

        $bookId = $book['id'];
        $addedTags = [];
        $duplicateTags = [];

        foreach ($tags as $tag) {
            $tag = trim($tag);
            if (empty($tag)) {
                continue;
            }

            // Check if tag already exists for this book
            $checkQuery = "SELECT COUNT(*) as count FROM book_taglist WHERE bid = ? AND tag = ?";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->execute([$bookId, $tag]);
            $exists = $checkStmt->fetch()['count'] > 0;

            if ($exists) {
                $duplicateTags[] = $tag;
            } else {
                // Insert new tag
                $insertQuery = "INSERT INTO book_taglist (bid, tag) VALUES (?, ?)";
                $insertStmt = $this->db->prepare($insertQuery);
                $insertStmt->execute([$bookId, $tag]);
                $addedTags[] = $tag;
            }
        }

        return [
            'added' => $addedTags,
            'duplicates' => $duplicateTags
        ];
    }

    public function getRelatedBooks($bookid, $count = 5, $forceRefresh = false)
    {
        $cacheKey = "related_books_discovery_{$bookid}_{$count}";

        // Related books cache for 1 hour (they don't change frequently)
        $relatedCacheTtl = 3600;

        // Get cached data
        $relatedData = null;
        if (!$forceRefresh) {
            $relatedData = $this->cache->get($cacheKey);
        }

        $fromCache = ($relatedData !== null);

        // If not cached, compute related books with discovery
        if ($relatedData === null) {
            $relatedData = $this->computeDiscoveryRelatedBooks($bookid, $count);

            if ($relatedData === null) {
                return null; // Book not found
            }

            // Cache the result
            $this->cache->set($cacheKey, $relatedData, $relatedCacheTtl);
        }

        return [
            'data' => $relatedData['books'],
            'categories' => $relatedData['categories'],
            'discovery_info' => $relatedData['discovery_info'],
            'primary_factors' => $relatedData['primary_factors'],
            'from_cache' => $fromCache
        ];
    }

    private function computeDiscoveryRelatedBooks($bookid, $count)
    {
        // First, get the source book details
        $sourceBook = $this->fetchBookDataFromDb($bookid);
        if (!$sourceBook) {
            return null;
        }

        // Get expanded candidate pool for discovery
        $query = "
            SELECT b.id, b.bookid, b.title, b.author, b.region, b.category, 
                   b.copyrighter, b.translated, b.location, b.purchdate,
                   p.name as place_name, pub.name as publisher_name,
                   (SELECT COUNT(*) FROM book_visit bv WHERE bv.bookid = b.id) as visit_count
            FROM book_book b
            LEFT JOIN book_place p ON b.place = p.id
            LEFT JOIN book_publisher pub ON b.publisher = pub.id
            WHERE b.location NOT IN ('na', '--') 
              AND b.bookid != ?
            ORDER BY b.id DESC
            LIMIT 800
        ";

        $stmt = $this->db->prepare($query);
        $stmt->execute([$bookid]);
        $candidates = $stmt->fetchAll();

        // Calculate discovery-enhanced scores
        $allScoredBooks = [];
        $primaryFactors = [];

        foreach ($candidates as $candidate) {
            $score = $this->calculateDiscoveryScore($sourceBook, $candidate);

            if ($score['total_score'] > 0.15) { // Lower threshold for discovery
                $candidate['tags'] = $this->getBookTags($candidate['id']);
                $candidate['cover_uri'] = "https://api.rsywx.com/covers/{$candidate['bookid']}.jpg";

                // Convert fields to proper types
                $candidate['id'] = (int)$candidate['id'];
                $candidate['translated'] = (bool)$candidate['translated'];
                $candidate['visit_count'] = (int)$candidate['visit_count'];

                $allScoredBooks[] = [
                    'book' => $candidate,
                    'similarity_score' => round($score['similarity_score'], 3),
                    'discovery_score' => round($score['discovery_score'], 3),
                    'total_score' => round($score['total_score'], 3),
                    'category' => $score['category'],
                    'relationship_reasons' => $score['reasons']
                ];

                // Track primary factors
                foreach ($score['factors'] as $factor) {
                    if (!in_array($factor, $primaryFactors)) {
                        $primaryFactors[] = $factor;
                    }
                }
            }
        }

        // Apply discovery distribution strategy
        return $this->applyDiscoveryDistribution($allScoredBooks, $count, $primaryFactors);
    }

    private function calculateDiscoveryScore($sourceBook, $candidateBook)
    {
        // Get base similarity score
        $baseScore = $this->calculateSimilarityScore($sourceBook, $candidateBook);

        $similarityScore = $baseScore['total_score'];
        $discoveryScore = 0;
        $category = '相似推荐';
        $reasons = $baseScore['reasons'];
        $factors = $baseScore['factors'];

        // Get tags for discovery analysis
        $sourceTags = $this->getBookTags($sourceBook['id']);
        $candidateTags = $this->getBookTags($candidateBook['id']);

        // Discovery bonuses

        // 1. Different author but shared themes (exploration bonus)
        if (
            $sourceBook['author'] !== $candidateBook['author'] &&
            $sourceBook['author'] !== '佚名' && $candidateBook['author'] !== '佚名' &&
            $sourceBook['author'] !== 'anonymous' && $candidateBook['author'] !== 'anonymous'
        ) {
            $tagOverlap = count(array_intersect($sourceTags, $candidateTags));
            if ($tagOverlap >= 1 && $tagOverlap <= 2) {
                $discoveryScore += 0.15;
                $category = '探索作者';
                $reasons[] = "探索新作者: " . $candidateBook['author'];
                $factors[] = 'author_exploration';
            }
        }

        // 2. Different region but similar themes (cultural bridge)
        if ($sourceBook['region'] !== $candidateBook['region']) {
            $tagOverlap = count(array_intersect($sourceTags, $candidateTags));
            if ($tagOverlap >= 1) {
                $discoveryScore += 0.12;
                $category = '文化桥梁';
                $reasons[] = "文化桥梁: " . $candidateBook['region'];
                $factors[] = 'cultural_exploration';
            }
        }

        // 3. Adjacent genre discovery (expand literary horizons)
        $adjacentGenres = $this->findAdjacentGenres($sourceTags);
        $candidateGenreMatch = array_intersect($adjacentGenres, $candidateTags);
        if (!empty($candidateGenreMatch)) {
            $discoveryScore += 0.1;
            $category = '发现类型';
            $reasons[] = "相邻类型: " . implode(', ', array_slice($candidateGenreMatch, 0, 2));
            $factors[] = 'genre_expansion';
        }

        // 4. Quality/popularity bonus (ensure good discoveries)
        $visitCount = (int)$candidateBook['visit_count'];
        if ($visitCount > 100) { // Well-regarded books
            $discoveryScore += 0.05;
            $factors[] = 'quality_filter';
        }

        // 5. Serendipity factor for highly-rated books with minimal overlap
        if ($visitCount > 500 && $similarityScore < 0.2 && $similarityScore > 0.05) {
            $discoveryScore += 0.2;
            $category = '意外发现';
            $reasons[] = "意外发现: 高质量推荐";
            $factors[] = 'serendipity';
        }

        $totalScore = ($similarityScore * 0.7) + ($discoveryScore * 0.3);

        return [
            'similarity_score' => $similarityScore,
            'discovery_score' => $discoveryScore,
            'total_score' => $totalScore,
            'category' => $category,
            'reasons' => $reasons,
            'factors' => array_unique($factors)
        ];
    }

    private function findAdjacentGenres($sourceTags)
    {
        // Define genre adjacency map for literary discovery
        $genreMap = [
            '文学' => ['哲学', '历史', '传记', '散文', '诗歌'],
            '哲学' => ['文学', '宗教', '心理学', '社会学'],
            '历史' => ['传记', '政治', '文学', '社会学'],
            '科幻' => ['哲学', '科学', '未来学', '技术'],
            '推理' => ['心理学', '犯罪', '悬疑', '社会'],
            '经典' => ['文学', '哲学', '历史', '传记'],
            '现代' => ['当代', '实验', '先锋', '后现代'],
            '意大利' => ['欧洲', '地中海', '拉丁', '文艺复兴'],
            '散文' => ['随笔', '游记', '回忆录', '日记']
        ];

        $adjacentGenres = [];
        foreach ($sourceTags as $tag) {
            if (isset($genreMap[$tag])) {
                $adjacentGenres = array_merge($adjacentGenres, $genreMap[$tag]);
            }
        }

        return array_unique($adjacentGenres);
    }

    private function applyDiscoveryDistribution($allScoredBooks, $count, $primaryFactors)
    {
        // Sort by total score (discovery-enhanced)
        usort($allScoredBooks, function ($a, $b) {
            return $b['total_score'] <=> $a['total_score'];
        });

        // Apply discovery distribution strategy
        $distribution = $this->calculateDistribution($count);

        $categorizedBooks = [
            '相似推荐' => [],
            '探索作者' => [],
            '文化桥梁' => [],
            '发现类型' => [],
            '意外发现' => []
        ];

        // Group books by category
        foreach ($allScoredBooks as $book) {
            $category = $book['category'];
            if (isset($categorizedBooks[$category])) {
                $categorizedBooks[$category][] = $book;
            }
        }

        // Select books according to distribution
        $finalBooks = [];
        $categoryInfo = [];

        foreach ($distribution as $category => $targetCount) {
            if ($targetCount > 0 && !empty($categorizedBooks[$category])) {
                $selected = array_slice($categorizedBooks[$category], 0, $targetCount);
                $finalBooks = array_merge($finalBooks, $selected);
                $categoryInfo[$category] = count($selected);
            }
        }

        // Fill remaining slots with best available books
        $remaining = $count - count($finalBooks);
        if ($remaining > 0) {
            $usedIds = array_column($finalBooks, 'book');
            $usedIds = array_column($usedIds, 'id');

            foreach ($allScoredBooks as $book) {
                if ($remaining <= 0) break;
                if (!in_array($book['book']['id'], $usedIds)) {
                    $finalBooks[] = $book;
                    $remaining--;
                }
            }
        }

        // Sort final results by total score
        usort($finalBooks, function ($a, $b) {
            return $b['total_score'] <=> $a['total_score'];
        });

        return [
            'books' => array_slice($finalBooks, 0, $count),
            'categories' => $categoryInfo,
            'discovery_info' => [
                'distribution_strategy' => $distribution,
                'total_candidates' => count($allScoredBooks),
                'discovery_enabled' => true
            ],
            'primary_factors' => $primaryFactors
        ];
    }

    private function calculateDistribution($count)
    {
        // Discovery distribution strategy based on count
        if ($count <= 3) {
            return [
                '相似推荐' => max(1, intval($count * 0.7)),
                '探索作者' => max(0, intval($count * 0.3)),
                '文化桥梁' => 0,
                '发现类型' => 0,
                '意外发现' => 0
            ];
        } elseif ($count <= 5) {
            return [
                '相似推荐' => max(2, intval($count * 0.6)),
                '探索作者' => max(1, intval($count * 0.25)),
                '文化桥梁' => max(0, intval($count * 0.1)),
                '发现类型' => max(0, intval($count * 0.05)),
                '意外发现' => 0
            ];
        } else {
            return [
                '相似推荐' => max(3, intval($count * 0.5)),
                '探索作者' => max(1, intval($count * 0.25)),
                '文化桥梁' => max(1, intval($count * 0.15)),
                '发现类型' => max(1, intval($count * 0.08)),
                '意外发现' => max(0, intval($count * 0.02))
            ];
        }
    }

    private function calculateSimilarityScore($sourceBook, $candidateBook)
    {
        $score = 0;
        $reasons = [];
        $factors = [];

        // Get tags for both books
        $sourceTags = $this->getBookTags($sourceBook['id']);
        $candidateTags = $this->getBookTags($candidateBook['id']);

        // 1. Tag-based similarity (40% weight)
        $tagSimilarity = $this->calculateTagSimilarity($sourceTags, $candidateTags);
        if ($tagSimilarity > 0) {
            $score += $tagSimilarity * 0.4;
            $factors[] = 'tags';

            $commonTags = array_intersect($sourceTags, $candidateTags);
            if (!empty($commonTags)) {
                $reasons[] = "共同标签: " . implode(', ', array_slice($commonTags, 0, 3));
            }
        }

        // 2. Author relationship (25% weight)
        $authorScore = 0;
        if (
            $sourceBook['author'] === $candidateBook['author'] &&
            $sourceBook['author'] !== '佚名' && $sourceBook['author'] !== 'anonymous'
        ) {
            $authorScore = 1.0;
            $reasons[] = "同一作者: " . $sourceBook['author'];
            $factors[] = 'author';
        } elseif ($sourceBook['region'] === $candidateBook['region'] && $sourceBook['region'] !== '中国') {
            $authorScore = 0.3;
            $reasons[] = "同一地区: " . $sourceBook['region'];
            $factors[] = 'region';
        }

        if ($sourceBook['copyrighter'] && $sourceBook['copyrighter'] === $candidateBook['copyrighter']) {
            $authorScore += 0.2;
            $reasons[] = "同一译者: " . $sourceBook['copyrighter'];
            $factors[] = 'translator';
        }

        $score += min($authorScore, 1.0) * 0.25;

        // 3. Category similarity (20% weight)
        if ($sourceBook['category'] && $candidateBook['category']) {
            $categoryScore = $this->calculateCategorySimilarity($sourceBook['category'], $candidateBook['category']);
            if ($categoryScore > 0) {
                $score += $categoryScore * 0.2;
                $factors[] = 'category';
                if ($categoryScore > 0.8) {
                    $reasons[] = "相同分类: " . trim($sourceBook['category']);
                }
            }
        }

        // 4. Behavioral similarity (15% weight) - simplified for prototype
        $behavioralScore = 0;

        // Remove location-based scoring - too personal/physical

        // Purchase date proximity (within 1 year)
        $sourcePurchase = new \DateTime($sourceBook['purchdate']);
        $candidatePurchase = new \DateTime($candidateBook['purchdate']);
        $daysDiff = abs($sourcePurchase->diff($candidatePurchase)->days);

        if ($daysDiff <= 365) {
            $proximityScore = 1 - ($daysDiff / 365);
            $behavioralScore += $proximityScore * 0.4;
            if ($daysDiff <= 30) {
                $reasons[] = "相近购买时间";
                $factors[] = 'purchase_time';
            }
        }

        $score += min($behavioralScore, 1.0) * 0.15;

        return [
            'total_score' => min($score, 1.0),
            'reasons' => $reasons,
            'factors' => array_unique($factors)
        ];
    }

    private function calculateTagSimilarity($tags1, $tags2)
    {
        if (empty($tags1) || empty($tags2)) {
            return 0;
        }

        $intersection = array_intersect($tags1, $tags2);
        $union = array_unique(array_merge($tags1, $tags2));

        // Jaccard similarity coefficient
        return count($intersection) / count($union);
    }

    private function calculateCategorySimilarity($cat1, $cat2)
    {
        $cat1 = trim($cat1);
        $cat2 = trim($cat2);

        if ($cat1 === $cat2) {
            return 1.0;
        }

        // Extract main category (before the dot)
        $main1 = explode('.', $cat1)[0];
        $main2 = explode('.', $cat2)[0];

        if ($main1 === $main2) {
            return 0.6;
        }

        return 0;
    }

    public function getMostPopularBooks($count = 1, $forceRefresh = false)
    {
        $cacheKey = "most_popular_books_{$count}";

        // Get cached data
        $booksData = null;
        if (!$forceRefresh) {
            $booksData = $this->cache->get($cacheKey);
        }

        $fromCache = ($booksData !== null);

        // If not cached, fetch from database using unified system
        if ($booksData === null) {
            $queryBuilder = new BookQueryBuilder();
            $books = $queryBuilder
                ->includeFields(['purchase', 'visit_stats'])
                ->mostPopular($count)
                ->execute();

            // Convert to array format for caching
            $booksData = array_map(function ($book) {
                return $book->toArray();
            }, $books);

            // Cache the result for 1 hour (popular books don't change frequently)
            $popularCacheTtl = 3600; // 1 hour
            $this->cache->set($cacheKey, $booksData, $popularCacheTtl);
        }

        return [
            'data' => $booksData,
            'from_cache' => $fromCache
        ];
    }
}
