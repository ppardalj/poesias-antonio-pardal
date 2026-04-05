<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Poesias\IndexGenerator;

$filename = __DIR__ . '/../input/index.htm';

if (!file_exists($filename)) {
    echo "Error: El archivo '$filename' no existe.\n";
    exit(1);
}

$html = file_get_contents($filename);
$generator = new IndexGenerator();
echo $generator->generate($html);
