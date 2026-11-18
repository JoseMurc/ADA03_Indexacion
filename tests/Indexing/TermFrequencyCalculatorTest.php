<?php

declare(strict_types=1);

namespace Tests\Indexing;

use App\Indexing\TermFrequencyCalculator;
use PHPUnit\Framework\TestCase;

final class TermFrequencyCalculatorTest extends TestCase
{
    public function testCuentaFrecuenciasPorToken(): void
    {
        $calculadora = new TermFrequencyCalculator();

        $frecuencias = $calculadora->calculate(['casa', 'perro', 'casa', 'gato', 'casa']);

        self::assertSame(['casa' => 3, 'perro' => 1, 'gato' => 1], $frecuencias);
    }

    public function testTokenNumericoNoRompeElTipado(): void
    {
        $calculadora = new TermFrequencyCalculator();

        $frecuencias = $calculadora->calculate(['2024', '2024', 'informe']);

        // PHP convierte la clave "2024" a int de forma automatica; lo relevante
        // es que el calculo no rompa bajo strict_types y el valor sea correcto
        // al consultarlo con la clave original (string).
        self::assertSame(2, $frecuencias['2024']);
        self::assertSame(1, $frecuencias['informe']);
    }

    public function testListaVaciaDevuelveMapaVacio(): void
    {
        $calculadora = new TermFrequencyCalculator();

        self::assertSame([], $calculadora->calculate([]));
    }
}
