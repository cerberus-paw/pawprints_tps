<?php
/**
 * Vista Acerca de Nosotros — PAWPrints
 * Tarea 3: «Acerca de nosotros» (Salvador)
 */
require __DIR__ . '/layouts/header.php';
?>

  <main>

    <!-- ============================================================
         HERO — Foto de la entrada de la tienda con tagline superpuesto.
         ============================================================ -->
    <figure>
      <img src="frontend/img/entrada-tienda.jpg" alt="Entrada de la librería PAWPrints en Mercedes, Buenos Aires">
      <figcaption>
        <h1>Nuestra Casa de Palabras</h1>
        <p>Un proyecto familiar nacido de la pasión compartida por los libros y la comunidad.</p>
      </figcaption>
    </figure>

    <!-- ============================================================
         HISTORIA — Texto fundacional + pilares de la librería.
         ============================================================ -->
    <article>

      <section>
        <p><time datetime="2011">Desde 2011</time></p>
        <h2>Nuestra Historia</h2>
        <p>Historia de cómo se creó PAWPrint: una librería fundada en 2011 con el sueño de convertirse en un punto de encuentro para los amantes de la lectura en Mercedes.</p>
      </section>

      <aside>
        <h3>Nuestros Pilares</h3>
        <dl>
          <dt>Pilar 1</dt>
          <dd>Descripción del Pilar 1: comunidad y encuentro.</dd>

          <dt>Pilar 2</dt>
          <dd>Descripción del Pilar 2: diversidad y acceso a la cultura.</dd>
        </dl>
      </aside>

    </article>

    <!-- ============================================================
         SERVICIOS COMUNITARIOS — Tarjetas de servicios.
         ============================================================ -->
    <section>
      <h2>Servicios Comunitarios</h2>

      <ul>
        <li>
          <article>
            <h3>Clubes de Lectura</h3>
            <p>Reuniones mensuales de debate literario moderadas por escritores invitados.</p>
          </article>
        </li>
        <li>
          <article>
            <h3>Talleres de Escritura</h3>
            <p>Clases formativas y prácticas de redacción creativa para todas las edades.</p>
          </article>
        </li>
        <li>
          <article>
            <h3>Actividades Infantiles</h3>
            <p>Tardes de cuentacuentos y manualidades para fomentar el amor lector desde la infancia.</p>
          </article>
        </li>
      </ul>
    </section>

    <!-- ============================================================
         UBICACIÓN — Datos de contacto y mapa interactivo.
         ============================================================ -->
    <section>
      <p><strong>Visitanos</strong></p>
      <h2>¿Dónde estamos?</h2>
      <p>Indicaciones de referencia: a media cuadra de la plaza central de Mercedes.</p>

      <address>
        <ul>
          <li>Instituto Uzue, Mercedes, Buenos Aires, Argentina</li>
          <li>Teléfono: <a href="tel:+54190168">+54 190168</a></li>
          <li>Horario de atención: lunes a sábados de 9 a 20 h.</li>
        </ul>
      </address>

      <figure>
        <iframe
          src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3282.5!2d-59.4306!3d-34.6503!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2sMercedes%2C+Buenos+Aires!5e0!3m2!1ses!2sar!4v1"
          width="600"
          height="400"
          allowfullscreen
          loading="lazy"
          referrerpolicy="no-referrer-when-downgrade"
          title="Ubicación de PAWPrints en Google Maps">
        </iframe>
        <figcaption>Ubicación en Google Maps — Mercedes, Buenos Aires, Argentina</figcaption>
      </figure>
    </section>

  </main>

<?php
require __DIR__ . '/layouts/footer.php';
