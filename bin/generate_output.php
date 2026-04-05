<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Poesias\IndexGenerator;
use Poesias\PoemParser;

$inputDir = __DIR__ . '/../input';
$outputDir = __DIR__ . '/../output';
$poemsOutputDir = $outputDir . '/poems';

// Asegurar que los directorios de salida existen
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0777, true);
}
if (!is_dir($poemsOutputDir)) {
    mkdir($poemsOutputDir, 0777, true);
}

$indexHtmlPath = $inputDir . '/index.htm';
if (!file_exists($indexHtmlPath)) {
    echo "Error: No se encuentra $indexHtmlPath\n";
    exit(1);
}

$indexHtml = file_get_contents($indexHtmlPath);

// 1. Obtener lista de poemas del índice
echo "Analizando el índice...\n";
$indexGenerator = new IndexGenerator();
$poemsFromIndex = $indexGenerator->getPoems($indexHtml);

// 2. Detectar poemas no listados en el índice
echo "Buscando poemas no listados en el índice...\n";
$allPoemFiles = glob($inputDir . '/Antonio*.htm');
$indexedHrefs = array_map(fn($p) => $p['href'], $poemsFromIndex);
$extraPoems = [];

foreach ($allPoemFiles as $file) {
    $href = basename($file);
    if (!in_array($href, $indexedHrefs)) {
        // Extraer título del propio fichero
        $poemHtml = file_get_contents($file);
        
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $poemHtml, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($dom);
        
        $poemParser = new PoemParser();
        $title = $poemParser->extractTitle($xpath);
        
        $extraPoems[] = [
            'href' => $href,
            'title' => $title ?: str_replace('.htm', '', $href), // Fallback al nombre del archivo
            'category' => 'sin-categorizar'
        ];
    }
}

$poems = array_merge($poemsFromIndex, $extraPoems);
$total = count($poems);
echo "Se han identificado $total poemas (" . count($poemsFromIndex) . " en índice + " . count($extraPoems) . " sin categorizar).\n";

// 3. Generar INDEX.md con poemas extra
echo "Generando INDEX.md...\n";
$indexMarkdown = $indexGenerator->generate($indexHtml, $extraPoems);
file_put_contents($outputDir . '/INDEX.md', $indexMarkdown);

// 4. Procesar cada poema
$poemParser = new PoemParser();
foreach ($poems as $index => $poem) {
    $href = $poem['href'];
    $poemFile = $inputDir . '/' . $href;
    
    if (!file_exists($poemFile)) {
        echo "Advertencia: El archivo $poemFile no existe. Saltando...\n";
        continue;
    }

    $currentNum = $index + 1;
    echo "[$currentNum/$total] Procesando $href...\n";
    
    $poemHtml = file_get_contents($poemFile);
    $markdown = $poemParser->parse($poemHtml, basename($href), $poem['title'], $poem['category'] ?? null);
    
    // El slug se usa para el nombre del archivo. 
    // Reutilizamos la lógica de PoemParser para obtener el slug exacto que espera el INDEX.md
    $formattedId = PoemParser::formatId($href);
    $slug = PoemParser::generateSlug($poem['title'], $formattedId);
    
    file_put_contents($poemsOutputDir . '/' . $slug . '.md', $markdown);
}

echo "\n¡Proceso completado! Los archivos se encuentran en la carpeta 'output/'.\n";
