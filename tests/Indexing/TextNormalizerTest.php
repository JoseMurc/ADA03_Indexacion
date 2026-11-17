<?php

declare(strict_types=1);

namespace Tests\Indexing;

use App\Indexing\Text\TextNormalizer;
use PHPUnit\Framework\TestCase;

final class TextNormalizerTest extends TestCase
{
    private TextNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new TextNormalizer();
    }

    /**
     * @dataProvider provideVariantesDeAnio
     */
    public function testUnificaVariantesConYSinEnie(string $entrada): void
    {
        self::assertSame('ano', $this->normalizer->normalize($entrada));
    }

    public static function provideVariantesDeAnio(): array
    {
        return [
            ['Año'],
            ['ano'],
            ['AÑO'],
        ];
    }

    public function testQuitaAcentosComunes(): void
    {
        self::assertSame(
            'el camion rapido paso por alli',
            $this->normalizer->normalize('El camión rápido pasó por allí'),
        );
    }

    public function testReemplazaPuntuacionPorEspacios(): void
    {
        self::assertSame('hola mundo', $this->normalizer->normalize('¡Hola, mundo!'));
    }

    public function testColapsaSaltosDeLineaYEspacios(): void
    {
        self::assertSame('linea uno linea dos', $this->normalizer->normalize("linea   uno\n\nlinea\tdos"));
    }

    public function testQuitaEmojisYSimbolosNoAlfanumericos(): void
    {
        self::assertSame('hola mundo', $this->normalizer->normalize('hola 😀 mundo 🚀'));
    }
}
