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
        });