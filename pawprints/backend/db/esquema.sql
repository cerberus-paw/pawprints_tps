-- La base de PAWPrints (TP5) en MySQL 8.4: el catálogo, las reservas y el personal.
--
-- Corre en un MySQL recién instalado, como root, sin crear nada antes a mano, y se puede volver a
-- correr: todo es «IF NOT EXISTS», así que no borra datos ni falla si ya está creado.
--
--   mysql -u root -p < pawprints/backend/db/esquema.sql
--
-- Los nombres de la base y del usuario son los de la sección [db] de config.example.ini.

CREATE DATABASE IF NOT EXISTS pawprints
    CHARACTER SET utf8mb4
    -- No distingue tildes ni mayúsculas al comparar: «garcia» encuentra «García» con un LIKE
    -- común, como la búsqueda del TP3, que normalizaba el texto en PHP.
    COLLATE utf8mb4_0900_ai_ci;

-- El usuario con el que se conecta el sitio. Sólo puede leer y escribir filas: no crea, cambia ni
-- borra tablas. Si alguien lograra inyectar SQL, no podría tirar la base ni darse más permisos
-- (TP5, consigna 4). Las tablas se crean con root, con este mismo script.
--
-- La clave es la de desarrollo de config.example.ini. En el servidor publicado se cambia después
-- de correr el script, con ALTER USER, y la nueva va sólo en su config.ini.
--
-- Va en «localhost» y en «127.0.0.1»: según cómo resuelva el servidor la conexión por TCP a la
-- máquina local, MySQL la identifica con uno u otro.
CREATE USER IF NOT EXISTS 'pawprints'@'localhost' IDENTIFIED BY 'pawprints';
CREATE USER IF NOT EXISTS 'pawprints'@'127.0.0.1' IDENTIFIED BY 'pawprints';
GRANT SELECT, INSERT, UPDATE, DELETE ON pawprints.* TO 'pawprints'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON pawprints.* TO 'pawprints'@'127.0.0.1';

USE pawprints;

-- El catálogo. Tiene los mismos campos que libros.json. El género, el formato y el autor quedan
-- como texto en el libro: ninguna consigna los trata como entidades propias y no tienen más datos
-- que el nombre.
CREATE TABLE IF NOT EXISTS libros (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    titulo VARCHAR(150) NOT NULL,
    autor VARCHAR(100) NOT NULL,
    genero VARCHAR(50) NOT NULL,
    -- Como lo escribió quien lo cargó, con o sin guiones: así se muestra.
    isbn VARCHAR(20) NOT NULL,
    -- El mismo ISBN sólo con los números. Es el que no se puede repetir: «978-987-1234-56-1» y
    -- «9789871234561» son el mismo libro. Lo calcula la base, así nadie se olvida de llenarlo.
    isbn_digitos VARCHAR(13) AS (REPLACE(REPLACE(isbn, '-', ''), ' ', '')) STORED NOT NULL,
    formato VARCHAR(50) NOT NULL,
    -- En pesos enteros, como en el JSON y en el formulario de carga.
    precio INT UNSIGNED NOT NULL,
    stock SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    resumen VARCHAR(300) NOT NULL,
    descripcion TEXT NOT NULL,
    -- El archivo de la tapa en frontend/img/. Puede faltar: un libro nuevo se carga sin tapa.
    imagen VARCHAR(100) NULL,
    creado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY libros_isbn (isbn_digitos),
    -- Los filtros de la barra lateral del catálogo.
    KEY libros_genero (genero),
    KEY libros_formato (formato)
) ENGINE = InnoDB;

-- Cada reserva hecha desde el sitio, que antes sólo llegaba por correo.
CREATE TABLE IF NOT EXISTS reservas (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    -- RESTRICT: un libro con reservas no se puede borrar, para que el listado del personal nunca
    -- muestre un pedido sin su libro.
    libro_id INT UNSIGNED NOT NULL,
    cantidad TINYINT UNSIGNED NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    correo VARCHAR(254) NOT NULL,
    telefono VARCHAR(20) NOT NULL,
    comentarios VARCHAR(500) NOT NULL DEFAULT '',
    -- Si salió el correo al personal. En 0, la reserva quedó guardada pero el aviso falló.
    aviso_enviado BOOLEAN NOT NULL DEFAULT FALSE,
    creada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT reservas_libro FOREIGN KEY (libro_id) REFERENCES libros (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    -- El listado del personal va de la más nueva a la más vieja.
    KEY reservas_creada (creada)
) ENGINE = InnoDB;

-- Quienes atienden la librería y entran a ver los pedidos. Se dan de alta con
-- bin/crear-personal.php: no hay registro público.
CREATE TABLE IF NOT EXISTS personal (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario VARCHAR(50) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    -- El de password_hash(): nunca la contraseña. 255 es lo que recomienda PHP, porque el
    -- algoritmo por defecto puede cambiar y dar un hash más largo.
    clave_hash VARCHAR(255) NOT NULL,
    creado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY personal_usuario (usuario)
) ENGINE = InnoDB;
