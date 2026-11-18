<?php

declare(strict_types=1);

namespace App\Storage\Dto;

/**
 * Resultado de procesar un lote de archivos subidos.
 */
final class UploadReport
{
    /** @var string[] Nombres originales guardados con exito. */
    public array $stored = [];

    /** @var array<string,string> Nombre original => nombre del documento ya existente con el mismo contenido. */
    public array $duplicates = [];

    /** @var array<string,string> Nombre original => motivo del rechazo. */
    public array $rejected = [];

    /** @var array{documentos:int,terminos:int,postings:int}|null */
    public ?array $reindexStats = null;

    public ?string $reindexError = null;
}
