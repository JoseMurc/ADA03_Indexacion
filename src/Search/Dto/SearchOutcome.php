<?php

declare(strict_types=1);

namespace App\Search\Dto;

final readonly class SearchOutcome
{
    /**
     * @param string[] $tokens
     * @param SearchResult[] $resultados
     */
    public function __construct(
        public array $tokens,
        public array $resultados,
        public int $totalDocumentos,
        public float $duracionMs,
    ) {
    }
}
