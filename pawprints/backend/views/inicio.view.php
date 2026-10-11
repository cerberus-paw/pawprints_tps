<?php
/**
 * Vista de Inicio — PAWPrints
 * Tarea 2: Inicio con el menú de navegación (Salvador)
 */
require __DIR__ . '/layouts/header.php';
?>

  <main>

    <!-- ============================================================
         HERO — Presentación de la librería.
         Muestra la tienda en línea y la física mediante enlaces de acción.
         ============================================================ -->
    <section>
      <p><small>Un lugar para perderse entre páginas</small></p>
      <h1>PAWPrints</h1>
      <p>
        Ubicados en la ciudad de Mercedes, somos mucho más que una librería
        independiente. Ofrecemos una cuidada curaduría literaria, envíos a
        toda Argentina y un espacio cálido de encuentro para amantes de la
        lectura.
      </p>

      <ul>
        <li><a href="index.php?action=catalogo">Explorar catálogo</a> <small>(tienda en línea)</small></li>
        <li><a href="index.php?action=sobre-nosotros">Conocer nuestra tienda</a> <small>(tienda física)</small></li>
      </ul>

      <figure>
        <img src="frontend/img/local.jpg" alt="Interior de la librería PAWPrints: estanterías de madera y sillones de lectura">
        <figcaption>Nuestro local en Mercedes, Buenos Aires.</figcaption>
      </figure>
    </section>

    <hr>

    <!-- ============================================================
         RECOMENDACIONES — Selección curada del librero.
         ============================================================ -->
    <section>
      <p><small>Nuestra selección</small></p>
      <h2>Recomendaciones del Librero</h2>

      <ul>
        <li>
          <article>
            <figure>
              <img src="frontend/img/libro-1.jpg" alt="Portada de El Laberinto de los Vientos">
            </figure>
            <p><small>Ficción</small></p>
            <h3><a href="index.php?action=detalle-libro&id=1">El Laberinto de los Vientos</a></h3>
            <p><strong>Autor:</strong> Salvador Baez</p>
            <p><strong>Precio:</strong> <data value="16500">$16.500</data></p>
            <p><a href="index.php?action=detalle-libro&id=1">Ver detalles &rarr;</a></p>
          </article>
        </li>

        <li>
          <article>
            <figure>
              <img src="frontend/img/libro-2.jpg" alt="Portada de El Secreto de la Torre">
            </figure>
            <p><small>Ficción</small></p>
            <h3><a href="index.php?action=detalle-libro&id=2">El Secreto de la Torre</a></h3>
            <p><strong>Autor:</strong> Tomás Resnik</p>
            <p><strong>Precio:</strong> <data value="14000">$14.000</data></p>
            <p><a href="index.php?action=detalle-libro&id=2">Ver detalles &rarr;</a></p>
          </article>
        </li>

        <li>
          <article>
            <figure>
              <img src="frontend/img/libro-3.jpg" alt="Portada de Sombras en el Horizonte">
            </figure>
            <p><small>Ficción</small></p>
            <h3><a href="index.php?action=detalle-libro&id=3">Sombras en el Horizonte</a></h3>
            <p><strong>Autor:</strong> Mateo Nomico</p>
            <p><strong>Precio:</strong> <data value="15800">$15.800</data></p>
            <p><a href="index.php?action=detalle-libro&id=3">Ver detalles &rarr;</a></p>
          </article>
        </li>

        <li>
          <article>
            <figure>
              <img src="frontend/img/libro-4.jpg" alt="Portada de Ecos del Cosmos">
            </figure>
            <p><small>Ciencia</small></p>
            <h3><a href="index.php?action=detalle-libro&id=4">Ecos del Cosmos</a></h3>
            <p><strong>Autor:</strong> Salvador Baez</p>
            <p><strong>Precio:</strong> <data value="17200">$17.200</data></p>
            <p><a href="index.php?action=detalle-libro&id=4">Ver detalles &rarr;</a></p>
          </article>
        </li>
      </ul>
    </section>

    <hr>

    <!-- ============================================================
         PROMOCIONES Y EVENTOS — Destaque de actividades y ofertas.
         ============================================================ -->
    <section>
      <h2>Próximos Eventos y Promociones</h2>

      <article>
        <p><strong>Promoción vigente</strong></p>
        <h3>2x1 en narrativa argentina</h3>
        <p>
          Durante todo el mes, llevás dos títulos de narrativa argentina
          al precio de uno. Válido en la tienda física y en la tienda en línea.
        </p>
        <p><a href="index.php?action=catalogo&categoria=ficcion">Ver sección &rarr;</a></p>
      </article>

      <ul>
        <li>
          <article>
            <p><time datetime="2026-09-18T19:00">Jueves 18 de septiembre, 19:00 h</time></p>
            <h3>Club de lectura: literatura latinoamericana</h3>
            <p>Encuentro mensual abierto a la comunidad, moderado por un escritor invitado.</p>
            <p><a href="index.php?action=sobre-nosotros">Saber más &rarr;</a></p>
          </article>
        </li>

        <li>
          <article>
            <p><time datetime="2026-09-27T17:30">Sábado 27 de septiembre, 17:30 h</time></p>
            <h3>Taller de escritura creativa</h3>
            <p>Clase abierta de redacción creativa para todas las edades. Cupos limitados.</p>
            <p><a href="index.php?action=sobre-nosotros">Saber más &rarr;</a></p>
          </article>
        </li>
      </ul>
    </section>

    <hr>

    <!-- ============================================================
         NUESTRA HISTORIA — Resumen institucional.
         ============================================================ -->
    <section>
      <p><small>Nuestra historia</small></p>
      <h2>La pasión de compartir buenas lecturas</h2>

      <figure>
        <img src="frontend/img/clientes.jpg" alt="Clientes de PAWPrints eligiendo libros en el salón principal">
        <figcaption>Lectores en una de nuestras jornadas de puertas abiertas.</figcaption>
      </figure>

      <p>
        PAWPrints nació en <time datetime="2011">2011</time> como un proyecto
        familiar, con la idea de que una librería puede ser también un punto
        de encuentro. Desde entonces sostenemos clubes de lectura, talleres
        de escritura y actividades infantiles para la comunidad de Mercedes.
      </p>
      <p><a href="index.php?action=sobre-nosotros">Conocer más sobre nosotros &rarr;</a></p>
    </section>

  </main>

<?php
require __DIR__ . '/layouts/footer.php';
