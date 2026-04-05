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

// 1. Generar INDEX.md
echo "Generando INDEX.md...\n";
$indexGenerator = new IndexGenerator();
$indexMarkdown = $indexGenerator->generate($indexHtml);
file_put_contents($outputDir . '/INDEX.md', $indexMarkdown);

// 2. Obtener lista de poemas
$poems = $indexGenerator->getPoems($indexHtml);
$total = count($poems);
echo "Se han identificado $total poemas.\n";

// 3. Procesar cada poema
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
    $markdown = $poemParser->parse($poemHtml, basename($href), $poem['title']);
    
    // El slug se usa para el nombre del archivo. 
    // Reutilizamos la lógica de PoemParser para obtener el slug exacto que espera el INDEX.md
    $formattedId = PoemParser::formatId($href);
    $slug = PoemParser::generateSlug($poem['title'], $formattedId);
    
    file_put_contents($poemsOutputDir . '/' . $slug . '.md', $markdown);
}

echo "\n¡Proceso completado! Los archivos se encuentran en la carpeta 'output/'.\n";
