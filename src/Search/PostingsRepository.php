<?php

declare(strict_types=1);

namespace App\Search;

use PDO;

/**
 * Acceso de solo lectura al indice invertido (diccionario + posting) para
 * resolver una consulta. No calcula tf-idf ni similitud: eso es responsabilidad
 * de TfIdfScorer.
 */
final class PostingsRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function countDocuments(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM documentos')->fetchColumn();
    }

    /**
     * @param string[] $terminos
     * @return array<string,array{id:int,df:int}>
     */
    public function findTerms(array $terminos): array
    {
        if ($terminos === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($terminos), '?'));
        $sentencia = $this->pdo->prepare(
            "SELECT id_termino, termino, frecuencia_documental FROM diccionario WHERE termino IN ($placeholders)"
        );
        $sentencia->execute(array_values($terminos));

        $resultado = [];
        foreach ($sentencia->fetchAll() as $fila) {
            $resultado[(string) $fila['termino']] = [
                'id' => (int) $fila['id_termino'],
                'df' => (int) $fila['frecuencia_documental'],
            ];
        }

        return $resultado;
    }

    /**
     * @param int[] $idsTerminos
     * @return list<array{id_documento:int,termino:string,tf:int}>
     */
    public function postingsForTermIds(array $idsTerminos): array
    {
        if ($idsTerminos === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($idsTerminos), '?'));
        $sentencia = $this->pdo->prepare(
            "SELECT p.id_documento, d.termino, p.frecuencia AS tf
             FROM posting p
             JOIN diccionario d ON d.id_termino = p.id_termino
             WHERE p.id_termino IN ($placeholders)"
        );
        $sentencia->execute(array_values($idsTerminos));

        $resultado = [];
        foreach ($sentencia->fetchAll() as $fila) {
            $resultado[] = [
                'id_documento' => (int) $fila['id_documento'],
                'termino' => (string) $fila['termino'],
                'tf' => (int) $fila['tf'],
            ];
        }

        return $resultado;
    }

    /**
     * Vector completo (TODOS los terminos) de cada documento solicitado,
     * necesario para calcular la norma real del documento.
     *
     * @param int[] $idsDocumentos
     * @return array<int,list<array{tf:int,df:int}>>
     */
    public function documentVectors(array $idsDocumentos): array
    {
        if ($idsDocumentos === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($idsDocumentos), '?'));
        $sentencia = $this->pdo->prepare(
            "SELECT p.id_documento, p.frecuencia AS tf, d.frecuencia_documental AS df
             FROM posting p
             JOIN diccionario d ON d.id_termino = p.id_termino
             WHERE p.id_documento IN ($placeholders)"
        );
        $sentencia->execute(array_values($idsDocumentos));

        $resultado = [];
        foreach ($sentencia->fetchAll() as $fila) {
            $idDocumento = (int) $fila['id_documento'];
            $resultado[$idDocumento][] = [
                'tf' => (int) $fila['tf'],
                'df' => (int) $fila['df'],
            ];
        }

        return $resultado;
    }

    /**
     * @param int[] $idsDocumentos
     * @return array<int,array{id:int,nombre_original:string,fragmento:string}>
     */
    public function documentsMeta(array $idsDocumentos): array
    {
        if ($idsDocumentos === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($idsDocumentos), '?'));
        $sentencia = $this->pdo->prepare(
            "SELECT id_documento AS id, nombre_original, fragmento
             FROM documentos
             WHERE id_documento IN ($placeholders)"
        );
        $sentencia->execute(array_values($idsDocumentos));

        $resultado = [];
        foreach ($sentencia->fetchAll() as $fila) {
            $resultado[(int) $fila['id']] = [
                'id' => (int) $fila['id'],
                'nombre_original' => (string) $fila['nombre_original'],
                'fragmento' => (string) $fila['fragmento'],
            ];
        }

        return $resultado;
    }
}
