    document.addEventListener('DOMContentLoaded', function(){
            const btnSaveAll = document.getElementById('btnSaveAll');
            if(btnSaveAll){
                btnSaveAll.addEventListener('click', function(e){
                    e.preventDefault();
                    const form = document.getElementById('formSaveAll');
                    if(form) form.submit();
                });
            }
            // Edit button: populate edit modal
            document.querySelectorAll('.btn-edit').forEach(function(btn){
                btn.addEventListener('click', function(){
                    const id = this.dataset.id;
                    const nombre = this.dataset.nombre || '';
                    const periodicidad = this.dataset.periodicidad || '';
                    const aviso_rojo = this.dataset.aviso_rojo || '';
                    const aviso_amarillo = this.dataset.aviso_amarillo || '';
                    const aviso_verde = this.dataset.aviso_verde || '';

                    const form = document.getElementById('formEditElemento');
                    if(!form) return;
                    form.action = '/gestionPeriodicidad/' + id;
                    // mark which id is being edited
                    form.dataset.editingId = id;
                    // Enable selects in the table row so user can edit inline if desired
                    const row = document.querySelector('tr[data-id="' + id + '"]');
                    if(row){
                        row.querySelectorAll('select').forEach(function(s){ s.removeAttribute('disabled'); });
                    }
                    form.querySelector('[name="nombre"]').value = nombre;
                    // Si existe search input en edit modal, sincronizar su texto
                    var selEdit = document.getElementById('selectProductoEdit');
                    var searchEdit = document.getElementById('searchProductoEdit');
                    if(selEdit && searchEdit){
                        // intentar encontrar opción que tenga el texto o value igual
                        var found = Array.from(selEdit.options).find(function(o){ return o.value === nombre || o.text.includes(nombre); });
                        if(found){
                            selEdit.value = found.value;
                            searchEdit.value = found.text;
                        } else {
                            searchEdit.value = nombre;
                        }
                    }
                    form.querySelector('[name="periodicidad"]').value = periodicidad;
                    form.querySelector('[name="aviso_rojo"]').value = aviso_rojo;
                    form.querySelector('[name="aviso_amarillo"]').value = aviso_amarillo;
                    form.querySelector('[name="aviso_verde"]').value = aviso_verde;
                });
            });
            // Delete button: use hidden form to avoid nested forms
            document.querySelectorAll('.btn-delete').forEach(function(btn){
                btn.addEventListener('click', function(){
                    const id = this.dataset.id;
                    const delForm = document.getElementById('formDeleteElemento');
                    if(!delForm) return;
                    delForm.action = '/gestionPeriodicidad/' + id;
                    delForm.submit();
                });
            });
            // When the edit modal is closed, re-disable the selects of the edited row
            var editModalEl = document.getElementById('modalEditElemento');
            if(editModalEl){
                editModalEl.addEventListener('hidden.bs.modal', function(){
                    var form = document.getElementById('formEditElemento');
                    if(!form) return;
                    var id = form.dataset.editingId;
                    if(id){
                        var row = document.querySelector('tr[data-id="' + id + '"]');
                        if(row){
                            row.querySelectorAll('select').forEach(function(s){ s.setAttribute('disabled',''); });
                        }
                        form.dataset.editingId = '';
                    }
                });
            }

            // Filtrado de productos en modal Add
            var searchAdd = document.getElementById('searchProductoAdd');
            var selectAdd = document.getElementById('selectProductoAdd');
            if(searchAdd && selectAdd){
                searchAdd.addEventListener('input', function(){
                    var q = this.value.trim().toLowerCase();
                    var anyVisible = 0;
                    Array.from(selectAdd.options).forEach(function(opt){
                        if(!opt.value) { opt.hidden = false; return; }
                        var txt = (opt.text || '').toLowerCase();
                        if(q === '' || txt.indexOf(q) !== -1){ opt.hidden = false; anyVisible++; } else { opt.hidden = true; }
                    });
                    // si queda una sola opción visible, selecciónala
                    if(anyVisible === 1){
                        var one = Array.from(selectAdd.options).find(function(o){ return !o.hidden && o.value; });
                        if(one) selectAdd.value = one.value;
                    }
                });
                // cuando seleccionan en el select, actualizar el input de búsqueda
                selectAdd.addEventListener('change', function(){
                    var o = selectAdd.selectedOptions[0];
                    if(o) searchAdd.value = o.text;
                });
            }

            // Filtrado de productos en modal Edit
            var searchEdit = document.getElementById('searchProductoEdit');
            var selectEdit = document.getElementById('selectProductoEdit');
            if(searchEdit && selectEdit){
                searchEdit.addEventListener('input', function(){
                    var q = this.value.trim().toLowerCase();
                    var anyVisible = 0;
                    Array.from(selectEdit.options).forEach(function(opt){
                        if(!opt.value) { opt.hidden = false; return; }
                        var txt = (opt.text || '').toLowerCase();
                        if(q === '' || txt.indexOf(q) !== -1){ opt.hidden = false; anyVisible++; } else { opt.hidden = true; }
                    });
                    if(anyVisible === 1){
                        var one = Array.from(selectEdit.options).find(function(o){ return !o.hidden && o.value; });
                        if(one) selectEdit.value = one.value;
                    }
                });
                selectEdit.addEventListener('change', function(){
                    var o = selectEdit.selectedOptions[0];
                    if(o) searchEdit.value = o.text;
                });
            }
        });