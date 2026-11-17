<?php

declare(strict_types=1);

namespace Tests\Indexing;

use App\Indexing\Text\StopwordList;
use App\Indexing\Text\TextNormalizer;
use App\Indexing\Text\Tokenizer;
use PHPUnit\Framework\TestCase;

final class TokenizerTest extends TestCase
{
    public function testFiltraStopwordsEnEspanol(): void
    {
        $tokenizer = new Tokenizer(new TextNormalizer(), new StopwordList());

        $tokens = $tokenizer->tokenize('el perro y el gato corren en la casa');

        self::assertSame(['perro', 'gato', 'corren', 'casa'], $tokens);
    }

    public function testConservaDuplicadosYOrdenOriginal(): void
    {
        $tokenizer = new Tokenizer(new TextNormalizer(), new StopwordList());

        $tokens = $tokenizer->tokenize('casa casa perro casa');

        self::assertSame(['casa', 'casa', 'perro', 'casa'], $tokens);
    }

    public function testDescartaTokensDemasiadoCortos(): void
    {
        $tokenizer = new Tokenizer(new TextNormalizer(), new StopwordList(enabled: false));

        $tokens = $tokenizer->tokenize('a oso de uno y');

        self::assertSame(['oso', 'de', 'uno'], $tokens);
    }

    public function testPuedeDesactivarStopwords(): void
    {
        $tokenizer = new Tokenizer(new TextNormalizer(), new StopwordList(enabled: false), longitudMinima: 1);

        $tokens = $tokenizer->tokenize('el gato y el perro');

        self::assertSame(['el', 'gato', 'y', 'el', 'perro'], $tokens);
    }
}
