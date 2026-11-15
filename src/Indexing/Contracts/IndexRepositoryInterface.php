<?php

declare(strict_types=1);

namespace App\Indexing\Contracts;

interface IndexRepositoryInterface
{
    public function beginTransaction(): void;

    public function commit(): void;

    public function rollBack(): void;

    /**
     * Borra el contenido actual de diccionario y posting. No modifica documentos.
     */
    public function clearIndex(): void;

    /**
     * @return int id_termino generado para el termino guardado.
     */
    public function saveTerm(string $termino, int $frecuenciaDocumental, int $frecuenciaTotal): int;

    public function savePosting(int $idTermino, int $idDocumento, int $frecuencia): void;

    public function updateDocumentStats(int $idDocumento, int $totalTerminos, string $fragmento): void;

    /**
     * Vuelca en el almacen cualquier escritura pendiente en buffer.
     */
    public function flush(): void;
}
