<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id <= 0) {
    http_response_code(400);
    exit('ID no valido.');
}

$stmt = $pdo->prepare('SELECT nombre_original, ruta_archivo FROM documentos WHERE id_documento = :id LIMIT 1');
$stmt->execute(['id' => $id]);
$doc = $stmt->fetch();

if (!$doc) {
    http_response_code(404);
    exit('Documento no encontrado.');
}

$ruta = (string) $doc['ruta_archivo'];
$esAbsoluta = str_starts_with($ruta, '/') || preg_match('#^[A-Za-z]:[\\\\/]#', $ruta) === 1;
$rutaCompleta = $esAbsoluta ? $ruta : rtrim($uploadsDir, '/\\') . DIRECTORY_SEPARATOR . $ruta;

if (!is_file($rutaCompleta) || !is_readable($rutaCompleta)) {
    http_response_code(404);
    exit('Archivo no disponible en el servidor.');
}

$nombre = str_replace(['"', "\r", "\n"], '', (string) $doc['nombre_original']);

header('Content-Description: File Transfer');
header('Content-Type: text/plain; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $nombre . '"');
header('Content-Length: ' . filesize($rutaCompleta));
header('Cache-Control: no-cache, must-revalidate');

readfile($rutaCompleta);
exit;