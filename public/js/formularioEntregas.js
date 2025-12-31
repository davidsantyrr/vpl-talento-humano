document.addEventListener('DOMContentLoaded', function () {
    const tipoSelect = document.getElementById('tipoSelect');
    const operacionSelect = document.getElementById('operacionSelect');

    if (!tipoSelect || !operacionSelect) return;

    function normalize(val) {
        return String(val || '').trim().toLowerCase();
    }

    // recordar última operación seleccionada y operación asociada al usuario
    let lastOperacionValue = operacionSelect.value || '';
    let usuarioOperacionId = null;

    function updateOperacionState() {
        const tipo = normalize(tipoSelect.value);
        // permitir operacion solo si tipo es 'prestamo'
        if (tipo === 'prestamo') {
            operacionSelect.disabled = false;
            operacionSelect.required = true;
            operacionSelect.classList.remove('disabled');
        } else {
            // para 'primera vez' o 'periodica' (o cualquier otro) deshabilitar
            // recordar la selección actual antes de deshabilitar
            lastOperacionValue = operacionSelect.value || lastOperacionValue;
            operacionSelect.disabled = true;
            operacionSelect.required = false;
            // al deshabilitar, restaurar la operación asociada al usuario si existe, sino limpiar
            if (usuarioOperacionId) {
                operacionSelect.value = usuarioOperacionId;
            } else {
                operacionSelect.value = '';
            }
            // mantener selección (si existe) pero no permitir cambios
            operacionSelect.classList.add('disabled');
        }
    }

    // eventos
    tipoSelect.addEventListener('change', updateOperacionState);

    // inicializar estado al cargar la página
    updateOperacionState();
    
    // --- Buscar usuario por número de documento y rellenar campos ---
    const numeroInput = document.getElementById('numberDocumento');
    const nombreInput = document.getElementById('nombre');
    const apellidosInput = document.getElementById('apellidos');
    const tipoDocumentoSelect = document.querySelector('select[name="tipo_documento"]');

    const lookupBox = document.getElementById('usuarioLookup');

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
                    const crearUrl = lookupBox?.dataset?.crearUrl || '/gestionUsuario';
                    showLookupMessage(`Usuario no encontrado. <a href="${crearUrl}">Crear usuario</a>`);
                    if (nombreInput) nombreInput.value = '';
                    if (apellidosInput) apellidosInput.value = '';
                    if (tipoDocumentoSelect) tipoDocumentoSelect.value = '';
                    usuarioOperacionId = null;
                    updateOperacionState();
                    return;
                }

                showLookupMessage(`Usuario encontrado: ${data.nombres} ${data.apellidos || ''}`);

                if (nombreInput) nombreInput.value = data.nombres || '';
                if (apellidosInput) apellidosInput.value = data.apellidos || '';
                if (tipoDocumentoSelect && data.tipo_documento) tipoDocumentoSelect.value = data.tipo_documento;

                if (operacionSelect && data.operacion_id) {
                    // asignar la operación encontrada; si el tipo no es 'prestamo' seguirá deshabilitada
                    usuarioOperacionId = data.operacion_id;
                    operacionSelect.value = data.operacion_id;
                    lastOperacionValue = data.operacion_id;
                }
                // actualizar el estado (habilita solo si tipo === 'prestamo')
                updateOperacionState();
            })
            .catch(() => {
                showLookupMessage('Error al buscar usuario', true);
            });
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
    const productos = (window.FormularioPageConfig && Array.isArray(window.FormularioPageConfig.allProducts) ? window.FormularioPageConfig.allProducts : (window.RecepcionPageConfig && Array.isArray(window.RecepcionPageConfig.allProducts) ? window.RecepcionPageConfig.allProducts : []));

    const modal = document.getElementById('modalElementos');
    const elementoSelect = document.getElementById('elementoSelect');
    const cantidadInput = document.getElementById('cantidadInput');
    const elementosTbody = document.getElementById('elementosTbody');
    const elementosFormTbody = document.getElementById('elementosFormTbody');
    const elementosJson = document.getElementById('elementosJson');

    let elementoSeleccionado = null;
    let elementos = [];

    function escapeHtml(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

    function filterProductos(term){ const t = String(term||'').trim().toLowerCase(); if (!t) return productos.slice(); return productos.filter(p => (p.sku||'').toLowerCase().includes(t) || (p.name||'').toLowerCase().includes(t)); }

    function renderModalTable(){ if (!elementosTbody) return; elementosTbody.innerHTML = elementos.map(it => `<tr><td>${escapeHtml(it.sku)} — ${escapeHtml(it.name)}</td><td style="text-align:center;">${it.cantidad}</td></tr>`).join(''); }
    function renderFormTable(){ if (!elementosFormTbody) return; elementosFormTbody.innerHTML = elementos.map((it, idx) => `<tr data-idx="${idx}"><td>${escapeHtml(it.sku)} — ${escapeHtml(it.name)}</td><td style="text-align:center;">${it.cantidad}</td><td><button type="button" class="btn btn-sm btn-danger" data-idx="${idx}">Quitar</button></td></tr>`).join(''); Array.from(elementosFormTbody.querySelectorAll('button[data-idx]')).forEach(btn=>btn.addEventListener('click', (e)=>{ const i = Number(btn.dataset.idx); elementos.splice(i,1); syncAndRender(); })); }

    function syncAndRender(){ if (elementosJson) elementosJson.value = JSON.stringify(elementos); renderModalTable(); renderFormTable(); }

    // abrir/cerrar modal y eventos
    window.abrirModal = function(){ if (!modal) return; modal.classList.add('active'); elementoSeleccionado = null; if (elementoSelect) { elementoSelect.value = ''; elementoSelect.focus(); } }
    window.cerrarModal = function(){ if (!modal) return; modal.classList.remove('active'); }

    window.agregarElementoModal = function(){
        let sel = null;
        if (elementoSelect && elementoSelect.value) {
            const sku = elementoSelect.value;
            const opt = elementoSelect.querySelector(`option[value="${sku}"]`);
            const name = opt ? opt.dataset.name || opt.textContent : '';
            sel = { sku, name };
        }
        if (!sel) { alert('Seleccione un elemento válido'); return; }
        const qty = parseInt(cantidadInput && cantidadInput.value ? cantidadInput.value : '0',10);
        if (!qty || qty < 1) { alert('Ingrese una cantidad válida'); return; }
        elementos.push({ sku: sel.sku, name: sel.name, cantidad: qty });
        syncAndRender();
    }

    if (elementoSelect){ elementoSelect.addEventListener('change', ()=>{ const sku = elementoSelect.value; const opt = elementoSelect.querySelector(`option[value="${sku}"]`); elementoSeleccionado = opt ? { sku, name: opt.dataset.name || opt.textContent } : null; } ); }

    // al cargar la página si hay elementos en el hidden, restaurarlos
    try{ const existing = elementosJson && elementosJson.value ? JSON.parse(elementosJson.value) : null; if (Array.isArray(existing)) { elementos = existing; syncAndRender(); } } catch(e){ /* ignore */ }
});
