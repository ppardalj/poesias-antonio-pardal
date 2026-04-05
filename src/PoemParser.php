<?php

namespace Poesias;

use DOMDocument;
use DOMXPath;
use Cocur\Slugify\Slugify;

class PoemParser
{
    private Slugify $slugify;

    public function __construct()
    {
        $this->slugify = new Slugify();
    }

    /**
     * Genera un slug a partir de un texto.
     */
    public function slugify(string $text): string
    {
        return $this->slugify->slugify($text);
    }

    /**
     * Extrae el ID formateado a partir del nombre de archivo.
     */
    public static function formatId(?string $id): ?string
    {
        if ($id === null) {
            return null;
        }
        // Extraer números del ID (ej. Antonio312.htm -> 312)
        if (preg_match('/(\d+)/', $id, $matches)) {
            return sprintf('%03d', $matches[1]);
        }
        return null;
    }

    /**
     * Genera un slug a partir de un título y un ID.
     */
    public static function generateSlug(string $title, ?string $formattedId = null): string
    {
        $slugify = new Slugify();
        $slug = $slugify->slugify($title);
        if ($formattedId !== null) {
            $slug = $formattedId . '-' . $slug;
        }
        return $slug;
    }

    /**
     * Parsea el contenido HTML de un poema y devuelve el output con Front Matter.
     *
     * @param string $html Contenido HTML del poema.
     * @param string|null $id ID del poema (ej. nombre del archivo).
     * @param string|null $title Título del poema (si se proporciona, se usa en lugar de extraerlo).
     * @param string|null $category Categoría del poema.
     * @return string Poema formateado con Front Matter.
     */
    public function parse(string $html, ?string $id = null, ?string $title = null, ?string $category = null): string
    {
        libxml_use_internal_errors(true); // evitar warnings por HTML roto

        $dom = new DOMDocument();
        $dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);

        $xpath = new DOMXPath($dom);

        if ($title === null) {
            $title = $this->extractTitle($xpath);
        } else {
            // Asegurarse de que el título proporcionado esté en MAYÚSCULAS y limpio
            $title = mb_strtoupper(trim($title), 'UTF-8');
        }
        
        $formattedId = self::formatId($id);
        $slug = self::generateSlug($title, $formattedId);

        $date = $this->extractDate($xpath);
        $poemBlocks = $this->extractPoemText($dom, $xpath);

        $output = $this->computeFrontMatter($title, $slug, $date, $formattedId, $category);
        $output .= "# $title\n\n";
        $output .= implode("\n\n", $poemBlocks);
        $output .= "\n";

        return $output;
    }

    /**
     * Extrae el título del poema.
     */
    public function extractTitle(DOMXPath $xpath): string
    {
        // Extraer el título del poema (normalmente en la etiqueta <title> o un <strong> específico)
        $titleNode = $xpath->query('//title')->item(0);
        $title = $titleNode ? trim($titleNode->nodeValue) : 'Sin título';

        // Intentar sacar el título de un <font size="6"> o similar si el del title es genérico
        $fontTitle = $xpath->query('//p[@align="center"]//font[@size="6"]|//p[@align="center" and @class="Estilo1"]//font|//div[@align="center"]//p//font[@size="6"]')->item(0);
        if ($fontTitle) {
            $title = trim($fontTitle->nodeValue);
        }

        // Intentar sacar el título del primer <strong> dentro de un <p align="center"> o <div align="center">
        // si el del title es genérico o no se encontró el font
        if (!$fontTitle || empty($title) || stripos($title, 'Poesias Antonio Pardal') !== false) {
            $firstStrong = $xpath->query('//p[@align="center"]//strong|//div[@align="center"]//p//strong')->item(0);
            if ($firstStrong && mb_strlen(trim($firstStrong->nodeValue)) < 50) {
                $title = trim($firstStrong->nodeValue);
            }
        }

        // Limpiar el título si contiene prefijos comunes o puntos finales
        $title = preg_replace('/^Poesias Antonio Pardal\s*-\s*/i', '', $title);
        $title = rtrim($title, '.');

        // Normalizar espacios en blanco (convertir todos los \n, \r, \t y múltiples espacios en un solo espacio)
        $title = preg_replace('/\s+/', ' ', $title);

        // Convertir a MAYÚSCULAS
        $title = mb_strtoupper($title, 'UTF-8');

        return $title;
    }

    /**
     * Extrae la fecha en formato ISO 8601.
     */
    private function extractDate(DOMXPath $xpath): ?string
    {
        // Buscar fechas en párrafos. Ejemplo: Antonio Pardal Rivas 9-7-2012 o 17-12-07
        $nodes = $xpath->query('//p');
        foreach ($nodes as $node) {
            $text = trim($node->nodeValue);
            // Buscar patrón de fecha (día-mes-año) con año de 2 o 4 dígitos
            if (preg_match('/(\d{1,2})-(\d{1,2})-(\d{2,4})/', $text, $matches)) {
                $day = (int)$matches[1];
                $month = (int)$matches[2];
                $year = (int)$matches[3];

                if ($year < 100) {
                    $year += 2000;
                }

                // Formatear a ISO (YYYY-MM-DD)
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
        }

        return null;
    }

    /**
     * Extrae el texto del poema procesando cada estrofa.
     *
     * @return string[]
     */
    private function extractPoemText(DOMDocument $dom, DOMXPath $xpath): array
    {
        // buscar <p align="center"> o <P align="center"> que contengan <strong> o <b> (o mayúsculas)
        $nodes = $xpath->query('//p[@align="center" or @align="CENTER"][.//strong or .//b or .//STRONG or .//B]');

        $poem = [];

        foreach ($nodes as $node) {
            // convertir <br> en saltos de línea
            $innerHTML = '';
            foreach ($node->childNodes as $child) {
                // saveHTML genera el HTML completo del nodo, incluyendo tags
                $innerHTML .= $dom->saveHTML($child);
            }
            
            // 1. Convertimos los <br> en marcadores temporales [[BR]] para que no se pierdan con strip_tags
            // Es vital capturar los <BR> antes de colapsar espacios.
            $text = preg_replace('/<br\s*\/?>/i', '[[BR]]', $innerHTML);
            
            // 2. Quitar el resto de tags HTML
            $text = strip_tags($text);

            // 3. Decodificar entidades HTML (&ntilde;, etc.)
            $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            
            // 4. Limpiar espacios en blanco del HTML original (incluyendo \n reales)
            // Reemplazamos todos los espacios en blanco (\s+) por un espacio normal, 
            // pero OJO: esto colapsará "[[BR]] \n [[BR]]" en "[[BR]] [[BR]]"
            $text = preg_replace('/\s+/', ' ', $text);
            $text = trim($text);

            // 5. Detectar estrofas: [[BR]] seguidos de espacios y más [[BR]] deben ser \n\n
            // Buscamos patrones como [[BR]] [[BR]] o [[BR]] [[BR]] [[BR]] y los convertimos en un marcador de estrofa
            $text = preg_replace('/\[\[BR\]\](\s*\[\[BR\]\])+/', "\n\n", $text);
            
            // Los [[BR]] individuales que queden son saltos de línea simples
            $text = str_replace('[[BR]]', "\n", $text);

            // 6. Dividir por múltiples saltos de línea (que indican cambios de estrofa)
            $blocks = preg_split("/\n{2,}/", $text);

            // filtrar cosas cortas (ruido)
            foreach ($blocks as $block) {
                // Limpiar cada línea de la estrofa para quitar espacios al principio y final
                $lines = explode("\n", $block);
                $cleanedLines = array_map('trim', $lines);
                $trimmedBlock = implode("\n", $cleanedLines);

                if (mb_strlen(str_replace("\n", "", $trimmedBlock)) > 15) {
                    $poem[] = $trimmedBlock;
                }
            }
        }

        return $poem;
    }

    /**
     * Computa el Front Matter.
     */
    private function computeFrontMatter(string $title, string $slug, ?string $date, ?string $id = null, ?string $category = null): string
    {
        $output = "---\n";
        if ($id !== null) {
            $output .= "id: $id\n";
        }
        $output .= "title: \"$title\"\n";
        $output .= "slug: $slug\n";
        if ($category !== null) {
            $output .= "category: " . $this->slugify->slugify($category) . "\n";
        }
        if ($date) {
            $output .= "date: $date\n";
        }
        $output .= "---\n\n";

        return $output;
    }
}
