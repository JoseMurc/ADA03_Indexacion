<?php

declare(strict_types=1);

namespace App\Indexing\Text;

/**
 * Unica fuente de configuracion del Tokenizer: tanto el indexador como el
 * buscador deben crear su Tokenizer a traves de esta fabrica, para que los
 * terminos de la consulta coincidan exactamente con los del indice.
 */
final class TokenizerFactory
{
    public static function create(): Tokenizer
    {
        return new Tokenizer(new TextNormalizer(), new StopwordList());
    }
}
