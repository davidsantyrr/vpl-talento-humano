(function(){
  const cfg = window.RecepcionPageConfig || {};
  const all = Array.isArray(cfg.allProducts) ? cfg.allProducts : [];

  const form = document.getElementById('recepcionForm');
  const itemsField = document.getElementById('itemsField');

  // Firma en canvas
  function setupCanvas(){
    const canvas = document.getElementById('firmaCanvas');
    const pad = document.getElementById('firmaPad');
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
      ctx.lineWidth = 2; ctx.lineJoin = 'round'; ctx.lineCap = 'round';
    }
    resizeCanvas();
    window.addEventListener('resize', resizeCanvas);

    let drawing = false;
    function getPos(e){ const rect = canvas.getBoundingClientRect(); const clientX = (e.touches ? e.touches[0].clientX : e.clientX); const clientY = (e.touches ? e.touches[0].clientY : e.clientY); return { x: clientX - rect.left, y: clientY - rect.top }; }
    function start(e){ e.preventDefault(); drawing = true; const p = getPos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); }
    function move(e){ if(!drawing) return; const p = getPos(e); ctx.lineTo(p.x, p.y); ctx.stroke(); }
    function end(){ drawing = false; }
    canvas.addEventListener('mousedown', start); canvas.addEventListener('mousemove', move); canvas.addEventListener('mouseup', end); canvas.addEventListener('mouseleave', end);
    canvas.addEventListener('touchstart', start, {passive:false}); canvas.addEventListener('touchmove', move, {passive:false}); canvas.addEventListener('touchend', end);

    const clearBtn = document.getElementById('clearFirma');
    clearBtn && clearBtn.addEventListener('click', function(){ ctx.clearRect(0,0,canvas.width,canvas.height); });

    // Validación del formulario
    form && form.addEventListener('submit', function(e){
      e.preventDefault();
      
      // Obtener valores de los campos
      const tipoDocumento = document.getElementById('tipoDocumento');
      const numDocumento = document.getElementById('numDocumento');
      const nombres = document.getElementById('nombresRecepcion');
      const apellidos = document.getElementById('apellidosRecepcion');
      const operacion = document.getElementById('operacionRecepcion');
      const usuariosId = document.getElementById('usuariosIdHidden');
      
      // Array para acumular errores
      let errores = [];
      
      // Validar tipo de documento
      if (!tipoDocumento || !tipoDocumento.value) {
        errores.push('Tipo de documento');
      }
      
      // Validar número de documento
      if (!numDocumento || !numDocumento.value.trim()) {
        errores.push('Número de documento');
      }
      
      // Validar nombres
      if (!nombres || !nombres.value.trim()) {
        errores.push('Nombres');
      }
      
      // Validar apellidos
      if (!apellidos || !apellidos.value.trim()) {
        errores.push('Apellidos');
      }
      
      // Validar operación
      if (!operacion || !operacion.value) {
        errores.push('Operación');
      }
      
      // Validar usuarios_id
      if (!usuariosId || !usuariosId.value) {
        errores.push('Usuario (busque por número de documento)');
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
      
      // Validar que haya elementos
      const itemsValue = itemsField ? itemsField.value : '[]';
      let items = [];
      try {
        items = JSON.parse(itemsValue);
      } catch(e) {
        items = [];
      }
      
      if (!items || items.length === 0) {
        Toast.fire({
          icon: 'warning',
          title: 'Sin elementos',
          html: 'Debe agregar al menos <strong>1 elemento</strong> a la recepción.<br>Use el botón "Añadir elemento"',
          timer: 4000
        });
        return false;
      }
      
      // Guardar firma
      const firmaField = document.getElementById('firmaField');
      if (firmaField) firmaField.value = canvas.toDataURL('image/png');
      
      // Si todo está bien, enviar el formulario
      form.submit();
    });
  }

  setupCanvas();
})();
