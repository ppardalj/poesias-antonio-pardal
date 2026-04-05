<?php

namespace Poesias;

use DOMDocument;
use DOMXPath;

class PoemParser
{
    /**
     * Parsea el contenido HTML de un poema y devuelve el output con Front Matter.
     *
     * @param string $html Contenido HTML del poema.
     * @return string Poema formateado con Front Matter.
     */
    public function parse(string $html): string
    {
        libxml_use_internal_errors(true); // evitar warnings por HTML roto

        $dom = new DOMDocument();
        $dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);

        $xpath = new DOMXPath($dom);

        // Extraer el título del poema (normalmente en la etiqueta <title> o un <strong> específico)
        $titleNode = $xpath->query('//title')->item(0);
        $title = $titleNode ? trim($titleNode->nodeValue) : 'Sin título';

        // Intentar sacar el título del primer <strong> dentro de un <p align="center"> si el del title es genérico
        $firstStrong = $xpath->query('//p[@align="center"]//strong')->item(0);
        if ($firstStrong && mb_strlen(trim($firstStrong->nodeValue)) < 50) {
            $title = trim($firstStrong->nodeValue);
        }

        // Limpiar el título si contiene prefijos comunes o puntos finales
        $title = preg_replace('/^Poesias Antonio Pardal\s*-\s*/i', '', $title);
        $title = rtrim($title, '.');

        // Fecha actual en formato ISO 8601
        $date = date('c');

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

        // Construir resultado final con Front Matter
        $output = "---\n";
        $output .= "title: \"$title\"\n";
        $output .= "date: $date\n";
        $output .= "---\n\n";

        $output .= implode("\n\n", $poem);
        $output .= "\n";

        return $output;
    }
}
