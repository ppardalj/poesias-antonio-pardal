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
     * @return string Poema formateado con Front Matter.
     */
    public function parse(string $html, ?string $id = null, ?string $title = null): string
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

        $output = $this->computeFrontMatter($title, $slug, $date, $formattedId);
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
        // buscar <p align="center"> que contengan <strong>
        $nodes = $xpath->query('//p[@align="center"][.//strong]');

        $poem = [];

        foreach ($nodes as $node) {
            // convertir <br> en saltos de línea
            $innerHTML = '';
            foreach ($node->childNodes as $child) {
                $innerHTML .= $dom->saveHTML($child);
            }

            // normalizar espacios y saltos de línea del HTML original para que no afecten
            // pero guardando los <br> antes
            $textWithPlaceholders = preg_replace('/<br\s*\/?>/i', '[[BR]]', $innerHTML);

            // quitar el resto de tags
            $text = strip_tags($textWithPlaceholders);

            // decodificar entidades HTML (&ntilde;, etc.)
            $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            // Normalizar espacios en blanco (convertir todos los \n, \r, \t y múltiples espacios en un solo espacio)
            $text = preg_replace('/\s+/', ' ', $text);

            // convertir nuestro marcador [[BR]] en saltos de línea reales
            $text = str_replace('[[BR]]', "\n", $text);

            // limpiar espacios en blanco entre versos y líneas en blanco adicionales dentro de la estrofa
            $lines = explode("\n", $text);
            $cleanedLines = [];
            foreach ($lines as $line) {
                $trimmed = trim($line);
                if ($trimmed !== '') {
                    $cleanedLines[] = $trimmed;
                }
            }
            $text = implode("\n", $cleanedLines);

            // filtrar cosas cortas (ruido)
            if (mb_strlen($text) > 30) {
                $poem[] = $text;
            }
        }

        return $poem;
    }

    /**
     * Computa el Front Matter.
     */
    private function computeFrontMatter(string $title, string $slug, ?string $date, ?string $id = null): string
    {
        $output = "---\n";
        if ($id !== null) {
            $output .= "id: $id\n";
        }
        $output .= "title: \"$title\"\n";
        $output .= "slug: $slug\n";
        if ($date) {
            $output .= "date: $date\n";
        }
        $output .= "---\n\n";

        return $output;
    }
}
