<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/index/index.css') }}">
</head>
<body>
    <header class="site-nav">
        <div class="nav-container">
            <div class="brand">Vlp-proyecto</div>
        </div>
    </header>

    <main>
        <div class="border-solid">
            <form method="POST" action="{{ route('login') }}">
                @csrf

                <h1>Inicio de sesion</h1>

                @if (session('successMessage'))
                    <p class="session-message success">{{ session('successMessage') }}</p>
                @endif

                @if (session('errorMessage'))
                    <p class="session-message error">{{ session('errorMessage') }}</p>
                @endif

                <div class="input-group">
                    <label for="email">Correo:</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus placeholder="tu@correo.com">
                    <span class="input-icon" aria-hidden="true">
                        <!-- email icon -->
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                          <path d="M3 6.5C3 5.67 3.67 5 4.5 5H19.5C20.33 5 21 5.67 21 6.5V17.5C21 18.33 20.33 19 19.5 19H4.5C3.67 19 3 18.33 3 17.5V6.5Z" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
                          <path d="M21 6L12 13L3 6" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    @error('email')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="input-group">
                    <label for="password">Contraseña:</label>
                    <input id="password" type="password" name="password" required placeholder="••••••••">
                    <button type="button" class="input-icon toggle-password" data-target="password" aria-pressed="false" title="Mostrar contraseña">
                        <!-- eye icon (initial) -->
                        <svg class="eye-open" width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                          <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
                          <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <svg class="eye-closed" width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" style="display:none;">
                          <path d="M17.94 17.94A10.94 10.94 0 0112 19c-7 0-11-7-11-7a21.49 21.49 0 014.11-5.06" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
                          <path d="M1 1l22 22" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                    @error('password')
                        <p class="field-error">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="btn-primary">Iniciar sesión</button>
            </form>
        </div>
    </main>

    <script>
    document.querySelectorAll('.toggle-password').forEach(function(btn){
      btn.addEventListener('click', function(){
        var targetId = this.dataset.target;
        var input = document.getElementById(targetId);
        if(!input) return;
        var eyeOpen = this.querySelector('.eye-open');
        var eyeClosed = this.querySelector('.eye-closed');
        if(input.type === 'password'){
          input.type = 'text';
          this.setAttribute('aria-pressed','true');
          this.title = 'Ocultar contraseña';
          if(eyeOpen) eyeOpen.style.display = 'none';
          if(eyeClosed) eyeClosed.style.display = 'block';
        } else {
          input.type = 'password';
          this.setAttribute('aria-pressed','false');
          this.title = 'Mostrar contraseña';
          if(eyeOpen) eyeOpen.style.display = 'block';
          if(eyeClosed) eyeClosed.style.display = 'none';
        }
      });
    });
    </script>
</body>
</html>
