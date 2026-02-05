<?php

namespace App\Http\Controllers\gestiones;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Usuarios;
use App\Models\SubArea;
use App\Models\Area;
use App\Models\Producto;
use App\Models\ElementoXUsuario;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\UsuariosImport;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\NamedRange;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use App\Models\Cargo;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GestionUsuarioController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $operations = SubArea::orderBy('operationName')->get();
        $areas      = Area::orderBy('nombre_area')->get();
        $productos  = Producto::orderBy('name_produc')->get(['sku','name_produc']);

        if ($q !== '') {
            $usuarios = Usuarios::where('numero_documento', $q)
                ->orWhere('nombres', 'like', "%{$q}%")
                ->orWhere('apellidos', 'like', "%{$q}%")
                ->orderBy('id', 'desc')
                ->get();
        } else {
            $usuarios = Usuarios::orderBy('id', 'desc')->get();
        }

        return view('gestiones.gestionUsuarios', compact(
            'usuarios',
            'operations',
            'areas',
            'productos',
            'q'
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
        'nombres' => 'required|string|max:255',
        'apellidos'=> 'nullable|string|max:255',
        'tipo_documento'    => 'nullable|string|max:100',
        'numero_documento'  => 'required|string|max:100|unique:usuarios_entregas,numero_documento',
        'email'  => 'required|email|unique:usuarios_entregas,email',
        'fecha_ingreso'=> 'required|date',
        'operacion_id'=> 'required|exists:sub_areas,id',
        'area_id'=> 'required|exists:area,id',
]);

        // VALIDACIONES
        $maxRows = 200;

        // Calcular tamaños de listas
        $typesCount = max(1, count($types));
        $opsCount = max(1, count($operationsPairs));
        $areasCount = max(1, count($areasPairs));
        $cargosCount = max(1, count($cargosPairs));

        for ($row = 2; $row <= $maxRows; $row++) {

            // TIPO DOCUMENTO (C)
            $v = new DataValidation();
            $v->setType(DataValidation::TYPE_LIST);
            $v->setAllowBlank(true);
            $v->setFormula1('=Lists!$A$1:$A$' . $typesCount);
            $v->setShowDropDown(true);
            $sheet->getCell("C{$row}")->setDataValidation($v);

            // OPERACIÓN (G)
            $v = new DataValidation();
            $v->setType(DataValidation::TYPE_LIST);
            $v->setAllowBlank(true);
            $v->setFormula1('=Lists!$B$1:$B$' . $opsCount);
            $v->setShowDropDown(true);
            $sheet->getCell("G{$row}")->setDataValidation($v);

            // ÁREA (H)
            $v = new DataValidation();
            $v->setType(DataValidation::TYPE_LIST);
            $v->setAllowBlank(true);
            $v->setFormula1('=Lists!$D$1:$D$' . $areasCount);
            $v->setShowDropDown(true);
            $sheet->getCell("H{$row}")->setDataValidation($v);

            // CARGO (I)
            $v = new DataValidation();
            $v->setType(DataValidation::TYPE_LIST);
            $v->setAllowBlank(true);
            $v->setFormula1('=Lists!$F$1:$F$' . $cargosCount);
            $v->setShowDropDown(true);
            $sheet->getCell("I{$row}")->setDataValidation($v);

            // EMAIL VALIDACIÓN
            $v = new DataValidation();
            $v->setType(DataValidation::TYPE_CUSTOM);
            $v->setFormula1('=AND(LEN(E'.$row.')>3,ISNUMBER(SEARCH("@",E'.$row.')) )');
            $sheet->getCell("E{$row}")->setDataValidation($v);

            // FECHA VALIDACIÓN
            $v = new DataValidation();
            $v->setType(DataValidation::TYPE_CUSTOM);
            $v->setFormula1('=ISNUMBER(F'.$row.')');
            $sheet->getCell("F{$row}")->setDataValidation($v);

            // VLOOKUP IDs
            $sheet->setCellValue("J{$row}", '=IFERROR(VLOOKUP(G'.$row.',Lists!$B$1:$C$'.$opsCount.',2,FALSE),"")');
            $sheet->setCellValue("K{$row}", '=IFERROR(VLOOKUP(H'.$row.',Lists!$D$1:$E$'.$areasCount.',2,FALSE),"")');
            $sheet->setCellValue("L{$row}", '=IFERROR(VLOOKUP(I'.$row.',Lists!$F$1:$G$'.$cargosCount.',2,FALSE),"")');
        }
    }

    /**
     * Listar productos asignados a un usuario (para precargar en modal)
     */
    public function productosAsignados($id)
    {
        $usuario = Usuarios::findOrFail($id);
        $items = ElementoXUsuario::where('usuarios_entregas_id', $usuario->id)
            ->orderBy('id', 'desc')
            ->get(['id', 'sku', 'name_produc']);

        return response()->json([
            'ok' => true,
            'data' => $items,
        ]);
    }

    /**
     * Eliminar asignación de producto a usuario
     */
    public function eliminarProductoAsignado($asignacionId)
    {
        $registro = ElementoXUsuario::findOrFail($asignacionId);
        $registro->delete();
        return response()->json(['ok' => true]);
    }

    public function edit($id)
    {
        $editUsuario = Usuarios::findOrFail($id);
        $usuarios    = Usuarios::orderBy('id', 'desc')->get();
        $operations  = SubArea::orderBy('operationName')->get();
        $areas       = Area::orderBy('nombre_area')->get();
        $productos   = Producto::orderBy('name_produc')->get(['sku','name_produc']);

        return view('gestiones.gestionUsuarios', compact(
        'usuarios',
        'editUsuario',
        'operations',
        'areas',
        'productos'
    ));
    }

    /**
     * Mostrar un usuario (compatibilidad con resource routes).
     * Reusa la lógica de edit para abrir la vista con el usuario cargado.
     */
    public function show($id)
    {
        return $this->edit($id);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
        'nombres'           => 'required|string|max:255',
        'apellidos'         => 'nullable|string|max:255',
        'tipo_documento'    => 'nullable|string|max:100',
        'numero_documento'  => 'required|string|max:100|unique:usuarios_entregas,numero_documento,' . $id,
        'email'             => 'required|email|unique:usuarios_entregas,email,' . $id,
        'fecha_ingreso'     => 'required|date',
        'operacion_id'      => 'required|exists:sub_areas,id',
        'area_id'           => 'required|exists:area,id',
]);

        $usuario = Usuarios::findOrFail($id);
        $usuario->update([
            'nombres' => $request->input('nombres'),
            'apellidos' => $request->input('apellidos'),
            'tipo_documento' => $request->input('tipo_documento'),
            'numero_documento' => $request->input('numero_documento'),
            'email' => $request->input('email'),
            'fecha_ingreso' => $request->input('fecha_ingreso'),
            'operacion_id' => $request->input('operacion_id'),
            'area_id' => $request->input('area_id'),
        ]);

        return redirect()->route('gestionUsuario.index')->with('success', 'Usuario actualizado correctamente.');
    }

    public function destroy($id)
    {
        $usuario = Usuarios::findOrFail($id);
        $usuario->delete();
        return redirect()->route('gestionUsuario.index')->with('success', 'Usuario eliminado correctamente.');
    }

    /**
     * Importar usuarios desde Excel (xlsx/csv).
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => [
                'required',
                'file',
                'mimes:xlsx,xls,csv,ods',
                'mimetypes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,text/csv,application/csv,text/plain'
            ],
        ]);

        $file = $request->file('file');
        \Illuminate\Support\Facades\Log::info('Iniciando importación de usuarios', ['file' => $file->getClientOriginalName()]);
        $import = new UsuariosImport();

        try {
            Excel::import($import, $file);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error en importación usuarios', ['error' => $e->getMessage()]);
            return redirect()->route('gestionUsuario.index')
                ->with('success', 'Error durante la importación: ' . $e->getMessage());
        }

        $summary = $import->getSummary();
        \Illuminate\Support\Facades\Log::info('Resumen importación usuarios', $summary);

        $msg = "Importación finalizada. Creados: {$summary['created']}. Omitidos: {$summary['skipped']}. Errores: " . count($summary['errors']);

        return redirect()->route('gestionUsuario.index')
            ->with('success', $msg)
            ->with('import_errors', $summary['errors']);
    }

    /**
     * Descargar plantilla XLSX para importación de usuarios con validaciones.
     */
public function downloadTemplate()
{
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('PlantillaUsuarios');

    // ================= HEADERS =================
    $headers = [
        'Nombres', 'Apellidos', 'Tipo_documento', 'numero_documento',
        'Email', 'fecha_ingreso',
        'operacion', 'area', 'cargo'
    ];

    foreach ($headers as $col => $h) {
        $sheet->setCellValueByColumnAndRow($col + 1, 1, $h);
    }

    // Columnas ocultas para IDs
    $sheet->setCellValue('J1', 'Operación ID');
    $sheet->setCellValue('K1', 'Área ID');
    $sheet->setCellValue('L1', 'Cargo ID');

    // ================= FILA EJEMPLO =================
    $example = [
        'Juan', 'Pérez', 'Cédula de Ciudadanía', '12345678',
        'juan.perez@ejemplo.com', date('Y-m-d'),
        'Administración', 'Finanzas', 'Coordinador'
    ];

    foreach ($example as $col => $v) {
        $sheet->setCellValueByColumnAndRow($col + 1, 2, $v);
    }

    // ================= CREAR HOJA LISTS =================
    $lists = $spreadsheet->createSheet();
    $lists->setTitle('Lists');

    // TIPOS DOCUMENTO
    $types = ['Cédula de Ciudadanía', 'Cédula de Extranjería', 'Pasaporte'];
    foreach ($types as $r => $t) {
        $lists->setCellValueByColumnAndRow(1, $r + 1, $t);
    }

    // OPERACIONES
    $operationsPairs = SubArea::orderBy('operationName')->pluck('operationName', 'id')->toArray();
    $r = 1;
    foreach ($operationsPairs as $id => $opName) {
        $lists->setCellValue("B{$r}", $opName);
        $lists->setCellValue("C{$r}", $id);
        $r++;
    }

    // ÁREAS
    $areasPairs = Area::orderBy('nombre_area')->pluck('nombre_area', 'id')->toArray();
    $r = 1;
    foreach ($areasPairs as $id => $areaName) {
        $lists->setCellValue("D{$r}", $areaName);
        $lists->setCellValue("E{$r}", $id);
        $r++;
    }

    // CARGOS
    $cargosPairs = Cargo::orderBy('nombre')->pluck('nombre', 'id')->toArray();
    $r = 1;
    foreach ($cargosPairs as $id => $cargoName) {
        $lists->setCellValue("F{$r}", $cargoName);
        $lists->setCellValue("G{$r}", $id);
        $r++;
    }

    // ================= NAMED RANGES =================
    $spreadsheet->addNamedRange(new NamedRange('TipoDocs', $lists, '$A$1:$A$' . max(1, count($types))));
    $spreadsheet->addNamedRange(new NamedRange('OperacionesList', $lists, '$B$1:$B$' . max(1, count($operationsPairs))));
    $spreadsheet->addNamedRange(new NamedRange('AreasList', $lists, '$D$1:$D$' . max(1, count($areasPairs))));
    $spreadsheet->addNamedRange(new NamedRange('CargosList', $lists, '$F$1:$F$' . max(1, count($cargosPairs))));

    // ================= VALIDACIONES =================
    $maxRows = 300;

    for ($row = 2; $row <= $maxRows; $row++) {

        // ================= TIPO DOCUMENTO =================
    $v = new DataValidation();
    $v->setType(DataValidation::TYPE_LIST);
    $v->setAllowBlank(false);
    $v->setFormula1('=TipoDocs');
    $v->setShowDropDown(true);
    $v->setErrorStyle(DataValidation::STYLE_STOP);
    $v->setShowErrorMessage(true);
    $v->setErrorTitle('Dato inválido');
    $v->setError('Seleccione un tipo de documento de la lista.');
    $sheet->getCell("C{$row}")->setDataValidation(clone $v);

    // ================= DOCUMENTO SOLO NÚMEROS =================
    $v = new DataValidation();
    $v->setType(DataValidation::TYPE_CUSTOM);
    $v->setAllowBlank(false);
    $v->setFormula1("=AND(D{$row}<>\"\",ISNUMBER(D{$row}*1))");
    $v->setErrorStyle(DataValidation::STYLE_STOP);
    $v->setShowErrorMessage(true);
    $v->setErrorTitle('Documento inválido');
    $v->setError('Solo se permiten números. No letras.');
    $sheet->getCell("D{$row}")->setDataValidation(clone $v);

    // ================= EMAIL REAL =================
    $v = new DataValidation();
    $v->setType(DataValidation::TYPE_CUSTOM);
    $v->setAllowBlank(false);
    $v->setFormula1("=AND(E{$row}<>\"\",ISNUMBER(SEARCH(\"@\",E{$row})),ISNUMBER(SEARCH(\".\",E{$row})))");
    $v->setErrorStyle(DataValidation::STYLE_STOP);
    $v->setShowErrorMessage(true);
    $v->setErrorTitle('Correo inválido');
    $v->setError('Ingrese un correo válido. Ej: usuario@correo.com');
    $sheet->getCell("E{$row}")->setDataValidation(clone $v);

    // ================= FECHA REAL =================
    $v = new DataValidation();
    $v->setType(DataValidation::TYPE_CUSTOM);
    $v->setAllowBlank(false);
    $v->setFormula1("=AND(F{$row}<>\"\",ISNUMBER(F{$row}))");
    $v->setErrorStyle(DataValidation::STYLE_STOP);
    $v->setShowErrorMessage(true);
    $v->setErrorTitle('Fecha inválida');
    $v->setError('Debe ingresar una fecha válida. No texto.');
    $sheet->getCell("F{$row}")->setDataValidation(clone $v);

    // ================= OPERACIÓN =================
    $v = new DataValidation();
    $v->setType(DataValidation::TYPE_LIST);
    $v->setAllowBlank(false);
    $v->setFormula1('=OperacionesList');
    $v->setShowDropDown(true);
    $v->setErrorStyle(DataValidation::STYLE_STOP);
    $v->setShowErrorMessage(true);
    $v->setErrorTitle('Selección obligatoria');
    $v->setError('Debe seleccionar una operación.');
    $sheet->getCell("G{$row}")->setDataValidation(clone $v);

    // ================= ÁREA =================
    $v = new DataValidation();
    $v->setType(DataValidation::TYPE_LIST);
    $v->setAllowBlank(false);
    $v->setFormula1('=AreasList');
    $v->setShowDropDown(true);
    $v->setErrorStyle(DataValidation::STYLE_STOP);
    $v->setShowErrorMessage(true);
    $v->setErrorTitle('Selección obligatoria');
    $v->setError('Debe seleccionar un área.');
    $sheet->getCell("H{$row}")->setDataValidation(clone $v);

    // ================= CARGO =================
    $v = new DataValidation();
    $v->setType(DataValidation::TYPE_LIST);
    $v->setAllowBlank(false);
    $v->setFormula1('=CargosList');
    $v->setShowDropDown(true);
    $v->setErrorStyle(DataValidation::STYLE_STOP);
    $v->setShowErrorMessage(true);
    $v->setErrorTitle('Selección obligatoria');
    $v->setError('Debe seleccionar un cargo.');
    $sheet->getCell("I{$row}")->setDataValidation(clone $v);
}

        // ================= VLOOKUP IDs =================
        $sheet->setCellValue("J{$row}", '=IFERROR(VLOOKUP(G'.$row.',Lists!$B$1:$C$'.count($operationsPairs).',2,FALSE),"")');
        $sheet->setCellValue("K{$row}", '=IFERROR(VLOOKUP(H'.$row.',Lists!$D$1:$E$'.count($areasPairs).',2,FALSE),"")');
        $sheet->setCellValue("L{$row}", '=IFERROR(VLOOKUP(I'.$row.',Lists!$F$1:$G$'.count($cargosPairs).',2,FALSE),"")');

    // ================= FORMATO DE COLUMNAS =================
    $sheet->getStyle("D2:D{$maxRows}")
        ->getNumberFormat()
        ->setFormatCode(NumberFormat::FORMAT_NUMBER);

    $sheet->getStyle("F2:F{$maxRows}")
        ->getNumberFormat()
        ->setFormatCode(NumberFormat::FORMAT_DATE_YYYYMMDD);

    // ================= ESTILO =================
    $sheet->freezePane('A2');
    $sheet->getStyle('A1:L1')->getFont()->setBold(true);

    // ================= OCULTAR IDs =================
    $sheet->getColumnDimension('J')->setVisible(false);
    $sheet->getColumnDimension('K')->setVisible(false);
    $sheet->getColumnDimension('L')->setVisible(false);

    // ================= PROTEGER HOJA =================
    // ================= PERMITIR EDICIÓN EN COLUMNAS USUARIAS =================
$sheet->getStyle("A2:I{$maxRows}")
    ->getProtection()
    ->setLocked(false);

// ================= BLOQUEAR SOLO COLUMNAS DE IDS =================
$sheet->getStyle("J2:L{$maxRows}")
    ->getProtection()
    ->setLocked(true);

// ================= ACTIVAR PROTECCIÓN SOLO PARA IDS =================
$sheet->getProtection()->setSheet(true);
$sheet->getProtection()->setPassword('1234');
$lists->getProtection()->setSheet(true);
$lists->getProtection()->setPassword('1234');


    // ================= EXPORTAR =================
    $writer = new Xlsx($spreadsheet);
    $fileName = 'Plantilla_Usuarios.xlsx';

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header("Content-Disposition: attachment; filename=\"$fileName\"");
    $writer->save("php://output");
    exit;
}



    /**
     * Buscar usuario por número de documento (AJAX)
     */
    public function findByDocumento(Request $request)
    {
        $numero = $request->query('numero');
        \Illuminate\Support\Facades\Log::info('findByDocumento called', ['numero' => $numero]);
        if (!$numero) {
            \Illuminate\Support\Facades\Log::info('findByDocumento missing numero');
            return response()->json(['error' => 'missing_number'], 400);
        }

        $usuario = Usuarios::where('numero_documento', $numero)->first();
        \Illuminate\Support\Facades\Log::info('findByDocumento result', ['usuario' => $usuario ? $usuario->toArray() : null]);

        if (!$usuario) {
            return response()->json(null, 204);
        }

        return response()->json($usuario);
    }
}
