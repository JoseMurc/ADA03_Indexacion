<?php

function consultarPostingsYEstadisticas($pdo, $terminos) {
    if (empty($terminos)) {
        return ['total_docs' => 0, 'postings' => []];
    }

    // 1. Obtener el número total de documentos en la colección (N) usando la tabla Documento
    $stmtTotal = $pdo->query("SELECT COUNT(DISTINCT id_documento) AS total FROM Documento");
    $totalDocs = $stmtTotal->fetch(PDO::FETCH_ASSOC)['total'] ?? 1;

    // 2. Crear los placeholders (?, ?, ?) para evitar SQL Injection
    $placeholders = implode(',', array_fill(0, count($terminos), '?'));

    // 3. Consultar uniendo Vocabulario, Posting y Documento según tu diagrama
    $sql = "SELECT 
                v.token AS termino, 
                v.documentos AS df, 
                p.id_documento, 
                p.frecuencia AS tf, 
                d.archivo, 
                d.url 
            FROM Vocabulario v
            JOIN Posting p ON v.id_token = p.id_token
            JOIN Documento d ON p.id_documento = d.id_documento
            WHERE v.token IN ($placeholders)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($terminos);
    $postingsBrutos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'total_docs' => (int)$totalDocs,
        'postings' => $postingsBrutos
    ];
}

?>