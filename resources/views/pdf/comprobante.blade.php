<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Comprobante de Entrega #{{ $registro->id }}</title>
    <style>
      body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; margin: 18px; }
      table { border-collapse: collapse; width: 100%; }
      .elementos-table th, .elementos-table td { border: 1px solid #1e40af; padding: 6px; }
      .elementos-table th { background: #eef2ff; font-weight: bold; }
      .header td { border: 1px solid #1e40af; padding: 8px; }
      .brand { font-weight: bold; font-size: 18px; color: #0f172a; }
      .brand img { max-height: 46px; width: auto; }
      .title { text-align: center; font-weight: bold; font-size: 16px; }
      .small { font-size: 11px; color: #334155; }
      .section-title { font-weight: bold; }
      .center { text-align: center; }
      .signature-line { border-top: 1px dashed #1e40af; margin-top: 22px; padding-top: 8px; text-align: center; }
      .firma-img { display: inline-block; height: 42px; margin: 4px 0; }
      .legal { font-size: 11px; color: #333; padding: 6px; }
    </style>
</head>
<body>
@php
    $fechaReg = \Carbon\Carbon::parse($registro->created_at)->format('d/m/Y');
    $nombreCompleto = trim(($registro->apellidos ?? '') . ' ' . ($registro->nombres ?? ''));
    $doc = $registro->numero_documento ?? 'N/A';
    $cargo = $registro->cargo ?? 'N/A';
    $personaEntrega = 'Sistema';
    $auth = session('auth.user');
    if (is_array($auth) && isset($auth['name'])) { $personaEntrega = $auth['name']; }
    elseif (is_object($auth) && isset($auth->name)) { $personaEntrega = $auth->name; }
    $motivoPrimeraVez = strtolower($registro->tipo ?? '') === 'primera vez';
    $motivoReposicion = !$motivoPrimeraVez && strtolower($registro->tipo ?? '') !== '';
@endphp

<!-- Cabecera principal -->
<table class="header" style="margin-bottom:8px;">
  <tr>
    <td style="width:40%;">
      @php
        $logoFile = public_path('img/logoVigia.jpeg');
        $logoSrc = null;
        if (is_file($logoFile)) {
          try { $logoSrc = 'data:image/png;base64,' . base64_encode(file_get_contents($logoFile)); } catch (\Throwable $e) { $logoSrc = null; }
        }
      @endphp
      <div class="brand">
        @if($logoSrc)
          <img src="{{ $logoSrc }}" alt="Vigía" />
        @else
          Vigía Plus Logistics
        @endif
      </div>
    </td>
    <td class="title" style="width:40%;">ENTREGA DE ELEMENTOS DE PROTECCIÓN PERSONAL</td>
    <td style="width:20%;">
      <table style="width:100%; border-collapse: collapse;">
        <tr><td class="small">CÓDIGO</td><td class="small">SGI-FO-011</td></tr>
        <tr><td class="small">FECHA</td><td class="small">{{ $fechaReg }}</td></tr>
      </table>
    </td>
  </tr>
</table>

<!-- Datos del colaborador -->
<table style="margin-bottom:6px;">
  <tr>
    <td style="width:50%; border:1px solid #1e40af; padding:6px;">
      <span class="section-title">APELLIDOS Y NOMBRES COLABORADOR:</span>
      <div>{{ $nombreCompleto ?: 'N/A' }}</div>
    </td>
    <td style="width:25%; border:1px solid #1e40af; padding:6px;">
      <span class="section-title">No. DOCUMENTO:</span>
      <div>{{ $doc }}</div>
    </td>
    <td style="width:25%; border:1px solid #1e40af; padding:6px;">
      <span class="section-title">CARGO:</span>
      <div>{{ $cargo }}</div>
    </td>
  </tr>
</table>

<!-- Texto legal -->
<div class="legal" style="border:1px solid #1e40af; margin-bottom:6px;">
  <strong>Art. 122 ley 9 de 1979:</strong>
  "Todos los empleadores están obligados a proporcionar a cada trabajador, sin costo para este, elementos de protección personal en cantidad y calidad acordes a los riesgos reales o potenciales en los lugares de trabajo."
  
</div>

<!-- Tabla principal -->
<table class="elementos-table">
  <thead>
    <tr>
      <th style="width:10%;" class="center">FECHA ENTREGA</th>
      <th style="width:22%;" class="center">ELEMENTO ENTREGADO</th>
      <th style="width:22%;" class="center">MOTIVO ENTREGA<br><span class="small">ENTREGA 1° VEZ / REPOSICIÓN</span></th>
      <th style="width:13%;" class="center">FIRMA COLABORADOR</th>
      <th style="width:23%;" class="center">PERSONA QUE ENTREGA Y SENSIBILIZA</th>
      <th style="width:10%;" class="center">OBSERVACIONES</th>
    </tr>
  </thead>
  <tbody>
    @php $rows = 8; @endphp
    @foreach($elementos as $i => $el)
      @php
        $sku = is_array($el) ? ($el['sku'] ?? 'N/A') : (isset($el->sku) ? $el->sku : 'N/A');
        $nombreProduc = is_array($el) ? ($el['name_produc'] ?? null) : (isset($el->name_produc) ? $el->name_produc : null);
        $nombre = $nombreProduc ?: $sku;
        $motivoText = $motivoPrimeraVez ? 'ENTREGA 1° VEZ' : ($motivoReposicion ? 'REPOSICIÓN' : '');
        $firmaSrc = isset($firma) ? ($firma['entrega'] ?? ($firma['recepcion'] ?? null)) : null;
      @endphp
      <tr>
        <td class="center">{{ $fechaReg }}</td>
        <td>{{ $nombre }}</td>
        <td class="center">{{ $motivoText }}</td>
        <td class="center">
          @if($i === 0 && !empty($firmaSrc))
            <img class="firma-img" src="{{ $firmaSrc }}" alt="Firma" />
          @endif
        </td>
        <td>{{ $personaEntrega }}</td>
        <td></td>
      </tr>
    @endforeach
    @for($j = count($elementos); $j < $rows; $j++)
      <tr>
        <td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td>
      </tr>
    @endfor
  </tbody>
</table>

<!-- Compromiso y firma -->
<div class="legal" style="border:1px solid #1e40af; margin-top:8px;">
  Al recibir estos elementos de protección personal me comprometo a mantenerlos en buen estado y hacer buen uso de los mismos, acorde a los riesgos que me han sido explicados, según mi cargo. Si durante el tiempo de vida útil de los elementos que me han sido entregados se llegaran a extraviar autorizo para que automáticamente sean descontados de mi salario y autoricen la compra de unos nuevos.
</div>
@php $firmaSrcBottom = isset($firma) ? ($firma['entrega'] ?? ($firma['recepcion'] ?? null)) : null; @endphp
<div style="text-align:center; margin-top:10px;">
  @if(!empty($firmaSrcBottom))
    <img class="firma-img" src="{{ $firmaSrcBottom }}" alt="Firma" />
  @endif
  <div class="signature-line">Firma Colaborador</div>
  
</div>

</body>
</html>
