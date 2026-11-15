# Modulo 2 — Indice invertido

Construye y mantiene el indice invertido (diccionario + posting) a partir de
los documentos que registra el Modulo 1 en la tabla `documentos`. No incluye
tf-idf, similitud coseno ni ranking (eso es responsabilidad del Modulo 3).

## Instalacion

```
composer install
```

## Reindexar

El script CLI lee las variables de entorno de conexion y ejecuta una
reconstruccion total del indice:

```
DB_HOST=127.0.0.1 DB_NAME=buscador_ir DB_USER=usuario DB_PASS=clave \
UPLOADS_BASE_DIR=/ruta/a/los/archivos \
php scripts/reindex.php
```

Variables de entorno (todas opcionales salvo credenciales reales):

| Variable           | Default         |
|---------------------|-----------------|
| `DB_HOST`           | `127.0.0.1`     |
| `DB_NAME`            | `buscador_ir`   |
| `DB_USER`            | (vacio)         |
| `DB_PASS`            | (vacio)         |
| `UPLOADS_BASE_DIR`  | (vacio)         |

`scripts/reindex.php` define la funcion `runReindex(PDO $pdo, string $baseDir): array`,
reutilizable desde PHP web (M1/M4) para disparar la misma reindexacion sin pasar
por la linea de comandos, por ejemplo justo despues de que M1 guarde un nuevo
archivo. No es un archivo directo del módulo 2, pero sirve de prueba manual y de ejemplo de ensamblado

Es una reconstruccion **total**: cada corrida borra `diccionario` y `posting`
y los reconstruye desde cero a partir de todos los documentos existentes (el
idf depende de N, por lo que no se hace de forma incremental). No modifica ni
borra la tabla `documentos`, solo actualiza `total_terminos` y `fragmento`.

## API publica para el Modulo 3: `Tokenizer`

`App\Indexing\Text\Tokenizer::tokenize(string $texto): array` devuelve un
arreglo de tokens normalizados (minusculas, sin acentos/ñ, sin puntuacion,
sin stopwords, con duplicados conservados y en el orden original). M3 debe
usar esta misma clase para procesar las consultas del usuario, de forma que
los terminos de la consulta coincidan exactamente con los del indice.

```php
$tokenizer = new App\Indexing\Text\Tokenizer(
    new App\Indexing\Text\TextNormalizer(),
    new App\Indexing\Text\StopwordList(),
);
$tokens = $tokenizer->tokenize('texto de la consulta del usuario');
```

## Suposiciones sobre `documentos.ruta_archivo`

Pendiente de confirmar con el Modulo 1. `MySqlDocumentSource` soporta ambos
casos para no bloquear el desarrollo:

- **Ruta absoluta** (empieza con `/` o con patron de unidad de Windows,
  `C:\` o `C:/`): se usa tal cual.
- **Ruta relativa**: se concatena con `UPLOADS_BASE_DIR`.

## Tests

```
vendor/bin/phpunit
```

La prueba de integracion contra MySQL real (`@group integration`) se omite
automaticamente si no estan definidas `DB_HOST`, `DB_NAME` y `DB_USER`. Puede que surjan errores en las pruebas si no configura en php.ini

```
extension=pdo_sqlite
```

