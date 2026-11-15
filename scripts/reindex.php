<?php

declare(strict_types=1);

use App\Indexing\IndexBuilder;
use App\Indexing\Repository\MySqlIndexRepository;
use App\Indexing\SnippetExtractor;
use App\Indexing\Source\MySqlDocumentSource;
use App\Indexing\TermFrequencyCalculator;
use App\Indexing\Text\StopwordList;
use App\Indexing\Text\TextNormalizer;
use App\Indexing\Text\Tokenizer;

require __DIR__ . '/../vendor/autoload.php';

/**
 * Ensambla las dependencias reales y ejecuta la reconstruccion total del
 * indice invertido. Reutilizable desde el CLI o desde PHP web (M1/M4).
 *
 * @return array{documentos:int,terminos:int,postings:int}
 */
function runReindex(PDO $pdo, string $baseDir): array
{
    $tokenizer = new Tokenizer(new TextNormalizer(), new StopwordList());
    $documentSource = new MySqlDocumentSource($pdo, $baseDir);
    $repository = new MySqlIndexRepository($pdo);

    $builder = new IndexBuilder(
        $tokenizer,
        new TermFrequencyCalculator(),
        new SnippetExtractor(),
        $repository,
    );

    return $builder->rebuild($documentSource->getAll());
}

if (PHP_SAPI !== 'cli') {
    return;
}

$host = getenv('DB_HOST') ?: '127.0.0.1';
$dbName = getenv('DB_NAME') ?: 'buscador_ir';
$user = getenv('DB_USER') ?: '';
$pass = getenv('DB_PASS') ?: '';
$baseDir = getenv('UPLOADS_BASE_DIR') ?: '';

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $host, $dbName),
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
    );

    $inicio = microtime(true);
    $estadisticas = runReindex($pdo, $baseDir);
    $duracion = microtime(true) - $inicio;

    printf(
        "Reindexacion completa: %d documentos, %d terminos, %d postings (%.2f s)\n",
        $estadisticas['documentos'],
        $estadisticas['terminos'],
        $estadisticas['postings'],
        $duracion,
    );
} catch (Throwable $excepcion) {
    fwrite(STDERR, 'Error durante la reindexacion: ' . $excepcion->getMessage() . "\n");
    exit(1);
}
