document.addEventListener('DOMContentLoaded', function () {
    // Si el modo AJAX está activo, no añadir listeners de submit desde este archivo
    if (window.__TH_AJAX_SUBMIT__ === true) {
        // continuar con lógicas de UI pero sin enganchar submit
    }

    const tipoSelect = document.getElementById('tipoSelect');
    const operacionSelect = document.getElementById('operacionSelect');
    const operacionHidden = document.getElementById('operacionIdHidden');
    const fieldOperacion = document.getElementById('field-operacion');
    const fieldCargo = document.getElementById('field-cargo');
    const cargoSelect = document.getElementById('cargoSelect');
    const cargoHidden = document.getElementById('cargoIdHidden');
    const lookupBox = document.getElementById('usuarioLookup');
    const elementoSelect = document.getElementById('elementoSelect');
    const btnAnadirElemento = document.getElementById('btnAnadirElemento');
    const btnSeleccionarRecepcion = document.getElementById('btnSeleccionarRecepcion');

    // El select de elementos se poblará desde cargo_productos SIN filtros
    if (elementoSelect) {
        elementoSelect.innerHTML = '<option value="">Seleccione un producto</option>';
    }

    if (!tipoSelect || !operacionSelect) return;

    function normalize(val) { return String(val || '').trim().toLowerCase(); }

    // recordar última operación seleccionada y operación asociada al usuario
    let lastOperacionValue = operacionSelect ? operacionSelect.value || '' : '';
    let usuarioOperacionId = null;

    function setCargoHidden(val){ if(cargoHidden) cargoHidden.value = val || ''; }

    // Sincronizar cambios del select visible de cargo al hidden y actualizar productos
    if (cargoSelect) {
        cargoSelect.addEventListener('change', function(){
            setCargoHidden(cargoSelect.value || '');
            updateElementoOptions();
        });
    }

    function updateOperacionState() {
        const tipo = normalize(tipoSelect.value);
        const showCargo = (tipo === 'primera vez' || tipo === 'periodica');
        const isCambio = (tipo === 'cambio');
        const isEditableOperacion = (tipo === 'prestamo');

        // Mostrar/ocultar botones según el tipo
        if (btnAnadirElemento) {
            btnAnadirElemento.style.display = isCambio ? 'none' : '';
        }
        if (btnSeleccionarRecepcion) {
            btnSeleccionarRecepcion.style.display = isCambio ? '' : 'none';
        }

        // Mostrar campo cargo solo para primera vez y periódica (no para cambio)
        if (fieldCargo && fieldOperacion) {
            fieldCargo.style.display = showCargo ? '' : 'none';
            fieldOperacion.style.display = '';
        }

        // Operación: solo editable si tipo === 'prestamo'
        if (operacionSelect) {
            operacionSelect.disabled = !isEditableOperacion;
            if (operacionSelect.disabled) operacionSelect.classList.add('disabled'); else operacionSelect.classList.remove('disabled');
        }

        // Si no es editable y tenemos una operación de usuario o historial, mostrarla en el select (aunque esté deshabilitado)
        if (operacionSelect && !isEditableOperacion) {
            const prefer = usuarioOperacionId || lastOperacionValue || operacionSelect.value || '';
            if (prefer) operacionSelect.value = prefer;
        }

        // Mantener siempre el hidden sincronizado para envío (fallbacks: select.value -> usuarioOperacionId -> lastOperacionValue)
        if (operacionHidden) {
            operacionHidden.value = (operacionSelect && operacionSelect.value) ? String(operacionSelect.value) : (usuarioOperacionId ? String(usuarioOperacionId) : String(lastOperacionValue || ''));
        }

        // si se muestra Cargo, intentar autoseleccionar con el dato del usuario
        if (showCargo && lookupBox) {
            const cargoId = lookupBox.dataset && lookupBox.dataset.cargoId ? String(lookupBox.dataset.cargoId) : '';
            if (cargoId && cargoSelect) {
                const opt = cargoSelect.querySelector(`option[value="${cargoId}"]`);
                if (opt) { cargoSelect.value = cargoId; setCargoHidden(cargoId); }
            } else { setCargoHidden(''); }
        } else { setCargoHidden(''); }
    }

    // eventos
    tipoSelect.addEventListener('change', function(){
        updateOperacionState();
        // actualizar hidden inmediatamente
        if (operacionHidden) operacionHidden.value = (operacionSelect && operacionSelect.value) ? String(operacionSelect.value) : (usuarioOperacionId ? String(usuarioOperacionId) : String(lastOperacionValue || ''));
    });

    // cuando el usuario cambia manualmente la operación, actualizar el valor recordado y el hidden
    if (operacionSelect) {
        operacionSelect.addEventListener('change', function () {
            lastOperacionValue = operacionSelect.value || lastOperacionValue;
            if (operacionHidden) operacionHidden.value = operacionSelect.value || '';
        });
    }

    function buscarUsuario(numero) {
        if (!numero) {
            showLookupMessage('');
            usuarioOperacionId = null;
            return;
        }

        showLookupMessage('Buscando usuario...');

        const fetchUrl = `${window.location.origin}/usuarios/buscar?numero=${encodeURIComponent(numero)}`;
        // debug
        console.debug('buscarUsuario fetch', fetchUrl);
        fetch(fetchUrl)
            .then(resp => {
                if (resp.status === 204) return null;
                if (!resp.ok) throw new Error('fetch_error');
                return resp.json();
            })
            .then(data => {
                if (!data) {
                    // limpiar dataset de cargo si no hay usuario
                    if (lookupBox) lookupBox.dataset.cargoId = '';
                    if (cargoHidden) cargoHidden.value = '';
                    const crearUrl = lookupBox?.dataset?.crearUrl || '/gestionUsuario';
                    showLookupMessage(`Usuario no encontrado. <a href="${crearUrl}">Crear usuario</a>`);
                    if (nombreInput) nombreInput.value = '';
                    if (apellidosInput) apellidosInput.value = '';
                    if (tipoDocumentoSelect) tipoDocumentoSelect.value = '';
                    usuarioOperacionId = null;
                    // asegurar hidden limpio
                    if (operacionHidden) operacionHidden.value = lastOperacionValue || '';
                    updateOperacionState();
                    // limpiar productos
                    updateElementoOptions();
                    return;
                }

                showLookupMessage(`Usuario encontrado: ${data.nombres} ${data.apellidos || ''}`);

                if (nombreInput) nombreInput.value = data.nombres || '';
                if (apellidosInput) apellidosInput.value = data.apellidos || '';
                if (tipoDocumentoSelect && data.tipo_documento) tipoDocumentoSelect.value = data.tipo_documento;

                if (operacionSelect && data.operacion_id) {
                    usuarioOperacionId = String(data.operacion_id);
                    operacionSelect.value = data.operacion_id;
                    lastOperacionValue = String(data.operacion_id);
                }

                // NUEVO: exponer cargo_id para otros scripts
                if (lookupBox) lookupBox.dataset.cargoId = data.cargo_id ? String(data.cargo_id) : '';
                if (cargoHidden) cargoHidden.value = data.cargo_id ? String(data.cargo_id) : '';
                // SINCRONIZAR operacion hidden
                if (operacionHidden) operacionHidden.value = (operacionSelect && operacionSelect.value) ? String(operacionSelect.value) : (usuarioOperacionId || lastOperacionValue || '');

                try {
                    document.dispatchEvent(new CustomEvent('usuario:cargado', { detail: data }));
                } catch (e) { /* ignore */ }

                // actualizar el estado (habilita solo si tipo === 'prestamo')
                updateOperacionState();
                // refrescar productos tras cargar usuario
                updateElementoOptions();
            })
            .catch(() => {
                showLookupMessage('Error al buscar usuario', true);
            });
    }

    // Hook cuando se cargan datos por documento
    document.addEventListener('usuario:cargado', function(e){
        const data = e && e.detail ? e.detail : null;
        if (data && typeof data.operacion_id !== 'undefined' && data.operacion_id) {
            usuarioOperacionId = String(data.operacion_id);
            lastOperacionValue = String(data.operacion_id) || lastOperacionValue;
        }
        if (cargoHidden && typeof data?.cargo_id !== 'undefined') {
            cargoHidden.value = data.cargo_id ? String(data.cargo_id) : '';
        }
        // sincronizar hidden
        if (operacionHidden) operacionHidden.value = (operacionSelect && operacionSelect.value) ? String(operacionSelect.value) : (usuarioOperacionId || lastOperacionValue || '');
        updateOperacionState();
        updateElementoOptions();
    });

    // inicializar estado al cargar la página
    updateOperacionState();
    
    // --- Buscar usuario por número de documento y rellenar campos ---
    const numeroInput = document.getElementById('numberDocumento');
    const nombreInput = document.getElementById('nombre');
    const apellidosInput = document.getElementById('apellidos');
    const tipoDocumentoSelect = document.querySelector('select[name="tipo_documento"]');

    // debounce helper
    function debounce(fn, wait) {
        let t;
        return function (...args) {
            clearTimeout(t);
            t = setTimeout(() => fn.apply(this, args), wait);
        };
    }

    function showLookupMessage(html, danger = false) {
        if (!lookupBox) return;
        lookupBox.innerHTML = html;
        lookupBox.style.color = danger ? 'darkred' : '';
    }

    if (numeroInput) {
        const debouncedBuscar = debounce(function () {
            const val = numeroInput.value && numeroInput.value.trim();
            if (!val) {
                showLookupMessage('');
                return;
            }
            buscarUsuario(val);
        }, 400);

        numeroInput.addEventListener('input', debouncedBuscar);
        numeroInput.addEventListener('blur', function () {
            const val = numeroInput.value && numeroInput.value.trim();
            if (val) buscarUsuario(val);
        });
    }

    // cuando el usuario cambia manualmente la operación, actualizar el valor recordado y el hidden
    if (operacionSelect) {
        operacionSelect.addEventListener('change', function () {
            lastOperacionValue = operacionSelect.value || lastOperacionValue;
            if (operacionHidden) operacionHidden.value = operacionSelect.value || '';
        });
    }

    // ------------------ Selector de productos para modal de elementos ------------------
    const modal = document.getElementById('modalElementos');
    const cantidadInput = document.getElementById('cantidadInput');
    const elementosTbody = document.getElementById('elementosTbody');
    const elementosFormTbody = document.getElementById('elementosFormTbody');
    const elementosJson = document.getElementById('elementosJson');

    let elementoSeleccionado = null;
    let elementos = [];
    let tempElementos = [];

    // Toast helper para alertas
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

    function escapeHtml(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

    function renderModalTable(){ 
        if (!elementosTbody) return; 
        elementosTbody.innerHTML = tempElementos.map(it => `<tr><td>${escapeHtml(it.sku)} — ${escapeHtml(it.name)}</td><td style="text-align:center;">${it.cantidad}</td></tr>`).join(''); 
    }
    
    function renderFormTable(){ 
        if (!elementosFormTbody) return; 
        elementosFormTbody.innerHTML = elementos.map((it, idx) => `<tr data-idx="${idx}"><td>${escapeHtml(it.sku)} — ${escapeHtml(it.name)}</td><td style="text-align:center;">${it.cantidad}</td><td><button type="button" class="btn btn-sm btn-danger" data-idx="${idx}">Quitar</button></td></tr>`).join(''); 
        Array.from(elementosFormTbody.querySelectorAll('button[data-idx]')).forEach(btn=>btn.addEventListener('click', (e)=>{ const i = Number(btn.dataset.idx); elementos.splice(i,1); syncFormTable(); })); 
    }

    function syncFormTable(){ 
        if (elementosJson) elementosJson.value = JSON.stringify(elementos); 
        renderFormTable(); 
    }

    // abrir/cerrar modal y eventos
    window.abrirModal = function(){
        if (!modal) return;
        tempElementos = elementos.slice();
        modal.classList.add('active');
        elementoSeleccionado = null;
        
        // Limpiar dropdown previo si existe
        const prevDD = document.getElementById('modal-prod-dd');
        if (prevDD) prevDD.remove();
        
        if (elementoSelect) {
            elementoSelect.value = '';
        }
        if (cantidadInput) cantidadInput.value = '1';
        
        // Cargar productos y configurar dropdown
        updateElementoOptions().then(() => {
            setupDropdown();
            renderModalTable();
        });
    }
    
    window.cerrarModal = function(){ 
        if (!modal) return; 
        tempElementos = [];
        modal.classList.remove('active');
        
        // Limpiar dropdown al cerrar
        const dd = document.getElementById('modal-prod-dd');
        if (dd) dd.remove();
        
        // Limpiar input de búsqueda y restaurar select
        const searchInput = document.getElementById('modal-search-input');
        if (searchInput) searchInput.remove();
        if (elementoSelect) elementoSelect.style.display = '';
    }

    window.guardarModal = function(){
        if (!modal) return;
        if (tempElementos.length === 0) {
            Toast.fire({
                icon: 'warning',
                title: 'Agregue al menos un producto a la lista'
            });
            return;
        }
        elementos = tempElementos.slice();
        // guardar también la preferencia de enviar a correos de gestión
        try {
            const chk = document.getElementById('chkEnviarGestionCorreos');
            const hidden = document.getElementById('enviarGestionCorreos');
            if (hidden) hidden.value = (chk && chk.checked) ? '1' : '0';
        } catch (e) {}

        syncFormTable();
        tempElementos = [];
        modal.classList.remove('active');
        Toast.fire({
            icon: 'success',
            title: 'Elementos agregados correctamente'
        });
    }

    window.agregarElementoModal = function(){
        let sel = null;
        if (elementoSelect && elementoSelect.value) {
            const sku = elementoSelect.value;
            const opt = elementoSelect.querySelector(`option[value="${sku}"]`);
            const name = opt ? (opt.dataset.name_produc || opt.textContent.split(' — ')[1] || opt.textContent) : '';
            sel = { sku, name };
        }
        if (!sel) { 
            Toast.fire({
                icon: 'error',
                title: 'Seleccione un elemento válido'
            });
            return; 
        }
        const qty = parseInt(cantidadInput && cantidadInput.value ? cantidadInput.value : '0',10);
        if (!qty || qty < 1) { 
            Toast.fire({
                icon: 'error',
                title: 'Ingrese una cantidad válida'
            });
            return; 
        }
        tempElementos.push({ sku: sel.sku, name: sel.name, cantidad: qty });
        elementoSelect.value = '';
        cantidadInput.value = '1';
        
        // Limpiar el input de búsqueda si existe
        const searchInput = document.getElementById('modal-search-input');
        if (searchInput) {
            searchInput.value = '';
        }
        
        renderModalTable();
        Toast.fire({
            icon: 'success',
            title: 'Producto agregado a la lista'
        });
    }

    // Setup del dropdown de búsqueda para el modal
    function setupDropdown() {
        if (!elementoSelect) return;
        
        // Verificar si ya existe un input de búsqueda
        let searchInput = document.getElementById('modal-search-input');
        if (searchInput) {
            // Si ya existe, solo actualizar opciones y focus
            searchInput.value = '';
            searchInput.focus();
            return;
        }
        
        // Convertir select en input de búsqueda
        const wrapper = elementoSelect.parentElement;
        searchInput = document.createElement('input');
        searchInput.type = 'text';
        searchInput.className = elementoSelect.className;
        searchInput.placeholder = 'Escribe para buscar SKU o nombre';
        searchInput.id = 'modal-search-input';
        
        elementoSelect.style.display = 'none';
        wrapper.insertBefore(searchInput, elementoSelect);
        
        const dd = document.createElement('ul');
        dd.id = 'modal-prod-dd';
        dd.className = 'modal-list';
        dd.hidden = true;
        document.body.appendChild(dd);
        
        let allOptions = Array.from(elementoSelect.options)
            .filter(opt => opt.value)
            .map(opt => ({
                sku: opt.value,
                name: opt.dataset.name_produc || opt.textContent.split(' — ')[1] || opt.textContent
            }));
        
        function updateDDPos(){
            const r = searchInput.getBoundingClientRect();
            const w = Math.min(r.width, 420);
            dd.style.left = r.left + 'px';
            dd.style.top = (r.bottom + 6) + 'px';
            dd.style.width = w + 'px';
        }
        
        function renderDD(list){
            dd.innerHTML = '';
            list.slice(0, 200).forEach(p => {
                const li = document.createElement('li');
                li.className = 'modal-list-item';
                li.textContent = `${p.sku} — ${p.name}`;
                li.addEventListener('click', () => {
                    elementoSelect.value = p.sku;
                    searchInput.value = `${p.sku} — ${p.name}`;
                    dd.hidden = true;
                });
                dd.appendChild(li);
            });
            dd.hidden = list.length === 0;
            if (!dd.hidden) updateDDPos();
        }
        
        function filter(term){
            const t = term.trim().toLowerCase();
            if (!t) return allOptions.slice();
            return allOptions.filter(p => 
                p.sku.toLowerCase().includes(t) || 
                p.name.toLowerCase().includes(t)
            );
        }
        
        searchInput.addEventListener('input', () => {
            elementoSelect.value = '';
            renderDD(filter(searchInput.value));
        });
        
        searchInput.addEventListener('focus', () => {
            elementoSelect.value = '';
            renderDD(allOptions.slice());
        });
        
        searchInput.addEventListener('click', () => {
            renderDD(filter(searchInput.value));
        });
        
        window.addEventListener('resize', updateDDPos);
        document.addEventListener('scroll', updateDDPos, true);
        document.addEventListener('click', (e) => {
            if (!dd.contains(e.target) && e.target !== searchInput) {
                dd.hidden = true;
            }
        });
        
        // Actualizar lista cuando cambia el select
        const observer = new MutationObserver(() => {
            allOptions = Array.from(elementoSelect.options)
                .filter(opt => opt.value)
                .map(opt => ({
                    sku: opt.value,
                    name: opt.dataset.name_produc || opt.textContent.split(' — ')[1] || opt.textContent
                }));
        });
        observer.observe(elementoSelect, { childList: true });
        
        searchInput.focus();
    }

    if (elementoSelect){ elementoSelect.addEventListener('change', ()=>{ const sku = elementoSelect.value; const opt = elementoSelect.querySelector(`option[value="${sku}"]`); elementoSeleccionado = opt ? { sku, name: (opt.dataset.name_produc || opt.textContent) } : null; } ); }

    // al cargar la página si hay elementos en el hidden, restaurarlos
    try{ const existing = elementosJson && elementosJson.value ? JSON.parse(elementosJson.value) : null; if (Array.isArray(existing)) { elementos = existing; syncFormTable(); } } catch(e){ /* ignore */ }

    async function fetchProductosCargo(cargoId, operacionId){
        try{
        let url = `${window.location.origin}/cargo-productos`;
        const params = new URLSearchParams();
        if(cargoId) params.append('cargo_id', cargoId);
        if(operacionId) params.append('sub_area_id', operacionId);
        if(params.toString()) url += '?' + params.toString();
        console.log('Fetching from:', url);
        const resp = await fetch(url);
        console.log('Response status:', resp.status);
        if(resp.status === 204) return [];
        if(!resp.ok) throw new Error('fetch_error');
        const data = await resp.json();
        console.log('Data received:', data);
        return Array.isArray(data) ? data.filter(p=>p && p.sku && p.name_produc) : [];
        }catch(e){
        console.error('Error fetching productos:', e);
        return [];
        }
    }

    async function updateElementoOptions(){
        if(!elementoSelect) return;
        console.log('updateElementoOptions called');
        // Obtener cargo_id y operacion_id
        const cargoId = (cargoHidden && cargoHidden.value) ? cargoHidden.value : (cargoSelect && cargoSelect.value) ? cargoSelect.value : '';
        const operacionId = operacionSelect && operacionSelect.value ? operacionSelect.value : '';
        console.log('Filtering by cargo_id:', cargoId, 'operacion_id:', operacionId);
        
        const lista = await fetchProductosCargo(cargoId, operacionId);
        console.log('Lista productos:', lista.length, 'items');
        const current = elementoSelect.value;
        elementoSelect.innerHTML = '<option value="">Seleccione un producto</option>';
        lista.forEach(p=>{
        const opt = document.createElement('option');
        opt.value = String(p.sku);
        opt.dataset.name_produc = String(p.name_produc);
        opt.textContent = `${p.sku} — ${p.name_produc}`;
        elementoSelect.appendChild(opt);
        console.log('Added option:', p.sku, p.name_produc);
        });
        // Habilitar si hay productos o si no hay filtros (mostrar todos)
        elementoSelect.disabled = (lista.length === 0 && (cargoId || operacionId));
        console.log('Select enabled:', !elementoSelect.disabled, 'total options:', elementoSelect.options.length);
        if(current){ const found = elementoSelect.querySelector(`option[value="${current}"]`); elementoSelect.value = found ? current : ''; }
    }

    // inicial: cargar lista completa y asegurar hidden inicial
    updateElementoOptions();
    // asegurar hidden inicial con valor del select o fallback
    if (operacionHidden) operacionHidden.value = (operacionSelect && operacionSelect.value) ? String(operacionSelect.value) : (usuarioOperacionId || lastOperacionValue || '');

    // ------------------ Modal de recepciones para tipo "cambio" ------------------
    const modalRecepciones = document.getElementById('modalRecepciones');
    const buscarRecepcionInput = document.getElementById('buscarRecepcionInput');
    const recepcionesTbody = document.getElementById('recepcionesTbody');

    window.abrirModalRecepcion = function(){
        if (!modalRecepciones) return;
        modalRecepciones.classList.add('active');
        if (buscarRecepcionInput) buscarRecepcionInput.value = '';
        if (recepcionesTbody) {
            recepcionesTbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 20px;">Ingrese un número de documento para buscar recepciones</td></tr>';
        }
    }

    window.cerrarModalRecepcion = function(){
        if (!modalRecepciones) return;
        modalRecepciones.classList.remove('active');
    }

    window.buscarRecepciones = async function(){
        const numero = buscarRecepcionInput ? buscarRecepcionInput.value.trim() : '';
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
                title: 'Buscando recepciones...'
            });

            const url = `${window.location.origin}/recepciones/buscar?numero=${encodeURIComponent(numero)}`;
            const resp = await fetch(url);
            
            if (!resp.ok) throw new Error('Error en la búsqueda');
            
            const recepciones = await resp.json();
            
            if (!Array.isArray(recepciones) || recepciones.length === 0) {
                recepcionesTbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 20px;">No se encontraron recepciones para este documento</td></tr>';
                Toast.fire({
                    icon: 'info',
                    title: 'No se encontraron recepciones'
                });
                return;
            }

            // Renderizar recepciones encontradas
            recepcionesTbody.innerHTML = recepciones.map(r => {
                // Formatear elementos recibidos
                const elementosTexto = r.elementos && r.elementos.length > 0
                    ? r.elementos.map(e => `${e.sku} (${e.cantidad})`).join(', ')
                    : 'Sin elementos';
                
                return `
                <tr>
                    <td>${escapeHtml(new Date(r.fecha).toLocaleDateString())}</td>
                    <td>${escapeHtml(r.nombres)} ${escapeHtml(r.apellidos)}</td>
                    <td>${escapeHtml(r.numero_documento)}</td>
                    <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${escapeHtml(elementosTexto)}">${escapeHtml(elementosTexto)}</td>
                    <td>
                        <button type="button" class="btn btn-sm primary" onclick='seleccionarRecepcion(${JSON.stringify(r).replace(/'/g, "&apos;")})'>
                            Seleccionar
                        </button>
                    </td>
                </tr>
                `;
            }).join('');

            Toast.fire({
                icon: 'success',
                title: `${recepciones.length} recepción(es) encontrada(s)`
            });
        } catch (e) {
            console.error('Error buscando recepciones:', e);
            Toast.fire({
                icon: 'error',
                title: 'Error al buscar recepciones'
            });
        }
    }

    window.seleccionarRecepcion = async function(recepcion){
        if (!recepcion || !recepcion.elementos) return;

        // Rellenar datos del usuario
        if (nombreInput) nombreInput.value = recepcion.nombres || '';
        if (apellidosInput) apellidosInput.value = recepcion.apellidos || '';
        if (numeroInput) numeroInput.value = recepcion.numero_documento || '';
        if (tipoDocumentoSelect) tipoDocumentoSelect.value = recepcion.tipo_documento || 'CC';

        // Buscar y cargar datos completos del usuario desde la base de datos
        if (recepcion.numero_documento) {
            try {
                const fetchUrl = `${window.location.origin}/usuarios/buscar?numero=${encodeURIComponent(recepcion.numero_documento)}`;
                const respUsuario = await fetch(fetchUrl);
                
                if (respUsuario.ok) {
                    const dataUsuario = await respUsuario.json();
                    if (dataUsuario) {
                        // Cargar operación y cargo del usuario
                        if (operacionSelect && dataUsuario.operacion_id) {
                            usuarioOperacionId = String(dataUsuario.operacion_id);
                            operacionSelect.value = dataUsuario.operacion_id;
                            lastOperacionValue = String(dataUsuario.operacion_id) || lastOperacionValue;
                        }
                        if (lookupBox && dataUsuario.cargo_id) {
                            lookupBox.dataset.cargoId = String(dataUsuario.cargo_id);
                        }
                        if (cargoHidden && dataUsuario.cargo_id) {
                            cargoHidden.value = String(dataUsuario.cargo_id);
                        }
                        if (cargoSelect && dataUsuario.cargo_id) {
                            cargoSelect.value = dataUsuario.cargo_id;
                        }
                        // Actualizar estado y productos
                        updateOperacionState();
                        await updateElementoOptions();
                    }
                }
            } catch (err) {
                console.error('Error cargando datos del usuario:', err);
            }
        }

        // Guardar ID de recepción en campo hidden
        const recepcionIdHidden = document.getElementById('recepcionIdHidden');
        if (recepcionIdHidden) {
            recepcionIdHidden.value = recepcion.id || '';
        }

        // Obtener nombres de productos desde cargo_productos
        try {
            const skus = recepcion.elementos.map(e => e.sku);
            const url = `${window.location.origin}/productos/nombres`;
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
                const data = await resp.json();
                // Crear mapa de SKU => nombre
                data.forEach(p => {
                    productosMap[p.sku] = p.name_produc;
                });
            }

            // Cargar elementos de la recepción con sus nombres
            elementos = recepcion.elementos.map(e => ({
                sku: e.sku,
                name: productosMap[e.sku] || e.sku,
                cantidad: parseInt(e.cantidad) || 1
            }));
        } catch (err) {
            console.error('Error obteniendo nombres de productos:', err);
            // Si falla, cargar solo con SKU
            elementos = recepcion.elementos.map(e => ({
                sku: e.sku,
                name: e.sku,
                cantidad: parseInt(e.cantidad) || 1
            }));
        }
        
        syncFormTable();
        cerrarModalRecepcion();
        
        // Habilitar botón "Añadir elemento" para edición
        if (btnAnadirElemento) {
            btnAnadirElemento.style.display = '';
        }
        // Ocultar botón "Seleccionar recepción"
        if (btnSeleccionarRecepcion) {
            btnSeleccionarRecepcion.style.display = 'none';
        }
        
        Toast.fire({
            icon: 'success',
            title: 'Recepción cargada. Puede editar los elementos'
        });
    }

    // Búsqueda al presionar Enter en el input
    if (buscarRecepcionInput) {
        buscarRecepcionInput.addEventListener('keypress', function(e){
            if (e.key === 'Enter') {
                e.preventDefault();
                buscarRecepciones();
            }
        });
    }

    // ------------------ Firma en canvas (copiado de recepcion.js) ------------------
    function setupCanvas(){
        const canvas = document.getElementById('firmaCanvas');
        const pad = document.getElementById('firmaPad');
        const form = document.getElementById('entregasForm');
        if (!canvas || !pad) return;
        const ctx = canvas.getContext('2d');
        
        function resizeCanvas(){
            const dpr = window.devicePixelRatio || 1;
            const cssWidth = Math.min(pad.clientWidth - 32, 600);
            const cssHeight = Math.max(160, Math.floor(cssWidth * 0.4));
            canvas.style.width = cssWidth + 'px';
            canvas.style.height = cssHeight + 'px';
            canvas.width = Math.floor(cssWidth * dpr);
            canvas.height = Math.floor(cssHeight * dpr);
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
            ctx.lineWidth = 2;
            ctx.lineJoin = 'round';
            ctx.lineCap = 'round';
        }
        resizeCanvas();
        window.addEventListener('resize', resizeCanvas);

        let drawing = false;
        function getPos(e){
            const rect = canvas.getBoundingClientRect();
            const clientX = (e.touches ? e.touches[0].clientX : e.clientX);
            const clientY = (e.touches ? e.touches[0].clientY : e.clientY);
            return { x: clientX - rect.left, y: clientY - rect.top };
        }
        function start(e){
            e.preventDefault();
            drawing = true;
            const p = getPos(e);
            ctx.beginPath();
            ctx.moveTo(p.x, p.y);
        }
        function move(e){
            if(!drawing) return;
            const p = getPos(e);
            ctx.lineTo(p.x, p.y);
            ctx.stroke();
        }
        function end(){
            drawing = false;
        }
        
        canvas.addEventListener('mousedown', start);
        canvas.addEventListener('mousemove', move);
        canvas.addEventListener('mouseup', end);
        canvas.addEventListener('mouseleave', end);
        canvas.addEventListener('touchstart', start, {passive:false});
        canvas.addEventListener('touchmove', move, {passive:false});
        canvas.addEventListener('touchend', end);

        const clearBtn = document.getElementById('clearFirma');
        if (clearBtn) {
            clearBtn.addEventListener('click', function(){
                ctx.clearRect(0, 0, canvas.width, canvas.height);
            });
        }

        // Solo validar el formulario de entregas, no otros formularios (como logout)
        if (form) {
            form.addEventListener('submit', function(e){
                e.preventDefault();
                
                // Validaciones de campos requeridos
                const tipoDocumento = document.querySelector('select[name="tipo_documento"]');
                const numeroDocumento = document.getElementById('numberDocumento');
                const nombre = document.getElementById('nombre');
                const tipo = document.getElementById('tipoSelect');
                const operacion = document.getElementById('operacionSelect');
                // obtener valor de operación aceptando el hidden si el select está deshabilitado
                const operacionVal = (operacion && operacion.value) ? operacion.value : (operacionHidden ? operacionHidden.value : '');

                // Array para acumular errores
                let errores = [];
                
                // Validar tipo de documento
                if (!tipoDocumento || !tipoDocumento.value) {
                    errores.push('Tipo de documento');
                }
                
                // Validar número de documento
                if (!numeroDocumento || !numeroDocumento.value.trim()) {
                    errores.push('Número de documento');
                }
                
                // Validar nombres
                if (!nombre || !nombre.value.trim()) {
                    errores.push('Nombres');
                }
                
                // Validar tipo de entrega
                if (!tipo || !tipo.value) {
                    errores.push('Tipo de entrega');
                }
                
                // Validar operación
                if (!operacionVal) {
                    errores.push('Operación');
                }
                
                // Si hay errores de campos, mostrarlos
                if (errores.length > 0) {
                    Toast.fire({
                        icon: 'error',
                        title: 'Campos faltantes',
                        html: `Por favor complete: <br><strong>${errores.join(', ')}</strong>`,
                        timer: 5000
                    });
                    return false;
                }
                
                // Validar que haya al menos un elemento
                if (elementos.length === 0) {
                    Toast.fire({
                        icon: 'warning',
                        title: 'Sin elementos',
                        html: 'Debe agregar al menos <strong>1 elemento</strong> a la entrega.<br>Use el botón "Añadir elemento"',
                        timer: 4000
                    });
                    return false;
                }

                // Guardar firma y elementos
                const firmaField = document.getElementById('firmaField');
                if (firmaField) firmaField.value = canvas.toDataURL('image/png');
                if (elementosJson) elementosJson.value = JSON.stringify(elementos);
                // Asegurar hidden de operacion actualizado antes de enviar
                if (operacionHidden) operacionHidden.value = operacionVal || '';
                
                // Si todo está bien, enviar el formulario por AJAX y actualizar stocks en la tabla sin recargar
                const action = form.getAttribute('action') || window.location.href;
                const fd = new FormData(form);
                fetch(action, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json'
                    },
                    body: fd
                }).then(r => r.json()).then(j => {
                    if (j && j.success) {
                        Toast.fire({ icon: 'success', title: 'Entrega registrada correctamente' });
                        // si vienen stocks actualizados, aplicarlos a la tabla
                        if (j.updatedStocks && typeof j.updatedStocks === 'object') {
                            Object.keys(j.updatedStocks).forEach(function(sku){
                                const val = String(j.updatedStocks[sku]);
                                document.querySelectorAll(`tr[data-sku="${sku}"]`).forEach(function(row){
                                    row.setAttribute('data-stock', val);
                                    // columna de stock es la 8ª (index 7)
                                    if (row.cells && row.cells.length > 7) row.cells[7].textContent = val;
                                });
                            });
                        }
                        // opcional: limpiar formulario y modal
                        try { elementos = []; syncFormTable(); } catch(e){}
                    } else {
                        Toast.fire({ icon: 'error', title: (j && j.message) ? j.message : 'Error al registrar entrega' });
                    }
                }).catch(err => {
                    console.error('Entrega submit error', err);
                    Toast.fire({ icon: 'error', title: 'Error al enviar la entrega' });
                });
            });
        }
    }

    // Exponer funciones globales para compatibilidad
    window.guardarFirma = function(){
        const canvas = document.getElementById('firmaCanvas');
        const firmaField = document.getElementById('firmaField');
        if (canvas && firmaField) {
            firmaField.value = canvas.toDataURL('image/png');
        }
        if (elementosJson) {
            elementosJson.value = JSON.stringify(elementos);
        }
    };

    window.limpiarFirma = function(){
        const canvas = document.getElementById('firmaCanvas');
        if (canvas) {
            const ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, canvas.width, canvas.height);
        }
    };

    setupCanvas();
});
