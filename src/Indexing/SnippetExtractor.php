<?php

declare(strict_types=1);

namespace App\Indexing;

/**
 * Extrae un fragmento corto (por defecto 50 caracteres) del texto original de
 * un documento, colapsando espacios y saltos de linea.
 */
final class SnippetExtractor
{
    public function extract(string $texto, int $length = 50): string
    {
        $colapsado = trim((string) preg_replace('/\s+/u', ' ', $texto));

        return mb_substr($colapsado, 0, $length, 'UTF-8');
    }
}
