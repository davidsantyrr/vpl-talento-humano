// filepath: c:\laragon\www\vpl-talento-humano\public\js\historial\historial.js
(function() {
	'use strict';

	// Toast global
	const Toast = Swal.mixin({
		toast: true,
		position: 'top-end',
		showConfirmButton: false,
		timer: 3000,
		timerProgressBar: true,
	});

	let registroActual = null;

	// ==================== MODAL DETALLE ====================
	window.verDetalle = function(registro) {
		registroActual = registro;
		const modal = document.getElementById('modalDetalle');
		
		if (!modal) {
			console.error('Modal de detalle no encontrado');
			return;
		}
		
		// Llenar datos básicos
		document.getElementById('modalTitulo').textContent = `Detalle de ${registro.registro_tipo === 'entrega' ? 'Entrega' : 'Recepción'} #${registro.id}`;
		document.getElementById('detalleTipo').innerHTML = `<span class="badge ${registro.registro_tipo === 'entrega' ? 'badge-entrega' : 'badge-recepcion'}">${registro.registro_tipo === 'entrega' ? 'Entrega' : 'Recepción'}</span>`;
		document.getElementById('detalleSubtipo').innerHTML = `<span class="badge badge-tipo">${capitalizeFirst(registro.tipo || '-')}</span>`;
		document.getElementById('detalleFecha').textContent = new Date(registro.created_at).toLocaleString('es-CO');
		
		// Estado según tipo
		let estadoHTML = '';
		if (registro.registro_tipo === 'entrega') {
			if (['periodica', 'primera vez'].includes(registro.tipo)) {
				estadoHTML = '<span class="badge badge-success">Completado</span>';
			} else {
				estadoHTML = registro.recibido 
					? '<span class="badge badge-success">Recibido</span>'
					: '<span class="badge badge-warning">Pendiente</span>';
			}
		} else {
			estadoHTML = registro.recibido 
				? '<span class="badge badge-success">Entregado</span>'
				: '<span class="badge badge-warning">Pendiente</span>';
		}
		document.getElementById('detalleEstado').innerHTML = estadoHTML;
		
		document.getElementById('detalleTipoDoc').textContent = registro.tipo_documento || '-';
		document.getElementById('detalleNumDoc').textContent = registro.numero_documento || '-';
		document.getElementById('detalleNombre').textContent = `${registro.nombres || ''} ${registro.apellidos || ''}`.trim() || '-';
		document.getElementById('detalleOperacion').textContent = registro.operacion || '-';
		
		// Cargar elementos
		cargarElementos(registro.registro_tipo, registro.id);
		
		// Mostrar modal
		modal.classList.add('active');
		document.body.style.overflow = 'hidden';
	};

	function cargarElementos(tipo, id) {
		const tbody = document.getElementById('detalleElementosTbody');
		tbody.innerHTML = '<tr><td colspan="2" style="text-align:center;padding:20px;">Cargando elementos...</td></tr>';
		
		try {
			if (registroActual && registroActual.elementos && Array.isArray(registroActual.elementos)) {
				if (registroActual.elementos.length === 0) {
					tbody.innerHTML = '<tr><td colspan="2" style="text-align:center;padding:20px;color:#6c757d;">No hay elementos registrados</td></tr>';
				} else {
					tbody.innerHTML = registroActual.elementos.map(e => `
						<tr>
							<td>${escapeHtml(e.sku || '-')}</td>
							<td style="text-align:center;">${escapeHtml(e.cantidad || 0)}</td>
						</tr>
					`).join('');
				}
			} else {
				tbody.innerHTML = '<tr><td colspan="2" style="text-align:center;padding:20px;color:#6c757d;">No se pudieron cargar los elementos</td></tr>';
			}
		} catch (error) {
			console.error('Error cargando elementos:', error);
			tbody.innerHTML = '<tr><td colspan="2" style="text-align:center;padding:20px;color:#dc2626;">Error al cargar elementos</td></tr>';
		}
	}

	window.cerrarModalDetalle = function() {
		const modal = document.getElementById('modalDetalle');
		if (modal) {
			modal.classList.remove('active');
			document.body.style.overflow = '';
			registroActual = null;
		}
	};

	window.descargarPDFDesdeModal = function() {
		if (registroActual) {
			descargarPDF(registroActual.registro_tipo, registroActual.id);
		}
	};

	window.descargarPDF = function(tipo, id) {
		Toast.fire({
			icon: 'info',
			title: 'Generando PDF...'
		});
		
		// Construir URL con parámetros
		const url = `/historial/pdf?tipo=${tipo}&id=${id}`;
		
		// Descargar archivo
		window.location.href = url;
	};

	// ==================== DESCARGA MASIVA ====================
	window.abrirModalDescargaMasiva = function() {
		const modal = document.getElementById('modalDescargaMasiva');
		if (!modal) {
			console.error('Modal de descarga masiva no encontrado');
			return;
		}

		const today = new Date().toISOString().split('T')[0];
		const firstDay = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0];
		
		// Pre-llenar fechas (primer día del mes a hoy)
		const fechaInicio = document.getElementById('descargaFechaInicio');
		const fechaFin = document.getElementById('descargaFechaFin');
		
		if (fechaInicio) fechaInicio.value = firstDay;
		if (fechaFin) fechaFin.value = today;
		
		modal.classList.add('active');
		document.body.style.overflow = 'hidden';
	};

	window.cerrarModalDescargaMasiva = function() {
		const modal = document.getElementById('modalDescargaMasiva');
		if (modal) {
			modal.classList.remove('active');
			document.body.style.overflow = '';
			const form = document.getElementById('formDescargaMasiva');
			if (form) form.reset();
		}
	};

	window.procesarDescargaMasiva = function(event) {
		event.preventDefault();
		
		const form = event.target;
		const formData = new FormData(form);
		
		const tipoRegistro = formData.get('tipo_registro');
		const operacionId = formData.get('operacion_id');
		const fechaInicio = formData.get('fecha_inicio');
		const fechaFin = formData.get('fecha_fin');
		
		// Validar que las fechas estén presentes
		if (!fechaInicio || !fechaFin) {
			Toast.fire({
				icon: 'error',
				title: 'Por favor complete todas las fechas requeridas'
			});
			return;
		}
		
		// Validar que las fechas no superen la fecha actual
		const hoy = new Date();
		hoy.setHours(0, 0, 0, 0);
		
		const fechaInicioDate = new Date(fechaInicio);
		const fechaFinDate = new Date(fechaFin);
		
		if (fechaInicioDate > hoy) {
			Toast.fire({
				icon: 'error',
				title: 'La fecha de inicio no puede ser mayor a la fecha actual'
			});
			return;
		}
		
		if (fechaFinDate > hoy) {
			Toast.fire({
				icon: 'error',
				title: 'La fecha fin no puede ser mayor a la fecha actual'
			});
			return;
		}
		
		// Validar que fecha inicio no sea mayor a fecha fin
		if (fechaInicioDate > fechaFinDate) {
			Toast.fire({
				icon: 'error',
				title: 'La fecha de inicio no puede ser mayor a la fecha fin'
			});
			return;
		}
		
		// Validar que el rango no sea mayor a 1 año
		const unAnio = 365 * 24 * 60 * 60 * 1000; // milisegundos en un año
		if (fechaFinDate - fechaInicioDate > unAnio) {
			Toast.fire({
				icon: 'warning',
				title: 'El rango de fechas no puede ser mayor a 1 año',
				text: 'Por favor seleccione un rango menor'
			});
			return;
		}
		
		// Construir URL con parámetros
		const params = new URLSearchParams({
			tipo_registro: tipoRegistro,
			fecha_inicio: fechaInicio,
			fecha_fin: fechaFin
		});
		
		if (operacionId) {
			params.append('operacion_id', operacionId);
		}
		
		// Mostrar loading
		Swal.fire({
			title: 'Generando archivos PDF...',
			html: 'Por favor espere mientras se genera el documento ZIP con todos los registros',
			allowOutsideClick: false,
			didOpen: () => {
				Swal.showLoading();
			}
		});
		
		// Construir URL con parámetros
		const url = `/historial/pdf-masivo?${params.toString()}`;
		
		// Descargar archivo
		window.location.href = url;
		
		// Cerrar loading después de un delay
		setTimeout(() => {
			Swal.close();
			Toast.fire({
				icon: 'success',
				title: 'Descarga iniciada'
			});
			cerrarModalDescargaMasiva();
		}, 2000);
	};

	// ==================== UTILIDADES ====================
	function capitalizeFirst(str) {
		if (!str) return '';
		return str.charAt(0).toUpperCase() + str.slice(1);
	}

	function escapeHtml(text) {
		const div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
	}

	// ==================== EVENT LISTENERS ====================
	document.addEventListener('DOMContentLoaded', function() {
		// Cerrar modales al hacer clic en el backdrop
		document.addEventListener('click', function(e) {
			if (e.target.classList.contains('modal') && e.target.classList.contains('active')) {
				if (e.target.id === 'modalDetalle') {
					cerrarModalDetalle();
				} else if (e.target.id === 'modalDescargaMasiva') {
					cerrarModalDescargaMasiva();
				}
			}
		});

		// Prevenir cierre al hacer clic dentro del contenido de la modal
		document.querySelectorAll('.modal-content').forEach(content => {
			content.addEventListener('click', function(e) {
				e.stopPropagation();
			});
		});

		// Cerrar modales con tecla Escape
		document.addEventListener('keydown', function(e) {
			if (e.key === 'Escape') {
				const modalDetalle = document.getElementById('modalDetalle');
				const modalDescarga = document.getElementById('modalDescargaMasiva');
				
				if (modalDetalle && modalDetalle.classList.contains('active')) {
					cerrarModalDetalle();
				}
				if (modalDescarga && modalDescarga.classList.contains('active')) {
					cerrarModalDescargaMasiva();
				}
			}
		});

		// Validación de fechas en tiempo real
		const fechaInicio = document.getElementById('descargaFechaInicio');
		const fechaFin = document.getElementById('descargaFechaFin');
		
		if (fechaInicio) {
			fechaInicio.addEventListener('change', function() {
				const hoy = new Date().toISOString().split('T')[0];
				
				// Validar que no supere la fecha actual
				if (this.value > hoy) {
					Toast.fire({
						icon: 'error',
						title: 'La fecha no puede ser mayor a hoy'
					});
					this.value = hoy;
				}
				
				// Si hay fecha fin seleccionada, validar que inicio no sea mayor
				if (fechaFin && fechaFin.value && this.value > fechaFin.value) {
					Toast.fire({
						icon: 'error',
						title: 'La fecha de inicio no puede ser mayor a la fecha fin'
					});
					this.value = '';
				}
			});
		}
		
		if (fechaFin) {
			fechaFin.addEventListener('change', function() {
				const hoy = new Date().toISOString().split('T')[0];
				
				// Validar que no supere la fecha actual
				if (this.value > hoy) {
					Toast.fire({
						icon: 'error',
						title: 'La fecha no puede ser mayor a hoy'
					});
					this.value = hoy;
				}
				
				// Si hay fecha inicio seleccionada, validar que fin no sea menor
				if (fechaInicio && fechaInicio.value && this.value < fechaInicio.value) {
					Toast.fire({
						icon: 'error',
						title: 'La fecha fin no puede ser menor a la fecha de inicio'
					});
					this.value = '';
				}
			});
		}
	});

	// Exponer Toast globalmente para usarlo desde la vista
	window.HistorialToast = Toast;

})();
