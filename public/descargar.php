<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($id === false || $id === null || $id <= 0) {
    http_response_code(400);
    exit('ID no valido.');
}

$documento = $documentRepository->findById($id);
if ($documento === null) {
    http_response_code(404);
    exit('Documento no encontrado.');
}

try {
    $rutaCompleta = $fileLocator->resolve((string) $documento['ruta_archivo']);
} catch (RuntimeException) {
    http_response_code(404);
    exit('Archivo no disponible en el servidor.');
}

$nombreOriginal = (string) $documento['nombre_original'];
$nombreSaneado = preg_replace('/[^A-Za-z0-9._ -]/', '_', $nombreOriginal) ?? 'documento.txt';

header('Content-Type: text/plain; charset=utf-8');
header(sprintf(
    'Content-Disposition: attachment; filename="%s"; filename*=UTF-8\'\'%s',
    $nombreSaneado,
    rawurlencode($nombreOriginal),
));
header('X-Content-Type-Options: nosniff');
header('Content-Length: ' . (string) filesize($rutaCompleta));
header('Cache-Control: no-cache, must-revalidate');

readfile($rutaCompleta);
exit;
