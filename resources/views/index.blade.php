<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login</title>
</head>
<body>
    @if (session('successMessage'))
        <p style="color: green;">{{ session('successMessage') }}</p>
    @endif

    @if (session('errorMessage'))
        <p style="color: red;">{{ session('errorMessage') }}</p>
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf
        <h1>Iniciar sesion</h1>
        <div>
            <label for="email">Correo:</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
            @error('email')
                <p style="color: red;">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="password">Contraseña:</label>
            <input id="password" type="password" name="password" required>
            @error('password')
                <p style="color:red;">{{ $message }}</p>
            @enderror
        </div>
        <button type="submit">Iniciar sesión</button>
    </form>
</body>
</html>
