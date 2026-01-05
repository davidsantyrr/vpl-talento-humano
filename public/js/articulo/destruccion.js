// ==================== MODAL DE DESTRUCCIÓN ====================
(function() {
  'use strict';

  // Función para abrir modal de destrucción
  window.abrirModalDestruccion = function(sku, nombreProducto, bodega, ubicacion, estatus, stock) {
    console.log('Abriendo modal de destrucción', {sku, nombreProducto, bodega, ubicacion, estatus, stock});
    
    const modal = document.getElementById('modalDestruccion');
    if (!modal) {
      console.error('Modal de destrucción no encontrado');
      alert('Error: Modal no encontrado. Por favor recargue la página.');
      return;
    }
    
    // Llenar datos visibles
    const elementos = {
      'destruccionNombreProducto': nombreProducto,
      'destruccionSku': sku,
      'destruccionBodega': bodega || '(sin asignar)',
      'destruccionUbicacion': ubicacion || '(sin asignar)',
      'destruccionEstatusActual': estatus,
      'destruccionStockActual': stock
    };

    for (const [id, valor] of Object.entries(elementos)) {
      const elemento = document.getElementById(id);
      if (elemento) {
        elemento.textContent = valor;
      } else {
        console.warn(`Elemento ${id} no encontrado`);
      }
    }
    
    // Llenar campos hidden
    const camposHidden = {
      'destruccionSkuHidden': sku,
      'destruccionBodegaHidden': bodega,
      'destruccionUbicacionHidden': ubicacion,
      'destruccionEstatusHidden': estatus
    };

    for (const [id, valor] of Object.entries(camposHidden)) {
      const campo = document.getElementById(id);
      if (campo) {
        campo.value = valor;
      } else {
        console.warn(`Campo hidden ${id} no encontrado`);
      }
    }
    
    // Configurar campo cantidad
    const cantidadInput = document.getElementById('destruccionCantidad');
    if (cantidadInput) {
      cantidadInput.max = stock;
      cantidadInput.value = stock;
    }
    
    // Limpiar archivo
    const archivoInput = document.getElementById('destruccionArchivo');
    if (archivoInput) {
      archivoInput.value = '';
    }
    
    // Mostrar modal
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
    
    console.log('Modal abierto exitosamente');
  };

  // Función para cerrar modal de destrucción
  window.cerrarModalDestruccion = function() {
    const modal = document.getElementById('modalDestruccion');
    if (modal) {
      modal.classList.remove('active');
      document.body.style.overflow = '';
      console.log('Modal cerrado');
    }
  };

  // Función para procesar destrucción
  window.procesarDestruccion = function(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    const archivo = formData.get('constancia');
    
    // Validaciones
    if (!archivo || archivo.size === 0) {
      Swal.fire({
        icon: 'error',
        title: 'Error',
        text: 'Debe cargar la constancia de destrucción (PDF)'
      });
      return;
    }
    
    if (archivo.type !== 'application/pdf') {
      Swal.fire({
        icon: 'error',
        title: 'Error',
        text: 'El archivo debe ser un PDF'
      });
      return;
    }

    // Validar tamaño (5MB)
    if (archivo.size > 5242880) {
      Swal.fire({
        icon: 'error',
        title: 'Error',
        text: 'El archivo no puede ser mayor a 5MB'
      });
      return;
    }
    
    // Mostrar loading
    Swal.fire({
      title: 'Procesando destrucción...',
      html: 'Por favor espere mientras se procesa la solicitud',
      allowOutsideClick: false,
      didOpen: () => {
        Swal.showLoading();
      }
    });
    
    // Enviar formulario
    fetch(form.action, {
      method: 'POST',
      body: formData,
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json'
      }
    })
    .then(response => {
      // Verificar si la respuesta es JSON válido
      const contentType = response.headers.get('content-type');
      if (!contentType || !contentType.includes('application/json')) {
        throw new Error('La respuesta del servidor no es JSON. Posible error de ruta o autenticación.');
      }
      
      if (!response.ok) {
        return response.json().then(data => {
          throw new Error(data.message || 'Error en la solicitud');
        });
      }
      
      return response.json();
    })
    .then(data => {
      Swal.close();
      
      if (data.success) {
        Swal.fire({
          icon: 'success',
          title: 'Éxito',
          text: data.message || 'Artículo destruido correctamente',
          timer: 2000,
          showConfirmButton: false
        });
        
        cerrarModalDestruccion();
        
        // Recargar página después de 2 segundos
        setTimeout(() => {
          location.reload();
        }, 2000);
      } else {
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: data.message || 'Error al procesar la destrucción'
        });
      }
    })
    .catch(error => {
      Swal.close();
      console.error('Error completo:', error);
      Swal.fire({
        icon: 'error',
        title: 'Error',
        text: error.message || 'Error al procesar la solicitud. Por favor intente nuevamente.',
        footer: 'Verifique la consola para más detalles'
      });
    });
  };

  // Inicializar cuando el DOM esté listo
  document.addEventListener('DOMContentLoaded', function() {
    console.log('Inicializando modal de destrucción...');
    
    // Event listener para botones de destruir
    const tabla = document.querySelector('.tabla-articulos tbody');
    if (tabla) {
      tabla.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-icon.delete');
        if (!btn) return;
        
        console.log('Click en botón delete detectado');
        
        const tr = btn.closest('tr');
        if (!tr) {
          console.error('No se encontró la fila (tr)');
          return;
        }
        
        // Extraer datos de la fila
        const sku = tr.dataset.sku;
        const bodega = tr.dataset.bodega || '';
        const ubicacion = tr.dataset.ubicacion || '';
        const estatus = tr.dataset.estatus || 'disponible';
        const stock = parseInt(tr.dataset.stock) || 0;
        const nombreProducto = tr.querySelector('td:nth-child(2)').textContent.trim();
        
        console.log('Datos extraídos de la fila:', {
          sku, nombreProducto, bodega, ubicacion, estatus, stock
        });
        
        // Abrir modal
        abrirModalDestruccion(sku, nombreProducto, bodega, ubicacion, estatus, stock);
      });
      
      console.log('Event listener añadido a la tabla');
    } else {
      console.error('Tabla de artículos no encontrada');
    }
    
    // Event listener para cerrar modal con backdrop
    const modal = document.getElementById('modalDestruccion');
    if (modal) {
      modal.addEventListener('click', function(e) {
        if (e.target === modal && modal.classList.contains('active')) {
          cerrarModalDestruccion();
        }
      });
      
      console.log('Event listener de modal backdrop añadido');
    }
    
    // Event listener para tecla Escape
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        const modal = document.getElementById('modalDestruccion');
        if (modal && modal.classList.contains('active')) {
          cerrarModalDestruccion();
        }
      }
    });
    
    console.log('Modal de destrucción inicializado completamente');
  });
})();
