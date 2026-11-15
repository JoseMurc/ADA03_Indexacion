<?php

declare(strict_types=1);

namespace Tests\Indexing;

use App\Indexing\IndexBuilder;
use App\Indexing\Repository\MySqlIndexRepository;
use App\Indexing\SnippetExtractor;
use App\Indexing\Source\MySqlDocumentSource;
use App\Indexing\TermFrequencyCalculator;
use App\Indexing\Text\StopwordList;
use App\Indexing\Text\TextNormalizer;
use App\Indexing\Text\Tokenizer;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Prueba de integracion contra una base de datos MySQL real con el schema
 * de M1 (`buscador_ir`). Se omite si no estan definidas las variables de
 * entorno de conexion.
 *
 * @group integration
 */
#[Group('integration')]
final class MySqlIndexRepositoryIntegrationTest extends TestCase
{
    private ?PDO $pdo = null;

    protected function setUp(): void
    {
        $host = getenv('DB_HOST');
        $dbName = getenv('DB_NAME');
        $user = getenv('DB_USER');
        $pass = getenv('DB_PASS');

        if ($host === false || $dbName === false || $user === false) {
            self::markTestSkipped('Variables de entorno de BD no definidas (DB_HOST, DB_NAME, DB_USER).');
        }

        $this->pdo = new PDO(
            sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $host, $dbName),
            $user,
            $pass !== false ? $pass : '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
    }

    protected function tearDown(): void
    {
        if ($this->pdo !== null) {
            $this->pdo->exec('DELETE FROM posting');
            $this->pdo->exec('DELETE FROM diccionario');
            $this->pdo->exec('DELETE FROM documentos');
        }
    }

    public function testReindexaContraElSchemaRealYMantieneInvariantes(): void
    {
        $pdo = $this->pdo;

        $baseDir = sys_get_temp_dir() . '/m2_integration_' . bin2hex(random_bytes(4));
        mkdir($baseDir);

        $archivo1 = $baseDir . '/doc1.txt';
        $archivo2 = $baseDir . '/doc2.txt';
        file_put_contents($archivo1, 'el perro corre y el gato duerme');
        file_put_contents($archivo2, 'el gato juega con el perro');

        $insertar = $pdo->prepare(
            'INSERT INTO documentos (nombre_original, nombre_guardado, ruta_archivo, hash_sha256) VALUES (?, ?, ?, ?)'
        );
        $insertar->execute(['doc1.txt', 'doc1.txt', 'doc1.txt', hash('sha256', 'doc1')]);
        $insertar->execute(['doc2.txt', 'doc2.txt', 'doc2.txt', hash('sha256', 'doc2')]);

        $tokenizer = new Tokenizer(new TextNormalizer(), new StopwordList());
        $builder = new IndexBuilder(
            $tokenizer,
            new TermFrequencyCalculator(),
            new SnippetExtractor(),
            new MySqlIndexRepository($pdo),
        );

        $builder->rebuild((new MySqlDocumentSource($pdo, $baseDir))->getAll());

        $terminos = $pdo->query('SELECT id_termino, frecuencia_documental, frecuencia_total FROM diccionario')
            ->fetchAll(PDO::FETCH_ASSOC);

        foreach ($terminos as $termino) {
            $sumaFrecuencias = (int) $pdo->query(
                sprintf('SELECT SUM(frecuencia) FROM posting WHERE id_termino = %d', $termino['id_termino'])
            )->fetchColumn();
            $cantidadDocumentos = (int) $pdo->query(
                sprintf('SELECT COUNT(*) FROM posting WHERE id_termino = %d', $termino['id_termino'])
            )->fetchColumn();

            self::assertSame((int) $termino['frecuencia_total'], $sumaFrecuencias);
            self::assertSame((int) $termino['frecuencia_documental'], $cantidadDocumentos);
        }

        $documentos = $pdo->query('SELECT total_terminos, fragmento FROM documentos')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($documentos as $documento) {
            self::assertGreaterThan(0, $documento['total_terminos']);
            self::assertNotSame('', $documento['fragmento']);
        }

        unlink($archivo1);
        unlink($archivo2);
        rmdir($baseDir);
    }
}
