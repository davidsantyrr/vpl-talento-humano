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
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    });

    function escapeHtml(s){ 
        return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); 
    }

    // Abrir modal de entregas
    window.abrirModalEntregas = function(){
        if (!modalEntregas) return;
        modalEntregas.classList.add('active');
        if (buscarEntregaInput) buscarEntregaInput.value = '';
        if (entregasTbody) {
            entregasTbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 20px;">Ingrese un número de documento para buscar entregas</td></tr>';
        }
    };

    // Cerrar modal de entregas
    window.cerrarModalEntregas = function(){
        if (!modalEntregas) return;
        modalEntregas.classList.remove('active');
    };

    // Buscar entregas tipo préstamo
    window.buscarEntregasPrestamo = async function(){
        const numero = buscarEntregaInput ? buscarEntregaInput.value.trim() : '';
        if (!numero) {
            Toast.fire({
                icon: 'warning',
                title: 'Ingrese un número de documento'
            });
            return;
        }

        try {
            Toast.fire({
                icon: 'info',
                title: 'Buscando entregas...'
            });

            const url = `/entregas/buscar?numero=${encodeURIComponent(numero)}`;
            console.debug('fetching', url);
            const resp = await fetch(url);
            
            if (!resp.ok) throw new Error('Error en la búsqueda');
            
            const entregas = await resp.json();
            
            if (!Array.isArray(entregas) || entregas.length === 0) {
                if (entregasTbody) {
                    entregasTbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 20px;">No se encontraron entregas de préstamo para este documento</td></tr>';
                }
                Toast.fire({
                    icon: 'info',
                    title: 'No se encontraron entregas'
                });
                return;
            }

            // Renderizar entregas encontradas
            if (entregasTbody) {
                entregasTbody.innerHTML = entregas.map(e => {
                    const elementosTexto = e.elementos && e.elementos.length > 0
                        ? e.elementos.map(el => `${el.sku} (${el.cantidad})`).join(', ')
                        : 'Sin elementos';
                    
                    return `
                    <tr>
                        <td>${escapeHtml(new Date(e.fecha).toLocaleDateString())}</td>
                        <td>${escapeHtml(e.nombres)} ${escapeHtml(e.apellidos)}</td>
                        <td>${escapeHtml(e.numero_documento)}</td>
                        <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${escapeHtml(elementosTexto)}">${escapeHtml(elementosTexto)}</td>
                        <td>
                            <button type="button" class="btn btn-sm primary" onclick='seleccionarEntregaPrestamo(${JSON.stringify(e).replace(/'/g, "&apos;")})'>
                                Seleccionar
                            </button>
                        </td>
                    </tr>
                    `;
                }).join('');
            }

            Toast.fire({
                icon: 'success',
                title: `${entregas.length} entrega(s) encontrada(s)`
            });
        } catch (e) {
            console.error('Error buscando entregas:', e);
            Toast.fire({
                icon: 'error',
                title: 'Error al buscar entregas'
            });
        }
    };

    // Renderiza la tabla de items y asigna eventos para quitar
    function renderItemsTable(){
        if (!tableBody) return;
        tableBody.innerHTML = items.map((it, idx) =>
            `<tr data-idx="${idx}"><td>${escapeHtml(it.sku)} — ${escapeHtml(it.name)}</td><td style="text-align:center;">${it.cantidad}</td><td><button type="button" class="btn btn-sm btn-danger" data-idx="${idx}">Quitar</button></td></tr>`
        ).join('');
        Array.from(tableBody.querySelectorAll('button[data-idx]')).forEach(btn => {
            btn.addEventListener('click', () => {
                const i = Number(btn.dataset.idx);
                if (!Number.isNaN(i) && i >= 0) {
                    items.splice(i, 1);
                    if (itemsField) itemsField.value = JSON.stringify(items);
                    renderItemsTable();
                }
            });
        });
        if (itemsField) itemsField.value = JSON.stringify(items);
    }

    // Seleccionar entrega de préstamo
    window.seleccionarEntregaPrestamo = async function(entrega){
        if (!entrega || !entrega.elementos) return;

        const nombreInput = document.getElementById('nombresRecepcion');
        const apellidosInput = document.getElementById('apellidosRecepcion');
        const numeroInput = document.getElementById('numDocumento');
        const tipoDocumentoSelect = document.getElementById('tipoDocumento');
        const operacionSelect = document.getElementById('operacionRecepcion');

        // Rellenar datos del usuario
        if (nombreInput) nombreInput.value = entrega.nombres || '';
        if (apellidosInput) apellidosInput.value = entrega.apellidos || '';
        if (numeroInput) numeroInput.value = entrega.numero_documento || '';
        if (tipoDocumentoSelect) tipoDocumentoSelect.value = entrega.tipo_documento || 'CC';

        // Buscar y cargar datos completos del usuario (corrección de llaves)
        if (entrega.numero_documento) {
            try {
                const fetchUrl = `/usuarios/buscar?numero=${encodeURIComponent(entrega.numero_documento)}`;
                console.debug('fetching usuario', fetchUrl);
                const respUsuario = await fetch(fetchUrl);
                if (respUsuario.ok) {
                    const dataUsuario = await respUsuario.json();
                    if (operacionSelect && dataUsuario.operacion_id) {
                        operacionSelect.value = dataUsuario.operacion_id;
                        const opHidden = document.getElementById('operacionIdHidden');
                        if (opHidden) opHidden.value = dataUsuario.operacion_id;
                    }
                }
            } catch (err) {
                console.error('Error cargando datos del usuario:', err);
            }
        }

        // Guardar ID de entrega en campo hidden
        const entregaIdHidden = document.getElementById('entregaIdHidden');
        if (entregaIdHidden) {
            entregaIdHidden.value = entrega.id || '';
        }

        // Obtener nombres de productos
        try {
            const skus = entrega.elementos.map(e => e.sku);
            const url = `/productos/nombres`;
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || 
                             document.querySelector('input[name="_token"]')?.value || '';
            
            const resp = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({ skus })
            });

            let productosMap = {};
            if (resp.ok) {
                const ctype = (resp.headers.get('content-type') || '').toLowerCase();
                if (ctype.includes('application/json')) {
                    const data = await resp.json();
                    if (Array.isArray(data)) {
                        data.forEach(p => { if (p && p.sku) productosMap[p.sku] = p.name_produc || p.name || p.nombre || p.sku; });
                    }
                } else {
                    // server returned HTML or empty response: log for debugging and fallback
                    const text = await resp.text();
                    console.error('productos/nombres returned non-JSON response:', { status: resp.status, body: text });
                }
            } else {
                const text = await resp.text().catch(()=>'');
                console.error('productos/nombres request failed', { status: resp.status, body: text });
            }

            items = entrega.elementos.map(e => ({
                sku: e.sku,
                name: productosMap[e.sku] || e.sku,
                cantidad: Math.max(1, parseInt(e.cantidad) || 1)
            }));
        } catch (err) {
            console.error('Error obteniendo nombres de productos:', err);
            items = entrega.elementos.map(e => ({
                sku: e.sku,
                name: e.sku,
                cantidad: Math.max(1, parseInt(e.cantidad) || 1)
            }));
        }
        
        // Actualizar tabla y campo oculto sin reiniciar selección
        renderItemsTable();
        
        cerrarModalEntregas();
        
        // Habilitar botón de añadir elementos para edición
        if (addBtnTrigger) {
            addBtnTrigger.style.display = '';
        }
        if (btnSeleccionarEntrega) {
            btnSeleccionarEntrega.style.display = 'none';
        }
        
        Toast.fire({
            icon: 'success',
            title: 'Entrega cargada. Puede editar los elementos'
        });
    };

    // Vincular botón
    if (btnSeleccionarEntrega) {
        btnSeleccionarEntrega.addEventListener('click', function(){
            abrirModalEntregas();
        });
    }

    // Búsqueda al presionar Enter
    if (buscarEntregaInput) {
        buscarEntregaInput.addEventListener('keypress', function(e){
            if (e.key === 'Enter') {
                e.preventDefault();
                buscarEntregasPrestamo();
            }
        });
    }
})();