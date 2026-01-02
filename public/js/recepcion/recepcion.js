(function(){
  const cfg = window.RecepcionPageConfig || {};
  const all = Array.isArray(cfg.allProducts) ? cfg.allProducts : [];

  const tableBody = document.querySelector('#itemsTable tbody');
  const itemsField = document.getElementById('itemsField');
  const form = document.getElementById('recepcionForm');
  const addBtnTrigger = document.getElementById('addItemBtn');
  const items = [];

  function escapeHtml(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
  function render(){
    if (!tableBody) return;
    tableBody.innerHTML = items.map(it => `<tr><td>${escapeHtml(it.sku)} — ${escapeHtml(it.name)}</td><td style="text-align:center;">${it.cantidad}</td></tr>`).join('');
    if (itemsField) itemsField.value = JSON.stringify(items);
  }

  function openAddModal(){
    let temp = items.slice();
    const prev = document.getElementById('sw-prod-dd');
    if (prev) prev.remove();
    Swal.fire({
      title: 'Añadir elementos',
      width: 1000,
      customClass: { popup: 'sw-tall' },
      html: `
        <div class="sw-grid">
          <div class="sw-field">
            <label>Producto</label>
            <input id="sw-prod-input" class="swal2-input" placeholder="Escribe para buscar SKU o nombre" />
          </div>
          <div class="sw-field">
            <label>Cantidad</label>
            <input id="sw-qty" type="number" min="1" value="1" class="swal2-input" />
          </div>
        </div>
        <div class="sw-actions">
          <button type="button" id="sw-add-btn" class="swal2-confirm swal2-styled">Agregar a lista</button>
        </div>
        <table class="sw-table" id="sw-items-table">
          <thead><tr><th>Elemento</th><th style="width:120px;">Cantidad</th></tr></thead>
          <tbody></tbody>
        </table>
      `,
      focusConfirm: false,
      showCancelButton: true,
      confirmButtonText: 'Guardar',
      didOpen: () => {
        const input = document.getElementById('sw-prod-input');
        const qtyEl = document.getElementById('sw-qty');
        const addBtn = document.getElementById('sw-add-btn');
        const tbody = document.querySelector('#sw-items-table tbody');
        let selected = null;

        const dd = document.createElement('ul');
        dd.id = 'sw-prod-dd';
        dd.className = 'sw-list';
        dd.hidden = true;
        document.body.appendChild(dd);

        function updateDDPos(){
          const r = input.getBoundingClientRect();
          const w = Math.min(r.width, 420);
          dd.style.left = r.left + 'px';
          dd.style.top = (r.bottom + 6) + 'px';
          dd.style.width = w + 'px';
        }
        function renderDD(list){
          dd.innerHTML = '';
          list.slice(0, 200).forEach(p => {
            const li = document.createElement('li');
            li.className = 'sw-item';
            li.textContent = `${p.sku} — ${p.name}`;
            li.addEventListener('click', () => { selected = p; input.value = `${p.sku} — ${p.name}`; dd.hidden = true; });
            dd.appendChild(li);
          });
          dd.hidden = list.length === 0;
          if (!dd.hidden) updateDDPos();
        }
        function filter(term){
          const t = term.trim().toLowerCase();
          if (!t) return all.slice();
          return all.filter(p => p.sku.toLowerCase().includes(t) || p.name.toLowerCase().includes(t));
        }
        function renderTable(){
          tbody.innerHTML = temp.map(it => `<tr><td>${escapeHtml(it.sku)} — ${escapeHtml(it.name)}</td><td style="text-align:center;">${it.cantidad}</td></tr>`).join('');
        }
        renderTable();

        input.addEventListener('input', () => { selected = null; renderDD(filter(input.value)); });
        input.addEventListener('focus', () => { selected = null; renderDD(all.slice()); });
        input.addEventListener('click', () => { selected = null; renderDD(all.slice()); });
        window.addEventListener('resize', updateDDPos);
        document.addEventListener('scroll', updateDDPos, true);
        document.addEventListener('click', (e) => { if (!dd.contains(e.target) && e.target !== input) dd.hidden = true; });

        addBtn.addEventListener('click', () => {
          const qty = parseInt(qtyEl.value || '0', 10);
          if (!selected) { Swal.showValidationMessage('Seleccione un producto de la lista'); return; }
          if (!qty || qty < 1) { Swal.showValidationMessage('Ingrese una cantidad válida'); return; }
          temp.push({ sku: selected.sku, name: selected.name, cantidad: qty });
          selected = null; input.value = ''; qtyEl.value = '1'; dd.hidden = true; renderTable();
          Swal.resetValidationMessage();
        });

        updateDDPos();
      },
      willClose: () => {
        const dd = document.getElementById('sw-prod-dd');
        if (dd) dd.remove();
        document.removeEventListener('scroll', ()=>{}, true);
      },
      preConfirm: () => {
        if (!temp.length) { Swal.showValidationMessage('Agregue al menos un producto a la lista'); return false; }
        return JSON.stringify(temp);
      }
    }).then(res => {
      if (!res.isConfirmed || !res.value) return;
      try { items.splice(0, items.length, ...JSON.parse(res.value)); } catch (e) {}
      render();
    });
  }

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
      if (itemsField) itemsField.value = JSON.stringify(items);
    });
  }

  addBtnTrigger && addBtnTrigger.addEventListener('click', openAddModal);
  setupCanvas();
})();
