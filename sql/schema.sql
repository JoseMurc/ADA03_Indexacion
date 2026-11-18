CREATE DATABASE IF NOT EXISTS buscador_ir
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE buscador_ir;

CREATE TABLE IF NOT EXISTS documentos (
    id_documento INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre_original VARCHAR(255) NOT NULL,
    nombre_guardado VARCHAR(255) NOT NULL UNIQUE,
    ruta_archivo VARCHAR(500) NOT NULL,
    fragmento VARCHAR(50) NOT NULL DEFAULT '',
    hash_sha256 CHAR(64) NOT NULL UNIQUE,
    total_terminos INT UNSIGNED NOT NULL DEFAULT 0,
    fecha_carga TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- ADA 04: almacenar el texto completo del documento
    contenido LONGTEXT NULL,

    -- ADA 04: indice para busquedas por relevancia
    FULLTEXT INDEX idx_contenido (contenido)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS diccionario (
    id_termino INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    termino VARCHAR(191) NOT NULL UNIQUE,
    frecuencia_documental INT UNSIGNED NOT NULL DEFAULT 0,
    frecuencia_total BIGINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS posting (
    id_termino INT UNSIGNED NOT NULL,
    id_documento INT UNSIGNED NOT NULL,
    frecuencia INT UNSIGNED NOT NULL,

    PRIMARY KEY (id_termino, id_documento),
    INDEX idx_posting_documento (id_documento),

    CONSTRAINT fk_posting_termino
        FOREIGN KEY (id_termino)
        REFERENCES diccionario(id_termino)
        ON DELETE CASCADE,

    CONSTRAINT fk_posting_documento
        FOREIGN KEY (id_documento)
        REFERENCES documentos(id_documento)
        ON DELETE CASCADE
) ENGINE=InnoDB;

