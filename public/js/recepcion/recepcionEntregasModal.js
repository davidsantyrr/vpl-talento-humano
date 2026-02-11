(function(){
    const modalEntregas = document.getElementById('modalEntregas');
    const buscarEntregaInput = document.getElementById('buscarEntregaInput');
    const entregasTbody = document.getElementById('entregasTbody');
    const btnSeleccionarEntrega = document.getElementById('btnSeleccionarEntrega');
    const tableBody = document.querySelector('#itemsTable tbody');
    const itemsField = document.getElementById('itemsField');
    const addBtnTrigger = document.getElementById('addItemBtn');

    let items = [];

    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true
    });

    function escapeHtml(s){ 
        return String(s||'')
            .replace(/&/g,'&amp;')
            .replace(/</g,'&lt;')
            .replace(/>/g,'&gt;'); 
    }

    // Abrir modal
    window.abrirModalEntregas = function(){
        modalEntregas?.classList.add('active');
        if (buscarEntregaInput) buscarEntregaInput.value = '';
        if (entregasTbody) {
            entregasTbody.innerHTML = `
                <tr>
                    <td colspan="5" style="text-align:center;padding:20px;">
                        Ingrese un número de documento para buscar entregas
                    </td>
                </tr>`;
        }
    };

    // Cerrar modal
    window.cerrarModalEntregas = function(){
        modalEntregas?.classList.remove('active');
    };

    // ✅ BUSCAR ENTREGAS PRESTAMO (FIX REAL)
    window.buscarEntregasPrestamo = async function(){
        const numero = buscarEntregaInput?.value.trim();

        if (!numero) {
            Toast.fire({ icon: 'warning', title: 'Ingrese un número de documento' });
            return;
        }

        try {
            Toast.fire({ icon: 'info', title: 'Buscando entregas...' });

            const url = `/entregas/buscar?numero=${encodeURIComponent(numero)}`;

            console.log('FETCH ENTREGAS →', url);

            const resp = await fetch(url);

            if (!resp.ok) {
                const text = await resp.text();
                console.error('ERROR FETCH ENTREGAS:', resp.status, text);
                Toast.fire({ icon: 'error', title: 'Error al buscar entregas' });
                return;
            }

            const entregas = await resp.json();

            if (!Array.isArray(entregas) || entregas.length === 0) {
                entregasTbody.innerHTML = `
                    <tr>
                        <td colspan="5" style="text-align:center;padding:20px;">
                            No se encontraron entregas
                        </td>
                    </tr>`;
                Toast.fire({ icon: 'info', title: 'No hay entregas disponibles' });
                return;
            }

            entregasTbody.innerHTML = entregas.map(e => {
                const elementosTexto = (e.elementos || [])
                    .map(el => `${el.sku} (${el.cantidad})`)
                    .join(', ') || 'Sin elementos';

                return `
                <tr>
                    <td>${escapeHtml(new Date(e.fecha).toLocaleDateString())}</td>
                    <td>${escapeHtml(e.nombres)} ${escapeHtml(e.apellidos)}</td>
                    <td>${escapeHtml(e.numero_documento)}</td>
                    <td title="${escapeHtml(elementosTexto)}">${escapeHtml(elementosTexto)}</td>
                    <td>
                        <button class="btn btn-sm primary"
                            onclick='seleccionarEntregaPrestamo(${JSON.stringify(e).replace(/'/g, "&apos;")})'>
                            Seleccionar
                        </button>
                    </td>
                </tr>`;
            }).join('');

            Toast.fire({ icon: 'success', title: `${entregas.length} entregas encontradas` });

        } catch (err) {
            console.error('ERROR GENERAL FETCH:', err);
            Toast.fire({ icon: 'error', title: 'Error inesperado al buscar entregas' });
        }
    };

    // Render tabla items
    function renderItemsTable(){
        if (!tableBody) return;

        tableBody.innerHTML = items.map((it, idx) => `
            <tr data-idx="${idx}">
                <td>${escapeHtml(it.sku)} — ${escapeHtml(it.name)}</td>
                <td style="text-align:center;">${it.cantidad}</td>
                <td>
                    <button class="btn btn-sm btn-danger" data-idx="${idx}">
                        Quitar
                    </button>
                </td>
            </tr>
        `).join('');

        tableBody.querySelectorAll('button[data-idx]').forEach(btn => {
            btn.addEventListener('click', () => {
                const i = Number(btn.dataset.idx);
                items.splice(i, 1);
                itemsField.value = JSON.stringify(items);
                renderItemsTable();
            });
        });

        itemsField.value = JSON.stringify(items);
    }

    // Seleccionar entrega
    window.seleccionarEntregaPrestamo = async function(entrega){
        if (!entrega?.elementos) return;

        document.getElementById('nombresRecepcion').value = entrega.nombres || '';
        document.getElementById('apellidosRecepcion').value = entrega.apellidos || '';
        document.getElementById('numDocumento').value = entrega.numero_documento || '';
        document.getElementById('tipoDocumento').value = entrega.tipo_documento || 'CC';

        const entregaIdHidden = document.getElementById('entregaIdHidden');
        if (entregaIdHidden) entregaIdHidden.value = entrega.id || '';

        items = entrega.elementos.map(e => ({
            sku: e.sku,
            name: e.sku,
            cantidad: Math.max(1, parseInt(e.cantidad) || 1)
        }));

        renderItemsTable();
        cerrarModalEntregas();

        addBtnTrigger.style.display = '';
        btnSeleccionarEntrega.style.display = 'none';

        Toast.fire({ icon: 'success', title: 'Entrega cargada correctamente' });
    };

    // Eventos UI
    btnSeleccionarEntrega?.addEventListener('click', abrirModalEntregas);

    buscarEntregaInput?.addEventListener('keypress', e => {
        if (e.key === 'Enter') {
            e.preventDefault();
            buscarEntregasPrestamo();
        }
    });

})();
