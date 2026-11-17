<?php

declare(strict_types=1);

namespace App\Search\Dto;

final readonly class SearchResult
{
    public function __construct(
        public int $idDocumento,
        public string $archivo,
        public string $fragmento,
        public float $coseno,
        public float $tfidf,
    ) {
    }
}
