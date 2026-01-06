<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{{ $tipo === 'entrega' ? 'Comprobante de Entrega' : 'Comprobante de Recepción' }} #{{ $registro->id }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            font-size: 12px;
            color: #333;
            padding: 20px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #111f2e;
            padding-bottom: 15px;
        }
        
        .header h1 {
            color: #111f2e;
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .header .subtitle {
            color: #666;
            font-size: 14px;
        }
        
        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 11px;
            margin-top: 10px;
        }
        
        .badge-entrega {
            background: #ede9fe;
            color: #7c3aed;
        }
        
        .badge-recepcion {
            background: #cffafe;
            color: #0891b2;
        }
        
        .info-section {
            margin-bottom: 25px;
        }
        
        .info-section h2 {
            background: #f3f4f6;
            padding: 8px 12px;
            font-size: 14px;
            color: #111f2e;
            border-left: 4px solid #111f2e;
            margin-bottom: 15px;
        }
        
        .info-grid {
            display: table;
            width: 100%;
            margin-bottom: 10px;
        }
        
        .info-row {
            display: table-row;
        }
        
        .info-label {
            display: table-cell;
            font-weight: bold;
            padding: 8px 12px;
            background: #f9fafb;
            width: 35%;
            border: 1px solid #e5e7eb;
        }
        
        .info-value {
            display: table-cell;
            padding: 8px 12px;
            border: 1px solid #e5e7eb;
        }
        
        .elementos-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        
        .elementos-table thead {
            background: #111f2e;
            color: white;
        }
        
        .elementos-table th {
            padding: 10px;
            text-align: left;
            font-weight: bold;
        }
        
        .elementos-table td {
            padding: 10px;
            border: 1px solid #e5e7eb;
        }
        
        .elementos-table tbody tr:nth-child(even) {
            background: #f9fafb;
        }
        
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #e5e7eb;
            text-align: center;
            font-size: 10px;
            color: #666;
        }
        
        .firma-section {
            margin-top: 60px;
            display: table;
            width: 100%;
        }
        
        .firma-box {
            display: table-cell;
            width: 45%;
            text-align: center;
            padding: 20px;
            vertical-align: top;
        }
        
        .firma-line {
            border-top: 2px solid #333;
            margin-top: 10px;
            padding-top: 10px;
            min-height: 50px;
        }
        
        .firma-label {
            font-weight: bold;
            color: #111f2e;
            margin-bottom: 8px;
            margin-top: 8px;
        }
        
        .status-badge {
            padding: 5px 12px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 11px;
        }
        
        .status-success {
            background: #d1fae5;
            color: #047857;
        }
        
        .status-warning {
            background: #fef3c7;
            color: #b45309;
        }

        img.firma-img {
            max-width: 100%;
            height: 100px;
            object-fit: contain;
            margin-top: 6px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $tipo === 'entrega' ? 'COMPROBANTE DE ENTREGA' : 'COMPROBANTE DE RECEPCIÓN' }}</h1>
        <p class="subtitle">Registro #{{ $registro->id }} | Fecha: {{ \Carbon\Carbon::parse($registro->created_at)->format('d/m/Y H:i:s') }}</p>
        <span class="badge {{ $tipo === 'entrega' ? 'badge-entrega' : 'badge-recepcion' }}">
            {{ strtoupper($tipo) }}
        </span>
    </div>

    <div class="info-section">
        <h2>Información del Usuario</h2>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Tipo de Documento:</div>
                <div class="info-value">{{ $registro->tipo_documento ?? 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Número de Documento:</div>
                <div class="info-value">{{ $registro->numero_documento ?? 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Nombres:</div>
                <div class="info-value">{{ $registro->nombres ?? 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Apellidos:</div>
                <div class="info-value">{{ $registro->apellidos ?? 'N/A' }}</div>
            </div>
        </div>
    </div>

    <div class="info-section">
        <h2>Detalles del Registro</h2>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Operación:</div>
                <div class="info-value">{{ $registro->operacion ?? 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Tipo:</div>
                <div class="info-value">{{ ucfirst($registro->tipo ?? 'N/A') }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Estado:</div>
                <div class="info-value">
                    @if($tipo === 'entrega')
                        @if(in_array($registro->tipo, ['periodica', 'primera vez']))
                            <span class="status-badge status-success">COMPLETADO</span>
                        @else
                            <span class="status-badge {{ $registro->recibido ? 'status-success' : 'status-warning' }}">
                                {{ $registro->recibido ? 'RECIBIDO' : 'PENDIENTE' }}
                            </span>
                        @endif
                    @else
                        <span class="status-badge {{ $registro->recibido ? 'status-success' : 'status-warning' }}">
                            {{ $registro->recibido ? 'ENTREGADO' : 'PENDIENTE' }}
                        </span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="info-section">
        <h2>Elementos</h2>
        <table class="elementos-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>SKU</th>
                    <th style="text-align: center;">Cantidad</th>
                </tr>
            </thead>
            <tbody>
                @forelse($elementos as $index => $elemento)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $elemento->sku ?? $elemento['sku'] ?? ($elemento->codigo ?? 'N/A') }}</td>
                        <td style="text-align: center;">{{ $elemento->cantidad ?? $elemento['cantidad'] ?? 'N/A' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" style="text-align: center; color: #999;">No hay elementos registrados</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="firma-section">
        @php
            // Procesar firma de forma directa y simple
            $firmaSrc = null;
            $debugMsg = [];
            
            if (isset($firma)) {
                $debugMsg[] = 'Variable firma existe';
                $debugMsg[] = 'Tipo: ' . gettype($firma);
                
                if (is_array($firma)) {
                    $debugMsg[] = 'Keys: ' . implode(', ', array_keys($firma));
                    
                    // Buscar la firma correcta según el tipo de documento
                    $firmaKey = ($tipo === 'entrega') ? 'entrega' : 'recepcion';
                    $debugMsg[] = "Buscando clave: {$firmaKey}";
                    
                    if (isset($firma[$firmaKey])) {
                        $rawFirma = $firma[$firmaKey];
                        $debugMsg[] = 'Firma encontrada, longitud: ' . strlen($rawFirma);
                        
                        // Limpiar la firma
                        $cleanFirma = trim($rawFirma);
                        $cleanFirma = preg_replace('/\s+/', '', $cleanFirma);
                        $debugMsg[] = 'Firma limpia, longitud: ' . strlen($cleanFirma);
                        
                        // Convertir a data URL si es necesario
                        if (str_starts_with($cleanFirma, 'data:image')) {
                            $firmaSrc = $cleanFirma;
                            $debugMsg[] = '✓ Ya es data URL';
                        } else {
                            // Quitar prefijo base64, si existe
                            $cleanFirma = preg_replace('/^base64,/', '', $cleanFirma);
                            if (strlen($cleanFirma) > 100) {
                                $firmaSrc = 'data:image/png;base64,' . $cleanFirma;
                                $debugMsg[] = '✓ Convertida a data URL';
                            } else {
                                $debugMsg[] = '✗ Firma muy corta';
                            }
                        }
                    } else {
                        $debugMsg[] = "✗ Clave '{$firmaKey}' no encontrada";
                    }
                } else {
                    $debugMsg[] = '✗ Firma no es array';
                }
            } else {
                $debugMsg[] = '✗ Variable firma NO existe';
            }
        @endphp
        @if($tipo === 'entrega')
            {{-- En entrega: solo firma del que RECIBE --}}
            <div class="firma-box" style="width: 100%; text-align: center;">
                <div style="margin-bottom: 10px;">
                    @if(!empty($firmaSrc))
                        <img class="firma-img" src="{{ $firmaSrc }}" alt="Firma" style="max-width:200px; height:80px; margin:0 auto 10px auto; display:block;">
                    @else
                        <p style="color: #ff0000; margin: 10px 0; font-weight: bold;">⚠ FIRMA NO DISPONIBLE</p>
                        <p style="color: #666; font-size: 8px; margin: 5px 0; line-height: 1.3;">
                            @foreach($debugMsg as $msg)
                                {{ $msg }}<br>
                            @endforeach
                        </p>
                    @endif
                </div>
                <div class="firma-line">
                    <p class="firma-label">Firma del que Recibe</p>
                    <p style="margin: 5px 0;">{{ $registro->nombres ?? 'N/A' }} {{ $registro->apellidos ?? '' }}</p>
                </div>
            </div>
        @else
            {{-- En recepción: solo firma del que ENTREGA (devuelve) --}}
            <div class="firma-box" style="width: 100%; text-align: center;">
                <div style="margin-bottom: 10px;">
                    @if(!empty($firmaSrc))
                        <img class="firma-img" src="{{ $firmaSrc }}" alt="Firma" style="max-width:200px; height:80px; margin:0 auto 10px auto; display:block;">
                    @else
                        <p style="color: #ff0000; margin: 10px 0; font-weight: bold;">⚠ FIRMA NO DISPONIBLE</p>
                        <p style="color: #666; font-size: 8px; margin: 5px 0; line-height: 1.3;">
                            @foreach($debugMsg as $msg)
                                {{ $msg }}<br>
                            @endforeach
                        </p>
                    @endif
                </div>
                <div class="firma-line">
                    <p class="firma-label">Firma del que Entrega (Devolución)</p>
                    <p style="margin: 5px 0;">{{ $registro->nombres ?? 'N/A' }} {{ $registro->apellidos ?? '' }}</p>
                </div>
            </div>
        @endif
    </div>

    <div class="footer">
        <p><strong>Documento generado automáticamente por el Sistema de Gestión de Talento Humano</strong></p>
        <p>Fecha de generación: {{ now()->format('d/m/Y H:i:s') }}</p>
    </div>
</body>
</html>
