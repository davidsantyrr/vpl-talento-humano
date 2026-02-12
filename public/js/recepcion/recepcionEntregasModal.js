(function(){

const modal = document.getElementById('modalEntregas');
const buscarInput = document.getElementById('buscarEntregaInput');
const tbody = document.getElementById('entregasTbody');
const itemsField = document.getElementById('itemsField');
const btnSeleccionarEntrega = document.getElementById('btnSeleccionarEntrega');

let items = [];

const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000
});

function escapeHtml(text){
    return String(text || '')
        .replace(/&/g,'&amp;')
        .replace(/</g,'&lt;')
        .replace(/>/g,'&gt;');
}

window.abrirModalEntregas = function(){
    modal.classList.add('active');
    tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;">Ingrese documento</td></tr>`;
};

window.cerrarModalEntregas = function(){
    modal.classList.remove('active');
};

window.buscarEntregasPrestamo = async function(){
    const numero = buscarInput.value.trim();

    if (!numero) {
        Toast.fire({ icon: 'warning', title: 'Ingrese documento' });
        return;
    }

    try {
        Toast.fire({ icon: 'info', title: 'Buscando...' });

        const apiBase = (window.API_BASE || '').replace(/\/$/, '');
        // Priorizar rutas /api/ para evitar conflicto con carpeta public/entregas/
        const bases = [
            window.RUTA_ENTREGAS_BUSCAR,
            '/api/entregas/recepcion/buscar',
            '/api/entregas/buscar',
            '/entregas/recepcion/buscar',
            '/entregas/buscar'
        ].filter(Boolean);

        let entregas = null;
        let lastStatus = null;

        for (const base of bases) {
            const full = base.startsWith('/') ? `${apiBase}${base}` : base;
            const url = `${full}?numero=${encodeURIComponent(numero)}`;
            console.log('FETCH →', url);
            const resp = await fetch(url, { headers: { 'Accept': 'application/json' } });
            if (!resp.ok) {
                lastStatus = resp.status;
                console.warn('Intento fallido:', base, resp.status);
                continue;
            }
            try {
                const data = await resp.json();
                entregas = Array.isArray(data) ? data : [];
                break;
            } catch (jsonErr) {
                lastStatus = 'json';
                console.warn('Respuesta no JSON en:', base, jsonErr);
                continue;
            }
        }

        if (!entregas) {
            tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;">Error consultando entregas (${lastStatus ?? 'error'})</td></tr>`;
            Toast.fire({ icon: 'error', title: 'No se pudo consultar entregas' });
            return;
        }

        if (entregas.length === 0) {
            tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;">No hay entregas</td></tr>`;
            return;
        }

        tbody.innerHTML = entregas.map(e => `
            <tr>
                <td>${escapeHtml(new Date(e.fecha).toLocaleDateString())}</td>
                <td>${escapeHtml(e.nombres)} ${escapeHtml(e.apellidos)}</td>
                <td>${escapeHtml(e.numero_documento)}</td>
                <td>${(e.elementos || []).map(el => `${el.sku} (${el.cantidad})`).join(', ')}</td>
                <td>
                    <button class="btn btn-sm btn-primary" onclick='seleccionarEntregaPrestamo(${JSON.stringify(e)})'>
                        Seleccionar
                    </button>
                </td>
            </tr>
        `).join('');

    } catch (err) {
        console.error(err);
        tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;">Error buscando entregas</td></tr>`;
        Toast.fire({ icon: 'error', title: 'Error buscando entregas' });
    }
};

window.seleccionarEntregaPrestamo = function(entrega){

    document.getElementById('nombresRecepcion').value = entrega.nombres || '';
    document.getElementById('apellidosRecepcion').value = entrega.apellidos || '';
    document.getElementById('numDocumento').value = entrega.numero_documento || '';
    document.getElementById('tipoDocumento').value = entrega.tipo_documento || 'CC';
    document.getElementById('entregaIdHidden').value = entrega.id;

    items = (entrega.elementos || []).map(e => ({
        sku: e.sku,
        name: e.name,
        cantidad: parseInt(e.cantidad) || 1
    }));

    itemsField.value = JSON.stringify(items);

    cerrarModalEntregas();

    Toast.fire({ icon: 'success', title: 'Entrega cargada' });
};

// Abrir modal desde el botón "Seleccionar entrega"
btnSeleccionarEntrega && btnSeleccionarEntrega.addEventListener('click', function(){
    abrirModalEntregas();
});

})();
