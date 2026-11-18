<?php

declare(strict_types=1);

namespace Tests\Indexing;

use App\Indexing\Source\MySqlDocumentSource;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class MySqlDocumentSourceTest extends TestCase
{
    private PDO $pdo;
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(
            'CREATE TABLE documentos (
                id_documento INTEGER PRIMARY KEY,
                nombre_original TEXT NOT NULL,
                ruta_archivo TEXT NOT NULL
            )'
        );

        $this->tmpDir = sys_get_temp_dir() . '/m2_test_' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmpDir . '/*') ?: [] as $archivo) {
            unlink($archivo);
        }
        rmdir($this->tmpDir);
    }

    public function testLeeArchivoUtf8SinBom(): void
    {
        $ruta = $this->tmpDir . '/utf8.txt';
        file_put_contents($ruta, 'contenido en español con ñ');
        $this->insertarDocumento(1, 'utf8.txt', 'utf8.txt');

        $documentos = iterator_to_array(
            (new MySqlDocumentSource($this->pdo, $this->tmpDir))->getAll()
        );

        self::assertSame('contenido en español con ñ', $documentos[0]->contenido);
    }

    public function testLeeArchivoUtf8ConBom(): void
    {
        $ruta = $this->tmpDir . '/bom.txt';
        file_put_contents($ruta, "\xEF\xBB\xBF" . 'contenido con bom');
        $this->insertarDocumento(1, 'bom.txt', 'bom.txt');

        $documentos = iterator_to_array(
            (new MySqlDocumentSource($this->pdo, $this->tmpDir))->getAll()
        );

        self::assertSame('contenido con bom', $documentos[0]->contenido);
    }

    public function testLeeArchivoIso88591YLoConvierteAUtf8(): void
    {
        $ruta = $this->tmpDir . '/iso.txt';
        file_put_contents($ruta, mb_convert_encoding('año café', 'ISO-8859-1', 'UTF-8'));
        $this->insertarDocumento(1, 'iso.txt', 'iso.txt');

        $documentos = iterator_to_array(
            (new MySqlDocumentSource($this->pdo, $this->tmpDir))->getAll()
        );

        self::assertSame('año café', $documentos[0]->contenido);
    }

    public function testResuelveRutaAbsoluta(): void
    {
        $ruta = $this->tmpDir . '/absoluto.txt';
        file_put_contents($ruta, 'contenido absoluto');
        $this->insertarDocumento(1, 'absoluto.txt', $ruta);

        $documentos = iterator_to_array(
            (new MySqlDocumentSource($this->pdo, '/otra/base/que/no/existe'))->getAll()
        );

        self::assertSame('contenido absoluto', $documentos[0]->contenido);
    }

    public function testResuelveRutaRelativaConBaseDir(): void
    {
        $ruta = $this->tmpDir . '/relativo.txt';
        file_put_contents($ruta, 'contenido relativo');
        $this->insertarDocumento(1, 'relativo.txt', 'relativo.txt');

        $documentos = iterator_to_array(
            (new MySqlDocumentSource($this->pdo, $this->tmpDir))->getAll()
        );

        self::assertSame('contenido relativo', $documentos[0]->contenido);
    }

    public function testArchivoInexistenteLanzaExcepcion(): void
    {
        $this->insertarDocumento(1, 'no-existe.txt', 'no-existe.txt');

        $this->expectException(RuntimeException::class);

        iterator_to_array(
            (new MySqlDocumentSource($this->pdo, $this->tmpDir))->getAll()
        );
    }

    private function insertarDocumento(int $id, string $nombreOriginal, string $rutaArchivo): void
    {
        $sentencia = $this->pdo->prepare(
            'INSERT INTO documentos (id_documento, nombre_original, ruta_archivo) VALUES (?, ?, ?)'
        );
        $sentencia->execute([$id, $nombreOriginal, $rutaArchivo]);
    }
}
