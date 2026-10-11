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
 */
$title = $title ?? 'PAWPrints — Librería en Mercedes';
$description = $description ?? 'Librería independiente en Mercedes, Buenos Aires.';
$activePage = $activePage ?? 'inicio';
$pageClass = $pageClass ?? 'page-inicio';
$extraCss = $extraCss ?? [];
$assetPath = 'frontend/';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($title) ?></title>
  <meta name="description" content="<?= htmlspecialchars($description) ?>">
  <link rel="stylesheet" href="<?= $assetPath ?>css/base.css">
  <link rel="stylesheet" href="<?= $assetPath ?>css/componentes.css">
  <?php foreach ($extraCss as $cssFile): ?>
    <link rel="stylesheet" href="<?= $assetPath ?>css/<?= htmlspecialchars($cssFile) ?>">
  <?php endforeach; ?>
  <link rel="stylesheet" href="<?= $assetPath ?>css/print.css" media="print">
</head>
<body class="<?= htmlspecialchars($pageClass) ?>">

  <!-- ============================================================
       HEADER — Componente dinámico de cabecera y menú de navegación.
       La directiva aria-current="page" se asigna dinámicamente según
       la página activa.
       ============================================================ -->
  <header>

    <p><a href="index.php"><img src="<?= $assetPath ?>img/pawprints-icono.svg" alt="" width="22" height="32">PAWPrints — Librería</a></p>

    <nav aria-label="Navegación principal">
      <ul>
        <li><a href="index.php?action=inicio"<?= $activePage === 'inicio' ? ' aria-current="page"' : '' ?>>Inicio</a></li>
        <li><a href="index.php?action=catalogo"<?= $activePage === 'catalogo' ? ' aria-current="page"' : '' ?>>Catálogo</a></li>
        <li><a href="index.php?action=sobre-nosotros"<?= $activePage === 'sobre-nosotros' ? ' aria-current="page"' : '' ?>>Sobre Nosotros</a></li>
      </ul>
    </nav>

    <search>
      <form action="index.php" method="get">
        <input type="hidden" name="action" value="catalogo">
        <label for="busqueda">Buscar libros</label>
        <input type="search" id="busqueda" name="q" placeholder="Título, autor o género">
        <button type="submit">Buscar</button>
      </form>
    </search>

    <p><a href="#carrito">Carrito</a></p>

  </header>

  <hr>
