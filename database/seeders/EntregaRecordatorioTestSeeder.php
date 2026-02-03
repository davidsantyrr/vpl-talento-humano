<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EntregaRecordatorioTestSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('mail.from.address') ?: 'test@example.com';

        $id = DB::table('entregas')->insertGetId([
            'rol_entrega' => 'Talento',
            'entrega_user' => 'Admin Test',
            'entrega_email' => $email,
            'tipo_entrega' => 'prestamo',
            'tipo_documento' => 'CC',
            'numero_documento' => '123',
            'nombres' => 'Juan',
            'apellidos' => 'Perez',
            'usuarios_id' => null,
            'sub_area_id' => null,
            'recepciones_id' => null,
            'recibido' => false,
            'comprobante_path' => null,
            'recordatorio_devolucion_at' => now()->toDateTimeString(),
            'recordatorio_devolucion_enviado' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('elemento_x_entrega')->insert([
            [
                'entrega_id' => $id,
                'sku' => 'SKU-TEST-1',
                'cantidad' => '2',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'entrega_id' => $id,
                'sku' => 'SKU-TEST-2',
                'cantidad' => '1',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->command->info('Entrega de prueba creada con ID: '.$id);
    }
}
