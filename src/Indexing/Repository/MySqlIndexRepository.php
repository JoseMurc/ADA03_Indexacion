<?php

declare(strict_types=1);

namespace App\Indexing\Repository;

use App\Indexing\Contracts\IndexRepositoryInterface;
use PDO;

/**
 * Persiste el indice invertido (diccionario/posting) y las estadisticas de
 * documentos en MySQL, usando unicamente prepared statements.
 */
final class MySqlIndexRepository implements IndexRepositoryInterface
{
    private const TAMANO_LOTE = 500;

    /** @var array<int, array{0:int,1:int,2:int}> */
    private array $bufferPostings = [];

    public function __construct(private PDO $pdo)
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->flush();
        $this->pdo->commit();
    }

    public function rollBack(): void
    {
        $this->bufferPostings = [];
        $this->pdo->rollBack();
    }

    public function clearIndex(): void
    {
        $this->pdo->exec('DELETE FROM posting');
        $this->pdo->exec('DELETE FROM diccionario');
    }

    public function saveTerm(string $termino, int $frecuenciaDocumental, int $frecuenciaTotal): int
    {
        $sentencia = $this->pdo->prepare(
            'INSERT INTO diccionario (termino, frecuencia_documental, frecuencia_total) VALUES (:termino, :df, :total)'
        );
        $sentencia->execute([
            'termino' => $termino,
            'df' => $frecuenciaDocumental,
            'total' => $frecuenciaTotal,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function savePosting(int $idTermino, int $idDocumento, int $frecuencia): void
    {
        $this->bufferPostings[] = [$idTermino, $idDocumento, $frecuencia];

        if (count($this->bufferPostings) >= self::TAMANO_LOTE) {
            $this->flush();
        }
    }

    public function updateDocumentStats(int $idDocumento, int $totalTerminos, string $fragmento): void
    {
        $sentencia = $this->pdo->prepare(
            'UPDATE documentos SET total_terminos = :total, fragmento = :fragmento WHERE id_documento = :id'
        );
        $sentencia->execute([
            'total' => $totalTerminos,
            'fragmento' => $fragmento,
            'id' => $idDocumento,
        ]);
    }

    public function flush(): void
    {
        if ($this->bufferPostings === []) {
            return;
        }

        $placeholders = [];
        $valores = [];
        foreach ($this->bufferPostings as $posting) {
            $placeholders[] = '(?, ?, ?)';
            array_push($valores, ...$posting);
        }

        $sql = 'INSERT INTO posting (id_termino, id_documento, frecuencia) VALUES ' . implode(', ', $placeholders);
        $sentencia = $this->pdo->prepare($sql);
        $sentencia->execute($valores);

        $this->bufferPostings = [];
    }
}
