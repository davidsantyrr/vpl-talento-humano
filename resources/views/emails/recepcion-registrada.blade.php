<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprobante de Recepción</title>
    <style>
        :root {
            --bg-dark: #0b1b2b;
            --primary: #111f2e;
            --accent: #667eea;
            --muted: #6b7280;
            --panel: #f3f4f6;
            --border: #d1d5db;
            --white: #ffffff;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #111827;
            max-width: 760px;
            margin: 0 auto;
            padding: 0;
            background: #edf2f7;
        }

        .wrapper {
            margin: 18px auto;
            padding: 0 16px;
        }

        .card {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: 0 12px 28px rgba(2, 6, 23, 0.08);
            overflow: hidden;
        }

        .header {
            background: var(--bg-dark);
            color: var(--white);
            padding: 22px;
            text-align: center;
        }

        .header h1 {
            margin: 0;
            font-size: 22px;
            font-weight: 800;
        }

        .header p {
            margin: 6px 0 0;
            font-size: 13px;
            opacity: .9;
        }

        .content {
            background: var(--white);
            padding: 20px;
        }

        .intro {
            color: #1f2937;
            font-size: 14px;
            margin: 0 0 12px;
        }

        .info-box {
            background: #fff;
            padding: 16px;
            margin: 14px 0;
            border-radius: 12px;
            border: 1px solid var(--border);
            box-shadow: 0 6px 18px rgba(2, 6, 23, 0.06);
        }

        .info-title {
            margin: 0 0 8px;
            color: var(--accent);
            font-weight: 700;
            font-size: 16px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin: 8px 0;
            font-size: 14px;
        }

        .label {
            font-weight: 600;
            color: #374151;
        }

        .value {
            color: #111827;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 12px 0;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
        }

        thead th {
            background: var(--bg-dark);
            color: #fff;
            padding: 10px;
            text-align: left;
            font-size: 13px;
        }

        tbody td {
            padding: 10px;
            border-top: 1px solid rgba(2, 6, 23, 0.06);
            font-size: 13px;
        }

        tbody tr:nth-child(even) {
            background: #f9fafb;
        }

        .footer {
            text-align: center;
            padding: 14px;
            color: var(--muted);
            font-size: 12px;
            background: #fff;
            border-top: 1px solid var(--border);
        }

        .badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            border: 1px solid rgba(17, 31, 46, .12);
        }

        .badge-prestamo {
            background: #e6f4ea;
            color: #054d2e;
        }

        .badge-cambio {
            background: #e0e7ff;
            color: #4338ca;
        }

        .note {
            color: #374151;
            font-size: 13px;
            background: #f9fafb;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 12px;
        }

        @media (max-width:640px) {
            .content {
                padding: 16px;
            }

            .info-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 4px;
            }
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <div class="card">
            <div class="header">
                <h1>Comprobante de Recepción</h1>
                <p>Registro #{{ $recepcion->id }}</p>
            </div>
            <div class="content">
                <p class="intro">Estimado/a <strong>{{ $recepcion->recepcion_user }}</strong>,</p>
                <p class="intro">Se ha registrado exitosamente una nueva recepción (devolución) en el sistema de Gestión
                    de Talento Humano.</p>

                <div class="info-box">
                    <div class="info-title">Información de quien Devuelve</div>
                    <div class="info-row">
                        <span class="label">Documento</span>
                        <span class="value">{{ $recepcion->tipo_documento }} - {{ $recepcion->numero_documento }}</span>
                    </div>
                    <div class="info-row">
                        <span class="label">Nombre Completo</span>
                        <span class="value">{{ $recepcion->nombres }} {{ $recepcion->apellidos }}</span>
                    </div>
                    <div class="info-row">
                        <span class="label">Tipo de Recepción</span>
                        <span class="value">
                            @php
                            $badgeClass = $recepcion->tipo_recepcion === 'prestamo' ? 'badge-prestamo' : 'badge-cambio';
                            @endphp
                            <span class="badge {{ $badgeClass }}">{{ ucfirst($recepcion->tipo_recepcion) }}</span>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="label">Fecha y Hora</span>
                        <span class="value">{{ \Carbon\Carbon::parse($recepcion->created_at)->format('d/m/Y H:i:s')
                            }}</span>
                    </div>
                </div>

                @if(count($elementos) > 0)
                <div class="info-title">Elementos Recibidos</div>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>SKU</th>
                            <th style="text-align:center;">Cantidad</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($elementos as $index => $elemento)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $elemento->sku ?? $elemento['sku'] ?? 'N/A' }}</td>
                            <td style="text-align:center;">{{ $elemento->cantidad ?? $elemento['cantidad'] ?? 'N/A' }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @endif

                <p class="note"><strong>El comprobante PDF se encuentra adjunto a este correo.</strong></p>
                <p class="intro" style="color:#6b7280;">Este es un mensaje automático, por favor no responder a este
                    correo.</p>
            </div>
            <div class="footer">
                <p><strong>Sistema de Gestión de Talento Humano</strong></p>
                <p>{{ now()->format('Y') }} © Todos los derechos reservados</p>
            </div>
        </div>
    </div>
</body>

</html>