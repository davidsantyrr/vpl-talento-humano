<?php

namespace App\Http\Controllers\gestiones;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class gestionCorreosController extends Controller
{
    public function index()
    {
        // Roles disponibles para mapear vista
        $rolesDisponibles = \App\Models\Periodicidad::whereNotNull('rol_periodicidad')
            ->where('rol_periodicidad', '!=', '')
            ->distinct()
            ->pluck('rol_periodicidad')
            ->toArray();

        // Detectar rol del usuario y filtrar correos por ese rol
        $user = session('auth.user') ?? null;
        $selectedRol = $this->detectRoleForView($user, $rolesDisponibles);
        $correosQuery = \App\Models\Correos::query();
        if ($selectedRol) {
            $correosQuery->whereRaw('LOWER(rol) LIKE ?', ['%'.mb_strtolower($selectedRol).'%']);
        } else {
            // Fallback por palabras clave del rol en sesión (hseq vs talento humano)
            $vals = $this->collectRoleStrings($user);
            $hitHseq = false; $hitTalento = false;
            foreach ($vals as $v) {
                $nv = mb_strtolower($v);
                if (strpos($nv,'hseq')!==false || strpos($nv,'seguridad')!==false) $hitHseq = true;
                if (strpos($nv,'talento')!==false || strpos($nv,'humano')!==false || $nv==='th') $hitTalento = true;
            }
            if ($hitHseq)      { $correosQuery->whereRaw('LOWER(rol) LIKE ?', ['%hseq%']); }
            elseif ($hitTalento){ $correosQuery->whereRaw('LOWER(rol) LIKE ?', ['%talento%']); }
        }
        $correos = $correosQuery->paginate(10);

        // Cargar áreas para el select (si existen)
        $areas = [];
        try {
            // El modelo Area usa la columna 'nombre_area' en la tabla, mapearla a 'nombre' para la vista
            $areas = \App\Models\Area::select('id', 'nombre_area as nombre')->orderBy('nombre_area')->get();
        } catch (\Throwable $_) {
            // Si no existe el modelo/tabla, continuar sin bloquear la vista
            $areas = collect([]);
        }

        return view('gestiones.gestionCorreos', compact('correos', 'rolesDisponibles', 'selectedRol', 'areas'));
    }

    public function create()
    {
        // CRUD operations are handled from the index view (modal/inline).
        return redirect()->route('gestionCorreos.index');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'rol' => 'required|string|max:191',
            'correo' => 'required|email|max:191',
            'area' => 'nullable|string|max:191',
        ]);
        // Determinar rol permitido desde sesión y/o periodicidad
        $rolesDisponibles = \App\Models\Periodicidad::whereNotNull('rol_periodicidad')
            ->where('rol_periodicidad', '!=', '')
            ->distinct()
            ->pluck('rol_periodicidad')
            ->toArray();
        $user = session('auth.user') ?? null;
        $selectedRol = $this->detectRoleForView($user, $rolesDisponibles);
        if (!$selectedRol) {
            // Fallback: forzar HSEQ o Talento_Humano según sesión
            $vals = $this->collectRoleStrings($user);
            $norm = implode(' ', $vals);
            $norm = mb_strtolower($norm);
            if (strpos($norm,'hseq')!==false || strpos($norm,'seguridad')!==false) $selectedRol = 'HSEQ';
            elseif (strpos($norm,'talento')!==false || strpos($norm,'humano')!==false || strpos($norm,' th ')!==false) $selectedRol = 'Talento_Humano';
        }

        // Asegurar creación bajo el rol del usuario actual (ignorar rol enviado si difiere)
        $create = [
            'rol' => $selectedRol ?: $data['rol'],
            'correo' => $data['correo'],
            'area' => $data['area'] ?? null,
        ];

        \App\Models\Correos::create($create);

        return redirect()->route('gestionCorreos.index')->with('success', 'Correo agregado.');
    }

    /**
     * Lookup a domain user by email and return their role and a matched periodicidad role (if any).
     * Used by the create view via AJAX to auto-select the role for the email being added.
     */
    public function lookupUser(Request $request)
    {
        $email = $request->query('email');
        if (empty($email)) {
            return response()->json(['found' => false]);
        }

        $usuario = \App\Models\Usuarios::where('email', $email)->first();
        $cargoNombre = null;
        if ($usuario && $usuario->cargo) {
            $cargoNombre = $usuario->cargo->nombre;
        }

        // Obtener roles disponibles
        $rolesDisponibles = \App\Models\Periodicidad::whereNotNull('rol_periodicidad')
            ->where('rol_periodicidad', '!=', '')
            ->distinct()
            ->pluck('rol_periodicidad')
            ->toArray();

        $matched = null;
        if ($cargoNombre) {
            // Reusar lógica de detección (normalización simple)
            $s = $this->normalize($cargoNombre);
            foreach ($rolesDisponibles as $rol) {
                $r = $this->normalize($rol);
                if ($s === '' || $r === '') continue;
                if (strpos($s, $r) !== false || strpos($r, $s) !== false) {
                    $matched = $rol;
                    break;
                }
            }
        }

        return response()->json([
            'found' => (bool)$usuario,
            'usuario' => $usuario ? ['id' => $usuario->id, 'nombres' => $usuario->nombres, 'apellidos' => $usuario->apellidos, 'email' => $usuario->email] : null,
            'cargo' => $cargoNombre,
            'matched_role' => $matched,
        ]);
    }

    public function edit($id)
    {
        // Editing is handled in a modal within the index view. Redirect there.
        return redirect()->route('gestionCorreos.index');
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'rol' => 'required|string|max:191',
            'correo' => 'required|email|max:191',
            'area' => 'nullable|string|max:191',
        ]);
        $correo = \App\Models\Correos::findOrFail($id);
        // Verificar que el usuario sólo edite correos de su rol
        $rolesDisponibles = \App\Models\Periodicidad::whereNotNull('rol_periodicidad')
            ->where('rol_periodicidad', '!=', '')
            ->distinct()
            ->pluck('rol_periodicidad')
            ->toArray();
        $user = session('auth.user') ?? null;
        $selectedRol = $this->detectRoleForView($user, $rolesDisponibles);
        $allowed = $selectedRol ? mb_strtolower($selectedRol) : null;
        if (!$allowed) {
            $vals = $this->collectRoleStrings($user);
            $norm = implode(' ', $vals); $norm = mb_strtolower($norm);
            if (strpos($norm,'hseq')!==false || strpos($norm,'seguridad')!==false) $allowed = 'hseq';
            elseif (strpos($norm,'talento')!==false || strpos($norm,'humano')!==false || strpos($norm,' th ')!==false) $allowed = 'talento';
        }
        if ($allowed && strpos(mb_strtolower($correo->rol), $allowed) === false) {
            return redirect()->route('gestionCorreos.index')->with('error', 'No puede editar correos de otro rol');
        }

        $update = [
            // Mantener el rol del usuario, ignorando cambios hacia otro rol
            'rol' => $selectedRol ?: $correo->rol,
            'correo' => $data['correo'],
            'area' => $data['area'] ?? null,
        ];
        $correo->update($update);

        return redirect()->route('gestionCorreos.index')->with('success', 'Correo actualizado.');
    }

    public function destroy($id)
    {
        $correo = \App\Models\Correos::findOrFail($id);
        // Solo permitir borrar correos del propio rol
        $rolesDisponibles = \App\Models\Periodicidad::whereNotNull('rol_periodicidad')
            ->where('rol_periodicidad', '!=', '')
            ->distinct()
            ->pluck('rol_periodicidad')
            ->toArray();
        $user = session('auth.user') ?? null;
        $selectedRol = $this->detectRoleForView($user, $rolesDisponibles);
        $allowed = $selectedRol ? mb_strtolower($selectedRol) : null;
        if (!$allowed) {
            $vals = $this->collectRoleStrings($user);
            $norm = implode(' ', $vals); $norm = mb_strtolower($norm);
            if (strpos($norm,'hseq')!==false || strpos($norm,'seguridad')!==false) $allowed = 'hseq';
            elseif (strpos($norm,'talento')!==false || strpos($norm,'humano')!==false || strpos($norm,' th ')!==false) $allowed = 'talento';
        }
        if ($allowed && strpos(mb_strtolower($correo->rol), $allowed) === false) {
            return redirect()->route('gestionCorreos.index')->with('error', 'No puede eliminar correos de otro rol');
        }

        $correo->delete();

        return redirect()->route('gestionCorreos.index')->with('success', 'Correo eliminado.');
    }

    private function detectRoleForView($user, array $rolesDisponibles): ?string
    {
        if (empty($user) || empty($rolesDisponibles)) return null;

        $values = $this->collectRoleStrings($user);
        foreach ($values as $v) {
            foreach ($rolesDisponibles as $rol) {
                $s = mb_strtolower(trim($v));
                $r = mb_strtolower(trim($rol));
                if ($s === '' || $r === '') continue;
                if (strpos($s, $r) !== false || strpos($r, $s) !== false) {
                    return $rol;
                }
            }
        }

        // Si no se encontró una coincidencia, registrar para depuración
        Log::info('gestionCorreos: no se detectó rol coincidente para la vista', [
            'detected_role_strings' => $values,
            'rolesDisponibles' => $rolesDisponibles,
            'user_sample' => is_array($user) ? array_intersect_key($user, array_flip(['id','email','name'])) : $user,
        ]);

        return null;
    }

    private function collectRoleStrings($user): array
    {
        $out = [];
        $push = function($val) use (&$out) {
            if (is_string($val)) {
                $v = $this->normalize($val);
                if ($v) $out[] = $v;
            }
        };
        $candidates = ['role','rol','perfil','perfil_name','perfilNombre','role_name','nombre_rol','slug','key','codigo','tipo','tipo_rol','name','display_name','full_name','nombre','nombres','usuario'];
        if (is_array($user)) {
            foreach ($candidates as $k) if (isset($user[$k])) $push($user[$k]);
            if (isset($user['roles'])) {
                $r = $user['roles'];
                if (is_array($r)) foreach ($r as $item) {
                    if (is_string($item)) $push($item);
                    elseif (is_array($item)) foreach (['name','nombre','role','rol','roles','slug','key'] as $kk) if (isset($item[$kk])) $push($item[$kk]);
                    elseif (is_object($item)) foreach (['name','nombre','role','rol','roles','slug','key'] as $kk) if (isset($item->$kk)) $push($item->$kk);
                }
            }
        } elseif (is_object($user)) {
            foreach ($candidates as $k) if (isset($user->$k)) $push($user->$k);
            if (isset($user->roles) && is_array($user->roles)) foreach ($user->roles as $item) {
                if (is_string($item)) $push($item);
                elseif (is_object($item)) foreach (['name','nombre','role','rol','slug','key'] as $kk) if (isset($item->$kk)) $push($item->$kk);
            }
        }
        return array_values(array_filter(array_unique($out)));
    }

    private function normalize(?string $value): ?string
    {
        if ($value === null) return null;
        $v = trim(mb_strtolower($value));
        $v = str_replace(['_', '-'], ' ', $v);
        $v = preg_replace('/\s+/', ' ', $v);
        return $v;
    }
}