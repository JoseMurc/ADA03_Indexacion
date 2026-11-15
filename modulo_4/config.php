<?php
declare(strict_types=1);

$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

$reindex = __DIR__ . '/../scripts/reindex.php';
if (file_exists($reindex)) {
    require_once $reindex;
}

$calculo = __DIR__ . '/../modulo_3/calculo.php';
if (file_exists($calculo)) {
    require_once $calculo;
}

$host = getenv('DB_HOST') ?: '127.0.0.1';
$db   = getenv('DB_NAME') ?: 'buscador_ir';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';

$uploadsDir = getenv('UPLOADS_BASE_DIR') ?: realpath(__DIR__ . '/../uploads') ?: (__DIR__ . '/../uploads');
if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0775, true);
}

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$db;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die('Error de conexion: ' . htmlspecialchars($e->getMessage()));
}