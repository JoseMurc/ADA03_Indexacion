<?php

declare(strict_types=1);

namespace App\Indexing\Text;

/**
 * Convierte texto libre en una lista de tokens normalizados, sin stopwords.
 * API publica estable: reutilizada por el Modulo 3 para procesar consultas.
 */
final class Tokenizer
{
    public function __construct(
        private TextNormalizer $normalizer,
        private StopwordList $stopwords,
        private int $longitudMinima = 2,
        private int $longitudMaxima = 100,
    ) {
    }

    /**
     * @return string[] Tokens en el orden original, con duplicados conservados.
     */
    public function tokenize(string $texto): array
    {
        $normalizado = $this->normalizer->normalize($texto);
        if ($normalizado === '') {
            return [];
        }

        $tokens = [];
        foreach (explode(' ', $normalizado) as $token) {
            $longitud = mb_strlen($token, 'UTF-8');
            if ($longitud < $this->longitudMinima || $longitud > $this->longitudMaxima) {
                continue;
            }
            if ($this->stopwords->isStopword($token)) {
                continue;
            }
            $tokens[] = $token;
        }

        return $tokens;
    }
}
