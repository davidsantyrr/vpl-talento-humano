<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use App\Models\Usuarios;
use App\Models\SubArea;
use App\Models\Area;
use App\Models\Cargo;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class UsuariosImport implements ToCollection, WithHeadingRow
{
    public $created = 0;
    public $skipped = 0;
    public $errors = [];

    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            // convertir fila a array simple
            $data = is_array($row) ? $row : $row->toArray();

            // logear las primeras filas crudas
            if ($index < 3) {
                \Illuminate\Support\Facades\Log::info('UsuariosImport fila ejemplo', ['index' => $index + 2, 'data' => $data]);
            }

            // detectar si la fila usa índices numéricos (sin encabezado)
            $hasNumeric = array_key_exists(0, $data) || array_key_exists(1, $data);

            $nombres = $apellidos = $tipo_documento = $numero_documento = $email = $fecha_ingreso = $operacion = $area = $cargo = '';

            if ($hasNumeric) {
                $nombres = trim((string)($data[0] ?? ''));
                $apellidos = trim((string)($data[1] ?? ''));
                $tipo_documento = trim((string)($data[2] ?? ''));
                $numero_documento = trim((string)($data[3] ?? ''));
                $email = trim((string)($data[4] ?? ''));
                $fecha_ingreso_raw = $data[5] ?? '';
                if (is_numeric($fecha_ingreso_raw) && $fecha_ingreso_raw) {
                    try {
                        $fecha_ingreso = ExcelDate::excelToDateTimeObject((float)$fecha_ingreso_raw)->format('Y-m-d');
                    } catch (\Exception $e) {
                        $fecha_ingreso = trim((string)$fecha_ingreso_raw);
                    }
                } else {
                    $fecha_ingreso = trim((string)$fecha_ingreso_raw);
                }
                $operacion = trim((string)($data[6] ?? ''));
                $area = trim((string)($data[7] ?? ''));
                $cargo = trim((string)($data[8] ?? ''));
            } else {
                // normalizar claves a formato simple
                $normalized = [];
                foreach ($data as $k => $v) {
                    $key = mb_strtolower(trim((string)$k));
                    if (class_exists('Normalizer')) {
                        $key = preg_replace('/[\p{Mn}]/u', '', \Normalizer::normalize($key, \Normalizer::FORM_KD));
                    } else {
                        $search = ['á','é','í','ó','ú','Á','É','Í','Ó','Ú','ñ','Ñ'];
                        $replace = ['a','e','i','o','u','a','e','i','o','u','n','n'];
                        $key = str_replace($search, $replace, $key);
                    }
                    $key = preg_replace('/[^a-z0-9]+/i', '_', $key);
                    $normalized[$key] = $v;
                }

                // aliases para los encabezados que se usan en distintos archivos
                $aliases = [
                    'nombres' => ['nombres','nombre','first_name'],
                    'apellidos' => ['apellidos','apellido','last_name'],
                    'tipo_documento' => ['tipo_de_documento','tipo_documento','tipo_documento'],
                    'numero_documento' => ['documento','numero_documento','n_documento','n_°_documento','n__documento'],
                    'email' => ['email','correo','correo_electronico'],
                    'fecha_ingreso' => ['fecha_ingreso','fecha_de_ingreso','fecha','ingreso_fecha'],
                    'operacion' => ['operacion','operaci_n','operation','operacion_id'],
                    'area' => ['area','area_id','nombre_area'],
                    'cargo' => ['cargo','cargo_id','nombre_cargo'],
                ];

                $get = function($keys) use ($normalized) {
                    foreach ($keys as $k) {
                        if (array_key_exists($k, $normalized) && $normalized[$k] !== null && $normalized[$k] !== '') {
                            return $normalized[$k];
                        }
                    }
                    return '';
                };

                $nombres = trim((string)$get($aliases['nombres']));
                $apellidos = trim((string)$get($aliases['apellidos']));
                $tipo_documento = trim((string)$get($aliases['tipo_documento']));
                $numero_documento = trim((string)$get($aliases['numero_documento']));
                $email = trim((string)$get($aliases['email']));
                $fecha_ingreso_raw = $get($aliases['fecha_ingreso']);
                if (is_numeric($fecha_ingreso_raw) && $fecha_ingreso_raw) {
                    try {
                        $fecha_ingreso = ExcelDate::excelToDateTimeObject((float)$fecha_ingreso_raw)->format('Y-m-d');
                    } catch (\Exception $e) {
                        $fecha_ingreso = trim((string)$fecha_ingreso_raw);
                    }
                } else {
                    $fecha_ingreso = '';
                    if ($fecha_ingreso_raw) {
                        $d = date_create($fecha_ingreso_raw);
                        if ($d) $fecha_ingreso = $d->format('Y-m-d');
                    }
                }
                $operacion = trim((string)$get($aliases['operacion']));
                $area = trim((string)$get($aliases['area']));
                $cargo = trim((string)$get($aliases['cargo']));

                if ($index < 3) {
                    \Illuminate\Support\Facades\Log::info('UsuariosImport mapping', ['index' => $index + 2, 'nombres' => $nombres, 'numero_documento' => $numero_documento, 'email' => $email, 'fecha_ingreso' => $fecha_ingreso]);
                }
            }

            // validar campos requeridos
            $missing = [];
            if (!$nombres) $missing[] = 'nombres';
            if (!$numero_documento) $missing[] = 'numero_documento';
            if (!$email) $missing[] = 'email';
            if (!$fecha_ingreso) $missing[] = 'fecha_ingreso';
            if (count($missing) > 0) {
                $this->skipped++;
                $this->errors[] = "Fila " . ($index + 2) . ": faltan datos requeridos (" . implode(', ', $missing) . ")";
                continue;
            }

            if (Usuarios::where('numero_documento', $numero_documento)->exists()) {
                $this->skipped++;
                $this->errors[] = "Fila " . ($index + 2) . ": numero_documento ya existe ({$numero_documento})";
                continue;
            }

            // resolver referencias
            $operacion_id = null;
            if (is_numeric($operacion) && $operacion) {
                $operacion_id = (int) $operacion;
                if (!SubArea::where('id', $operacion_id)->exists()) $operacion_id = null;
            } elseif ($operacion) {
                $op = SubArea::where('operationName', $operacion)->first();
                if ($op) $operacion_id = $op->id;
            }

            $area_id = null;
            if (is_numeric($area) && $area) {
                $area_id = (int) $area;
                if (!Area::where('id', $area_id)->exists()) $area_id = null;
            } elseif ($area) {
                $a = Area::where('nombre_area', $area)->first();
                if ($a) $area_id = $a->id;
            }

            $cargo_id = null;
            if (is_numeric($cargo) && $cargo) {
                $cargo_id = (int) $cargo;
                if (!Cargo::where('id', $cargo_id)->exists()) $cargo_id = null;
            } elseif ($cargo) {
                $c = Cargo::where('nombre', $cargo)->first();
                if ($c) $cargo_id = $c->id;
            }

            try {
                Usuarios::create([
                    'nombres' => $nombres,
                    'apellidos' => $apellidos,
                    'tipo_documento' => $tipo_documento,
                    'numero_documento' => $numero_documento,
                    'email' => $email,
                    'cargo_id' => $cargo_id,
                    'fecha_ingreso' => $fecha_ingreso,
                    'operacion_id' => $operacion_id,
                    'area_id' => $area_id,
                ]);
                $this->created++;
            } catch (\Exception $e) {
                $this->skipped++;
                $this->errors[] = "Fila " . ($index + 2) . ": error al crear - " . $e->getMessage();
            }
        }
    }

    public function getSummary(): array
    {
        return [
            'created' => $this->created,
            'skipped' => $this->skipped,
            'errors' => $this->errors,
        ];
    }
}
