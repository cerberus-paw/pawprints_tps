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
 * @var array<string, string> $ordenes  valor => etiqueta del desplegable
 */
require __DIR__ . '/layouts/header.php';

$pesos = static fn (int $monto): string => '$' . number_format($monto, 0, ',', '.');

/**
 * Un enlace que conserva la vista actual y cambia sólo lo que se le pasa; un valor vacío saca
 * ese parámetro. Devuelve la URL ya escapada, lista para un href. Los filtros van en el orden de
 * FILTROS y la página al final, igual que la URL que arma el JS del catálogo.
 */
$url = static function (string $base, array $cambios = []) use ($filtros): string {
    $query = array_filter(
        array_merge($filtros, $cambios),
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
$precioDesde = $filtros['precio_desde'] ?? '';
$precioHasta = $filtros['precio_hasta'] ?? '';
$ordenActivo = $filtros['orden'] ?? '';

// El tramo del listado que se ve en esta página, contado desde 1.
$primero = ($pagina - 1) * $porPagina + 1;
$ultimo = $primero + count($libros) - 1;

// Un identificador válido para el id de cada control, a partir del valor del filtro.
$slug = static fn (string $valor): string => preg_replace('/[^a-z0-9]+/', '-', strtolower(
    strtr($valor, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', '&' => 'y']),
)) ?? '';

/**
 * Una tarjeta del listado. Sin libro imprime la tarjeta vacía del <template> que completa el JS
 * del catálogo: así el HTML de la tarjeta está en un solo lugar y los estilos, que se cuelgan de
 * la estructura, valen igual para las dos. El JS completa cada [data-campo] con textContent.
 */
$tarjeta = static function (?array $libro) use ($pesos): void {
    $campo = static fn (string $nombre): string => $libro === null ? '' : htmlspecialchars((string) $libro[$nombre]);
    $ficha = $libro === null ? '' : "/libros/{$libro['id']}";
    $imagen = $libro === null ? '' : '/img/' . htmlspecialchars($libro['imagen']);
    $alt = $libro === null ? '' : 'Portada del libro ' . htmlspecialchars($libro['titulo']);
    $precio = $libro === null ? '' : $pesos($libro['precio']);
    ?>
          <li>
            <article>
              <figure>
                <img src="<?= $imagen ?>" alt="<?= $alt ?>">
                <figcaption data-campo="formato"><?= $campo('formato') ?></figcaption>
              </figure>
              <header>
                <p><small>GÉNERO: <span data-campo="genero"><?= $campo('genero') ?></span></small></p>
                <h4><a href="<?= $ficha ?>" data-campo="titulo"><?= $campo('titulo') ?></a></h4>
                <p><strong>Autor:</strong> <span data-campo="autor"><?= $campo('autor') ?></span></p>
              </header>
              <p><small><strong>Descripción:</strong> <span data-campo="resumen"><?= $campo('resumen') ?></span></small></p>
              <p><strong>Precio:</strong> <data value="<?= $campo('precio') ?>"><?= $precio ?></data></p>
              <p><a href="<?= $ficha ?>">Ver detalles &rarr;</a></p>
            </article>
          </li>
    <?php
};
?>

  <!-- data-catalogo-json: de dónde trae el JS el catálogo entero para filtrar en el navegador. -->
  <main data-catalogo-json="/catalogo/json">

    <!-- ============================================================
         PRESENTACIÓN Y BÚSQUEDA — el buscador conserva los filtros
         activos en campos ocultos, así cada formulario de la página
         cambia una sola cosa de la vista.
         ============================================================ -->
    <section aria-labelledby="titulo-catalogo">
      <h1 id="titulo-catalogo">Explora nuestro catálogo</h1>
      <p>Navega entre nuestra colección de libros físicos y digitales.</p>

      <search>
        <form action="/catalogo" method="get" id="catalogo-busqueda">
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

        <form action="/catalogo" method="get" id="catalogo-filtros">

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

          <!-- El rango de precio son dos montos libres, en pesos e inclusivos. Un campo vacío es
               «sin límite»; si el mínimo supera al máximo, el servidor no aplica el filtro. -->
          <fieldset>
            <legend>Rango de Precio</legend>
            <div class="rango-precio">
              <p>
                <label for="precio-desde">Desde ($)</label>
                <input type="number" id="precio-desde" name="precio_desde" value="<?= htmlspecialchars($precioDesde) ?>"
                       min="0" step="1" inputmode="numeric" placeholder="Sin mínimo">
              </p>
              <p>
                <label for="precio-hasta">Hasta ($)</label>
                <input type="number" id="precio-hasta" name="precio_hasta" value="<?= htmlspecialchars($precioHasta) ?>"
                       min="0" step="1" inputmode="numeric" placeholder="Sin máximo">
              </p>
            </div>
          </fieldset>

          <button type="submit">Aplicar Filtros</button>
          <?php $ocultos(['genero', 'formato', 'precio_desde', 'precio_hasta']); ?>
        </form>
      </aside>

      <!-- ============================================================
           RESULTADOS — el listado de esta página, el paginado y el
           mismo listado en CSV.
           ============================================================ -->
      <section aria-labelledby="titulo-lista-libros">
        <template id="plantilla-libro">
          <?php $tarjeta(null); ?>
        </template>

        <h3 id="titulo-lista-libros" tabindex="-1">Resultados</h3>

        <!-- «Mostrando X–Y de Z»: qué tramo del listado se ve y cuántos libros hay en total. El
             JS del catálogo arma este mismo texto cuando redibuja el listado, y aria-live hace que
             el lector de pantalla lo anuncie. -->
        <p id="catalogo-resumen" aria-live="polite">
          <?php if ($total === 0): ?>
          Mostrando <strong>0 libros</strong>
          <?php else: ?>
          Mostrando <strong><?= $primero ?>–<?= $ultimo ?></strong> de
          <strong><?= $total ?> <?= $total === 1 ? 'libro' : 'libros' ?></strong>
          <?php endif; ?>
        </p>

        <!-- El orden y la cantidad por página van en la misma barra y con un solo botón: las dos
             cambian cómo se ve este mismo listado. Sin el campo oculto de «pagina», cambiar
             cualquiera de las dos vuelve a la primera página, que es lo que se espera: con otra
             cantidad por página, la página 3 de antes ya no es la misma. -->
        <form action="/catalogo" method="get" id="catalogo-vista">
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
        <p data-sin-resultados>
          No encontramos libros con esa búsqueda.
          <a href="/catalogo">Ver todo el catálogo</a>.
        </p>
        <?php else: ?>
        <ul>
          <?php foreach ($libros as $libro): ?>
          <?php $tarjeta($libro); ?>
          <?php endforeach; ?>
        </ul>

        <?php if ($paginas > 1): ?>
        <nav class="paginado" aria-label="Paginado del catálogo">
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
          <a href="<?= $url('/catalogo/csv') ?>" id="catalogo-csv" download>Descargar estos resultados en CSV</a>
        </p>
      </section>
    </section>

  </main>

<?php
require __DIR__ . '/layouts/footer.php';
