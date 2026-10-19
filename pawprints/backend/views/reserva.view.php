<?php
/**
 * Vista de Reserva — PAWPrints
 * Tarea 5: reserva que manda un correo al personal (Mateo)
 *
 * @var list<array{id: int, titulo: string, autor: string, precio: int}> $libros
 * @var array{id: int, titulo: string, autor: string, precio: int}|null  $libro el de la tarjeta de resumen
 * @var array<string, string> $valores lo que ya escribió el usuario, para no hacérselo escribir de nuevo
 * @var array<string, string> $errores campo => mensaje; «envio» si no salió el correo
 * @var int    $maxEjemplares
 * @var string $trampa nombre del campo trampa para bots
 */
require __DIR__ . '/layouts/header.php';

// El id del control de cada campo, para que el resumen de errores enlace a él.
$controles = [
    'nombre' => 'nombre',
    'email' => 'email',
    'telefono' => 'telefono',
    'libro_id' => 'libro',
    'cantidad' => 'cantidad',
    'comentarios' => 'comentarios',
];

// Un campo con error queda marcado como inválido y asociado a su mensaje: el lector de pantalla
// lo lee al enfocarlo, y el CSS lo pinta sin necesitar una clase.
$invalido = static fn (string $campo): string => isset($errores[$campo])
    ? ' aria-invalid="true" aria-describedby="error-' . $campo . '"'
    : '';
$mensaje = static fn (string $campo): string => isset($errores[$campo])
    ? '<p id="error-' . $campo . '">' . htmlspecialchars($errores[$campo]) . '</p>'
    : '';
$valor = static fn (string $campo): string => htmlspecialchars($valores[$campo]);
?>

  <main>

    <section aria-labelledby="titulo-reserva">
      <h1 id="titulo-reserva">Formulario de Reserva</h1>
      <p>
        Reservá tu ejemplar de manera gratuita y retiralo en nuestra
        librería física en Mercedes cuando te convenga.
      </p>
    </section>

    <section aria-label="Contenido de la página de reserva">

      <article aria-labelledby="subtitulo-formulario">
        <h2 id="subtitulo-formulario" hidden>Datos de la reserva</h2>

        <?php if (isset($errores['envio'])): ?>
        <p role="alert"><?= htmlspecialchars($errores['envio']) ?></p>
        <?php elseif ($errores !== []): ?>
        <!-- role="alert" hace que el lector de pantalla lo anuncie apenas carga la página. -->
        <div role="alert">
          <p>Revisá estos datos antes de enviar la reserva:</p>
          <ul>
            <?php foreach ($errores as $campo => $texto): ?>
            <li><a href="#<?= $controles[$campo] ?? $campo ?>"><?= htmlspecialchars($texto) ?></a></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>

        <!-- Sin novalidate: el navegador valida primero con los atributos de cada campo, y el
             servidor vuelve a validar todo, porque lo del navegador se saltea fácil. -->
        <form action="/reserva" method="post" aria-labelledby="titulo-reserva">

          <fieldset>
            <legend>Datos del solicitante</legend>

            <div>
              <label for="nombre">Nombre completo <abbr title="campo obligatorio">*</abbr></label>
              <input type="text" id="nombre" name="nombre" value="<?= $valor('nombre') ?>"
                     placeholder="Ej. Juan Pérez García" required maxlength="100"
                     autocomplete="name"<?= $invalido('nombre') ?>>
              <?= $mensaje('nombre') ?>
            </div>

            <div>
              <div>
                <label for="email">Correo electrónico <abbr title="campo obligatorio">*</abbr></label>
                <input type="email" id="email" name="email" value="<?= $valor('email') ?>"
                       placeholder="juan.perez@ejemplo.com" required maxlength="254"
                       autocomplete="email"<?= $invalido('email') ?>>
                <?= $mensaje('email') ?>
              </div>

              <div>
                <label for="telefono">Teléfono de contacto <abbr title="campo obligatorio">*</abbr></label>
                <input type="tel" id="telefono" name="telefono" value="<?= $valor('telefono') ?>"
                       placeholder="+54 9 11 1234-5678" required maxlength="20"
                       pattern="[\+]?[\d\s\-\(\)]{7,20}" autocomplete="tel"<?= $invalido('telefono') ?>>
                <?= $mensaje('telefono') ?>
              </div>
            </div>
          </fieldset>

          <fieldset>
            <legend>Datos del libro</legend>

            <div>
              <label for="libro">Seleccioná el libro a reservar <abbr title="campo obligatorio">*</abbr></label>
              <!-- Lista cerrada armada desde el catálogo: el value es el id del libro. -->
              <select id="libro" name="libro_id" required<?= $invalido('libro_id') ?>>
                <option value="" disabled<?= $libro === null ? ' selected' : '' ?>>— Seleccioná un libro —</option>
                <?php foreach ($libros as $opcion): ?>
                <option value="<?= $opcion['id'] ?>"<?= $opcion['id'] === ($libro['id'] ?? null) ? ' selected' : '' ?>><?= htmlspecialchars("{$opcion['titulo']} — {$opcion['autor']}") ?></option>
                <?php endforeach; ?>
              </select>
              <?= $mensaje('libro_id') ?>
            </div>

            <div>
              <label for="cantidad">Cantidad <abbr title="campo obligatorio">*</abbr></label>
              <input type="number" id="cantidad" name="cantidad" value="<?= $valor('cantidad') ?>"
                     min="1" max="<?= $maxEjemplares ?>" required<?= $invalido('cantidad') ?>>
              <?= $mensaje('cantidad') ?>
              <small>Máximo <?= $maxEjemplares ?> ejemplares por reserva.</small>
            </div>
          </fieldset>

          <fieldset>
            <legend>Información adicional</legend>

            <div>
              <label for="comentarios">Comentarios adicionales</label>
              <textarea id="comentarios" name="comentarios" rows="5" maxlength="500"
                        placeholder="¿Deseás que lo envolvamos para regalo? ¿Alguna instrucción sobre la recogida?"<?= $invalido('comentarios') ?>><?= $valor('comentarios') ?></textarea>
              <?= $mensaje('comentarios') ?>
            </div>
          </fieldset>

          <!-- Trampa para bots: hidden la saca de la vista y del lector de pantalla, así que sólo
               la completa un programa que llena todos los campos que encuentra. -->
          <div hidden>
            <label for="<?= $trampa ?>">No completar este campo</label>
            <input type="text" id="<?= $trampa ?>" name="<?= $trampa ?>" tabindex="-1" autocomplete="off">
          </div>

          <div>
            <button type="submit">Enviar Reserva</button>
          </div>

        </form>
      </article>

      <aside aria-labelledby="titulo-resumen-libro">
        <h2 id="titulo-resumen-libro" hidden>Resumen del libro</h2>

        <!-- Sin JavaScript la tarjeta no sigue al desplegable: muestra el libro con el que se
             llegó (desde su detalle) o el que se eligió antes de un error. El TP4 la actualiza. -->
        <article<?= $libro !== null ? ' aria-labelledby="nombre-libro-resumen"' : '' ?>>
          <?php if ($libro !== null): ?>
          <header>
            <p><strong>Libro <?= $libro['id'] ?></strong></p>
            <h3 id="nombre-libro-resumen"><?= htmlspecialchars($libro['titulo']) ?></h3>
            <p><?= htmlspecialchars($libro['autor']) ?></p>
            <p><strong><data value="<?= $libro['precio'] ?>">$<?= number_format($libro['precio'], 0, ',', '.') ?></data></strong></p>
          </header>

          <hr>
          <?php endif; ?>

          <section aria-labelledby="titulo-beneficios">
            <h3 id="titulo-beneficios" hidden>Beneficios de reservar en tienda</h3>
            <ul>
              <li>Te guardamos el libro por 10 días hábiles.</li>
              <li>Te llega un correo con el detalle de la reserva.</li>
              <li>Pago directo al retirarlo en la tienda física.</li>
            </ul>
          </section>
        </article>
      </aside>

    </section>

  </main>

<?php
require __DIR__ . '/layouts/footer.php';
