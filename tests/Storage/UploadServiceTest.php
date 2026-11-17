<?php

declare(strict_types=1);

namespace Tests\Storage;

use App\Storage\DocumentRepository;
use App\Storage\FileLocator;
use App\Storage\UploadService;
use PDO;
use PHPUnit\Framework\TestCase;

final class UploadServiceTest extends TestCase
{
    private string $uploadsDir;
    private string $tmpDir;
    private PDO $pdo;
    private DocumentRepository $documentRepository;
    private FileLocator $fileLocator;

    protected function setUp(): void
    {
        $this->uploadsDir = sys_get_temp_dir() . '/m1_uploads_' . bin2hex(random_bytes(4));
        $this->tmpDir = sys_get_temp_dir() . '/m1_tmp_' . bin2hex(random_bytes(4));
        mkdir($this->uploadsDir);
        mkdir($this->tmpDir);

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->crearTablaDocumentos();

        $this->documentRepository = new DocumentRepository($this->pdo);
        $this->fileLocator = new FileLocator($this->uploadsDir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->uploadsDir . '/*') ?: [] as $archivo) {
            unlink($archivo);
        }
        rmdir($this->uploadsDir);
        foreach (glob($this->tmpDir . '/*') ?: [] as $archivo) {
            unlink($archivo);
        }
        rmdir($this->tmpDir);
    }

    private function crearTablaDocumentos(): void
    {
        $this->pdo->exec('CREATE TABLE documentos (
            id_documento INTEGER PRIMARY KEY,
            nombre_original TEXT NOT NULL,
            nombre_guardado TEXT NOT NULL UNIQUE,
            ruta_archivo TEXT NOT NULL,
            fragmento TEXT NOT NULL DEFAULT "",
            hash_sha256 TEXT NOT NULL UNIQUE,
            total_terminos INTEGER NOT NULL DEFAULT 0
        )');
    }

    private function crearArchivoTemporal(string $contenido): string
    {
        $ruta = $this->tmpDir . '/' . bin2hex(random_bytes(4)) . '.tmp';
        file_put_contents($ruta, $contenido);

        return $ruta;
    }

    /**
     * @param list<array{name:string,tmp_name:string,size:int,error?:int}> $archivos
     */
    private function filesDesde(array $archivos): array
    {
        $files = ['name' => [], 'type' => [], 'tmp_name' => [], 'error' => [], 'size' => []];
        foreach ($archivos as $archivo) {
            $files['name'][] = $archivo['name'];
            $files['type'][] = 'text/plain';
            $files['tmp_name'][] = $archivo['tmp_name'];
            $files['error'][] = $archivo['error'] ?? UPLOAD_ERR_OK;
            $files['size'][] = $archivo['size'];
        }

        return $files;
    }

    private function crearServicio(\Closure $reindex): UploadService
    {
        return new UploadService(
            $this->documentRepository,
            $this->fileLocator,
            $reindex,
            static fn (string $tmp, string $destino): bool => copy($tmp, $destino),
        );
    }

    public function testMismoContenidoConOtroNombreEsDuplicado(): void
    {
        $contador = 0;
        $servicio = $this->crearServicio(static function () use (&$contador): array {
            $contador++;
            return ['documentos' => 1, 'terminos' => 0, 'postings' => 0];
        });

        $tmp1 = $this->crearArchivoTemporal('mismo contenido');
        $tmp2 = $this->crearArchivoTemporal('mismo contenido');

        $reporte1 = $servicio->handle($this->filesDesde([
            ['name' => 'uno.txt', 'tmp_name' => $tmp1, 'size' => filesize($tmp1)],
        ]));
        self::assertSame(['uno.txt'], $reporte1->stored);

        $reporte2 = $servicio->handle($this->filesDesde([
            ['name' => 'otro-nombre.txt', 'tmp_name' => $tmp2, 'size' => filesize($tmp2)],
        ]));

        self::assertSame([], $reporte2->stored);
        self::assertArrayHasKey('otro-nombre.txt', $reporte2->duplicates);
        self::assertSame('uno.txt', $reporte2->duplicates['otro-nombre.txt']);
        self::assertSame(1, $contador);
    }

    public function testMismoNombreConOtroContenidoSeGuarda(): void
    {
        $servicio = $this->crearServicio(static fn (): array => ['documentos' => 1, 'terminos' => 0, 'postings' => 0]);

        $tmp1 = $this->crearArchivoTemporal('contenido uno');
        $tmp2 = $this->crearArchivoTemporal('contenido completamente distinto');

        $servicio->handle($this->filesDesde([
            ['name' => 'doc.txt', 'tmp_name' => $tmp1, 'size' => filesize($tmp1)],
        ]));
        $reporte2 = $servicio->handle($this->filesDesde([
            ['name' => 'doc.txt', 'tmp_name' => $tmp2, 'size' => filesize($tmp2)],
        ]));

        self::assertSame(['doc.txt'], $reporte2->stored);
        self::assertSame(2, $this->documentRepository->count());
    }

    public function testArchivoPdfEsRechazado(): void
    {
        $servicio = $this->crearServicio(static fn (): array => ['documentos' => 0, 'terminos' => 0, 'postings' => 0]);
        $tmp = $this->crearArchivoTemporal('contenido');

        $reporte = $servicio->handle($this->filesDesde([
            ['name' => 'malware.pdf', 'tmp_name' => $tmp, 'size' => filesize($tmp)],
        ]));

        self::assertSame([], $reporte->stored);
        self::assertArrayHasKey('malware.pdf', $reporte->rejected);
    }

    public function testArchivoVacioEsRechazado(): void
    {
        $servicio = $this->crearServicio(static fn (): array => ['documentos' => 0, 'terminos' => 0, 'postings' => 0]);
        $tmp = $this->crearArchivoTemporal('');

        $reporte = $servicio->handle($this->filesDesde([
            ['name' => 'vacio.txt', 'tmp_name' => $tmp, 'size' => 0],
        ]));

        self::assertSame([], $reporte->stored);
        self::assertArrayHasKey('vacio.txt', $reporte->rejected);
    }

    public function testArchivoConByteNuloEsRechazado(): void
    {
        $servicio = $this->crearServicio(static fn (): array => ['documentos' => 0, 'terminos' => 0, 'postings' => 0]);
        $tmp = $this->crearArchivoTemporal("contenido\0binario");

        $reporte = $servicio->handle($this->filesDesde([
            ['name' => 'raro.txt', 'tmp_name' => $tmp, 'size' => filesize($tmp)],
        ]));

        self::assertSame([], $reporte->stored);
        self::assertArrayHasKey('raro.txt', $reporte->rejected);
    }

    public function testSiElInsertFallaSeBorraElArchivoFisico(): void
    {
        // Rompemos la tabla para forzar que el INSERT de DocumentRepository falle
        // con un error generico (no de unicidad), y verificamos que no quede basura en disco.
        $this->pdo->exec('DROP TABLE documentos');
        $this->pdo->exec('CREATE TABLE documentos (
            id_documento INTEGER PRIMARY KEY,
            nombre_original TEXT NOT NULL,
            ruta_archivo TEXT NOT NULL,
            hash_sha256 TEXT NOT NULL UNIQUE
        )');

        $servicio = $this->crearServicio(static fn (): array => ['documentos' => 0, 'terminos' => 0, 'postings' => 0]);
        $tmp = $this->crearArchivoTemporal('contenido valido');

        $reporte = $servicio->handle($this->filesDesde([
            ['name' => 'bueno.txt', 'tmp_name' => $tmp, 'size' => filesize($tmp)],
        ]));

        self::assertSame([], $reporte->stored);
        self::assertArrayHasKey('bueno.txt', $reporte->rejected);
        self::assertSame([], glob($this->uploadsDir . '/*'));
    }

    public function testReindexSeLlamaUnaVezPorLoteYNingunaSiNoHuboGuardados(): void
    {
        $contador = 0;
        $servicio = $this->crearServicio(static function () use (&$contador): array {
            $contador++;
            return ['documentos' => 2, 'terminos' => 0, 'postings' => 0];
        });

        $tmp1 = $this->crearArchivoTemporal('archivo uno');
        $tmp2 = $this->crearArchivoTemporal('archivo dos');

        $servicio->handle($this->filesDesde([
            ['name' => 'uno.txt', 'tmp_name' => $tmp1, 'size' => filesize($tmp1)],
            ['name' => 'dos.txt', 'tmp_name' => $tmp2, 'size' => filesize($tmp2)],
        ]));

        self::assertSame(1, $contador);

        $reporteSinArchivos = $servicio->handle($this->filesDesde([]));
        self::assertSame(1, $contador);
        self::assertSame([], $reporteSinArchivos->stored);
    }
}
