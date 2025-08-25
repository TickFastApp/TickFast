<?php
require_once './config/db_connection.php';

$event_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$event_id) {
    header('Location: ./events.php');
    exit;
}

$db = getDB();

// Obtener datos del evento
$stmt = $db->prepare("
    SELECT s.*, v.nombre as venue_nombre, v.Direccion as venue_direccion, 
           v.Capacidad as venue_capacidad, v.PrecioBase, v.descripcion as venue_descripcion
    FROM Shows s
    JOIN Venue v ON s.id_venue = v.id_venue
    WHERE s.id_show = ? AND s.estado = 'activo'
");
$stmt->execute([$event_id]);
$evento = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$evento) {
    header('Location: events.php?error=not_found');
    exit;
}

// Obtener artistas del evento
$stmt = $db->prepare("
    SELECT a.* FROM Artistas a
    JOIN Show_Artistas sa ON a.id_artista = sa.id_artista
    WHERE sa.id_show = ? AND a.activo = 1
");
$stmt->execute([$event_id]);
$artistas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener sectores disponibles con precios
$stmt = $db->prepare("
    SELECT s.*, 
           ROUND(v.PrecioBase + (v.PrecioBase * s.Porc_agr / 100), 2) as precio_final,
           s.tickets_disponibles
    FROM Sector s
    JOIN Venue v ON s.idVenue = v.id_venue
    WHERE v.id_venue = ? AND s.tickets_disponibles > 0
    ORDER BY precio_final ASC
");
$stmt->execute([$evento['id_venue']]);
$sectores = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Procesar compra de tickets
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'buy_ticket') {
    if (!isLoggedIn()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión para comprar tickets']);
        exit;
    }
    
    $sector_id = intval($_POST['sector_id']);
    $cantidad = intval($_POST['cantidad']);
    
    if ($cantidad < 1 || $cantidad > 10) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Cantidad inválida']);
        exit;
    }
    
    // Verificar disponibilidad
    $stmt = $db->prepare("SELECT tickets_disponibles FROM Sector WHERE idSector = ?");
    $stmt->execute([$sector_id]);
    $disponibles = $stmt->fetchColumn();
    
    if ($disponibles < $cantidad) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'No hay suficientes tickets disponibles']);
        exit;
    }
    
    // Obtener precio del sector
    $stmt = $db->prepare("
        SELECT ROUND(v.PrecioBase + (v.PrecioBase * s.Porc_agr / 100), 2) as precio
        FROM Sector s
        JOIN Venue v ON s.idVenue = v.id_venue
        WHERE s.idSector = ?
    ");
    $stmt->execute([$sector_id]);
    $precio = $stmt->fetchColumn();
    
    try {
        $db->beginTransaction();
        
        // Crear tickets
        for ($i = 0; $i < $cantidad; $i++) {
            $codigo_qr = uniqid('TF' . $event_id . '_');
            
            $stmt = $db->prepare("
                INSERT INTO Ticket (id_show, id_sector, id_usuario, precio, codigo_qr) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$event_id, $sector_id, $_SESSION['user_id'], $precio, $codigo_qr]);
        }
        
        // Actualizar disponibilidad
        $stmt = $db->prepare("UPDATE Sector SET tickets_disponibles = tickets_disponibles - ? WHERE idSector = ?");
        $stmt->execute([$cantidad, $sector_id]);
        
        $db->commit();
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'message' => 'Tickets comprados exitosamente',
            'redirect' => 'profile.php?tab=tickets'
        ]);
        exit;
        
    } catch (Exception $e) {
        $db->rollback();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Error al procesar la compra']);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($evento['Nombre']); ?> - TickFast</title>
    <link rel="stylesheet" href="./assets/styles.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎫</text></svg>">
    <meta name="description" content="<?php echo htmlspecialchars($evento['descripcion'] ?? 'Evento en ' . $evento['venue_nombre']); ?>">
</head>
<body class="<?php echo isLoggedIn() ? 'logged-in' : ''; ?>">
    <!-- Header -->
    <header class="header">
        <nav class="navbar">
            <a href="index.php" class="logo">TickFast</a>
            <ul class="nav-menu">
                <li><a href="index.php" class="nav-link">Inicio</a></li>
                <li><a href="events.php" class="nav-link">Eventos</a></li>
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
        </nav>
    </header>

    <!-- Contenido Principal -->
    <main class="main-content">
        <div class="container">
            <!-- Breadcrumb -->
            <nav style="padding: 1rem 0; color: var(--gray-medium);">
                <a href="index.php" style="color: var(--gray-medium);">Inicio</a> > 
                <a href="events.php" style="color: var(--gray-medium);">Eventos</a> > 
                <span><?php echo htmlspecialchars($evento['Nombre']); ?></span>
            </nav>

            <!-- Hero del Evento -->
            <div class="event-hero" style="background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url('<?php echo $evento['imagen'] ? htmlspecialchars($evento['imagen']) : 'data:image/svg+xml,<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 1200 400\"><rect fill=\"%23667eea\" width=\"1200\" height=\"400\"/><text x=\"50%\" y=\"50%\" fill=\"white\" font-size=\"60\" text-anchor=\"middle\" dy=\"0.3em\">🎵</text></svg>'; ?>'); background-size: cover; background-position: center; color: white; padding: 4rem 2rem; border-radius: var(--border-radius); margin-bottom: 2rem;">
                <div style="max-width: 800px;">
                    <h1 style="font-size: 3rem; margin-bottom: 1rem; text-shadow: 2px 2px 4px rgba(0,0,0,0.8);">
                        <?php echo htmlspecialchars($evento['Nombre']); ?>
                    </h1>
                    
                    <div style="display: flex; flex-wrap: wrap; gap: 2rem; font-size: 1.2rem; margin-bottom: 2rem;">
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <span>📅</span>
                            <span><?php echo formatDate($evento['Fecha']); ?></span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <span>🕒</span>
                            <span><?php echo formatTime($evento['Horario']); ?></span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <span>📍</span>
                            <span><?php echo htmlspecialchars($evento['venue_nombre']); ?></span>
                        </div>
                    </div>
                    
                    <?php if (!empty($artistas)): ?>
                        <div style="margin-bottom: 2rem;">
                            <h3 style="margin-bottom: 1rem;">Artistas:</h3>
                            <div style="display: flex; flex-wrap: wrap; gap: 1rem;">
                                <?php foreach ($artistas as $artista): ?>
                                    <span style="background: rgba(255,255,255,0.2); padding: 0.5rem 1rem; border-radius: 20px; backdrop-filter: blur(10px);">
                                        <?php echo htmlspecialchars($artista['Nombre'] . ' ' . $artista['Apellido']); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div style="display: flex; gap: 1rem;">
                        <a href="#tickets" class="btn btn-primary" style="font-size: 1.2rem; padding: 1rem 2rem;">
                            Comprar Tickets
                        </a>
                        <button onclick="shareEvent()" class="btn btn-outline" style="background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.5);">
                            Compartir
                        </button>
                    </div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 3rem; align-items: start;">
                <!-- Contenido principal -->
                <div>
                    <!-- Descripción del evento -->
                    <?php if ($evento['descripcion']): ?>
                        <section style="background: white; padding: 2rem; border-radius: var(--border-radius); box-shadow: var(--box-shadow); margin-bottom: 2rem;">
                            <h2 style="margin-bottom: 1rem; color: var(--primary-color);">Acerca del Evento</h2>
                            <p style="line-height: 1.8; color: var(--text-dark);">
                                <?php echo nl2br(htmlspecialchars($evento['descripcion'])); ?>
                            </p>
                        </section>
                    <?php endif; ?>

                    <!-- Información del venue -->
                    <section style="background: white; padding: 2rem; border-radius: var(--border-radius); box-shadow: var(--box-shadow); margin-bottom: 2rem;">
                        <h2 style="margin-bottom: 1rem; color: var(--primary-color);">Información del Venue</h2>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                            <div>
                                <h3 style="margin-bottom: 0.5rem;"><?php echo htmlspecialchars($evento['venue_nombre']); ?></h3>
                                <p style="color: var(--gray-medium); margin-bottom: 1rem;">
                                    📍 <?php echo htmlspecialchars($evento['venue_direccion']); ?>
                                </p>
                                <p style="color: var(--gray-medium);">
                                    👥 Capacidad: <?php echo number_format($evento['venue_capacidad']); ?> personas
                                </p>
                                
                                <?php if ($evento['venue_descripcion']): ?>
                                    <p style="margin-top: 1rem; line-height: 1.6;">
                                        <?php echo htmlspecialchars($evento['venue_descripcion']); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            
                            <div>
                                <!-- Mapa simulado -->
                                <div style="background: var(--gray-light); height: 200px; border-radius: var(--border-radius); display: flex; align-items: center; justify-content: center; color: var(--gray-medium);">
                                    🗺️ Mapa del venue
                                    <br>
                                    <small>Integración con Google Maps</small>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- Sidebar de compra -->
                <aside id="tickets">
                    <div style="background: white; padding: 2rem; border-radius: var(--border-radius); box-shadow: var(--box-shadow); position: sticky; top: 100px;">
                        <h2 style="margin-bottom: 2rem; color: var(--primary-color);">Selecciona tus Tickets</h2>
                        
                        <?php if (empty($sectores)): ?>
                            <div style="text-align: center; padding: 2rem; color: var(--gray-medium);">
                                <h3>😔 Evento Agotado</h3>
                                <p>No hay tickets disponibles para este evento.</p>
                            </div>
                        <?php else: ?>
                            <div style="display: flex; flex-direction: column; gap: 1rem;">
                                <?php foreach ($sectores as $sector): ?>
                                    <div class="sector-option" style="border: 2px solid #e9ecef; border-radius: var(--border-radius); padding: 1.5rem; transition: var(--transition); cursor: pointer;" data-sector-id="<?php echo $sector['idSector']; ?>" data-precio="<?php echo $sector['precio_final']; ?>">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                                            <div>
                                                <h4 style="margin-bottom: 0.25rem; color: var(--primary-color);">
                                                    <?php echo htmlspecialchars($sector['Nombre']); ?>
                                                </h4>
                                                <p style="color: var(--gray-medium); font-size: 0.9rem; margin: 0;">
                                                    <?php echo number_format($sector['tickets_disponibles']); ?> disponibles
                                                </p>
                                            </div>
                                            <div style="text-align: right;">
                                                <div style="font-size: 1.5rem; font-weight: bold; color: var(--accent-color);">
                                                    <?php echo formatPrice($sector['precio_final']); ?>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="quantity-selector" style="display: flex; align-items: center; justify-content: space-between;">
                                            <span>Cantidad:</span>
                                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                <button type="button" class="btn-quantity" data-action="decrease" style="width: 40px; height: 40px; border: 1px solid var(--gray-medium); background: white; border-radius: 50%; cursor: pointer;">-</button>
                                                <input type="number" class="quantity-input" value="1" min="1" max="10" style="width: 60px; text-align: center; border: 1px solid #e9ecef; padding: 0.5rem; border-radius: var(--border-radius);">
                                                <button type="button" class="btn-quantity" data-action="increase" style="width: 40px; height: 40px; border: 1px solid var(--gray-medium); background: white; border-radius: 50%; cursor: pointer;">+</button>
                                            </div>
                                        </div>
                                        
                                        <button class="btn-buy-sector btn btn-primary" style="width: 100%; margin-top: 1rem;" data-sector-id="<?php echo $sector['idSector']; ?>">
                                            <?php echo isLoggedIn() ? 'Comprar' : 'Iniciar Sesión para Comprar'; ?>
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Información adicional -->
                        <div style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid #e9ecef;">
                            <h4 style="margin-bottom: 1rem; color: var(--primary-color);">Información Importante</h4>
                            <ul style="list-style: none; padding: 0; font-size: 0.9rem; color: var(--gray-medium);">
                                <li style="margin-bottom: 0.5rem;">✅ Tickets digitales enviados por email</li>
                                <li style="margin-bottom: 0.5rem;">🔒 Compra 100% segura</li>
                                <li style="margin-bottom: 0.5rem;">📱 Código QR para acceso rápido</li>
                                <li style="margin-bottom: 0.5rem;">❌ No se permiten cancelaciones</li>
                            </ul>
                        </div>
                    </div>
                </aside>
            </div>
            
            <!-- Eventos relacionados -->
            <section style="margin-top: 4rem;">
                <h2 style="text-align: center; margin-bottom: 2rem; color: var(--primary-color);">
                    Eventos Similares
                </h2>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem;">
                    <!-- Aquí se cargarían eventos similares vía JavaScript -->
                    <div class="related-events-container">
                        <div style="text-align: center; padding: 2rem; color: var(--gray-medium);">
                            Cargando eventos similares...
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <!-- Modal de Login -->
    <div id="loginModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Iniciar Sesión</h2>
            <p>Inicia sesión para comprar tickets</p>
            <a href="login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="btn btn-primary" style="display: block; text-align: center; margin-top: 1rem;">
                Ir a Iniciar Sesión
            </a>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 TickFast. Todos los derechos reservados.</p>
        </div>
    </footer>

    <script src="main.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            initializeTicketPurchase();
            loadRelatedEvents();
            initializeSharing();
        });
        
        function initializeTicketPurchase() {
            // Manejar selección de cantidad
            document.querySelectorAll('.btn-quantity').forEach(button => {
                button.addEventListener('click', function() {
                    const action = this.getAttribute('data-action');
                    const quantityInput = this.parentNode.querySelector('.quantity-input');
                    let quantity = parseInt(quantityInput.value);
                    
                    if (action === 'increase' && quantity < 10) {
                        quantity++;
                    } else if (action === 'decrease' && quantity > 1) {
                        quantity--;
                    }
                    
                    quantityInput.value = quantity;
                    updateSectorPrice(this.closest('.sector-option'));
                });
            });
            
            // Manejar cambio directo en input
            document.querySelectorAll('.quantity-input').forEach(input => {
                input.addEventListener('change', function() {
                    let quantity = parseInt(this.value);
                    if (quantity < 1) quantity = 1;
                    if (quantity > 10) quantity = 10;
                    this.value = quantity;
                    updateSectorPrice(this.closest('.sector-option'));
                });
            });
            
            // Manejar compra de tickets
            document.querySelectorAll('.btn-buy-sector').forEach(button => {
                button.addEventListener('click', function() {
                    <?php if (!isLoggedIn()): ?>
                        showModal(document.getElementById('loginModal'));
                        return;
                    <?php endif; ?>
                    
                    const sectorId = this.getAttribute('data-sector-id');
                    const quantityInput = this.parentNode.querySelector('.quantity-input');
                    const quantity = parseInt(quantityInput.value);
                    
                    buyTickets(sectorId, quantity, this);
                });
            });
        }
        
        function updateSectorPrice(sectorElement) {
            const precio = parseFloat(sectorElement.getAttribute('data-precio'));
            const quantity = parseInt(sectorElement.querySelector('.quantity-input').value);
            const total = precio * quantity;
            
            // Actualizar precio mostrado si existe un elemento de total
            const totalElement = sectorElement.querySelector('.total-price');
            if (totalElement) {
                totalElement.textContent = formatPrice(total);
            }
        }
        
        function buyTickets(sectorId, quantity, buttonElement) {
            const originalText = buttonElement.textContent;
            buttonElement.innerHTML = '<span class="loading"></span> Procesando...';
            buttonElement.disabled = true;
            
            const formData = new FormData();
            formData.append('action', 'buy_ticket');
            formData.append('sector_id', sectorId);
            formData.append('cantidad', quantity);
            
            fetch(window.location.href, {
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
                        }, 2000);
                    }
                } else {
                    showAlert(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Error al procesar la compra', 'error');
            })
            .finally(() => {
                buttonElement.textContent = originalText;
                buttonElement.disabled = false;
            });
        }
        
        function loadRelatedEvents() {
            // Simular carga de eventos relacionados
            setTimeout(() => {
                const container = document.querySelector('.related-events-container');
                container.innerHTML = `
                    <div style="text-align: center; color: var(--gray-medium);">
                        <p>Próximamente: eventos relacionados basados en tus preferencias</p>
                    </div>
                `;
            }, 1000);
        }
        
        function initializeSharing() {
            window.shareEvent = function() {
                const eventUrl = window.location.href;
                const eventTitle = document.querySelector('h1').textContent;
                
                if (navigator.share) {
                    navigator.share({
                        title: eventTitle + ' - TickFast',
                        text: 'Mira este evento increíble en TickFast',
                        url: eventUrl
                    });
                } else {
                    // Fallback: copiar al portapapeles
                    navigator.clipboard.writeText(eventUrl).then(() => {
                        showAlert('Enlace copiado al portapapeles', 'success');
                    });
                }
            };
        }
        
        // Efecto parallax en el hero
        window.addEventListener('scroll', function() {
            const hero = document.querySelector('.event-hero');
            const scrolled = window.pageYOffset;
            const rate = scrolled * -0.3;
            
            if (hero) {
                hero.style.backgroundPosition = `center ${rate}px`;
            }
        });
        
        // Highlight del sector seleccionado
        document.querySelectorAll('.sector-option').forEach(sector => {
            sector.addEventListener('mouseenter', function() {
                this.style.borderColor = 'var(--accent-color)';
                this.style.transform = 'translateY(-2px)';
                this.style.boxShadow = '0 8px 20px rgba(233, 69, 96, 0.1)';
            });
            
            sector.addEventListener('mouseleave', function() {
                this.style.borderColor = '#e9ecef';
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = 'none';
            });
        });
    </script>
</body>
</html>