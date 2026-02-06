<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="{{ secure_asset('css/registro.css') }}">

</head>

<body>
    <div class="container">

        <form class="formulario">
            <h2>Registro de Usuario Externo</h2>
            <label for="nombre">Nombres:</label><br>
            <input type="text" id="nombre" name="nombre" required><br><br>

            <label for="apellido">Apellidos:</label><br>
            <input type="text" id="apellido" name="apellido" required><br><br>

            <label for="email">Correo Electrónico:</label><br>
            <input type="email" id="email" name="email" required><br><br>

            <label for="telefono">Contraseña:</label><br>
            <input type="password" id="telefono" name="telefono" required><br><br>
            <label for="direccion">Confirmar Contraseña:</label><br>
            <input type="password" id="direccion" name="direccion" required><br><br>
            <button type="submit" class="boton">Registrar</button>
        </form>
    </div>
</body>

</html>