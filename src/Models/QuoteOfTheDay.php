<?php

namespace App\Models;

use App\Database\Connection;
use PDO;

class QuoteOfTheDay
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Connection::getInstance()->getConnection();
    }

    public function getQuoteOfTheDay(): array
    {
        // Always fetch a fresh random quote - no caching needed for random data
        $quoteData = $this->fetchQuoteOfTheDayFromDb();

        return [
            'data' => $quoteData,
            'from_cache' => false // Never cached since it's always random
        ];
    }

    private function fetchQuoteOfTheDayFromDb(): array
    {
        // Get a truly random quote from the qotd table
        $query = "SELECT id, quote, source FROM qotd ORDER BY RAND() LIMIT 1";

        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $quote = $stmt->fetch();

        return [
            'id' => (int)$quote['id'],
            'quote' => $quote['quote'],
            'source' => $quote['source']
        ];
    }
}