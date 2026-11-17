<?php

function calcularSimilitudCoseno($terminosConsulta, $datosBD) {
    $totalDocs = $datosBD['total_docs'];
    $postings = $datosBD['postings'];

    if (empty($postings) || $totalDocs == 0) {
        return []; // No hay resultados
    }

    // 1. Calcular la frecuencia de los términos en la consulta ($tf$ de la consulta)
    $frecuenciasConsulta = array_count_values($terminosConsulta);
    $dfConsulta = []; // Guardaremos los df de cada término de la consulta

    // Primero mapeamos el df ($df_t$) de cada término desde los postings o la BD
    $vocabularioDF = [];
    foreach ($postings as $p) {
        $vocabularioDF[$p['termino']] = (int)$p['df'];
    }

    // 2. Calcular los pesos TF-IDF para la Consulta
    $vectorConsulta = [];
    $normaConsultaSq = 0;

    foreach ($frecuenciasConsulta as $termino => $tfQ) {
        // Si el término de la consulta existe en nuestro vocabulario indexado:
        if (isset($vocabularioDF[$termino])) {
            $df = $vocabularioDF[$termino];
            $idf = log($totalDocs / $df);
            
            // TF-IDF de la consulta
            $pesoQ = $tfQ * $idf;
            $vectorConsulta[$termino] = $pesoQ;
            $normaConsultaSq += pow($pesoQ, 2);
        }
    }
    $normaConsulta = sqrt($normaConsultaSq);

    // Si la norma de la consulta es 0 (ninguna palabra coincide con el índice), salimos
    if ($normaConsulta == 0) {
        return [];
    }

    // 3. Agrupar postings por documento para calcular los vectores de los documentos ($D$)
    $documentosVectores = [];
    $documentosInfo = [];

    foreach ($postings as $p) {
        $idDoc = $p['id_documento'];
        $termino = $p['termino'];
        $tfD = (int)$p['tf'];
        $df = (int)$p['df'];

        // Guardar metadatos del documento para el ranking final
        if (!isset($documentosInfo[$idDoc])) {
            $documentosInfo[$idDoc] = [
                'id_documento' => $idDoc,
                'archivo' => $p['archivo'],
                'url' => $p['url']
            ];
        }

        // Calcular IDF del término
        $idf = log($totalDocs / $df);
        
        // TF-IDF del término en este documento
        $pesoD = $tfD * $idf;

        // Almacenar el vector del documento
        $documentosVectores[$idDoc][$termino] = $pesoD;
    }

    // 4. Calcular la Similitud Coseno para cada documento
    $resultadosFinales = [];

    foreach ($documentosVectores as $idDoc => $vectorDoc) {
        $productoPunto = 0;
        $normaDocSq = 0;

        // Producto punto entre Consulta y Documento, y cálculo de la norma del Documento
        foreach ($vectorDoc as $termino => $pesoD) {
            // Sumar a la norma cuadrada del documento
            $normaDocSq += pow($pesoD, 2);

            // Si el término también está en la consulta, participa en el producto punto
            if (isset($vectorConsulta[$termino])) {
                $productoPunto += ($vectorConsulta[$termino] * $pesoD);
            }
        }

        $normaDoc = sqrt($normaDocSq);

        // Evitar división entre cero
        $denominador = $normaConsulta * $normaDoc;
        $similitud = ($denominador > 0) ? ($productoPunto / $denominador) : 0;

        // Guardar resultado con su respectivo ranking
        $resultadosFinales[] = [
            'id_documento' => $idDoc,
            'archivo' => $documentosInfo[$idDoc]['archivo'],
            'url' => $documentosInfo[$idDoc]['url'],
            'similitud' => $similitud
        ];
    }

    // 5. Ranking: Ordenar los documentos de mayor a menor similitud (relevancia TF-IDF)
    usort($resultadosFinales, function($a, $b) {
        return $b['similitud'] <=> $a['similitud'];
    });

    return $resultadosFinales;
}

?>