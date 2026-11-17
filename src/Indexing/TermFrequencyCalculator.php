<?php

declare(strict_types=1);

namespace App\Indexing;

/**
 * Calcula la frecuencia (tf crudo) de cada token dentro de una lista de tokens.
 */
final class TermFrequencyCalculator
{
    /**
     * @param string[] $tokens
     * @return array<string,int> Mapa termino => frecuencia.
     */
    public function calculate(array $tokens): array
    {
        $frecuencias = [];
        foreach ($tokens as $token) {
            $token = (string) $token;
            $frecuencias[$token] = ($frecuencias[$token] ?? 0) + 1;
        }

        /** @var array<string,int> $resultado */
        $resultado = [];
        foreach ($frecuencias as $termino => $frecuencia) {
            $resultado[(string) $termino] = $frecuencia;
        }

        return $resultado;
    }
}
