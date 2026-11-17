<?php

declare(strict_types=1);

namespace App\Storage;

use App\Storage\Dto\UploadReport;
use Closure;
use PDOException;
use Throwable;

/**
 * Procesa un lote de archivos .txt subidos: valida, detecta duplicados por
 * contenido (hash sha256), los guarda en disco y los registra en BD. Al
 * final del lote, si se guardo al menos un archivo, dispara la reindexacion.
 */
final class UploadService
{
    private const DEFAULT_MAX_BYTES = 5_242_880;

    private Closure $moveFile;

    public function __construct(
        private DocumentRepository $documentRepository,
        private FileLocator $fileLocator,
        private Closure $reindex,
        ?Closure $moveFile = null,
        private int $maxBytes = self::DEFAULT_MAX_BYTES,
    ) {
        $this->moveFile = $moveFile ?? static fn (string $tmp, string $destino): bool => move_uploaded_file($tmp, $destino);
    }

    /**
     * @param array{name:array,type:array,tmp_name:array,error:array,size:array} $files
     */
    public function handle(array $files): UploadReport
    {
        $reporte = new UploadReport();
        $archivos = $this->normalizar($files);
        $seGuardoAlguno = false;

        foreach ($archivos as $archivo) {
            $nombreOriginal = basename((string) $archivo['name']);

            if ((int) $archivo['error'] === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            if ((int) $archivo['error'] !== UPLOAD_ERR_OK) {
                $reporte->rejected[$nombreOriginal] = $this->describirErrorSubida((int) $archivo['error']);
                continue;
            }

            $tmpName = (string) $archivo['tmp_name'];

            if (strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION)) !== 'txt') {
                $reporte->rejected[$nombreOriginal] = 'Solo se aceptan archivos .txt.';
                continue;
            }

            $tamano = (int) $archivo['size'];
            if ($tamano <= 0) {
                $reporte->rejected[$nombreOriginal] = 'El archivo esta vacio.';
                continue;
            }
            if ($tamano > $this->maxBytes) {
                $reporte->rejected[$nombreOriginal] = sprintf('El archivo excede el tamano maximo de %d bytes.', $this->maxBytes);
                continue;
            }

            $primerosBytes = @file_get_contents($tmpName, false, null, 0, 8192);
            if ($primerosBytes !== false && str_contains($primerosBytes, "\0")) {
                $reporte->rejected[$nombreOriginal] = 'El archivo contiene bytes nulos y no parece texto plano.';
                continue;
            }

            $hash = hash_file('sha256', $tmpName);
            if ($hash === false) {
                $reporte->rejected[$nombreOriginal] = 'No se pudo calcular el hash del archivo.';
                continue;
            }

            $existente = $this->documentRepository->findByHash($hash);
            if ($existente !== null) {
                $reporte->duplicates[$nombreOriginal] = (string) $existente['nombre_original'];
                continue;
            }

            $nombreGuardado = $this->generarNombreGuardado($nombreOriginal);
            $destino = $this->fileLocator->baseDir() . DIRECTORY_SEPARATOR . $nombreGuardado;

            if (!($this->moveFile)($tmpName, $destino)) {
                $reporte->rejected[$nombreOriginal] = 'No se pudo guardar el archivo en el servidor.';
                continue;
            }

            try {
                $this->documentRepository->insert($nombreOriginal, $nombreGuardado, $nombreGuardado, $hash);
            } catch (PDOException $excepcion) {
                @unlink($destino);

                if ($excepcion->getCode() === '23000') {
                    $existente = $this->documentRepository->findByHash($hash);
                    $reporte->duplicates[$nombreOriginal] = $existente !== null
                        ? (string) $existente['nombre_original']
                        : $nombreOriginal;
                    continue;
                }

                $reporte->rejected[$nombreOriginal] = 'No se pudo registrar el archivo en la base de datos.';
                continue;
            }

            $reporte->stored[] = $nombreOriginal;
            $seGuardoAlguno = true;
        }

        if ($seGuardoAlguno) {
            try {
                $reporte->reindexStats = ($this->reindex)();
            } catch (Throwable $excepcion) {
                $reporte->reindexError = $excepcion->getMessage();
            }
        }

        return $reporte;
    }

    /**
     * Convierte el formato de arrays paralelos de $_FILES['archivos'] (o uno
     * ya normalizado para un solo archivo) en una lista de archivos.
     *
     * @return list<array{name:string,type:string,tmp_name:string,error:int,size:int}>
     */
    private function normalizar(array $files): array
    {
        if (!is_array($files['name'])) {
            return [$files];
        }

        $archivos = [];
        foreach (array_keys($files['name']) as $indice) {
            $archivos[] = [
                'name' => $files['name'][$indice],
                'type' => $files['type'][$indice],
                'tmp_name' => $files['tmp_name'][$indice],
                'error' => $files['error'][$indice],
                'size' => $files['size'][$indice],
            ];
        }

        return $archivos;
    }

    private function describirErrorSubida(int $codigo): string
    {
        return match ($codigo) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'El archivo excede el tamano maximo permitido.',
            UPLOAD_ERR_PARTIAL => 'La subida del archivo se interrumpio.',
            UPLOAD_ERR_NO_TMP_DIR => 'Falta un directorio temporal en el servidor.',
            UPLOAD_ERR_CANT_WRITE => 'No se pudo escribir el archivo en el servidor.',
            UPLOAD_ERR_EXTENSION => 'Una extension de PHP detuvo la subida del archivo.',
            default => 'Error desconocido al subir el archivo.',
        };
    }

    private function generarNombreGuardado(string $nombreOriginal): string
    {
        $saneado = preg_replace('/[^A-Za-z0-9._-]/', '_', $nombreOriginal) ?? 'archivo.txt';
        $prefijo = bin2hex(random_bytes(8)) . '_';

        $longitudMaxima = 255 - strlen($prefijo);
        if ($longitudMaxima < 1) {
            $longitudMaxima = 1;
        }
        if (strlen($saneado) > $longitudMaxima) {
            $saneado = substr($saneado, 0, $longitudMaxima);
        }

        return $prefijo . $saneado;
    }
}
