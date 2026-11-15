<?php

declare(strict_types=1);

namespace App\Indexing\Text;

/**
 * Normaliza texto a una forma canonica para indizacion y consulta:
 * minusculas, sin diacriticos (incluida la ñ, por la collation utf8mb4_unicode_ci
 * que iguala ñ=n) y sin puntuacion.
 */
final class TextNormalizer
{
    /** @var array<string,string> Fallback de transliteracion si ext-intl no esta disponible. */
    private const TRANSLITERACION = [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
        'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
        'â' => 'a', 'ê' => 'e', 'î' => 'i', 'ô' => 'o', 'û' => 'u',
        'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u',
        'ã' => 'a', 'õ' => 'o', 'ñ' => 'n', 'ç' => 'c',
    ];

    public function normalize(string $texto): string
    {
        $texto = mb_strtolower($texto, 'UTF-8');
        $texto = $this->quitarDiacriticos($texto);
        $texto = (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $texto);
        $texto = (string) preg_replace('/\s+/u', ' ', $texto);

        return trim($texto);
    }

    private function quitarDiacriticos(string $texto): string
    {
        if (class_exists(\Normalizer::class)) {
            $descompuesto = \Normalizer::normalize($texto, \Normalizer::FORM_D);
            if ($descompuesto !== false) {
                return (string) preg_replace('/\p{Mn}+/u', '', $descompuesto);
            }
        }

        return strtr($texto, self::TRANSLITERACION);
    }
}
