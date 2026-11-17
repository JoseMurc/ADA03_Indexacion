<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$tokenEnviado = (string) ($_POST['csrf_token'] ?? '');
$tokenValido = isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $tokenEnviado);

if (!$tokenValido || !isset($_FILES['archivos'])) {
    $_SESSION['alerta'] = [
        'tipo' => 'error',
        'texto' => 'Solicitud invalida. Intenta de nuevo.',
    ];
    header('Location: index.php');
    exit;
}

$reporte = $uploadService->handle($_FILES['archivos']);

$mensajes = [];
if ($reporte->stored !== []) {
    $txt = sprintf('Se subieron e indexaron %d archivo(s): %s.', count($reporte->stored), implode(', ', $reporte->stored));
    if ($reporte->reindexStats !== null) {
        $txt .= sprintf(
            ' (%d terminos en %d documentos).',
            $reporte->reindexStats['terminos'],
            $reporte->reindexStats['documentos'],
        );
    }
    $mensajes[] = $txt;
}
if ($reporte->duplicates !== []) {
    foreach ($reporte->duplicates as $nombre => $existente) {
        $mensajes[] = sprintf('"%s" es un duplicado de "%s" y no se guardo.', $nombre, $existente);
    }
}
if ($reporte->rejected !== []) {
    foreach ($reporte->rejected as $nombre => $motivo) {
        $mensajes[] = sprintf('"%s" fue rechazado: %s', $nombre, $motivo);
    }
}
if ($reporte->reindexError !== null) {
    $mensajes[] = 'Los archivos se guardaron, pero la reindexacion fallo: ' . $reporte->reindexError;
}

$tipoAlerta = 'info';
if ($reporte->reindexError !== null || $reporte->rejected !== []) {
    $tipoAlerta = 'warning';
} elseif ($reporte->stored !== []) {
    $tipoAlerta = 'success';
}

$_SESSION['alerta'] = [
    'tipo' => $tipoAlerta,
    'texto' => $mensajes !== [] ? implode(' ', $mensajes) : 'No se seleccionaron archivos.',
];

header('Location: index.php');
exit;
