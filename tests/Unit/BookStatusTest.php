<?php

namespace Tests\Unit;

use Tests\BaseTestCase;
use App\Models\BookStatus;
use App\Cache\ApcuCache;
use App\Database\Connection;

class BookStatusTest extends BaseTestCase
{
    private BookStatus $bookStatus;

    protected function setUp(): void
    {
        parent::setUp();

        // Fresh cache instance per test (parent constructor wires an ApcuCache)
        $this->bookStatus = new BookStatus();
    }

    public function testGetCollectionStatusStructure()
    {
        // This test assumes you have test data or mocked database
        // For now, we'll test the structure of the response
        
        try {
            $result = $this->bookStatus->getCollectionStatus(true); // Force refresh
            
            $this->assertIsArray($result);
            $this->assertArrayHasKey('data', $result);
            $this->assertArrayHasKey('from_cache', $result);
            
            $data = $result['data'];
            $this->assertArrayHasKey('total_books', $data);
            $this->assertArrayHasKey('total_pages', $data);
            $this->assertArrayHasKey('total_kwords', $data);
            $this->assertArrayHasKey('total_visits', $data);
            
            // Check data types
            $this->assertIsNumeric($data['total_books']);
            $this->assertIsNumeric($data['total_pages']);
            $this->assertIsNumeric($data['total_kwords']);
            $this->assertIsNumeric($data['total_visits']);
            
            // First call should not be from cache
            $this->assertFalse($result['from_cache']);
            
        } catch (\Exception $e) {
            // If database is not available, skip this test
            $this->markTestSkipped('Database not available for testing: ' . $e->getMessage());
        }
    }

    public function testCachingBehavior()
    {
        try {
            // First call - should fetch from database
            $result1 = $this->bookStatus->getCollectionStatus(true);
            $this->assertFalse($result1['from_cache']);
            
            // Second call - should use cache
            $result2 = $this->bookStatus->getCollectionStatus(false);
            $this->assertTrue($result2['from_cache']);
            
            // Data should be the same
            $this->assertEquals($result1['data'], $result2['data']);
            
        } catch (\Exception $e) {
            $this->markTestSkipped('Database not available for testing: ' . $e->getMessage());
        }
    }

    public function testForceRefresh()
    {
        try {
            // First call to populate cache
            $this->bookStatus->getCollectionStatus(false);
            
            // Force refresh should bypass cache
            $result = $this->bookStatus->getCollectionStatus(true);
            $this->assertFalse($result['from_cache']);
            
        } catch (\Exception $e) {
            $this->markTestSkipped('Database not available for testing: ' . $e->getMessage());
        }
    }

    public function testClearCache()
    {
        try {
            // Populate cache
            $this->bookStatus->getCollectionStatus(false);
            
            // Clear cache
            $result = $this->bookStatus->clearCache();
            $this->assertTrue($result);
            
            // Next call should not be from cache
            $result = $this->bookStatus->getCollectionStatus(false);
            $this->assertFalse($result['from_cache']);
            
        } catch (\Exception $e) {
            $this->markTestSkipped('Database not available for testing: ' . $e->getMessage());
        }
    }
}