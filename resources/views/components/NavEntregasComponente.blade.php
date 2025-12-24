<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="{{ asset('css/components/styleNavEntregas.css') }}">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    {{-- Nav Entregas Component --}}
    <div class="header" role="banner">
      <nav class="navegacion" aria-label="Navegación principal">
        <div class="nav-left">
          <a href="{{ url('/') }}" class="brand">
            <img src="{{ asset('img/logoVigia.jpeg') }}" alt="Logo" class="logoVigia">
          </a>
        </div>
        <div class="opciones">
          <a href="#" class="nav-link">Home</a>
          <a href="{{ url('/menus/menuentrega') }}" class="nav-link">Menú</a>
          <a href="{{ route('articulos.index') }}" class="nav-link">Inventario</a>
          <a href="#" class="nav-link">Entrega</a>
          <a href="#" class="nav-link">Cambio</a>
          <a href="#" class="nav-link">Consulta</a>
          <a href="#" class="nav-link">Configuración</a>
          {{ $slot ?? '' }}
        </div>
        <div class="nav-actions">
          <form id="logoutForm" action="{{ route('logout') }}" method="POST">
            @csrf
            <button class="cerrarSesion" type="submit">Cerrar sesión</button>
          </form>
        </div>
      </nav>
    </div>

    <script>
      (function(){
        const form = document.getElementById('logoutForm');
        if(!form) return;
        form.addEventListener('submit', function(e){
          e.preventDefault();
          Swal.fire({
            title: '¿Cerrar sesión?',
            text: 'Se cerrará tu sesión actual.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, cerrar',
            cancelButtonText: 'Cancelar'
          }).then((result) => {
            if (result.isConfirmed) {
              const loading = Swal.fire({
                title: 'Cerrando sesión...',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
              });
              form.submit();
            }
          });
        });
      })();
    </script>
</body>
</html>