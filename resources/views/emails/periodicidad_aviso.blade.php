<div style="font-family: Poppins, Arial, sans-serif; color:#111; line-height:1.4;">
  <h2>Aviso de periodicidad: {{ $p['name'] ?? $p['sku'] }}</h2>
  <p><strong>SKU:</strong> {{ $p['sku'] }}</p>
  <p><strong>Estado (semaforización):</strong> {{ $p['color'] ?? (ucfirst($p['urgency'] ?? 'ok')) }}</p>
  @php
    $displayDays = $p['threshold_days'] ?? ($p['days_int'] ?? (int) round($p['days_remaining'] ?? 0));
  @endphp
  <p><strong>Próxima entrega:</strong> {{ $p['next_date'] ?? 'N/A' }} (en {{ $displayDays }} días)</p>
  @if(!empty($p['threshold_days']))
    <p><strong>Umbral para semáforo ({{ $p['threshold_color'] ?? 'Nivel' }}):</strong> {{ $p['threshold_days'] }} días</p>
  @endif
  <p><strong>Cantidad a entregar:</strong> {{ $p['quantity'] ?? 1 }}</p>
  <p><strong>Usuarios destinatarios ({{ count($p['users'] ?? []) }}):</strong></p>
  <ul>
    @foreach($p['users'] ?? [] as $u)
      <li>{{ $u['nombres'] ?? ($u['name'] ?? '—') }} {{ $u['apellidos'] ?? '' }} @if(!empty($u['email'])) — {{ $u['email'] }} @endif (cantidad: {{ $u['cantidad'] ?? 1 }})</li>
    @endforeach
  </ul>
  <p>Si no hay usuarios listados, revisa la configuración de asignaciones o el historial de entregas.</p>
  <p style="color:#666; font-size:12px; margin-top:18px;">{{ config('app.name') }} — Aviso automático de periodicidad.</p>
</div>
