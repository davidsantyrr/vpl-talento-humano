// filepath: c:\laragon\www\vpl-talento-humano\public\js\recepcion\recepcionModal.js
(function(){
    const cfg = window.RecepcionPageConfig || {};
    const allProducts = Array.isArray(cfg.allProducts) ? cfg.allProducts : [];
    
    const modal = document.getElementById('modalElementosRecepcion');
    const elementoSelect = document.getElementById('elementoSelectRecepcion');
    const cantidadInput = document.getElementById('cantidadInputRecepcion');
    const elementosTbody = document.getElementById('elementosTbodyRecepcion');
    const tableBody = document.querySelector('#itemsTable tbody');
    const itemsField = document.getElementById('itemsField');
    const addBtnTrigger = document.getElementById('addItemBtn');
    const btnSeleccionarEntrega = document.getElementById('btnSeleccionarEntrega');
    const tipoRecepcionSelect = document.getElementById('tipoRecepcionSelect');

    let items = [];
    let tempItems = [];

    // Toast helper
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

    // Función para actualizar botones según el tipo
    function updateTipoRecepcionState() {
        if (!tipoRecepcionSelect) return;
        const tipo = tipoRecepcionSelect.value;
        const isPrestamo = (tipo === 'prestamo');

        if (addBtnTrigger) {
            addBtnTrigger.style.display = isPrestamo ? 'none' : '';
        }
        if (btnSeleccionarEntrega) {
            btnSeleccionarEntrega.style.display = isPrestamo ? '' : 'none';
        }
    }

    // Evento para cambio de tipo
    if (tipoRecepcionSelect) {
        tipoRecepcionSelect.addEventListener('change', updateTipoRecepcionState);
    }

    // Inicializar estado
    updateTipoRecepcionState();

    function escapeHtml(s){ 
        return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); 
    }

    function renderModalTable(){
        if (!elementosTbody) return;
        elementosTbody.innerHTML = tempItems.map(it => 
            `<tr><td>${escapeHtml(it.sku)} — ${escapeHtml(it.name)}</td><td style="text-align:center;">${it.cantidad}</td></tr>`
        ).join('');
    }

    function renderFormTable(){
        if (!tableBody) return;
        tableBody.innerHTML = items.map((it, idx) => 
            `<tr data-idx="${idx}"><td>${escapeHtml(it.sku)} — ${escapeHtml(it.name)}</td><td style="text-align:center;">${it.cantidad}</td><td><button type="button" class="btn btn-sm btn-danger" data-idx="${idx}">Quitar</button></td></tr>`
        ).join('');
        
        Array.from(tableBody.querySelectorAll('button[data-idx]')).forEach(btn => {
            btn.addEventListener('click', () => {
                const i = Number(btn.dataset.idx);
                items.splice(i, 1);
                syncFormTable();
            });
        });
        
        if (itemsField) itemsField.value = JSON.stringify(items);
    }

    function syncFormTable(){
        renderFormTable();
    }

    // Poblar select con productos desde cargo_productos filtrado por operación
    async function populateSelect(){
        if (!elementoSelect) return;
        
        const operacionSelect = document.getElementById('operacionRecepcion');
        const operacionId = operacionSelect && operacionSelect.value ? operacionSelect.value : '';
        
        console.log('Poblando select con operacion_id:', operacionId);
        
        // Fetch productos desde cargo_productos
        const productos = await fetchProductosCargo(operacionId);
        console.log('Productos recibidos:', productos.length);
        
        elementoSelect.innerHTML = '<option value="">Seleccione un producto</option>';
        productos.forEach(p => {
            const opt = document.createElement('option');
            opt.value = String(p.sku);
            opt.dataset.name_produc = String(p.name_produc);
            opt.textContent = `${p.sku} — ${p.name_produc}`;
            elementoSelect.appendChild(opt);
        });
    }
    
    async function fetchProductosCargo(operacionId){
        try{
            let url = `${window.location.origin}/cargo-productos`;
            const params = new URLSearchParams();
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

    // Abrir modal
    window.abrirModalRecepcion = async function(){
        if (!modal) return;
        tempItems = items.slice();
        modal.classList.add('active');
        
        // Limpiar dropdown previo
        const prevDD = document.getElementById('modal-prod-dd-recepcion');
        if (prevDD) prevDD.remove();
        
        if (elementoSelect) elementoSelect.value = '';
        if (cantidadInput) cantidadInput.value = '1';
        
        await populateSelect();
        setupDropdownRecepcion();
        renderModalTable();
    };

    // Cerrar modal
    window.cerrarModalRecepcion = function(){
        if (!modal) return;
        tempItems = [];
        modal.classList.remove('active');
        
        const dd = document.getElementById('modal-prod-dd-recepcion');
        if (dd) dd.remove();
        
        const searchInput = document.getElementById('modal-search-input-recepcion');
        if (searchInput) searchInput.remove();
        if (elementoSelect) elementoSelect.style.display = '';
    };

    // Guardar modal
    window.guardarModalRecepcion = function(){
        if (!modal) return;
        if (tempItems.length === 0) {
            Toast.fire({
                icon: 'warning',
                title: 'Agregue al menos un producto a la lista'
            });
            return;
        }
        items = tempItems.slice();
        syncFormTable();
        tempItems = [];
        modal.classList.remove('active');
        Toast.fire({
            icon: 'success',
            title: 'Elementos agregados correctamente'
        });
    };

    // Agregar elemento
    window.agregarElementoModalRecepcion = function(){
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
        const qty = parseInt(cantidadInput && cantidadInput.value ? cantidadInput.value : '0', 10);
        if (!qty || qty < 1) {
            Toast.fire({
                icon: 'error',
                title: 'Ingrese una cantidad válida'
            });
            return;
        }
        tempItems.push({ sku: sel.sku, name: sel.name, cantidad: qty });
        elementoSelect.value = '';
        cantidadInput.value = '1';
        
        // Limpiar el input de búsqueda si existe
        const searchInput = document.getElementById('modal-search-input-recepcion');
        if (searchInput) {
            searchInput.value = '';
        }
        
        renderModalTable();
        Toast.fire({
            icon: 'success',
            title: 'Producto agregado a la lista'
        });
    };

    // Setup dropdown de búsqueda
    function setupDropdownRecepcion(){
        if (!elementoSelect) return;
        
        let searchInput = document.getElementById('modal-search-input-recepcion');
        if (searchInput) {
            searchInput.value = '';
            searchInput.focus();
            return;
        }
        
        const wrapper = elementoSelect.parentElement;
        searchInput = document.createElement('input');
        searchInput.type = 'text';
        searchInput.className = elementoSelect.className;
        searchInput.placeholder = 'Escribe para buscar SKU o nombre';
        searchInput.id = 'modal-search-input-recepcion';
        
        elementoSelect.style.display = 'none';
        wrapper.insertBefore(searchInput, elementoSelect);
        
        const dd = document.createElement('ul');
        dd.id = 'modal-prod-dd-recepcion';
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

    // Vincular botón de añadir elemento
    if (addBtnTrigger) {
        addBtnTrigger.addEventListener('click', function(){
            abrirModalRecepcion();
        });
    }
    
    // Escuchar cambios en operación para recargar productos
    const operacionSelect = document.getElementById('operacionRecepcion');
    if (operacionSelect) {
        operacionSelect.addEventListener('change', function(){
            console.log('Operación cambió, recargando productos...');
            // Si la modal está abierta, recargar productos
            if (modal && modal.classList.contains('active')) {
                populateSelect().then(() => {
                    // Actualizar dropdown si existe
                    const searchInput = document.getElementById('modal-search-input-recepcion');
                    if (searchInput) {
                        searchInput.value = '';
                        setupDropdownRecepcion();
                    }
                });
            }
        });
    }

    // Inicializar
    populateSelect();
})();
