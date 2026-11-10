<?php
/**
 * Vista de libro nuevo cargado — PAWPrints
 * TP4, consigna 2, y TP5: lo que quedó guardado en el catálogo después de un envío del formulario
 * de carga que pasó todas las validaciones
 *
 * @var array<string, mixed> $libro   como lo devuelve LibroRepository::porId()
 * @var bool                 $sinTapa si se cargó sin imagen de tapa
 */
require __DIR__ . '/layouts/header.php';
?>

  <main>

    <section aria-labelledby="titulo-confirmacion">
      <h1 id="titulo-confirmacion">El libro ya está en el catálogo</h1>
      <p>
        Pasó todas las validaciones y quedó guardado. Ya se puede buscar y reservar
        desde el sitio.
      </p>

      <dl>
        <dt>Título</dt>
        <dd><?= htmlspecialchars($libro['titulo']) ?></dd>
        <dt>Autor</dt>
        <dd><?= htmlspecialchars($libro['autor']) ?></dd>
        <dt>Género</dt>
        <dd><?= htmlspecialchars($libro['genero']) ?></dd>
        <dt>Formato</dt>
        <dd><?= htmlspecialchars($libro['formato']) ?></dd>
        <dt>ISBN</dt>
        <dd><?= htmlspecialchars($libro['isbn']) ?></dd>
        <dt>Precio</dt>
        <dd><data value="<?= $libro['precio'] ?>">$<?= number_format($libro['precio'], 0, ',', '.') ?></data></dd>
        <dt>Stock</dt>
        <dd><?= $libro['stock'] ?> <?= $libro['stock'] === 1 ? 'ejemplar' : 'ejemplares' ?></dd>
        <dt>Resumen</dt>
        <dd><?= nl2br(htmlspecialchars($libro['resumen'])) ?></dd>
        <?php if ($libro['descripcion'] !== ''): ?>
        <dt>Descripción</dt>
        <dd><?= nl2br(htmlspecialchars($libro['descripcion'])) ?></dd>
        <?php endif; ?>
        <dt>Tapa</dt>
        <?php if ($sinTapa): ?>
        <dd>Sin tapa: en el catálogo se muestra una genérica.</dd>
        <?php else: ?>
        <dd>
          <img src="/img/<?= htmlspecialchars($libro['imagen']) ?>" alt="Tapa de <?= htmlspecialchars($libro['titulo']) ?>" width="120" height="170" style="max-width: 120px; height: auto; border-radius: var(--r-md); box-shadow: var(--sh-md); display: block; margin-bottom: var(--sp-2);">
        </dd>
        <?php endif; ?>
      </dl>

      <ul>
        <li><a href="/libros/<?= $libro['id'] ?>">Ver la ficha del libro</a></li>
        <li><a href="/libros/nuevo">Cargar otro libro</a></li>
        <li><a href="/catalogo">Ver el catálogo</a></li>
      </ul>
    </section>

  </main>

<?php
require __DIR__ . '/layouts/footer.php';
