<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: sans-serif; }
        .firma img { width: 200px; }
    </style>
</head>
<body>

<h2>Comprobante de Entrega</h2>

<p><strong>Nombre:</strong> {{ $nombre }}</p>

<div class="firma">
    <p>Firma del receptor</p>
    <img src="{{ $firmaBase64 }}">
</div>

</body>
</html>
