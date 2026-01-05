document.addEventListener('DOMContentLoaded', function () {
	// Handle delete confirmations for forms that use @method('DELETE')
	const deleteForms = Array.from(document.querySelectorAll('form')).filter(f => {
		const m = f.querySelector('input[name="_method"]');
		return m && m.value && m.value.toUpperCase() === 'DELETE';
	});

	deleteForms.forEach(form => {
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			if (typeof Swal === 'undefined') {
				if (confirm('Eliminar operación?')) {
					form.submit();
				}
				return;
			}

			Swal.fire({
				title: '¿Eliminar operación?',
				text: 'Esta acción no se puede deshacer.',
				icon: 'warning',
				showCancelButton: true,
				confirmButtonColor: '#d33',
				cancelButtonColor: '#3085d6',
				confirmButtonText: 'Sí, eliminar',
				cancelButtonText: 'Cancelar'
			}).then((result) => {
				if (result.isConfirmed) {
					form.submit();
				}
			});
		});
	});

	// Show toast for server-side flash message (session('success'))
	const alertEl = document.querySelector('.alert-success');
	if (alertEl) {
		const msg = alertEl.textContent.trim();
		if (typeof Swal === 'undefined') {
			alert(msg);
		} else {
			Swal.fire({
				toast: true,
				position: 'top-end',
				icon: 'success',
				title: msg,
				showConfirmButton: false,
				timer: 3000,
				timerProgressBar: true
			});
		}
		alertEl.remove();
	}
});

