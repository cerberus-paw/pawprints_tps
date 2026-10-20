# PAWPrints — Trabajos Prácticos de PAW

Programación en Ambiente Web (11086) — Universidad Nacional de Luján.

Sitio web de la librería **PAWPrints**. Los TPs son incrementales sobre un mismo proyecto: no hay
una carpeta por entrega, hay un solo sitio que va creciendo, y cada etiqueta de Git marca el
estado con el que se entregó.

| Entrega | Tema | Etiqueta |
| --- | --- | --- |
| [TP1](#tp1--maquetado-web) | Maquetado con HTML5 | `tp1` |
| [TP2](#tp2--estilos) | Identidad visual y CSS | `tp2` |
| [TP3](#tp3--programación-backend-1) | Backend en PHP con MVC | `TP3-v3.0.0` |

## Estructura del repositorio

| Ruta | Contenido |
| --- | --- |
| [`pawprints/frontend/`](pawprints/frontend/) | Raíz pública del sitio: front controller, [`css/`](pawprints/frontend/css/), [`img/`](pawprints/frontend/img/) y las maquetas del TP2 |
| [`pawprints/backend/`](pawprints/backend/) | El backend en PHP, fuera de la raíz pública ([arquitectura](#1-arquitectura)) |
| [`diseño/`](diseño/) | [SiteMap](diseño/SiteMap/), [wireframes](diseño/Wireframes/) y logo de la identidad corporativa |
| [`autores.txt`](autores.txt) | Nombre, apellido y legajo de cada integrante |
| [`VERSION`](VERSION) | Versión del proyecto, con versionado semántico |

Las cinco maquetas del TP2: [inicio](pawprints/frontend/index.html) · [catálogo](pawprints/frontend/catalogo.html) ·
[detalle de libro](pawprints/frontend/detalle-libro.html) · [reserva](pawprints/frontend/reserva.html) ·
[sobre nosotros](pawprints/frontend/sobre-nosotros.html).

## Cómo levantar el sitio

Requisitos: **PHP 8.1 o superior** (con el servidor web incorporado y la extensión `openssl`, que
trae cualquier instalación común) y **Git**. No hay base de datos, ni Composer, ni dependencias
que instalar: los libros están en [`data/libros.json`](pawprints/backend/data/libros.json).

```bash
git clone https://github.com/cerberus-paw/pawprints_tps.git
cd pawprints_tps
cp pawprints/backend/config/config.example.ini pawprints/backend/config/config.ini
php -S localhost:8080 -t pawprints/frontend
```

Abrir <http://localhost:8080/>. Para detener el servidor, `Ctrl+C`.

El único archivo de configuración es `pawprints/backend/config/config.ini` (el ejemplo, copiado):

| Clave | Para qué | Valor para trabajar en una máquina propia |
| --- | --- | --- |
| `app.entorno` | Cuánto detalle de un error ve el cliente | El ejemplo trae `"produccion"`. Con `"desarrollo"` la página de error agrega el detalle |
| `correo.transporte` | Qué se hace con cada correo | `"archivo"`, que viene por defecto: los guarda como `.eml` en `pawprints/backend/logs/correos/` en lugar de mandarlos |
| `correo.reservas` | A quién le llega el aviso de cada reserva | Cualquier dirección |
| `smtp.*` | Servidor, usuario y contraseña, sólo con `transporte = "smtp"` | Vacío |

Si el puerto 8080 está ocupado, se usa otro en el mismo comando (`php -S localhost:8090 …`).
El servidor, el log (`pawprints/backend/logs/app.log`) y los correos quedan en la carpeta del
repositorio: no hace falta crear ni dar permisos a nada.

Para verificar que las rutas responden lo que corresponde (con el servidor andando):

```bash
for r in / /sobre-nosotros /catalogo "/catalogo?q=baez" /catalogo/csv /libros/1 /reserva \
         /libros/9999 /xyz; do
  printf '%-22s %s\n' "$r" "$(curl -s -o /dev/null -w '%{http_code}' "http://localhost:8080$r")"
done
```

Las siete primeras responden `200` y las dos últimas, `404`. Para ver una reserva de punta a punta,
completar el formulario en <http://localhost:8080/reserva>: el aviso al personal y la confirmación
quedan como dos `.eml` en `pawprints/backend/logs/correos/`.

Para publicarlo en un servidor, ver el [punto 7 del TP3](#7-publicación).

## TP1 — Maquetado web

### 1. SiteMap

> Realizar un SiteMap del Sitio, planteando la jerarquía de las secciones y las páginas.

En [`diseño/SiteMap/SiteMap-PAWPrints.png`](diseño/SiteMap/SiteMap-PAWPrints.png). Tres secciones
cuelgan de la raíz (Inicio, Catálogo, Acerca de nosotros); Promociones depende de Inicio, y Libro
de Catálogo, con Reserva colgando de Libro. El contacto no es una página: es un bloque transversal
del footer.

### 2. Wireframes

> Diseñar wireframes low-fi en Figma para representar las pantallas principales del sitio web.

Los cinco están en [`diseño/Wireframes/`](diseño/Wireframes/), uno por pantalla. Las cinco
comparten encabezado y pie, que se maquetaron una vez y se replicaron idénticos.

### 3. Maquetado del sitio

> Maquetar el sitio web usando solo HTML5, siguiendo las pautas de los wireframes.

Las cinco páginas se maquetaron **sin una línea de CSS ni de JavaScript**: no había `<style>`,
`<link rel="stylesheet">`, atributos `style=`, `<script>` ni manejadores `on*`. Los estilos
llegaron recién en el TP2, y lo único que se agregó entonces al maquetado fueron los `<link>`.

Como no hay servidor hasta el TP3 no existen los `include`, así que encabezado y pie se repiten en
cada archivo. Se mantienen idénticos byte a byte, y lo único que cambia es el `aria-current="page"`
que marca la sección activa.

### 4. Elementos semánticos de HTML5

> Demostrar la correcta utilización de los elementos semánticos de HTML5. Refleje en cada sección los tags HTML que mejor consideren que se adaptan al contenido de la página a mostrar.

| Elemento | Por qué |
| --- | --- |
| `<header>` `<main>` `<footer>` | Landmarks del documento, hijos directos de `<body>` |
| `<nav>` | Los dos menús, cada uno con su `aria-label` porque al haber dos no se distinguirían |
| `<aside>` | El carrito: complementario y repetido, por eso queda fuera de `<main>` |
| `<search>` | Agrupa el buscador. Aporta el rol por sí solo, así que el `<form>` no lleva `role="search"` |
| `<article>` | Fichas de libro, eventos y promociones: se sostienen fuera de contexto |
| `<section>` | Agrupaciones temáticas, siempre con encabezado |
| `<figure>` / `<figcaption>` | Imágenes con su descripción |
| `<address>` | Datos de contacto del sitio, en el pie |
| `<time datetime>` | Fechas de eventos y fundación, en ISO 8601 |
| `<data value>` | Precios: valor numérico sin perder el formato legible |
| `<dl>` `<dt>` `<dd>` | Pilares de la empresa: pares nombre–descripción |
| `<abbr>` `<small>` `<strong>` | Asterisco de campo obligatorio, letra chica legal, destaque real |

`aria-current="page"` marca el ítem activo: es el equivalente semántico del resaltado del
wireframe, y el CSS del TP2 se cuelga de ese atributo. Todas las imágenes llevan `alt`, cada
`<input>` su `<label>`, y los encabezados no saltan niveles.

### 5. Formulario de reserva

> Crear un formulario de reserva de libro para demostrar el uso adecuado de los formularios HTML, utilizando los tags y atributos que considere que mejor se adapten al tipo de dato del campo, para facilitar la validación.

En [`pawprints/reserva.html`](pawprints/frontend/reserva.html), con los campos agrupados en tres
`<fieldset>`.

| Campo | Tipo | Validación |
| --- | --- | --- |
| Nombre | `text` | `required`, `maxlength="100"` |
| Correo | `email` | `required`, formato, `maxlength="254"` |
| Teléfono | `tel` | `required`, `pattern`, `maxlength="20"` |
| Libro | `<select>` | `required`, lista cerrada del catálogo |
| Cantidad | `number` | `required`, `min="1"`, `max="5"` |
| Comentarios | `<textarea>` | Opcional, `maxlength="500"` |

Los tipos se eligieron para que **la validación la haga el navegador sin JavaScript**. El libro se
elige con un `<select>` y no con texto libre, para garantizar un identificador conocido y evitar
errores de tipeo; la primera opción es un `<option value="" disabled selected>` que guía sin ser un
valor válido. El `<form>` usa `method="post"` porque transmite datos personales que no deben quedar
en la URL ni en el historial. Como indica el enunciado, **no se implementa el procesamiento de la
reserva**.

### Funcionalidades del sitio

| Funcionalidad pedida | Dónde |
| --- | --- |
| Presentación de la librería y sus servicios | [`index.html`](pawprints/frontend/index.html) |
| Tienda en línea y tienda física | [`index.html`](pawprints/frontend/index.html): un enlace de acción por canal |
| Lista de libros con nombre, autor, imagen, precio y descripción | [`catalogo.html`](pawprints/frontend/catalogo.html) |
| Cada libro como tarjeta con opción de ver más | [`catalogo.html`](pawprints/frontend/catalogo.html) |
| Descripción completa, autor y botón de compra o reserva | [`detalle-libro.html`](pawprints/frontend/detalle-libro.html) |
| Formulario de reserva | [`reserva.html`](pawprints/frontend/reserva.html) |
| Promociones, eventos y novedades destacados | [`index.html`](pawprints/frontend/index.html) |
| Historia, misión y servicios a la comunidad | [`sobre-nosotros.html`](pawprints/frontend/sobre-nosotros.html) |
| Dirección, teléfono, correo y redes | Pie de las cinco páginas |

### Decisiones tomadas

- **Carrito.** El wireframe muestra un contador en "2". Se resolvió como enlace de fragmento
  `#carrito` a un `<aside>` de la misma página. El contador se eliminó: afirmaba un estado que el
  sitio no puede tener sin cliente ni servidor. El panel queda vacío hasta el TP3.
- **Carrusel.** Las recomendaciones son un carrusel en el wireframe; necesita JavaScript, así que
  se maquetaron como lista. El comportamiento llega en el TP4, que pide implementarlo.
- **Íconos.** Lupa, carrito y redes van como texto: sin CSS un ícono no se interpreta, y el texto
  es lo accesible.
- **Redes sociales.** Las cuentas no existen, así que se listan sin `href`, para no dejar enlaces
  rotos contra dominios reales.
- **Imágenes.** Viven en [`pawprints/img/`](pawprints/frontend/img/), guardadas en el repositorio y
  referenciadas con rutas relativas para no depender de servicios externos. En el TP1 eran
  marcadores generados; hoy son fotografías de Wikimedia Commons, con su autor y su licencia en
  [`img/CREDITOS.md`](pawprints/frontend/img/CREDITOS.md). Las seis portadas se compusieron sobre esas
  fotos con la tipografía del sitio. Todas son provisorias, hasta que existan fotos de la
  librería.
- **Foto del autor.** Salvador Baez es un personaje inventado, así que la ficha muestra su
  escritorio y no un retrato: una cara real le atribuiría una identidad que no es la suya.

## TP2 — Estilos

> Tomando de base las maquetaciones presentadas, el ejercicio consiste en agregar el contenido CSS necesario para lograr que el maquetado se vea tal como se diseñaron los Wireframes. En caso de ser necesario, ajustar las características de los mismos para que se adapten al manual de identidad corporativa. Tener en cuenta, asimismo, generar el código necesario para que las pantallas se adapten a las versiones mobile, desktop y de impresión.

Se eligió la **Opción Violeta** del manual de identidad
([`diseño/PAWPrints OpcionVioleta.svg`](diseño/PAWPrints%20OpcionVioleta.svg)): violeta `#5C068C`
sobre blanco, con una escala derivada de ese color para hovers, fondos y bordes. El ícono va
arriba a la izquierda en las cinco páginas, recortado de ese mismo archivo a
[`img/pawprints-icono.svg`](pawprints/frontend/img/pawprints-icono.svg).

Del maquetado del TP1 solo se tocaron dos cosas: los `<link>` del `<head>` y una clase en `<body>`
que identifica la página. Todo lo demás se estila con selectores estructurales y semánticos
(`header > p:first-child`, `nav a[aria-current="page"]`, `:nth-of-type`), sin ensuciar el HTML con
clases de presentación.

| Hoja | Alcance |
| --- | --- |
| [`css/base.css`](pawprints/frontend/css/base.css) | Reset, tipografías y variables globales en `:root` |
| [`css/componentes.css`](pawprints/frontend/css/componentes.css) | Lo compartido: header, nav, buscador, carrito, footer y botones |
| [`css/inicio.css`](pawprints/frontend/css/inicio.css) | Hero, libros recomendados, promociones y eventos |
| [`css/sobre-nosotros.css`](pawprints/frontend/css/sobre-nosotros.css) | Hero full-bleed, historia, servicios y ubicación |
| [`css/catalogo.css`](pawprints/frontend/css/catalogo.css) | Buscador, barra de filtros, tarjetas de libro y paginado |
| [`css/detalle-libro.css`](pawprints/frontend/css/detalle-libro.css) | Ficha del libro, reseña del autor y recomendaciones |
| [`css/reserva.css`](pawprints/frontend/css/reserva.css) | Formulario y tarjeta de resumen del libro |
| [`css/print.css`](pawprints/frontend/css/print.css) | Versión de impresión, común a todas las páginas |

**Desktop y mobile.** Tres puntos de corte — 1024 px (el sitio pasa a una columna), 768 px
(tablet y teléfono: el menú se vuelve deslizable y el buscador baja de fila) y 480 px (ajustes
finos de las tarjetas).

**Impresión.** [`print.css`](pawprints/frontend/css/print.css) se carga con `media="print"`: pasa la paleta
a blanco y negro, fija `@page` en A4, evita cortar tarjetas entre hojas, oculta buscador, carrito y
mapas, y expande los enlaces con su URL entre paréntesis.


## TP3 — Programación Backend 1

### 1. Arquitectura

> Defina e implemente una arquitectura preliminar para su aplicación web. Tenga en cuenta que algunas de las problemáticas comunes de las aplicaciones como:
>
> - análisis de la peticiones HTTP;
> - mapeo de URLs en funcionalidades de la aplicación;
> - generación de las respuestas HTTP;
> - generación de registros;
> - persistencia;
> - configuración;
> - generación de diferentes representaciones de la información (documentos HTML, PDF, imágenes, archivos XML, etc.)
>
> Para cada una de ellas, defina a qué módulo de su arquitectura se corresponden. Para tomar ideas, puede valerse de las Recomendaciones de Estándares de PHP definidas por el PHP Framework Interop Group.

PHP con patrón MVC y un **front controller**: todos los pedidos entran por un único script y
recorren el núcleo hasta el controlador de la página. El sitio está dividido en dos carpetas:

- [`pawprints/frontend/`](pawprints/frontend/) es la **raíz pública** del servidor: lo que el
  navegador recibe tal cual (CSS, imágenes y las maquetas del TP2) y el front controller,
  [`index.php`](pawprints/frontend/index.php).
- [`pawprints/backend/`](pawprints/backend/) queda **fuera** de la raíz pública, así que el
  código, las plantillas, la configuración y los logs no se pueden pedir por URL.

```text
pawprints/backend/
├── bootstrap.php      autoload PSR-4: PawPrints\ → src/
├── routes.php         la tabla de rutas del sitio
├── config/            config.example.ini, que se copia a config.ini
├── logs/              app.log
├── data/              los datos del TP3, en JSON
├── views/             plantillas PHP, con encabezado y pie en layouts/
└── src/
    ├── App.php        el recorrido de cada pedido
    ├── Core/          lo transversal: no sabe nada de libros
    ├── Controllers/   uno por página
    └── Models/        entidades y repositorios
```

Cada pedido hace el mismo recorrido, en [`App::handle()`](pawprints/backend/src/App.php):
`Request` → `Router` → controlador (que usa los modelos y una vista) → `Response`, y una línea
en el log.

| Problemática | Módulo | Qué resuelve |
| --- | --- | --- |
| Análisis de las peticiones HTTP | [`Core/Request`](pawprints/backend/src/Core/Request.php) | Método, ruta, query (GET) y campos del formulario (POST). Los controladores no leen `$_GET`, `$_POST` ni `$_SERVER` |
| Mapeo de URLs | [`Core/Router`](pawprints/backend/src/Core/Router.php) y [`routes.php`](pawprints/backend/routes.php) | Método + ruta → controlador, con parámetros en la ruta (`/libros/{id}`). 404 si la ruta no existe y 405 con `Allow` si existe con otro método |
| Generación de las respuestas HTTP | [`Core/Response`](pawprints/backend/src/Core/Response.php) | Estado, cabeceras y cuerpo: HTML, texto, redirección o descarga. El controlador la devuelve en lugar de hacer `echo` y `header()` |
| Generación de registros | [`Core/Logger`](pawprints/backend/src/Core/Logger.php) | `logs/app.log` con los niveles y métodos de PSR-3: una línea por pedido y el detalle de cada error |
| Persistencia | [`Core/JsonRepository`](pawprints/backend/src/Core/JsonRepository.php) y [`Models/`](pawprints/backend/src/Models/) | Cada repositorio lee su archivo de `data/`. En el TP5 pasa a PDO detrás de los mismos métodos |
| Configuración | [`Core/Config`](pawprints/backend/src/Core/Config.php) y [`config.example.ini`](pawprints/backend/config/config.example.ini) | Entorno, zona horaria, log y correo de las reservas, en un `.ini` que no se versiona |
| Representaciones de la información | [`Core/View`](pawprints/backend/src/Core/View.php) con [`views/`](pawprints/backend/views/), y [`Core/Csv`](pawprints/backend/src/Core/Csv.php) | HTML con plantillas PHP (Template View) y CSV para el catálogo. Un PDF o un XML serían otra clase al lado: el controlador elige la representación |

#### Decisiones

- **Sin dependencias de terceros.** En la clase práctica se pidió trabajar con lo mínimo, así
  que el autoload ([`bootstrap.php`](pawprints/backend/bootstrap.php)) y el logger están hechos a
  mano. Siguen PSR-4 y la interfaz de PSR-3: cuando el TP5 sume Composer para Twig, su autoload y
  Monolog los reemplazan sin tocar el resto.
- **URLs.** Lo que identifica un recurso va en la ruta (`/libros/3`); la búsqueda, los filtros y
  el paginado, en la query (`/catalogo?q=borges&pagina=2`), porque son la misma página vista de
  otra forma.
- **Errores.** Un controlador que no encuentra lo que le piden lanza `HttpException::notFound()`.
  Todos los errores terminan en [`App::errorResponse()`](pawprints/backend/src/App.php), que arma
  la página de error y deja el detalle en el log ([punto 6](#6-errores)).
- **Maquetas.** Las cinco páginas `.html` del TP2 siguen en `frontend/` como el maquetado que se
  entregó entonces, pero ya ninguna ruta las usa: las cinco pantallas se sirven desde su
  controlador. El `MaquetaController` provisorio se borró junto con sus rutas.

Requiere PHP 8.1 o superior. Los pasos para levantarlo están en [Cómo levantar el
sitio](#cómo-levantar-el-sitio). El ejemplo de `config.ini` viene con `entorno = "produccion"`: para
ver el detalle de los errores mientras se trabaja, cambiarlo a `"desarrollo"`.

### 4. Catálogo

| Ruta | Qué hace |
| --- | --- |
| `GET /catalogo` | El listado. La búsqueda, los filtros, el orden y la página van en la query |
| `GET /catalogo/csv` | El mismo listado, con los mismos filtros y sin paginar, para abrir en una planilla |
| `GET /libros/{id}` | La ficha del libro, con el botón que lleva a `/reserva?libro={id}` |

[`CatalogoController`](pawprints/backend/src/Controllers/CatalogoController.php) atiende las tres.
[`Models/LibroRepository`](pawprints/backend/src/Models/LibroRepository.php) resuelve la búsqueda,
los filtros y el orden sobre [`data/libros.json`](pawprints/backend/data/libros.json), y
[`Models/AutorRepository`](pawprints/backend/src/Models/AutorRepository.php) trae la reseña del
autor para la ficha. Las vistas son [`catalogo`](pawprints/backend/views/catalogo.view.php) y
[`libro`](pawprints/backend/views/libro.view.php).

**Qué se puede pedir.** Todo viaja en la query, así que cualquier vista del catálogo es un enlace
que se puede compartir, y los formularios de la página conservan en campos ocultos lo que no
cambian:

| Control | Parámetro | Qué hace |
| --- | --- | --- |
| Buscador | `q` | Texto libre sobre título, autor, género e ISBN. Sin tildes y sin distinguir mayúsculas (`ficcion` encuentra «Ficción»); el ISBN se encuentra con guiones o sin ellos |
| Categorías | `genero` | Un género exacto, con la cantidad de libros al lado |
| Formato | `formato` | Tapa dura, tapa blanda o digital |
| Rango de precio | `precio` | Los tres tramos del wireframe |
| Ordenar por | `orden` | `recomendados` (el orden del catálogo), `precio-asc`, `precio-desc` y `titulo-az` |
| Libros por página | `por_pagina` | `4` (la de por defecto), `8`, `12` o `24`. Cualquier otro valor cae en la de por defecto |
| Paginado | `pagina` | Desde 1. Cuántas hay depende de `por_pagina` |

**Lo que no existe.** Un libro cuyo id no está en el catálogo, un id que no es un número y una
página del catálogo más allá de la última responden `404` con
[`HttpException::notFound()`](pawprints/backend/src/Core/HttpException.php). Una búsqueda sin
resultados, en cambio, responde `200`: la página existe y lo que hay para decir es que no se
encontró nada, con un enlace para volver al catálogo completo.

#### Decisiones

- **Una opción por filtro.** El wireframe mostraba casillas, pero
  [`Core/Request`](pawprints/backend/src/Core/Request.php) descarta los parámetros que llegan como
  arreglo (`?genero[]=…`), así que los filtros son botones de radio: una opción por filtro, más la
  de «todas». Filtrar por varios géneros a la vez queda para cuando haya base de datos.
- **Una página que no existe es un 404; una búsqueda vacía, no.** Un `?pagina=7` escrito a mano o
  un enlace viejo no tienen que parecer un catálogo que se quedó sin libros.
- **La cantidad por página es una lista cerrada, y un valor cualquiera no es un error.** `4`, `8`,
  `12` o `24`; cualquier otra cosa cae en la de por defecto, igual que un orden desconocido, porque
  cuántos registros se ven es una forma de mirar el listado y no otro recurso. Que la lista sea
  cerrada evita además que un `?por_pagina=` escrito a mano se traiga el catálogo entero en un solo
  pedido. Cambiarla vuelve a la página 1: con otra cantidad por página, la página 3 de antes ya no
  es la misma.
- **El CSV es otra ruta y no un parámetro.** `/catalogo/csv` identifica a la otra representación
  del mismo recurso, igual que `/libros/{id}` identifica a un libro; la query sigue siendo para
  filtrar. Se descarga con la búsqueda y los filtros puestos, pero sin paginar: en una planilla las
  páginas no significan nada. Lo arma [`Core/Csv`](pawprints/backend/src/Core/Csv.php) y viaja con
  `Content-Disposition`, con la marca de orden de bytes que necesita Excel para las tildes.
- **Sin punto en la ruta del CSV.** `/catalogo.csv` sería más natural, pero el servidor de
  desarrollo de PHP (`php -S`) trata cualquier ruta con extensión como un archivo estático y
  responde `404` sin llegar al front controller.
- **El repositorio devuelve el libro completo.**
  [`CatalogoDeLibros`](pawprints/backend/src/Models/CatalogoDeLibros.php) —la interfaz de la que
  depende la reserva— declara `id`, `titulo`, `autor` y `precio`; el repositorio devuelve además
  género, ISBN, formato, stock y descripción. La reserva usa las cuatro que necesita y el catálogo,
  el resto.
- **Sin `mb_*` ni `iconv`.** Sacar las tildes para comparar se hace con `strtr`, para no depender
  de extensiones que el hosting del punto 7 puede no tener instaladas.
- **Cuatro libros por página.** El catálogo tiene los seis libros del maquetado, así que con cuatro
  por página el paginado se ve y se puede probar. Es una constante del repositorio
  (`LibroRepository::POR_PAGINA`).
- **Los autores, aparte.** La reseña del autor vive en
  [`data/autores.json`](pawprints/backend/data/autores.json) y no en cada libro, porque un autor
  escribe varios libros y su reseña es una sola. El que todavía no tiene foto muestra sólo el texto.

### 5. Reserva
| Ruta | Qué hace |
| --- | --- |
| `GET /reserva` | El formulario. Con `?libro=3` llega con ese libro elegido: es a donde apunta el botón del detalle |
| `POST /reserva` | Valida, manda los correos y redirige con `303` a la confirmación. Si hay errores, vuelve al formulario con `422` |
| `GET /reserva/confirmacion` | La página de reserva recibida |

[`ReservaController`](pawprints/backend/src/Controllers/ReservaController.php) recibe el
formulario; [`Models/Reserva`](pawprints/backend/src/Models/Reserva.php) lo valida, y sólo existe
si es válida; las vistas [`reserva`](pawprints/backend/views/reserva.view.php) y
[`correos/`](pawprints/backend/views/correos/) generan la página y el texto de cada correo.

**Validaciones.** El navegador valida primero con los atributos del TP1 (por eso se sacó el
`novalidate` de la maqueta), y el servidor vuelve a validar todo, porque lo del navegador se
saltea mandando el POST a mano:

| Campo | En el servidor |
| --- | --- |
| Nombre | Obligatorio, hasta 100 caracteres. Los espacios y saltos de línea se juntan en uno |
| Correo | Obligatorio, hasta 254 caracteres, `FILTER_VALIDATE_EMAIL` |
| Teléfono | Obligatorio, el mismo `pattern` del HTML y de 7 a 15 dígitos |
| Libro | Un id que exista en el catálogo: el desplegable se puede reemplazar a mano |
| Cantidad | Entero de 1 a 5 |
| Comentarios | Opcional, hasta 500 caracteres |

Además, ningún campo admite UTF-8 mal formado ni caracteres de control. Un error se muestra al
lado de su campo (`aria-invalid` y `aria-describedby`, así el lector de pantalla lo lee al
enfocarlo) y en un resumen arriba, con lo que la persona ya había escrito.

**Los correos.** Van dos, en texto plano. Al personal (`correo.reservas`), con `Reply-To` del
cliente, para que responder le escriba directo. Al cliente, una confirmación con `Reply-To` del
personal. La reserva no se guarda en ningún lado, así que si el aviso al personal no sale la
reserva se perdería: el cliente vuelve al formulario, con sus datos, y un aviso para que pruebe
de nuevo (`503`). Si falla sólo la confirmación, la reserva sigue adelante y queda en el log.

El envío está en [`Core/Mailer`](pawprints/backend/src/Core/Mailer.php), con dos transportes que
se eligen en `config.ini`:

- `transporte = "archivo"`, para desarrollo: [`FileMailer`](pawprints/backend/src/Core/FileMailer.php)
  guarda cada correo como `.eml` en `backend/logs/correos/`, que se abre con cualquier cliente de
  correo. No hace falta ninguna cuenta para probar la reserva.
- `transporte = "smtp"`, para el sitio publicado: [`SmtpMailer`](pawprints/backend/src/Core/SmtpMailer.php),
  un cliente SMTP con STARTTLS y `AUTH LOGIN`, con los datos de la sección `[smtp]`. Con Gmail:
  `smtp.gmail.com`, puerto 587, `"tls"`, y una [contraseña de aplicación](https://myaccount.google.com/apppasswords).

#### Decisiones

- **SMTP a mano y no `mail()`.** `mail()` depende de que el servidor tenga `sendmail`
  configurado, y la mayoría de los hostings no lo tiene. PHPMailer resolvería lo mismo, pero el
  TP3 va sin dependencias de terceros.
- **Inyección de cabeceras.** El nombre y el correo del cliente van en las cabeceras `To` y
  `Reply-To`: con un salto de línea adentro podrían agregar un `Bcc` a cualquiera. La validación
  no los deja pasar, y [`Email`](pawprints/backend/src/Core/Email.php) rechaza igual cualquier
  cabecera que tenga uno.
- **Abuso del formulario.** Como el sitio le manda un correo a la dirección que se escriba, el
  formulario podría usarse para mandarle correos a terceros. Por eso la confirmación no repite
  los comentarios, y un campo oculto hace de trampa para bots: si llega completo, la reserva se
  descarta sin avisarle al bot.
- **Datos personales.** El log registra el libro y la cantidad de cada reserva, pero no el nombre,
  el correo ni el teléfono.
- **Catálogo.** La reserva depende de
  [`CatalogoDeLibros`](pawprints/backend/src/Models/CatalogoDeLibros.php), una interfaz con
  `todos()` y `porId()` que implementa el repositorio de libros del punto 4.

### 6. Errores

Todo error del sitio termina en la misma página, con el menú y el pie en su lugar, y con el estado
HTTP que corresponde:

| Estado | Cuándo | Qué dice |
| --- | --- | --- |
| `404` | Una ruta que no existe, un libro o una página del catálogo inexistentes | «Página no encontrada», con el mensaje de quien lanzó el error («No encontramos ese libro») |
| `405` | La ruta existe, pero con otro método (un `POST /catalogo`). Lleva la cabecera `Allow` | «Ese pedido no se puede hacer acá» |
| `500` | Cualquier error nuestro | «Algo se rompió de nuestro lado», con una referencia para encontrarlo en el log |

[`App::errorResponse()`](pawprints/backend/src/App.php) es el único lugar por donde salen: elige
el estado, registra lo que haya que investigar y arma la página con la vista
[`error`](pawprints/backend/views/error.view.php) y
[`css/error.css`](pawprints/frontend/css/error.css). Los mensajes de cada estado están en
`App::MENSAJES`, y una [`HttpException`](pawprints/backend/src/Core/HttpException.php) puede traer
el suyo, más preciso.

**Lo que el cliente no ve.** Con `app.entorno = "produccion"` en
[`config.ini`](pawprints/backend/config/config.example.ini), la página de error no tiene más que el
estado, el mensaje y la referencia: ni la clase de la excepción, ni el archivo, ni la línea, ni la
traza, ni una ruta del servidor. Todo eso va a `logs/app.log`, que está fuera de la raíz pública y
no se puede pedir por URL. Con `app.entorno = "desarrollo"`, la misma página agrega el detalle
abajo, para no tener que ir a buscar el log en cada vuelta. Cualquier otro valor, o la clave
ausente, cuenta como producción: el detalle sólo aparece si se lo pide explícitamente.

**Lo que PHP no manda por ese camino.**
[`Core/ErrorHandler`](pawprints/backend/src/Core/ErrorHandler.php) se registra antes de que la
aplicación arranque y tapa los tres agujeros que quedaban:

| | Antes | Ahora |
| --- | --- | --- |
| Un aviso (`Undefined array key`, una división por cero) | Se imprimía en medio del HTML con la ruta del archivo | Se convierte en `ErrorException` y sale como cualquier otro error, por la página de error |
| Una excepción fuera del pedido (falta `config.ini`) | El error de PHP, en pantalla | Al log, y un `500` genérico |
| Un fatal (memoria agotada, un `require` que no existe) | Lo imprimía PHP y terminaba | Al log, y un `500` en texto plano |

#### Decisiones

- **`display_errors` apagado siempre, en los dos entornos.** Cuánto se muestra lo decide la página
  de error, que sabe escapar lo que imprime y dónde ponerlo; un aviso impreso por PHP sale en
  cualquier parte del HTML. De paso, el manejo de errores no depende del `php.ini` del servidor
  donde se publique, que es el punto 7. Por lo mismo se fuerza `zend.exception_ignore_args`: la
  traza no lleva los argumentos de cada llamada, que podrían ser los datos de una reserva.
- **En producción el detalle no se oculta: no se genera.** Esconderlo con CSS lo deja igual en el
  código fuente, a un «ver código» de distancia.
- **Una referencia de seis dígitos.** La misma que se le muestra a la persona queda en la entrada
  del log, así se puede encontrar el error del que vino a hablar sin pedirle que explique nada. No
  dice nada por sí sola, y por eso puede mostrarse.
- **Un `4xx` no se registra aparte.** Es parte del uso normal del sitio —un enlace viejo, una
  dirección escrita a mano— y ya queda en la línea que `App::handle()` escribe por cada pedido. Un
  `5xx` sí, con la traza.
- **El fatal responde en texto plano.** Cuando se llega al manejador de apagado, puede haber sido
  justamente la vista, el autoload o el logger lo que falló: ese `500` no usa nada del núcleo, para
  no fallar mientras se responde un error. Antes descarta los búferes abiertos, así no sale media
  página pegada al mensaje.
- **El `@` se respeta.**
  [`Config`](pawprints/backend/src/Core/Config.php) y
  [`Logger`](pawprints/backend/src/Core/Logger.php) lo usan a propósito para encargarse ellos del
  `.ini` mal escrito o del log sin permisos, así que un aviso silenciado no se convierte en
  excepción.
- **Lo que un controlador imprima por su cuenta** (un `var_dump` olvidado) se muestra en desarrollo
  y se descarta en producción: nadie revisó qué tiene adentro.

### 7. Publicación

> Publique su aplicación web de forma que sea accesible a través de Internet.

**<https://3-23-199-91.sslip.io>**, con HTTPS (certificado de Let's Encrypt) y la versión `3.0.0`.
`sslip.io` es un DNS público que resuelve `3-23-199-91.sslip.io` a la IP `3.23.199.91`, la IP fija
(Elastic IP) del servidor, así que el sitio tiene nombre sin registrar un dominio.

| | |
| --- | --- |
| Servidor | Instancia EC2 de AWS (`t3.micro`, 1 GB, región `us-east-2`), con IP elástica |
| Sistema operativo | Ubuntu 26.04.1 LTS |
| Software | Apache 2.4 con `mod_php`, PHP 8.5, Git y Certbot (los paquetes de Ubuntu) |
| Red | Security group con el 22 (SSH), el 80 y el 443; el 80 redirige a HTTPS |
| Sistema | SSH sólo con clave, `fail2ban` en el SSH y actualizaciones de seguridad automáticas |

Comandos que se corrieron en el servidor, como `ubuntu`:

```bash
sudo apt-get update
sudo apt-get install -y apache2 libapache2-mod-php php-cli git certbot python3-certbot-apache fail2ban unattended-upgrades

sudo git clone --branch TP3 https://github.com/mnomico/paw_tps.git /var/www/pawprints
cd /var/www/pawprints
sudo cp pawprints/backend/config/config.example.ini pawprints/backend/config/config.ini
sudo chown -R www-data:www-data pawprints/backend/logs
```

Archivos de configuración que se modificaron:

| Archivo | Cambio |
| --- | --- |
| `pawprints/backend/config/config.ini` | Copia del ejemplo, que ya trae `entorno = "produccion"`. Para el correo, `transporte = "smtp"` y, en `[smtp]`, `usuario` y `clave` (una contraseña de aplicación de Gmail); `[correo] remitente` tiene que ser esa misma cuenta |
| `/etc/apache2/sites-available/pawprints.conf` | El sitio, que reemplaza al de Apache por defecto (`a2dissite 000-default`, `a2ensite pawprints`) |
| `/etc/php/8.5/apache2/conf.d/99-pawprints.ini` | `pcre.jit=0` |
| `/etc/apache2/conf-available/seguridad-pawprints.conf` | `ServerTokens Prod` y `ServerSignature Off`, para que Apache no diga su versión |

El sitio de Apache, con `frontend/` como raíz pública (el backend, la configuración y los logs
quedan afuera) y el front controller atendiendo todo lo que no sea un archivo:

```apache
<VirtualHost *:80>
    ServerName 3-23-199-91.sslip.io
    DocumentRoot /var/www/pawprints/pawprints/frontend
    <Directory /var/www/pawprints/pawprints/frontend>
        Require all granted
        Options -Indexes
        FallbackResource /index.php
    </Directory>
</VirtualHost>
```

`pcre.jit=0` es necesario en este sistema: Apache corre con la escritura y la ejecución de memoria
separadas, y el JIT de las expresiones regulares no puede reservar memoria ejecutable. Sin esa
línea, PHP avisa, el manejador de errores del [punto 6](#6-errores) lo convierte en excepción y
todas las rutas dan `500`.

HTTPS y el redireccionamiento desde el puerto 80 los configura Certbot, que además renueva el
certificado solo:

```bash
sudo a2dissite 000-default && sudo a2ensite pawprints && sudo a2enmod headers
sudo certbot --apache -d 3-23-199-91.sslip.io --redirect
```

En el sitio con HTTPS se agregan las cabeceras `Strict-Transport-Security` y
`X-Content-Type-Options: nosniff`.

Para actualizar el sitio con una versión nueva:

```bash
cd /var/www/pawprints && sudo git pull --ff-only
```

Cómo se comprobó que anda (desde otra máquina):

```bash
curl -sI http://3-23-199-91.sslip.io/               # 301 a https
for r in / /sobre-nosotros /catalogo "/catalogo?q=baez" /catalogo/csv /libros/1 /reserva; do
  printf '%-22s %s\n' "$r" "$(curl -s -o /dev/null -w '%{http_code}' "https://3-23-199-91.sslip.io$r")"
done                                                 # todas 200
curl -s -o /dev/null -w '%{http_code}\n' https://3-23-199-91.sslip.io/libros/9999   # 404
curl -s -o /dev/null -w '%{http_code}\n' https://3-23-199-91.sslip.io/backend/config/config.ini   # 404
```

## Entorno de desarrollo

| Componente | Versión |
| --- | --- |
| Sistema operativo | CachyOS (Arch Linux), kernel 7.2.2 |
| Navegador | Mozilla Firefox 155.0 |
| Servidor de pruebas | PHP 8.5.11 (`php -S`) |
| Control de versiones | Git 2.55.0 |
| Wireframes · SiteMap | Figma · Whimsical |

El sitio se sirve con PHP (el servidor incorporado en desarrollo, Apache en el servidor publicado)
y funciona en cualquier navegador moderno. Las maquetas del TP2 siguen siendo HTML y CSS estáticos.
