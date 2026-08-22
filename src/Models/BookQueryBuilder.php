<?php

namespace App\Models;

use App\Database\Connection;

class BookQueryBuilder
{
    private $db;
    private $baseQuery;
    private $joins = [];
    private $fields = [];
    private $conditions = [];
    private $orderBy = [];
    private $limit = null;
    private $offset = null;
    
    public function __construct()
    {
        $this->db = Connection::getInstance()->getConnection();
        $this->baseQuery = "FROM book_book b";
        $this->addField('b.id', 'id');
        $this->addField('b.bookid', 'bookid');
        $this->addField('b.title', 'title');
        $this->addField('b.author', 'author');
        $this->addField('b.translated', 'translated');
        $this->addField('b.copyrighter', 'copyrighter');
        $this->addField('b.region', 'region');
        $this->addField('b.location', 'location');
    }
    
    public function includeFields(array $fieldGroups)
    {
        foreach ($fieldGroups as $group) {
            switch ($group) {
                case 'purchase':
                    $this->includePurchaseFields();
                    break;
                case 'visits':
                    $this->includeVisitFields();
                    break;
                case 'visit_stats':
                    $this->includeVisitStats();
                    break;
                case 'computed':
                    $this->includeComputedFields();
                    break;
                case 'publication':
                    $this->includePublicationFields();
                    break;
                case 'rich':
                    $this->includeRichFields();
                    break;
            }
        }
        return $this;
    }
    
    private function includePurchaseFields()
    {
        $this->addField('b.purchdate', 'purchdate');
        $this->addField('b.price', 'price');
        $this->addField('p.name', 'place_name');
        $this->addField('pub.name', 'publisher_name');
        
        $this->addJoin('LEFT JOIN book_place p ON b.place = p.id');
        $this->addJoin('LEFT JOIN book_publisher pub ON b.publisher = pub.id');
        
        return $this;
    }
    
    private function includeVisitFields()
    {
        // For last visited books - get the most recent visit info
        $this->addField('recent_visits.visitwhen', 'last_visited');
        $this->addField('recent_visits.region', 'region');
        
        return $this;
    }
    
    private function includeVisitStats()
    {
        // total_visits and last_visit are denormalized onto book_book
        // (b.total_visits / b.last_visit, both bumped by the book_visit INSERT
        // trigger), so the COUNT(*)/MAX() GROUP BY over book_visit is no longer
        // needed.
        $this->addField('COALESCE(b.total_visits, 0)', 'total_visits');
        $this->addField('b.last_visit', 'last_visited');
        
        return $this;
    }
    
    private function includeComputedFields()
    {
        // Ensure visit_stats is included for computed fields
        $this->includeVisitStats();
        
        // Add computed fields like days_since_visit, years_ago
        $this->addField('DATEDIFF(NOW(), b.last_visit)', 'days_since_visit');
        
        return $this;
    }
    
    private function includePublicationFields()
    {
        $this->addField('p.name', 'place_name');
        $this->addField('pub.name', 'publisher_name');
        
        $this->addJoin('LEFT JOIN book_place p ON b.place = p.id');
        $this->addJoin('LEFT JOIN book_publisher pub ON b.publisher = pub.id');
        
        return $this;
    }
    
    private function includeRichFields()
    {
        // For detailed book view - tags and reviews would be loaded separately
        // to avoid complex JOINs that could cause performance issues
        return $this;
    }
    
    public function latest($count = 1)
    {
        $this->addOrderBy('b.purchdate DESC, b.id DESC');
        $this->limit($count);
        return $this;
    }
    
    public function random($count = 1)
    {
        $this->addOrderBy('RAND()');
        $this->limit($count);
        return $this;
    }
    
    public function lastVisited($count = 1)
    {
        // Use subquery for most recent visits
        $this->addJoin('INNER JOIN (
            SELECT v.bookid, v.visitwhen, v.country
            FROM book_visit v 
            ORDER BY v.visitwhen DESC 
            LIMIT ' . (int)$count . '
        ) recent_visits ON b.id = recent_visits.bookid');
        
        // total_visits is denormalized onto book_book (b.total_visits, bumped
        // by the book_visit INSERT trigger alongside last_visit).
        $this->addField('recent_visits.visitwhen', 'last_visited');
        $this->addField('recent_visits.country', 'visit_country');
        $this->addField('COALESCE(b.total_visits, 0)', 'total_visits');
        
        $this->addOrderBy('recent_visits.visitwhen DESC');
        return $this;
    }
    
    public function forgotten($count = 1)
    {
        // last_visit and total_visits are denormalized onto book_book
        // (b.last_visit / b.total_visits, both bumped by the book_visit INSERT
        // trigger), so this is a single lightweight query with no book_visit join.
        $this->addField('COALESCE(b.total_visits, 0)', 'total_visits');
        $this->addField('b.last_visit', 'last_visited');
        $this->addField('DATEDIFF(NOW(), b.last_visit)', 'days_since_visit');

        $this->addOrderBy('b.last_visit ASC');
        $this->limit($count);
        return $this;
    }
    
    public function todaysBooks($month, $date)
    {
        $currentYear = date('Y');
        $monthDay = sprintf('%02d-%02d', $month, $date);
        
        $this->addCondition("DATE_FORMAT(b.purchdate, '%m-%d') = ?", $monthDay);
        $this->addCondition("YEAR(b.purchdate) < ?", $currentYear);
        $this->addField("({$currentYear} - YEAR(b.purchdate))", 'years_ago');
        $this->addOrderBy('b.purchdate DESC');
        
        return $this;
    }
    
    public function byId($bookId)
    {
        $this->addCondition('b.id = ?', $bookId);
        $this->limit(1);
        return $this;
    }
    
    public function byBookId($bookId)
    {
        $this->addCondition('b.bookid = ?', $bookId);
        $this->limit(1);
        return $this;
    }
    
    public function mostPopular($count = 1)
    {
        // Ensure total_visits is exposed for ordering
        $this->includeVisitStats();
        
        // Order by the denormalized column (descending), then book ID descending for consistency
        $this->addOrderBy('b.total_visits DESC, b.id DESC');
        $this->limit($count);
        return $this;
    }
    
    public function leastPopular($count = 1)
    {
        // Ensure total_visits is exposed for ordering
        $this->includeVisitStats();
        
        // Order by the denormalized column (ascending), then book ID descending for consistency
        $this->addOrderBy('b.total_visits ASC, b.id DESC');
        $this->limit($count);
        return $this;
    }
    
    private function addField($field, $alias = null)
    {
        if ($alias) {
            $this->fields[] = "{$field} as {$alias}";
        } else {
            $this->fields[] = $field;
        }
    }
    
    private function addJoin($join)
    {
        if (!in_array($join, $this->joins)) {
            $this->joins[] = $join;
        }
    }
    
    private function addCondition($condition, $value = null)
    {
        $this->conditions[] = ['condition' => $condition, 'value' => $value];
    }
    
    private function addOrderBy($orderBy)
    {
        $this->orderBy[] = $orderBy;
    }
    
    public function orderBy($orderBy)
    {
        $this->orderBy[] = $orderBy;
        return $this;
    }
    
    private function limit($limit)
    {
        $this->limit = (int)$limit;
    }
    
    public function execute()
    {
        // Always exclude invalid locations
        $this->addCondition("b.location NOT IN ('na', '--')");
        
        // Build the complete query
        $query = "SELECT " . implode(', ', $this->fields) . " ";
        $query .= $this->baseQuery . " ";
        $query .= implode(' ', $this->joins) . " ";
        
        if (!empty($this->conditions)) {
            $whereConditions = array_map(function($c) { return $c['condition']; }, $this->conditions);
            $query .= "WHERE " . implode(' AND ', $whereConditions) . " ";
        }
        
        if (!empty($this->orderBy)) {
            $query .= "ORDER BY " . implode(', ', $this->orderBy) . " ";
        }
        
        if ($this->limit) {
            $query .= "LIMIT ";
            if ($this->offset) {
                $query .= "{$this->offset}, ";
            }
            $query .= "{$this->limit}";
        }
        
        // Prepare and execute
        $stmt = $this->db->prepare($query);
        
        // Bind parameters
        $paramIndex = 1;
        foreach ($this->conditions as $condition) {
            if (isset($condition['values'])) {
                foreach ($condition['values'] as $value) {
                    $stmt->bindValue($paramIndex++, $value);
                }
            } elseif ($condition['value'] !== null) {
                $stmt->bindValue($paramIndex++, $condition['value']);
            }
        }
        
        $stmt->execute();
        $results = $stmt->fetchAll();
        

        
        // Convert to BookResponse objects
        $books = [];
        foreach ($results as $row) {
            $books[] = BookResponse::fromDatabaseRow($row);
        }
        
        return $books;
    }
    
    public function executeOne()
    {
        $this->limit(1);
        $results = $this->execute();
        return !empty($results) ? $results[0] : null;
    }
    
    public function searchByAuthor($author)
    {
        $this->addCondition("b.author LIKE ?", "%{$author}%");
        return $this;
    }
    
    public function searchByTitle($title)
    {
        $this->addCondition("b.title LIKE ?", "%{$title}%");
        return $this;
    }
    
    public function searchByTag($tag)
    {
        $this->addJoin('INNER JOIN book_taglist t ON b.id = t.bid');
        $this->addCondition("t.tag = ?", $tag);
        return $this;
    }
    
    public function searchMisc($value)
    {
        $this->conditions[] = [
            'condition' => "(b.title LIKE ? OR b.author LIKE ?)", 
            'values' => ["%{$value}%", "%{$value}%"]
        ];
        return $this;
    }
    
    public function paginate($page, $perPage)
    {
        $offset = ($page - 1) * $perPage;
        $this->limit = $perPage;
        $this->offset = $offset;
        return $this;
    }
    
    public function count()
    {
        // Build count query without LIMIT
        $query = "SELECT COUNT(DISTINCT b.id) as total ";
        $query .= $this->baseQuery . " ";
        $query .= implode(' ', $this->joins) . " ";
        
        // Filter out LIMIT conditions and location filter
        $countConditions = array_filter($this->conditions, function($c) {
            return !str_contains($c['condition'], 'LIMIT');
        });
        
        // Always exclude invalid locations for count
        $countConditions[] = ['condition' => "b.location NOT IN ('na', '--')", 'value' => null];
        
        if (!empty($countConditions)) {
            $whereConditions = array_map(function($c) { return $c['condition']; }, $countConditions);
            $query .= "WHERE " . implode(' AND ', $whereConditions) . " ";
        }
        
        $stmt = $this->db->prepare($query);
        
        $paramIndex = 1;
        foreach ($countConditions as $condition) {
            if (isset($condition['values'])) {
                foreach ($condition['values'] as $value) {
                    $stmt->bindValue($paramIndex++, $value);
                }
            } elseif ($condition['value'] !== null) {
                $stmt->bindValue($paramIndex++, $condition['value']);
            }
        }
        
        $stmt->execute();
        $result = $stmt->fetch();
        return (int)$result['total'];
    }
}