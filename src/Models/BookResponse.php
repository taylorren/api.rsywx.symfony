<?php

namespace App\Models;

class BookResponse
{
    // Core fields (always present)
    public $id;
    public $bookid;
    public $title;
    public $author;
    public $cover_uri;
    public $translated;
    public $copyrighter;
    public $region;
    public $location;

    // Purchase information (optional)
    public $purchdate = null;
    public $price = null;
    public $place_name = null;
    public $publisher_name = null;

    // Publication information (optional)
    public $pubdate = null;
    public $printdate = null;
    public $ver = null;
    public $deco = null;
    public $isbn = null;
    public $category = null;
    public $ol = null;

    // Book details (optional)
    public $kword = null;
    public $page = null;
    public $intro = null;
    public $instock = null;

    // Visit information (optional)
    public $total_visits = null;
    public $last_visited = null;
    public $visit_country = null;

    // Computed fields (optional)
    public $days_since_visit = null;
    public $years_ago = null;

    // Rich content (optional)
    public $tags = null;
    public $reviews = null;

    private $setFields = [];

    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
                $this->setFields[] = $key;
            }
        }

        // Always ensure cover_uri is set
        if ($this->bookid && !$this->cover_uri) {
            $this->cover_uri = "https://api.rsywx.com/covers/{$this->bookid}.jpg";
            $this->setFields[] = 'cover_uri';
        }
        
        // Ensure core fields are always in setFields
        $coreFields = ['id', 'bookid', 'title', 'author', 'cover_uri', 'translated', 'copyrighter', 'region', 'location'];
        foreach ($coreFields as $field) {
            if (!in_array($field, $this->setFields)) {
                $this->setFields[] = $field;
            }
        }
    }

    /**
     * Convert to array, excluding null values for cleaner JSON
     */
    public function toArray($includeNulls = false)
    {
        $result = [];
        $reflection = new \ReflectionClass($this);

        // Core fields that should always be included, even if null
        $coreFields = ['id', 'bookid', 'title', 'author', 'cover_uri', 'translated', 'copyrighter', 'region', 'location'];
        
        // Visit fields that should be included if they were set (even if null)
        $visitFields = ['visit_country', 'last_visited'];

        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            $propertyName = $property->getName();
            $value = $property->getValue($this);

            // Always include core fields, even if null
            // Include fields that were explicitly set, even if null
            if (in_array($propertyName, $coreFields) || 
                in_array($propertyName, $this->setFields) ||
                $includeNulls || 
                $value !== null) {
                $result[$propertyName] = $value;
            }
        }

        return $result;
    }

    /**
     * Create from database row with field mapping
     */
    public static function fromDatabaseRow(array $row, array $fieldMappings = [])
    {
        $data = [];

        // Apply field mappings (e.g., 'publisher_name' => 'name' from JOIN)
        foreach ($fieldMappings as $responseField => $dbField) {
            if (isset($row[$dbField])) {
                $data[$responseField] = $row[$dbField];
            }
        }

        // Direct field mappings
        $directFields = [
            'id',
            'bookid',
            'title',
            'author',
            'purchdate',
            'price',
            'translated',
            'copyrighter',
            'region',
            'location',
            'pubdate',
            'printdate',
            'ver',
            'deco',
            'isbn',
            'category',
            'ol',
            'kword',
            'page',
            'intro',
            'instock',
            'place_name',
            'publisher_name',
            'total_visits',
            'last_visited',
            'visit_country',
            'days_since_visit',
            'years_ago',
            'tags',
            'reviews'
        ];

        foreach ($directFields as $field) {
            if (array_key_exists($field, $row)) {
                $data[$field] = $row[$field];
            }
        }


        return new self($data);
    }
}
