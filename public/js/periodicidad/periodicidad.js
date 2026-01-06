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
            const periodicidad = this.getAttribute('data-periodicidad');
            const avisoRojo = this.getAttribute('data-aviso_rojo');
            const avisoAmarillo = this.getAttribute('data-aviso_amarillo');
            const avisoVerde = this.getAttribute('data-aviso_verde');

            // Actualizar action del formulario
            const formEdit = document.getElementById('formEditElemento');
            formEdit.action = `/gestiones/gestionPeriodicidad/${id}`;

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
            formEdit.querySelector('[name="aviso_rojo"]').value = avisoRojo;
            formEdit.querySelector('[name="aviso_amarillo"]').value = avisoAmarillo;
            formEdit.querySelector('[name="aviso_verde"]').value = avisoVerde;
        });
    });

    // =========================================
    // 4. MANEJAR ELIMINACIÓN
    // =========================================
    const btnDeletes = document.querySelectorAll('.btn-delete');
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
                    const formDelete = document.getElementById('formDeleteElemento');
                    formDelete.action = `/gestiones/gestionPeriodicidad/${id}`;
                    formDelete.submit();
                }
            });
        });
    });

    console.log('✅ periodicidad.js inicializado completamente');
});