// ==================== MODAL DE CONSTANCIAS ====================
(function() {
  'use strict';

  // Función para abrir modal de constancias
  window.abrirModalConstancias = function(sku, nombreProducto) {
    console.log('Abriendo modal de constancias para SKU:', sku);
    
    const modal = document.getElementById('modalVerConstancias');
    if (!modal) {
      console.error('Modal de constancias no encontrado');
      return;
    }
    
    // Llenar información del producto
    document.getElementById('constanciasSku').textContent = sku;
    document.getElementById('constanciasProducto').textContent = nombreProducto;
    
    // Mostrar loading
    const lista = document.getElementById('constanciasLista');
    lista.innerHTML = '<p style="text-align: center; color: #999; padding: 2rem;">Cargando constancias...</p>';
    
    // Mostrar modal
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
    
    // Cargar constancias
    fetch(`/articulos/constancias/${sku}`)
      .then(response => response.json())
      .then(data => {
        if (data.success && data.constancias.length > 0) {
          mostrarConstancias(data.constancias);
        } else {
          lista.innerHTML = `
            <div style="text-align: center; padding: 2rem;">
              <svg width="48" height="48" fill="currentColor" viewBox="0 0 16 16" style="color: #9ca3af; margin-bottom: 1rem;">
                <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
                <path d="M7.002 11a1 1 0 1 1 2 0 1 1 0 0 1-2 0zM7.1 4.995a.905.905 0 1 1 1.8 0l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 4.995z"/>
              </svg>
              <p style="color: #6b7280;">No se encontraron constancias de destrucción para este artículo.</p>
            </div>
          `;
        }
      })
      .catch(error => {
        console.error('Error cargando constancias:', error);
        lista.innerHTML = `
          <div style="text-align: center; padding: 2rem;">
            <p style="color: #dc2626;">Error al cargar las constancias. Por favor intente nuevamente.</p>
          </div>
        `;
      });
  };

  // Función para cerrar modal de constancias
  window.cerrarModalConstancias = function() {
    const modal = document.getElementById('modalVerConstancias');
    if (modal) {
      modal.classList.remove('active');
      document.body.style.overflow = '';
    }
  };

  // Función para mostrar lista de constancias
  function mostrarConstancias(constancias) {
    const lista = document.getElementById('constanciasLista');
    
    let html = '';
    constancias.forEach(constancia => {
      html += `
        <div class="constancia-item" onclick="descargarConstancia('${constancia.url_descarga}', '${constancia.archivo}')">
          <div class="constancia-info">
            <div class="constancia-titulo">
              <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                <path d="M14 14V4.5L9.5 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2zM9.5 3A1.5 1.5 0 0 0 11 4.5h2V14a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h5.5v2z"/>
                <path d="M4.603 14.087a.81.81 0 0 1-.438-.42c-.195-.388-.13-.776.08-1.102.198-.307.526-.568.897-.787a7.68 7.68 0 0 1 1.482-.645 19.697 19.697 0 0 0 1.062-2.227 7.269 7.269 0 0 1-.43-1.295c-.086-.4-.119-.796-.046-1.136.075-.354.274-.672.65-.823.192-.077.4-.12.602-.077a.7.7 0 0 1 .477.365c.088.164.12.356.127.538.007.188-.012.396-.047.614-.084.51-.27 1.134-.52 1.794a10.954 10.954 0 0 0 .98 1.686 5.753 5.753 0 0 1 1.334.05c.364.066.734.195.96.465.12.144.193.32.2.518.007.192-.047.382-.138.563a1.04 1.04 0 0 1-.354.416.856.856 0 0 1-.51.138c-.331-.014-.654-.196-.933-.417a5.712 5.712 0 0 1-.911-.95 11.651 11.651 0 0 0-1.997.406 11.307 11.307 0 0 1-1.02 1.51c-.292.35-.609.656-.927.787a.793.793 0 0 1-.58.029zm1.379-1.901c-.166.076-.32.156-.459.238-.328.194-.541.383-.647.547-.094.145-.096.25-.04.361.01.022.02.036.026.044a.266.266 0 0 0 .035-.012c.137-.056.355-.235.635-.572a8.18 8.18 0 0 0 .45-.606zm1.64-1.33a12.71 12.71 0 0 1 1.01-.193 11.744 11.744 0 0 1-.51-.858 20.801 20.801 0 0 1-.5 1.05zm2.446.45c.15.163.296.3.435.41.24.19.407.253.498.256a.107.107 0 0 0 .07-.015.307.307 0 0 0 .094-.125.436.436 0 0 0 .059-.2.095.095 0 0 0-.026-.063c-.052-.062-.2-.152-.518-.209a3.876 3.876 0 0 0-.612-.053zM8.078 7.8a6.7 6.7 0 0 0 .2-.828c.031-.188.043-.343.038-.465a.613.613 0 0 0-.032-.198.517.517 0 0 0-.145.04c-.087.035-.158.106-.196.283-.04.192-.03.469.046.822.024.111.054.227.09.346z"/>
              </svg>
              ${constancia.archivo}
            </div>
            <div class="constancia-detalles">
              <span>📅 ${constancia.fecha_formateada}</span>
              <span>👤 ${constancia.usuario}</span>
              <span>📦 Cantidad: ${constancia.cantidad}</span>
              <span>💾 ${constancia.tamano_mb} MB</span>
            </div>
          </div>
          <div class="constancia-acciones">
            <button type="button" class="btn-icon-download" onclick="event.stopPropagation(); descargarConstancia('${constancia.url_descarga}', '${constancia.archivo}')" title="Descargar PDF">
              <svg width="18" height="18" fill="currentColor" viewBox="0 0 16 16">
                <path d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z"/>
                <path d="M7.646 11.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 0 0-.708-.708L8.5 10.293V1.5a.5.5 0 0 0-1 0v8.793L5.354 8.146a.5.5 0 1 0-.708.708l3 3z"/>
              </svg>
            </button>
          </div>
        </div>
      `;
    });
    
    lista.innerHTML = html;
  }

  // Función para descargar constancia
  window.descargarConstancia = function(url, nombreArchivo) {
    console.log('Descargando:', nombreArchivo);
    window.open(url, '_blank');
  };

  // Inicializar cuando el DOM esté listo
  document.addEventListener('DOMContentLoaded', function() {
    console.log('Inicializando modal de constancias...');
    
    // Event listener para botones de ver constancias
    const tabla = document.querySelector('.tabla-articulos tbody');
    if (tabla) {
      tabla.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-icon.view-constancias');
        if (!btn) return;
        
        console.log('Click en botón ver constancias detectado');
        
        const tr = btn.closest('tr');
        if (!tr) {
          console.error('No se encontró la fila (tr)');
          return;
        }
        
        const sku = btn.dataset.sku || tr.dataset.sku;
        const nombreProducto = tr.querySelector('td:nth-child(2)').textContent.trim();
        
        console.log('Datos extraídos:', { sku, nombreProducto });
        
        abrirModalConstancias(sku, nombreProducto);
      });
      
      console.log('Event listener de constancias añadido');
    }
    
    // Event listener para cerrar modal con backdrop
    const modal = document.getElementById('modalVerConstancias');
    if (modal) {
      modal.addEventListener('click', function(e) {
        if (e.target === modal && modal.classList.contains('active')) {
          cerrarModalConstancias();
        }
      });
    }
    
    // Event listener para tecla Escape
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        const modal = document.getElementById('modalVerConstancias');
        if (modal && modal.classList.contains('active')) {
          cerrarModalConstancias();
        }
      }
    });
    
    console.log('Modal de constancias inicializado completamente');
  });
})();
