<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menú de Entregas</title>
    <link rel="stylesheet" href="{{ asset('css/menus/menuEntrega.css') }}">
    <!-- Font Awesome 6 (reliable CDN) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css">
</head>
<body>
  <x-NavEntregasComponente />
  <header class="page-head" role="banner">
    <h1>Menú de Entregas</h1>
    <p>Accede rápidamente a las funciones de inventario y gestión de entregas</p>
  </header>
  <main class="container" role="main">
    <section class="cards" aria-label="Accesos rápidos">
      <!-- Inventario -->
      <article class="card c-blue" role="article">
        <div class="card__top">
          <div class="icon" aria-hidden="true">
            <i class="fa-solid fa-boxes-stacked"></i>
          </div>
          <h3>Inventario</h3>
          <p class="desc">Administra y consulta el stock de elementos disponibles</p>
        </div>
        <div class="card__footer"><a href="{{ route('articulos.index') }}" class="btn btn-blue" aria-label="Ingresar a Inventario">Ingresar</a></div>
      </article>

      <!-- Realizar cambio -->
      <article class="card c-orange" role="article">
        <div class="card__top">
          <div class="icon" aria-hidden="true">
            <i class="fa-solid fa-arrows-rotate"></i>
          </div>
          <h3>Realizar cambio</h3>
          <p class="desc">Registra cambios o reemplazos de elementos entregados</p>
        </div>
        <div class="card__footer"><a href="#" class="btn btn-orange" aria-label="Ingresar a Realizar cambio">Ingresar</a></div>
      </article>

      <!-- Realizar entrega -->
      <article class="card c-green" role="article">
        <div class="card__top">
          <div class="icon" aria-hidden="true">
            <i class="fa-solid fa-box-open"></i>
          </div>
          <h3>Realizar entrega</h3>
          <p class="desc">Procesa nuevas entregas de elementos a usuarios</p>
        </div>
        <div class="card__footer"><a href="#" class="btn btn-green" aria-label="Ingresar a Realizar entrega">Ingresar</a></div>
      </article>

      <!-- Histórico de entregas -->
      <article class="card c-red" role="article">
        <div class="card__top">
          <div class="icon" aria-hidden="true">
            <i class="fa-solid fa-clock-rotate-left"></i>
          </div>
          <h3>Histórico de entregas</h3>
          <p class="desc">Consulta y gestiona el registro de entregas realizadas</p>
        </div>
        <div class="card__footer"><a href="#" class="btn btn-red" aria-label="Ingresar a Histórico de entregas">Ingresar</a></div>
      </article>

      <!-- Configuración de notificación -->
      <article class="card c-yellow" role="article">
        <div class="card__top">
          <div class="icon" aria-hidden="true">
            <i class="fa-solid fa-bell"></i>
          </div>
          <h3>Configuración de notificación</h3>
          <p class="desc">Define alertas y avisos automáticos para el inventario</p>
        </div>
        <div class="card__footer"><a href="#" class="btn btn-yellow" aria-label="Ingresar a Configuración de notificación">Ingresar</a></div>
      </article>

      <!-- Consultar elementos por usuario -->
      <article class="card c-purple" role="article">
        <div class="card__top">
          <div class="icon" aria-hidden="true">
            <i class="fa-solid fa-magnifying-glass"></i>
          </div>
          <h3>Consultar elementos por usuario</h3>
          <p class="desc">Busca rápidamente los elementos asignados a un usuario</p>
        </div>
        <div class="card__footer"><a href="#" class="btn btn-purple" aria-label="Ingresar a Consultar elementos por usuario">Ingresar</a></div>
      </article>

      <!-- Configurar elementos por cargo -->
      <article class="card c-cyan" role="article">
        <div class="card__top">
          <div class="icon" aria-hidden="true">
            <i class="fa-solid fa-user-gear"></i>
          </div>
          <h3>Configurar elementos por cargo</h3>
          <p class="desc">Establece los elementos por tipo de cargo o rol</p>
        </div>
        <div class="card__footer"><a href="#" class="btn btn-cyan" aria-label="Ingresar a Configurar elementos por cargo">Ingresar</a></div>
      </article>

    </section>
  </main>
</body>
</html>