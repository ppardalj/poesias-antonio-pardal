<?php

namespace Poesias\Tests;

use PHPUnit\Framework\TestCase;
use Poesias\PoemParser;

class PoemParserTest extends TestCase
{
    private PoemParser $parser;

    protected function setUp(): void
    {
        $this->parser = new PoemParser();
    }

    public function testParseTitleFromTitleTag(): void
    {
        $html = "<html><head><title>Test Poem</title></head><body></body></html>";
        $output = $this->parser->parse($html);
        
        $this->assertStringContainsString('title: "Test Poem"', $output);
    }

    public function testParseTitleFromStrongTag(): void
    {
        $html = '<html><head><title>Generic Title</title></head><body>
            <p align="center"><strong>Real Title</strong></p>
        </body></html>';
        $output = $this->parser->parse($html);
        
        $this->assertStringContainsString('title: "Real Title"', $output);
    }

    public function testCleanTitlePrefixAndPeriod(): void
    {
        $html = '<html><head><title>Poesias Antonio Pardal - My Poem.</title></head><body></body></html>';
        $output = $this->parser->parse($html);
        
        $this->assertStringContainsString('title: "My Poem"', $output);
    }

    public function testFrontMatterContainsIsoDate(): void
    {
        $html = "<html><head><title>Title</title></head><body></body></html>";
        $output = $this->parser->parse($html);
        
        // Regex for ISO 8601 date (simplified)
        $this->assertMatchesRegularExpression('/date: \d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $output);
    }

    public function testVersesSeparatedByBrNoBlankLines(): void
    {
        $html = '<html><head><title>Poem</title></head><body>
            <div align="left">
                <p align="center"><strong>
                    Verse 1<br>
                    Verse 2<br>
                    <br>
                    Verse 3 extra text for length
                </strong></p>
            </div>
        </body></html>';
        
        $output = $this->parser->parse($html);
        
        // Verses should be separated by \n, no double \n inside stanza
        $this->assertStringContainsString("Verse 1\nVerse 2\nVerse 3 extra text for length", $output);
    }

    public function testStanzasSeparatedByDoubleNewline(): void
    {
        $html = '<html><head><title>Poem</title></head><body>
            <p align="center"><strong>Stanza 1 Line 1<br>Stanza 1 Line 2<br>Stanza 1 Line 3<br>Stanza 1 Line 4 length</strong></p>
            <p align="center"><strong>Stanza 2 Line 1<br>Stanza 2 Line 2<br>Stanza 2 Line 3<br>Stanza 2 Line 4 length</strong></p>
        </body></html>';
        
        $output = $this->parser->parse($html);
        
        // Note: each stanza must be long enough (>30 chars) to not be filtered out
        $this->assertStringContainsString("Stanza 1 Line 1\nStanza 1 Line 2\nStanza 1 Line 3\nStanza 1 Line 4 length\n\nStanza 2 Line 1\nStanza 2 Line 2\nStanza 2 Line 3\nStanza 2 Line 4 length", $output);
    }

    public function testHtmlNewlinesAreIgnored(): void
    {
        $html = '<html><head><title>Poem</title></head><body>
            <p align="center"><strong>
                Verse 
                Part 1
                <br>
                Verse Part 2 extra text for length
            </strong></p>
        </body></html>';
        
        $output = $this->parser->parse($html);
        
        // "Verse Part 1" should be joined because there's no <br> between them
        $this->assertStringContainsString("Verse Part 1\nVerse Part 2 extra text for length", $output);
    }
}
