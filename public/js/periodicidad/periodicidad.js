// =========================================
// GESTIÓN DE PERIODICIDADES - JavaScript
// =========================================

document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 periodicidad.js cargado');

    // =========================================
    // 1. MODAL AGREGAR - Sincronizar SKU
    // =========================================
    const selectProductoAdd = document.getElementById('selectProductoAdd');
    const skuHiddenAdd = document.getElementById('skuHiddenAdd');
    const searchProductoAdd = document.getElementById('searchProductoAdd');
    const formAdd = document.querySelector('#modalAddElemento form');

    console.log('🔍 Elementos encontrados (Add):', {
        selectProductoAdd: !!selectProductoAdd,
        skuHiddenAdd: !!skuHiddenAdd,
        searchProductoAdd: !!searchProductoAdd,
        formAdd: !!formAdd
    });

    if (selectProductoAdd && skuHiddenAdd) {
        selectProductoAdd.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const sku = selectedOption.getAttribute('data-sku');
            const nombre = selectedOption.value;
            
            skuHiddenAdd.value = sku || '';
            
            console.log('✅ Producto seleccionado (Add):', {
                nombre: nombre,
                sku: sku,
                campoOculto: skuHiddenAdd.value
            });
        });
        
        console.log('✅ Listener SKU (Add) registrado');
    } else {
        console.error('❌ Error: No se encontraron elementos select o hidden (Add)');
    }

    // Búsqueda en tiempo real para modal de agregar
    if (searchProductoAdd && selectProductoAdd) {
        searchProductoAdd.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const options = selectProductoAdd.options;

            for (let i = 0; i < options.length; i++) {
                const option = options[i];
                const text = option.textContent.toLowerCase();
                
                if (text.includes(searchTerm) || searchTerm === '') {
                    option.style.display = '';
                } else {
                    option.style.display = 'none';
                }
            }
        });
    }

    // Debug al enviar formulario de agregar
    if (formAdd) {
        formAdd.addEventListener('submit', function(e) {
            const formData = new FormData(formAdd);
            const dataObj = {};
            formData.forEach((value, key) => {
                dataObj[key] = value;
            });
            
            console.log('📤 Enviando formulario (Add):', dataObj);
            
            if (!dataObj.sku || dataObj.sku === '') {
                console.error('❌ ¡ADVERTENCIA! SKU está vacío:', {
                    nombre: dataObj.nombre,
                    sku: dataObj.sku,
                    campoOcultoValue: skuHiddenAdd ? skuHiddenAdd.value : 'N/A'
                });
            } else {
                console.log('✅ SKU presente en formulario:', dataObj.sku);
            }
        });
    }

    // =========================================
    // 2. MODAL EDITAR - Sincronizar SKU
    // =========================================
    const selectProductoEdit = document.getElementById('selectProductoEdit');
    const skuHiddenEdit = document.getElementById('skuHiddenEdit');
    const searchProductoEdit = document.getElementById('searchProductoEdit');

    if (selectProductoEdit && skuHiddenEdit) {
        selectProductoEdit.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const sku = selectedOption.getAttribute('data-sku');
            skuHiddenEdit.value = sku || '';
            console.log('✅ SKU actualizado (Edit):', sku);
        });
        
        console.log('✅ Listener SKU (Edit) registrado');
    }

    // Búsqueda en tiempo real para modal de editar
    if (searchProductoEdit && selectProductoEdit) {
        searchProductoEdit.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const options = selectProductoEdit.options;

            for (let i = 0; i < options.length; i++) {
                const option = options[i];
                const text = option.textContent.toLowerCase();
                
                if (text.includes(searchTerm) || searchTerm === '') {
                    option.style.display = '';
                } else {
                    option.style.display = 'none';
                }
            }
        });
    }

    // =========================================
    // 3. MANEJAR EDICIÓN
    // =========================================
    const btnEdits = document.querySelectorAll('.btn-edit');
    btnEdits.forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const nombre = this.getAttribute('data-nombre');
            const sku = this.getAttribute('data-sku');
            const periodicidad = this.getAttribute('data-periodicidad');
            const rojo = this.getAttribute('data-aviso_rojo');
            const amarillo = this.getAttribute('data-aviso_amarillo');
            const verde = this.getAttribute('data-aviso_verde');

            // Actualizar action del formulario
            const formEdit = document.getElementById('formEditElemento');
            const updateTpl = formEdit.getAttribute('data-update-template') || '';
            formEdit.setAttribute('action', updateTpl.replace('__ID__', id));
            formEdit.setAttribute('data-destroy', (formEdit.getAttribute('data-destroy-template') || '').replace('__ID__', id));

            // Seleccionar producto y actualizar SKU
            for (let i = 0; i < selectProductoEdit.options.length; i++) {
                if (selectProductoEdit.options[i].value === nombre) {
                    selectProductoEdit.selectedIndex = i;
                    
                    // Actualizar SKU oculto
                    const sku = selectProductoEdit.options[i].getAttribute('data-sku');
                    skuHiddenEdit.value = sku || '';
                    console.log('✅ Cargando edición:', { nombre, sku });
                    break;
                }
            }

            // Seleccionar periodicidad
            const periodicidadSelect = formEdit.querySelector('[name="periodicidad"]');
            periodicidadSelect.value = periodicidad;

            // Seleccionar avisos
            formEdit.querySelector('[name="aviso_rojo"]').value = rojo;
            formEdit.querySelector('[name="aviso_amarillo"]').value = amarillo;
            formEdit.querySelector('[name="aviso_verde"]').value = verde;
        });
    });

    // =========================================
    // 4. MANEJAR ELIMINACIÓN
    // =========================================
    const btnDeletes = document.querySelectorAll('.btn-delete');
    const formDelete = document.getElementById('formDeleteElemento');
    btnDeletes.forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            
            Swal.fire({
                title: '¿Estás seguro?',
                text: "Esta acción no se puede deshacer",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    const destroyTpl = (formEdit && formEdit.getAttribute('data-destroy-template')) || '';
                    const url = destroyTpl.replace('__ID__', id);
                    formDelete.setAttribute('action', url);
                    formDelete.submit();
                }
            });
        });
    });

    console.log('✅ periodicidad.js inicializado completamente');
});

// Inicialización de eventos para gestión de periodicidad
(function(){
  const editModal = document.getElementById('modalEditElemento');
  const formEdit = document.getElementById('formEditElemento');
  const selectProductoEdit = document.getElementById('selectProductoEdit');
  const skuHiddenEdit = document.getElementById('skuHiddenEdit');
  const searchProductoEdit = document.getElementById('searchProductoEdit');

  const addModal = document.getElementById('modalAddElemento');
  const selectProductoAdd = document.getElementById('selectProductoAdd');
  const skuHiddenAdd = document.getElementById('skuHiddenAdd');
  const searchProductoAdd = document.getElementById('searchProductoAdd');

  // Helper: filtrar opciones por texto
  function filterSelect(select, term){
    const t = (term || '').toLowerCase();
    Array.from(select.options).forEach(opt => {
      if(!opt.value) return; // mantener placeholder
      const text = (opt.text || '').toLowerCase();
      opt.hidden = t && !text.includes(t);
    });
  }

  // Vincular búsqueda en Add
  if (searchProductoAdd && selectProductoAdd) {
    searchProductoAdd.addEventListener('input', function(){
      filterSelect(selectProductoAdd, this.value);
    });
    selectProductoAdd.addEventListener('change', function(){
      const opt = this.selectedOptions[0];
      skuHiddenAdd.value = opt ? (opt.getAttribute('data-sku') || '') : '';
    });
  }

  // Vincular búsqueda en Edit
  if (searchProductoEdit && selectProductoEdit) {
    searchProductoEdit.addEventListener('input', function(){
      filterSelect(selectProductoEdit, this.value);
    });
    selectProductoEdit.addEventListener('change', function(){
      const opt = this.selectedOptions[0];
      skuHiddenEdit.value = opt ? (opt.getAttribute('data-sku') || '') : '';
    });
  }

  // Abrir modal de edición con datos y configurar acción dinámica
  document.querySelectorAll('.btn-edit').forEach(btn => {
    btn.addEventListener('click', function(){
      const id = this.getAttribute('data-id');
      const nombre = this.getAttribute('data-nombre');
      const sku = this.getAttribute('data-sku');
      const periodicidad = this.getAttribute('data-periodicidad');
      const rojo = this.getAttribute('data-aviso_rojo');
      const amarillo = this.getAttribute('data-aviso_amarillo');
      const verde = this.getAttribute('data-aviso_verde');

      // Seleccionar producto por nombre y colocar SKU oculto
      if (selectProductoEdit) {
        let foundIdx = 0;
        Array.from(selectProductoEdit.options).forEach((opt, idx) => {
          if (opt.value === nombre) {
            foundIdx = idx;
          }
        });
        selectProductoEdit.selectedIndex = foundIdx;
        const opt = selectProductoEdit.options[foundIdx];
        skuHiddenEdit.value = sku || (opt ? (opt.getAttribute('data-sku') || '') : '');
      }

      // Setear selects de periodicidad y avisos
      const periodicidadSelect = editModal.querySelector('select[name="periodicidad"]');
      const rojoSelect = editModal.querySelector('select[name="aviso_rojo"]');
      const amarilloSelect = editModal.querySelector('select[name="aviso_amarillo"]');
      const verdeSelect = editModal.querySelector('select[name="aviso_verde"]');
      if (periodicidadSelect) periodicidadSelect.value = periodicidad || '1_mes';
      if (rojoSelect) rojoSelect.value = rojo || '3';
      if (amarilloSelect) amarilloSelect.value = amarillo || '7';
      if (verdeSelect) verdeSelect.value = verde || '14';

      // Construir acción usando templates absolutas
      const updateTpl = formEdit.getAttribute('data-update-template') || '';
      formEdit.setAttribute('action', updateTpl.replace('__ID__', id));
      formEdit.setAttribute('data-destroy', (formEdit.getAttribute('data-destroy-template') || '').replace('__ID__', id));
    });
  });

  // Eliminar elemento con formulario oculto
  const formDelete = document.getElementById('formDeleteElemento');
  document.querySelectorAll('.btn-delete').forEach(btn => {
    btn.addEventListener('click', function(){
      const id = this.getAttribute('data-id');
      if (!formDelete) return;
      const destroyTpl = (formEdit && formEdit.getAttribute('data-destroy-template')) || '';
      const url = destroyTpl.replace('__ID__', id);
      formDelete.setAttribute('action', url);
      formDelete.submit();
    });
  });
})();