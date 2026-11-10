<?php
/**
 * Vista de carga de libros nuevos — PAWPrints
 * TP4, consigna 2: validaciones y devoluciones en línea en el formulario de «libros nuevos»
 *
 * @var array<string, string> $valores  lo que ya escribió el usuario, para no hacérselo escribir de nuevo
 * @var array<string, string> $errores  campo => mensaje, de la validación del servidor
 * @var list<string> $generos
 * @var list<string> $formatos
 * @var array<string, array<string, string>> $mensajes los de LibroNuevo::MENSAJES
 * @var string $patronIsbn
 * @var array<string, int> $limites
 */
require __DIR__ . '/layouts/header.php';

/*
 * Los atributos de cada control:
 * - data-error-*: los mensajes de LibroNuevo, para que validador.js muestre los mismos que el
 *   servidor. Sólo van los que el navegador puede detectar: «lista» y «repetido» los decide el
 *   servidor, que conoce el catálogo.
 * - aria-describedby: la ayuda del campo, si tiene, y el mensaje de error, si el servidor
 *   encontró uno. El lector de pantalla lee los dos al enfocarlo.
 * - aria-invalid, si tiene error: el CSS lo pinta sin necesitar una clase.
 */
$atributos = static function (string $campo, ?string $ayuda = null) use ($mensajes, $errores): string {
    $html = '';
    foreach ($mensajes[$campo] as $tipo => $texto) {
        if (!in_array($tipo, ['lista', 'repetido', 'caracteres'], true)) {
            $html .= ' data-error-' . $tipo . '="' . htmlspecialchars($texto) . '"';
        }
    }
    $describe = array_filter([$ayuda, isset($errores[$campo]) ? "error-{$campo}" : null]);
    if ($describe !== []) {
        $html .= ' aria-describedby="' . implode(' ', $describe) . '"';
    }
    if (isset($errores[$campo])) {
        $html .= ' aria-invalid="true"';
    }

    return $html;
};
$mensaje = static fn (string $campo): string => isset($errores[$campo])
    ? '<p id="error-' . $campo . '" class="error-campo">' . htmlspecialchars($errores[$campo]) . '</p>'
    : '';
$valor = static fn (string $campo): string => htmlspecialchars($valores[$campo]);
$opciones = static function (array $lista, string $elegida): string {
    $html = '';
    foreach ($lista as $opcion) {
        $html .= '<option' . ($opcion === $elegida ? ' selected' : '') . '>' . htmlspecialchars($opcion) . '</option>';
    }

    return $html;
};
?>

  <main>

    <section aria-labelledby="titulo-libro-nuevo">
      <h1 id="titulo-libro-nuevo">Cargar un libro nuevo</h1>
      <p>
        Para el personal de la librería: completá los datos del libro tal como
        van a aparecer en el catálogo. Los campos con <abbr title="campo obligatorio">*</abbr>
        son obligatorios.
      </p>

      <!-- El resumen de errores. Está siempre, oculto si no hay nada que mostrar, para que el
           lector de pantalla anuncie lo que validador.js escriba adentro: role="alert" funciona
           mejor sobre un elemento que ya existe. -->
      <div class="resumen-errores" role="alert" data-resumen-errores<?= $errores === [] ? ' hidden' : '' ?>>
        <?php if ($errores !== []): ?>
        <p>Revisá estos datos antes de cargar el libro:</p>
        <ul>
          <?php foreach ($errores as $campo => $texto): ?>
          <li><a href="#<?= htmlspecialchars($campo) ?>"><?= htmlspecialchars($texto) ?></a></li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </div>

      <!-- Sin novalidate en el HTML: sin JavaScript, el navegador valida con los atributos de cada
           campo. validador.js lo agrega al arrancar y desde ahí muestra sus propios mensajes, que
           se ven igual en todos los navegadores. El servidor vuelve a validar todo igual. -->
      <form action="/libros/nuevo" method="post" enctype="multipart/form-data" aria-labelledby="titulo-libro-nuevo" data-validar>
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">

        <fieldset>
          <legend>Datos del libro</legend>

          <div class="campo">
            <label for="titulo">Título <abbr title="campo obligatorio">*</abbr></label>
            <input type="text" id="titulo" name="titulo" value="<?= $valor('titulo') ?>"
                   required maxlength="<?= $limites['titulo'] ?>"<?= $atributos('titulo') ?>>
            <?= $mensaje('titulo') ?>
          </div>

          <div class="campo">
            <label for="autor">Autor <abbr title="campo obligatorio">*</abbr></label>
            <input type="text" id="autor" name="autor" value="<?= $valor('autor') ?>"
                   required maxlength="<?= $limites['autor'] ?>"<?= $atributos('autor') ?>>
            <?= $mensaje('autor') ?>
          </div>

          <div class="fila">
            <div class="campo">
              <label for="genero">Género <abbr title="campo obligatorio">*</abbr></label>
              <select id="genero" name="genero" required<?= $atributos('genero') ?>>
                <option value="">— Elegí un género —</option>
                <?= $opciones($generos, $valores['genero']) ?>
              </select>
              <?= $mensaje('genero') ?>
            </div>

            <div class="campo">
              <label for="formato">Formato <abbr title="campo obligatorio">*</abbr></label>
              <select id="formato" name="formato" required<?= $atributos('formato') ?>>
                <option value="">— Elegí un formato —</option>
                <?= $opciones($formatos, $valores['formato']) ?>
              </select>
              <?= $mensaje('formato') ?>
            </div>
          </div>

          <div class="campo">
            <label for="isbn">ISBN <abbr title="campo obligatorio">*</abbr></label>
            <!-- data-regla="isbn": además del formato, validador.js controla el dígito verificador
                 con la regla que le registra la página (js/paginas/libro-nuevo.js). -->
            <input type="text" id="isbn" name="isbn" value="<?= $valor('isbn') ?>"
                   required pattern="<?= htmlspecialchars($patronIsbn) ?>" inputmode="numeric"
                   autocomplete="off" placeholder="978-987-1234-56-1" data-regla="isbn"<?= $atributos('isbn', 'ayuda-isbn') ?>>
            <small id="ayuda-isbn">Los 13 números del código de barras. Los guiones son opcionales.</small>
            <?= $mensaje('isbn') ?>
          </div>

          <div class="campo campo-tapa" data-zona-arrastre>
            <label for="tapa">Tapa del libro</label>
            <input type="file" id="tapa" name="tapa" accept="image/jpeg,image/png,image/webp"<?= $atributos('tapa', 'ayuda-tapa') ?>>
            <small id="ayuda-tapa">Formatos: JPG, PNG o WebP. Hasta 2 MB.</small>
            <?= $mensaje('tapa') ?>
            <?php if ($errores !== [] && !isset($errores['tapa'])): ?>
            <p id="aviso-tapa" class="aviso-campo aviso-reingreso-tapa" role="status">
              <?= !empty($tapaEnviada)
                ? 'Tenés que volver a elegir la tapa: por seguridad el navegador no la conserva al volver con errores.'
                : 'Si habías seleccionado una tapa, tenés que volver a elegirla: por seguridad el navegador no la conserva al volver con errores.' ?>
            </p>
            <?php endif; ?>
          </div>
        </fieldset>

        <fieldset>
          <legend>Precio y stock</legend>

          <div class="fila">
            <div class="campo">
              <label for="precio">Precio <abbr title="campo obligatorio">*</abbr></label>
              <input type="number" id="precio" name="precio" value="<?= $valor('precio') ?>"
                     required min="1" max="<?= $limites['precio'] ?>" step="1" inputmode="numeric"<?= $atributos('precio', 'ayuda-precio') ?>>
              <small id="ayuda-precio">En pesos, sin centavos.</small>
              <?= $mensaje('precio') ?>
            </div>

            <div class="campo">
              <label for="stock">Stock <abbr title="campo obligatorio">*</abbr></label>
              <input type="number" id="stock" name="stock" value="<?= $valor('stock') ?>"
                     required min="0" max="<?= $limites['stock'] ?>" step="1" inputmode="numeric"<?= $atributos('stock', 'ayuda-stock') ?>>
              <small id="ayuda-stock">Ejemplares disponibles en la tienda.</small>
              <?= $mensaje('stock') ?>
            </div>
          </div>
        </fieldset>

        <fieldset>
          <legend>Texto del catálogo</legend>

          <div class="campo">
            <label for="resumen">Resumen <abbr title="campo obligatorio">*</abbr></label>
            <textarea id="resumen" name="resumen" rows="3" required
                      maxlength="<?= $limites['resumen'] ?>"<?= $atributos('resumen', 'ayuda-resumen') ?>><?= $valor('resumen') ?></textarea>
            <small id="ayuda-resumen">Una o dos oraciones: es lo que se lee en el listado del catálogo. Hasta <?= $limites['resumen'] ?> caracteres.</small>
            <?= $mensaje('resumen') ?>
          </div>

          <div class="campo">
            <label for="descripcion">Descripción</label>
            <textarea id="descripcion" name="descripcion" rows="6"
                      maxlength="<?= $limites['descripcion'] ?>"<?= $atributos('descripcion', 'ayuda-descripcion') ?>><?= $valor('descripcion') ?></textarea>
            <small id="ayuda-descripcion">El texto completo de la ficha del libro. Si queda vacía, la ficha muestra el resumen.</small>
            <?= $mensaje('descripcion') ?>
          </div>
        </fieldset>

        <div>
          <button type="submit">Cargar libro</button>
        </div>

      </form>
    </section>

  </main>

<?php
require __DIR__ . '/layouts/footer.php';
