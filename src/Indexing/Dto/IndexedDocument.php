<?php

declare(strict_types=1);

namespace App\Indexing\Dto;

/**
 * Representa un documento leido desde su origen (archivo en disco), listo
 * para ser tokenizado e indizado.
 */
final readonly class IndexedDocument
{
    public function __construct(
        public int $idDocumento,
        public string $nombreOriginal,
        public string $contenido,
    ) {
    }
}
