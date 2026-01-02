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

    form && form.addEventListener('submit', function(){
      const firmaField = document.getElementById('firmaField');
      if (firmaField) firmaField.value = canvas.toDataURL('image/png');
      // El itemsField se maneja en recepcionModal.js
    });
  }

  setupCanvas();
})();
