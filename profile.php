<?php
require_once './config/db_connection.php';

requireLogin();

$user = getCurrentUser();
$db = getDB();

// Procesar actualizaciones del perfil
if ($_POST && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_profile') {
        $nombre = trim($_POST['nombre']);
        $documento = trim($_POST['documento']);
        $fecha_nac = $_POST['fecha_nac'];
        $direccion = trim($_POST['direccion']);
        $telefono = trim($_POST['telefono']);
        
        if (empty($nombre)) {
            $error = 'El nombre es obligatorio';
        } else {
            $stmt = $db->prepare("
                UPDATE Usuario SET 
                    Nombre = ?, Documento = ?, FechaNac = ?, Direccion = ?, NumTel = ?
                WHERE id_usuario = ?
            ");
            
            $documento_int = !empty($documento) ? intval($documento) : null;
            $fecha_nac_val = !empty($fecha_nac) ? $fecha_nac : null;
            
            if ($stmt->execute([$nombre, $documento_int, $fecha_nac_val, $direccion, $telefono, $_SESSION['user_id']])) {
                $success = 'Perfil actualizado exitosamente';
                $user = getCurrentUser(); // Recargar datos
                $_SESSION['user_name'] = $nombre;
            } else {
                $error = 'Error al actualizar el perfil';
            }
        }
    }
    
    if ($_POST['action'] === 'change_password') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($current_password) || empty($new_password)) {
            $error = 'Complete todos los campos de contraseña';
        } elseif (strlen($new_password) < 6) {
            $error = 'La nueva contraseña debe tener al menos 6 caracteres';
        } elseif ($new_password !== $confirm_password) {
            $error = 'Las contraseñas nuevas no coinciden';
        } elseif (!password_verify($current_password, $user['Contrasena'])) {
            $error = 'La contraseña actual es incorrecta';
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE Usuario SET Contrasena = ? WHERE id_usuario = ?");
            
            if ($stmt->execute([$hashed_password, $_SESSION['user_id']])) {
                $success = 'Contraseña cambiada exitosamente';
            } else {
                $error = 'Error al cambiar la contraseña';
            }
        }
    }
    
    // Respuesta AJAX
    if (isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => isset($success),
            'message' => isset($success) ? $success : (isset($error) ? $error : 'Error desconocido')
        ]);
        exit;
    }
}

// Obtener tickets del usuario
$stmt = $db->prepare("
    SELECT t.*, s.Nombre as show_name, s.Fecha, s.Horario, 
           sec.Nombre as sector_name, v.nombre as venue_name,
           t.codigo_qr, t.estado, t.fecha_compra
    FROM Ticket t
    JOIN Shows s ON t.id_show = s.id_show
    JOIN Sector sec ON t.id_sector = sec.idSector
    JOIN Venue v ON s.id_venue = v.id_venue
    WHERE t.id_usuario = ?
    ORDER BY s.Fecha DESC, t.fecha_compra DESC
");
$stmt->execute([$_SESSION['user_id']]);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas del usuario
$stmt = $db->prepare("SELECT COUNT(*) FROM Ticket WHERE id_usuario = ?");
$stmt->execute([$_SESSION['user_id']]);
$total_tickets = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT SUM(precio) FROM Ticket WHERE id_usuario = ?");
$stmt->execute([$_SESSION['user_id']]);
$total_gastado = $stmt->fetchColumn() ?? 0;

$stmt = $db->prepare("SELECT COUNT(DISTINCT id_show) FROM Ticket WHERE id_usuario = ?");
$stmt->execute([$_SESSION['user_id']]);
$eventos_asistidos = $stmt->fetchColumn();

$active_tab = $_GET['tab'] ?? 'profile';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - TickFast</title>
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
                <li><a href="profile.php" class="nav-link" style="color: var(--accent-color);">Mi Perfil</a></li>
                <li>
                    <a href="cart.php" class="nav-link">
                        🛒 Carrito 
                        <span id="cartCount" style="background: var(--accent-color); color: white; border-radius: 50%; padding: 2px 6px; font-size: 0.8rem; display: none;">0</span>
                    </a>
                </li>
                <li><a href="logout.php" class="nav-link">Salir</a></li>
            </ul>
        </nav>
    </header>

    <!-- Contenido Principal -->
    <main class="main-content">
        <div class="container">
            <!-- Header del perfil -->
            <div style="background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); color: white; padding: 2rem; border-radius: var(--border-radius); margin-bottom: 2rem;">
                <div style="display: flex; align-items: center; gap: 2rem;">
                    <div style="width: 80px; height: 80px; background: var(--accent-color); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem;">
                        👤
                    </div>
                    <div>
                        <h1 style="margin-bottom: 0.5rem;">¡Hola, <?php echo htmlspecialchars($user['Nombre']); ?>!</h1>
                        <p style="opacity: 0.9; margin-bottom: 1rem;"><?php echo htmlspecialchars($user['Mail']); ?></p>
                        <p style="opacity: 0.8; font-size: 0.9rem;">Miembro desde <?php echo formatDate($user['fecha_registro']); ?></p>
                    </div>
                </div>
            </div>

            <!-- Estadísticas rápidas -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 2rem; margin-bottom: 3rem;">
                <div style="background: white; padding: 2rem; border-radius: var(--border-radius); box-shadow: var(--box-shadow); text-align: center;">
                    <div style="font-size: 2.5rem; color: var(--accent-color); margin-bottom: 0.5rem;">
                        <?php echo $total_tickets; ?>
                    </div>
                    <div style="color: var(--gray-medium);">Tickets Comprados</div>
                </div>
                
                <div style="background: white; padding: 2rem; border-radius: var(--border-radius); box-shadow: var(--box-shadow); text-align: center;">
                    <div style="font-size: 2.5rem; color: var(--accent-color); margin-bottom: 0.5rem;">
                        <?php echo $eventos_asistidos; ?>
                    </div>
                    <div style="color: var(--gray-medium);">Eventos Asistidos</div>
                </div>
                
                <div style="background: white; padding: 2rem; border-radius: var(--border-radius); box-shadow: var(--box-shadow); text-align: center;">
                    <div style="font-size: 2.5rem; color: var(--accent-color); margin-bottom: 0.5rem;">
                        <?php echo formatPrice($total_gastado); ?>
                    </div>
                    <div style="color: var(--gray-medium);">Total Gastado</div>
                </div>
            </div>

            <!-- Contenido con pestañas -->
            <div class="profile-section">
                <!-- Pestañas -->
                <div class="profile-tabs">
                    <button class="profile-tab <?php echo $active_tab === 'profile' ? 'active' : ''; ?>" data-tab="profileContent">
                        Datos Personales
                    </button>
                    <button class="profile-tab <?php echo $active_tab === 'tickets' ? 'active' : ''; ?>" data-tab="ticketsContent">
                        Mis Tickets
                    </button>
                    <button class="profile-tab <?php echo $active_tab === 'security' ? 'active' : ''; ?>" data-tab="securityContent">
                        Seguridad
                    </button>
                </div>

                <!-- Contenido de las pestañas -->
                
                <!-- Datos Personales -->
                <div id="profileContent" class="profile-content <?php echo $active_tab === 'profile' ? 'active' : ''; ?>">
                    <h3 style="margin-bottom: 2rem; color: var(--primary-color);">Información Personal</h3>
                    
                    <?php if (isset($success)): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                    <?php endif; ?>
                    
                    <?php if (isset($error)): ?>
                        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" data-ajax="true" style="max-width: 600px;">
                        <input type="hidden" name="action" value="update_profile">
                        <input type="hidden" name="ajax" value="1">
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label for="nombre">Nombre Completo *</label>
                                <input type="text" id="nombre" name="nombre" class="form-control" required
                                       value="<?php echo htmlspecialchars($user['Nombre']); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="email_display">Email</label>
                                <input type="email" id="email_display" class="form-control" 
                                       value="<?php echo htmlspecialchars($user['Mail']); ?>" disabled
                                       style="background: var(--gray-light);">
                                <small style="color: var(--gray-medium);">El email no se puede cambiar</small>
                            </div>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label for="documento">Documento</label>
                                <input type="number" id="documento" name="documento" class="form-control"
                                       value="<?php echo $user['Documento'] ? htmlspecialchars($user['Documento']) : ''; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="fecha_nac">Fecha de Nacimiento</label>
                                <input type="date" id="fecha_nac" name="fecha_nac" class="form-control"
                                       value="<?php echo $user['FechaNac'] ? htmlspecialchars($user['FechaNac']) : ''; ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="direccion">Dirección</label>
                            <input type="text" id="direccion" name="direccion" class="form-control"
                                   value="<?php echo $user['Direccion'] ? htmlspecialchars($user['Direccion']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="telefono">Teléfono</label>
                            <input type="tel" id="telefono" name="telefono" class="form-control"
                                   value="<?php echo $user['NumTel'] ? htmlspecialchars($user['NumTel']) : ''; ?>">
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            Actualizar Información
                        </button>
                    </form>
                </div>

                <!-- Mis Tickets -->
                <div id="ticketsContent" class="profile-content <?php echo $active_tab === 'tickets' ? 'active' : ''; ?>">
                    <h3 style="margin-bottom: 2rem; color: var(--primary-color);">Mis Tickets</h3>
                    
                    <?php if (empty($tickets)): ?>
                        <div style="text-align: center; padding: 3rem; color: var(--gray-medium);">
                            <div style="font-size: 4rem; margin-bottom: 1rem;">🎫</div>
                            <h4>No tienes tickets aún</h4>
                            <p>¡Explora nuestros eventos y compra tu primer ticket!</p>
                            <a href="events.php" class="btn btn-primary" style="margin-top: 1rem;">
                                Ver Eventos
                            </a>
                        </div>
                    <?php else: ?>
                        <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                            <?php foreach ($tickets as $ticket): ?>
                                <div class="ticket">
                                    <div class="ticket-info">
                                        <div>
                                            <h4 style="margin-bottom: 0.5rem; color: white;">
                                                <?php echo htmlspecialchars($ticket['show_name']); ?>
                                            </h4>
                                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; opacity: 0.9;">
                                                <div>
                                                    <strong>Fecha:</strong><br>
                                                    <?php echo formatDate($ticket['Fecha']); ?>
                                                </div>
                                                <div>
                                                    <strong>Hora:</strong><br>
                                                    <?php echo formatTime($ticket['Horario']); ?>
                                                </div>
                                                <div>
                                                    <strong>Venue:</strong><br>
                                                    <?php echo htmlspecialchars($ticket['venue_name']); ?>
                                                </div>
                                                <div>
                                                    <strong>Sector:</strong><br>
                                                    <?php echo htmlspecialchars($ticket['sector_name']); ?>
                                                </div>
                                                <div>
                                                    <strong>Precio:</strong><br>
                                                    <?php echo formatPrice($ticket['precio']); ?>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="ticket-qr" onclick="showQRCode('<?php echo htmlspecialchars($ticket['codigo_qr']); ?>')" style="cursor: pointer;" title="Click para ver QR">
                                            📱
                                        </div>
                                    </div>
                                    
                                    <div style="margin-top: 1rem; display: flex; justify-content: space-between; align-items: center;">
                                        <div style="font-size: 0.8rem; opacity: 0.8;">
                                            Comprado el <?php echo formatDate($ticket['fecha_compra']); ?>
                                        </div>
                                        
                                        <div style="display: flex; gap: 1rem;">
                                            <span class="ticket-status" style="padding: 0.25rem 0.75rem; border-radius: 15px; font-size: 0.8rem; background: 
                                                <?php 
                                                switch($ticket['estado']) {
                                                    case 'vendido': echo 'var(--success)'; break;
                                                    case 'usado': echo 'var(--warning)'; break;
                                                    case 'cancelado': echo 'var(--danger)'; break;
                                                    default: echo 'var(--gray-medium)';
                                                }
                                                ?>; color: white;">
                                                <?php 
                                                switch($ticket['estado']) {
                                                    case 'vendido': echo '✅ Válido'; break;
                                                    case 'usado': echo '🎫 Usado'; break;
                                                    case 'cancelado': echo '❌ Cancelado'; break;
                                                    default: echo $ticket['estado'];
                                                }
                                                ?>
                                            </span>
                                            
                                            <button class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.25rem 0.75rem;" 
                                                    onclick="downloadTicket('<?php echo $ticket['id_ticket']; ?>')">
                                                Descargar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Seguridad -->
                <div id="securityContent" class="profile-content <?php echo $active_tab === 'security' ? 'active' : ''; ?>">
                    <h3 style="margin-bottom: 2rem; color: var(--primary-color);">Configuración de Seguridad</h3>
                    
                    <!-- Cambiar contraseña -->
                    <div style="background: white; padding: 2rem; border-radius: var(--border-radius); box-shadow: var(--box-shadow); margin-bottom: 2rem; max-width: 500px;">
                        <h4 style="margin-bottom: 1.5rem;">Cambiar Contraseña</h4>
                        
                        <form method="POST" data-ajax="true" id="passwordForm">
                            <input type="hidden" name="action" value="change_password">
                            <input type="hidden" name="ajax" value="1">
                            
                            <div class="form-group">
                                <label for="current_password">Contraseña Actual *</label>
                                <input type="password" id="current_password" name="current_password" class="form-control" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="new_password">Nueva Contraseña *</label>
                                <input type="password" id="new_password" name="new_password" class="form-control" required minlength="6">
                                <div class="password-strength" id="newPasswordStrength"></div>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password">Confirmar Nueva Contraseña *</label>
                                <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                Cambiar Contraseña
                            </button>
                        </form>
                    </div>
                    
                    <!-- Información de seguridad -->
                    <div style="background: var(--gray-light); padding: 2rem; border-radius: var(--border-radius);">
                        <h4 style="margin-bottom: 1rem;">Consejos de Seguridad</h4>
                        <ul style="list-style: none; padding: 0;">
                            <li style="margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                                <span style="color: var(--success);">🔒</span>
                                Usa una contraseña única y segura
                            </li>
                            <li style="margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                                <span style="color: var(--success);">📱</span>
                                Nunca compartas tus códigos QR
                            </li>
                            <li style="margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                                <span style="color: var(--success);">🔐</span>
                                Cierra sesión en dispositivos compartidos
                            </li>
                            <li style="margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                                <span style="color: var(--success);">⚠️</span>
                                Reporta cualquier actividad sospechosa
                            </li>
                        </ul>
                        
                        <div style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid #ddd;">
                            <button class="btn btn-secondary" onclick="downloadAccountData()">
                                Descargar mis datos
                            </button>
                            <button class="btn" style="color: var(--danger); margin-left: 1rem;" onclick="showDeleteAccount()">
                                Eliminar cuenta
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal para mostrar QR -->
    <div id="qrModal" class="modal">
        <div class="modal-content" style="text-align: center;">
            <span class="close">&times;</span>
            <h3>Código QR del Ticket</h3>
            <div id="qrCodeContainer" style="margin: 2rem 0;">
                <!-- Aquí se mostraría el QR real -->
                <div style="width: 200px; height: 200px; background: white; border: 2px solid #ddd; margin: 0 auto; display: flex; align-items: center; justify-content: center; font-size: 3rem;">
                    📱
                </div>
            </div>
            <p id="qrCodeText" style="font-family: monospace; background: var(--gray-light); padding: 1rem; border-radius: var(--border-radius);"></p>
            <p style="color: var(--gray-medium); font-size: 0.9rem;">
                Presenta este código en la entrada del evento
            </p>
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
            initializePasswordValidation();
        });
        
        function initializePasswordValidation() {
            const newPasswordInput = document.getElementById('new_password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            const strengthDiv = document.getElementById('newPasswordStrength');
            
            if (newPasswordInput) {
                newPasswordInput.addEventListener('input', function() {
                    const password = this.value;
                    let strength = 0;
                    
                    if (password.length >= 6) strength++;
                    if (/[A-Z]/.test(password)) strength++;
                    if (/[a-z]/.test(password)) strength++;
                    if (/[0-9]/.test(password)) strength++;
                    if (/[^A-Za-z0-9]/.test(password)) strength++;
                    
                    let strengthText = '';
                    let strengthColor = '';
                    
                    if (password.length === 0) {
                        strengthDiv.innerHTML = '';
                        return;
                    }
                    
                    if (strength < 2) {
                        strengthText = 'Débil';
                        strengthColor = 'var(--danger)';
                    } else if (strength < 4) {
                        strengthText = 'Media';
                        strengthColor = 'var(--warning)';
                    } else {
                        strengthText = 'Fuerte';
                        strengthColor = 'var(--success)';
                    }
                    
                    strengthDiv.innerHTML = `
                        <div style="margin-top: 0.5rem;">
                            <div style="display: flex; gap: 2px; margin-bottom: 0.25rem;">
                                ${Array(5).fill().map((_, i) => 
                                    `<div style="height: 4px; flex: 1; background: ${i < strength ? strengthColor : '#e0e0e0'}; border-radius: 2px;"></div>`
                                ).join('')}
                            </div>
                            <small style="color: ${strengthColor};">${strengthText}</small>
                        </div>
                    `;
                });
            }
            
            if (confirmPasswordInput) {
                confirmPasswordInput.addEventListener('input', function() {
                    const password = newPasswordInput.value;
                    const confirmPassword = this.value;
                    
                    if (confirmPassword.length === 0) {
                        this.style.borderColor = '';
                        return;
                    }
                    
                    if (password === confirmPassword) {
                        this.style.borderColor = 'var(--success)';
                    } else {
                        this.style.borderColor = 'var(--danger)';
                    }
                });
            }
        }
        
        function showQRCode(code) {
            const modal = document.getElementById('qrModal');
            const codeText = document.getElementById('qrCodeText');
            
            codeText.textContent = code;
            showModal(modal);
        }
        
        function downloadTicket(ticketId) {
            // Simular descarga de ticket
            showAlert('Descargando ticket...', 'success');
            
            // En una implementación real, esto generaría un PDF
            setTimeout(() => {
                const link = document.createElement('a');
                link.href = `generate_ticket.php?id=${ticketId}`;
                link.download = `ticket_${ticketId}.pdf`;
                link.click();
            }, 1000);
        }
        
        function downloadAccountData() {
            if (confirm('¿Deseas descargar una copia de todos tus datos de cuenta?')) {
                showAlert('Preparando descarga...', 'success');
                
                setTimeout(() => {
                    const link = document.createElement('a');
                    link.href = 'export_user_data.php';
                    link.download = 'mis_datos_tickfast.json';
                    link.click();
                }, 2000);
            }
        }
        
        function showDeleteAccount() {
            if (confirm('⚠️ ATENCIÓN: Esta acción eliminará permanentemente tu cuenta y todos tus datos.\n\n¿Estás completamente seguro de que deseas continuar?')) {
                if (confirm('Esta es tu última oportunidad para cancelar. ¿Realmente deseas eliminar tu cuenta de TickFast?')) {
                    window.location.href = 'delete_account.php';
                }
            }
        }
        
        // Activar tab específico desde URL
        const urlParams = new URLSearchParams(window.location.search);
        const activeTab = urlParams.get('tab');
        
        if (activeTab) {
            const tabs = document.querySelectorAll('.profile-tab');
            const contents = document.querySelectorAll('.profile-content');
            
            tabs.forEach(tab => {
                if (tab.getAttribute('data-tab') === activeTab + 'Content') {
                    tab.click();
                }
            });
        }
    </script>