<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu Principal</title>
    <link rel="stylesheet" href="{{ asset('css/menus/styleMenu.css') }}">
</head>

<body>
    <div class="head">
        <nav>
            <div class="nav-left">
                <img src="{{ asset('img/logoVigia.jpeg') }}" alt="Logo" class="logoVigia">
            </div>
            <div class="nav-center">
                <h1 class="title">Menu Principal</h1>
            </div>
            <div class="nav-right">
                <button class="logout-btn" type="button">Cerrar sesión</button>
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
                    <button class="btn" type="button" href="#">Ingresar</button>
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
                    <button class="btn" type="button">Ingresar</button>
                </div>
            </div>
        </div>
    </div>
</body>

</html>