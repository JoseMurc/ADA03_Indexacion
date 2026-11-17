<?php

declare(strict_types=1);

namespace App\Indexing;

use App\Indexing\Contracts\IndexRepositoryInterface;
use App\Indexing\Dto\IndexedDocument;
use App\Indexing\Text\Tokenizer;
use Throwable;

/**
 * Reconstruye por completo el indice invertido (diccionario + posting) a
 * partir de una coleccion de documentos. Es una reconstruccion total: el
 * idf depende de N, asi que no se hace de forma incremental.
 */
final class IndexBuilder
{
    public function __construct(
        private Tokenizer $tokenizer,
        private TermFrequencyCalculator $frequencyCalculator,
        private SnippetExtractor $snippetExtractor,
        private IndexRepositoryInterface $repository,
    ) {
    }

    /**
     * @param iterable<IndexedDocument> $documents
     * @return array{documentos:int,terminos:int,postings:int}
     */
    public function rebuild(iterable $documents): array
    {
        $this->repository->beginTransaction();

        try {
            $this->repository->clearIndex();

            /** @var array<string,array<int,int>> $postingsPorTermino termino => [idDocumento => frecuencia] */
            $postingsPorTermino = [];
            /** @var array<string,int> $frecuenciaTotalPorTermino */
            $frecuenciaTotalPorTermino = [];
            $totalDocumentos = 0;

            foreach ($documents as $documento) {
                $totalDocumentos++;

                $tokens = $this->tokenizer->tokenize($documento->contenido);
                $frecuencias = $this->frequencyCalculator->calculate($tokens);

                foreach ($frecuencias as $termino => $frecuencia) {
                    // PHP convierte claves de array numericas (p. ej. "2024") a int;
                    // forzamos string para no romper el tipado estricto mas adelante.
                    $termino = (string) $termino;
                    $postingsPorTermino[$termino][$documento->idDocumento] = $frecuencia;
                    $frecuenciaTotalPorTermino[$termino] = ($frecuenciaTotalPorTermino[$termino] ?? 0) + $frecuencia;
                }

                $this->repository->updateDocumentStats(
                    $documento->idDocumento,
                    array_sum($frecuencias),
                    $this->snippetExtractor->extract($documento->contenido),
                );
            }

            $totalPostings = 0;
            foreach ($postingsPorTermino as $termino => $postingsDeTermino) {
                // Misma conversion de clave numerica que PHP aplica a los arrays.
                $termino = (string) $termino;
                $idTermino = $this->repository->saveTerm(
                    $termino,
                    count($postingsDeTermino),
                    $frecuenciaTotalPorTermino[$termino],
                );

                foreach ($postingsDeTermino as $idDocumento => $frecuencia) {
                    $this->repository->savePosting($idTermino, $idDocumento, $frecuencia);
                    $totalPostings++;
                }
            }

            $this->repository->commit();
        } catch (Throwable $excepcion) {
            $this->repository->rollBack();
            throw $excepcion;
        }

        return [
            'documentos' => $totalDocumentos,
            'terminos' => count($postingsPorTermino),
            'postings' => $totalPostings,
        ];
    }
}
