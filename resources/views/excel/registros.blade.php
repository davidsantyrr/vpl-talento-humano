<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Tipo de registro</th>
            <th>Tipo</th>
            <th>Fecha</th>
            <th>Numero documento</th>
            <th>Tipo documento</th>
            <th>Nombres</th>
            <th>Apellidos</th>
            <th>Operacion</th>
            <th>Recibido</th>
            <th>Comprobante path</th>
            <th>Elementos</th>
        </tr>
    </thead>
    <tbody>
        @foreach($rows as $r)
            <tr>
                <td>{{ $r['id'] }}</td>
                <td>{{ $r['registro_tipo'] }}</td>
                <td>{{ $r['tipo'] }}</td>
                <td>{{ $r['fecha'] }}</td>
                <td>{{ $r['numero_documento'] }}</td>
                <td>{{ $r['tipo_documento'] }}</td>
                <td>{{ $r['nombres'] }}</td>
                <td>{{ $r['apellidos'] }}</td>
                <td>{{ $r['operacion'] }}</td>
                <td>{{ $r['recibido'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
