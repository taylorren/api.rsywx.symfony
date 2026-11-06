<?php

namespace App\Models;

use App\Database\Connection;
use PDO;
use App\Cache\ApcuCache;
use App\Exceptions\NotFoundException;

class WordOfTheDay
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Connection::getInstance()->getConnection();
    }

    public function getWordOfTheDay(): array
    {
        // Always fetch a fresh random word - no caching needed for random data
        $wordData = $this->fetchWordOfTheDayFromDb();

        return [
            'data' => $wordData,
            'from_cache' => false // Never cached since it's always random
        ];
    }

    private function fetchWordOfTheDayFromDb(): array
    {
        // Random row by offset (avoids ORDER BY RAND()).
        $count = (int) $this->db->query("SELECT COUNT(*) AS c FROM wotd")->fetch()['c'];
        if ($count === 0) {
            throw new NotFoundException('No word available');
        }

        $stmt = $this->db->prepare("SELECT id, word, meaning, sentence, type FROM wotd LIMIT 1 OFFSET ?");
        $stmt->bindValue(1, random_int(0, $count - 1), PDO::PARAM_INT);
        $stmt->execute();
        $word = $stmt->fetch();

        if ($word === false) {
            throw new NotFoundException('No word available');
        }

        return [
            'id' => (int)$word['id'],
            'word' => $word['word'],
            'meaning' => $word['meaning'],
            'sentence' => $word['sentence'],
            'type' => $word['type']
        ];
    }
}
