<?php

declare(strict_types=1);

namespace App\Search;

use App\Search\Dto\SearchOutcome;
use App\Search\Dto\SearchResult;

final class FulltextSearchService
{
    public function __construct(
        private FulltextRepository $repository
    ) {
    }

    public function search(string $consulta): SearchOutcome
    {
        $inicio = microtime(true);
        $consulta = trim($consulta);

        $totalDocumentos = $this->repository->countDocuments();

        $tokens = preg_split('/\s+/u', mb_strtolower($consulta))
            ?: [];

        $tokens = array_values(array_filter($tokens));

        if ($consulta === '') {
            return new SearchOutcome(
                [],
                [],
                $totalDocumentos,
                (microtime(true) - $inicio) * 1000
            );
        }

        $filas = $this->repository->search($consulta);
        $resultados = [];

        foreach ($filas as $fila) {
            $resultados[] = new SearchResult(
                (int) $fila['id_documento'],
                (string) $fila['nombre_original'],
                (string) $fila['fragmento'],
                (float) $fila['relevancia'],
                0.0
            );
        }

        return new SearchOutcome(
            $tokens,
            $resultados,
            $totalDocumentos,
            (microtime(true) - $inicio) * 1000
        );
    }
}

