<?php

namespace App\Models;

use App\Database\Connection;
use PDO;
use App\Exceptions\NotFoundException;

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
        // Random row by offset (avoids ORDER BY RAND()).
        $count = (int) $this->db->query("SELECT COUNT(*) AS c FROM qotd")->fetch()['c'];
        if ($count === 0) {
            throw new NotFoundException('No quote available');
        }

        $stmt = $this->db->prepare("SELECT id, quote, source FROM qotd LIMIT 1 OFFSET ?");
        $stmt->bindValue(1, random_int(0, $count - 1), PDO::PARAM_INT);
        $stmt->execute();
        $quote = $stmt->fetch();

        if ($quote === false) {
            throw new NotFoundException('No quote available');
        }

        return [
            'id' => (int)$quote['id'],
            'quote' => $quote['quote'],
            'source' => $quote['source']
        ];
    }
}