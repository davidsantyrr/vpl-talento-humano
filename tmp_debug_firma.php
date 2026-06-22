<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$personQuery = '1021673489';
$query = App\Models\Entrega::query();
$query->where('numero_documento', $personQuery);
$query->orderByDesc('created_at');
$previousEntregas = $query->with('elementos')->limit(20)->get();
foreach ($previousEntregas as $entrega) {
    $firmaPath = !empty($entrega->firma_path) ? storage_path('app/' . ltrim($entrega->firma_path, '/')) : null;
    $firma = ($firmaPath && file_exists($firmaPath)) ? 'YES' : 'NO';
    echo $entrega->id . ' => ' . ($entrega->firma_path ?? 'NULL') . ' => ' . $firma . ' => ' . ($entrega->comprobante_path ?? 'NULL') . ' => ' . $entrega->created_at . PHP_EOL;
}
echo 'count=' . $previousEntregas->count() . PHP_EOL;
