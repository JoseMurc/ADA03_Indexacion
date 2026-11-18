<?php

declare(strict_types=1);

use App\Indexing\Reindexer;
use App\Support\Database;

require __DIR__ . '/../vendor/autoload.php';

if (PHP_SAPI !== 'cli') {
    return;
}

$baseDir = getenv('UPLOADS_BASE_DIR') ?: (__DIR__ . '/../storage/uploads');

try {
    $pdo = Database::fromEnv();

    $inicio = microtime(true);
    $estadisticas = (new Reindexer($pdo, $baseDir))->run();
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
