<?php

declare(strict_types=1);

namespace App\Storage;

use PDO;

/**
 * Acceso a la tabla `documentos` para las operaciones de M1 (carga y
 * descarga). No toca `fragmento` ni `total_terminos`: esos campos los
 * llena el indexador (M2) al reindexar.
 */
final class DocumentRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findByHash(string $hashSha256): ?array
    {
        $sentencia = $this->pdo->prepare(
            'SELECT * FROM documentos WHERE hash_sha256 = :hash LIMIT 1'
        );
        $sentencia->execute(['hash' => $hashSha256]);

        $fila = $sentencia->fetch();

        return $fila === false ? null : $fila;
    }

    public function insert(
        string $nombreOriginal,
        string $nombreGuardado,
        string $rutaArchivo,
        string $hashSha256,
    ): int {
        $sentencia = $this->pdo->prepare(
            'INSERT INTO documentos (nombre_original, nombre_guardado, ruta_archivo, hash_sha256)
             VALUES (:nombre_original, :nombre_guardado, :ruta_archivo, :hash)'
        );
        $sentencia->execute([
            'nombre_original' => $nombreOriginal,
            'nombre_guardado' => $nombreGuardado,
            'ruta_archivo' => $rutaArchivo,
            'hash' => $hashSha256,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function findById(int $idDocumento): ?array
    {
        $sentencia = $this->pdo->prepare(
            'SELECT * FROM documentos WHERE id_documento = :id LIMIT 1'
        );
        $sentencia->execute(['id' => $idDocumento]);

        $fila = $sentencia->fetch();

        return $fila === false ? null : $fila;
    }

    public function count(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM documentos')->fetchColumn();
    }
}
