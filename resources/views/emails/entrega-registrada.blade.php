<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprobante de Entrega</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
            border-radius: 10px 10px 0 0;
        }
        .content {
            background: #f9fafb;
            padding: 30px;
            border: 1px solid #e5e7eb;
        }
        .info-box {
            background: white;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        .info-row {
            margin: 10px 0;
        }
        .label {
            font-weight: bold;
            color: #4b5563;
        }
        .value {
            color: #1f2937;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: white;
        }
        th {
            background: #667eea;
            color: white;
            padding: 12px;
            text-align: left;
        }
        td {
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
        }
        tr:nth-child(even) {
            background: #f9fafb;
        }
        .footer {
            text-align: center;
            padding: 20px;
            color: #6b7280;
            font-size: 12px;
        }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        .badge-prestamo {
            background: #dbeafe;
            color: #1e40af;
        }
        .badge-primera {
            background: #d1fae5;
            color: #047857;
        }
        .badge-periodica {
            background: #fef3c7;
            color: #b45309;
        }
        .badge-cambio {
            background: #e0e7ff;
            color: #4338ca;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>✅ Comprobante de Entrega</h1>
        <p>Registro #{{ $entrega->id }}</p>
    </div>

    <div class="content">
        <p>Estimado/a <strong>{{ $entrega->entrega_user }}</strong>,</p>
        <p>Se ha registrado exitosamente una nueva entrega en el sistema de Gestión de Talento Humano.</p>

        <div class="info-box">
            <h3 style="margin-top: 0; color: #667eea;">📋 Información del Receptor</h3>
            <div class="info-row">
                <span class="label">Documento:</span>
                <span class="value">{{ $entrega->tipo_documento }} - {{ $entrega->numero_documento }}</span>
            </div>
            <div class="info-row">
                <span class="label">Nombre Completo:</span>
                <span class="value">{{ $entrega->nombres }} {{ $entrega->apellidos }}</span>
            </div>
            <div class="info-row">
                <span class="label">Tipo de Entrega:</span>
                <span class="value">
                    @php
                        $badgeClass = match($entrega->tipo_entrega) {
                            'prestamo' => 'badge-prestamo',
                            'primera vez' => 'badge-primera',
                            'periodica' => 'badge-periodica',
                            'cambio' => 'badge-cambio',
                            default => 'badge-prestamo'
                        };
                    @endphp
                    <span class="badge {{ $badgeClass }}">{{ ucfirst($entrega->tipo_entrega) }}</span>
                </span>
            </div>
            <div class="info-row">
                <span class="label">Fecha y Hora:</span>
                <span class="value">{{ \Carbon\Carbon::parse($entrega->created_at)->format('d/m/Y H:i:s') }}</span>
            </div>
        </div>

        @if(count($elementos) > 0)
        <h3 style="color: #667eea;">📦 Elementos Entregados</h3>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>SKU</th>
                    <th style="text-align: center;">Cantidad</th>
                </tr>
            </thead>
            <tbody>
                @foreach($elementos as $index => $elemento)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $elemento->sku ?? $elemento['sku'] ?? 'N/A' }}</td>
                    <td style="text-align: center;">{{ $elemento->cantidad ?? $elemento['cantidad'] ?? 'N/A' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif

        <p style="margin-top: 30px;"><strong>El comprobante PDF se encuentra adjunto a este correo.</strong></p>

        <p style="color: #6b7280; font-size: 14px; margin-top: 20px;">
            Este es un mensaje automático, por favor no responder a este correo.
        </p>
    </div>

    <div class="footer">
        <p><strong>Sistema de Gestión de Talento Humano</strong></p>
        <p>{{ now()->format('Y') }} © Todos los derechos reservados</p>
    </div>
</body>
</html>
