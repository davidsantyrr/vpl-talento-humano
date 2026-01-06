// En la sección donde se procesa la recepción de tipo cambio/baja/prestamo
// Buscar donde se usa $usuario y asegurarse de que esté definido

// Después de obtener $usuarioId
if ($usuarioId) {
    Log::info('Usuario encontrado en BD', ['usuario_id' => $usuarioId]);
    
    // Buscar el usuario completo para usar sus datos
    $usuario = Usuario::find($usuarioId);
    
    if (!$usuario) {
        // Si no existe, crear con datos manuales
        $usuario = (object)[
            'nombres' => $request->nombres ?? '',
            'apellidos' => $request->apellidos ?? '',
            'tipo_documento' => $request->tipo_doc ?? '',
            'numero_documento' => $request->num_doc ?? '',
            'operacion_id' => $request->operation_id ?? null
        ];
    }
} else {
    Log::info('Usuario no encontrado, guardando datos manuales', ['numero_documento' => $request->num_doc]);
    
    // Crear objeto usuario con datos manuales
    $usuario = (object)[
        'nombres' => $request->nombres ?? '',
        'apellidos' => $request->apellidos ?? '',
        'tipo_documento' => $request->tipo_doc ?? '',
        'numero_documento' => $request->num_doc ?? '',
        'operacion_id' => $request->operation_id ?? null
    ];
}
