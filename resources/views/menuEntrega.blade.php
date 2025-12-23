<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menú de Entregas</title>
    <link rel="stylesheet" href="{{ asset('css/menuEntrega.css') }}">
</head>
<body>
  <div class="page-head">
    <h1>Menú de Entregas</h1>
    <p>Accede rápidamente a las funciones de inventario y gestión de entregas</p>
  </div>
  <div class="container">
    <div class="cards">
      <!-- Inventario -->
      <article class="card c-blue">
        <div class="card__top">
          <div class="icon">
            <!-- icon cajas -->
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <rect x="3" y="3" width="8" height="8" rx="1.5" stroke="currentColor" stroke-width="1.6"/>
              <rect x="13" y="3" width="8" height="8" rx="1.5" stroke="currentColor" stroke-width="1.6"/>
              <rect x="3" y="13" width="8" height="8" rx="1.5" stroke="currentColor" stroke-width="1.6"/>
              <path d="M17 13v8" stroke="currentColor" stroke-width="1.6"/>
              <path d="M13 17h8" stroke="currentColor" stroke-width="1.6"/>
            </svg>
          </div>
          <h3>Inventario</h3>
        </div>
        <div class="card__footer"><a href="#" class="btn">Ingresar</a></div>
      </article>

      <!-- Realizar cambio -->
      <article class="card c-orange">
        <div class="card__top">
          <div class="icon">
            <!-- icon cambio -->
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M7 7h10l-3-3M17 17H7l3 3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </div>
          <h3>Realizar cambio</h3>
        </div>
        <div class="card__footer"><a href="#" class="btn">Ingresar</a></div>
      </article>

      <!-- Realizar entrega -->
      <article class="card c-green">
        <div class="card__top">
          <div class="icon">
            <!-- icon caja entrega -->
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M3 9l9-5 9 5v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V9z" stroke="currentColor" stroke-width="1.6"/>
              <path d="M12 4v16" stroke="currentColor" stroke-width="1.6"/>
            </svg>
          </div>
          <h3>Realizar entrega</h3>
        </div>
        <div class="card__footer"><a href="#" class="btn">Ingresar</a></div>
      </article>

      <!-- Histórico de entregas -->
      <article class="card c-red">
        <div class="card__top">
          <div class="icon">
            <!-- icon reloj-lista -->
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <circle cx="7" cy="7" r="3.5" stroke="currentColor" stroke-width="1.6"/>
              <path d="M7 5v2l1.2 1.2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
              <rect x="12" y="6" width="8" height="2" rx="1" fill="currentColor"/>
              <rect x="12" y="11" width="8" height="2" rx="1" fill="currentColor"/>
              <rect x="12" y="16" width="8" height="2" rx="1" fill="currentColor"/>
            </svg>
          </div>
          <h3>Historico de entregas</h3>
        </div>
        <div class="card__footer"><a href="#" class="btn">Ingresar</a></div>
      </article>

      <!-- Configuración de notificación -->
      <article class="card c-yellow">
        <div class="card__top">
          <div class="icon">
            <!-- icon campana -->
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M18 8a6 6 0 10-12 0c0 7-3 7-3 7h18s-3 0-3-7" stroke="currentColor" stroke-width="1.6"/>
              <path d="M13.73 21a2 2 0 01-3.46 0" stroke="currentColor" stroke-width="1.6"/>
            </svg>
          </div>
          <h3>Configuración de notificación</h3>
        </div>
        <div class="card__footer"><a href="#" class="btn">Ingresar</a></div>
      </article>

      <!-- Consultar elementos por usuario -->
      <article class="card c-purple">
        <div class="card__top">
          <div class="icon">
            <!-- icon lupa -->
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <circle cx="11" cy="11" r="6" stroke="currentColor" stroke-width="1.6"/>
              <path d="M16.5 16.5L20 20" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
            </svg>
          </div>
          <h3>Consultar elementos por usuario</h3>
        </div>
        <div class="card__footer"><a href="#" class="btn">Ingresar</a></div>
      </article>

      <!-- Configurar elementos por cargo -->
      <article class="card c-cyan">
        <div class="card__top">
          <div class="icon">
            <!-- icon engranaje usuario -->
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4z" stroke="currentColor" stroke-width="1.6"/>
              <path d="M3 20a7 7 0 0 1 14 0" stroke="currentColor" stroke-width="1.6"/>
              <path d="M18.5 10.5l1 .6 1-.6v-1.2l-1-.6-1 .6z" stroke="currentColor" stroke-width="1.4"/>
            </svg>
          </div>
          <h3>Configurar elementos por cargo</h3>
        </div>
        <div class="card__footer"><a href="#" class="btn">Ingresar</a></div>
      </article>

    </div>
  </div>
</body>
</html>