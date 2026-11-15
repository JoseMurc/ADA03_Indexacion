<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['archivos'])) {
    header('Location: index.php');
    exit;
}

$archivos = $_FILES['archivos'];
$total = count($archivos['name']);
$subidos = 0;
$duplicados = 0;
$errores = [];

try {
    $columnas = $pdo->query('DESCRIBE documentos')->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
    $_SESSION['alerta'] = [
        'tipo' => 'error',
        'texto' => 'No se encontro la tabla documentos en la base de datos.'
    ];
    header('Location: index.php');
    exit;
}

$tieneHash = in_array('hash_archivo', $columnas, true) || in_array('hash', $columnas, true);
$colHash = in_array('hash_archivo', $columnas, true) ? 'hash_archivo' : 'hash';

for ($i = 0; $i < $total; $i++) {
    $nombre = basename($archivos['name'][$i]);
    $tmp = $archivos['tmp_name'][$i];
    $error = $archivos['error'][$i];

    if ($error === UPLOAD_ERR_NO_FILE) {
        continue;
    }

    if ($error !== UPLOAD_ERR_OK) {
        $errores[] = "Error al subir $nombre.";
        continue;
    }

    if (strtolower(pathinfo($nombre, PATHINFO_EXTENSION)) !== 'txt') {
        $errores[] = "$nombre no es un archivo .txt.";
        continue;
    }

    $hash = hash_file('sha256', $tmp);

    if ($tieneHash) {
        $stmt = $pdo->prepare("SELECT id_documento FROM documentos WHERE $colHash = :h LIMIT 1");
        $stmt->execute(['h' => $hash]);
    } else {
        $stmt = $pdo->prepare("SELECT id_documento FROM documentos WHERE nombre_original = :n LIMIT 1");
        $stmt->execute(['n' => $nombre]);
    }

    if ($stmt->fetch()) {
        $duplicados++;
        continue;
    }

    $nombreFisico = uniqid('doc_', true) . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $nombre);
    $destino = rtrim($uploadsDir, '/\\') . DIRECTORY_SEPARATOR . $nombreFisico;

    if (!move_uploaded_file($tmp, $destino)) {
        $errores[] = "No se pudo guardar $nombre.";
        continue;
    }

    if ($tieneHash) {
        $stmtIns = $pdo->prepare("INSERT INTO documentos (nombre_original, ruta_archivo, $colHash) VALUES (:n, :r, :h)");
        $stmtIns->execute(['n' => $nombre, 'r' => $nombreFisico, 'h' => $hash]);
    } else {
        $stmtIns = $pdo->prepare("INSERT INTO documentos (nombre_original, ruta_archivo) VALUES (:n, :r)");
        $stmtIns->execute(['n' => $nombre, 'r' => $nombreFisico]);
    }

    $subidos++;
}

$stats = null;
if ($subidos > 0 && function_exists('runReindex')) {
    try {
        $stats = runReindex($pdo, $uploadsDir);
    } catch (Throwable $e) {
        $errores[] = 'Fallo la reindexacion: ' . $e->getMessage();
    }
}

$mensajes = [];
if ($subidos > 0) {
    $txt = "Se subieron e indexaron $subidos archivo(s).";
    if ($stats) {
        $txt .= " ({$stats['terminos']} terminos en {$stats['documentos']} docs).";
    }
    $mensajes[] = $txt;
}
if ($duplicados > 0) {
    $mensajes[] = "$duplicados duplicado(s) omitido(s).";
}
if (!empty($errores)) {
    $mensajes[] = implode(' ', $errores);
}

$_SESSION['alerta'] = [
    'tipo' => !empty($errores) ? 'warning' : ($subidos > 0 ? 'success' : 'info'),
    'texto' => implode(' ', $mensajes) ?: 'No se seleccionaron archivos.'
];

header('Location: index.php');
exit;