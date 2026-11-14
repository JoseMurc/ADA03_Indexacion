<?php

function procesarConsulta($consultaCruda) {
    // 1. Convertir todo a minúsculas
    $texto = mb_strtolower($consultaCruda, 'UTF-8');

    // 2. Eliminar acentos y caracteres especiales
    $texto = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
    $texto = preg_replace('/[^a-z0-9\s]/', '', $texto);

    // 3. Tokenizar: separar por espacios en blanco
    $tokens = explode(' ', trim(preg_replace('/\s+/', ' ', $texto)));

    // 4. Lista de palabras vacías (stopwords)
    $stopwords = [
        'el', 'la', 'los', 'las', 'un', 'una', 'unos', 'unas',
        'de', 'del', 'a', 'en', 'y', 'o', 'que', 'con',
        'por', 'para', 'es'
    ];

    // 5. Filtrar stopwords y eliminar duplicados
    $tokensFiltrados = array_diff($tokens, $stopwords);

    return array_values(array_unique($tokensFiltrados));
}


?>