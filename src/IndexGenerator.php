<?php

namespace Poesias;

use DOMDocument;
use DOMXPath;

class IndexGenerator
{
    /**
     * Genera el índice en formato Markdown a partir del HTML de index.htm.
     *
     * @param string $html Contenido HTML del índice.
     * @param array $extraPoems Lista opcional de poemas adicionales para añadir al final.
     * @return string Índice en formato Markdown.
     */
    public function generate(string $html, array $extraPoems = []): string
    {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($dom);

        $output = "# Índice de Poesías de Antonio Pardal\n";
        $processed = [];

        // Buscamos todas las secciones (Estilo11)
        $sections = $xpath->query('//*[contains(@class, "Estilo11")]');

        foreach ($sections as $index => $section) {
            $sectionTitle = trim($section->nodeValue);
            
            // Verificamos si hay un subtítulo en Estilo12 cerca
            $subtitle = '';
            $parent = $section->parentNode;
            while ($parent && $parent->nodeName !== 'tr' && $parent->nodeName !== 'body') {
                $parent = $parent->parentNode;
            }
            if ($parent && $parent->nodeName === 'tr') {
                $nextTr = $xpath->query('./following-sibling::tr[1]', $parent)->item(0);
                if ($nextTr) {
                    $subNode = $xpath->query('.//*[contains(@class, "Estilo12")]', $nextTr)->item(0);
                    if ($subNode) {
                        $subtitle = ' ' . trim($subNode->nodeValue);
                    }
                }
            }

            $fullSectionTitle = html_entity_decode($sectionTitle . $subtitle, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $fullSectionTitle = preg_replace('/\s+/', ' ', $fullSectionTitle);
            
            $output .= "\n## " . mb_strtoupper($fullSectionTitle) . "\n\n";

            // Para encontrar los enlaces de esta sección, buscamos todos los enlaces 'a' que contengan 'Antonio'
            // que se encuentren DESPUÉS de este nodo de sección y ANTES del siguiente nodo de sección (si existe).
            
            $links = $xpath->query('.//following::a[contains(@href, "Antonio")]', $section);
            
            foreach ($links as $link) {
                if ($index < $sections->length - 1) {
                    $nextSection = $sections->item($index + 1);
                    // Comprobamos si el link está después de nextSection.
                    $isAfterNext = $xpath->evaluate('count(following::*[contains(@class, "Estilo11")])', $link) < ($sections->length - 1 - $index);
                    
                    if ($isAfterNext) {
                        break;
                    }
                }
                
                $href = $link->getAttribute('href');
                $title = trim($link->nodeValue);
                
                if (empty($title)) continue;
                
                $title = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $title = preg_replace('/\s+/', ' ', $title);
                $title = trim($title);
                
                $formattedId = PoemParser::formatId($href);
                $slug = PoemParser::generateSlug($title, $formattedId);
                
                $poemParser = new PoemParser();
                $categorySlug = $poemParser->slugify($fullSectionTitle);
                $outputPath = "poems/$categorySlug/$slug.md";
                
                $key = $href . '|' . $title;
                if (isset($processed[$key])) continue;
                $processed[$key] = true;
                
                $output .= "- [$title]($outputPath)\n";
            }
        }

        if (!empty($extraPoems)) {
            $output .= "\n## SIN CATEGORIZAR\n\n";
            foreach ($extraPoems as $poem) {
                $href = $poem['href'];
                $title = $poem['title'];
                
                $formattedId = PoemParser::formatId($href);
                $slug = PoemParser::generateSlug($title, $formattedId);
                
                $poemParser = new PoemParser();
                $categorySlug = $poemParser->slugify($poem['category'] ?? 'sin-categorizar');
                $outputPath = "poems/$categorySlug/$slug.md";
                
                $output .= "- [$title]($outputPath)\n";
            }
        }

        return $output;
    }

    /**
     * Extrae todos los poemas únicos del HTML del índice con su categoría.
     *
     * @param string $html Contenido HTML del índice.
     * @return array Lista de poemas con href, título y categoría.
     */
    public function getPoems(string $html): array
    {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($dom);

        $poems = [];
        $sections = $xpath->query('//*[contains(@class, "Estilo11")]');

        foreach ($sections as $index => $section) {
            $sectionTitle = trim($section->nodeValue);
            
            // Verificamos si hay un subtítulo en Estilo12 cerca
            $subtitle = '';
            $parent = $section->parentNode;
            while ($parent && $parent->nodeName !== 'tr' && $parent->nodeName !== 'body') {
                $parent = $parent->parentNode;
            }
            if ($parent && $parent->nodeName === 'tr') {
                $nextTr = $xpath->query('./following-sibling::tr[1]', $parent)->item(0);
                if ($nextTr) {
                    $subNode = $xpath->query('.//*[contains(@class, "Estilo12")]', $nextTr)->item(0);
                    if ($subNode) {
                        $subtitle = ' ' . trim($subNode->nodeValue);
                    }
                }
            }

            $category = html_entity_decode($sectionTitle . $subtitle, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $category = preg_replace('/\s+/', ' ', $category);
            $category = trim($category);

            // Para encontrar los enlaces de esta sección
            $links = $xpath->query('.//following::a[contains(@href, "Antonio")]', $section);
            
            foreach ($links as $link) {
                if ($index < $sections->length - 1) {
                    $nextSection = $sections->item($index + 1);
                    // Comprobamos si el link está después de nextSection.
                    $isAfterNext = $xpath->evaluate('count(following::*[contains(@class, "Estilo11")])', $link) < ($sections->length - 1 - $index);
                    
                    if ($isAfterNext) {
                        break;
                    }
                }
                
                $href = $link->getAttribute('href');
                $title = trim($link->nodeValue);
                
                if (empty($title)) continue;
                
                $title = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $title = preg_replace('/\s+/', ' ', $title);
                $title = trim($title);
                
                $key = $href . '|' . $title;
                if (isset($poems[$key])) continue;
                
                $poems[$key] = [
                    'href' => $href,
                    'title' => $title,
                    'category' => $category
                ];
            }
        }

        return array_values($poems);
    }
}
