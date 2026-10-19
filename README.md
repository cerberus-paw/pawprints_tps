# PAWPrints — Trabajos Prácticos de PAW

Programación en Ambiente Web (11086) — Universidad Nacional de Luján.

Sitio web de la librería **PAWPrints**. Los TPs son incrementales sobre un mismo proyecto: no hay
una carpeta por entrega, hay un solo sitio que va creciendo, y cada etiqueta de Git marca el
estado con el que se entregó.

| Entrega | Tema | Etiqueta |
| --- | --- | --- |
| [TP1](#tp1--maquetado-web) | Maquetado con HTML5 | `tp1` |
| [TP2](#tp2--estilos) | Identidad visual y CSS | `tp2` |
| [TP3](#tp3--programación-backend-1) | Backend en PHP con MVC | `tp3` |

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

HTML y CSS estáticos: no requiere compilación, base de datos ni dependencias, y no hay archivos
de configuración que modificar.

```bash
git clone https://github.com/mnomico/paw_tps.git
cd paw_tps/pawprints
python3 -m http.server 8080
```

Abrir <http://localhost:8080/index.html>. Para detener el servidor, `Ctrl+C`.

También se pueden abrir los archivos directamente, sin servidor, con `xdg-open index.html`
(Linux), `open` (macOS) o `start` (Windows).

Para verificar que las cinco páginas responden `200`:

```bash
for f in *.html; do
  printf '%-26s %s\n' "$f" \
    "$(curl -s -o /dev/null -w '%{http_code}' "http://localhost:8080/$f")"
done
```

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
| [`css/reserva.css`](pawprints/frontend/css/reserva.css) | Formulario y tarjeta de resumen del libro |
| [`css/print.css`](pawprints/frontend/css/print.css) | Versión de impresión, común a todas las páginas |

**Desktop y mobile.** Tres puntos de corte — 1024 px (el sitio pasa a una columna), 768 px
(tablet y teléfono: el menú se vuelve deslizable y el buscador baja de fila) y 480 px (ajustes
finos de las tarjetas).

**Impresión.** [`print.css`](pawprints/frontend/css/print.css) se carga con `media="print"`: pasa la paleta
a blanco y negro, fija `@page` en A4, evita cortar tarjetas entre hojas, oculta buscador, carrito y
mapas, y expande los enlaces con su URL entre paréntesis.

**Pendiente:** [`catalogo.html`](pawprints/frontend/catalogo.html) y
[`detalle-libro.html`](pawprints/frontend/detalle-libro.html) todavía no tienen su hoja propia.

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
  Todos los errores terminan en [`App::errorResponse()`](pawprints/backend/src/App.php), que por
  ahora responde un texto genérico sin rutas ni código y deja el detalle en el log; el punto 6 se
  apoya ahí.
- **Maquetas.** `catalogo.html` y `detalle-libro.html` siguen en `frontend/` hasta que el punto 4
  tenga su controlador. Mientras tanto, `/catalogo` y `/libros/{id}` redirigen a ellas desde
  [`MaquetaController`](pawprints/backend/src/Controllers/MaquetaController.php), para que el menú
  no quede roto.

Requiere PHP 8.1 o superior. Para probarlo desde la raíz del repositorio:

```bash
cp pawprints/backend/config/config.example.ini pawprints/backend/config/config.ini
php -S localhost:8080 -t pawprints/frontend
```

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

## Entorno de desarrollo

| Componente | Versión |
| --- | --- |
| Sistema operativo | CachyOS (Arch Linux), kernel 7.2.2 |
| Navegador | Mozilla Firefox 155.0 |
| Servidor de pruebas | Python 3.14.7 (`http.server`) |
| Control de versiones | Git 2.55.0 |
| Wireframes · SiteMap | Figma · Whimsical |

Al ser HTML y CSS estáticos, el sitio funciona en cualquier navegador moderno y con cualquier
servidor de archivos estáticos.
