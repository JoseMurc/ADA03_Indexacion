<?php

declare(strict_types=1);

namespace App\Search;

use App\Indexing\Text\TokenizerFactory;
use App\Search\Dto\SearchOutcome;
use App\Search\Dto\SearchResult;

/**
 * Orquesta una busqueda: tokeniza la consulta con el mismo Tokenizer del
 * indexador, resuelve el indice invertido con PostingsRepository, califica
 * con TfIdfScorer y ordena los resultados.
 */
final class SearchService
{
    public function __construct(
        private PostingsRepository $postingsRepository,
        private TfIdfScorer $scorer,
    ) {
    }

    public function search(string $consulta): SearchOutcome
    {
        $inicio = microtime(true);

        $tokens = TokenizerFactory::create()->tokenize($consulta);
        $totalDocumentos = $this->postingsRepository->countDocuments();

        if ($tokens === []) {
            return new SearchOutcome($tokens, [], $totalDocumentos, $this->duracionMs($inicio));
        }

        $queryTf = array_count_values($tokens);
        $terminosUnicos = array_map(strval(...), array_keys($queryTf));

        $diccionario = $this->postingsRepository->findTerms($terminosUnicos);
        if ($diccionario === []) {
            return new SearchOutcome($tokens, [], $totalDocumentos, $this->duracionMs($inicio));
        }

        $dfPorTermino = [];
        $idsTerminos = [];
        foreach ($diccionario as $termino => $info) {
            $dfPorTermino[$termino] = $info['df'];
            $idsTerminos[] = $info['id'];
        }

        $postingsPorDoc = [];
        $idsDocumentos = [];
        foreach ($this->postingsRepository->postingsForTermIds($idsTerminos) as $posting) {
            $idDocumento = $posting['id_documento'];
            $postingsPorDoc[$idDocumento][$posting['termino']] = $posting['tf'];
            $idsDocumentos[$idDocumento] = true;
        }
        $idsDocumentos = array_keys($idsDocumentos);

        $vectoresPorDoc = $this->postingsRepository->documentVectors($idsDocumentos);
        $meta = $this->postingsRepository->documentsMeta($idsDocumentos);

        $puntuaciones = $this->scorer->score($totalDocumentos, $queryTf, $dfPorTermino, $postingsPorDoc, $vectoresPorDoc);

        $resultados = [];
        foreach ($puntuaciones as $idDocumento => $puntuacion) {
            if (!isset($meta[$idDocumento])) {
                continue;
            }

            $resultados[] = new SearchResult(
                $idDocumento,
                $meta[$idDocumento]['nombre_original'],
                $meta[$idDocumento]['fragmento'],
                $puntuacion['coseno'],
                $puntuacion['tfidf'],
            );
        }

        usort($resultados, static function (SearchResult $a, SearchResult $b): int {
            if ($a->coseno !== $b->coseno) {
                return $b->coseno <=> $a->coseno;
            }
            if ($a->tfidf !== $b->tfidf) {
                return $b->tfidf <=> $a->tfidf;
            }

            return $a->idDocumento <=> $b->idDocumento;
        });

        return new SearchOutcome($tokens, $resultados, $totalDocumentos, $this->duracionMs($inicio));
    }

    private function duracionMs(float $inicio): float
    {
        return (microtime(true) - $inicio) * 1000;
    }
}
