<?php

use PHPUnit\Framework\TestCase;
use Poesias\IndexGenerator;

class IndexGeneratorTest extends TestCase
{
    private IndexGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new IndexGenerator();
    }

    public function testGenerateBasicIndex(): void
    {
        $html = '<html><body>
            <div class="Estilo11">SECCIÓN 1</div>
            <a href="Antonio1.htm">Poema 1</a>
            <div class="Estilo11">SECCIÓN 2</div>
            <a href="Antonio2.htm">Poema 2</a>
        </body></html>';

        $output = $this->generator->generate($html);

        $this->assertStringContainsString('## SECCIÓN 1', $output);
        $this->assertStringContainsString('- [Poema 1](poems/001-poema-1.md)', $output);
        $this->assertStringContainsString('## SECCIÓN 2', $output);
        $this->assertStringContainsString('- [Poema 2](poems/002-poema-2.md)', $output);
    }

    public function testGenerateIndexWithSubtitles(): void
    {
        $html = '<html><body>
            <table>
                <tr>
                    <td class="Estilo11">AMOR</td>
                </tr>
                <tr>
                    <td class="Estilo12">(A VICTORIA)</td>
                </tr>
            </table>
            <a href="Antonio1.htm">Poema Amor</a>
        </body></html>';

        $output = $this->generator->generate($html);
        
        $this->assertStringContainsString('## AMOR (A VICTORIA)', $output);
        $this->assertStringContainsString('- [Poema Amor](poems/001-poema-amor.md)', $output);
    }

    public function testSeparationOfSections(): void
    {
        $html = '<html><body>
            <div class="Estilo11">SEC1</div>
            <a href="Antonio1.htm">P1</a>
            <div class="Estilo11">SEC2</div>
            <a href="Antonio2.htm">P2</a>
        </body></html>';

        $output = $this->generator->generate($html);
        
        // Verificar que P2 no está bajo SEC1 y P1 no está bajo SEC2
        $parts = explode('##', $output);
        // $parts[0] es el título H1
        // $parts[1] es SEC1
        // $parts[2] es SEC2
        
        $this->assertStringContainsString('SEC1', $parts[1]);
        $this->assertStringContainsString('P1', $parts[1]);
        $this->assertStringNotContainsString('P2', $parts[1]);
        
        $this->assertStringContainsString('SEC2', $parts[2]);
        $this->assertStringContainsString('P2', $parts[2]);
        $this->assertStringNotContainsString('P1', $parts[2]);
    }
}
