<?php
require_once './config/db_connection.php';

// Obtener todos los artistas activos
$db = getDB();

// Manejar filtros
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$genre = isset($_GET['genre']) ? trim($_GET['genre']) : '';

// Construir query con filtros
$whereClause = "WHERE a.activo = 1";
$params = [];

if (!empty($search)) {
    $whereClause .= " AND (a.Nombre LIKE ? OR a.Apellido LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($genre)) {
    $whereClause .= " AND a.genero = ?";
    $params[] = $genre;
}

$stmt = $db->prepare("
    SELECT a.*, 
           COUNT(DISTINCT sa.id_show) as total_shows,
           GROUP_CONCAT(DISTINCT s.Nombre SEPARATOR ', ') as proximos_shows
    FROM Artistas a
    LEFT JOIN Show_Artistas sa ON a.id_artista = sa.id_artista
    LEFT JOIN Shows s ON sa.id_show = s.id_show AND s.estado = 'activo' AND s.Fecha >= CURDATE()
    $whereClause
    GROUP BY a.id_artista
    ORDER BY a.Nombre ASC, a.Apellido ASC
");

$stmt->execute($params);
$artists = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener géneros únicos para el filtro
$stmt_genres = $db->prepare("SELECT DISTINCT genero FROM Artistas WHERE genero IS NOT NULL AND activo = 1 ORDER BY genero");
$stmt_genres->execute();
$genres = $stmt_genres->fetchAll(PDO::FETCH_COLUMN);

// Obtener estadísticas
$stmt_stats = $db->prepare("
    SELECT 
        COUNT(*) as total_artistas,
        COUNT(DISTINCT genero) as total_generos,
        (SELECT COUNT(*) FROM Show_Artistas sa 
         JOIN Shows s ON sa.id_show = s.id_show 
         WHERE s.estado = 'activo' AND s.Fecha >= CURDATE()) as shows_activos
    FROM Artistas WHERE activo = 1
");
$stmt_stats->execute();
$stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Artistas - TickFast</title>
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
                <li><a href="artists.php" class="nav-link active">Artistas</a></li>
                
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
                <h1 style="font-size: 3rem; margin-bottom: 1rem;">🎤 Artistas</h1>
                <p style="font-size: 1.2rem; opacity: 0.9;">Conoce a los mejores artistas de la escena musical argentina</p>
            </div>
        </section>

        <!-- Estadísticas -->
        <section style="background: var(--gray-light); padding: 2rem 0;">
            <div class="container">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 2rem; text-align: center;">
                    <div style="background: white; padding: 1.5rem; border-radius: var(--border-radius); box-shadow: var(--box-shadow);">
                        <div style="font-size: 2rem; color: var(--accent-color); margin-bottom: 0.5rem;"><?php echo number_format($stats['total_artistas']); ?></div>
                        <div style="font-weight: bold;">Artistas Disponibles</div>
                    </div>
                    <div style="background: white; padding: 1.5rem; border-radius: var(--border-radius); box-shadow: var(--box-shadow);">
                        <div style="font-size: 2rem; color: var(--accent-color); margin-bottom: 0.5rem;"><?php echo number_format($stats['total_generos']); ?></div>
                        <div style="font-weight: bold;">Géneros Musicales</div>
                    </div>
                    <div style="background: white; padding: 1.5rem; border-radius: var(--border-radius); box-shadow: var(--box-shadow);">
                        <div style="font-size: 2rem; color: var(--accent-color); margin-bottom: 0.5rem;"><?php echo number_format($stats['shows_activos']); ?></div>
                        <div style="font-weight: bold;">Shows Activos</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Sección de Búsqueda y Filtros -->
        <section class="search-section">
            <div class="container">
                <h2>Encuentra tu artista favorito</h2>
                <form method="GET" class="search-form">
                    <div class="form-group">
                        <input type="text" name="search" class="form-control" 
                               placeholder="Buscar artista por nombre..." 
                               value="<?php echo htmlspecialchars($search); ?>">
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
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                    <a href="artists.php" class="btn btn-secondary">Limpiar</a>
                </form>
            </div>
        </section>

        <!-- Géneros Populares -->
        <?php if (empty($search) && empty($genre)): ?>
        <section style="padding: 3rem 0; background: var(--gray-light);">
            <div class="container">
                <h2 style="text-align: center; margin-bottom: 2rem; color: var(--primary-color);">
                    Explora por Género
                </h2>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem;">
                    <?php foreach ($genres as $index => $g): ?>
                        <a href="artists.php?genre=<?php echo urlencode($g); ?>" 
                           style="background: linear-gradient(45deg, var(--primary-color), var(--accent-color)); 
                                  color: white; padding: 2rem; border-radius: var(--border-radius); 
                                  text-decoration: none; text-align: center; transition: var(--transition);
                                  display: block;">
                            <div style="font-size: 3rem; margin-bottom: 1rem;">
                                <?php 
                                $icons = ['🎸', '🎤', '🎵', '🎼', '🎹', '🥁', '🎺', '🎻'];
                                echo $icons[$index % count($icons)]; 
                                ?>
                            </div>
                            <h3 style="margin: 0;"><?php echo htmlspecialchars($g); ?></h3>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <!-- Grid de Artistas -->
        <section class="artists-section" style="padding: 3rem 0;">
            <div class="container">
                <?php if (!empty($search) || !empty($genre)): ?>
                    <h2 style="text-align: center; margin-bottom: 2rem; color: var(--primary-color);">
                        Resultados de búsqueda
                        <?php if (!empty($genre)): ?>
                            - <?php echo htmlspecialchars($genre); ?>
                        <?php endif; ?>
                    </h2>
                <?php endif; ?>

                <div class="artists-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
                    <?php foreach ($artists as $artist): ?>
                        <div class="artist-card" style="background: white; border-radius: var(--border-radius); overflow: hidden; box-shadow: var(--box-shadow); transition: var(--transition);">
                            <div class="artist-image" style="width: 100%; height: 250px; background: linear-gradient(45deg, var(--primary-color), var(--accent-color)); display: flex; align-items: center; justify-content: center; color: var(--text-light); font-size: 5rem; position: relative;">
                                <?php if ($artist['imagen']): ?>
                                    <img src="<?php echo htmlspecialchars($artist['imagen']); ?>" 
                                         alt="<?php echo htmlspecialchars($artist['Nombre'] . ' ' . $artist['Apellido']); ?>"
                                         style="width: 100%; height: 100%; object-fit: cover;">
                                <?php else: ?>
                                    🎤
                                <?php endif; ?>
                                
                                <!-- Badge de género -->
                                <?php if ($artist['genero']): ?>
                                    <div style="position: absolute; top: 10px; right: 10px; background: rgba(0,0,0,0.7); color: white; padding: 0.3rem 0.8rem; border-radius: 15px; font-size: 0.8rem;">
                                        <?php echo htmlspecialchars($artist['genero']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div style="padding: 1.5rem;">
                                <h3 style="color: var(--primary-color); margin-bottom: 0.5rem; font-size: 1.4rem;">
                                    <?php echo htmlspecialchars($artist['Nombre'] . ' ' . $artist['Apellido']); ?>
                                </h3>
                                
                                <div style="display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 1rem; color: var(--gray-medium);">
                                    <?php if ($artist['genero']): ?>
                                        <span>🎵 <?php echo htmlspecialchars($artist['genero']); ?></span>
                                    <?php endif; ?>
                                    
                                    <?php if ($artist['total_shows'] > 0): ?>
                                        <span style="color: var(--success);">🎫 <?php echo $artist['total_shows']; ?> eventos próximos</span>
                                    <?php else: ?>
                                        <span style="color: var(--gray-medium);">📅 Sin eventos próximos</span>
                                    <?php endif; ?>
                                </div>

                                <?php if ($artist['descripcion']): ?>
                                    <p style="color: var(--gray-medium); margin-bottom: 1rem; line-height: 1.4;">
                                        <?php echo htmlspecialchars(substr($artist['descripcion'], 0, 120)) . (strlen($artist['descripcion']) > 120 ? '...' : ''); ?>
                                    </p>
                                <?php endif; ?>

                                <?php if ($artist['proximos_shows']): ?>
                                    <div style="background: var(--gray-light); padding: 1rem; border-radius: var(--border-radius); margin-bottom: 1rem;">
                                        <h4 style="margin: 0 0 0.5rem 0; color: var(--primary-color); font-size: 0.9rem;">Próximos Shows:</h4>
                                        <p style="margin: 0; color: var(--gray-medium); font-size: 0.9rem; line-height: 1.3;">
                                            <?php echo htmlspecialchars(substr($artist['proximos_shows'], 0, 100)) . (strlen($artist['proximos_shows']) > 100 ? '...' : ''); ?>
                                        </p>
                                    </div>
                                <?php endif; ?>
                                
                                <div style="display: flex; gap: 1rem;">
                                    <a href="artist.php?id=<?php echo $artist['id_artista']; ?>" class="btn btn-primary" style="flex: 1; text-align: center;">
                                        Ver Perfil
                                    </a>
                                    <?php if ($artist['total_shows'] > 0): ?>
                                        <a href="events.php?artist=<?php echo $artist['id_artista']; ?>" class="btn btn-outline" style="flex: 1; text-align: center;">
                                            Ver Eventos
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if (empty($artists)): ?>
                    <div style="text-align: center; padding: 3rem; color: var(--gray-medium);">
                        <div style="font-size: 4rem; margin-bottom: 1rem;">🔍</div>
                        <h3>No se encontraron artistas</h3>
                        <p>Intenta modificar los filtros de búsqueda</p>
                        <a href="artists.php" class="btn btn-primary" style="margin-top: 1rem;">Ver todos los artistas</a>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Información adicional -->
        <section style="background: var(--gray-light); padding: 4rem 0;">
            <div class="container">
                <h2 style="text-align: center; margin-bottom: 3rem; color: var(--primary-color);">
                    La mejor música en vivo
                </h2>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 3rem;">
                    <div style="text-align: center; padding: 2rem;">
                        <div style="font-size: 4rem; margin-bottom: 1rem;">⭐</div>
                        <h3>Artistas de Primera</h3>
                        <p>Los mejores exponentes de cada género musical, desde leyendas hasta nuevos talentos emergentes.</p>
                    </div>
                    
                    <div style="text-align: center; padding: 2rem;">
                        <div style="font-size: 4rem; margin-bottom: 1rem;">🎭</div>
                        <h3>Experiencias Únicas</h3>
                        <p>Desde conciertos íntimos hasta espectáculos masivos, cada show es una experiencia inolvidable.</p>
                    </div>
                    
                    <div style="text-align: center; padding: 2rem;">
                        <div style="font-size: 4rem; margin-bottom: 1rem;">🎪</div>
                        <h3>Variedad de Géneros</h3>
                        <p>Rock, pop, jazz, folklore, tango y más. Encuentra tu género favorito y descubre nuevos sonidos.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Artistas Destacados (solo si no hay filtros aplicados) -->
        <?php if (empty($search) && empty($genre) && !empty($artists)): ?>
        <section style="padding: 4rem 0;">
            <div class="container">
                <h2 style="text-align: center; margin-bottom: 3rem; color: var(--primary-color);">
                    Artistas con Más Shows
                </h2>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem;">
                    <?php 
                    // Ordenar artistas por número de shows y tomar los primeros 6
                    $featured_artists = array_slice(
                        array_filter($artists, function($a) { return $a['total_shows'] > 0; }), 
                        0, 6
                    );
                    usort($featured_artists, function($a, $b) { return $b['total_shows'] - $a['total_shows']; });
                    ?>
                    
                    <?php foreach ($featured_artists as $artist): ?>
                        <div style="background: white; padding: 1.5rem; border-radius: var(--border-radius); box-shadow: var(--box-shadow); text-align: center; transition: var(--transition);" 
                             onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='translateY(0)'">
                            <div style="width: 80px; height: 80px; background: linear-gradient(45deg, var(--primary-color), var(--accent-color)); border-radius: 50%; margin: 0 auto 1rem; display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem;">
                                🎤
                            </div>
                            <h4 style="color: var(--primary-color); margin-bottom: 0.5rem;">
                                <?php echo htmlspecialchars($artist['Nombre'] . ' ' . $artist['Apellido']); ?>
                            </h4>
                            <p style="color: var(--gray-medium); margin-bottom: 1rem;">
                                <?php echo htmlspecialchars($artist['genero']); ?>
                            </p>
                            <div style="background: var(--accent-color); color: white; padding: 0.5rem 1rem; border-radius: 15px; font-size: 0.9rem; font-weight: bold;">
                                <?php echo $artist['total_shows']; ?> shows activos
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if (empty($featured_artists)): ?>
                    <p style="text-align: center; color: var(--gray-medium); font-style: italic;">
                        No hay artistas con shows activos en este momento.
                    </p>
                <?php endif; ?>
            </div>
        </section>
        <?php endif; ?>
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
        // Código específico para la página de artistas
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
            
            // Aplicar animación a las artist cards
            document.querySelectorAll('.artist-card').forEach((card, index) => {
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

            // Efecto parallax para los géneros
            const genreCards = document.querySelectorAll('section a[href*="genre="]');
            genreCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.05) rotate(2deg)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1) rotate(0deg)';
                });
            });

            // Búsqueda en tiempo real (opcional)
            const searchInput = document.querySelector('input[name="search"]');
            if (searchInput) {
                let searchTimeout;
                searchInput.addEventListener('input', function() {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        // Aquí podrías implementar búsqueda AJAX en tiempo real
                        // Por ahora solo mostramos feedback visual
                        if (this.value.length > 2) {
                            this.style.borderColor = 'var(--success)';
                        } else {
                            this.style.borderColor = '';
                        }
                    }, 300);
                });
            }
        });

        // Función para filtrar artistas por género (desde los botones de género)
        function filterByGenre(genre) {
            window.location.href = 'artists.php?genre=' + encodeURIComponent(genre);
        }

        // Función para limpiar filtros
        function clearFilters() {
            window.location.href = 'artists.php';
        }
    </script>
</body>
</html>