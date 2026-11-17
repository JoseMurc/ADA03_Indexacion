<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Indexing\Reindexer;
use App\Search\PostingsRepository;
use App\Search\SearchService;
use App\Search\TfIdfScorer;
use App\Storage\DocumentRepository;
use App\Storage\FileLocator;
use App\Storage\UploadService;
use App\Support\Database;

session_start();

$uploadsBaseDir = getenv('UPLOADS_BASE_DIR') ?: (__DIR__ . '/../storage/uploads');
if (!is_dir($uploadsBaseDir)) {
    mkdir($uploadsBaseDir, 0775, true);
}

$pdo = Database::fromEnv();

$fileLocator = new FileLocator($uploadsBaseDir);
$documentRepository = new DocumentRepository($pdo);

$uploadService = new UploadService(
    $documentRepository,
    $fileLocator,
    static fn (): array => (new Reindexer($pdo, $fileLocator->baseDir()))->run(),
);

$searchService = new SearchService(
    new PostingsRepository($pdo),
    new TfIdfScorer(),
);
