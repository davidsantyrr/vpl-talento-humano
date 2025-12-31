document.addEventListener('DOMContentLoaded', function () {
    const tipoSelect = document.getElementById('tipoSelect');
    const operacionSelect = document.getElementById('operacionSelect');

    if (!tipoSelect || !operacionSelect) return;

    function normalize(val) {
        return String(val || '').trim().toLowerCase();
    }

    function updateOperacionState() {
        const tipo = normalize(tipoSelect.value);
        // permitir operacion solo si tipo es 'prestamo'
        if (tipo === 'prestamo') {
            operacionSelect.disabled = false;
            operacionSelect.required = true;
            operacionSelect.classList.remove('disabled');
        } else {
            // para 'primera vez' o 'periodica' (o cualquier otro) deshabilitar
            operacionSelect.disabled = true;
            operacionSelect.required = false;
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
                    updateOperacionState();
                    return;
                }

                showLookupMessage(`Usuario encontrado: ${data.nombres} ${data.apellidos || ''}`);

                if (nombreInput) nombreInput.value = data.nombres || '';
                if (apellidosInput) apellidosInput.value = data.apellidos || '';
                if (tipoDocumentoSelect && data.tipo_documento) tipoDocumentoSelect.value = data.tipo_documento;

                if (operacionSelect && data.operacion_id) {
                    // asignar la operación encontrada; si el tipo no es 'prestamo' seguirá deshabilitada
                    operacionSelect.value = data.operacion_id;
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
});
