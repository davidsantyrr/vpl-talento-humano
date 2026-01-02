<?php

namespace App\Http\Controllers\entregasPdf;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Models\SubArea;
use App\Models\Entrega;
use App\Models\Producto;
use App\Models\Usuarios;
use App\Models\ElementoXEntrega;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;


class EntregaController extends Controller
{
    /** Mostrar el formulario de entregas */
    public function create()
    {
        $operations = SubArea::orderBy('operationName')->get();
        // Attempt to select expected columns; if the external table uses different column names,
        // fall back to reading rows and map candidate fields.
        $allProducts = collect();
        try {
            $conn = (new Producto())->getConnectionName() ?: config('database.default');
            $hasSku = Schema::connection($conn)->hasColumn('productos', 'sku');
            $hasName = Schema::connection($conn)->hasColumn('productos', 'name_produc');
            if ($hasSku && $hasName) {
                $allProducts = Producto::select('sku', 'name_produc')->orderBy('name_produc')->get();
            } else {
                // fallback: pull some rows and map likely fields
                $rows = Producto::limit(500)->get();
                $allProducts = $rows->map(function($r){
                    $sku = $r->sku ?? $r->codigo ?? $r->id ?? null;
                    $name = $r->name_produc ?? $r->nombre ?? $r->name ?? '';
                    return (object)['sku' => $sku, 'name_produc' => $name];
                })->filter(fn($x) => $x->sku !== null)->values();
            }
        } catch (\Exception $e) {
            // if anything fails, return empty collection to avoid breaking the view
            $allProducts = collect();
        }

        return view('formularioEntregas.formularioEntregas', compact('operations','allProducts'));
    }

    /** Mostrar historial de entregas (ruta pública) */
    public function index(Request $request)
    {
        $operations = SubArea::orderBy('operationName')->get();
        $query = Entrega::with(['operacion','usuario','elementos'])->orderBy('created_at', 'desc');
        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->whereHas('usuario', function($uq) use ($q){
                $uq->where('nombres', 'like', "%{$q}%")
                   ->orWhere('apellidos', 'like', "%{$q}%")
                   ->orWhere('numero_documento', 'like', "%{$q}%");
            });
        }
        if ($request->filled('operacion')) {
            $query->where('operacion_id', $request->input('operacion'));
        }

        $entregas = $query->paginate(15)->withQueryString();

        return view('formularioEntregas.HistorialEntregas', compact('entregas','operations'));
    }

    /** Procesar el envío del formulario, generar PDF y devolver descarga */
    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'apellidos' => 'nullable|string|max:255',
            'numberDocumento' => 'required|string',
            'elementos' => 'nullable|string',
            'firma' => 'nullable|string',
            'tipo' => 'nullable|string',
            'operacion_id' => 'nullable|integer',
            'tipo_documento' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            // ensure uploads dir
            if (!Storage::exists('public/entregas')) {
                Storage::makeDirectory('public/entregas');
            }

            // Find or create usuario
            $usuario = Usuarios::firstOrCreate([
                'numero_documento' => $data['numberDocumento'],
            ], [
                'nombres' => $data['nombre'] ?? null,
                'apellidos' => $data['apellidos'] ?? null,
                'tipo_documento' => $data['tipo_documento'] ?? null,
                'operacion_id' => $data['operacion_id'] ?? null,
            ]);

            // create entrega
            $entrega = Entrega::create([
                'rol_entrega' => 'web',
                'entrega_user' => optional(auth()->user())->id ?? null,
                'tipo_entrega' => $data['tipo'] ?? null,
                'usuarios_id' => $usuario->id,
                'operacion_id' => $data['operacion_id'] ?? null,
            ]);

            // save elementos if present
            $items = json_decode($data['elementos'] ?? '[]', true) ?: [];
            foreach ($items as $it) {
                if (empty($it['sku'])) continue;
                ElementoXEntrega::create([
                    'entrega_id' => $entrega->id,
                    'sku' => $it['sku'],
                    'cantidad' => $it['cantidad'] ?? 1,
                ]);
            }

            // generate PDF (same as before)
            $firmaBase64 = $data['firma'] ?? '';
            $pdf = Pdf::loadView('pdf', [
                'firmaBase64' => $firmaBase64,
                'nombre' => trim(($data['nombre'] ?? '') . ' ' . ($data['apellidos'] ?? '')),
                'documento' => $data['numberDocumento'] ?? '',
                'elementos' => $items,
            ]);

            $nombrePdf = 'entrega_' . time() . '_' . uniqid() . '.pdf';
            $rutaRelativa = 'public/entregas/' . $nombrePdf;
            Storage::put($rutaRelativa, $pdf->output());
            $fullPath = storage_path('app/' . $rutaRelativa);

            DB::commit();

            $pdfUrl = asset('storage/entregas/' . $nombrePdf);

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => true, 'file' => $pdfUrl, 'name' => $nombrePdf, 'message' => 'Entrega realizada'], 200);
            }

            // Para peticiones normales, redirigimos al historial con mensaje y enlace al PDF
            return redirect()->route('entregas.index')
                ->with('status', 'Entrega realizada')
                ->with('pdf', $pdfUrl);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error creando entrega y registros: ' . $e->getMessage(), ['request' => $request->all()]);
            return redirect()->back()->with('error', 'Ocurrió un error al procesar la entrega.');
        }
    }

    /**
     * API: Lista de productos permitidos según cargo y subárea.
     * Retorna [{sku, name_produc}] usando la tabla local cargo_productos.
     */
    public function cargoProductos(Request $request)
    {
        $cargoId = (int) $request->query('cargo_id');
        $subAreaId = (int) $request->query('sub_area_id');
        try {
            $query = DB::table('cargo_productos')->select(['sku', 'name_produc']);
            if ($cargoId) { $query->where('cargo_id', $cargoId); }
            if ($subAreaId) { $query->where('sub_area_id', $subAreaId); }
            $rows = $query->orderBy('name_produc')->get();
            $data = $rows->map(function ($r) {
                return [
                    'sku' => (string) ($r->sku ?? ''),
                    'name_produc' => (string) ($r->name_produc ?? ''),
                ];
            })->filter(fn($x) => !empty($x['sku']))->values();
            return response()->json($data, 200);
        } catch (\Throwable $e) {
            Log::warning('cargo_productos query failed', ['error' => $e->getMessage()]);
            return response()->json([], 200);
        }
    }
}