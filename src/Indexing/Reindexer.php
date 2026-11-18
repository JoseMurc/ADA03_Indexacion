<?php

declare(strict_types=1);

namespace App\Indexing;

use App\Indexing\Repository\MySqlIndexRepository;
use App\Indexing\Source\MySqlDocumentSource;
use App\Indexing\Text\TokenizerFactory;
use PDO;

/**
 * Ensambla las dependencias reales y ejecuta la reconstruccion total del
 * indice invertido. Reutilizable desde el CLI (scripts/reindex.php) o desde
 * la web (tras una carga de archivos).
 */
final class Reindexer
{
    private const NOMBRE_LOCK = 'buscador_ir_reindex';

    public function __construct(
        private PDO $pdo,
        private string $baseDir,
    ) {
    }

    /**
     * @return array{documentos:int,terminos:int,postings:int}
     */
    public function run(): array
    {
        $sentencia = $this->pdo->prepare('SELECT GET_LOCK(:nombre, 10)');
        $sentencia->execute(['nombre' => self::NOMBRE_LOCK]);
        $obtenido = (bool) $sentencia->fetchColumn();

        try {
            $builder = new IndexBuilder(
                TokenizerFactory::create(),
                new TermFrequencyCalculator(),
                new SnippetExtractor(),
                new MySqlIndexRepository($this->pdo),
            );

            $documentSource = new MySqlDocumentSource($this->pdo, $this->baseDir);

            return $builder->rebuild($documentSource->getAll());
        } finally {
            if ($obtenido) {
                $liberar = $this->pdo->prepare('SELECT RELEASE_LOCK(:nombre)');
                $liberar->execute(['nombre' => self::NOMBRE_LOCK]);
            }
        }
    }
}
