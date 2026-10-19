<?php
/**
 * Vista de Error — PAWPrints
 * Tarea 6: comportamiento ante un error, sin comprometer la aplicación (Agustina)
 *
 * Una sola vista para todos los estados: cambian el número, el título y el mensaje. Mantiene el
 * menú y el pie del sitio, así un 404 sigue siendo una página de PAWPrints desde la que se puede
 * seguir navegando, y no un texto suelto en el navegador.
 *
 * El bloque de detalle técnico lo arma App sólo cuando app.entorno es «desarrollo»: en producción
 * llega en null y no se genera. Lo que se muestra acá no es nunca lo que se guarda en el log.
 *
 * @var int         $status     el estado HTTP con el que se responde
 * @var string      $titulo     el encabezado, de App::MENSAJES
 * @var string      $mensaje    el de la HttpException, o el de respaldo del estado
 * @var string|null $referencia los seis dígitos con los que el error quedó en el log (sólo en 5xx)
 * @var array{clase: string, mensaje: string, origen: string, traza: string}|null $detalle
 */
require __DIR__ . '/layouts/header.php';
?>

  <main>

    <!-- ============================================================
         ERROR — Estado, qué pasó y por dónde seguir.
         ============================================================ -->
    <section aria-labelledby="titulo-error">

      <p><data value="<?= $status ?>"><?= $status ?></data></p>
      <h1 id="titulo-error"><?= htmlspecialchars($titulo) ?></h1>
      <p><?= htmlspecialchars($mensaje) ?></p>

      <nav aria-label="Cómo seguir">
        <ul>
          <li><a href="/">Volver al inicio</a></li>
          <li><a href="/catalogo">Ver el catálogo</a></li>
          <li><a href="/sobre-nosotros">Sobre Nosotros</a></li>
        </ul>
      </nav>

      <?php if ($referencia !== null): ?>
        <!-- La referencia no explica nada por sí sola; sirve para encontrar el error en el log. -->
        <p>
          Si querés contarnos qué pasó, pasanos esta referencia:
          <data value="<?= htmlspecialchars($referencia) ?>"><?= htmlspecialchars($referencia) ?></data>
        </p>
      <?php endif; ?>

    </section>

    <?php if ($detalle !== null): ?>
      <!-- ============================================================
           DETALLE — Sólo con app.entorno = "desarrollo". En producción
           este bloque no existe en el HTML: no se oculta con CSS, que
           sería mirar el código fuente y leerlo igual.
           ============================================================ -->
      <section aria-labelledby="titulo-detalle">

        <h2 id="titulo-detalle">Detalle del error</h2>
        <p>Esto se ve porque el entorno es de desarrollo. En producción, acá no hay nada.</p>

        <dl>
          <dt>Excepción</dt>
          <dd><code><?= htmlspecialchars($detalle['clase']) ?></code></dd>

          <dt>Mensaje</dt>
          <dd><?= htmlspecialchars($detalle['mensaje']) ?></dd>

          <dt>Origen</dt>
          <dd><code><?= htmlspecialchars($detalle['origen']) ?></code></dd>
        </dl>

        <pre><code><?= htmlspecialchars($detalle['traza']) ?></code></pre>

      </section>
    <?php endif; ?>

  </main>

<?php
require __DIR__ . '/layouts/footer.php';
