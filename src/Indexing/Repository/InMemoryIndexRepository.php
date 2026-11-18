<?php

declare(strict_types=1);

namespace App\Indexing\Repository;

use App\Indexing\Contracts\IndexRepositoryInterface;
use RuntimeException;

/**
 * Implementacion en memoria de IndexRepositoryInterface, pensada para tests
 * (permite inspeccionar el estado resultante sin una base de datos real).
 */
final class InMemoryIndexRepository implements IndexRepositoryInterface
{
    /** @var array<int,array{termino:string,frecuencia_documental:int,frecuencia_total:int}> */
    private array $terminos = [];

    /** @var array<int,array{id_termino:int,id_documento:int,frecuencia:int}> */
    private array $postings = [];

    /** @var array<int,array{total_terminos:int,fragmento:string}> */
    private array $documentStats = [];

    private int $siguienteIdTermino = 1;

    private bool $enTransaccion = false;

    /** @var array{terminos:array,postings:array,documentStats:array,siguienteIdTermino:int}|null */
    private ?array $snapshot = null;

    public function beginTransaction(): void
    {
        $this->snapshot = [
            'terminos' => $this->terminos,
            'postings' => $this->postings,
            'documentStats' => $this->documentStats,
            'siguienteIdTermino' => $this->siguienteIdTermino,
        ];
        $this->enTransaccion = true;
    }

    public function commit(): void
    {
        $this->flush();
        $this->enTransaccion = false;
        $this->snapshot = null;
    }

    public function rollBack(): void
    {
        if ($this->snapshot === null) {
            throw new RuntimeException('No hay una transaccion activa para revertir.');
        }

        $this->terminos = $this->snapshot['terminos'];
        $this->postings = $this->snapshot['postings'];
        $this->documentStats = $this->snapshot['documentStats'];
        $this->siguienteIdTermino = $this->snapshot['siguienteIdTermino'];
        $this->enTransaccion = false;
        $this->snapshot = null;
    }

    public function clearIndex(): void
    {
        $this->terminos = [];
        $this->postings = [];
        $this->siguienteIdTermino = 1;
    }

    public function saveTerm(string $termino, int $frecuenciaDocumental, int $frecuenciaTotal): int
    {
        $idTermino = $this->siguienteIdTermino++;
        $this->terminos[$idTermino] = [
            'termino' => $termino,
            'frecuencia_documental' => $frecuenciaDocumental,
            'frecuencia_total' => $frecuenciaTotal,
        ];

        return $idTermino;
    }

    public function savePosting(int $idTermino, int $idDocumento, int $frecuencia): void
    {
        $this->postings[] = [
            'id_termino' => $idTermino,
            'id_documento' => $idDocumento,
            'frecuencia' => $frecuencia,
        ];
    }

    public function updateDocumentStats(int $idDocumento, int $totalTerminos, string $fragmento): void
    {
        $this->documentStats[$idDocumento] = [
            'total_terminos' => $totalTerminos,
            'fragmento' => $fragmento,
        ];
    }

    public function flush(): void
    {
        // No hay buffer que volcar: las escrituras en memoria son inmediatas.
    }

    /** @return array<int,array{termino:string,frecuencia_documental:int,frecuencia_total:int}> */
    public function getTerms(): array
    {
        return $this->terminos;
    }

    /** @return array<int,array{id_termino:int,id_documento:int,frecuencia:int}> */
    public function getPostings(): array
    {
        return $this->postings;
    }

    /** @return array<int,array{total_terminos:int,fragmento:string}> */
    public function getDocumentStats(): array
    {
        return $this->documentStats;
    }
}
