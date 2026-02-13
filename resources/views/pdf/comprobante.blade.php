<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Comprobante de Entrega #{{ $registro->id }}</title>
    <style>
      @page { margin: 20mm 12mm; }
      body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #222; }
      .container { margin: 0; padding: 0; }
      .header-wrap { display: block; width: 100%; margin-bottom: 6px; }
      .brand { font-weight: 700; font-size: 18px; color: #0b1220; }
      .brand img { max-height: 48px; width: auto; display: inline-block; vertical-align: middle; }
      .doc-title { text-align: center; font-weight: 700; font-size: 14px; color: #0b1220; padding: 6px 0; border-bottom: 2px solid #e6eef8; }
      .meta { text-align: right; font-size: 11px; color: #334155; }
      .company-row { width:100%; }

      .card { border: 1px solid #e6eef8; background: #f8fafc; padding: 8px; border-radius: 4px; }
      .card .label { font-weight: 600; color: #0b1220; font-size: 11px; }
      .card .value { color: #0f172a; font-size: 12px; }

      table { border-collapse: collapse; width: 100%; }
      .elementos-table th, .elementos-table td { border: 1px solid #dbeafe; padding: 8px; vertical-align: middle; }
      .elementos-table th { background: #0f172a; color: #ffffff; font-weight: 700; font-size: 12px; }
      .elementos-table tbody tr:nth-child(odd) td { background: #fbfdff; }
      .elementos-table tbody tr:nth-child(even) td { background: #ffffff; }

      .signature-line { border-top: 1px solid #c7d2fe; margin-top: 18px; padding-top: 8px; text-align: center; color: #334155; min-height: 10px; }
      .firma-img { display: inline-block; height: 48px; margin: 8px 0 4px; max-width: 220px; }
      .legal { font-size: 11px; color: #334155; padding: 8px; }

      .footer { font-size: 10px; color: #64748b; text-align: center; margin-top: 18px; clear: both; }
      .page-number { float: right; font-size: 10px; color: #64748b; }

      .small { font-size: 11px; color: #475569; }
      .center { text-align: center; }
      .section-title { font-weight: 700; color: #0b1220; }
    </style>
</head>
<body>
<div class="container">
@php
  $fechaReg = \Carbon\Carbon::parse($registro->created_at)->format('d/m/Y');
  $nombreCompleto = trim(($registro->nombres ?? '') . ' ' . ($registro->apellidos ?? ''));
  $doc = $registro->numero_documento ?? 'N/A';
  $cargo = $registro->cargo ?? 'N/A';
  
  // Persona que entrega: usar el campo guardado en la entrega, o fallback a sesión
  $personaEntrega = $registro->entrega_user ?? null;
  if (empty($personaEntrega)) {
    $personaEntrega = 'Sistema';
    $auth = session('auth.user');
    if (is_array($auth) && isset($auth['name'])) { $personaEntrega = $auth['name']; }
    elseif (is_object($auth) && isset($auth->name)) { $personaEntrega = $auth->name; }
  }

  // Normalizar tipo y detectar motivo base
  $tipoNormalizado = strtolower(trim($registro->tipo ?? ''));
  $motivoPrimeraVez = $tipoNormalizado === 'primera vez' || strpos($tipoNormalizado, 'primera') !== false;
  $motivoReposicion = $tipoNormalizado === 'reposición' || $tipoNormalizado === 'reposicion' || strpos($tipoNormalizado, 'reposi') !== false;
  $motivoPeriodica = strpos($tipoNormalizado, 'period') !== false || strpos($tipoNormalizado, 'periodica') !== false || strpos($tipoNormalizado, 'periodic') !== false;

  // Tipo de entrega explícito (mostrar en motivo)
  $tipoEntregaLabel = $registro->tipo ?? ($registro->tipo_entrega ?? ($registro->tipo_recepcion ?? ''));
  $tipoEntregaLabel = is_string($tipoEntregaLabel) ? trim($tipoEntregaLabel) : '';
@endphp

<!-- HEADER -->
<div class="header-wrap">
  <table class="company-row">
    <tr>
      <td style="width:55%; vertical-align: middle;">
        <div class="brand">
          @php
            $logoFile = public_path('img/logoVigia.jpeg');
            $logoSrc = null;
            if (is_file($logoFile)) {
              try { $logoSrc = 'data:image/png;base64,' . base64_encode(file_get_contents($logoFile)); } catch (\Throwable $e) { $logoSrc = null; }
            }
          @endphp
          @if($logoSrc)
            <img src="{{ $logoSrc }}" alt="Logo" />
            <span style="margin-left:8px; vertical-align: middle;">Vigía Plus Logistics</span>
          @else
            <span>Vigía Plus Logistics</span>
          @endif
        </div>
      </td>
      <td style="width:45%; vertical-align: middle; text-align:right;">
        <div class="meta">
          <div><strong>CÓDIGO:</strong> SGI-FO-011</div>
          <div><strong>FECHA:</strong> {{ $fechaReg }}</div>
        </div>
      </td>
    </tr>
  </table>
</div>

<div class="doc-title">ENTREGA DE ELEMENTOS DE PROTECCIÓN PERSONAL</div>

<!-- Datos del colaborador (card) -->
<div style="margin-top:8px; margin-bottom:8px;">
  <table style="width:100%">
    <tr>
      <td style="width:65%; padding-right:8px;">
        <div class="card">
          <div class="label">NOMBRE COMPLETO</div>
          <div class="value">{{ $nombreCompleto ?: 'N/A' }}</div>
          <div style="margin-top:6px;">
            <span class="label">CARGO:</span> <span class="small">{{ $cargo }}</span>
          </div>
        </div>
      </td>
      <td style="width:35%;">
        <div class="card">
          <div class="label">DOCUMENTO</div>
          <div class="value">{{ $doc }}</div>
          <div style="margin-top:6px;">
            <span class="label">OPERACIÓN</span> <div class="small">{{ $registro->operacion ?? '-' }}</div>
          </div>
        </div>
      </td>
    </tr>
  </table>
</div>

<!-- Texto legal -->
<div class="legal" style="border:1px solid #dbeafe; margin-bottom:10px; padding:10px; background:#fbfdff;">
  <strong>Art. 122 ley 9 de 1979:</strong>
  "Todos los empleadores están obligados a proporcionar a cada trabajador, sin costo para este, elementos de protección personal en cantidad y calidad acordes a los riesgos reales o potenciales en los lugares de trabajo."
</div>

<!-- Tabla principal -->
<table class="elementos-table">
  <thead>
    <tr>
      <th style="width:12%;" class="center">FECHA</th>
      <th style="width:36%;" class="center">ELEMENTO ENTREGADO</th>
      <th style="width:20%;" class="center">MOTIVO<br><span class="small">(1° Vez / Reposición)</span></th>
      <th style="width:12%;" class="center">FIRMA</th>
      <th style="width:20%;" class="center">PERSONA QUE ENTREGA</th>
    </tr>
  </thead>
  <tbody>
    @php $rows = 8; @endphp
    @foreach($elementos as $i => $el)
      @php
        $sku = is_array($el) ? ($el['sku'] ?? 'N/A') : (isset($el->sku) ? $el->sku : 'N/A');
        // Buscar nombre en 'name_produc' o 'name' (el JS usa 'name')
        $nombreProduc = is_array($el) 
            ? ($el['name_produc'] ?? $el['name'] ?? null) 
            : (isset($el->name_produc) ? $el->name_produc : (isset($el->name) ? $el->name : null));
        // Mostrar SKU y nombre del producto si está disponible
        if (!empty($nombreProduc)) {
          $elementoDisplay = $sku . ' - ' . $nombreProduc;
        } else {
          $elementoDisplay = $sku;
        }
        if ($motivoPrimeraVez) {
          $motivoText = 'ENTREGA 1° VEZ';
        } elseif ($motivoReposicion) {
          $motivoText = 'REPOSICIÓN';
        } elseif ($motivoPeriodica) {
          $motivoText = 'PERIÓDICA';
        } else {
          $motivoText = ''; 
        }

        // Agregar tipo de entrega al motivo para mayor claridad
        $extraTipo = $tipoEntregaLabel ? (' — ' . strtoupper($tipoEntregaLabel)) : '';
        $motivoText = trim($motivoText . $extraTipo);
        $firmaSrc = isset($firma) ? ($firma['entrega'] ?? ($firma['recepcion'] ?? null)) : null;
      @endphp
      <tr>
        <td class="center">{{ $fechaReg }}</td>
        <td>{{ $elementoDisplay }}</td>
        <td class="center">{{ $motivoText }}</td>
        <td class="center">&nbsp;</td>
        <td class="center">{{ $personaEntrega }}</td>
      </tr>
    @endforeach
    @for($j = count($elementos); $j < $rows; $j++)
      <tr>
        <td style="height:18px">&nbsp;</td><td></td><td></td><td></td><td></td>
      </tr>
    @endfor
  </tbody>
</table>

<!-- Compromiso y firma -->
<div class="legal" style="margin-top:10px;">
  <strong>Compromiso:</strong>
  <div style="margin-top:6px;">Al recibir estos elementos de protección personal me comprometo a mantenerlos en buen estado y darles el uso adecuado conforme a las instrucciones recibidas. Autorizo, en caso de pérdida por negligencia, los descuentos y reposición según la política de la empresa.</div>
</div>

@php $firmaSrcBottom = isset($firma) ? ($firma['entrega'] ?? ($firma['recepcion'] ?? null)) : null; @endphp
<div style="margin-top:22px; text-align:center;">
  @if(!empty($firmaSrcBottom))
    <img class="firma-img" src="{{ $firmaSrcBottom }}" alt="Firma Colaborador" />
  @else
    <div style="height:56px;"></div>
  @endif
  <div class="signature-line" style="width:60%; margin:0 auto;">Firma Colaborador</div>
</div>

<div class="footer">
  Vigía Plus Logistics — Documento generado {{ now()->format('Y-m-d H:i') }}
  <span class="page-number">Página <span class="pagenum"></span></span>
</div>

<script type="text/php">
if (isset($pdf)) {
    $font = $fontMetrics->get_font("DejaVuSans", "bold");
    $pdf->page_text(520, 820, "Página {PAGE_NUM} de {PAGE_COUNT}", $font, 9, array(0.4,0.4,0.4));
}
</script>

</div>
</body>
</html>
