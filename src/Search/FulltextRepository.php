<?php

declare(strict_types=1);

namespace App\Search;

use PDO;

final class FulltextRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function countDocuments(): int
    {
        return (int) $this->pdo
            ->query('SELECT COUNT(*) FROM documentos')
            ->fetchColumn();
    }

    public function search(string $consulta): array
    {
        $sql = '
            SELECT
                id_documento,
                nombre_original,
                fragmento,
                MATCH(contenido) AGAINST(
                    :consulta_score IN NATURAL LANGUAGE MODE
                ) AS relevancia
            FROM documentos
            WHERE MATCH(contenido) AGAINST(
                :consulta_filtro IN NATURAL LANGUAGE MODE
            ) > 0
            ORDER BY relevancia DESC, id_documento ASC
        ';

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            'consulta_score' => $consulta,
            'consulta_filtro' => $consulta,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

