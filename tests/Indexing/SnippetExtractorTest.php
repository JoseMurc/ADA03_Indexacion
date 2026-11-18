<?php

declare(strict_types=1);

namespace Tests\Indexing;

use App\Indexing\SnippetExtractor;
use PHPUnit\Framework\TestCase;

final class SnippetExtractorTest extends TestCase
{
    public function testNuncaExcedeLaLongitudPorDefecto(): void
    {
        $extractor = new SnippetExtractor();
        $texto = str_repeat('palabra ', 20);

        $fragmento = $extractor->extract($texto);

        self::assertLessThanOrEqual(50, mb_strlen($fragmento, 'UTF-8'));
    }

    public function testColapsaSaltosDeLineaYEspacios(): void
    {
        $extractor = new SnippetExtractor();

        $fragmento = $extractor->extract("linea uno\nlinea   dos\t\tlinea tres");

        self::assertSame('linea uno linea dos linea tres', $fragmento);
    }

    public function testTextoCortoQuedaIntacto(): void
    {
        $extractor = new SnippetExtractor();

        $fragmento = $extractor->extract('texto corto');

        self::assertSame('texto corto', $fragmento);
    }

    public function testRespetaLongitudPersonalizada(): void
    {
        $extractor = new SnippetExtractor();

        $fragmento = $extractor->extract('abcdefghij', 5);

        self::assertSame('abcde', $fragmento);
    }
}
