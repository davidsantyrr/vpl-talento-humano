<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Notificación de inventario bajo</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; color: #222;">
  <p>Estimado/a,</p>

  <p>Le informamos que el siguiente artículo ha alcanzado un nivel de stock igual o inferior al umbral configurado:</p>

  <p>
    <strong>Elemento (identificador):</strong> {{ $elemento }}<br>
    <strong>SKU:</strong> {{ $sku }}<br>
    @if(!empty($nombre))<strong>Nombre:</strong> {{ $nombre }}<br>@endif
    <strong>Stock actual:</strong> {{ $stockActual }}<br>
    <strong>Umbral configurado:</strong> {{ $umbral }}
  </p>

  <p>Por favor proceda a gestionar la reposición según los procedimientos internos. Si necesita asistencia, contacte con el equipo de operaciones.</p>

  <p>Atentamente,<br>
  {{ $appName ?? config('app.name') }}</p>

</body>
</html>
