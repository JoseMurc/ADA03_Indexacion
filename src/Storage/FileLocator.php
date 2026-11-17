<?php

declare(strict_types=1);

namespace App\Storage;

use RuntimeException;

/**
 * Resuelve rutas relativas de documentos dentro de un directorio base,
 * evitando salidas del directorio (path traversal).
 */
final class FileLocator
{
    private string $baseDir;

    public function __construct(string $baseDir)
    {
        $real = realpath($baseDir);
        if ($real === false) {
            throw new RuntimeException(sprintf('El directorio base "%s" no existe.', $baseDir));
        }

        $this->baseDir = $real;
    }

    public function baseDir(): string
    {
        return $this->baseDir;
    }

    public function resolve(string $rutaRelativa): string
    {
        if (str_contains($rutaRelativa, "\0")) {
            throw new RuntimeException('Ruta invalida: contiene un byte nulo.');
        }

        $esAbsoluta = str_starts_with($rutaRelativa, '/')
            || str_starts_with($rutaRelativa, '\\')
            || preg_match('#^[A-Za-z]:[\\\\/]#', $rutaRelativa) === 1;

        if ($esAbsoluta) {
            throw new RuntimeException('Ruta invalida: debe ser relativa al directorio base.');
        }

        if (str_contains($rutaRelativa, '..')) {
            throw new RuntimeException('Ruta invalida: no se permite salir del directorio base.');
        }

        $rutaCompleta = $this->baseDir . DIRECTORY_SEPARATOR . $rutaRelativa;
        $real = realpath($rutaCompleta);

        if ($real === false) {
            throw new RuntimeException(sprintf('El archivo "%s" no existe.', $rutaRelativa));
        }

        $prefijo = $this->baseDir . DIRECTORY_SEPARATOR;
        if (!str_starts_with($real, $prefijo)) {
            throw new RuntimeException('Ruta invalida: queda fuera del directorio base.');
        }

        return $real;
    }
}
