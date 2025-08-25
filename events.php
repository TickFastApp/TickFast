<?php
require_once './config/db_connection.php';

// Obtener todos los eventos activos
$db = getDB();

// Manejar filtros
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date = isset($_GET['date']) ? $_GET['date'] : '';
$genre = isset($_GET['genre']) ? trim($_GET['genre']) : '';
$venue = isset($_GET['venue']) ? (int)$_GET['venue'] : 0;
$artist = isset($_GET['artist']) ? (int)$_GET['artist'] : 0;
$price_min = isset($_GET['price_min']) ? (float)$_GET['price_min'] : 0;
$price_max = isset($_GET['price_max']) ? (float)$_GET['price_max'] : 999999;

// Construir query con filtros
$whereClause = "WHERE s.estado = 'activo' AND s.Fecha >= CURDATE()";
$params = [];

if (!empty($search)) {
    $whereClause .= " AND (s.Nombre LIKE ? OR v.nombre LIKE ? OR a.Nombre LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($date)) {
    $whereClause .= " AND s.Fecha = ?";
    $params[] = $date;
}

if (!empty($genre)) {
    $whereClause .= " AND a.genero = ?";
    $params[] = $genre;
}

if ($venue > 0) {
    $whereClause .= " AND s.id_venue = ?";
    $params[] = $venue;
}

if ($artist > 0) {
    $whereClause .= " AND sa.id_artista = ?";
    $params[] = $artist;
}

// Query principal con precios
$stmt = $db->prepare("
    SELECT s.*, 
           v.nombre as venue_nombre, 
           v.Direccion as venue_direccion,
           v.Capacidad as venue_capacidad,
           GROUP_CONCAT(DISTINCT CONCAT(a.Nombre, ' ', IFNULL(a.Apellido, '')) SEPARATOR ', ') as artistas,
           GROUP_CONCAT(DISTINCT a.genero SEPARATOR ', ') as generos,
           MIN(sec.Capacidad * (v.PrecioBase + (v.PrecioBase * sec.Porc_agr / 100))) as precio_minimo,
           MAX(sec.Capacidad * (v.PrecioBase + (v.PrecioBase * sec.Porc_agr / 100))) as precio_maximo,
           COUNT(DISTINCT sec.idSector) as sectores_disponibles
    FROM Shows s
    JOIN Venue v ON s.id_venue = v.id_venue
    LEFT JOIN Show_Artistas sa ON s.id_show = sa.id_show
    LEFT JOIN Artistas a ON sa.id_artista = a.id_artista
    LEFT JOIN Sector sec ON v.id_venue = sec.idVenue AND sec.tickets_disponibles > 0
    $whereClause
    GROUP BY s.id_show
    HAVING (? = 0 OR precio_minimo >= ?) AND (? = 999999 OR precio_minimo <= ?)
    ORDER BY s.Fecha ASC, s.Horario ASC
");

$stmt->execute([...$params, $price_min, $price_min, $price_max, $price_max]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener géneros para filtros
$stmt_genres = $db->prepare("
    SELECT DISTINCT a.genero 
    FROM Artistas a 
    JOIN Show_Artistas sa ON a.id_artista = sa.id_artista
    JOIN Shows s ON sa.id_show = s.id_show
    WHERE a.genero IS NOT NULL AND a.activo = 1 AND s.estado = 'activo' AND s.Fecha >= CURDATE()
    ORDER BY a.genero
");
$stmt_genres->execute();
$genres = $stmt_genres->fetchAll(PDO::FETCH_COLUMN);

// Obtener venues para filtros
$stmt_venues = $db->prepare("
    SELECT DISTINCT v.id_venue, v.nombre 
    FROM Venue v 
    JOIN Shows s ON v.id_venue = s.id_venue
    WHERE v.activo = 1 AND s.estado = 'activo' AND s.Fecha >= CURDATE()
    ORDER BY v.nombre
");
$stmt_venues->execute();
$venues = $stmt_venues->fetchAll(PDO::FETCH_ASSOC);

// Obtener estadísticas
$stmt_stats = $db->prepare("
    SELECT 
        COUNT(DISTINCT s.id_show) as total_eventos,
        COUNT(DISTINCT s.id_venue) as venues_activos,
        COUNT(DISTINCT sa.id_artista) as artistas_activos,
        MIN(s.Fecha) as proximo_evento
    FROM Shows s
    LEFT JOIN Show_Artistas sa ON s.id_show = sa.id_show
    WHERE s.estado = 'activo' AND s.Fecha >= CURDATE()
");
$stmt_stats->execute();
$stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eventos - TickFast</title>
    <link rel="stylesheet" href="./assets/styles.css">
</head>
<body class="<?php echo isLoggedIn() ? 'logged-in' : ''; ?>">
    <!-- Header -->
    <header class="header">
        <nav class="navbar">
            <img src="./assets/img/tickfast.png" height="60" alt="TickFast Logo" class="logo">
            
            <ul class="nav-menu">
                <li><a href="index.php" class="nav-link">Inicio</a></li>
                <li><a href="venues.php" class="nav-link">Venues</a></li>
                <li><a href="artists.php" class="nav-link">Artistas</a></li>
                <li><a href="events.php" class="nav-link active">Eventos</a></li>
                
                <?php if (isLoggedIn()): ?>
                    <li><a href="profile.php" class="nav-link">Mi Perfil</a></li>
                    <li>
                        <a href="cart.php" class="nav-link">
                            🛒 Carrito 
                            <span id="cartCount" style="background: var(--accent-color); color: white; border-radius: 50%; padding: 2px 6px; font-size: 0.8rem; display: none;">0</span>
                        </a>
                    </li>
                    <li><a href="logout.php" class="nav-link">Salir</a></li>
                <?php else: ?>
                    <li><a href="login.php" class="nav-link">Iniciar Sesión</a></li>
                    <li><a href="register.php" class="nav-link btn btn-primary">Registrarse</a></li>
                <?php endif; ?>
            </ul>
            
            <button id="menuToggle" class="menu-toggle" style="display: none;">☰</button>
        </nav>
    </header>

    <!-- Contenido Principal -->
    <main class="main-content">
        <!-- Hero Section -->
        <section class="hero" style="padding: 3rem 2rem; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <div class="container" style="text-align: center; color: white;">
                <h1 style="font-size: 3rem; margin-bottom: 1rem;">🎫 Eventos</h1>
                <p style="font-size: 1.2rem; opacity: 0.9;">Descubre los mejores eventos en vivo de Argentina</p>
                <?php if ($stats['proximo_evento']): ?>
                    <p style="font-size: 1rem; opacity: 0.8; margin-top: 1rem;">
                        Próximo evento: <?php echo formatDate($stats['proximo_evento']); ?>
                    </p>
                <?php endif; ?>
            </div>
        </section>

        <!-- Estadísticas -->
        <section style="background: var(--gray-light); padding: 2rem 0;">
            <div class="container">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 2rem; text-align: center;">
                    <div style="background: white; padding: 1.5rem; border-radius: var(--border-radius); box-shadow: var(--box-shadow);">
                        <div style="font-size: 2rem; color: var(--accent-color); margin-bottom: 0.5rem;"><?php echo number_format($stats['total_eventos']); ?></div>
                        <div style="font-weight: bold;">Eventos Disponibles</div>
                    </div>
                    <div style="background: white; padding: 1.5rem; border-radius: var(--border-radius); box-shadow: var(--box-shadow);">
                        <div style="font-size: 2rem; color: var(--accent-color); margin-bottom: 0.5rem;"><?php echo number_format($stats['venues_activos']); ?></div>
                        <div style="font-weight: bold;">Venues Activos</div>
                    </div>
                    <div style="background: white; padding: 1.5rem; border-radius: var(--border-radius); box-shadow: var(--box-shadow);">
                        <div style="font-size: 2rem; color: var(--accent-color); margin-bottom: 0.5rem;"><?php echo number_format($stats['artistas_activos']); ?></div>
                        <div style="font-weight: bold;">Artistas en Actividad</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Sección de Búsqueda y Filtros Avanzados -->
        <section class="search-section" style="padding: 3rem 2rem;">
            <div class="container">
                <h2>Encuentra tu evento perfecto</h2>
                <form method="GET" class="search-form" style="max-width: 1000px; margin: 0 auto;">
                    <!-- Fila 1: Búsqueda principal -->
                    <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <div class="form-group">
                            <input type="text" name="search" class="form-control" 
                                   placeholder="Buscar evento, artista o venue..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="form-group">
                            <input type="date" name="date" class="form-control" 
                                   min="<?php echo date('Y-m-d'); ?>"
                                   value="<?php echo htmlspecialchars($date); ?>">
                        </div>
                        <div class="form-group">
                            <select name="genre" class="form-control">
                                <option value="">Todos los géneros</option>
                                <?php foreach ($genres as $g): ?>
                                    <option value="<?php echo htmlspecialchars($g); ?>" 
                                            <?php echo $genre === $g ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($g); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Fila 2: Filtros adicionales -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <div class="form-group">
                            <select name="venue" class="form-control">
                                <option value="">Todos los venues</option>
                                <?php foreach ($venues as $v): ?>
                                    <option value="<?php echo $v['id_venue']; ?>" 
                                            <?php echo $venue === $v['id_venue'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($v['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <input type="number" name="price_min" class="form-control" 
                                   placeholder="Precio mínimo" min="0" step="100"
                                   value="<?php echo $price_min > 0 ? $price_min : ''; ?>">
                        </div>
                        <div class="form-group">
                            <input type="number" name="price_max" class="form-control" 
                                   placeholder="Precio máximo" min="0" step="100"
                                   value="<?php echo $price_max < 999999 ? $price_max : ''; ?>">
                        </div>
                        <div class="form-group" style="display: flex; gap: 0.5rem;">
                            <button type="submit" class="btn btn-primary" style="flex: 1;">Filtrar</button>
                            <a href="events.php" class="btn btn-secondary" style="flex: 1; text-align: center; display: flex; align-items: center; justify-content: center;">Limpiar</a>
                        </div>
                    </div>
                </form>
            </div>
        </section>

        <!-- Filtros rápidos por fecha -->
        <section style="background: white; padding: 2rem 0; border-top: 1px solid #eee;">
            <div class="container">
                <div style="display: flex; justify-content: center; gap: 1rem; flex-wrap: wrap;">
                    <a href="events.php" class="btn <?php echo empty($date) ? 'btn-primary' : 'btn-outline'; ?>" style="border-radius: 25px;">
                        Todos los días
                    </a>
                    <a href="events.php?date=<?php echo date('Y-m-d'); ?>" class="btn <?php echo $date === date('Y-m-d') ? 'btn-primary' : 'btn-outline'; ?>" style="border-radius: 25px;">
                        Hoy
                    </a>
                    <a href="events.php?date=<?php echo date('Y-m-d', strtotime('tomorrow')); ?>" class="btn <?php echo $date === date('Y-m-d', strtotime('tomorrow')) ? 'btn-primary' : 'btn-outline'; ?>" style="border-radius: 25px;">
                        Mañana
                    </a>
                    <a href="events.php?date=<?php echo date('Y-m-d', strtotime('this weekend')); ?>" class="btn <?php echo $date === date('Y-m-d', strtotime('this weekend')) ? 'btn-primary' : 'btn-outline'; ?>" style="border-radius: 25px;">
                        Este fin de semana
                    </a>
                    <a href="events.php?date=<?php echo date('Y-m-d', strtotime('next week')); ?>" class="btn btn-outline" style="border-radius: 25px;">
                        Próxima semana
                    </a>
                </div>
            </div>
        </section>

        <!-- Grid de Eventos -->
        <section class="events-section" style="padding: 3rem 0;">
            <div class="container">
                <?php if (!empty($search) || !empty($date) || !empty($genre) || $venue > 0): ?>
                    <div style="margin-bottom: 2rem; text-align: center;">
                        <h2 style="color: var(--primary-color); margin-bottom: 0.5rem;">Resultados de búsqueda</h2>
                        <p style="color: var(--gray-medium);">
                            <?php echo count($events); ?> evento(s) encontrado(s)
                            <?php if (!empty($genre)): ?>
                                en el género <strong><?php echo htmlspecialchars($genre); ?></strong>
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endif; ?>

                <div class="events-grid">
                    <?php foreach ($events as $event): ?>
                        <div class="event-card" data-event-id="<?php echo $event['id_show']; ?>">
                            <div class="event-image">
                                <?php if ($event['imagen']): ?>
                                    <img src="<?php echo htmlspecialchars($event['imagen']); ?>" 
                                         alt="<?php echo htmlspecialchars($event['Nombre']); ?>"
                                         style="width: 100%; height: 100%; object-fit: cover;">
                                <?php else: ?>
                                    🎵
                                <?php endif; ?>
                                
                                <!-- Badge de estado -->
                                <div style="position: absolute; top: 10px; left: 10px; background: rgba(40, 167, 69, 0.9); color: white; padding: 0.3rem 0.8rem; border-radius: 15px; font-size: 0.8rem;">
                                    <?php echo ucfirst($event['estado']); ?>
                                </div>
                                
                                <!-- Badge de género -->
                                <?php if ($event['generos']): ?>
                                    <div style="position: absolute; top: 10px; right: 10px; background: rgba(0,0,0,0.7); color: white; padding: 0.3rem 0.8rem; border-radius: 15px; font-size: 0.8rem;">
                                        <?php echo htmlspecialchars(explode(',', $event['generos'])[0]); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="event-details">
                                <h3 class="event-title"><?php echo htmlspecialchars($event['Nombre']); ?></h3>
                                
                                <?php if ($event['artistas']): ?>
                                    <div style="color: var(--accent-color); font-weight: bold; margin-bottom: 0.5rem; font-size: 0.9rem;">
                                        🎤 <?php echo htmlspecialchars($event['artistas']); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="event-info">
                                    <span>📅 <?php echo formatDate($event['Fecha']); ?></span>
                                    <span>🕐 <?php echo formatTime($event['Horario']); ?></span>
                                    <span>🏟️ <?php echo htmlspecialchars($event['venue_nombre']); ?></span>
                                    <span>👥 <?php echo number_format($event['venue_capacidad']); ?> personas</span>
                                    <?php if ($event['sectores_disponibles'] > 0): ?>
                                        <span style="color: var(--success);">🎫 <?php echo $event['sectores_disponibles']; ?> sectores disponibles</span>
                                    <?php else: ?>
                                        <span style="color: var(--danger);">⚠️ Agotado</span>
                                    <?php endif; ?>
                                </div>

                                <?php if ($event['descripcion']): ?>
                                    <p style="color: var(--gray-medium); margin: 1rem 0; line-height: 1.4; font-size: 0.9rem;">
                                        <?php echo htmlspecialchars(substr($event['descripcion'], 0, 100)) . (strlen($event['descripcion']) > 100 ? '...' : ''); ?>
                                    </p>
                                <?php endif; ?>
                                
                                <div style="display: flex; justify-content: space-between; align-items: center; margin: 1rem 0;">
                                    <div class="event-price">
                                        <?php if ($event['precio_minimo'] && $event['precio_maximo']): ?>
                                            <?php if ($event['precio_minimo'] == $event['precio_maximo']): ?>
                                                <?php echo formatPrice($event['precio_minimo']); ?>
                                            <?php else: ?>
                                                Desde <?php echo formatPrice($event['precio_minimo']); ?>
                                                <small style="color: var(--gray-medium); display: block; font-size: 0.8rem;">
                                                    hasta <?php echo formatPrice($event['precio_maximo']); ?>
                                                </small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span style="color: var(--gray-medium);">Precio a confirmar</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div style="display: flex; gap: 0.5rem;">
                                    <a href="event.php?id=<?php echo $event['id_show']; ?>" class="btn btn-primary" style="flex: 2;">
                                        <?php echo $event['sectores_disponibles'] > 0 ? 'Comprar Tickets' : 'Ver Detalles'; ?>
                                    </a>
                                    <button onclick="addToWishlist(<?php echo $event['id_show']; ?>)" class="btn btn-outline" style="flex: 1;" title="Agregar a favoritos">
                                        ❤️
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if (empty($events)): ?>
                    <div style="text-align: center; padding: 4rem 2rem; color: var(--gray-medium);">
                        <div style="font-size: 5rem; margin-bottom: 1rem;">🔍</div>
                        <h3>No se encontraron eventos</h3>
                        <p>Intenta modificar los filtros de búsqueda o explorar diferentes fechas</p>
                        <div style="margin-top: 2rem; display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                            <a href="events.php" class="btn btn-primary">Ver todos los eventos</a>
                            <a href="artists.php" class="btn btn-outline">Explorar artistas</a>
                            <a href="venues.php" class="btn btn-outline">Explorar venues</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Información adicional -->
        <section style="background: var(--gray-light); padding: 4rem 0;">
            <div class="container">
                <h2 style="text-align: center; margin-bottom: 3rem; color: var(--primary-color);">
                    ¿Por qué elegir TickFast?
                </h2>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 3rem;">
                    <div style="text-align: center; padding: 2rem;">
                        <div style="font-size: 4rem; margin-bottom: 1rem;">🎫</div>
                        <h3>Tickets Garantizados</h3>
                        <p>Todos nuestros tickets son originales y verificados. Compra con total confianza.</p>
                    </div>
                    
                    <div style="text-align: center; padding: 2rem;">
                        <div style="font-size: 4rem; margin-bottom: 1rem;">⚡</div>
                        <h3>Compra Instantánea</h3>
                        <p>Proceso de compra rápido y seguro. Recibe tus tickets al instante por email.</p>
                    </div>
                    
                    <div style="text-align: center; padding: 2rem;">
                        <div style="font-size: 4rem; margin-bottom: 1rem;">🎵</div>
                        <h3>Los Mejores Eventos</h3>
                        <p>Acceso exclusivo a los eventos más importantes y demandados del país.</p>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem; margin-bottom: 2rem;">
                <div>
                    <h4 style="margin-bottom: 1rem;">TickFast</h4>
                    <p>La plataforma líder en venta de tickets para eventos en vivo en Argentina.</p>
                </div>
                
                <div>
                    <h4 style="margin-bottom: 1rem;">Enlaces Rápidos</h4>
                    <ul style="list-style: none;">
                        <li><a href="events.php" style="color: var(--text-light); text-decoration: none;">Eventos</a></li>
                        <li><a href="venues.php" style="color: var(--text-light); text-decoration: none;">Venues</a></li>
                        <li><a href="artists.php" style="color: var(--text-light); text-decoration: none;">Artistas</a></li>
                    </ul>
                </div>
                
                <div>
                    <h4 style="margin-bottom: 1rem;">Soporte</h4>
                    <ul style="list-style: none;">
                        <li><a href="help.php" style="color: var(--text-light); text-decoration: none;">Ayuda</a></li>
                        <li><a href="contact.php" style="color: var(--text-light); text-decoration: none;">Contacto</a></li>
                        <li><a href="terms.php" style="color: var(--text-light); text-decoration: none;">Términos</a></li>
                    </ul>
                </div>
                
                <div>
                    <h4 style="margin-bottom: 1rem;">Síguenos</h4>
                    <div style="display: flex; gap: 1rem;">
                        <a href="#" style="color: var(--text-light); text-decoration: none; font-size: 1.5rem;">📘</a>
                        <a href="#" style="color: var(--text-light); text-decoration: none; font-size: 1.5rem;">📷</a>
                        <a href="#" style="color: var(--text-light); text-decoration: none; font-size: 1.5rem;">🐦</a>
                    </div>
                </div>
            </div>
            
            <div style="text-align: center; padding-top: 2rem; border-top: 1px solid rgba(255,255,255,0.2);">
                <p>&copy; 2025 TickFast. Todos los derechos reservados.</p>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="main.js"></script>
    
    <script>
        // Código específico para la página de eventos
        document.addEventListener('DOMContentLoaded', function() {
            // Animación de entrada para las cards
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };
            
            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, observerOptions);
            
            // Aplicar animación a las event cards
            document.querySelectorAll('.event-card').forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(50px)';
                card.style.transition = `opacity 0.6s ease ${index * 0.1}s, transform 0.6s ease ${index * 0.1}s`;
                observer.observe(card);
                
                // Efecto hover más elaborado
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-15px) scale(1.03)';
                    this.style.boxShadow = '0 15px 35px rgba(0, 0, 0, 0.2)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0) scale(1)';
                    this.style.boxShadow = 'var(--box-shadow)';
                });
            });

            // Función para ordenar eventos
            setupEventSorting();
            
            // Función para búsqueda en tiempo real
            setupRealTimeSearch();
        });

        function setupEventSorting() {
            // Agregar opciones de ordenamiento
            const container = document.querySelector('.events-section .container');
            const eventsGrid = document.querySelector('.events-grid');
            
            if (container && eventsGrid && eventsGrid.children.length > 1) {
                const sortContainer = document.createElement('div');
                sortContainer.style.cssText = 'text-align: center; margin-bottom: 2rem;';
                sortContainer.innerHTML = `
                    <div style="display: inline-flex; gap: 1rem; align-items: center; background: white; padding: 1rem; border-radius: var(--border-radius); box-shadow: var(--box-shadow);">
                        <span style="font-weight: bold; color: var(--primary-color);">Ordenar por:</span>
                        <select id="eventSort" style="padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px;">
                            <option value="date-asc">Fecha (más próximo)</option>
                            <option value="date-desc">Fecha (más lejano)</option>
                            <option value="price-asc">Precio (menor a mayor)</option>
                            <option value="price-desc">Precio (mayor a menor)</option>
                            <option value="name-asc">Nombre (A-Z)</option>
                            <option value="name-desc">Nombre (Z-A)</option>
                        </select>
                    </div>
                `;
                
                container.insertBefore(sortContainer, eventsGrid);
                
                document.getElementById('eventSort').addEventListener('change', function() {
                    sortEvents(this.value);
                });
            }
        }

        function sortEvents(sortBy) {
            const eventsGrid = document.querySelector('.events-grid');
            const events = Array.from(eventsGrid.children);
            
            events.sort((a, b) => {
                switch(sortBy) {
                    case 'date-asc':
                        return new Date(getEventDate(a)) - new Date(getEventDate(b));
                    case 'date-desc':
                        return new Date(getEventDate(b)) - new Date(getEventDate(a));
                    case 'price-asc':
                        return getEventPrice(a) - getEventPrice(b);
                    case 'price-desc':
                        return getEventPrice(b) - getEventPrice(a);
                    case 'name-asc':
                        return getEventName(a).localeCompare(getEventName(b));
                    case 'name-desc':
                        return getEventName(b).localeCompare(getEventName(a));
                    default:
                        return 0;
                }
            });
            
            // Reorganizar elementos en el DOM
            events.forEach(event => eventsGrid.appendChild(event));
            
            // Reaplicar animaciones
            events.forEach((event, index) => {
                event.style.opacity = '0';
                event.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    event.style.opacity = '1';
                    event.style.transform = 'translateY(0)';
                }, index * 50);
            });
        }

        function getEventDate(eventCard) {
            const dateText = eventCard.querySelector('.event-info span').textContent;
            return dateText.replace('📅 ', '');
        }

        function getEventPrice(eventCard) {
            const priceText = eventCard.querySelector('.event-price').textContent;
            const price = priceText.match(/[\d,]+/);
            return price ? parseInt(price[0].replace(',', '')) : 0;
        }

        function getEventName(eventCard) {
            return eventCard.querySelector('.event-title').textContent;
        }

        function setupRealTimeSearch() {
            const searchInput = document.querySelector('input[name="search"]');
            if (searchInput) {
                let searchTimeout;
                searchInput.addEventListener('input', function() {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        // Feedback visual de búsqueda
                        if (this.value.length > 2) {
                            this.style.borderColor = 'var(--success)';
                            this.style.boxShadow = '0 0 0 3px rgba(40, 167, 69, 0.1)';
                        } else {
                            this.style.borderColor = '';
                            this.style.boxShadow = '';
                        }
                    }, 300);
                });
            }
        }

        // Función para agregar a wishlist (lista de deseos)
        function addToWishlist(eventId) {
            if (!isUserLoggedIn()) {
                showAlert('Debes iniciar sesión para agregar eventos a favoritos', 'warning');
                return;
            }
            
            fetch('wishlist.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'add',
                    event_id: eventId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Evento agregado a favoritos', 'success');
                    // Cambiar el ícono del botón
                    event.target.innerHTML = '💖';
                    event.target.style.background = 'var(--accent-color)';
                    event.target.style.color = 'white';
                } else {
                    showAlert(data.message || 'Error al agregar a favoritos', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Error al agregar a favoritos', 'error');
            });
        }

        // Función para filtros rápidos por precio
        function quickPriceFilter(min, max) {
            const form = document.querySelector('form[method="GET"]');
            const minInput = form.querySelector('input[name="price_min"]');
            const maxInput = form.querySelector('input[name="price_max"]');
            
            minInput.value = min || '';
            maxInput.value = max || '';
            form.submit();
        }

        // Función para compartir evento
        function shareEvent(eventId, eventName) {
            if (navigator.share) {
                navigator.share({
                    title: eventName,
                    text: `¡Mira este increíble evento: ${eventName}!`,
                    url: `${window.location.origin}/event.php?id=${eventId}`
                });
            } else {
                // Fallback: copiar al portapapeles
                const url = `${window.location.origin}/event.php?id=${eventId}`;
                navigator.clipboard.writeText(url).then(() => {
                    showAlert('Enlace copiado al portapapeles', 'success');
                });
            }
        }

        // Función para mostrar mapa de venue
        function showVenueMap(venueName, venueAddress) {
            const mapUrl = `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(venueAddress)}`;
            window.open(mapUrl, '_blank');
        }

        // Auto-refresh para eventos con pocos tickets
        function setupAutoRefresh() {
            const lowStockEvents = document.querySelectorAll('[data-low-stock="true"]');
            if (lowStockEvents.length > 0) {
                setInterval(() => {
                    // Verificar disponibilidad cada 30 segundos
                    lowStockEvents.forEach(eventCard => {
                        const eventId = eventCard.getAttribute('data-event-id');
                        checkEventAvailability(eventId);
                    });
                }, 30000);
            }
        }

        function checkEventAvailability(eventId) {
            fetch(`api/event-availability.php?id=${eventId}`)
                .then(response => response.json())
                .then(data => {
                    const eventCard = document.querySelector(`[data-event-id="${eventId}"]`);
                    if (eventCard && data.available_sectors === 0) {
                        // Marcar como agotado
                        const button = eventCard.querySelector('.btn-primary');
                        button.textContent = 'Agotado';
                        button.classList.remove('btn-primary');
                        button.classList.add('btn-secondary');
                        button.disabled = true;
                    }
                });
        }

        // Inicializar funciones adicionales
        document.addEventListener('DOMContentLoaded', function() {
            setupAutoRefresh();
            
            // Agregar tooltips a los badges
            document.querySelectorAll('[title]').forEach(element => {
                element.addEventListener('mouseenter', function() {
                    // Implementar tooltip personalizado si es necesario
                });
            });
            
            // Lazy loading para imágenes de eventos
            if ('IntersectionObserver' in window) {
                const imageObserver = new IntersectionObserver((entries, observer) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const img = entry.target;
                            if (img.dataset.src) {
                                img.src = img.dataset.src;
                                img.removeAttribute('data-src');
                                observer.unobserve(img);
                            }
                        }
                    });
                });

                document.querySelectorAll('img[data-src]').forEach(img => {
                    imageObserver.observe(img);
                });
            }
        });

        // Funciones para mejorar la experiencia del usuario
        function showEventPreview(eventId) {
            // Mostrar preview rápido del evento en un modal
            fetch(`api/event-preview.php?id=${eventId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showEventModal(data.event);
                    }
                });
        }

        function showEventModal(event) {
            const modal = document.createElement('div');
            modal.className = 'modal';
            modal.style.display = 'block';
            modal.innerHTML = `
                <div class="modal-content" style="max-width: 600px;">
                    <span class="close">&times;</span>
                    <h2>${event.name}</h2>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin: 2rem 0;">
                        <div>
                            <h3>Detalles del Evento</h3>
                            <p><strong>Fecha:</strong> ${event.date}</p>
                            <p><strong>Hora:</strong> ${event.time}</p>
                            <p><strong>Venue:</strong> ${event.venue}</p>
                            <p><strong>Artistas:</strong> ${event.artists}</p>
                        </div>
                        <div>
                            <h3>Información de Tickets</h3>
                            <p><strong>Desde:</strong> ${event.min_price}</p>
                            <p><strong>Sectores disponibles:</strong> ${event.sectors}</p>
                            <a href="event.php?id=${event.id}" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
                                Ver Detalles Completos
                            </a>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            // Cerrar modal
            modal.querySelector('.close').addEventListener('click', () => {
                document.body.removeChild(modal);
            });
            
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    document.body.removeChild(modal);
                }
            });
        }
    </script>
</body>
</html>