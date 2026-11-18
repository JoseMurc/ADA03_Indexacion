<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$query = trim($_GET['q'] ?? '');
$resultado = $query !== '' ? $searchService->search($query) : null;
$totalCorpus = $resultado?->totalDocumentos ?? $documentRepository->count();

/** Variantes acentuadas de cada letra, ya que los tokens llegan sin diacriticos (ver TextNormalizer). */
const EQUIVALENCIAS_ACENTOS = [
    'a' => '[aàáâäã]', 'e' => '[eèéêë]', 'i' => '[iìíîï]',
    'o' => '[oòóôöõ]', 'u' => '[uùúûü]', 'n' => '[nñ]', 'c' => '[cç]',
];

function resaltarTexto(string $texto, array $tokens): string
{
    $limpio = htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
    $unicos = array_unique(array_filter($tokens, fn ($t) => mb_strlen($t, 'UTF-8') >= 2));

    if ($limpio === '' || $unicos === []) {
        return $limpio;
    }

    $patrones = array_map(function (string $token): string {
        $letras = preg_split('//u', $token, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return implode('', array_map(
            fn (string $letra) => EQUIVALENCIAS_ACENTOS[$letra] ?? preg_quote($letra, '/'),
            $letras,
        ));
    }, $unicos);

    $patron = '/\b(' . implode('|', $patrones) . ')\b/iu';

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
            <div class="alert alert-<?= htmlspecialchars($_SESSION['alerta']['tipo'], ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($_SESSION['alerta']['texto'], ENT_QUOTES, 'UTF-8') ?>
            </div>
            <?php unset($_SESSION['alerta']); ?>
        <?php endif; ?>

        <div class="grid-layout">
            <aside class="card upload-card">
                <h2>Subir Documentos</h2>
                <p class="help-text">Selecciona archivos <code>.txt</code> para agregarlos al indice.</p>
                <form action="subir.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
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
                        <input type="text" name="q" value="<?= htmlspecialchars($query, ENT_QUOTES, 'UTF-8') ?>" placeholder="Buscar documentos..." autofocus required>
                        <button type="submit" class="btn btn-primary">Buscar</button>
                    </form>

                    <?php if ($resultado !== null): ?>
                        <div class="search-meta">
                            <span>Tokens:
                                <?php if ($resultado->tokens === []): ?>
                                    <em>sin terminos validos</em>
                                <?php else: ?>
                                    <?php foreach (array_unique($resultado->tokens) as $tok): ?>
                                        <code class="token-pill"><?= htmlspecialchars($tok, ENT_QUOTES, 'UTF-8') ?></code>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </span>
                            <span><?= count($resultado->resultados) ?> resultado(s) (<?= number_format($resultado->duracionMs, 2) ?> ms)</span>
                        </div>
                    <?php endif; ?>
                </section>

                <?php if ($resultado !== null): ?>
                    <section class="results-section">
                        <?php if ($resultado->resultados === []): ?>
                            <div class="card empty-state">
                                <h3>Sin resultados</h3>
                                <p>No hubo coincidencias para <strong>"<?= htmlspecialchars($query, ENT_QUOTES, 'UTF-8') ?>"</strong>.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($resultado->resultados as $i => $doc): ?>
                                <?php $pct = min(100, max(0, $doc->coseno * 100)); ?>
                                <article class="card result-card">
                                    <div class="result-top">
                                        <div class="result-title-group">
                                            <span class="rank-badge">#<?= $i + 1 ?></span>
                                            <h3 class="doc-title"><?= htmlspecialchars($doc->archivo, ENT_QUOTES, 'UTF-8') ?></h3>
                                        </div>
                                        <div class="score-box">
                                            <span class="score-value"><?= number_format($pct, 2) ?>%</span>
                                            <span class="score-label">cos: <?= number_format($doc->coseno, 4) ?> · tf-idf: <?= number_format($doc->tfidf, 4) ?></span>
                                        </div>
                                    </div>

                                    <div class="relevance-bar">
                                        <div class="relevance-fill" style="width: <?= $pct ?>%"></div>
                                    </div>

                                    <?php if ($doc->fragmento !== ''): ?>
                                        <blockquote class="snippet">
                                            <?= resaltarTexto($doc->fragmento, $resultado->tokens) ?>
                                        </blockquote>
                                    <?php else: ?>
                                        <p class="snippet-empty">Sin fragmento disponible.</p>
                                    <?php endif; ?>

                                    <div class="result-footer">
                                        <span class="doc-id">ID: <?= $doc->idDocumento ?></span>
                                        <a href="descargar.php?id=<?= $doc->idDocumento ?>" class="btn btn-download">Descargar .txt</a>
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
