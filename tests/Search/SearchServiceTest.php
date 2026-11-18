<?php

declare(strict_types=1);

namespace Tests\Search;

use App\Search\PostingsRepository;
use App\Search\SearchService;
use App\Search\TfIdfScorer;
use PDO;
use PHPUnit\Framework\TestCase;

final class SearchServiceTest extends TestCase
{
    private PDO $pdo;
    private SearchService $searchService;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec('CREATE TABLE documentos (
            id_documento INTEGER PRIMARY KEY,
            nombre_original TEXT NOT NULL,
            ruta_archivo TEXT NOT NULL,
            fragmento TEXT NOT NULL DEFAULT "",
            total_terminos INTEGER NOT NULL DEFAULT 0
        )');
        $this->pdo->exec('CREATE TABLE diccionario (
            id_termino INTEGER PRIMARY KEY,
            termino TEXT NOT NULL,
            frecuencia_documental INTEGER NOT NULL,
            frecuencia_total INTEGER NOT NULL
        )');
        $this->pdo->exec('CREATE TABLE posting (
            id_termino INTEGER NOT NULL,
            id_documento INTEGER NOT NULL,
            frecuencia INTEGER NOT NULL
        )');

        $this->pdo->exec("INSERT INTO documentos (id_documento, nombre_original, ruta_archivo, fragmento) VALUES (1, 'ano_nuevo.txt', 'ano_nuevo.txt', 'Feliz ano nuevo')");
        $this->pdo->exec("INSERT INTO diccionario (id_termino, termino, frecuencia_documental, frecuencia_total) VALUES (1, 'ano', 1, 2)");
        $this->pdo->exec('INSERT INTO posting (id_termino, id_documento, frecuencia) VALUES (1, 1, 2)');

        $this->searchService = new SearchService(new PostingsRepository($this->pdo), new TfIdfScorer());
    }

    public function testAnoAnoYMayusculasDanElMismoResultado(): void
    {
        $conTilde = $this->searchService->search('año');
        $sinTilde = $this->searchService->search('ano');
        $mayusculas = $this->searchService->search('AÑO');

        self::assertCount(1, $conTilde->resultados);
        self::assertCount(1, $sinTilde->resultados);
        self::assertCount(1, $mayusculas->resultados);

        self::assertSame($conTilde->resultados[0]->idDocumento, $sinTilde->resultados[0]->idDocumento);
        self::assertSame($conTilde->resultados[0]->coseno, $sinTilde->resultados[0]->coseno);
        self::assertSame($conTilde->resultados[0]->coseno, $mayusculas->resultados[0]->coseno);
    }

    public function testConsultaSinTokensValidosNoLanzaExcepcionYDaResultadosVacios(): void
    {
        $resultado = $this->searchService->search('   ');

        self::assertSame([], $resultado->resultados);
    }

    public function testConsultaSinTerminosConocidosDaResultadosVacios(): void
    {
        $resultado = $this->searchService->search('palabraqueNoExiste');

        self::assertSame([], $resultado->resultados);
    }
}
