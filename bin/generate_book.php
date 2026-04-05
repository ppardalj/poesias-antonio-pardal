#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Genera un libro en Markdown a partir de los poemas ya parseados.
 *
 * Estructura esperada:
 * - bin/
 * - output/
 *   - poems/
 *     - categoria-x/
 *       - 001-titulo.md
 *       - 002-otro.md
 *
 * Uso:
 *   php bin/generate_book.php
 *
 * Opcional:
 *   php bin/generate_book.php --title="Poesías de Antonio Pardal" --author="Antonio Pardal"
 */

const DEFAULT_BOOK_TITLE = 'Poesías de Antonio Pardal';
const DEFAULT_BOOK_AUTHOR = 'Antonio Pardal';

main($argv);

/**
 * Punto de entrada.
 *
 * @param array<int, string> $argv
 */
function main(array $argv): void
{
    $projectRoot = realpath(__DIR__ . '/..');
    if ($projectRoot === false) {
        fwrite(STDERR, "No se pudo resolver el directorio raíz del proyecto.\n");
        exit(1);
    }

    $options = parseCliOptions($argv);

    $bookTitle = $options['title'] ?? DEFAULT_BOOK_TITLE;
    $bookAuthor = $options['author'] ?? DEFAULT_BOOK_AUTHOR;

    $poemsRoot = $projectRoot . '/output/poems';
    $bookDir = $projectRoot . '/output/book';
    $bookFile = $bookDir . '/book.md';

    if (!is_dir($poemsRoot)) {
        fwrite(STDERR, "No existe el directorio de poemas: {$poemsRoot}\n");
        exit(1);
    }

    ensureDirectoryExists($bookDir);

    $poems = loadPoems($poemsRoot);

    if ($poems === []) {
        fwrite(STDERR, "No se encontraron poemas en {$poemsRoot}\n");
        exit(1);
    }

    $groupedPoems = groupPoemsByCategory($poems);
    $markdown = buildBookMarkdown($bookTitle, $bookAuthor, $groupedPoems);

    file_put_contents($bookFile, $markdown);

    fwrite(STDOUT, "Libro generado correctamente en:\n{$bookFile}\n");
    fwrite(STDOUT, "\nSiguiente paso sugerido:\n");
    fwrite(STDOUT, "pandoc output/book/book.md -o output/book/book.pdf --pdf-engine=xelatex\n");
}

/**
 * @param array<int, string> $argv
 * @return array<string, string>
 */
function parseCliOptions(array $argv): array
{
    $options = [];

    foreach ($argv as $arg) {
        if (!str_starts_with($arg, '--')) {
            continue;
        }

        $parts = explode('=', substr($arg, 2), 2);
        $key = $parts[0] ?? '';
        $value = $parts[1] ?? '';

        if ($key !== '') {
            $options[$key] = $value;
        }
    }

    return $options;
}

function ensureDirectoryExists(string $path): void
{
    if (is_dir($path)) {
        return;
    }

    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        fwrite(STDERR, "No se pudo crear el directorio: {$path}\n");
        exit(1);
    }
}

/**
 * @param string $poemsRoot
 * @return array<int, array{
 *     id: int,
 *     raw_id: string,
 *     title: string,
 *     slug: string,
 *     category: string,
 *     content: string,
 *     source_path: string
 * }>
 */
function loadPoems(string $poemsRoot): array
{
    $files = findMarkdownFiles($poemsRoot);
    $poems = [];

    foreach ($files as $filePath) {
        $poem = parsePoemFile($filePath);
        if ($poem !== null) {
            $poems[] = $poem;
        }
    }

    usort(
        $poems,
        static function (array $a, array $b): int {
            if ($a['category'] === $b['category']) {
                return $a['id'] <=> $b['id'];
            }

            return strcmp($a['category'], $b['category']);
        }
    );

    return $poems;
}

/**
 * @param string $root
 * @return array<int, string>
 */
function findMarkdownFiles(string $root): array
{
    $result = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );

    /** @var SplFileInfo $file */
    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }

        if (strtolower($file->getExtension()) !== 'md') {
            continue;
        }

        $result[] = $file->getPathname();
    }

    sort($result);

    return $result;
}

/**
 * @param string $filePath
 * @return array{
 *     id: int,
 *     raw_id: string,
 *     title: string,
 *     slug: string,
 *     category: string,
 *     content: string,
 *     source_path: string
 * }|null
 */
function parsePoemFile(string $filePath): ?array
{
    $raw = file_get_contents($filePath);
    if ($raw === false) {
        fwrite(STDERR, "No se pudo leer el fichero: {$filePath}\n");
        return null;
    }

    [$frontmatter, $body] = extractFrontmatterAndBody($raw);
    $meta = parseSimpleYamlFrontmatter($frontmatter);

    $rawId = trim((string)($meta['id'] ?? '0'));
    $title = trim((string)($meta['title'] ?? 'Sin título'));
    $slug = trim((string)($meta['slug'] ?? ''));
    $category = trim((string)($meta['category'] ?? basename(dirname($filePath))));

    $id = (int)ltrim($rawId, '0');
    if ($rawId === '0' || $rawId === '') {
        $id = 0;
    }

    $cleanBody = cleanPoemBody($body, $title);

    return [
        'id' => $id,
        'raw_id' => $rawId,
        'title' => $title,
        'slug' => $slug,
        'category' => $category,
        'content' => $cleanBody,
        'source_path' => $filePath,
    ];
}

/**
 * @param string $raw
 * @return array{0: string, 1: string}
 */
function extractFrontmatterAndBody(string $raw): array
{
    $pattern = '/^---\R(.*?)\R---\R?(.*)$/s';

    if (preg_match($pattern, $raw, $matches) === 1) {
        return [$matches[1], $matches[2]];
    }

    return ['', $raw];
}

/**
 * Parser YAML mínimo para pares clave: valor.
 *
 * Soporta líneas tipo:
 *   id: 049
 *   title: "NOSTALGIA DE AMOR"
 *   category: poesias-de-amor
 *
 * @param string $frontmatter
 * @return array<string, string>
 */
function parseSimpleYamlFrontmatter(string $frontmatter): array
{
    $data = [];
    $lines = preg_split('/\R/', $frontmatter) ?: [];

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $parts = explode(':', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $key = trim($parts[0]);
        $value = trim($parts[1]);

        $value = trim($value, " \t\n\r\0\x0B\"'");

        $data[$key] = $value;
    }

    return $data;
}

function cleanPoemBody(string $body, string $title): string
{
    $body = ltrim($body);

    $headingPattern = '/^#\s+' . preg_quote($title, '/') . '\s*\R+/iu';
    $body = preg_replace($headingPattern, '', $body) ?? $body;

    $body = trim($body);

    // Normalizar saltos triples o mayores a dobles.
    $body = preg_replace("/\R{3,}/", "\n\n", $body) ?? $body;

    // Para que Pandoc/Markdown respete los saltos de línea simples en los versos,
    // añadimos dos espacios al final de cada línea que no sea un salto de estrofa.
    $lines = preg_split('/\R/', $body) ?: [];
    $newLines = [];
    foreach ($lines as $line) {
        $trimmedLine = rtrim($line);
        if ($trimmedLine === '') {
            // Salto de estrofa: añadimos una línea vacía extra para mayor separación
            $newLines[] = '';
            $newLines[] = '&nbsp;'; // Forzamos un espacio en blanco para que Pandoc mantenga el hueco
            $newLines[] = '';
        } else {
            // Línea de verso: añadir dos espacios al final
            $newLines[] = $trimmedLine . '  ';
        }
    }

    return trim(implode("\n", $newLines));
}

/**
 * @param array<int, array{
 *     id: int,
 *     raw_id: string,
 *     title: string,
 *     slug: string,
 *     category: string,
 *     content: string,
 *     source_path: string
 * }> $poems
 * @return array<string, array<int, array{
 *     id: int,
 *     raw_id: string,
 *     title: string,
 *     slug: string,
 *     category: string,
 *     content: string,
 *     source_path: string
 * }>>
 */
function groupPoemsByCategory(array $poems): array
{
    $grouped = [];

    foreach ($poems as $poem) {
        $grouped[$poem['category']][] = $poem;
    }

    ksort($grouped);

    foreach ($grouped as &$categoryPoems) {
        usort(
            $categoryPoems,
            static fn(array $a, array $b): int => $a['id'] <=> $b['id']
        );
    }

    return $grouped;
}

/**
 * @param array<string, array<int, array{
 *     id: int,
 *     raw_id: string,
 *     title: string,
 *     slug: string,
 *     category: string,
 *     content: string,
 *     source_path: string
 * }>> $groupedPoems
 */
function buildBookMarkdown(string $bookTitle, string $bookAuthor, array $groupedPoems): string
{
    $parts = [];

    // Frontmatter útil para Pandoc.
    $parts[] = "---";
    $parts[] = 'title: "' . escapeYamlString($bookTitle) . '"';
    $parts[] = 'author: "' . escapeYamlString($bookAuthor) . '"';
    $parts[] = 'lang: "es"';
    $parts[] = "---";
    $parts[] = "";

    // Portada mínima.
    $parts[] = "# {$bookTitle}";
    $parts[] = "";
    $parts[] = $bookAuthor;
    $parts[] = "";
    $parts[] = "\\newpage";
    $parts[] = "";

    // Índice simple en markdown.
    $parts[] = "# Índice";
    $parts[] = "";

    foreach ($groupedPoems as $category => $poems) {
        $readableCategory = humanizeCategory($category);
        $parts[] = "## {$readableCategory}";
        $parts[] = "";

        foreach ($poems as $poem) {
            $parts[] = "- {$poem['title']}";
        }

        $parts[] = "";
    }

    $parts[] = "\\newpage";
    $parts[] = "";

    // Contenido por categorías.
    foreach ($groupedPoems as $category => $poems) {
        $readableCategory = humanizeCategory($category);

        $parts[] = "# {$readableCategory}";
        $parts[] = "";

        foreach ($poems as $poem) {
            $parts[] = "## {$poem['title']}";
            $parts[] = "";
            $parts[] = $poem['content'];
            $parts[] = "";
            $parts[] = "\\newpage";
            $parts[] = "";
        }
    }

    return implode("\n", $parts) . "\n";
}

function humanizeCategory(string $category): string
{
    $category = str_replace(['-', '_'], ' ', $category);
    $category = trim($category);

    $words = preg_split('/\s+/', $category) ?: [];

    $words = array_map(
        static function (string $word): string {
            $word = mb_strtolower($word, 'UTF-8');
            return mb_convert_case($word, MB_CASE_TITLE, 'UTF-8');
        },
        $words
    );

    return implode(' ', $words);
}

function escapeYamlString(string $value): string
{
    return str_replace('"', '\"', $value);
}