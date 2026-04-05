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
        
        $this->assertStringContainsString('title: "TEST POEM"', $output);
        $this->assertStringContainsString("# TEST POEM\n", $output);
    }

    public function testParseTitleFromStrongTag(): void
    {
        $html = '<html><head><title>Generic Title</title></head><body>
            <p align="center"><strong>Real Title</strong></p>
        </body></html>';
        $output = $this->parser->parse($html);
        
        $this->assertStringContainsString('title: "REAL TITLE"', $output);
    }

    public function testCleanTitlePrefixAndPeriod(): void
    {
        $html = '<html><head><title>Poesias Antonio Pardal - My Poem.</title></head><body></body></html>';
        $output = $this->parser->parse($html);
        
        $this->assertStringContainsString('title: "MY POEM"', $output);
    }

    public function testFrontMatterDoesNotContainDateIfMissing(): void
    {
        $html = "<html><head><title>Title</title></head><body></body></html>";
        $output = $this->parser->parse($html);
        
        $this->assertStringNotContainsString('date:', $output);
    }

    public function testParseDateFromParagraph(): void
    {
        $html = '<html><body><p>Antonio Pardal Rivas 9-7-2012</p></body></html>';
        $output = $this->parser->parse($html);
        
        $this->assertStringContainsString('date: 2012-07-09', $output);
    }

    public function testParseDateWithTwoDigitYear(): void
    {
        $html = '<html><body><p>17-12-07</p></body></html>';
        $output = $this->parser->parse($html);
        
        $this->assertStringContainsString('date: 2007-12-17', $output);
    }

    public function testParseTitleFromEstilo1Class(): void
    {
        $html = '<html><body><p align="center" class="Estilo1"><font color="#FFFFCC">Flor de loto</font></p></body></html>';
        $output = $this->parser->parse($html);
        
        $this->assertStringContainsString('title: "FLOR DE LOTO"', $output);
    }

    public function testParseTitleNormalizesWhitespace(): void
    {
        $html = '<html><body><p align="center" class="Estilo1"><font color="#FFFFCC">Canci&oacute;n 
          de la Tierra</font></p></body></html>';
        $output = $this->parser->parse($html);
        
        $this->assertStringContainsString('title: "CANCIÓN DE LA TIERRA"', $output);
    }

    public function testFrontMatterContainsSlug(): void
    {
        $html = '<html><head><title>Canci&oacute;n de la Tierra</title></head><body></body></html>';
        $output = $this->parser->parse($html);
        
        $this->assertStringContainsString('slug: cancion-de-la-tierra', $output);
    }

    public function testFrontMatterContainsIdAndSlugWithId(): void
    {
        $html = '<html><head><title>Test Poem</title></head><body></body></html>';
        $output = $this->parser->parse($html, 'Antonio312.htm');
        
        $this->assertStringContainsString('id: 312', $output);
        $this->assertStringContainsString('slug: 312-test-poem', $output);
    }

    public function testIdPaddedWithZeros(): void
    {
        $html = '<html><head><title>Test Poem</title></head><body></body></html>';
        $output = $this->parser->parse($html, 'Antonio8.htm');
        
        $this->assertStringContainsString('id: 008', $output);
        $this->assertStringContainsString('slug: 008-test-poem', $output);
    }

    public function testVersesSeparatedByBrNoBlankLines(): void
    {
        $html = '<html><head><title>Poem</title></head><body>
            <div align="left">
                <p align="center"><strong>
                    Verse 1 of the first stanza long enough<br>
                    Verse 2 of the first stanza long enough<br>
                    <br>
                    Verse 3 of the second stanza long enough
                </strong></p>
            </div>
        </body></html>';
        
        $output = $this->parser->parse($html);
        
        // Verses should be separated by \n, and if there is a double <br>, they should be in different stanzas (double \n)
        $this->assertStringContainsString("Verse 1 of the first stanza long enough\nVerse 2 of the first stanza long enough\n\nVerse 3 of the second stanza long enough", $output);
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

    public function testParseWithExplicitTitle(): void
    {
        $html = '<html><head><title>Original Title</title></head><body></body></html>';
        $output = $this->parser->parse($html, 'Antonio1.htm', 'Title from Index');
        
        $this->assertStringContainsString('title: "TITLE FROM INDEX"', $output);
        $this->assertStringContainsString('slug: 001-title-from-index', $output);
        $this->assertStringNotContainsString('ORIGINAL TITLE', $output);
        $this->assertStringContainsString("# TITLE FROM INDEX\n", $output);
    }

    public function testParseWithCategory(): void
    {
        $html = '<html><head><title>Test Poem</title></head><body></body></html>';
        $output = $this->parser->parse($html, null, null, 'Poesías de Amor (A Victoria)');
        
        $this->assertStringContainsString('category: poesias-de-amor-a-victoria', $output);
    }

    public function testParseTitleFromAntonio12Pattern(): void
    {
        $html = '<html><head><title>Poesias Antonio Pardal.</title></head><body>
            <div align="center">
                <p><strong><font color="#D9BD8E" size="6" face="Arial, Helvetica, sans-serif">MAGDALENA</font></strong></p>
                <p>&nbsp;</p>
            </div>
        </body></html>';
        $output = $this->parser->parse($html);
        
        $this->assertStringContainsString('title: "MAGDALENA"', $output);
    }

    public function testParseTextWithUppercaseTagsAndB(): void
    {
        $html = '<html><head><title>LA LLAGA</title></head><body>
            <P align="center" class="EnlaceAnfy  Estilo69"><B>P&uacute;stula purulenta que me acosas <BR>
            las entra&ntilde;as. &iquest;Por qu&eacute; me vas matando <BR>
            poco a poco? &iquest;Por qu&eacute; vas destrozando <BR>
            mis campos, mis espigas y mis rosas? </B></P>
        </body></html>';

        $output = $this->parser->parse($html);
        
        $this->assertStringContainsString("Pústula purulenta que me acosas\nlas entrañas. ¿Por qué me vas matando\npoco a poco? ¿Por qué vas destrozando\nmis campos, mis espigas y mis rosas?", $output);
    }

    public function testStanzasInSameParagraphSeparatedByDoubleBr(): void
    {
        $html = '<html><head><title>Test Poem</title></head><body>
            <p align="center"><strong>
                Line 1 of the first stanza long enough<br>
                Line 2 of the first stanza long enough<br>
                <br>
                Line 3 of the second stanza long enough<br>
                Line 4 of the second stanza long enough
            </strong></p>
        </body></html>';

        $output = $this->parser->parse($html);
        
        // Debe contener un salto de línea doble entre las estrofas
        $this->assertStringContainsString("Line 1 of the first stanza long enough\nLine 2 of the first stanza long enough\n\nLine 3 of the second stanza long enough\nLine 4 of the second stanza long enough", $output);
    }

    public function testShouldNotFilterShortLastStanza(): void
    {
        $html = '<html><head><title>Test Poem</title></head><body>
            <p align="center"><strong>
                Line 1 of the first stanza long enough<br>
                Line 2 of the first stanza long enough<br>
                <br>
                This stanza is short
            </strong></p>
        </body></html>';

        $output = $this->parser->parse($html);
        
        // "This stanza is short" has 20 characters, which is < 30.
        // It should be present in the output.
        $this->assertStringContainsString("This stanza is short", $output);
    }
}
