<?php
require_once './config/db_connection.php';

// Obtener todos los venues activos
$db = getDB();

// Manejar filtros
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$capacity_min = isset($_GET['capacity_min']) ? (int)$_GET['capacity_min'] : 0;
$capacity_max = isset($_GET['capacity_max']) ? (int)$_GET['capacity_max'] : 999999;

// Construir query con filtros
$whereClause = "WHERE v.activo = 1";
$params = [];

if (!empty($search)) {
    $whereClause .= " AND (v.nombre LIKE ? OR v.Direccion LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($capacity_min > 0) {
    $whereClause .= " AND v.Capacidad >= ?";
    $params[] = $capacity_min;
}

if ($capacity_max < 999999) {
    $whereClause .= " AND v.Capacidad <= ?";
    $params[] = $capacity_max;
}

$stmt = $db->prepare("
    SELECT v.*, 
           COUNT(s.id_show) as total_shows,
           MIN(v.PrecioBase) as precio_min
    FROM Venue v
    LEFT JOIN Shows s ON v.id_venue = s.id_venue AND s.estado = 'activo' AND s.Fecha >= CURDATE()
    $whereClause
    GROUP BY v.id_venue
    ORDER BY v.nombre ASC
");

$stmt->execute($params);
$venues = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener estadísticas
$stmt_stats = $db->prepare("
    SELECT 
        COUNT(*) as total_venues,
        AVG(Capacidad) as capacidad_promedio,
        MAX(Capacidad) as capacidad_maxima
    FROM Venue WHERE activo = 1
");
$stmt_stats->execute();
$stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Venues - TickFast</title>
    <link rel="stylesheet" href="./assets/styles.css">
</head>
<body class="<?php echo isLoggedIn() ? 'logged-in' : ''; ?>">
    <!-- Header -->
    <header class="header">
        <nav class="navbar">
            <img src="./assets/img/tickfast.png" height="60" alt="TickFast Logo" class="logo">
            
            <ul class="nav-menu">
                <li><a href="index.php" class="nav-link">Inicio</a></li>
                <li><a href="venues.php" class="nav-link active">Venues</a></li>
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
        <section class="hero" style="padding: 3rem 2rem; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <div class="container" style="text-align: center; color: white;">
                <h1 style="font-size: 3rem; margin-bottom: 1rem;">🏟️ Venues</h1>
                <p style="font-size: 1.2rem; opacity: 0.9;">Descubre los mejores espacios para eventos en Argentina</p>
            </div>
        </section>

        <!-- Estadísticas -->
        <section style="background: var(--gray-light); padding: 2rem 0;">
            <div class="container">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 2rem; text-align: center;">
                    <div style="background: white; padding: 1.5rem; border-radius: var(--border-radius); box-shadow: var(--box-shadow);">
                        <div style="font-size: 2rem; color: var(--accent-color); margin-bottom: 0.5rem;"><?php echo number_format($stats['total_venues']); ?></div>
                        <div style="font-weight: bold;">Venues Disponibles</div>
                    </div>
                    <div style="background: white; padding: 1.5rem; border-radius: var(--border-radius); box-shadow: var(--box-shadow);">
                        <div style="font-size: 2rem; color: var(--accent-color); margin-bottom: 0.5rem;"><?php echo number_format($stats['capacidad_promedio']); ?></div>
                        <div style="font-weight: bold;">Capacidad Promedio</div>
                    </div>
                    <div style="background: white; padding: 1.5rem; border-radius: var(--border-radius); box-shadow: var(--box-shadow);">
                        <div style="font-size: 2rem; color: var(--accent-color); margin-bottom: 0.5rem;"><?php echo number_format($stats['capacidad_maxima']); ?></div>
                        <div style="font-weight: bold;">Mayor Capacidad</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Sección de Búsqueda y Filtros -->
        <section class="search-section">
            <div class="container">
                <h2>Encuentra tu venue perfecto</h2>
                <form method="GET" class="search-form">
                    <div class="form-group">
                        <input type="text" name="search" class="form-control" 
                               placeholder="Buscar venue por nombre o ubicación..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="form-group">
                        <input type="number" name="capacity_min" class="form-control" 
                               placeholder="Capacidad mínima" min="0"
                               value="<?php echo $capacity_min > 0 ? $capacity_min : ''; ?>">
                    </div>
                    <div class="form-group">
                        <input type="number" name="capacity_max" class="form-control" 
                               placeholder="Capacidad máxima" min="0"
                               value="<?php echo $capacity_max < 999999 ? $capacity_max : ''; ?>">
                    </div>
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                    <a href="venues.php" class="btn btn-secondary">Limpiar</a>
                </form>
            </div>
        </section>

        <!-- Grid de Venues -->
        <section class="venues-section" style="padding: 3rem 0;">
            <div class="container">
                <div class="venues-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 2rem;">
                    <?php foreach ($venues as $venue): ?>
                        <div class="venue-card" style="background: white; border-radius: var(--border-radius); overflow: hidden; box-shadow: var(--box-shadow); transition: var(--transition);">
                            <div class="venue-image" style="width: 100%; height: 250px; background: linear-gradient(45deg, var(--primary-color), var(--accent-color)); display: flex; align-items: center; justify-content: center; color: var(--text-light); font-size: 4rem;">
                                <?php if ($venue['imagen']): ?>
                                    <img src="<?php echo htmlspecialchars($venue['imagen']); ?>" 
                                         alt="<?php echo htmlspecialchars($venue['nombre']); ?>"
                                         style="width: 100%; height: 100%; object-fit: cover;">
                                <?php else: ?>
                                    🏟️
                                <?php endif; ?>
                            </div>
                            
                            <div style="padding: 1.5rem;">
                                <h3 style="color: var(--primary-color); margin-bottom: 0.5rem; font-size: 1.3rem;">
                                    <?php echo htmlspecialchars($venue['nombre']); ?>
                                </h3>
                                
                                <div style="display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 1rem; color: var(--gray-medium);">
                                    <span>📍 <?php echo htmlspecialchars($venue['Direccion']); ?></span>
                                    <span>👥 Capacidad: <?php echo number_format($venue['Capacidad']); ?> personas</span>
                                    <span>💰 Desde <?php echo formatPrice($venue['PrecioBase']); ?></span>
                                    <?php if ($venue['total_shows'] > 0): ?>
                                        <span style="color: var(--success);">🎫 <?php echo $venue['total_shows']; ?> eventos próximos</span>
                                    <?php else: ?>
                                        <span style="color: var(--gray-medium);">📅 Sin eventos próximos</span>
                                    <?php endif; ?>
                                </div>

                                <?php if ($venue['descripcion']): ?>
                                    <p style="color: var(--gray-medium); margin-bottom: 1rem; line-height: 1.4;">
                                        <?php echo htmlspecialchars(substr($venue['descripcion'], 0, 120)) . (strlen($venue['descripcion']) > 120 ? '...' : ''); ?>
                                    </p>
                                <?php endif; ?>
                                
                                <div style="display: flex; gap: 1rem;">
                                    <a href="venue.php?id=<?php echo $venue['id_venue']; ?>" class="btn btn-primary" style="flex: 1; text-align: center;">
                                        Ver Detalles
                                    </a>
                                    <?php if ($venue['total_shows'] > 0): ?>
                                        <a href="events.php?venue=<?php echo $venue['id_venue']; ?>" class="btn btn-outline" style="flex: 1; text-align: center;">
                                            Ver Eventos
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if (empty($venues)): ?>
                    <div style="text-align: center; padding: 3rem; color: var(--gray-medium);">
                        <div style="font-size: 4rem; margin-bottom: 1rem;">🔍</div>
                        <h3>No se encontraron venues</h3>
                        <p>Intenta modificar los filtros de búsqueda</p>
                        <a href="venues.php" class="btn btn-primary" style="margin-top: 1rem;">Ver todos los venues</a>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Información adicional -->
        <section style="background: var(--gray-light); padding: 4rem 0;">
            <div class="container">
                <h2 style="text-align: center; margin-bottom: 3rem; color: var(--primary-color);">
                    ¿Por qué elegir nuestros venues?
                </h2>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 3rem;">
                    <div style="text-align: center; padding: 2rem;">
                        <div style="font-size: 4rem; margin-bottom: 1rem;">🏆</div>
                        <h3>Calidad Garantizada</h3>
                        <p>Todos nuestros venues cumplen con los más altos estándares de calidad y seguridad.</p>
                    </div>
                    
                    <div style="text-align: center; padding: 2rem;">
                        <div style="font-size: 4rem; margin-bottom: 1rem;">🎯</div>
                        <h3>Ubicaciones Estratégicas</h3>
                        <p>Venues ubicados en las mejores zonas, con fácil acceso y excelente conectividad.</p>
                    </div>
                    
                    <div style="text-align: center; padding: 2rem;">
                        <div style="font-size: 4rem; margin-bottom: 1rem;">🎪</div>
                        <h3>Versatilidad</h3>
                        <p>Espacios adaptables para diferentes tipos de eventos, desde conciertos hasta obras teatrales.</p>
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
        // Código específico para la página de venues
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
            
            // Aplicar animación a las venue cards
            document.querySelectorAll('.venue-card').forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(50px)';
                card.style.transition = `opacity 0.6s ease ${index * 0.1}s, transform 0.6s ease ${index * 0.1}s`;
                observer.observe(card);
                
                // Efecto hover
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-10px) scale(1.02)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0) scale(1)';
                });
            });
        });
    </script>
</body>
</html>