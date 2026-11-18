<?php

declare(strict_types=1);

namespace Tests\EndToEnd;

use App\Search\PostingsRepository;
use App\Search\SearchService;
use App\Search\TfIdfScorer;
use App\Storage\DocumentRepository;
use App\Storage\FileLocator;
use App\Storage\UploadService;
use App\Support\Database;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Prueba de extremo a extremo contra MySQL real: carga sql/schema.sql, sube
 * las muestras con UploadService, reindexa y busca, verificando las
 * invariantes del indice invertido (M2) y el orden de los resultados (M3).
 *
 * Se omite si no estan definidas las variables de entorno de conexion.
 *
 * @group integration
 */
#[Group('integration')]
final class PipelineIntegrationTest extends TestCase
{
    private ?PDO $pdo = null;
    private string $uploadsDir;

    protected function setUp(): void
    {
        $host = getenv('DB_HOST');
        $dbName = getenv('DB_NAME');
        $user = getenv('DB_USER');

        if ($host === false || $dbName === false || $user === false) {
            self::markTestSkipped('Variables de entorno de BD no definidas (DB_HOST, DB_NAME, DB_USER).');
        }

        $pdoSinSchema = new PDO(
            sprintf('mysql:host=%s;charset=utf8mb4', $host),
            $user,
            getenv('DB_PASS') !== false ? getenv('DB_PASS') : '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
        foreach (array_filter(array_map('trim', explode(';', (string) file_get_contents(__DIR__ . '/../../sql/schema.sql')))) as $sentencia) {
            $pdoSinSchema->exec($sentencia);
        }

        $this->pdo = Database::fromEnv();
        $this->pdo->exec('DELETE FROM posting');
        $this->pdo->exec('DELETE FROM diccionario');
        $this->pdo->exec('DELETE FROM documentos');

        $this->uploadsDir = sys_get_temp_dir() . '/m_e2e_' . bin2hex(random_bytes(4));
        mkdir($this->uploadsDir);
    }

    protected function tearDown(): void
    {
        if ($this->pdo !== null) {
            $this->pdo->exec('DELETE FROM posting');
            $this->pdo->exec('DELETE FROM diccionario');
            $this->pdo->exec('DELETE FROM documentos');
        }

        if (isset($this->uploadsDir) && is_dir($this->uploadsDir)) {
            foreach (glob($this->uploadsDir . '/*') ?: [] as $archivo) {
                unlink($archivo);
            }
            rmdir($this->uploadsDir);
        }
    }

    public function testSubirMuestrasReindexarYBuscarMantieneLasInvariantesDeM2(): void
    {
        $pdo = $this->pdo;
        $fileLocator = new FileLocator($this->uploadsDir);
        $documentRepository = new DocumentRepository($pdo);

        $uploadService = new UploadService(
            $documentRepository,
            $fileLocator,
            static fn (): array => (new \App\Indexing\Reindexer($pdo, $fileLocator->baseDir()))->run(),
            static fn (string $tmp, string $destino): bool => copy($tmp, $destino),
        );

        $muestras = glob(__DIR__ . '/../../samples/*.txt') ?: [];
        self::assertNotEmpty($muestras, 'No se encontraron muestras en samples/.');

        $files = ['name' => [], 'type' => [], 'tmp_name' => [], 'error' => [], 'size' => []];
        foreach ($muestras as $ruta) {
            $files['name'][] = basename($ruta);
            $files['type'][] = 'text/plain';
            $files['tmp_name'][] = $ruta;
            $files['error'][] = UPLOAD_ERR_OK;
            $files['size'][] = filesize($ruta);
        }

        $reporte = $uploadService->handle($files);

        self::assertNull($reporte->reindexError);
        self::assertCount(count($muestras), $reporte->stored);
        self::assertNotNull($reporte->reindexStats);

        // Invariantes de M2: la suma de posting.frecuencia por termino debe
        // igualar frecuencia_total, y el conteo de filas de posting debe
        // igualar frecuencia_documental.
        $terminos = $pdo->query('SELECT id_termino, frecuencia_documental, frecuencia_total FROM diccionario')
            ->fetchAll();

        self::assertNotEmpty($terminos);
        foreach ($terminos as $termino) {
            $sumaFrecuencias = (int) $pdo->query(
                sprintf('SELECT COALESCE(SUM(frecuencia), 0) FROM posting WHERE id_termino = %d', $termino['id_termino'])
            )->fetchColumn();
            $cantidadDocumentos = (int) $pdo->query(
                sprintf('SELECT COUNT(*) FROM posting WHERE id_termino = %d', $termino['id_termino'])
            )->fetchColumn();

            self::assertSame((int) $termino['frecuencia_total'], $sumaFrecuencias);
            self::assertSame((int) $termino['frecuencia_documental'], $cantidadDocumentos);
        }

        // Busqueda: "cafe" (sin tilde) debe encontrar el documento de cafe.txt
        // como resultado mas relevante.
        $searchService = new SearchService(new PostingsRepository($pdo), new TfIdfScorer());
        $resultado = $searchService->search('cafe');

        self::assertNotEmpty($resultado->resultados);
        self::assertSame('cafe.txt', $resultado->resultados[0]->archivo);

        for ($i = 1; $i < count($resultado->resultados); $i++) {
            self::assertGreaterThanOrEqual(
                $resultado->resultados[$i]->coseno,
                $resultado->resultados[$i - 1]->coseno,
            );
        }
    }
}
