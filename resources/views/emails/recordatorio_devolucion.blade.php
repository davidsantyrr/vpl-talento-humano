<div style="font-family: Arial, sans-serif; color:#111;">
    <h2>Recordatorio de devolución de préstamo</h2>
    @php
        $fullName = trim(($entrega->nombres ?? '').' '.($entrega->apellidos ?? ''));
    @endphp
    <p>
        Hola {{ $destinatarioNombre ?? $fullName ?: 'usuario' }},<br>
        Este es un recordatorio de que hoy está programada la devolución del préstamo.
    </p>
    @if(!empty($entrega->numero_documento))
        <p><strong>Quien debe devolver:</strong> {{ $fullName ?: 'N/A' }} (Documento: {{ $entrega->numero_documento }})</p>
    @else
        <p><strong>Quien debe devolver:</strong> {{ $fullName ?: 'N/A' }}</p>
    @endif
    @if(!empty($fechaLimite))
        <p><strong>Fecha de devolución:</strong> {{ \Illuminate\Support\Carbon::parse($fechaLimite)->format('Y-m-d') }}</p>
    @endif

    <h3>Elementos entregados</h3>
    <table width="100%" cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse;">
        <thead>
            <tr>
                <th align="left">SKU</th>
                <th align="left">Nombre</th>
                <th align="right">Cantidad</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $it)
                <tr>
                    <td>{{ $it['sku'] }}</td>
                    <td>{{ $it['name'] ?? $it['sku'] }}</td>
                    <td align="right">{{ $it['cantidad'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p style="margin-top:16px;">Si ya realizaste la devolución, por favor ignora este mensaje.</p>
    <p>Gracias.</p>
</div>
