<?php
require_once './config/db_connection.php';

// Obtener eventos destacados
$db = getDB();
$stmt = $db->prepare("
    SELECT s.*, v.nombre as venue_nombre, v.Direccion as venue_direccion,
           MIN(sec.Capacidad * (v.PrecioBase + (v.PrecioBase * sec.Porc_agr / 100))) as precio_minimo
    FROM Shows s
    JOIN Venue v ON s.id_venue = v.id_venue
    JOIN Sector sec ON v.id_venue = sec.idVenue
    WHERE s.estado = 'activo' AND s.Fecha >= CURDATE()
    GROUP BY s.id_show
    ORDER BY s.Fecha ASC
    LIMIT 12
");
$stmt->execute();
$eventos_destacados = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener géneros para filtros
$stmt = $db->prepare("SELECT DISTINCT genero FROM Artistas WHERE genero IS NOT NULL AND activo = 1");
$stmt->execute();
$generos = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TickFast - Tu plataforma de tickets favorita</title>
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
        <section class="hero">
            <div class="carrousel-img">
                <img src="./assets/hero.jpg" alt="Hero Image" style="width: 100%; height: 100%; object-fit: cover;">
                
            </div>
        </section>

        <!-- Sección de Búsqueda -->
        <section id="search" class="search-section">
            <div class="container">
                <h2>Encuentra tu evento perfecto</h2>
                <form id="searchForm" class="search-form">
                    <div class="form-group">
                        <input type="text" id="searchInput" class="form-control" placeholder="Buscar artista, evento o venue...">
                    </div>
                    <div class="form-group">
                        <input type="date" id="filterDate" class="form-control" min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <select id="filterGenre" class="form-control">
                            <option value="">Todos los géneros</option>
                            <?php foreach ($generos as $genero): ?>
                                <option value="<?php echo htmlspecialchars($genero); ?>">
                                    <?php echo htmlspecialchars($genero); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Buscar</button>
                </form>
            </div>
        </section>

        <!-- Eventos Destacados -->
        <section class="events-section">
            <div class="container">
                <h2 style="text-align: center; margin: 2rem 0; color: var(--primary-color);">
                    Eventos Destacados
                </h2>
                
                <div class="events-grid">
                    <?php foreach ($eventos_destacados as $evento): ?>
                        <div class="event-card" data-event-id="<?php echo $evento['id_show']; ?>">
                            <div class="event-image">
                                <?php if ($evento['imagen']): ?>
                                    <img src="<?php echo htmlspecialchars($evento['imagen']); ?>" 
                                         alt="<?php echo htmlspecialchars($evento['Nombre']); ?>"
                                         style="width: 100%; height: 100%; object-fit: cover;">
                                <?php else: ?>
                                    🎵
                                <?php endif; ?>
                            </div>
                            <div class="event-details">
                                <h3 class="event-title"><?php echo htmlspecialchars($evento['Nombre']); ?></h3>
                                <div class="event-info">
                                    <span>📅 <?php echo formatDate($evento['Fecha']); ?></span>
                                    <span>🕒 <?php echo formatTime($evento['Horario']); ?></span>
                                    <span>📍 <?php echo htmlspecialchars($evento['venue_nombre']); ?></span>
                                </div>
                                <div class="event-price">
                                    Desde <?php echo formatPrice($evento['precio_minimo']); ?>
                                </div>
                                <a href="event.php?id=<?php echo $evento['id_show']; ?>" class="btn btn-primary">
                                    Ver Tickets
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if (empty($eventos_destacados)): ?>
                    <div style="text-align: center; padding: 3rem; color: var(--gray-medium);">
                        <h3>No hay eventos disponibles en este momento</h3>
                        <p>Vuelve pronto para descubrir nuevos eventos</p>
                    </div>
                <?php endif; ?>
                
                <div style="text-align: center; margin-top: 3rem;">
                    <a href="events.php" class="btn btn-outline">Ver Todos los Eventos</a>
                </div>
            </div>
        </section>

        <!-- Sección de Categorías -->
        <section class="categories-section" style="background: var(--gray-light); padding: 4rem 0;">
            <div class="container">
                <h2 style="text-align: center; margin-bottom: 3rem; color: var(--primary-color);">
                    Explora por Categoría
                </h2>
                
                <div class="categories-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem;">
                    <?php foreach ($generos as $index => $genero): ?>
                        <a href="events.php?genre=<?php echo urlencode($genero); ?>" 
                           class="category-card" 
                           style="background: linear-gradient(45deg, var(--primary-color), var(--accent-color)); 
                                  color: white; padding: 2rem; border-radius: var(--border-radius); 
                                  text-decoration: none; text-align: center; transition: var(--transition);">
                            <div style="font-size: 3rem; margin-bottom: 1rem;">
                                <?php 
                                $icons = ['🎸', '🎤', '🎵', '🎼', '🎹', '🥁'];
                                echo $icons[$index % count($icons)]; 
                                ?>
                            </div>
                            <h3><?php echo htmlspecialchars($genero); ?></h3>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- Sección de Información -->
        <section class="info-section" style="padding: 4rem 0;">
            <div class="container">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 3rem;">
                    <div class="info-card" style="text-align: center; padding: 2rem;">
                        <div style="font-size: 4rem; margin-bottom: 1rem;">🎫</div>
                        <h3>Tickets Seguros</h3>
                        <p>Compra con confianza. Todos nuestros tickets son verificados y garantizados.</p>
                    </div>
                    
                    <div class="info-card" style="text-align: center; padding: 2rem;">
                        <div style="font-size: 4rem; margin-bottom: 1rem;">⚡</div>
                        <h3>Entrega Rápida</h3>
                        <p>Recibe tus tickets al instante por email o descárgalos desde tu perfil.</p>
                    </div>
                    
                    <div class="info-card" style="text-align: center; padding: 2rem;">
                        <div style="font-size: 4rem; margin-bottom: 1rem;">🎵</div>
                        <h3>Mejores Eventos</h3>
                        <p>Los artistas más importantes y los venues más prestigiosos del país.</p>
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
        // Código específico para la página principal
        document.addEventListener('DOMContentLoaded', function() {
            // Efecto parallax en el hero
            window.addEventListener('scroll', function() {
                const hero = document.querySelector('.hero');
                const scrolled = window.pageYOffset;
                const rate = scrolled * -0.5;
                hero.style.transform = `translateY(${rate}px)`;
            });
            
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
            });
        });
    </script>
</body>
</html>