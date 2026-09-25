<?php

namespace Tests\Unit;

use App\WordPress\SimpleWordPressDB;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the excerpt fallback used by the /blogs/latest endpoint.
 * buildExcerpt() is static and DB-free, so these run without a database.
 */
final class SimpleWordPressDBExcerptTest extends TestCase
{
    public function testStoredExcerptWinsWhenPresent(): void
    {
        $excerpt = SimpleWordPressDB::buildExcerpt('  A hand-written excerpt.  ', '<p>Full content here</p>');

        $this->assertSame('A hand-written excerpt.', $excerpt);
    }

    public function testFallsBackToStrippedContentWhenExcerptEmpty(): void
    {
        $excerpt = SimpleWordPressDB::buildExcerpt('', "<p>Hello <strong>world</strong>.</p>");

        $this->assertSame('Hello world.', $excerpt);
    }

    public function testFallsBackWhenExcerptIsNull(): void
    {
        $excerpt = SimpleWordPressDB::buildExcerpt(null, '<p>Only content</p>');

        $this->assertSame('Only content', $excerpt);
    }

    public function testTruncatesLongContentTo150CharsWithEllipsis(): void
    {
        $content = '<p>' . str_repeat('a', 200) . '</p>';

        $excerpt = SimpleWordPressDB::buildExcerpt('', $content);

        $this->assertSame(153, mb_strlen($excerpt)); // 150 chars + '...'
        $this->assertStringEndsWith('...', $excerpt);
        $this->assertStringNotContainsString('<', $excerpt);
    }

    public function testDoesNotTruncateAtExactlyMaxLength(): void
    {
        $content = str_repeat('b', 150);

        $excerpt = SimpleWordPressDB::buildExcerpt('', $content);

        $this->assertSame($content, $excerpt);
        $this->assertStringEndsNotWith('...', $excerpt);
    }

    public function testCollapsesWhitespaceIncludingNonBreakingSpaces(): void
    {
        $excerpt = SimpleWordPressDB::buildExcerpt('', "Line one\n\n  line\ttwo&nbsp;three");

        $this->assertSame('Line one line two three', $excerpt);
    }

    public function testReturnsEmptyStringWhenBothExcerptAndContentEmpty(): void
    {
        $this->assertSame('', SimpleWordPressDB::buildExcerpt('', ''));
        $this->assertSame('', SimpleWordPressDB::buildExcerpt(null, null));
    }

    public function testHtmlEntitiesAreDecoded(): void
    {
        $excerpt = SimpleWordPressDB::buildExcerpt('', '<p>Tom &amp; Jerry &lt;3</p>');

        $this->assertSame('Tom & Jerry <3', $excerpt);
    }

    public function testChineseContentTruncationIsMultibyteSafe(): void
    {
        $content = str_repeat('汉', 200);

        $excerpt = SimpleWordPressDB::buildExcerpt('', $content);

        $this->assertSame(153, mb_strlen($excerpt));
        $this->assertSame(150, mb_strlen(rtrim($excerpt, '.')));
    }
}