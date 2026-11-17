<?php

declare(strict_types=1);

namespace App\Indexing\Source;

use App\Indexing\Contracts\DocumentSourceInterface;
use App\Indexing\Dto\IndexedDocument;
use PDO;
use RuntimeException;

/**
 * Lee los documentos registrados por el Modulo 1 (tabla `documentos`) y
 * entrega su contenido en UTF-8, listo para tokenizar.
 */
final class MySqlDocumentSource implements DocumentSourceInterface
{
    public function __construct(
        private PDO $pdo,
        private string $baseDir,
    ) {
    }

    public function getAll(): iterable
    {
        $sentencia = $this->pdo->query(
            'SELECT id_documento, nombre_original, ruta_archivo FROM documentos ORDER BY id_documento'
        );

        if ($sentencia === false) {
            throw new RuntimeException('No se pudo consultar la tabla documentos.');
        }

        while ($fila = $sentencia->fetch(PDO::FETCH_ASSOC)) {
            $idDocumento = (int) $fila['id_documento'];
            $ruta = $this->resolverRuta((string) $fila['ruta_archivo']);

            yield new IndexedDocument(
                $idDocumento,
                (string) $fila['nombre_original'],
                $this->leerContenido($ruta, $idDocumento),
            );
        }
    }

    private function resolverRuta(string $rutaArchivo): string
    {
        $esAbsoluta = str_starts_with($rutaArchivo, '/')
            || preg_match('#^[A-Za-z]:[\\\\/]#', $rutaArchivo) === 1;

        if ($esAbsoluta) {
            return $rutaArchivo;
        }

        return rtrim($this->baseDir, '/\\') . DIRECTORY_SEPARATOR . $rutaArchivo;
    }

    private function leerContenido(string $ruta, int $idDocumento): string
    {
        if (!is_readable($ruta)) {
            throw new RuntimeException(sprintf(
                'No se pudo leer el archivo del documento %d en la ruta "%s".',
                $idDocumento,
                $ruta,
            ));
        }

        $contenido = file_get_contents($ruta);
        if ($contenido === false) {
            throw new RuntimeException(sprintf(
                'No se pudo leer el archivo del documento %d en la ruta "%s".',
                $idDocumento,
                $ruta,
            ));
        }

        return $this->aUtf8($this->quitarBom($contenido));
    }

    private function quitarBom(string $contenido): string
    {
        $bom = "\xEF\xBB\xBF";
        if (str_starts_with($contenido, $bom)) {
            return substr($contenido, strlen($bom));
        }

        return $contenido;
    }

    private function aUtf8(string $contenido): string
    {
        $codificacion = mb_detect_encoding($contenido, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
        if ($codificacion === false) {
            $codificacion = 'Windows-1252';
        }

        if ($codificacion === 'UTF-8') {
            return $contenido;
        }

        return (string) mb_convert_encoding($contenido, 'UTF-8', $codificacion);
    }
}
