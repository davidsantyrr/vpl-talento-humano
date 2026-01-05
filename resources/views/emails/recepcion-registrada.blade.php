<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprobante de Recepción</title>
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
            background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
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
            border-left: 4px solid #06b6d4;
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
            background: #06b6d4;
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
        .badge-cambio {
            background: #e0e7ff;
            color: #4338ca;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📥 Comprobante de Recepción</h1>
        <p>Registro #{{ $recepcion->id }}</p>
    </div>

    <div class="content">
        <p>Estimado/a <strong>{{ $recepcion->recepcion_user }}</strong>,</p>
        <p>Se ha registrado exitosamente una nueva recepción (devolución) en el sistema de Gestión de Talento Humano.</p>

        <div class="info-box">
            <h3 style="margin-top: 0; color: #06b6d4;">📋 Información de quien Devuelve</h3>
            <div class="info-row">
                <span class="label">Documento:</span>
                <span class="value">{{ $recepcion->tipo_documento }} - {{ $recepcion->numero_documento }}</span>
            </div>
            <div class="info-row">
                <span class="label">Nombre Completo:</span>
                <span class="value">{{ $recepcion->nombres }} {{ $recepcion->apellidos }}</span>
            </div>
            <div class="info-row">
                <span class="label">Tipo de Recepción:</span>
                <span class="value">
                    @php
                        $badgeClass = $recepcion->tipo_recepcion === 'prestamo' ? 'badge-prestamo' : 'badge-cambio';
                    @endphp
                    <span class="badge {{ $badgeClass }}">{{ ucfirst($recepcion->tipo_recepcion) }}</span>
                </span>
            </div>
            <div class="info-row">
                <span class="label">Fecha y Hora:</span>
                <span class="value">{{ \Carbon\Carbon::parse($recepcion->created_at)->format('d/m/Y H:i:s') }}</span>
            </div>
        </div>

        @if(count($elementos) > 0)
        <h3 style="color: #06b6d4;">📦 Elementos Recibidos</h3>
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
