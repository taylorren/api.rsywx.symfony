<?php

namespace App\Models;

use App\Database\Connection;
use App\Cache\MemoryCache;

class WordOfTheDay
{
    private $db;

    public function __construct()
    {
        $this->db = Connection::getInstance()->getConnection();
    }

    public function getWordOfTheDay()
    {
        // Always fetch a fresh random word - no caching needed for random data
        $wordData = $this->fetchWordOfTheDayFromDb();

        return [
            'data' => $wordData,
            'from_cache' => false // Never cached since it's always random
        ];
    }

    private function fetchWordOfTheDayFromDb()
    {
        // Get a truly random word from the wotd table
        $query = "SELECT id, word, meaning, sentence, type FROM wotd ORDER BY RAND() LIMIT 1";

        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $word = $stmt->fetch();

        return [
            'id' => (int)$word['id'],
            'word' => $word['word'],
            'meaning' => $word['meaning'],
            'sentence' => $word['sentence'],
            'type' => $word['type']
        ];
    }
}
