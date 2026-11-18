<?php

declare(strict_types=1);

namespace Tests\Storage;

use App\Storage\FileLocator;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class FileLocatorTest extends TestCase
{
    private string $baseDir;
    private FileLocator $fileLocator;

    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir() . '/m1_filelocator_' . bin2hex(random_bytes(4));
        mkdir($this->baseDir);
        file_put_contents($this->baseDir . '/documento.txt', 'contenido');

        $this->fileLocator = new FileLocator($this->baseDir);
    }

    protected function tearDown(): void
    {
        @unlink($this->baseDir . '/documento.txt');
        @rmdir($this->baseDir);
    }

    public function testResuelveUnaRutaRelativaValidaDentroDelBaseDir(): void
    {
        $resuelta = $this->fileLocator->resolve('documento.txt');

        self::assertSame(realpath($this->baseDir . '/documento.txt'), $resuelta);
    }

    public function testRechazaRutaDeTraversal(): void
    {
        $this->expectException(RuntimeException::class);

        $this->fileLocator->resolve('../../etc/passwd');
    }

    public function testRechazaRutaAbsoluta(): void
    {
        $this->expectException(RuntimeException::class);

        $this->fileLocator->resolve($this->baseDir . '/documento.txt');
    }

    public function testRechazaByteNulo(): void
    {
        $this->expectException(RuntimeException::class);

        $this->fileLocator->resolve("documento.txt\0.jpg");
    }
}
