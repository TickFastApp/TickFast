// TickFast - JavaScript Principal
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
});

function initializeApp() {
    // Inicializar componentes
    initializeModals();
    initializeSearch();
    initializeForms();
    initializeCart();
    initializeProfileTabs();
    
    // Event listeners globales
    setupEventListeners();
}

// Modales
function initializeModals() {
    const modals = document.querySelectorAll('.modal');
    const modalTriggers = document.querySelectorAll('[data-modal]');
    const closeButtons = document.querySelectorAll('.close');

    modalTriggers.forEach(trigger => {
        trigger.addEventListener('click', function(e) {
            e.preventDefault();
            const modalId = this.getAttribute('data-modal');
            const modal = document.getElementById(modalId);
            if (modal) {
                showModal(modal);
            }
        });
    });

    closeButtons.forEach(button => {
        button.addEventListener('click', function() {
            const modal = this.closest('.modal');
            hideModal(modal);
        });
    });

    // Cerrar modal al hacer click fuera
    modals.forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                hideModal(this);
            }
        });
    });
}

function showModal(modal) {
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
    modal.classList.add('show');
}

function hideModal(modal) {
    modal.style.display = 'none';
    document.body.style.overflow = '';
    modal.classList.remove('show');
}

// Búsqueda
function initializeSearch() {
    const searchForm = document.getElementById('searchForm');
    const searchInput = document.getElementById('searchInput');
    const filterDate = document.getElementById('filterDate');
    const filterGenre = document.getElementById('filterGenre');

    if (searchForm) {
        searchForm.addEventListener('submit', function(e) {
            e.preventDefault();
            performSearch();
        });
    }

    // Búsqueda en tiempo real
    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                performSearch();
            }, 300);
        });
    }

    // Filtros
    [filterDate, filterGenre].forEach(filter => {
        if (filter) {
            filter.addEventListener('change', performSearch);
        }
    });
}

function performSearch() {
    const searchTerm = document.getElementById('searchInput')?.value || '';
    const filterDate = document.getElementById('filterDate')?.value || '';
    const filterGenre = document.getElementById('filterGenre')?.value || '';

    showLoading('Buscando eventos...');

    // Simular búsqueda AJAX
    fetch('search.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            search: searchTerm,
            date: filterDate,
            genre: filterGenre
        })
    })
    .then(response => response.json())
    .then(data => {
        displaySearchResults(data);
        hideLoading();
    })
    .catch(error => {
        console.error('Error en búsqueda:', error);
        showAlert('Error al buscar eventos', 'error');
        hideLoading();
    });
}

function displaySearchResults(events) {
    const eventsGrid = document.querySelector('.events-grid');
    if (!eventsGrid) return;

    if (events.length === 0) {
        eventsGrid.innerHTML = '<div class="no-results">No se encontraron eventos</div>';
        return;
    }

    let html = '';
    events.forEach(event => {
        html += createEventCard(event);
    });
    
    eventsGrid.innerHTML = html;
}

function createEventCard(event) {
    return `
        <div class="event-card" data-event-id="${event.id_show}">
            <div class="event-image">
                ${event.imagen ? `<img src="${event.imagen}" alt="${event.Nombre}">` : '🎵'}
            </div>
            <div class="event-details">
                <h3 class="event-title">${event.Nombre}</h3>
                <div class="event-info">
                    <span>📅 ${formatDate(event.Fecha)}</span>
                    <span>🕒 ${formatTime(event.Horario)}</span>
                    <span>📍 ${event.venue_nombre}</span>
                </div>
                <div class="event-price">Desde ${formatPrice(event.precio_minimo)}</div>
                <button class="btn btn-primary" onclick="viewEvent(${event.id_show})">
                    Ver Tickets
                </button>
            </div>
        </div>
    `;
}

// Carrito de compras
function initializeCart() {
    updateCartUI();
}

function addToCart(eventId, sectorId, quantity = 1) {
    const cartData = {
        event_id: eventId,
        sector_id: sectorId,
        quantity: quantity
    };

    fetch('cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'add',
            ...cartData
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Ticket agregado al carrito', 'success');
            updateCartUI();
            updateCartCount();
        } else {
            showAlert(data.message || 'Error al agregar ticket', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Error al agregar al carrito', 'error');
    });
}

function removeFromCart(ticketId) {
    fetch('cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'remove',
            ticket_id: ticketId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Ticket eliminado del carrito', 'success');
            updateCartUI();
            updateCartCount();
        }
    });
}

function updateCartCount() {
    fetch('cart.php?action=count')
        .then(response => response.json())
        .then(data => {
            const cartCount = document.getElementById('cartCount');
            if (cartCount) {
                cartCount.textContent = data.count;
                cartCount.style.display = data.count > 0 ? 'inline' : 'none';
            }
        });
}

function updateCartUI() {
    const cartContainer = document.getElementById('cartItems');
    if (!cartContainer) return;

    fetch('cart.php?action=get')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayCartItems(data.items, data.total);
            }
        });
}

function displayCartItems(items, total) {
    const cartContainer = document.getElementById('cartItems');
    const cartTotal = document.getElementById('cartTotal');

    if (items.length === 0) {
        cartContainer.innerHTML = '<div class="empty-cart">Tu carrito está vacío</div>';
        if (cartTotal) cartTotal.textContent = formatPrice(0);
        return;
    }

    let html = '';
    items.forEach(item => {
        html += `
            <div class="cart-item">
                <div>
                    <h4>${item.show_name}</h4>
                    <p>${item.sector_name} - ${formatPrice(item.precio)}</p>
                </div>
                <button class="btn btn-secondary" onclick="removeFromCart(${item.id})">
                    Eliminar
                </button>
            </div>
        `;
    });

    cartContainer.innerHTML = html;
    if (cartTotal) cartTotal.textContent = formatPrice(total);
}

// Formularios
function initializeForms() {
    const forms = document.querySelectorAll('form[data-ajax]');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            submitAjaxForm(this);
        });
    });
}

function submitAjaxForm(form) {
    const formData = new FormData(form);
    const submitButton = form.querySelector('button[type="submit"]');
    
    // Mostrar loading en botón
    const originalText = submitButton.textContent;
    submitButton.innerHTML = '<span class="loading"></span> Procesando...';
    submitButton.disabled = true;

    fetch(form.action, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            if (data.redirect) {
                setTimeout(() => {
                    window.location.href = data.redirect;
                }, 1500);
            }
        } else {
            showAlert(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Error al procesar formulario', 'error');
    })
    .finally(() => {
        submitButton.textContent = originalText;
        submitButton.disabled = false;
    });
}

// Pestañas del perfil
function initializeProfileTabs() {
    const tabs = document.querySelectorAll('.profile-tab');
    const contents = document.querySelectorAll('.profile-content');

    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const targetId = this.getAttribute('data-tab');
            
            // Remover clase active de todos los tabs y contenidos
            tabs.forEach(t => t.classList.remove('active'));
            contents.forEach(c => c.classList.remove('active'));
            
            // Activar tab y contenido seleccionado
            this.classList.add('active');
            document.getElementById(targetId).classList.add('active');
        });
    });
}

// Event listeners globales
function setupEventListeners() {
    // Navegación del menú móvil
    const menuToggle = document.getElementById('menuToggle');
    const navMenu = document.querySelector('.nav-menu');
    
    if (menuToggle && navMenu) {
        menuToggle.addEventListener('click', function() {
            navMenu.classList.toggle('active');
        });
    }

    // Scroll suave para enlaces internos
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth'
                });
            }
        });
    });

    // Lazy loading para imágenes
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.remove('lazy');
                    observer.unobserve(img);
                }
            });
        });

        document.querySelectorAll('img[data-src]').forEach(img => {
            imageObserver.observe(img);
        });
    }
}

// Funciones de utilidad
function showAlert(message, type = 'info') {
    const alertContainer = document.getElementById('alertContainer') || createAlertContainer();
    
    const alert = document.createElement('div');
    alert.className = `alert alert-${type}`;
    alert.textContent = message;
    
    // Botón cerrar
    const closeBtn = document.createElement('span');
    closeBtn.innerHTML = '&times;';
    closeBtn.style.cssText = 'float: right; cursor: pointer; font-size: 1.2rem;';
    closeBtn.onclick = () => alert.remove();
    
    alert.appendChild(closeBtn);
    alertContainer.appendChild(alert);
    
    // Auto-remove después de 5 segundos
    setTimeout(() => {
        if (alert.parentNode) {
            alert.remove();
        }
    }, 5000);
}

function createAlertContainer() {
    const container = document.createElement('div');
    container.id = 'alertContainer';
    container.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; width: 300px;';
    document.body.appendChild(container);
    return container;
}

function showLoading(message = 'Cargando...') {
    const loading = document.createElement('div');
    loading.id = 'globalLoading';
    loading.innerHTML = `
        <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
                    background: rgba(0,0,0,0.5); display: flex; align-items: center; 
                    justify-content: center; z-index: 9999;">
            <div style="background: white; padding: 2rem; border-radius: 8px; text-align: center;">
                <div class="loading" style="margin-bottom: 1rem;"></div>
                <div>${message}</div>
            </div>
        </div>
    `;
    document.body.appendChild(loading);
}

function hideLoading() {
    const loading = document.getElementById('globalLoading');
    if (loading) {
        loading.remove();
    }
}

function formatPrice(price) {
    // Formatea el precio en formato moneda ARS
    return '$ ' + parseFloat(price).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

// Funciones específicas del negocio
function viewEvent(eventId) {
    window.location.href = `event.php?id=${eventId}`;
}

function buyTicket(eventId, sectorId) {
    if (!isUserLoggedIn()) {
        showModal(document.getElementById('loginModal'));
        return;
    }
    
    addToCart(eventId, sectorId);
}

function proceedToCheckout() {
    if (!isUserLoggedIn()) {
        showAlert('Debes iniciar sesión para continuar', 'warning');
        return;
    }
    
    window.location.href = 'checkout.php';
}

function isUserLoggedIn() {
    // Esta función se puede mejorar verificando el estado de sesión
    return document.body.classList.contains('logged-in');
}

// Validación de formularios
function validateForm(form) {
    const requiredFields = form.querySelectorAll('[required]');
    let isValid = true;
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.classList.add('error');
            isValid = false;
        } else {
            field.classList.remove('error');
        }
    });
    
    // Validación de email
    const emailFields = form.querySelectorAll('input[type="email"]');
    emailFields.forEach(field => {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (field.value && !emailRegex.test(field.value)) {
            field.classList.add('error');
            isValid = false;
        }
    });
    
    // Validación de contraseñas
    const passwordField = form.querySelector('input[name="password"]');
    const confirmPasswordField = form.querySelector('input[name="confirm_password"]');
    
    if (passwordField && confirmPasswordField) {
        if (passwordField.value !== confirmPasswordField.value) {
            confirmPasswordField.classList.add('error');
            showAlert('Las contraseñas no coinciden', 'error');
            isValid = false;
        }
    }
    
    return isValid;
}

// Animaciones y efectos
function animateOnScroll() {
    const elements = document.querySelectorAll('[data-animate]');
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate');
            }
        });
    });
    
    elements.forEach(el => observer.observe(el));
}

// Inicializar animaciones al cargar
document.addEventListener('DOMContentLoaded', animateOnScroll);

// Gestión de estado local (simulando localStorage)
const appState = {
    cart: [],
    user: null,
    preferences: {}
};

function saveState() {
    // En un entorno real, aquí se guardaría en localStorage
    console.log('Estado guardado:', appState);
}

function loadState() {
    // En un entorno real, aquí se cargaría desde localStorage
    console.log('Estado cargado:', appState);
}

// Filtros avanzados
function applyFilters() {
    const filters = {
        priceMin: document.getElementById('priceMin')?.value,
        priceMax: document.getElementById('priceMax')?.value,
        venue: document.getElementById('venueFilter')?.value,
        genre: document.getElementById('genreFilter')?.value
    };
    
    performSearch(filters);
}

function resetFilters() {
    document.querySelectorAll('.filter-input').forEach(input => {
        input.value = '';
    });
    performSearch();
}

// Integración con APIs externas (simulado)
function loadExternalData() {
    // Simulación de carga de datos de APIs externas
    return new Promise((resolve) => {
        setTimeout(() => {
            resolve({
                weather: { temp: 22, condition: 'sunny' },
                traffic: { status: 'normal' },
                recommendations: []
            });
        }, 1000);
    });
}

// Inicialización final
window.addEventListener('load', function() {
    loadState();
    updateCartCount();
    
    // Verificar si hay mensajes flash en la sesión
    const flashMessages = document.querySelectorAll('.flash-message');
    flashMessages.forEach(msg => {
        showAlert(msg.textContent, msg.dataset.type);
        msg.remove();
    });
});