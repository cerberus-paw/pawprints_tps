<?php
/**
 * Vista de la ficha de un libro — PAWPrints
 * Tarea 4: catálogo, detalle del libro (Agustina)
 *
 * Mantiene la estructura de detalle-libro.html del TP2: detalle-libro.css se cuelga de los
 * `aria-labelledby` de cada sección y del orden de los párrafos.
 *
 * @var array<string, mixed>      $libro
 * @var array<string, mixed>|null $autor        null si el autor todavía no tiene reseña
 * @var list<array<string, mixed>> $recomendados otros libros del catálogo
 */
require __DIR__ . '/layouts/header.php';

$pesos = static fn (int $monto): string => '$' . number_format($monto, 0, ',', '.');
?>

  <main>

    <!-- ============================================================
         FICHA DEL LIBRO — el botón de reservar lleva a /reserva con
         el libro ya elegido (punto 5).
         ============================================================ -->
    <article aria-labelledby="titulo-libro-principal">
      <header>
        <p><small>GÉNERO: <?= htmlspecialchars($libro['genero']) ?> &bull; ISBN: <?= htmlspecialchars($libro['isbn']) ?></small></p>
        <h1 id="titulo-libro-principal"><?= htmlspecialchars($libro['titulo']) ?></h1>
        <p>Por <strong><?= htmlspecialchars($libro['autor']) ?></strong></p>
      </header>

      <figure>
        <img src="/img/<?= htmlspecialchars($libro['imagen']) ?>"
             alt="Portada del libro <?= htmlspecialchars($libro['titulo']) ?> de <?= htmlspecialchars($libro['autor']) ?>">
        <figcaption>Portada oficial - <?= htmlspecialchars($libro['titulo']) ?></figcaption>
      </figure>

      <section aria-labelledby="titulo-descripcion">
        <h2 id="titulo-descripcion">Descripción del libro</h2>
        <p><?= htmlspecialchars($libro['descripcion']) ?></p>
      </section>

      <section aria-labelledby="titulo-comprar-reservar">
        <h2 id="titulo-comprar-reservar" hidden>Opciones de compra y reserva</h2>
        <p>
          <strong>Precio:</strong> <data value="<?= $libro['precio'] ?>"><?= $pesos($libro['precio']) ?></data>
          <?php if ($libro['stock'] > 0): ?>
          <mark>EN STOCK</mark>
          <?php else: ?>
          <mark>SIN STOCK</mark>
          <?php endif; ?>
        </p>

        <p>
          <a href="#carrito">Agregar al carrito</a>
          <?php if ($libro['stock'] > 0): ?>
          <!-- El formulario de reserva lo recibe en el desplegable ya elegido. -->
          <a href="/reserva?libro=<?= $libro['id'] ?>">Reservar en tienda</a>
          <?php endif; ?>
        </p>
      </section>
    </article>

    <?php if ($autor !== null): ?>
    <hr>

    <!-- ============================================================
         SOBRE EL AUTOR — la reseña vive en data/autores.json, porque
         un autor escribe varios libros y su reseña es una sola.
         ============================================================ -->
    <section aria-labelledby="titulo-sobre-autor">
      <h2 id="titulo-sobre-autor">Sobre el Autor</h2>

      <article>
        <?php if ($autor['imagen'] !== null): ?>
        <figure>
          <!-- No se usa un retrato: los autores son personajes inventados y poner la cara de una
               persona real le atribuiría una identidad que no es la suya. -->
          <img src="/img/<?= htmlspecialchars($autor['imagen']) ?>" alt="<?= htmlspecialchars((string) $autor['imagen_alt']) ?>">
          <figcaption><?= htmlspecialchars((string) $autor['imagen_alt']) ?></figcaption>
        </figure>
        <?php endif; ?>
        <div>
          <header>
            <h3><?= htmlspecialchars($autor['nombre']) ?></h3>
          </header>
          <p><?= htmlspecialchars($autor['bio']) ?></p>
        </div>
      </article>
    </section>
    <?php endif; ?>

    <?php if ($recomendados !== []): ?>
    <hr>

    <!-- ============================================================
         RECOMENDACIONES — otros libros del catálogo, primero los del
         mismo género.
         ============================================================ -->
    <section aria-labelledby="titulo-recomendaciones">
      <h2 id="titulo-recomendaciones">Recomendaciones</h2>

      <ul>
        <?php foreach ($recomendados as $otro): ?>
        <li>
          <article>
            <figure>
              <img src="/img/<?= htmlspecialchars($otro['imagen']) ?>" alt="Portada del libro <?= htmlspecialchars($otro['titulo']) ?>">
              <figcaption><?= htmlspecialchars($otro['formato']) ?></figcaption>
            </figure>
            <header>
              <p><small>GÉNERO: <?= htmlspecialchars($otro['genero']) ?></small></p>
              <h3><a href="/libros/<?= $otro['id'] ?>"><?= htmlspecialchars($otro['titulo']) ?></a></h3>
              <p><strong>Autor:</strong> <?= htmlspecialchars($otro['autor']) ?></p>
            </header>
            <p><strong>Precio:</strong> <data value="<?= $otro['precio'] ?>"><?= $pesos($otro['precio']) ?></data></p>
            <p><a href="/libros/<?= $otro['id'] ?>">Ver detalles &rarr;</a></p>
          </article>
        </li>
        <?php endforeach; ?>
      </ul>
    </section>
    <?php endif; ?>

  </main>

<?php
require __DIR__ . '/layouts/footer.php';
