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

## Variables de entorno

`src/Support/Database.php` es el unico lugar del codigo que lee estas
variables (via `getenv()`); si no estan definidas, usa los valores por
defecto de la tabla. **No hay soporte de archivo `.env`** todavia (el
`.gitignore` lo excluye por si en el futuro se agrega una libreria como
`vlucas/phpdotenv`, pero hoy mismo no se lee ninguno): hay que definirlas en
el sistema operativo o en la terminal, como se explica mas abajo.

| Variable            | Default                      | Uso                                      |
|---------------------|-------------------------------|-------------------------------------------|
| `DB_HOST`           | `127.0.0.1`                   | Host de MySQL                             |
| `DB_PORT`           | `3306`                        | Puerto de MySQL                           |
| `DB_NAME`           | `buscador_ir`                 | Base de datos                             |
| `DB_USER`           | `root`                        | Usuario de MySQL                          |
| `DB_PASS`           | (vacio)                        | Contrasena de MySQL                       |
| `UPLOADS_BASE_DIR`  | `<raiz del proyecto>/storage/uploads` | Carpeta donde se guardan los .txt subidos |

`DB_PASS` es la que mas problemas suele dar: si tu usuario de MySQL tiene
contrasena y esta variable no esta definida (o esta vacia), la conexion
falla con `Access denied for user 'root'@'localhost' (using password: NO)`.
Puedes definirla de tres formas, de menos a mas permanente:

1. **Solo para un comando** (no queda en el historial de variables de la
   sesion, pero hay que repetirla cada vez).
2. **Para toda la sesion de terminal actual** (alcanza con definirla una vez
   antes de levantar el servidor o correr el CLI; se pierde al cerrar esa
   terminal).
3. **Persistente a nivel de usuario del sistema operativo** (sobrevive a
   reinicios y a abrir terminales nuevas; es la opcion recomendada para no
   tener que acordarse de definirla cada vez). En una maquina compartida,
   ten en cuenta que queda guardada en texto plano en tu perfil de usuario.

La sintaxis exacta de cada opcion depende del sistema operativo; ver la
seccion siguiente.

## Puesta en marcha

Instalacion de dependencias, base de datos y arranque del servidor, por
sistema operativo. Elige la seccion que corresponda a tu entorno.

### Windows (PowerShell)

```powershell
# 1. Dependencias de PHP
composer install

# 2. Base de datos (pide la contrasena de root de MySQL)
Get-Content sql\schema.sql | mysql -u root -p

# 3. Definir DB_PASS para esta terminal (opcion 2 de la tabla anterior)
$env:DB_PASS = "tu_contrasena"

# 4. Arrancar el servidor
php -S localhost:8000 -t public
```

Abrir `http://localhost:8000/` en el navegador.

Consideraciones propias de Windows:

- **PATH:** si `composer`, `php` o `mysql` no se reconocen como comando,
  no estan en el `PATH`. Busca `mysql.exe` (normalmente en
  `C:\Program Files\MySQL\MySQL Server 8.0\bin` o, si usas XAMPP/Laragon,
  dentro de su carpeta de instalacion) y agrega esa carpeta al `PATH` de tu
  usuario:
  ```powershell
  [Environment]::SetEnvironmentVariable(
      "Path",
      [Environment]::GetEnvironmentVariable("Path", "User") + ";C:\ruta\a\la\carpeta\bin",
      "User"
  )
  ```
  Despues de modificar el `PATH` (o de instalar algo que lo modifique, como
  el instalador `.exe` de Composer), **hay que cerrar y volver a abrir toda
  la ventana de VS Code** (no solo la pestana de terminal): la terminal
  integrada hereda el `PATH` del proceso de VS Code, que no se actualiza
  solo.
- **Redireccion con `<`:** no funciona en PowerShell (`El operador '<' esta
  reservado para uso futuro`). Usa `Get-Content archivo | mysql ...` en vez
  de `mysql ... < archivo`, como en el paso 2 de arriba.
- **Persistir `DB_PASS` entre sesiones** (opcion 3): mismo patron que el
  `PATH`, pero con la variable `DB_PASS` en vez de `Path`:
  ```powershell
  [Environment]::SetEnvironmentVariable("DB_PASS", "tu_contrasena", "User")
  ```
  Tambien requiere reabrir la terminal/VS Code para que aplique.
- Si corres `php -S` desde la terminal integrada de VS Code y luego abres el
  archivo del proyecto en el editor con rutas que contienen `Búsqueda y
  Recuperación...` (tildes y enies), no hay problema: PHP las maneja bien
  siempre que la terminal use UTF-8 (el valor por defecto en Windows 10/11).

### Linux / WSL (terminal de Ubuntu)

```bash
# 1. Dependencias del sistema (si no estan instaladas)
sudo apt update
sudo apt install php php-mbstring php-sqlite3 php-mysql mysql-server composer

# 2. Arrancar el servicio de MySQL (en WSL no arranca solo al iniciar la terminal)
sudo service mysql start

# 3. Dependencias de PHP del proyecto
composer install

# 4. Base de datos (pide la contrasena de root de MySQL)
mysql -u root -p < sql/schema.sql

# 5. Definir DB_PASS para esta sesion
export DB_PASS="tu_contrasena"

# 6. Arrancar el servidor
php -S localhost:8000 -t public
```

Abrir `http://localhost:8000/` en el navegador (en WSL, `localhost` apunta
automaticamente a Windows desde WSL2 en versiones recientes).

Consideraciones propias de Linux/WSL:

- En una instalacion nueva de MySQL via `apt`, `root` suele autenticarse con
  `sudo mysql` (plugin `auth_socket`), sin pedir contrasena. Si es tu caso,
  usa `sudo mysql` para el paso 4 y deja `DB_PASS` vacio, o crea un usuario
  con contrasena dedicado al proyecto.
- Si el proyecto vive en el sistema de archivos de Windows (p. ej.
  `/mnt/c/Users/...`) en vez de dentro del filesystem nativo de WSL,
  `composer install` y `php -S` funcionan igual, pero son notablemente mas
  lentos por la capa de traduccion de `/mnt/c`. Si la velocidad es un
  problema, clona el repo dentro de `~/` (filesystem nativo de WSL) en vez
  de usar la carpeta de Windows.
- **Persistir `DB_PASS` entre sesiones** (opcion 3): agrega la linea al
  final de `~/.bashrc` (o `~/.zshrc` si usas zsh) y luego `source ~/.bashrc`:
  ```bash
  echo 'export DB_PASS="tu_contrasena"' >> ~/.bashrc
  source ~/.bashrc
  ```

### macOS

```bash
# 1. Dependencias (con Homebrew; instalar Homebrew primero si no lo tienes)
brew install php mysql composer

# 2. Arrancar MySQL como servicio
brew services start mysql

# 3. Dependencias de PHP del proyecto
composer install

# 4. Base de datos (pide la contrasena de root de MySQL)
mysql -u root -p < sql/schema.sql

# 5. Definir DB_PASS para esta sesion
export DB_PASS="tu_contrasena"

# 6. Arrancar el servidor
php -S localhost:8000 -t public
```

Abrir `http://localhost:8000/` en el navegador.

Consideraciones propias de macOS:

- Una instalacion nueva de MySQL via Homebrew normalmente deja a `root` sin
  contrasena; en ese caso deja `DB_PASS` vacio (o sin definir) y usa
  `mysql -u root` sin `-p`.
- **Persistir `DB_PASS` entre sesiones** (opcion 3): igual que en Linux, en
  el archivo de configuracion de tu shell (`~/.zshrc` por defecto desde
  macOS Catalina en adelante, `~/.bash_profile` si usas bash):
  ```bash
  echo 'export DB_PASS="tu_contrasena"' >> ~/.zshrc
  source ~/.zshrc
  ```
- Si `composer`/`php`/`mysql` no se reconocen tras instalarlos con Homebrew,
  revisa que `/opt/homebrew/bin` (Apple Silicon) o `/usr/local/bin` (Intel)
  este en tu `PATH`; `brew install` suele dejarlo configurado solo, pero
  puede requerir abrir una terminal nueva para que aplique.

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
samples/             8 documentos de prueba en español
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
