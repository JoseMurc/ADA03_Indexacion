<?php

declare(strict_types=1);

namespace Tests\Indexing;

use App\Indexing\Contracts\DocumentSourceInterface;
use App\Indexing\Dto\IndexedDocument;
use App\Indexing\IndexBuilder;
use App\Indexing\Repository\InMemoryIndexRepository;
use App\Indexing\SnippetExtractor;
use App\Indexing\TermFrequencyCalculator;
use App\Indexing\Text\StopwordList;
use App\Indexing\Text\TextNormalizer;
use App\Indexing\Text\Tokenizer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class IndexBuilderTest extends TestCase
{
    private function crearBuilder(InMemoryIndexRepository $repository, int $longitudMinima = 1): IndexBuilder
    {
        $tokenizer = new Tokenizer(new TextNormalizer(), new StopwordList(enabled: false), $longitudMinima);

        return new IndexBuilder(
            $tokenizer,
            new TermFrequencyCalculator(),
            new SnippetExtractor(),
            $repository,
        );
    }

    /**
     * Caso del material de clase (stopwords desactivadas, sin filtro de longitud).
     */
    public function testCasoDelMaterialDeClase(): void
    {
        $repository = new InMemoryIndexRepository();
        $builder = $this->crearBuilder($repository, longitudMinima: 1);

        $d1 = new IndexedDocument(1, 'd1.txt', 'Now is the time for all good men to come to the aid of their country');
        $d2 = new IndexedDocument(2, 'd2.txt', 'It was a dark and stormy night in the country manor. The time was past midnight');

        $builder->rebuild([$d1, $d2]);

        $terminosPorNombre = [];
        foreach ($repository->getTerms() as $idTermino => $termino) {
            $terminosPorNombre[$termino['termino']] = $termino;
        }

        self::assertSame(2, $terminosPorNombre['country']['frecuencia_documental']);
        self::assertSame(2, $terminosPorNombre['country']['frecuencia_total']);

        self::assertSame(2, $terminosPorNombre['the']['frecuencia_documental']);
        self::assertSame(4, $terminosPorNombre['the']['frecuencia_total']);

        self::assertSame(2, $terminosPorNombre['time']['frecuencia_documental']);
        self::assertSame(2, $terminosPorNombre['time']['frecuencia_total']);

        self::assertSame(1, $terminosPorNombre['to']['frecuencia_documental']);
        self::assertSame(2, $terminosPorNombre['to']['frecuencia_total']);

        self::assertSame(1, $terminosPorNombre['was']['frecuencia_documental']);
        self::assertSame(2, $terminosPorNombre['was']['frecuencia_total']);

        $idTermino = null;
        foreach ($repository->getTerms() as $id => $termino) {
            if ($termino['termino'] === 'the') {
                $idTermino = $id;
                break;
            }
        }

        $postingsDeThe = array_filter(
            $repository->getPostings(),
            static fn (array $p) => $p['id_termino'] === $idTermino,
        );
        $frecuenciasPorDocumento = [];
        foreach ($postingsDeThe as $posting) {
            $frecuenciasPorDocumento[$posting['id_documento']] = $posting['frecuencia'];
        }

        self::assertSame(2, $frecuenciasPorDocumento[1]);
        self::assertSame(2, $frecuenciasPorDocumento[2]);

        // Con el texto exacto del enunciado, D1 y D2 tienen 16 tokens cada uno
        // (comprobado token por token); el enunciado menciona 14 para D2, pero
        // no se alcanza con ningun filtro de longitud razonable sobre este texto.
        $statsDocumentos = $repository->getDocumentStats();
        self::assertSame(16, $statsDocumentos[1]['total_terminos']);
        self::assertSame(16, $statsDocumentos[2]['total_terminos']);
    }

    public function testEsIdempotente(): void
    {
        $repository = new InMemoryIndexRepository();
        $builder = $this->crearBuilder($repository, longitudMinima: 2);

        $documentos = [
            new IndexedDocument(1, 'd1.txt', 'el perro corre en el parque'),
            new IndexedDocument(2, 'd2.txt', 'el gato duerme en el sofa'),
        ];

        $primeraCorrida = $builder->rebuild($documentos);
        $segundaCorrida = $builder->rebuild($documentos);

        self::assertSame($primeraCorrida, $segundaCorrida);
        self::assertCount(count($repository->getTerms()), $repository->getTerms());
    }

    public function testRevierteLaTransaccionSiElOrigenFallaAMitad(): void
    {
        $repository = new InMemoryIndexRepository();
        $builder = $this->crearBuilder($repository, longitudMinima: 2);

        // Deja un estado previo para comprobar que el rollback lo restaura.
        $builder->rebuild([new IndexedDocument(1, 'd1.txt', 'estado previo valido')]);
        $estadoPrevio = $repository->getTerms();

        $origenQueFalla = new class implements DocumentSourceInterface {
            public function getAll(): iterable
            {
                yield new IndexedDocument(2, 'd2.txt', 'un documento valido');
                throw new RuntimeException('fallo simulado del origen de documentos');
            }
        };

        $this->expectException(RuntimeException::class);

        try {
            $builder->rebuild($origenQueFalla->getAll());
        } finally {
            self::assertSame($estadoPrevio, $repository->getTerms());
        }
    }

    public function testInvariantesDeFrecuenciaDocumentalYTotal(): void
    {
        $repository = new InMemoryIndexRepository();
        $builder = $this->crearBuilder($repository, longitudMinima: 2);

        $documentos = [
            new IndexedDocument(1, 'd1.txt', 'perro gato perro'),
            new IndexedDocument(2, 'd2.txt', 'gato pajaro'),
            new IndexedDocument(3, 'd3.txt', 'perro pajaro pajaro pajaro'),
        ];

        $builder->rebuild($documentos);

        foreach ($repository->getTerms() as $idTermino => $termino) {
            $postingsDelTermino = array_filter(
                $repository->getPostings(),
                static fn (array $p) => $p['id_termino'] === $idTermino,
            );

            $sumaFrecuencias = array_sum(array_column($postingsDelTermino, 'frecuencia'));
            self::assertSame($termino['frecuencia_total'], $sumaFrecuencias);
            self::assertSame($termino['frecuencia_documental'], count($postingsDelTermino));
        }
    }

    public function testDocumentoVacioRecibeEstadisticasEnCero(): void
    {
        $repository = new InMemoryIndexRepository();
        $builder = $this->crearBuilder($repository, longitudMinima: 2);

        $builder->rebuild([new IndexedDocument(1, 'vacio.txt', '')]);

        $stats = $repository->getDocumentStats();
        self::assertSame(0, $stats[1]['total_terminos']);
        self::assertSame('', $stats[1]['fragmento']);
    }
}
