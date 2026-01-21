<!DOCTYPE html>
<html lang="es">
<head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Gestiones</title>
        <link rel="stylesheet" href="{{ asset('css/menus/menuEntrega.css') }}">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css">
        <style>
                /* Small page-specific tweaks */
                body { background: linear-gradient(180deg,#f8fafc 0,#eef2f7 100%); }
        </style>
</head>
<body>
    <x-NavEntregasComponente />
  <header class="page-head" role="banner">
    <h1>Menú de Gestiones</h1>
    <p>Accede rápidamente a las funciones de las gestiones</p>
  </header>

    <main class="container" role="main">
        <section class="cards" aria-label="Accesos rápidos">
        <!-- Gestión de Correos -->
        <article class="card c-blue" role="article">
            <div class="card__top">
            <div class="icon" aria-hidden="true">
                <i class="fa-solid fa-envelope"></i>
            </div>
            <h3>Gestión de Correos</h3>
            <p class="desc">Administra las direcciones de correo asociadas a los roles del sistema</p>
            </div>
            <div class="card__footer"><a href="{{ route('gestionCorreos.index') }}" class="btn btn-green"
                aria-label="Ingresar a Gestión de Correos">Ingresar</a></div>
        </article>

        <!-- Gestión de usuarios -->
        <article class="card c-red" role="article">
            <div class="card__top">
            <div class="icon" aria-hidden="true">
                <i class="fa-solid fa-users"></i>
            </div>
            <h3>Gestión de usuarios</h3>
            <p class="desc">gestionar los nuevos usuarios </p>
            </div>
            <div class="card__footer"><a href="{{ route('gestionUsuario.index') }}" class="btn btn-green"
                aria-label="Ingresar a Gestión de Usuarios">Ingresar</a></div>
        </article>

        <!-- Gestión de areas -->
        <article class="card c-purple" role="article">
            <div class="card__top">
            <div class="icon" aria-hidden="true">
                <i class="fa-solid fa-layer-group"></i>
            </div>
            <h3>Gestión de áreas</h3>
            <p class="desc">Administra las áreas de la organización</p>
            </div>
            <div class="card__footer"><a href="{{ route('gestionArea.index') }}" class="btn btn-green"
                aria-label="Ingresar a Gestión de Áreas">Ingresar</a></div>
        </article>

        <!-- Gestión de operaciones -->
        <article class="card c-black" role="article">
            <div class="card__top">
            <div class="icon" aria-hidden="true">
                <i class="fa-solid fa-gear"></i>
            </div>
            <h3>Gestión de operaciones</h3>
            <p class="desc">Administra las operaciones de la organización</p>
            </div>
            <div class="card__footer"><a href="{{ route('gestionOperacion.index') }}" class="btn btn-green"
                aria-label="Ingresar a Gestión de Operaciones">Ingresar</a></div>
        </article>

        <!-- Gestión de periodicidad -->
        <article class="card c-orange" role="article">
            <div class="card__top">
            <div class="icon" aria-hidden="true">
                <i class="fa-solid fa-calendar-days"></i>
            </div>
            <h3>Gestión de periodicidad</h3>
            <p class="desc">Administra la periodicidad de las actividades y procesos</p>
            </div>
            <div class="card__footer"><a href="{{ route('gestionPeriodicidad.index') }}" class="btn btn-green"
                aria-label="Ingresar a Gestión de Periodicidad">Ingresar</a></div>
        </article>

       <!-- Gestión de Centro de costos -->
        <article class="card c-pink" role="article">
            <div class="card__top">
            <div class="icon" aria-hidden="true">
                <i class="fa-solid fa-wallet"></i>
            </div>
            <h3>Gestión de Centro de costos</h3>
            <p class="desc">Administra los centros de costos de la organización</p>
            </div>
            <div class="card__footer"><a href="{{ route('gestionCentroCosto.index') }}" class="btn btn-green"
                aria-label="Ingresar a Gestión de Centro de costos">Ingresar</a></div>
        </article>
        
        </section>
</body>
</html>