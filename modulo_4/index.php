<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/config.php';

use App\Indexing\Text\StopwordList;
use App\Indexing\Text\TextNormalizer;
use App\Indexing\Text\Tokenizer;

$query = trim($_GET['q'] ?? '');
$resultados = [];
$tokensConsulta = [];
$tiempoMs = 0.0;
$totalCorpus = 0;

try {
    $totalCorpus = (int) $pdo->query('SELECT COUNT(*) FROM documentos')->fetchColumn();
} catch (Throwable $e) {
}

if ($query !== '') {
    $inicio = microtime(true);

    if (class_exists(Tokenizer::class)) {
        $tokenizer = new Tokenizer(new TextNormalizer(), new StopwordList());
        $tokensConsulta = $tokenizer->tokenize($query);
    } elseif (function_exists('procesarConsulta')) {
        require_once __DIR__ . '/../modulo_3/procesarConsulta.php';
        $tokensConsulta = procesarConsulta($query);
    }

    if (!empty($tokensConsulta)) {
        $unicos = array_values(array_unique($tokensConsulta));
        $placeholders = implode(',', array_fill(0, count($unicos), '?'));

        $sql = "SELECT
                    dic.termino AS termino,
                    dic.frecuencia_documental AS df,
                    p.id_documento,
                    p.frecuencia AS tf,
                    doc.nombre_original AS archivo,
                    doc.ruta_archivo AS url,
                    doc.fragmento
                FROM diccionario dic
                INNER JOIN posting p ON dic.id_termino = p.id_termino
                INNER JOIN documentos doc ON p.id_documento = doc.id_documento
                WHERE dic.termino IN ($placeholders)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($unicos);
        $postings = $stmt->fetchAll();

        $fragmentos = [];
        foreach ($postings as $row) {
            $fragmentos[(int) $row['id_documento']] = $row['fragmento'] ?? '';
        }

        $datosBD = [
            'total_docs' => max(1, $totalCorpus),
            'postings' => $postings,
        ];

        if (function_exists('calcularSimilitudCoseno')) {
            $resultados = calcularSimilitudCoseno($tokensConsulta, $datosBD);
            foreach ($resultados as &$res) {
                $res['fragmento'] = $fragmentos[(int) $res['id_documento']] ?? '';
            }
            unset($res);
        }
    }

    $tiempoMs = (microtime(true) - $inicio) * 1000;
}

function resaltarTexto(string $texto, array $tokens): string
{
    $limpio = htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
    $unicos = array_unique(array_filter($tokens, fn($t) => mb_strlen($t) >= 2));

    if ($limpio === '' || empty($unicos)) {
        return $limpio;
    }

    $patron = '/\b(' . implode('|', array_map(fn($t) => preg_quote($t, '/'), $unicos)) . ')\b/iu';
    return preg_replace($patron, '<mark>$1</mark>', $limpio) ?? $limpio;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buscador - ADA 03</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <header class="header">
            <h1>Buscador Documental TF-IDF</h1>
            <p class="subtitle">Documentos indexados: <strong><?= $totalCorpus ?></strong></p>
        </header>

        <?php if (isset($_SESSION['alerta'])): ?>
            <div class="alert alert-<?= htmlspecialchars($_SESSION['alerta']['tipo']) ?>">
                <?= htmlspecialchars($_SESSION['alerta']['texto']) ?>
            </div>
            <?php unset($_SESSION['alerta']); ?>
        <?php endif; ?>

        <div class="grid-layout">
            <aside class="card upload-card">
                <h2>Subir Documentos</h2>
                <p class="help-text">Selecciona archivos <code>.txt</code> para agregarlos al indice.</p>
                <form action="subir.php" method="POST" enctype="multipart/form-data">
                    <label class="file-drop" for="archivos">
                        <span id="file-label">Elegir archivos .txt...</span>
                        <input type="file" name="archivos[]" id="archivos" accept=".txt,text/plain" multiple required>
                    </label>
                    <button type="submit" class="btn btn-secondary btn-block">Subir e Indexar</button>
                </form>
            </aside>

            <main class="main-panel">
                <section class="card search-card">
                    <form action="index.php" method="GET" class="search-form">
                        <input type="text" name="q" value="<?= htmlspecialchars($query) ?>" placeholder="Buscar documentos..." autofocus required>
                        <button type="submit" class="btn btn-primary">Buscar</button>
                    </form>

                    <?php if ($query !== ''): ?>
                        <div class="search-meta">
                            <span>Tokens:
                                <?php if (empty($tokensConsulta)): ?>
                                    <em>sin terminos validos</em>
                                <?php else: ?>
                                    <?php foreach (array_unique($tokensConsulta) as $tok): ?>
                                        <code class="token-pill"><?= htmlspecialchars($tok) ?></code>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </span>
                            <span><?= count($resultados) ?> resultado(s) (<?= number_format($tiempoMs, 2) ?> ms)</span>
                        </div>
                    <?php endif; ?>
                </section>

                <?php if ($query !== ''): ?>
                    <section class="results-section">
                        <?php if (empty($resultados)): ?>
                            <div class="card empty-state">
                                <h3>Sin resultados</h3>
                                <p>No hubo coincidencias para <strong>"<?= htmlspecialchars($query) ?>"</strong>.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($resultados as $i => $doc): ?>
                                <?php $pct = min(100, max(0, $doc['similitud'] * 100)); ?>
                                <article class="card result-card">
                                    <div class="result-top">
                                        <div class="result-title-group">
                                            <span class="rank-badge">#<?= $i + 1 ?></span>
                                            <h3 class="doc-title"><?= htmlspecialchars($doc['archivo']) ?></h3>
                                        </div>
                                        <div class="score-box">
                                            <span class="score-value"><?= number_format($pct, 2) ?>%</span>
                                            <span class="score-label">cos: <?= number_format($doc['similitud'], 4) ?></span>
                                        </div>
                                    </div>

                                    <div class="relevance-bar">
                                        <div class="relevance-fill" style="width: <?= $pct ?>%"></div>
                                    </div>

                                    <?php if (!empty($doc['fragmento'])): ?>
                                        <blockquote class="snippet">
                                            <?= resaltarTexto($doc['fragmento'], $tokensConsulta) ?>
                                        </blockquote>
                                    <?php else: ?>
                                        <p class="snippet-empty">Sin fragmento disponible.</p>
                                    <?php endif; ?>

                                    <div class="result-footer">
                                        <span class="doc-id">ID: <?= (int) $doc['id_documento'] ?></span>
                                        <a href="descargar.php?id=<?= (int) $doc['id_documento'] ?>" class="btn btn-download">Descargar .txt</a>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script>
        const input = document.getElementById('archivos');
        const label = document.getElementById('file-label');
        input.addEventListener('change', () => {
            if (input.files.length === 1) {
                label.textContent = input.files[0].name;
            } else if (input.files.length > 1) {
                label.textContent = input.files.length + ' archivos seleccionados';
            } else {
                label.textContent = 'Elegir archivos .txt...';
            }
        });
    </script>
</body>
</html>