<?php
require_once './config/db_connection.php';

requireLogin();

$db = getDB();

// Manejar acciones del carrito via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'add':
            $event_id = intval($input['event_id']);
            $sector_id = intval($input['sector_id']);
            $quantity = intval($input['quantity']) ?: 1;
            
            // Verificar disponibilidad
            $stmt = $db->prepare("SELECT tickets_disponibles FROM Sector WHERE idSector = ?");
            $stmt->execute([$sector_id]);
            $disponibles = $stmt->fetchColumn();
            
            if ($disponibles < $quantity) {
                echo json_encode(['success' => false, 'message' => 'No hay suficientes tickets disponibles']);
                exit;
            }
            
            // Obtener precio del sector
            $stmt = $db->prepare("
                SELECT ROUND(v.PrecioBase + (v.PrecioBase * s.Porc_agr / 100), 2) as precio,
                       s.Nombre as sector_nombre, sh.Nombre as show_nombre
                FROM Sector s
                JOIN Venue v ON s.idVenue = v.id_venue
                JOIN Shows sh ON v.id_venue = sh.id_venue
                WHERE s.idSector = ? AND sh.id_show = ?
            ");
            $stmt->execute([$sector_id, $event_id]);
            $sector_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$sector_data) {
                echo json_encode(['success' => false, 'message' => 'Sector no encontrado']);
                exit;
            }
            
            // Agregar al carrito (en sesión)
            if (!isset($_SESSION['cart'])) {
                $_SESSION['cart'] = [];
            }
            
            $cart_item = [
                'event_id' => $event_id,
                'sector_id' => $sector_id,
                'quantity' => $quantity,
                'precio' => $sector_data['precio'],
                'sector_nombre' => $sector_data['sector_nombre'],
                'show_nombre' => $sector_data['show_nombre']
            ];
            
            $_SESSION['cart'][] = $cart_item;
            
            echo json_encode(['success' => true, 'message' => 'Ticket agregado al carrito']);
            break;
            
        case 'remove':
            $item_index = intval($input['item_index']);
            
            if (isset($_SESSION['cart'][$item_index])) {
                array_splice($_SESSION['cart'], $item_index, 1);
                echo json_encode(['success' => true, 'message' => 'Ticket eliminado del carrito']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Item no encontrado']);
            }
            break;
            
        case 'clear':
            $_SESSION['cart'] = [];
            echo json_encode(['success' => true, 'message' => 'Carrito vaciado']);
            break;
            
        case 'checkout':
            if (empty($_SESSION['cart'])) {
                echo json_encode(['success' => false, 'message' => 'El carrito está vacío']);
                exit;
            }
            
            try {
                $db->beginTransaction();
                
                foreach ($_SESSION['cart'] as $item) {
                    // Verificar disponibilidad nuevamente
                    $stmt = $db->prepare("SELECT tickets_disponibles FROM Sector WHERE idSector = ?");
                    $stmt->execute([$item['sector_id']]);
                    $disponibles = $stmt->fetchColumn();
                    
                    if ($disponibles < $item['quantity']) {
                        $db->rollback();
                        echo json_encode(['success' => false, 'message' => 'No hay suficientes tickets disponibles para ' . $item['show_nombre']]);
                        exit;
                    }
                    
                    // Crear tickets
                    for ($i = 0; $i < $item['quantity']; $i++) {
                        $codigo_qr = uniqid('TF' . $item['event_id'] . '_');
                        
                        $stmt = $db->prepare("
                            INSERT INTO Ticket (id_show, id_sector, id_usuario, precio, codigo_qr) 
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $item['event_id'], 
                            $item['sector_id'], 
                            $_SESSION['user_id'], 
                            $item['precio'], 
                            $codigo_qr
                        ]);
                    }
                    
                    // Actualizar disponibilidad
                    $stmt = $db->prepare("UPDATE Sector SET tickets_disponibles = tickets_disponibles - ? WHERE idSector = ?");
                    $stmt->execute([$item['quantity'], $item['sector_id']]);
                }
                
                $db->commit();
                $_SESSION['cart'] = [];
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Compra realizada exitosamente',
                    'redirect' => 'profile.php?tab=tickets'
                ]);
                
            } catch (Exception $e) {
                $db->rollback();
                echo json_encode(['success' => false, 'message' => 'Error al procesar la compra']);
            }
            break;
    }
    exit;
}

// Manejar peticiones GET para obtener información del carrito
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['action']) {
        case 'get':
            $cart = $_SESSION['cart'] ?? [];
            $total = array_reduce($cart, function($sum, $item) {
                return $sum + ($item['precio'] * $item['quantity']);
            }, 0);
            
            echo json_encode([
                'success' => true,
                'items' => $cart,
                'total' => $total
            ]);
            break;
            
        case 'count':
            $cart = $_SESSION['cart'] ?? [];
            $count = array_reduce($cart, function($sum, $item) {
                return $sum + $item['quantity'];
            }, 0);
            
            echo json_encode(['count' => $count]);
            break;
    }
    exit;
}

// Página HTML del carrito
$cart = $_SESSION['cart'] ?? [];
$total = array_reduce($cart, function($sum, $item) {
    return $sum + ($item['precio'] * $item['quantity']);
}, 0);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carrito de Compras - TickFast</title>
    <link rel="stylesheet" href="./assets/styles.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎫</text></svg>">
</head>
<body class="logged-in">
    <!-- Header -->
    <header class="header">
        <nav class="navbar">
            <a href="index.php" class="logo">TickFast</a>
            <ul class="nav-menu">
                <li><a href="index.php" class="nav-link">Inicio</a></li>
                <li><a href="events.php" class="nav-link">Eventos</a></li>
                <li><a href="venues.php" class="nav-link">Venues</a></li>
                <li><a href="artists.php" class="nav-link">Artistas</a></li>
                <li><a href="profile.php" class="nav-link">Mi Perfil</a></li>
                <li><a href="cart.php" class="nav-link" style="color: var(--accent-color);">🛒 Carrito</a></li>
                <li><a href="logout.php" class="nav-link">Salir</a></li>
            </ul>
        </nav>
    </header>

    <!-- Contenido Principal -->
    <main class="main-content">
        <div class="container">
            <h1 style="color: var(--primary-color); margin-bottom: 2rem; text-align: center;">
                🛒 Mi Carrito de Compras
            </h1>

            <?php if (empty($cart)): ?>
                <!-- Carrito vacío -->
                <div style="text-align: center; padding: 4rem 2rem; color: var(--gray-medium);">
                    <div style="font-size: 6rem; margin-bottom: 2rem; opacity: 0.5;">🛒</div>
                    <h2>Tu carrito está vacío</h2>
                    <p style="font-size: 1.2rem; margin-bottom: 2rem;">¡Explora nuestros increíbles eventos y encuentra tu próximo show favorito!</p>
                    <a href="events.php" class="btn btn-primary" style="font-size: 1.1rem; padding: 1rem 2rem;">
                        Explorar Eventos
                    </a>
                </div>
            <?php else: ?>
                <!-- Contenido del carrito -->
                <div style="display: grid; grid-template-columns: 1fr auto; gap: 3rem; align-items: start;">
                    <!-- Items del carrito -->
                    <div>
                        <div style="background: white; border-radius: var(--border-radius); box-shadow: var(--box-shadow); overflow: hidden;">
                            <div style="background: var(--primary-color); color: white; padding: 1.5rem;">
                                <h3 style="margin: 0;">Tickets Seleccionados (<?php echo count($cart); ?> items)</h3>
                            </div>
                            
                            <div id="cartItems">
                                <?php foreach ($cart as $index => $item): ?>
                                    <div class="cart-item" style="padding: 2rem; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
                                        <div style="flex: 1;">
                                            <h4 style="color: var(--primary-color); margin-bottom: 0.5rem;">
                                                <?php echo htmlspecialchars($item['show_nombre']); ?>
                                            </h4>
                                            
                                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 1rem; color: var(--gray-medium);">
                                                <div>
                                                    <strong>Sector:</strong><br>
                                                    <?php echo htmlspecialchars($item['sector_nombre']); ?>
                                                </div>
                                                <div>
                                                    <strong>Cantidad:</strong><br>
                                                    <?php echo $item['quantity']; ?> ticket<?php echo $item['quantity'] > 1 ? 's' : ''; ?>
                                                </div>
                                                <div>
                                                    <strong>Precio unitario:</strong><br>
                                                    <?php echo formatPrice($item['precio']); ?>
                                                </div>
                                            </div>
                                            
                                            <div style="font-size: 1.2rem; font-weight: bold; color: var(--accent-color);">
                                                Subtotal: <?php echo formatPrice($item['precio'] * $item['quantity']); ?>
                                            </div>
                                        </div>
                                        
                                        <div style="margin-left: 2rem;">
                                            <button class="btn btn-secondary" 
                                                    onclick="removeFromCart(<?php echo $index; ?>)"
                                                    style="background: var(--danger); border-color: var(--danger);">
                                                🗑️ Eliminar
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div style="padding: 1.5rem; background: var(--gray-light); text-align: center;">
                                <button class="btn btn-secondary" onclick="clearCart()">
                                    Vaciar Carrito
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Resumen y checkout -->
                    <div style="min-width: 350px;">
                        <div style="background: white; padding: 2rem; border-radius: var(--border-radius); box-shadow: var(--box-shadow); position: sticky; top: 100px;">
                            <h3 style="margin-bottom: 2rem; color: var(--primary-color);">Resumen de Compra</h3>
                            
                            <div style="border-bottom: 1px solid #eee; padding-bottom: 1rem; margin-bottom: 1rem;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                                    <span>Subtotal:</span>
                                    <span id="cartSubtotal"><?php echo formatPrice($total); ?></span>
                                </div>
                                
                                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; color: var(--gray-medium);">
                                    <span>Descuentos:</span>
                                    <span>-<?php echo formatPrice(0); ?></span>
                                </div>
                                
                                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; color: var(--gray-medium);">
                                    <span>Service fee:</span>
                                    <span><?php echo formatPrice($total * 0.05); ?></span>
                                </div>
                            </div>
                            
                            <div style="display: flex; justify-content: space-between; font-size: 1.5rem; font-weight: bold; color: var(--primary-color); margin-bottom: 2rem;">
                                <span>Total:</span>
                                <span id="cartTotal"><?php echo formatPrice($total * 1.05); ?></span>
                            </div>
                            
                            <button class="btn btn-primary" 
                                    onclick="proceedToCheckout()"
                                    style="width: 100%; font-size: 1.2rem; padding: 1rem; margin-bottom: 1rem;">
                                💳 Proceder al Pago
                            </button>
                            
                            <a href="events.php" class="btn btn-outline" style="width: 100%; text-align: center; display: block;">
                                Seguir Comprando
                            </a>
                            
                            <!-- Información adicional -->
                            <div style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid #eee; font-size: 0.9rem; color: var(--gray-medium);">
                                <h5 style="color: var(--primary-color); margin-bottom: 1rem;">Información de Compra:</h5>
                                <ul style="list-style: none; padding: 0;">
                                    <li style="margin-bottom: 0.5rem;">✅ Compra 100% segura</li>
                                    <li style="margin-bottom: 0.5rem;">📱 Tickets digitales instantáneos</li>
                                    <li style="margin-bottom: 0.5rem;">🎫 Códigos QR únicos</li>
                                    <li style="margin-bottom: 0.5rem;">📧 Confirmación por email</li>
                                    <li style="margin-bottom: 0.5rem;">❌ No se permiten cancelaciones</li>
                                </ul>
                            </div>
                            
                            <!-- Métodos de pago aceptados -->
                            <div style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid #eee; text-align: center;">
                                <h5 style="color: var(--primary-color); margin-bottom: 1rem; font-size: 0.9rem;">Métodos de Pago:</h5>
                                <div style="display: flex; justify-content: center; gap: 1rem; flex-wrap: wrap;">
                                    <div style="padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px; font-size: 0.8rem;">💳 Visa</div>
                                    <div style="padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px; font-size: 0.8rem;">💳 MasterCard</div>
                                    <div style="padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px; font-size: 0.8rem;">💰 Mercado Pago</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Eventos relacionados -->
                <section style="margin-top: 4rem;">
                    <h2 style="text-align: center; margin-bottom: 2rem; color: var(--primary-color);">
                        También te puede interesar
                    </h2>
                    
                    <div id="relatedEvents" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem;">
                        <div style="text-align: center; padding: 2rem; color: var(--gray-medium);">
                            Cargando sugerencias...
                        </div>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </main>

    <!-- Modal de confirmación de checkout -->
    <div id="checkoutModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h3 style="margin-bottom: 2rem; color: var(--primary-color);">Confirmar Compra</h3>
            
            <div style="background: var(--gray-light); padding: 1.5rem; border-radius: var(--border-radius); margin-bottom: 2rem;">
                <h4 style="margin-bottom: 1rem;">Resumen Final:</h4>
                <div id="checkoutSummary"></div>
            </div>
            
            <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 1rem; border-radius: var(--border-radius); margin-bottom: 2rem; color: #856404;">
                <strong>⚠️ Importante:</strong> Una vez confirmada la compra, no se permiten cancelaciones ni reembolsos.
            </div>
            
            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <button class="btn btn-secondary" onclick="hideModal(document.getElementById('checkoutModal'))">
                    Cancelar
                </button>
                <button class="btn btn-primary" onclick="confirmCheckout()">
                    💳 Confirmar y Pagar
                </button>
            </div>
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
            updateCartCount();
            loadRelatedEvents();
        });
        
        function removeFromCart(itemIndex) {
            if (!confirm('¿Estás seguro de que deseas eliminar este ticket del carrito?')) {
                return;
            }
            
            fetch('cart.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'remove',
                    item_index: itemIndex
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    showAlert(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Error al eliminar el ticket', 'error');
            });
        }
        
        function clearCart() {
            if (!confirm('¿Estás seguro de que deseas vaciar todo el carrito?')) {
                return;
            }
            
            fetch('cart.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'clear' })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                }
            });
        }
        
        function proceedToCheckout() {
            // Mostrar modal de confirmación
            const modal = document.getElementById('checkoutModal');
            const summaryDiv = document.getElementById('checkoutSummary');
            
            // Obtener resumen del carrito
            fetch('cart.php?action=get')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let summaryHTML = '';
                        data.items.forEach(item => {
                            summaryHTML += `
                                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                                    <span>${item.show_nombre} (${item.sector_nombre}) x${item.quantity}</span>
                                    <span>${formatPrice(item.precio * item.quantity)}</span>
                                </div>
                            `;
                        });
                        
                        const serviceFee = data.total * 0.05;
                        const total = data.total + serviceFee;
                        
                        summaryHTML += `
                            <hr style="margin: 1rem 0;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                                <span>Subtotal:</span>
                                <span>${formatPrice(data.total)}</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                                <span>Service fee:</span>
                                <span>${formatPrice(serviceFee)}</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-weight: bold; font-size: 1.2rem; color: var(--primary-color);">
                                <span>Total:</span>
                                <span>${formatPrice(total)}</span>
                            </div>
                        `;
                        
                        summaryDiv.innerHTML = summaryHTML;
                        showModal(modal);
                    }
                });
        }
        
        function confirmCheckout() {
            const button = event.target;
            const originalText = button.textContent;
            
            button.innerHTML = '<span class="loading"></span> Procesando...';
            button.disabled = true;
            
            fetch('cart.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'checkout' })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    hideModal(document.getElementById('checkoutModal'));
                    
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
                button.textContent = originalText;
                button.disabled = false;
            });
        }
        
        function loadRelatedEvents() {
            <?php if (!empty($cart)): ?>
                // Simular carga de eventos relacionados
                setTimeout(() => {
                    const container = document.getElementById('relatedEvents');
                    
                    // En una implementación real, esto cargaría eventos basados en las preferencias
                    container.innerHTML = `
                        <div style="text-align: center; color: var(--gray-medium); grid-column: 1 / -1;">
                            <p>Próximamente: recomendaciones personalizadas basadas en tu carrito</p>
                            <a href="events.php" class="btn btn-outline" style="margin-top: 1rem;">
                                Ver Todos los Eventos
                            </a>
                        </div>
                    `;
                }, 1500);
            <?php endif; ?>
        }
        
        // Actualizar totales en tiempo real si se modifican cantidades
        function updateTotals() {
            fetch('cart.php?action=get')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const serviceFee = data.total * 0.05;
                        const total = data.total + serviceFee;
                        
                        document.getElementById('cartSubtotal').textContent = formatPrice(data.total);
                        document.getElementById('cartTotal').textContent = formatPrice(total);
                    }
                });
        }
        
        // Persistir carrito en localStorage como backup
        function backupCart() {
            fetch('cart.php?action=get')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        localStorage.setItem('tickfast_cart_backup', JSON.stringify(data.items));
                    }
                });
        }
        
        // Ejecutar backup periódicamente
        setInterval(backupCart, 30000); // cada 30 segundos
        
        // Advertencia antes de cerrar la página si hay items en el carrito
        <?php if (!empty($cart)): ?>
        window.addEventListener('beforeunload', function(e) {
            const message = '¿Estás seguro de que quieres salir? Tienes tickets en tu carrito.';
            e.returnValue = message;
            return message;
        });
        <?php endif; ?>
    </script>
</body>
</html></text>