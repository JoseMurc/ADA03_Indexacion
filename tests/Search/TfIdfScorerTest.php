<?php

declare(strict_types=1);

namespace Tests\Search;

use App\Search\TfIdfScorer;
use PHPUnit\Framework\TestCase;

final class TfIdfScorerTest extends TestCase
{
    /**
     * Ejemplo del material de clase con 3 documentos:
     * D1 "It is going to rain today", D2 "Today I am not going outside",
     * D3 "I am going to watch the season premiere".
     * "going" aparece en los 3 documentos (df=3, N=3); "to" en D1 y D3 (df=2, N=3).
     */
    public function testIdfClasicoDaCeroParaUnTerminoEnTodosLosDocumentos(): void
    {
        $scorer = new TfIdfScorer(idfSuavizado: false);

        self::assertSame(0.0, $scorer->idf(3, 3));
    }

    public function testIdfClasicoDeTerminoEnDosDeTresDocumentos(): void
    {
        $scorer = new TfIdfScorer(idfSuavizado: false);

        self::assertEqualsWithDelta(0.405, $scorer->idf(3, 2), 0.001);
    }

    public function testIdfSuavizadoEsPositivoCuandoDfIgualaAN(): void
    {
        $scorer = new TfIdfScorer(idfSuavizado: true);

        self::assertGreaterThan(0.0, $scorer->idf(3, 3));
    }

    public function testConNIgualAUnoYIdfSuavizadoSeEncuentraElDocumento(): void
    {
        $scorer = new TfIdfScorer(idfSuavizado: true);

        $resultados = $scorer->score(
            n: 1,
            queryTf: ['solo' => 1],
            dfPorTermino: ['solo' => 1],
            postingsPorDoc: [1 => ['solo' => 1]],
            vectoresPorDoc: [1 => [['tf' => 1, 'df' => 1]]],
        );

        self::assertEqualsWithDelta(1.0, $resultados[1]['coseno'], 0.0001);
    }

    public function testConsultaDeUnTerminoNoEmpataEntreDocumentoDominadoYDocumentoDiverso(): void
    {
        $scorer = new TfIdfScorer();

        // Doc 1: solo contiene "x" (dominado por el termino buscado).
        // Doc 2: contiene "x" una vez entre otros terminos distintos.
        $resultados = $scorer->score(
            n: 2,
            queryTf: ['x' => 1],
            dfPorTermino: ['x' => 2],
            postingsPorDoc: [
                1 => ['x' => 10],
                2 => ['x' => 1],
            ],
            vectoresPorDoc: [
                1 => [['tf' => 10, 'df' => 2]],
                2 => [
                    ['tf' => 1, 'df' => 2],
                    ['tf' => 1, 'df' => 1],
                    ['tf' => 1, 'df' => 1],
                    ['tf' => 1, 'df' => 1],
                ],
            ],
        );

        self::assertGreaterThan($resultados[2]['coseno'], $resultados[1]['coseno']);
        self::assertNotSame($resultados[1]['coseno'], $resultados[2]['coseno']);

        foreach ($resultados as $resultado) {
            self::assertGreaterThan(0.0, $resultado['coseno']);
            self::assertLessThanOrEqual(1.0, $resultado['coseno']);
        }
    }

    public function testConsultaIdenticaAlDocumentoDaCosenoCercaDeUno(): void
    {
        $scorer = new TfIdfScorer();

        $resultados = $scorer->score(
            n: 5,
            queryTf: ['a' => 2, 'b' => 1],
            dfPorTermino: ['a' => 3, 'b' => 2],
            postingsPorDoc: [1 => ['a' => 2, 'b' => 1]],
            vectoresPorDoc: [1 => [['tf' => 2, 'df' => 3], ['tf' => 1, 'df' => 2]]],
        );

        self::assertEqualsWithDelta(1.0, $resultados[1]['coseno'], 0.0001);
    }

    public function testTerminosDesconocidosDevuelveResultadosVacios(): void
    {
        $scorer = new TfIdfScorer();

        $resultados = $scorer->score(
            n: 3,
            queryTf: ['fantasma' => 1],
            dfPorTermino: [],
            postingsPorDoc: [],
            vectoresPorDoc: [],
        );

        self::assertSame([], $resultados);
    }
}
