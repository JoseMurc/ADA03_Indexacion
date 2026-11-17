<?php

declare(strict_types=1);

namespace App\Search;

/**
 * Calcula tf-idf y similitud coseno. Clase pura: no toca la base de datos.
 *
 * Corrige dos bugs de la implementacion anterior (modulo_3/calculo.php):
 * 1. Con idf = ln(N/df) clasico, un termino presente en todos los documentos
 *    (df == N) pesa 0, y con N=1 no hay forma de encontrar nada. Por defecto
 *    usamos idf suavizado ln(1 + N/df) (> 0 siempre). Ver $idfSuavizado abajo
 *    para revertir al idf clasico si el profesor lo exige.
 * 2. La norma del documento debe calcularse con TODOS sus terminos, no solo
 *    con los de la consulta (si no, una consulta de 1 termino da coseno 1.0
 *    a cualquier documento que lo contenga).
 */
final class TfIdfScorer
{
    /**
     * @param bool $idfSuavizado true (por defecto): ln(1 + N/df), evita idf=0 cuando
     *                           df==N y permite encontrar resultados con N=1.
     *                           false: ln(N/df), formula clasica del material de clase.
     *                           Para volver a la formula clasica, instanciar con `false`.
     */
    public function __construct(private bool $idfSuavizado = true)
    {
    }

    public function idf(int $n, int $df): float
    {
        if ($df <= 0) {
            return 0.0;
        }

        return $this->idfSuavizado
            ? log(1 + ($n / $df))
            : log($n / $df);
    }

    /**
     * @param array<string,int> $queryTf termino => tf en la consulta.
     * @param array<string,int> $dfPorTermino termino => frecuencia_documental (solo terminos de la consulta que existen en el diccionario).
     * @param array<int,array<string,int>> $postingsPorDoc id_documento => [termino => tf] (solo terminos de la consulta).
     * @param array<int,list<array{tf:int,df:int}>> $vectoresPorDoc id_documento => lista de TODOS sus terminos (tf,df), para la norma real.
     * @return array<int,array{coseno:float,tfidf:float}>
     */
    public function score(
        int $n,
        array $queryTf,
        array $dfPorTermino,
        array $postingsPorDoc,
        array $vectoresPorDoc,
    ): array {
        $idfPorTermino = [];
        foreach ($dfPorTermino as $termino => $df) {
            $idfPorTermino[$termino] = $this->idf($n, $df);
        }

        $pesoConsulta = [];
        $normaConsultaCuadrada = 0.0;
        foreach ($queryTf as $termino => $tfQ) {
            if (!isset($idfPorTermino[$termino])) {
                continue; // termino desconocido: se ignora
            }
            $peso = $tfQ * $idfPorTermino[$termino];
            $pesoConsulta[$termino] = $peso;
            $normaConsultaCuadrada += $peso ** 2;
        }
        $normaConsulta = sqrt($normaConsultaCuadrada);

        $resultados = [];
        foreach ($vectoresPorDoc as $idDocumento => $terminosDelDocumento) {
            $normaDocumentoCuadrada = 0.0;
            foreach ($terminosDelDocumento as $entrada) {
                $pesoTermino = $entrada['tf'] * $this->idf($n, $entrada['df']);
                $normaDocumentoCuadrada += $pesoTermino ** 2;
            }
            $normaDocumento = sqrt($normaDocumentoCuadrada);

            $productoPunto = 0.0;
            $tfidfConsulta = 0.0;
            foreach ($postingsPorDoc[$idDocumento] ?? [] as $termino => $tfDoc) {
                if (!isset($idfPorTermino[$termino])) {
                    continue;
                }
                $pesoDoc = $tfDoc * $idfPorTermino[$termino];
                $tfidfConsulta += $pesoDoc;

                if (isset($pesoConsulta[$termino])) {
                    $productoPunto += $pesoConsulta[$termino] * $pesoDoc;
                }
            }

            $denominador = $normaConsulta * $normaDocumento;
            $coseno = $denominador > 0.0 ? $productoPunto / $denominador : 0.0;
            $coseno = max(0.0, min(1.0, $coseno));

            $resultados[$idDocumento] = [
                'coseno' => $coseno,
                'tfidf' => $tfidfConsulta,
            ];
        }

        return $resultados;
    }
}
