# ADA03_Indexacion

Buscador documental con indice invertido, tf-idf y similitud coseno. El
usuario carga archivos `.txt` desde un formulario web; cada carga reindexa
por completo la coleccion y las busquedas devuelven los documentos
ordenados por relevancia, con enlace de descarga y fragmento resaltado.

## Requisitos

- PHP 8.1 o superior, con las extensiones `mbstring`, `pdo` y `pdo_mysql`
  (se recomienda tambien `intl`, para una normalizacion de acentos mas robusta).
- MySQL 8 (o compatible).
- Composer.

## Instalacion

```bash
composer install
```

## Base de datos

El schema (tablas `documentos`, `diccionario`, `posting`) esta en
`sql/schema.sql` y crea la base de datos `buscador_ir` si no existe:

```bash
mysql -u root -p < sql/schema.sql
```

## Variables de entorno

| Variable            | Default                      | Uso                                      |
|---------------------|-------------------------------|-------------------------------------------|
| `DB_HOST`           | `127.0.0.1`                   | Host de MySQL                             |
| `DB_PORT`           | `3306`                        | Puerto de MySQL                           |
| `DB_NAME`           | `buscador_ir`                 | Base de datos                             |
| `DB_USER`           | `root`                        | Usuario de MySQL                          |
| `DB_PASS`           | (vacio)                        | Contrasena de MySQL                       |
| `UPLOADS_BASE_DIR`  | `<raiz del proyecto>/storage/uploads` | Carpeta donde se guardan los .txt subidos |

## Arranque

```bash
php -S localhost:8000 -t public
```

Y abrir `http://localhost:8000/` en el navegador.

## Reindexar por CLI

Normalmente no es necesario: cada carga de archivos desde la web reindexa
automaticamente. Pero puede forzarse manualmente:

```bash
DB_HOST=127.0.0.1 DB_NAME=buscador_ir DB_USER=usuario DB_PASS=clave \
UPLOADS_BASE_DIR=/ruta/a/storage/uploads \
php scripts/reindex.php
```

Es una reconstruccion **total**: cada corrida borra `diccionario` y `posting`
y los reconstruye desde cero a partir de todos los documentos existentes (el
idf depende de N, por lo que no se hace de forma incremental). No modifica ni
borra la tabla `documentos`, solo actualiza `total_terminos` y `fragmento`.

## Tests

```bash
vendor/bin/phpunit
```

Las pruebas marcadas `@group integration` (contra MySQL real) se omiten
automaticamente si no estan definidas `DB_HOST`, `DB_NAME` y `DB_USER`.

> Si en Windows las pruebas que usan SQLite en memoria fallan con
> `could not find driver`, falta habilitar la extension `pdo_sqlite` en el
> `php.ini` que usa tu PHP (`php --ini` muestra cual es):
> ```ini
> extension=pdo_sqlite
> ```

## Estructura de carpetas

```
public/              Docroot: index.php, subir.php, descargar.php, bootstrap.php, styles.css
src/Indexing/        Modulo 2: normalizacion, tokenizacion, construccion del indice invertido
src/Search/          Modulo 3: PostingsRepository, TfIdfScorer, SearchService
src/Storage/         Modulo 1: DocumentRepository, FileLocator, UploadService
src/Support/         Database (unico punto que lee variables de entorno de conexion)
scripts/reindex.php  Envoltorio CLI delgado sobre App\Indexing\Reindexer
sql/schema.sql       Schema de MySQL (documentos, diccionario, posting)
storage/uploads/     Carpeta donde se guardan los .txt subidos (fuera de public/)
samples/             5 documentos de prueba en español
tests/               Indexing, Search, Storage, EndToEnd (PHPUnit)
docs/                Captura de una busqueda, para el entregable
```

## Decisiones de diseño

- **idf suavizado por defecto:** `TfIdfScorer` usa `ln(1 + N/df)` en vez del
  `ln(N/df)` clasico. Con la formula clasica, un termino presente en todos los
  documentos pesa 0, y con una coleccion de un solo documento (N=1) nunca se
  encuentra nada. El idf suavizado evita ambos problemas. Para volver a la
  formula clasica (por ejemplo si el profesor la exige explicitamente),
  instanciar `new TfIdfScorer(idfSuavizado: false)` — ver el comentario en
  `src/Search/TfIdfScorer.php`, que documenta exactamente donde revertirlo.
- **`documentos.ruta_archivo` es relativa**, igual a `nombre_guardado`,
  respecto a `UPLOADS_BASE_DIR`.
- **Duplicado = mismo `hash_sha256`** (contenido identico), aunque el nombre
  difiera. Un archivo con el mismo nombre pero contenido distinto SI se
  almacena como un documento nuevo.
- **tf crudo:** se usa `posting.frecuencia` sin normalizar por
  `total_terminos`, porque el coseno es invariante a escalar el vector del
  documento.
- **Norma del documento:** se calcula con TODOS los terminos del documento
  (no solo los de la consulta), para que una consulta de un solo termino no
  le de coseno 1.0 a cualquier documento que lo contenga.
- **Ranking:** orden por coseno descendente; desempate por suma de tf-idf de
  los terminos de la consulta (descendente) y luego por id de documento.

## API publica para busquedas: `Tokenizer`

`App\Indexing\Text\TokenizerFactory::create(): Tokenizer` es la unica fuente
de configuracion del tokenizador. Tanto el indexador (M2) como el buscador
(M3) deben crear su `Tokenizer` a traves de esta fabrica, para que los
terminos de la consulta coincidan exactamente con los del indice (por
ejemplo, que "año", "ano" y "AÑO" se traten como el mismo termino).

```php
$tokens = App\Indexing\Text\TokenizerFactory::create()->tokenize('texto de la consulta');
```

## Empaquetado para entrega

El entregable es un ZIP con el proyecto (sin dependencias de desarrollo, para
que corra sin Composer) y una captura de una busqueda en `docs/captura-busqueda.png`.

### Bash

```bash
rm -rf /tmp/ADA03_Indexacion
cp -r . /tmp/ADA03_Indexacion
cd /tmp/ADA03_Indexacion
rm -rf .git vendor storage/uploads/* .phpunit.cache
composer install --no-dev --optimize-autoloader
# copiar aqui docs/captura-busqueda.png si aun no esta
cd ..
zip -r ADA03_Indexacion.zip ADA03_Indexacion
```

### PowerShell

```powershell
$destino = "$env:TEMP\ADA03_Indexacion"
Remove-Item -Recurse -Force $destino -ErrorAction SilentlyContinue
Copy-Item -Recurse . $destino
Set-Location $destino
Remove-Item -Recurse -Force .git, vendor, storage\uploads\*, .phpunit.cache -ErrorAction SilentlyContinue
composer install --no-dev --optimize-autoloader
# copiar aqui docs\captura-busqueda.png si aun no esta
Compress-Archive -Path $destino -DestinationPath "$env:TEMP\ADA03_Indexacion.zip" -Force
```
