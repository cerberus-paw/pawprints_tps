<?php
/**
 * Vista de Reserva recibida — PAWPrints
 * Tarea 5: a dónde lleva el 303 después de una reserva que salió bien (Mateo)
 */
require __DIR__ . '/layouts/header.php';
?>

  <main>

    <section aria-labelledby="titulo-confirmacion">
      <h1 id="titulo-confirmacion">¡Recibimos tu reserva!</h1>
      <p>
        Te mandamos un correo con el detalle. Te guardamos el libro por 10 días
        hábiles, y lo pagás al retirarlo en nuestra tienda de Mercedes.
      </p>
      <p>
        Si en unos minutos no te llegó el correo, revisá la carpeta de correo no
        deseado o llamanos al <a href="tel:+54190168">+54 190168</a>.
      </p>
      <ul>
        <li><a href="/catalogo">Seguir viendo el catálogo</a></li>
        <li><a href="/">Volver al inicio</a></li>
      </ul>
    </section>

  </main>

<?php
require __DIR__ . '/layouts/footer.php';
