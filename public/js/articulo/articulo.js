(function(){
  const cfg = (window.ArticulosPageConfig || {});
  const statusMsg = cfg.statusMsg || null;
  const errorMsg = cfg.errorMsg || null;
  const perPage = Number(cfg.perPage || 20);
  const csrfToken = cfg.csrfToken || '';
  const baseUrl = cfg.articulosBaseUrl || '';
  const UBI_ALL = Array.isArray(cfg.ubicacionesAll) ? cfg.ubicacionesAll : [];

  function toast(icon, title, ms){
    if (!title) return;
    Swal.fire({ toast: true, position: 'top-end', icon, title, showConfirmButton: false, timer: ms || 2500, timerProgressBar: true });
  }

  // Requiere que la fila tenga bodega y ubicación antes de editar estatus/stock
  function requireLocation(row){
    const b = (row.dataset.bodega || '').trim();
    const u = (row.dataset.ubicacion || '').trim();
    const ok = (b !== '' && u !== '');
    if (!ok) {
      toast('warning', 'Debes ingresar la ubicación primero', 2600);
    }
    return ok;
  }

  function buildBodegaOptions(selected){
    const bodegas = [...new Set(UBI_ALL.map(u => u.bodega))];
    return bodegas.map(b => `<option value="${b}" ${selected===b?'selected':''}>${b}</option>`).join('');
  }
  function buildUbicacionOptions(bod, selected){
    const list = UBI_ALL.filter(u => !bod || u.bodega === bod);
    let html = `<option value="">Seleccione ubicación</option>`;
    html += list.map(u => `<option value="${u.ubicacion}" ${selected===u.ubicacion?'selected':''} data-bodega="${u.bodega}">${u.ubicacion}</option>`).join('');
    return html;
  }

  function openLocation(row){
    const sku = row.dataset.sku;
    const bodega = row.dataset.bodega || '';
    const ubicacion = row.dataset.ubicacion || '';

    // si ya tiene ubicación, pedir confirmación para agregar una nueva
    if (bodega.trim() !== '' || ubicacion.trim() !== '') {
      Swal.fire({
        icon: 'info',
        title: 'Añadir nueva ubicación',
        text: 'Este elemento ya tiene una ubicación. ¿Deseas agregar una nueva ubicación adicional para este elemento?',
        showCancelButton: true,
        confirmButtonText: 'Sí, agregar',
        cancelButtonText: 'Cancelar'
      }).then(dec => { if (dec.isConfirmed) { showLocationModal(row, 'new'); } });
      return;
    }
    showLocationModal(row, 'update');
  }

  function showLocationModal(row, mode){
    const sku = row.dataset.sku;
    const bodega = row.dataset.bodega || '';
    const ubicacion = row.dataset.ubicacion || '';

    Swal.fire({
      title: `Ubicación (${sku})`,
      html: `
        <div class="modal-grid">
          <div class="field">
            <label>Bodega</label>
            <select id="loc-bodega" class="sw-select">
              <option value="">Seleccione bodega</option>
              ${buildBodegaOptions(bodega)}
            </select>
          </div>
          <div class="field">
            <label>Ubicación</label>
            <select id="loc-ubicacion" class="sw-select">
              ${buildUbicacionOptions(bodega, '')}
            </select>
          </div>
        </div>
      `,
      focusConfirm: false,
      customClass: { popup: 'sw-popup' },
      showCancelButton: true,
      confirmButtonText: 'Guardar',
      didOpen: () => {
        const bodSel = document.getElementById('loc-bodega');
        const ubiSel = document.getElementById('loc-ubicacion');
        bodSel.addEventListener('change', function(){
          const b = bodSel.value || '';
          ubiSel.innerHTML = buildUbicacionOptions(b, '');
          const realOpts = Array.from(ubiSel.options).filter(o => o.value);
          if (realOpts.length === 1) { ubiSel.value = realOpts[0].value; }
        });
        ubiSel.addEventListener('change', function(){
          const opt = ubiSel.options[ubiSel.selectedIndex];
          const dbod = opt && opt.dataset ? opt.dataset.bodega : '';
          if (ubiSel.value && dbod) { bodSel.value = dbod; }
          else if (!ubiSel.value) { bodSel.value = ''; }
        });
      },
      preConfirm: () => {
        const bod = document.getElementById('loc-bodega').value;
        const ubi = document.getElementById('loc-ubicacion').value;
        if (!bod || !ubi) { Swal.showValidationMessage('Selecciona bodega y ubicación'); return false; }
        return { bodega: bod, ubicacion: ubi };
      }
    }).then(res => {
      if (!res.isConfirmed || !res.value) return;
      const form = document.createElement('form');
      form.method = 'POST';
      form.action = `${baseUrl}/${sku}`;
      const csrf = document.createElement('input'); csrf.type = 'hidden'; csrf.name = '_token'; csrf.value = csrfToken;
      const per = document.createElement('input'); per.type = 'hidden'; per.name = 'per_page'; per.value = String(perPage);
      const b = document.createElement('input'); b.type = 'hidden'; b.name = 'bodega'; b.value = res.value.bodega || '';
      const u = document.createElement('input'); u.type = 'hidden'; u.name = 'ubicacion'; u.value = res.value.ubicacion || '';
      const e = document.createElement('input'); e.type = 'hidden'; e.name = 'estatus'; e.value = row.dataset.estatus || 'disponible';
      const s = document.createElement('input'); s.type = 'hidden'; s.name = 'stock'; s.value = mode === 'new' ? '0' : String(row.dataset.stock || 0);
      form.appendChild(csrf); form.appendChild(per); form.appendChild(b); form.appendChild(u); form.appendChild(e); form.appendChild(s);
      if (mode === 'new') { const nl = document.createElement('input'); nl.type = 'hidden'; nl.name = 'new_location'; nl.value = '1'; form.appendChild(nl); }
      document.body.appendChild(form);
      form.submit();
    });
  }

  function openEditor(row){
    if (!requireLocation(row)) return;
    const sku = row.dataset.sku;
    const currentStatus = (row.dataset.estatus || 'disponible');
    let estatus = currentStatus;
    let stock = Number(row.dataset.stock || 0);

    Swal.fire({
      title: `Editar (${sku})`,
      html: `
        <div class="modal-grid">
          <div class="field">
            <label>Estatus</label>
            <select id="sw-estatus" class="sw-select">
              <option value="disponible" ${estatus==='disponible'?'selected':''}>Disponible</option>
              <option value="perdido" ${estatus==='perdido'?'selected':''}>Perdido</option>
              <option value="prestado" ${estatus==='prestado'?'selected':''}>Prestado</option>
            </select>
          </div>
          <div class="field">
            <label id="sw-stock-label">Stock</label>
            <input id="sw-stock" type="number" min="0" value="${stock}" class="sw-input" />
            <small id="sw-hint" class="text-muted"></small>
          </div>
        </div>
      `,
      focusConfirm: false,
      customClass: { popup: 'sw-popup' },
      showCancelButton: true,
      confirmButtonText: 'Guardar',
      didOpen: () => {
        const sel = document.getElementById('sw-estatus');
        const label = document.getElementById('sw-stock-label');
        const hint = document.getElementById('sw-hint');
        const input = document.getElementById('sw-stock');
        function updateHint(){
          const target = sel.value;
          if (target !== currentStatus) {
            label.textContent = 'Cantidad a transferir';
            hint.textContent = `Se descontará de "${currentStatus}" y se sumará a "${target}".`;
            input.value = '';
          } else {
            label.textContent = 'Stock';
            hint.textContent = '';
            input.value = stock;
          }
        }
        sel.addEventListener('change', updateHint);
        updateHint();
      },
      preConfirm: () => {
        const target = document.getElementById('sw-estatus').value;
        const qtyStr = document.getElementById('sw-stock').value;
        const qty = Number(qtyStr);
        if (qtyStr === '' || qty < 0) {
          Swal.showValidationMessage('Cantidad inválida');
          return false;
        }
        if (target !== currentStatus && qty > stock) {
          Swal.showValidationMessage('No puedes mover más de lo disponible en este estatus');
          Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: 'Cantidad supera el stock del estatus actual', showConfirmButton: false, timer: 2500 });
          return false;
        }
        return { targetStatus: target, qty };
      }
    }).then(res => {
      if (!res.isConfirmed || !res.value) return;
      const form = document.createElement('form');
      form.method = 'POST';
      form.action = `${baseUrl}/${sku}`;
      const csrf = document.createElement('input'); csrf.type = 'hidden'; csrf.name = '_token'; csrf.value = csrfToken;
      const per = document.createElement('input'); per.type = 'hidden'; per.name = 'per_page'; per.value = String(perPage);
      const b = document.createElement('input'); b.type = 'hidden'; b.name = 'bodega'; b.value = row.dataset.bodega || '';
      const u = document.createElement('input'); u.type = 'hidden'; u.name = 'ubicacion'; u.value = row.dataset.ubicacion || '';
      const e = document.createElement('input'); e.type = 'hidden'; e.name = 'estatus'; e.value = res.value.targetStatus || currentStatus;
      const s = document.createElement('input'); s.type = 'hidden'; s.name = 'stock'; s.value = String(res.value.qty || 0);
      form.appendChild(csrf); form.appendChild(per); form.appendChild(b); form.appendChild(u); form.appendChild(e); form.appendChild(s);
      if (res.value.targetStatus !== currentStatus) { const f = document.createElement('input'); f.type = 'hidden'; f.name = 'from_status'; f.value = currentStatus; form.appendChild(f); }
      document.body.appendChild(form);
      form.submit();
    });
  }

  function openDestroy(row){
    const sku = row.dataset.sku;
    const currentStatus = (row.dataset.estatus || 'disponible');
    const stock = Number(row.dataset.stock || 0);
    Swal.fire({
      title: `Destruir (${sku})`,
      html: `
        <div class="modal-grid center">
          <div class="field">
            <label>Cantidad a destruir</label>
            <input id="sw-destroy" type="number" min="1" max="${stock}" class="sw-input" />
            <small class="text-muted">Se descontará de "${currentStatus}" y pasará a estatus Destruido.</small>
          </div>
        </div>
      `,
      focusConfirm: false,
      customClass: { popup: 'sw-popup' },
      showCancelButton: true,
      confirmButtonText: 'Destruir',
      preConfirm: () => {
        const qtyStr = document.getElementById('sw-destroy').value;
        const qty = Number(qtyStr);
        if (qtyStr === '' || qty <= 0) { Swal.showValidationMessage('Ingrese una cantidad válida'); return false; }
        if (qty > stock) { Swal.showValidationMessage('No puedes destruir más de lo disponible en este estatus'); return false; }
        return { qty };
      }
    }).then(res => {
      if (!res.isConfirmed || !res.value) return;
      const form = document.createElement('form');
      form.method = 'POST';
      form.action = `${baseUrl}/${sku}`;
      const csrf = document.createElement('input'); csrf.type = 'hidden'; csrf.name = '_token'; csrf.value = csrfToken;
      const per = document.createElement('input'); per.type = 'hidden'; per.name = 'per_page'; per.value = String(perPage);
      const b = document.createElement('input'); b.type = 'hidden'; b.name = 'bodega'; b.value = row.dataset.bodega || '';
      const u = document.createElement('input'); u.type = 'hidden'; u.name = 'ubicacion'; u.value = row.dataset.ubicacion || '';
      const e = document.createElement('input'); e.type = 'hidden'; e.name = 'estatus'; e.value = 'destruido';
      const s = document.createElement('input'); s.type = 'hidden'; s.name = 'stock'; s.value = String(res.value.qty || 0);
      const f = document.createElement('input'); f.type = 'hidden'; f.name = 'from_status'; f.value = currentStatus;
      form.appendChild(csrf); form.appendChild(per); form.appendChild(b); form.appendChild(u); form.appendChild(e); form.appendChild(s); form.appendChild(f);
      document.body.appendChild(form);
      form.submit();
    });
  }

  function bindButtons(){
    document.querySelectorAll('.btn-icon.location').forEach(function(btn){
      const row = btn.closest('tr'); if(!row) return; btn.addEventListener('click', function(){ openLocation(row); });
    });
    document.querySelectorAll('.btn-icon.edit').forEach(function(btn){
      const row = btn.closest('tr'); if(!row) return; btn.addEventListener('click', function(){ openEditor(row); });
    });
    document.querySelectorAll('.btn-icon.delete').forEach(function(btn){
      const row = btn.closest('tr'); if(!row) return; btn.addEventListener('click', function(){ openDestroy(row); });
    });
  }

  function setupTabs(){
    const tabs = document.querySelectorAll('.tab-btn');
    const rows = document.querySelectorAll('.tabla-articulos tbody tr');
    function applyFilter(st) {
      rows.forEach(tr => {
        const est = (tr.dataset.estatus || '').toLowerCase();
        tr.style.display = (est === st) ? '' : 'none';
      });
    }
    tabs.forEach(btn => {
      btn.addEventListener('click', () => {
        tabs.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        applyFilter(btn.dataset.status);
      });
    });
    applyFilter('disponible');
  }

  function init(){
    if (statusMsg) toast('success', statusMsg, 2500);
    if (errorMsg) toast('error', errorMsg, 3000);
    bindButtons();
    setupTabs();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else { init(); }
})();
