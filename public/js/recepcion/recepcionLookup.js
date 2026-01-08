(function(){
    const numeroInput = document.getElementById('numDocumento');
    const nombresInput = document.getElementById('nombresRecepcion');
    const apellidosInput = document.getElementById('apellidosRecepcion');
    const tipoDocumentoSelect = document.getElementById('tipoDocumento');
    const operacionSelect = document.getElementById('operacionRecepcion');
    const lookupBox = document.getElementById('usuarioLookupRecepcion');
    const usuariosIdHidden = document.getElementById('usuariosIdHidden');

    if (!numeroInput || !lookupBox) return;

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
                    if (nombresInput) nombresInput.value = '';
                    if (tipoDocumentoSelect) tipoDocumentoSelect.value = 'CC';
                    if (operacionSelect) operacionSelect.value = '';
                    const opHiddenClear = document.getElementById('operacionIdHidden');
                    if (opHiddenClear) opHiddenClear.value = '';
                    if (usuariosIdHidden) usuariosIdHidden.value = '';
                    if (usuariosIdHidden) usuariosIdHidden.value = '';
                    return;
                }

                showLookupMessage(`Usuario encontrado: ${data.nombres ?? data.name} ${data.apellidos || ''}`);

                if (nombresInput) nombresInput.value = data.nombres ?? data.name ?? '';
                if (apellidosInput) apellidosInput.value = data.apellidos ?? '';
                if (tipoDocumentoSelect && data.tipo_documento) {
                    tipoDocumentoSelect.value = data.tipo_documento;
                if (operacionSelect && (data.operacion_id || data.operaciones)) {
                    operacionSelect.value = data.operacion_id ?? operacionSelect.value;
                    const opHidden = document.getElementById('operacionIdHidden');
                    if (opHidden) opHidden.value = data.operacion_id ?? opHidden.value;
                }
                }
                if (usuariosIdHidden && typeof data.id !== 'undefined') {
                    usuariosIdHidden.value = String(data.id);
                }

                try {
                    document.dispatchEvent(new CustomEvent('usuario:cargado:recepcion', { detail: data }));
                } catch (e) { /* ignore */ }
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
})();
