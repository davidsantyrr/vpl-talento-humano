@extends('layouts.app')

@push('styles')
  <link rel="stylesheet" href="{{ asset('css/menus/styleMenu.css') }}">
@endpush

@push('scripts')
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@endpush

@section('content')
    <div class="head">
        <nav>
            <div class="nav-left">
                <img src="{{ asset('img/logo.png') }}" alt="Logo" class="logoVigia">
            </div>
            <div class="nav-center">
                <h1 class="title">Menu Principal</h1>
            </div>
            <div class="nav-right">
                <form id="logoutFormMenu" action="{{ route('logout') }}" method="POST" style="margin:0">
                    @csrf
                    <button class="logout-btn" type="submit">Cerrar sesión</button>
                </form>
            </div>
        </nav>
    </div>

    <div class="container">
        <div class="menu">
            <div class="card" role="article" aria-label="Módulo de vacantes">
                <div class="card__media" aria-hidden="true">
                    <img src="https://media.istockphoto.com/id/1787508974/es/vector/icono-de-b%C3%BAsqueda-de-vacante-de-empleo-s%C3%ADmbolo-de-encontrar-un-trabajo-para-hacer-negocios.jpg?s=612x612&w=0&k=20&c=mxK-BK8xZtKUSxwcIC79cVrD0XUtcxphzlQHtOBFpbY= "
                        alt="Icono búsqueda de vacantes">
                </div>

                <div class="card__body">
                    <h3 class="card__title">Módulo de Vacantes</h3>
                    <p class="card__subtitle">Explora y gestiona las vacantes disponibles. Accede al panel para crear,
                        editar o eliminar ofertas de empleo.</p>
                </div>

                <div class="card__actions">
                    <a class="btn" role="button" href="#" style="text-decoration: none;">Ingresar</a>
                </div>
            </div>

            <div class="card" role="article" aria-label="Módulo de entregas">
                <div class="card__media" aria-hidden="true">
                    <img src="https://media.istockphoto.com/id/1388965773/es/vector/equipo-de-protecci%C3%B3n-personal-de-trabajo-y-conjunto-de-iconos-de-ropa-vector.jpg?s=612x612&w=0&k=20&c=MF5KmbooSCt4PZrmSyWX5CwmF5klq07wokAjLThYGWs="
                        alt="Icono entregas">
                </div>

                <div class="card__body">
                    <h3 class="card__title">Módulo de Entregas</h3>
                    <p class="card__subtitle">Gestiona las entregas de productos y servicios. Accede al panel para
                        registrar, revisar o actualizar entregas.</p>
                </div>

                <div class="card__actions">
                    <a class="btn" role="button" href="{{ url('/menus/menuentrega') }}" style="text-decoration: none;">Ingresar</a>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
      (function(){
        const form = document.getElementById('logoutFormMenu');
        if(form){
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
                Swal.fire({ title: 'Cerrando sesión...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
                form.submit();
              }
            });
          });
        }

        // Toast for unavailable modules (links without route)
        document.querySelectorAll('.card__actions a[href="#"]').forEach(function(link){
          link.addEventListener('click', function(e){
            e.preventDefault();
            Swal.fire({
              toast: true,
              position: 'top-end',
              icon: 'info',
              title: 'Módulo no disponible por el momento',
              showConfirmButton: false,
              timer: 2500,
              timerProgressBar: true
            });
          });
        });
      })();
    </script>
    @endpush

@endsection