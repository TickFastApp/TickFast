// Base de datos simulada de eventos
let allTickets = [
    {
        id: 1,
        title: "Maria Becerra - River 360°",
        category: "musica",
        location: "Buenos Aires",
        date: "2025-12-14",
        time: "21:00",
        venue: "Estadio River Plate",
        price: 55000,
        source: {
            name: "All Access",
            url: "https://www.allaccess.com.ar/event/maria-becerra",
            color: "#7b0cc5ff"
        },
        image: "https://cdn.getcrowder.com/images/4458ebd9-942e-45ec-b302-9e45e21148e8-mb-1312-banneraa-1920x720.jpg?w=1920&format=webp",
        availability: "Muy Pocas disponibles"
    },
    {
        id: 2,
        title: "Lollapalooza Argentina 2026",
        category: "musica",
        location: "Buenos Aires",
        date: "2026-03-14",
        time: "12:00",
        venue: "Hipodromo de San Isidro",
        price: 375000,
        source: {
            name: "All Access",
            url: "https://www.allaccess.com.ar/event/lollapalooza-2026-venta-general",
            color: "#7b0cc5ff"
        },
        image: "https://cdn.getcrowder.com/images/9a0e2384-88e1-4d94-8ba8-0d5e94b1503e-lolla-headliners-bannersaa-1920x720-min.jpg",
        availability: "Disponible"
    },
    {
        id: 3,
        title: "Duki - Ameri Tour Buenos Aires",
        category: "musica",
        location: "Buenos Aires",
        date: "2025-12-18",
        time: "21:00",
        venue: "Movistar Arena",
        price: 35000,
        source: {
            name: "Movistar Arena",
            url: "https://www.movistararena.com.ar/show/e69127ec-55d8-4a59-b6c5-a7fdaa536615",
            color: "#00369bff"
        },
        image: "https://www.movistararena.com.ar/static/artistas/B5298_DUKI_FileFotoFichaDesktop",
        availability: "Muy Pocas disponibles",
    },
    {
        id: 4,
        title: "RusherKing - Teatro Opera",
        category: "musica",
        location: "Buenos Aires",
        date: "2025-11-30",
        time: "21:00",
        venue: "Teatro Opera",
        price: 28250,
        source: {
            name: "Ticketek",
            url: "https://www.ticketek.com.ar/rusher/teatro-opera",
            color: "#00a2ffff"
        },
        image: "https://prod-cms-static.ticketek.com.ar/sites/default/files/images/show-header/rushsan960.png",
        availability: "Disponibles",
    },
    {
        id: 5,
        title: "Creamfields 2025 - Argentina",
        category: "musica",
        location: "Buenos Aires",
        date: "2025-10-12",
        time: "12:00",
        venue: "Parque de la ciudad",
        price: 35000,
        source: {
            name: "Entrada Uno",
            url: "https://entradauno.com/landing/14819-creamfields-2025?idEspectaculoCartel=14819&cHashValidacion=6c2bfda23f0c98b3f2c2300fd497e36b83b73720",
            color: "#0059ffff"
        },
        image: "https://contenidos.entradauno.com/Venues/entradaUno/creamfields/2025-nuevas/destacada%20720x405%20cream-01.jpg",
        availability: "Disponibles",
    },
    {
        id: 6,
        title: "Dua Lipa - Radical Optimism Tour",
        category: "musica",
        location: "Buenos Aires",
        date: "2025-11-09",
        time: "21:00",
        venue: "Estadio Mas Monumental - River Plate",
        price: 80000,
        source: {
            name: "All Access",
            url: "https://www.allaccess.com.ar/event/dua-lipa",
            color: "#7200b4ff"
        },
        image: "https://cdn.getcrowder.com/images/e4ed53e5-93fc-4aa5-981e-54cf0ff8c0bd-1920x720-5-min.jpg",
        availability: "Pocas disponibles",
    },
];

let displayedTickets = [];
let currentPage = 1;
const ticketsPerPage = 6;
let isLoading = false;

// Inicializar la aplicación
document.addEventListener('DOMContentLoaded', function() {
    displayTickets(allTickets.slice(0, ticketsPerPage));
    displayedTickets = allTickets;
    
    // Event listener para el Enter en el campo de búsqueda
    document.getElementById('searchInput').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            searchTickets();
        }
    });
    
    // Event listeners para los filtros
    document.getElementById('categoryFilter').addEventListener('change', searchTickets);
    document.getElementById('locationFilter').addEventListener('change', searchTickets);
    
    // Smooth scrolling para los enlaces de navegación
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
});

// Función para mostrar/ocultar menú móvil
function toggleMenu() {
    const navLinks = document.querySelector('.nav-links');
    navLinks.classList.toggle('active');
}

// Función de búsqueda principal
function searchTickets() {
    if (isLoading) return;
    
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const category = document.getElementById('categoryFilter').value;
    const location = document.getElementById('locationFilter').value;
    
    showLoadingModal();
    
    // Simular delay de búsqueda
    setTimeout(() => {
        let filteredTickets = allTickets.filter(ticket => {
            const matchesSearch = searchTerm === '' || 
                ticket.title.toLowerCase().includes(searchTerm) ||
                ticket.venue.toLowerCase().includes(searchTerm);
            
            const matchesCategory = category === '' || ticket.category === category;
            
            const matchesLocation = location === '' || 
                ticket.location.toLowerCase().replace(/\s+/g, '-') === location;
            
            return matchesSearch && matchesCategory && matchesLocation;
        });
        
        displayTickets(filteredTickets.slice(0, ticketsPerPage));
        displayedTickets = filteredTickets;
        currentPage = 1;
        
        // Actualizar botón de cargar más
        updateLoadMoreButton();
        
        hideLoadingModal();
        
        // Scroll a resultados
        document.getElementById('eventos').scrollIntoView({
            behavior: 'smooth'
        });
    }, 1500);
}

// Función para ordenar resultados
function sortResults() {
    const sortBy = document.getElementById('sortFilter').value;
    let sortedTickets = [...displayedTickets];
    
    switch(sortBy) {
        case 'price':
            sortedTickets.sort((a, b) => a.price - b.price);
            break;
        case 'date':
            sortedTickets.sort((a, b) => new Date(a.date) - new Date(b.date));
            break;
        case 'popularity':
            // Simular popularidad basada en disponibilidad y precio
            sortedTickets.sort((a, b) => {
                const popularityA = getPopularityScore(a);
                const popularityB = getPopularityScore(b);
                return popularityB - popularityA;
            });
            break;
    }
    
    displayTickets(sortedTickets.slice(0, currentPage * ticketsPerPage));
}

// Función para obtener score de popularidad
function getPopularityScore(ticket) {
    let score = 0;
    
    if (ticket.availability.includes('Muy pocas')) score += 100;
    else if (ticket.availability.includes('Pocas')) score += 50;
    else score += 10;
    
    if (ticket.originalPrice) score += 30;
    
    if (ticket.category === 'musica') score += 20;
    if (ticket.category === 'deportes') score += 25;
    
    return score;
}

// Función para mostrar tickets en el DOM
function displayTickets(tickets) {
    const ticketsGrid = document.getElementById('ticketsGrid');
    
    if (tickets.length === 0) {
        ticketsGrid.innerHTML = `
            <div class="no-results">
                <h3>No se encontraron entradas</h3>
                <p>Intenta cambiar los filtros o términos de búsqueda</p>
            </div>
        `;
        return;
    }
    
    ticketsGrid.innerHTML = tickets.map(ticket => createTicketCard(ticket)).join('');
    observeTickets();
}

// Función para crear una tarjeta de ticket
function createTicketCard(ticket) {
    const formattedDate = formatDate(ticket.date);
    const formattedPrice = formatPrice(ticket.price);
    const originalPriceHtml = ticket.originalPrice ? 
        `<span style="text-decoration: line-through; color: #718096; font-size: 1rem;">${formatPrice(ticket.originalPrice)}</span>` : '';
    
    const availabilityClass = getAvailabilityClass(ticket.availability);
    
    return `
        <div class="ticket-card" data-id="${ticket.id}">
            <div class="ticket-image">
            <span class="category-tag">${getCategoryName(ticket.category)}</span>
            <img src="${ticket.image}" alt="${ticket.title}" class="ticket-img">
            </div>
            <div class="ticket-content">
                <h3 class="ticket-title">${ticket.title}</h3>
                <div class="ticket-info">
                    <span class="ticket-date">📅 ${formattedDate} - ${ticket.time}</span>
                    <span class="ticket-location">📍 ${ticket.venue}, ${ticket.location}</span>
                    <span class="ticket-availability ${availabilityClass}">🎫 ${ticket.availability}</span>
                </div>
                <div class="ticket-price">
                    ${originalPriceHtml}
                    ${formattedPrice}
                </div>
                <div class="ticket-source">
                    Disponible en: <strong style="color: ${ticket.source.color};">${ticket.source.name}</strong>
                </div>
                <button class="buy-btn" onclick="redirectToSource('${ticket.source.url}', '${ticket.title}', ${ticket.id})">
                    Comprar en ${ticket.source.name}
                </button>
            </div>
        </div>
    `;
}

function getCategoryName(category) {
    const categories = {
        'musica': 'Música',
        'deportes': 'Deportes',
        'teatro': 'Teatro',
        'comedia': 'Comedia'
    };
    return categories[category] || category;
}

function getAvailabilityClass(availability) {
    if (availability.includes('SOLD OUT')) return 'availability-critical';
    if (availability.includes('Pocas')) return 'availability-warning';
    return 'availability-good';
}

function formatDate(dateString) {
    const date = new Date(dateString);
    const options = { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric',
        weekday: 'long'
    };
    return date.toLocaleDateString('es-AR', options);
}

function formatPrice(price) {
    return price.toLocaleString('es-AR');
}

// ✅ corregido
function loadMoreTickets() {
    if (isLoading) return;
    
    const startIndex = currentPage * ticketsPerPage;
    const endIndex = startIndex + ticketsPerPage;
    const moreTickets = displayedTickets.slice(startIndex, endIndex);
    
    if (moreTickets.length === 0) return;
    
    isLoading = true;
    const loadMoreBtn = document.getElementById('loadMoreBtn');
    loadMoreBtn.textContent = 'Cargando...';
    
    setTimeout(() => {
        const ticketsGrid = document.getElementById('ticketsGrid');
        const newTicketsHtml = moreTickets.map(ticket => createTicketCard(ticket)).join('');
        ticketsGrid.innerHTML += newTicketsHtml;
        
        // 👇 ahora observamos los nuevos
        observeTickets();
        
        currentPage++;
        updateLoadMoreButton();
        
        isLoading = false;
        loadMoreBtn.textContent = 'Cargar más entradas';
    }, 1000);
}

function updateLoadMoreButton() {
    const loadMoreBtn = document.getElementById('loadMoreBtn');
    const totalPages = Math.ceil(displayedTickets.length / ticketsPerPage);
    
    if (currentPage >= totalPages) {
        loadMoreBtn.style.display = 'none';
    } else {
        loadMoreBtn.style.display = 'inline-block';
    }
}

function redirectToSource(sourceUrl, ticketTitle, ticketId) {
    console.log(`Usuario clickeó en ticket: ${ticketTitle} (ID: ${ticketId})`);
    showRedirectModal(sourceUrl, ticketTitle);
    setTimeout(() => {
        hideRedirectModal();
        window.open(sourceUrl, '_blank');
    }, 3000);
}

function showLoadingModal() {
    const modal = document.getElementById('loadingModal');
    modal.style.display = 'block';
    isLoading = true;
}

function hideLoadingModal() {
    const modal = document.getElementById('loadingModal');
    modal.style.display = 'none';
    isLoading = false;
}

function showRedirectModal(sourceUrl, ticketTitle) {
    const modal = document.getElementById('loadingModal');
    const modalContent = modal.querySelector('.modal-content');
    
    modalContent.innerHTML = `
        <div class="loading-spinner"></div>
        <h3>Redirigiendo a la tienda oficial</h3>
        <p>Te estamos llevando a comprar "<strong>${ticketTitle}</strong>"</p>
        <p style="margin-top: 1rem; color: #718096; font-size: 0.9rem;">
            Esto te llevará a ${new URL(sourceUrl).hostname}
        </p>
    `;
    
    modal.style.display = 'block';
}

function hideRedirectModal() {
    const modal = document.getElementById('loadingModal');
    const modalContent = modal.querySelector('.modal-content');
    
    modalContent.innerHTML = `
        <div class="loading-spinner"></div>
        <p>Buscando las mejores entradas para ti...</p>
    `;
    
    modal.style.display = 'none';
}

window.onclick = function(event) {
    const modal = document.getElementById('loadingModal');
    if (event.target === modal) {
        hideLoadingModal();
        hideRedirectModal();
    }
}

// Simular más eventos
function generateMoreEvents() {
    const additionalEvents = [
        {
            id: 7,
            title: "Guns N' Roses - Buenos Aires",
            category: "musica",
            location: "Buenos Aires",
            date: "2025-10-18",
            time: "21:00",
            venue: "Estadio de Huracán",
            price: 95000,
            source: {
                name: "All Access",
                url: "https://www.allaccess.com.ar/event/guns-n-roses",
                color: "#8200b6ff"
            },
            image: "https://cdn.getcrowder.com/images/2c298c07-dac1-4232-8080-704fac5256bb-gunsnroses-bannersaa-nuevafecha1920x720.jpg",
            availability: "Muy Pocas disponibles",
        },
        {
            id: 8,
            title: "Cazzu Latinaje en vivo",
            category: "musica",
            location: "Buenos Aires",
            date: "2025-09-14",
            time: "21:00",
            venue: "Movistar Arena",
            price: 35000,
            source: {
                name: "Movistar Arena",
                url: "https://www.movistararena.com.ar/show/20f66520-b8f9-41a9-bbbb-cf4522d56740",
                color: "#0021b4ff"
            },
            image: "https://www.movistararena.com.ar/static/artistas/2BAD8_Cazzu_FileFotoFichaDesktop",
            availability: "Pocas disponibles",
        },
        {
            id: 9,
            title: "Juanes Latam Tour Mendoza",
            category: "musica",
            location: "Mendoza",
            date: "2025-10-31",
            time: "21:00",
            venue: "Arena Maipú",
            price: 61600,
            source: {
                name: "Ticketek",
                url: "https://www.ticketek.com.ar/juanes/arena-maipu",
                color: "#0021b4ff"
            },
            image: "https://prod-cms-static.ticketek.com.ar/sites/default/files/images/show-header/juan960.png",
            availability: "Disponibles",
        },
        {
            id: 10,
            title: "Joaco Burgos",
            category: "musica",
            location: "Buenos Aires",
            date: "2025-09-13",
            time: "21:00",
            venue: "Vorterix",
            price: "20000",
            source: {
                name: "All Access",
                url: "https://www.allaccess.com.ar/event/joaco-burgos",
                color: "#8200b6ff"
            },
            image: "https://cdn.getcrowder.com/images/ff76ae12-adae-463b-87b1-8ed40310b98d-joaco-burgos-aa-640x640-1.jpg?w=1920",
            availability: "Disponibles"
        },
        {
            id: 11,
            title: "Andrea Bocelli Live in Concert Hipódromo de San Isidro",
            category: "musica",
            location: "Buenos Aires",
            date: "2025-11-19",
            time: "21:00",
            venue: "Hipodromo de San Isidro",
            price: "210000",
            source: {
                name: "All Access",
                url: "https://www.allaccess.com.ar/page/andrea-bocelli-hipodromo",
                color: "#8200b6ff"
            },
            image: "https://cdn.getcrowder.com/images/47331c4e-5e70-40cc-918f-1ffbe2d1497a-andreabocelli-hsi-banneraa-640x640.jpg?w=1920",
            availability: "Pocas Disponible"
        },
        {
            id: 12,
            title: "Kendrick Lamar Grand National Tour with Ca7riel & Paco Amoroso",
            category: "musica",
            location: "Buenos Aires",
            date: "2025-10-05",
            time: "21:00",
            venue: "Estadio Mas Monumental - River Plate",
            price: "80000",
            source: {
                name: "All Access",
                url: "https://www.allaccess.com.ar/page/kendrick-lamar",
                color: "#8200b6ff"
            },
            image: "https://cdn.getcrowder.com/images/1572fd27-1afa-4c33-bb4d-20ef7e148cd8-640x640-7.jpg?w=1920",
            availability: "Pocas Disponibles"
        },
        {
            id: 13,
            title: "Ulises Bueno",
            category: "musica",
            location: "Buenos Aires",
            date: "2025-09-28",
            time: "21:00",
            venue: "Estadio de la Costa",
            price: "45000",
            source: {
                name: "All Access",
                url: "https://www.allaccess.com.ar/page/ulises-bueno",
                color: "#8200b6ff"
            },
            image: "https://cdn.getcrowder.com/images/e63633fc-81fe-44cd-b9c2-f9a1bfd1a70a-flyer-ulises-bueno---estadio-de-la-costa-640x640.jpg?w=1920",
            availability: "Disponibles"
        },
        {
            id: 14,
            title: "Imagine Dragons LOOM World Tour",
            category: "musica",
            location: "Buenos Aires",
            date: "2025-10-24",
            time: "20:00",
            venue: "Hipodromo de San Isidro",
            price: "80000",
            source: {
                name: "All Access",
                url: "https://www.allaccess.com.ar/page/imagine-dragons",
                color: "#8200b6ff"
            },
            image: "https://cdn.getcrowder.com/images/ce903353-bdaf-4910-9036-dc86df9d4c94-bf38aff4-e196-4dcb-944c-1a5722097631-imaginedragons-bannersaa-640x640.jpg?w=1920",
            availability: "Disponibles"
        },
        {
            id: 15,
            title: "Sex Pistols",
            category: "musica",
            location: "Buenos Aires",
            date: "2025-09-12",
            time: "21:00",
            venue: "Estadio Obras",
            price: "TBC",
            source: {
                name: "All Access",
                url: "https://www.allaccess.com.ar/event/sex-pistols",
                color: "#8200b6ff"
            },
            image: "https://cdn.getcrowder.com/images/8992fcf7-3d46-47c4-990e-94fad6e94da2-pistols.jpg?w=1920",
            availability: "SOLD OUT"
        },
        {
            id: 16,
            title: "Linkin Park From Zero World Tour",
            category: "musica",
            location: "Buenos Aires",
            date: "2025-10-32",
            time: "20:00",
            venue: "Parque de la Ciudad",
            price: "90000",
            source: {
                name: "All Access",
                url: "https://www.allaccess.com.ar/page/linkin-park",
                color: "#8200b6ff"
            },
            image: "https://cdn.getcrowder.com/images/d16a4761-5722-4e54-80b2-9260a922a80b-linkin-640x640.jpg?w=1920",
            availability: "Disponibles"
        },
        {
            id: 17,
            title: "Bad Bunny DTMF World Tour",
            category: "musica",
            location: "Buenos Aires",
            date: "2026-02-14",
            time: "20:00",
            venue: "Estadio Mas Monumental - River Plate",
            price: "95000",
            source: {
                name: "All Access",
                url: "https://www.allaccess.com.ar/page/bad-bunny",
                color: "#8200b6ff"
            },
            image: "https://cdn.getcrowder.com/images/fcf30efa-77c5-4cf9-8b36-67387ca88ab1-789058aa-7e3a-40ae-9a96-7dcf42b8c66b-banner-mobile--quentro-640-x-640.jpg?w=1920",
            availability: "SOLD OUT"
        },
        {
            id: 18,
            title: "Los Ángeles Azules",
            category: "musica",
            location: "Buenos Aires",
            date: "2025-10-26",
            time: "20:00",
            venue: "Parque de la Ciudad",
            price: "50000",
            source: {
                name: "All Access",
                url: "https://www.allaccess.com.ar/page/los-angeles-azules",
                color: "#8200b6ff"
            },
            image: "https://cdn.getcrowder.com/images/35480df9-4cca-4a46-9c12-3ab977ebf67c-bannersaa640x640.jpg?w=1920",
            availability: "Disponibles"
        },
        {
            id: 19,
            title: "Oasis Live '25",
            category: "musica",
            location: "Buenos Aires",
            date: "2025-11-16",
            time: "21:00",
            venue: "Estadio Mas Monumental - River Plate",
            price: "145000",
            source: {
                name: "All Access",
                url: "https://www.allaccess.com.ar/page/Oasis",
                color: "#8200b6ff"
            },
            image: "https://cdn.getcrowder.com/images/e0755d0f-eb68-45d8-ab5d-406861dc15b1-98925d7c-ebdc-4683-8aef-930bfcf1ead5-640x640-web-aa-mobile.jpg?w=1920",
            availability: "Muy Pocas disponibles"
        },
        {
            id: 20,
            title: "Andrea Bocelli Live in Concert Teatro Colon",
            category: "musica",
            location: "Buenos Aires",
            date: "2025-11-18",
            time: "21:00",
            venue: "Teatro Colon",
            price: "TBC",
            source: {
                name: "All Access",
                url: "https://www.allaccess.com.ar/event/andrea-bocelli",
                color: "#8200b6ff"
            },
            image: "https://cdn.getcrowder.com/images/b126e0bf-231a-4da6-85ca-219db19cd06e-640x640-13.jpg?w=1920",
            availability: "SOLD OUT"
        },
        {
            id: 21,
            title: "Airbag EL CLUB DE LA PELEA Tercer Round",
            category: "musica",
            location: "Buenos Aires",
            date: "2025-10-06",
            time: "21:00",
            venue: "Estadio Mas Monumental - River Plate",
            price: "45000",
            source: {
                name: "All Access",
                url: "https://www.allaccess.com.ar/page/Airbag",
                color: "#8200b6ff"
            },
            image: "https://cdn.getcrowder.com/images/e3313ea6-58ba-4376-8d25-4cda24729246-640x640-11.jpg?w=1920",
            availability: "SOLD OUT"
        },
        {
            id: 22,
            title: "Pierce The Veil I CAN’T HEAR YOU World Tour",
            category: "musica",
            location: "Buenos Aires",
            date: "2025-12-13",
            time: "21:00",
            venue: "C Art Media",
            price: "60000",
            source: {
                name: "All Access",
                url: "https://www.allaccess.com.ar/event/pierce-the-veil",
                color: "#8200b6ff"
            },
            image: "https://cdn.getcrowder.com/images/4a5e7a9f-8eb7-4f02-8f1a-df7c599fa98a-pierce-649.jpg?w=1920",
            availability: "Disponibles"
        },
        {
            id: 23,
            title: "Lola Indigo Nave Dragón",
            category: "musica",
            location: "Buenos Aires",
            date: "2025-10-03",
            time: "21:00",
            venue: "C Art Media",
            price: "50000",
            source: {
                name: "All Access",
                url: "https://www.allaccess.com.ar/event/lola-indigo",
                color: "#8200b6ff"
            },
            image: "https://cdn.getcrowder.com/images/51d31d70-d910-4991-93e5-587984026e24-aa-banner-640x640.jpg?w=1920",
            availability: "Pocas Disponibles"
        },
        {
            id: 21,
            title: "Chase Atlantic Lost in South America Tour",
            category: "musica",
            location: "Buenos Aires",
            date: "2025-11-20",
            time: "TBC",
            venue: "C Art Media",
            price: "TBC",
            source: {
                name: "All Access",
                url: "https://www.allaccess.com.ar/event/chase-atlantic",
                color: "#8200b6ff"
            },
            image: "https://cdn.getcrowder.com/images/d505c262-27c8-4aa5-a35e-aae9e98f186d-chaseatlantic-banneraa-640x640.jpg?w=1920",
            availability: "PRÓXIMAMENTE"
        }
    ];
    
    allTickets = [...allTickets, ...additionalEvents];
}
generateMoreEvents();

// Observer para animaciones
let observer;
function initObserver() {
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);
}

function observeTickets() {
    document.querySelectorAll('.ticket-card').forEach(card => {
        if (!card.classList.contains('observed')) {
            card.style.opacity = '0';
            card.style.transform = 'translateY(30px)';
            card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(card);
            card.classList.add('observed');
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    initObserver();
    displayTickets(allTickets.slice(0, ticketsPerPage));
    displayedTickets = [...allTickets];
    observeTickets();
});
