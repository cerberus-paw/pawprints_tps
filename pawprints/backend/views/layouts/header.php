<?php
/**
 * Layout Header — PAWPrints
 * Incluye la cabecera del documento y el menú de navegación modular (Punto 2).
 *
 * @var string $title
 * @var string $description
 * @var string $activePage ('inicio' | 'catalogo' | 'sobre-nosotros')
 * @var string $pageClass
 * @var array $extraCss
 * @var array $extraJs   módulos de frontend/js/ que necesita la página, por ejemplo 'paginas/inicio.js'
 * @var array{nombre: string, csrf: string}|null $personal quién del personal ingresó (lo comparte App)
 */
$title = $title ?? 'PAWPrints — Librería en Mercedes';
$description = $description ?? 'Librería independiente en Mercedes, Buenos Aires.';
$activePage = $activePage ?? 'inicio';
$pageClass = $pageClass ?? 'page-inicio';
$extraCss = $extraCss ?? [];
$extraJs = $extraJs ?? [];
$assetPath = '/';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($title) ?></title>
  <meta name="description" content="<?= htmlspecialchars($description) ?>">
  <?php /* Sin un ícono declarado, el navegador pide /favicon.ico por su cuenta, que no existe, y
           la consola muestra un 404 en cada página. */ ?>
  <link rel="icon" href="<?= $assetPath ?>img/pawprints-icono.svg" type="image/svg+xml">
  <link rel="stylesheet" href="<?= $assetPath ?>css/base.css">
  <link rel="stylesheet" href="<?= $assetPath ?>css/componentes.css">
  <?php foreach ($extraCss as $cssFile): ?>
    <link rel="stylesheet" href="<?= $assetPath ?>css/<?= htmlspecialchars($cssFile) ?>">
  <?php endforeach; ?>
  <link rel="stylesheet" href="<?= $assetPath ?>css/print.css" media="print">
  <?php /* Los módulos se ejecutan diferidos: pueden ir en el <head> sin frenar el dibujo de la
           página, y cuando corren el HTML ya está entero. */ ?>
  <script type="module" src="<?= $assetPath ?>js/componentes/historial-busquedas.js"></script>
  <?php foreach ($extraJs as $jsFile): ?>
    <script type="module" src="<?= $assetPath ?>js/<?= htmlspecialchars($jsFile) ?>"></script>
  <?php endforeach; ?>
</head>
<body class="<?= htmlspecialchars($pageClass) ?>">

  <!-- ============================================================
       HEADER — Componente dinámico de cabecera y menú de navegación.
       La directiva aria-current="page" se asigna dinámicamente según
       la página activa.
       ============================================================ -->
  <header>

    <p><a href="/"><img src="<?= $assetPath ?>img/pawprints-icono.svg" alt="" width="22" height="32">PAWPrints — Librería</a></p>

    <nav aria-label="Navegación principal">
      <ul>
        <li><a href="/"<?= $activePage === 'inicio' ? ' aria-current="page"' : '' ?>>Inicio</a></li>
        <li><a href="/catalogo"<?= $activePage === 'catalogo' ? ' aria-current="page"' : '' ?>>Catálogo</a></li>
        <li><a href="/sobre-nosotros"<?= $activePage === 'sobre-nosotros' ? ' aria-current="page"' : '' ?>>Sobre Nosotros</a></li>
<?php if (($personal ?? null) !== null): /* Lo mismo que partials/menu.html.twig. A la izquierda, para no sumar espacios al HTML de siempre. */ ?>
        <li><a href="/libros/nuevo">Cargar libro</a></li>
        <li>
          <form action="/personal/salir" method="post">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($personal['csrf']) ?>">
            <button type="submit" title="Salir de la sesión de <?= htmlspecialchars($personal['nombre']) ?>">Salir</button>
          </form>
        </li>
<?php endif; ?>
      </ul>
    </nav>

    <search>
      <form action="/catalogo" method="get">
        <label for="busqueda">Buscar libros</label>
        <input type="search" id="busqueda" name="q" placeholder="Título, autor o género">
        <button type="submit">Buscar</button>
      </form>
    </search>

    <p><a href="#carrito">Carrito</a></p>

  </header>

  <hr>
