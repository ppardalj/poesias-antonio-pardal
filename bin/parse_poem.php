<?php


require_once __DIR__ . '/../vendor/autoload.php';

use Poesias\PoemParser;

if ($argc < 2) {
    echo "Uso: php bin/parse_poem.php <archivo_html>\n";
    exit(1);
}

$filename = $argv[1];

if (!file_exists($filename)) {
    echo "Error: El archivo '$filename' no existe.\n";
    exit(1);
}

$html = file_get_contents($filename);

$parser = new PoemParser();
$output = $parser->parse($html);

echo $output;
