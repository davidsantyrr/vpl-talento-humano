<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="{{ asset('css/styleSidebar.css') }}">
    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<!-- checkbox -->
    <input type="checkbox" id="toggle-sidebar">

    

    <!-- NAVBAR -->
    <nav class="top-nav">

    <!-- botón -->
    <label for="toggle-sidebar" class="toggle-btn">
        <i class="fas fa-bars"></i>
    </label>
        <div class="nav-left">
            <img src="{{ asset('img/logoVigia.jpeg') }}" alt="Logo" class="nav-logo">
        </div>

        <div class="nav-right">
            <i class="fas fa-bell"></i>
            <i class="fas fa-user"></i>
        </div>
    </nav>

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <ul class="sidebar-menu">
            <li><a href="#"><i class="fas fa-home"></i><span>Dashboard</span></a></li>
            <li><a href="#"><i class="fas fa-user"></i><span>Perfil</span></a></li>
            <li><a href="#"><i class="fas fa-clipboard-list"></i><span>Historial postulaciones</span></a></li>
            <li><a href="#"><i class="fas fa-briefcase"></i><span>Historial vacantes</span></a></li>
            <li><a href="#"><i class="fas fa-bell"></i><span>Notificaciones</span></a></li>
            <li><a href="#"><i class="fas fa-file-signature"></i><span>Crear requisición</span></a></li>
            <li><a href="#"><i class="fas fa-history"></i><span>Historial requisiciones</span></a></li>
            <li><a href="#"><i class="fas fa-list"></i><span>Todas las requisiciones</span></a></li>
            <li><a href="#"><i class="fas fa-check-circle"></i><span>Requisiciones por aprobar</span></a></li>
            <li><a href="#"><i class="fas fa-sign-out-alt"></i><span>Salir</span></a></li>
        </ul>
    </aside>


</body>
</html>