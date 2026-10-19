<?php
/**
 * Vista del Catálogo — PAWPrints
 * Tarea 4: catálogo con listado, búsqueda, filtros, orden, paginado y CSV (Agustina)
 *
 * Mantiene la estructura de catalogo.html del TP2 porque catalogo.css se cuelga de ella con
 * selectores estructurales (`main > section:nth-of-type(1)`, `article > p:nth-of-type(2)`…):
 * mover un elemento de lugar le cambia los estilos.
 *
 * @var list<array<string, mixed>> $libros   los de esta página
 * @var int    $total     resultados de la búsqueda, en todas las páginas
 * @var int    $pagina    la página que se está viendo, desde 1
 * @var int    $paginas   cuántas hay
 * @var int    $porPagina cuántos libros entran en una página
 * @var list<int>             $cantidades las que se pueden elegir en el desplegable
 * @var array<string, string> $filtros  los de la query, sin los vacíos
 * @var array<string, int>    $generos  género => cuántos libros
 * @var array<string, int>    $formatos formato => cuántos libros
 * @var array<string, string> $rangos   valor => etiqueta del rango de precio
 * @var array<string, string> $ordenes  valor => etiqueta del desplegable
 */
require __DIR__ . '/layouts/header.php';

$pesos = static fn (int $monto): string => '$' . number_format($monto, 0, ',', '.');

/**
 * Un enlace que conserva la vista actual y cambia sólo lo que se le pasa; un valor vacío saca
 * ese parámetro. Devuelve la URL ya escapada, lista para un href.
 */
$url = static function (string $base, array $cambios = []) use ($filtros): string {
    $query = array_filter(
        $cambios + $filtros,
        static fn ($valor): bool => $valor !== '' && $valor !== null,
    );

    return htmlspecialchars($base . ($query === [] ? '' : '?' . http_build_query($query)));
};

/** Un campo oculto por filtro activo, para que cada formulario cambie sólo lo suyo. */
$ocultos = static function (array $excepto) use ($filtros): void {
    foreach ($filtros as $nombre => $valor) {
        if (in_array($nombre, $excepto, true)) {
            continue;
        }
        printf(
            '<input type="hidden" name="%s" value="%s">' . "\n",
            htmlspecialchars($nombre),
            htmlspecialchars($valor),
        );
    }
};

$buscado = $filtros['q'] ?? '';
$generoActivo = $filtros['genero'] ?? '';
$formatoActivo = $filtros['formato'] ?? '';
$precioActivo = $filtros['precio'] ?? '';
$ordenActivo = $filtros['orden'] ?? '';

// Un identificador válido para el id de cada control, a partir del valor del filtro.
$slug = static fn (string $valor): string => preg_replace('/[^a-z0-9]+/', '-', strtolower(
    strtr($valor, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', '&' => 'y']),
)) ?? '';
?>

  <main>

    <!-- ============================================================
         PRESENTACIÓN Y BÚSQUEDA — el buscador conserva los filtros
         activos en campos ocultos, así cada formulario de la página
         cambia una sola cosa de la vista.
         ============================================================ -->
    <section aria-labelledby="titulo-catalogo">
      <h1 id="titulo-catalogo">Explora nuestro catálogo</h1>
      <p>Navega entre nuestra colección de libros físicos y digitales.</p>

      <search>
        <form action="/catalogo" method="get">
          <label for="buscar-catalogo">Buscar por título, autor, género o ISBN:</label>
          <input type="search" id="buscar-catalogo" name="q" value="<?= htmlspecialchars($buscado) ?>"
                 maxlength="100" placeholder="Buscar por título, autor, género o ISBN...">
          <button type="submit">Buscar</button>
          <?php $ocultos(['q']); ?>
        </form>
      </search>
    </section>

    <hr>

    <section aria-labelledby="titulo-seccion-catalogo">
      <h2 id="titulo-seccion-catalogo">Catálogo de Libros</h2>

      <!-- ============================================================
           FILTROS — una sola opción por filtro: Core\Request descarta
           las queries con arrays, así que son radios y no casillas.
           Las cantidades se cuentan sobre el catálogo completo.
           ============================================================ -->
      <aside aria-labelledby="titulo-filtros">
        <h3 id="titulo-filtros">Filtros</h3>

        <form action="/catalogo" method="get">

          <fieldset>
            <legend>Categorías</legend>
            <ul>
              <li>
                <input type="radio" id="cat-todas" name="genero" value=""<?= $generoActivo === '' ? ' checked' : '' ?>>
                <label for="cat-todas">Todas <small>(<?= array_sum($generos) ?>)</small></label>
              </li>
              <?php foreach ($generos as $genero => $cuantos): ?>
              <li>
                <input type="radio" id="cat-<?= $slug($genero) ?>" name="genero" value="<?= htmlspecialchars($genero) ?>"<?= $generoActivo === $genero ? ' checked' : '' ?>>
                <label for="cat-<?= $slug($genero) ?>"><?= htmlspecialchars($genero) ?> <small>(<?= $cuantos ?>)</small></label>
              </li>
              <?php endforeach; ?>
            </ul>
          </fieldset>

          <fieldset>
            <legend>Formato</legend>
            <ul>
              <li>
                <input type="radio" id="form-todos" name="formato" value=""<?= $formatoActivo === '' ? ' checked' : '' ?>>
                <label for="form-todos">Todos</label>
              </li>
              <?php foreach ($formatos as $formato => $cuantos): ?>
              <li>
                <input type="radio" id="form-<?= $slug($formato) ?>" name="formato" value="<?= htmlspecialchars($formato) ?>"<?= $formatoActivo === $formato ? ' checked' : '' ?>>
                <label for="form-<?= $slug($formato) ?>"><?= htmlspecialchars($formato) ?> <small>(<?= $cuantos ?>)</small></label>
              </li>
              <?php endforeach; ?>
            </ul>
          </fieldset>

          <fieldset>
            <legend>Rango de Precio</legend>
            <ul>
              <li>
                <input type="radio" id="precio-todos" name="precio" value=""<?= $precioActivo === '' ? ' checked' : '' ?>>
                <label for="precio-todos">Cualquier precio</label>
              </li>
              <?php foreach ($rangos as $valor => $etiqueta): ?>
              <li>
                <input type="radio" id="precio-<?= $slug($valor) ?>" name="precio" value="<?= htmlspecialchars($valor) ?>"<?= $precioActivo === $valor ? ' checked' : '' ?>>
                <label for="precio-<?= $slug($valor) ?>"><?= htmlspecialchars($etiqueta) ?></label>
              </li>
              <?php endforeach; ?>
            </ul>
          </fieldset>

          <button type="submit">Aplicar Filtros</button>
          <?php $ocultos(['genero', 'formato', 'precio']); ?>
        </form>
      </aside>

      <!-- ============================================================
           RESULTADOS — el listado de esta página, el paginado y el
           mismo listado en CSV.
           ============================================================ -->
      <section aria-labelledby="titulo-lista-libros">
        <h3 id="titulo-lista-libros">Resultados</h3>

        <p>
          Mostrando <strong><?= $total ?> <?= $total === 1 ? 'libro' : 'libros' ?></strong>
          <?php if ($buscado !== ''): ?>
          para <em>«<?= htmlspecialchars($buscado) ?>»</em>
          <?php endif; ?>
          <?php if ($generoActivo !== ''): ?>
          de <em><?= htmlspecialchars($generoActivo) ?></em>
          <?php endif; ?>
          <?php if ($paginas > 1): ?>
          — página <?= $pagina ?> de <?= $paginas ?>
          <?php endif; ?>
        </p>

        <!-- El orden y la cantidad por página van en la misma barra y con un solo botón: las dos
             cambian cómo se ve este mismo listado. Sin el campo oculto de «pagina», cambiar
             cualquiera de las dos vuelve a la primera página, que es lo que se espera: con otra
             cantidad por página, la página 3 de antes ya no es la misma. -->
        <form action="/catalogo" method="get">
          <label for="ordenar-libros">Ordenar por:</label>
          <select id="ordenar-libros" name="orden">
            <?php foreach ($ordenes as $valor => $etiqueta): ?>
            <option value="<?= htmlspecialchars($valor) ?>"<?= $ordenActivo === $valor ? ' selected' : '' ?>><?= htmlspecialchars($etiqueta) ?></option>
            <?php endforeach; ?>
          </select>

          <label for="libros-por-pagina">Libros por página:</label>
          <select id="libros-por-pagina" name="por_pagina">
            <?php foreach ($cantidades as $cantidad): ?>
            <option value="<?= $cantidad ?>"<?= $porPagina === $cantidad ? ' selected' : '' ?>><?= $cantidad ?></option>
            <?php endforeach; ?>
          </select>

          <!-- «Actualizar» y no «Aplicar»: el botón de los filtros, al costado, ya dice «Aplicar
               Filtros», y dos botones parecidos en la misma pantalla se confunden. -->
          <button type="submit">Actualizar</button>
          <?php $ocultos(['orden', 'por_pagina']); ?>
        </form>

        <?php if ($libros === []): ?>
        <p>
          No encontramos libros con esa búsqueda.
          <a href="/catalogo">Ver todo el catálogo</a>.
        </p>
        <?php else: ?>
        <ul>
          <?php foreach ($libros as $libro): ?>
          <li>
            <article>
              <figure>
                <img src="/img/<?= htmlspecialchars($libro['imagen']) ?>" alt="Portada del libro <?= htmlspecialchars($libro['titulo']) ?>">
                <figcaption><?= htmlspecialchars($libro['formato']) ?></figcaption>
              </figure>
              <header>
                <p><small>GÉNERO: <?= htmlspecialchars($libro['genero']) ?></small></p>
                <h4><a href="/libros/<?= $libro['id'] ?>"><?= htmlspecialchars($libro['titulo']) ?></a></h4>
                <p><strong>Autor:</strong> <?= htmlspecialchars($libro['autor']) ?></p>
              </header>
              <p><small><strong>Descripción:</strong> <?= htmlspecialchars($libro['resumen']) ?></small></p>
              <p><strong>Precio:</strong> <data value="<?= $libro['precio'] ?>"><?= $pesos($libro['precio']) ?></data></p>
              <p><a href="/libros/<?= $libro['id'] ?>">Ver detalles &rarr;</a></p>
            </article>
          </li>
          <?php endforeach; ?>
        </ul>

        <?php if ($paginas > 1): ?>
        <nav aria-label="Paginado del catálogo">
          <ul>
            <?php if ($pagina > 1): ?>
            <li><a href="<?= $url('/catalogo', ['pagina' => $pagina - 1 > 1 ? (string) ($pagina - 1) : '']) ?>" rel="prev">&larr; Anterior</a></li>
            <?php endif; ?>
            <?php for ($n = 1; $n <= $paginas; $n++): ?>
            <li>
              <?php if ($n === $pagina): ?>
              <!-- aria-current marca la página actual del paginado, igual que el menú marca la sección. -->
              <a href="<?= $url('/catalogo', ['pagina' => $n > 1 ? (string) $n : '']) ?>" aria-current="page"><?= $n ?></a>
              <?php else: ?>
              <a href="<?= $url('/catalogo', ['pagina' => $n > 1 ? (string) $n : '']) ?>"><?= $n ?></a>
              <?php endif; ?>
            </li>
            <?php endfor; ?>
            <?php if ($pagina < $paginas): ?>
            <li><a href="<?= $url('/catalogo', ['pagina' => (string) ($pagina + 1)]) ?>" rel="next">Siguiente &rarr;</a></li>
            <?php endif; ?>
          </ul>
        </nav>
        <?php endif; ?>
        <?php endif; ?>

        <p>
          <!-- La otra representación del mismo listado: se descarga con la búsqueda y los
               filtros puestos, pero sin paginar. -->
          <a href="<?= $url('/catalogo/csv') ?>" download>Descargar estos resultados en CSV</a>
        </p>
      </section>
    </section>

  </main>

<?php
require __DIR__ . '/layouts/footer.php';
