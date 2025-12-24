const canvas = document.getElementById('firmaCanvas');
const ctx = canvas.getContext('2d');
let dibujando = false;

// Ajustes del trazo
ctx.lineWidth = 2;
ctx.lineCap = 'round';
ctx.strokeStyle = '#000';

// Mouse
canvas.addEventListener('mousedown', () => dibujando = true);
canvas.addEventListener('mouseup', () => dibujando = false);
canvas.addEventListener('mouseleave', () => dibujando = false);

canvas.addEventListener('mousemove', dibujar);

// Touch (móvil)
canvas.addEventListener('touchstart', () => dibujando = true);
canvas.addEventListener('touchend', () => dibujando = false);
canvas.addEventListener('touchmove', dibujarTouch);

function dibujar(e) {
    if (!dibujando) return;

    const rect = canvas.getBoundingClientRect();
    ctx.lineTo(e.clientX - rect.left, e.clientY - rect.top);
    ctx.stroke();
    ctx.beginPath();
    ctx.moveTo(e.clientX - rect.left, e.clientY - rect.top);

    guardarFirma();
}

function dibujarTouch(e) {
    e.preventDefault();
    if (!dibujando) return;

    const rect = canvas.getBoundingClientRect();
    const touch = e.touches[0];

    ctx.lineTo(touch.clientX - rect.left, touch.clientY - rect.top);
    ctx.stroke();
    ctx.beginPath();
    ctx.moveTo(touch.clientX - rect.left, touch.clientY - rect.top);

    guardarFirma();
}

// Guardar firma en Base64
function guardarFirma() {
    document.getElementById('firmaInput').value = canvas.toDataURL('image/png');
}

// Limpiar canvas
function limpiarFirma() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    document.getElementById('firmaInput').value = '';
}