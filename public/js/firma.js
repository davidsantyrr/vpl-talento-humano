const canvas = document.getElementById('firmaCanvas');
const ctx = canvas.getContext('2d');
let dibujando = false;
let lastX = 0;
let lastY = 0;

// Ajustes del trazo
ctx.lineWidth = 2;
ctx.lineCap = 'round';
ctx.lineJoin = 'round';
ctx.strokeStyle = '#000';

// Reemplazo: manejo preciso de inicio / movimiento / fin
canvas.addEventListener('mousedown', (e) => {
    const rect = canvas.getBoundingClientRect();
    lastX = e.clientX - rect.left;
    lastY = e.clientY - rect.top;
    dibujando = true;
    ctx.beginPath();
    ctx.moveTo(lastX, lastY);
});
canvas.addEventListener('mousemove', (e) => {
    if (!dibujando) return;
    const rect = canvas.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;
    ctx.beginPath();
    ctx.moveTo(lastX, lastY);
    ctx.lineTo(x, y);
    ctx.stroke();
    lastX = x;
    lastY = y;
    // removed frequent guardarFirma() calls to reduce overhead
});
canvas.addEventListener('mouseup', () => {
    dibujando = false;
    ctx.beginPath();
    guardarFirma();
});
canvas.addEventListener('mouseleave', () => {
    dibujando = false;
    ctx.beginPath();
    guardarFirma();
});

// Touch (móvil)
canvas.addEventListener('touchstart', (e) => {
    e.preventDefault();
    const rect = canvas.getBoundingClientRect();
    const touch = e.touches[0];
    lastX = touch.clientX - rect.left;
    lastY = touch.clientY - rect.top;
    dibujando = true;
    ctx.beginPath();
    ctx.moveTo(lastX, lastY);
});
canvas.addEventListener('touchmove', (e) => {
    e.preventDefault();
    if (!dibujando) return;
    const rect = canvas.getBoundingClientRect();
    const touch = e.touches[0];
    const x = touch.clientX - rect.left;
    const y = touch.clientY - rect.top;
    ctx.beginPath();
    ctx.moveTo(lastX, lastY);
    ctx.lineTo(x, y);
    ctx.stroke();
    lastX = x;
    lastY = y;
});
canvas.addEventListener('touchend', () => {
    dibujando = false;
    ctx.beginPath();
    guardarFirma();
});

// Guardar firma en Base64
function guardarFirma() {
    const canvas = document.getElementById('firmaCanvas');
    const input = document.getElementById('firmaInput');
    if (!canvas || !input) return;
    input.value = canvas.toDataURL('image/png');
}

// Limpiar canvas
function limpiarFirma() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    document.getElementById('firmaInput').value = '';
}

// Nuevas funciones para el modal
function abrirModal() {
    const modal = document.getElementById('modalElementos');
    if (!modal) return;
    modal.classList.add('active');
}

function cerrarModal() {
    const modal = document.getElementById('modalElementos');
    if (!modal) return;
    modal.classList.remove('active');
}

// cerrar al hacer click fuera del contenido interno
document.addEventListener('click', function(e) {
    const modal = document.getElementById('modalElementos');
    if (!modal || !modal.classList.contains('active')) return;
    if (e.target === modal) cerrarModal();
});

// cerrar con Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') cerrarModal();
});

// mantiene lista en memoria por si se necesita enviar al servidor
let elementosLista = [];

/** renderiza ambas tablas (modal preview y tabla del formulario) y actualiza input oculto */
function renderElementos() {
    const tbodyModal = document.getElementById('elementosTbody');
    const tbodyForm = document.getElementById('elementosFormTbody');
    const elementosJson = document.getElementById('elementosJson');
    if (!tbodyModal || !tbodyForm || !elementosJson) return;

    // limpiar
    tbodyModal.innerHTML = '';
    tbodyForm.innerHTML = '';

    // repoblar
    elementosLista.forEach((el, idx) => {
        const trModal = document.createElement('tr');
        trModal.dataset.index = idx;
        trModal.innerHTML = `
            <td>${el.texto}</td>
            <td>${el.cantidad}</td>
            <td><button type="button" onclick="eliminarFila(${idx})">Eliminar</button></td>
        `;
        tbodyModal.appendChild(trModal);

        const trForm = document.createElement('tr');
        trForm.dataset.index = idx;
        trForm.innerHTML = `
            <td>${el.texto}</td>
            <td>${el.cantidad}</td>
            <td><button type="button" onclick="eliminarFila(${idx})">Eliminar</button></td>
        `;
        tbodyForm.appendChild(trForm);
    });

    // actualizar input oculto con JSON limpio (sin indices nulos)
    elementosJson.value = JSON.stringify(elementosLista);
}

/**
 * Agrega el elemento seleccionado en el modal a la lista y renderiza tablas.
 */
function agregarElementoModal() {
    const select = document.getElementById('elementoSelect');
    const cantidadInput = document.getElementById('cantidadInput');
    if (!select || !cantidadInput) return;

    const valor = select.value;
    const texto = select.options[select.selectedIndex]?.text || valor;
    const cantidad = parseInt(cantidadInput.value, 10);

    if (!valor || isNaN(cantidad) || cantidad <= 0) {
        alert('Seleccione un elemento y asigne una cantidad válida.');
        return;
    }

    elementosLista.push({ valor, texto, cantidad });

    // renderizar tablas y actualizar input oculto
    renderElementos();

    // limpiar inputs para siguiente adición
    cantidadInput.value = '';
    select.selectedIndex = 0;

    // opcional: dejar el modal abierto para seguir añadiendo
}

/**
 * Elimina una fila por índice y re-renderiza tablas.
 */
function eliminarFila(index) {
    if (index < 0 || index >= elementosLista.length) return;
    elementosLista.splice(index, 1);
    renderElementos();
}