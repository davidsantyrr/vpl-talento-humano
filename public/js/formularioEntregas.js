document.addEventListener('DOMContentLoaded', function () {
    const tipoSelect = document.getElementById('tipoSelect');
    const operacionSelect = document.getElementById('operacionSelect');
    const fieldOperacion = document.getElementById('field-operacion');
    const fieldCargo = document.getElementById('field-cargo');
    const cargoSelect = document.getElementById('cargoSelect');
    const cargoHidden = document.getElementById('cargoIdHidden');
    const lookupBox = document.getElementById('usuarioLookup');
    const elementoSelect = document.getElementById('elementoSelect');

    // El select de elementos se poblará desde cargo_productos SIN filtros
    if (elementoSelect) {
        elementoSelect.innerHTML = '<option value="">Seleccione un producto</option>';
    }

    if (!tipoSelect || !operacionSelect) return;

    function normalize(val) { return String(val || '').trim().toLowerCase(); }

    // recordar última operación seleccionada y operación asociada al usuario
    let lastOperacionValue = operacionSelect.value || '';
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
        const showCargo = (tipo === 'primera vez' || tipo === 'periodica' || tipo === 'cambio');

        // Mostrar ambos campos para esos tipos
        if (fieldCargo && fieldOperacion) {
            fieldCargo.style.display = showCargo ? '' : 'none';
            fieldOperacion.style.display = '';
        }

        // Operación siempre habilitada y requerida
        operacionSelect.disabled = false;
        operacionSelect.required = true;
        operacionSelect.classList.remove('disabled');

        // si hay operación del usuario y no hay selección, aplicar
        if (!operacionSelect.value && usuarioOperacionId) {
            operacionSelect.value = usuarioOperacionId;
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
    tipoSelect.addEventListener('change', updateOperacionState);

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
                    usuarioOperacionId = data.operacion_id;
                    operacionSelect.value = data.operacion_id;
                    lastOperacionValue = data.operacion_id;
                }

                // NUEVO: exponer cargo_id para otros scripts
                if (lookupBox) lookupBox.dataset.cargoId = data.cargo_id ? String(data.cargo_id) : '';
                if (cargoHidden) cargoHidden.value = data.cargo_id ? String(data.cargo_id) : '';
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
        }
        if (cargoHidden && typeof data?.cargo_id !== 'undefined') {
            cargoHidden.value = data.cargo_id ? String(data.cargo_id) : '';
        }
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

    // cuando el usuario cambia manualmente la operación, actualizar el valor recordado
    operacionSelect.addEventListener('change', function () {
        lastOperacionValue = operacionSelect.value || lastOperacionValue;
    });

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
        if (elementoSelect) {
            elementoSelect.value = '';
            elementoSelect.innerHTML = '<option value="">Seleccione un producto</option>';
        }
        if (cantidadInput) cantidadInput.value = '1';
        updateElementoOptions();
        renderModalTable();
        if (elementoSelect) elementoSelect.focus();
    }
    
    window.cerrarModal = function(){ 
        if (!modal) return; 
        tempElementos = [];
        modal.classList.remove('active'); 
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
            const name = opt ? (opt.dataset.name_produc || opt.textContent) : '';
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
        renderModalTable();
        Toast.fire({
            icon: 'success',
            title: 'Producto agregado a la lista'
        });
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

    // inicial: cargar lista completa
    updateElementoOptions();

    // ------------------ Firma en canvas (copiado de recepcion.js) ------------------
    function setupCanvas(){
        const canvas = document.getElementById('firmaCanvas');
        const pad = document.getElementById('firmaPad');
        const form = document.querySelector('form');
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

        if (form) {
            form.addEventListener('submit', function(){
                const firmaField = document.getElementById('firmaField');
                if (firmaField) firmaField.value = canvas.toDataURL('image/png');
                if (elementosJson) elementosJson.value = JSON.stringify(elementos);
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
