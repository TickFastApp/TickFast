<?php
require_once './config/db_connection.php';

$error_message = '';
$success_message = '';

// Procesar formulario de registro
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'register') {
    $email = trim($_POST['email']);
    $nombre = trim($_POST['nombre']);
    $documento = trim($_POST['documento']);
    $fecha_nac = $_POST['fecha_nac'];
    $direccion = trim($_POST['direccion']);
    $telefono = trim($_POST['telefono']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validaciones
    if (empty($email) || empty($nombre) || empty($password) || empty($confirm_password)) {
        $error_message = 'Por favor completa todos los campos obligatorios';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Por favor ingresa un email válido';
    } elseif (strlen($password) < 6) {
        $error_message = 'La contraseña debe tener al menos 6 caracteres';
    } elseif ($password !== $confirm_password) {
        $error_message = 'Las contraseñas no coinciden';
    } else {
        $db = getDB();
        
        // Verificar si el email ya existe
        $stmt = $db->prepare("SELECT COUNT(*) FROM Usuario WHERE Mail = ?");
        $stmt->execute([$email]);
        $email_exists = $stmt->fetchColumn();
        
        if ($email_exists) {
            $error_message = 'Ya existe una cuenta con este email';
        } else {
            // Crear nuevo usuario
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $db->prepare("
                INSERT INTO Usuario (Mail, Nombre, Documento, FechaNac, Direccion, NumTel, Contrasena) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $documento_int = !empty($documento) ? intval($documento) : null;
            $fecha_nac_val = !empty($fecha_nac) ? $fecha_nac : null;
            
            if ($stmt->execute([$email, $nombre, $documento_int, $fecha_nac_val, $direccion, $telefono, $hashed_password])) {
                $user_id = $db->lastInsertId();
                
                // Iniciar sesión automáticamente
                $_SESSION['user_id'] = $user_id;
                $_SESSION['user_name'] = $nombre;
                $_SESSION['user_email'] = $email;
                
                if (isset($_POST['ajax'])) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'message' => 'Registro exitoso. ¡Bienvenido a TickFast!',
                        'redirect' => 'index.php'
                    ]);
                    exit;
                }
                
                header('Location: index.php');
                exit;
            } else {
                $error_message = 'Error al crear la cuenta. Inténtalo nuevamente.';
            }
        }
    }
    
    if (isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $error_message
        ]);
        exit;
    }
}

// Si ya está logueado, redirigir
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrarse - TickFast</title>
    <link rel="stylesheet" href="./assets/styles.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎫</text></svg>">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <nav class="navbar">
            <a href="index.php" class="logo">TickFast</a>
            <ul class="nav-menu">
                <li><a href="index.php" class="nav-link">Inicio</a></li>
                <li><a href="events.php" class="nav-link">Eventos</a></li>
                <li><a href="login.php" class="nav-link">Iniciar Sesión</a></li>
            </ul>
        </nav>
    </header>

    <!-- Contenido Principal -->
    <main class="main-content">
        <div class="container">
            <div class="form-container" style="max-width: 600px;">
                <h1 class="form-title">Crear Cuenta</h1>
                <p style="text-align: center; color: var(--gray-medium); margin-bottom: 2rem;">
                    Únete a TickFast y descubre los mejores eventos
                </p>
                
                <?php if ($error_message): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
                <?php endif; ?>
                
                <?php if ($success_message): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
                <?php endif; ?>
                
                <form method="POST" data-ajax="true" id="registerForm">
                    <input type="hidden" name="action" value="register">
                    <input type="hidden" name="ajax" value="1">
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label for="nombre">Nombre Completo *</label>
                            <input type="text" id="nombre" name="nombre" class="form-control" required
                                   value="<?php echo isset($_POST['nombre']) ? htmlspecialchars($_POST['nombre']) : ''; ?>"
                                   placeholder="Tu nombre completo">
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email *</label>
                            <input type="email" id="email" name="email" class="form-control" required
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                   placeholder="tu@email.com">
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label for="documento">Documento</label>
                            <input type="number" id="documento" name="documento" class="form-control"
                                   value="<?php echo isset($_POST['documento']) ? htmlspecialchars($_POST['documento']) : ''; ?>"
                                   placeholder="DNI sin puntos">
                        </div>
                        
                        <div class="form-group">
                            <label for="fecha_nac">Fecha de Nacimiento</label>
                            <input type="date" id="fecha_nac" name="fecha_nac" class="form-control"
                                   value="<?php echo isset($_POST['fecha_nac']) ? htmlspecialchars($_POST['fecha_nac']) : ''; ?>"
                                   max="<?php echo date('Y-m-d', strtotime('-13 years')); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="direccion">Dirección</label>
                        <input type="text" id="direccion" name="direccion" class="form-control"
                               value="<?php echo isset($_POST['direccion']) ? htmlspecialchars($_POST['direccion']) : ''; ?>"
                               placeholder="Tu dirección completa">
                    </div>
                    
                    <div class="form-group">
                        <label for="telefono">Teléfono</label>
                        <input type="tel" id="telefono" name="telefono" class="form-control"
                               value="<?php echo isset($_POST['telefono']) ? htmlspecialchars($_POST['telefono']) : ''; ?>"
                               placeholder="Ej: +54 9 11 1234-5678">
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label for="password">Contraseña *</label>
                            <input type="password" id="password" name="password" class="form-control" required
                                   placeholder="Mínimo 6 caracteres">
                            <div class="password-strength" id="passwordStrength"></div>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirmar Contraseña *</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required
                                   placeholder="Confirma tu contraseña">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 0.5rem;">
                            <input type="checkbox" name="terms" required>
                            Acepto los <a href="terms.php" target="_blank" style="color: var(--accent-color);">términos y condiciones</a>
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 0.5rem;">
                            <input type="checkbox" name="newsletter" checked>
                            Quiero recibir ofertas y novedades por email
                        </label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        Crear Cuenta
                    </button>
                </form>
                
                <div style="text-align: center; margin-top: 2rem;">
                    <p>¿Ya tienes cuenta? <a href="login.php" style="color: var(--accent-color);">Inicia sesión aquí</a></p>
                </div>
                
                <!-- Beneficios de registrarse -->
                <div style="background: var(--gray-light); padding: 2rem; border-radius: var(--border-radius); margin-top: 2rem;">
                    <h4 style="margin-bottom: 1rem; text-align: center;">¿Por qué registrarse?</h4>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                        <div style="text-align: center;">
                            <div style="font-size: 2rem; margin-bottom: 0.5rem;">⚡</div>
                            <strong>Compra rápida</strong>
                            <p style="font-size: 0.9rem; margin: 0;">Proceso de checkout optimizado</p>
                        </div>
                        <div style="text-align: center;">
                            <div style="font-size: 2rem; margin-bottom: 0.5rem;">🎫</div>
                            <strong>Mis tickets</strong>
                            <p style="font-size: 0.9rem; margin: 0;">Accede a todos tus tickets</p>
                        </div>
                        <div style="text-align: center;">
                            <div style="font-size: 2rem; margin-bottom: 0.5rem;">🔔</div>
                            <strong>Notificaciones</strong>
                            <p style="font-size: 0.9rem; margin: 0;">Entérate de nuevos eventos</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 TickFast. Todos los derechos reservados.</p>
        </div>
    </footer>

    <script src="main.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            const strengthDiv = document.getElementById('passwordStrength');
            
            // Validación de fortaleza de contraseña
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                let strength = 0;
                let feedback = [];
                
                if (password.length >= 6) strength++;
                else feedback.push('Al menos 6 caracteres');
                
                if (/[A-Z]/.test(password)) strength++;
                else feedback.push('Una mayúscula');
                
                if (/[a-z]/.test(password)) strength++;
                else feedback.push('Una minúscula');
                
                if (/[0-9]/.test(password)) strength++;
                else feedback.push('Un número');
                
                if (/[^A-Za-z0-9]/.test(password)) strength++;
                else feedback.push('Un símbolo');
                
                // Mostrar indicador de fortaleza
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
            
            // Validación de confirmación de contraseña
            confirmPasswordInput.addEventListener('input', function() {
                const password = passwordInput.value;
                const confirmPassword = this.value;
                
                if (confirmPassword.length === 0) {
                    this.style.borderColor = '';
                    return;
                }
                
                if (password === confirmPassword) {
                    this.style.borderColor = 'var(--success)';
                    hideFieldError(this);
                } else {
                    this.style.borderColor = 'var(--danger)';
                    showFieldError(this, 'Las contraseñas no coinciden');
                }
            });
            
            // Validación de email en tiempo real
            const emailInput = document.getElementById('email');
            emailInput.addEventListener('blur', async function() {
                const email = this.value;
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                
                if (!email) return;
                
                if (!emailRegex.test(email)) {
                    this.style.borderColor = 'var(--danger)';
                    showFieldError(this, 'Email no válido');
                    return;
                }
                
                // Verificar si el email ya existe
                try {
                    const response = await fetch('check-email.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ email: email })
                    });
                    
                    const data = await response.json();
                    
                    if (data.exists) {
                        this.style.borderColor = 'var(--warning)';
                        showFieldError(this, 'Este email ya está registrado');
                    } else {
                        this.style.borderColor = 'var(--success)';
                        hideFieldError(this);
                    }
                } catch (error) {
                    console.error('Error verificando email:', error);
                }
            });
            
            // Validación de documento argentino
            const documentoInput = document.getElementById('documento');
            documentoInput.addEventListener('input', function() {
                const documento = this.value;
                
                if (documento.length === 0) {
                    this.style.borderColor = '';
                    hideFieldError(this);
                    return;
                }
                
                if (documento.length < 7 || documento.length > 8) {
                    this.style.borderColor = 'var(--warning)';
                    showFieldError(this, 'DNI debe tener 7 u 8 dígitos');
                } else {
                    this.style.borderColor = 'var(--success)';
                    hideFieldError(this);
                }
            });
            
            // Formateo de teléfono
            const telefonoInput = document.getElementById('telefono');
            telefonoInput.addEventListener('input', function() {
                let value = this.value.replace(/\D/g, '');
                
                // Formato argentino: +54 9 11 1234-5678
                if (value.length >= 10) {
                    if (value.startsWith('54')) {
                        value = value.substring(2);
                    }
                    
                    if (value.length === 10) {
                        this.value = `+54 9 ${value.substring(0, 2)} ${value.substring(2, 6)}-${value.substring(6)}`;
                    }
                }
            });
            
            function showFieldError(field, message) {
                hideFieldError(field);
                const errorDiv = document.createElement('div');
                errorDiv.className = 'field-error';
                errorDiv.style.cssText = 'color: var(--danger); font-size: 0.8rem; margin-top: 0.25rem;';
                errorDiv.textContent = message;
                field.parentNode.appendChild(errorDiv);
            }
            
            function hideFieldError(field) {
                const existingError = field.parentNode.querySelector('.field-error');
                if (existingError) {
                    existingError.remove();
                }
            }
            
            // Animación de entrada
            const formContainer = document.querySelector('.form-container');
            formContainer.style.opacity = '0';
            formContainer.style.transform = 'translateY(30px)';
            
            setTimeout(() => {
                formContainer.style.transition = 'opacity 0.8s ease, transform 0.8s ease';
                formContainer.style.opacity = '1';
                formContainer.style.transform = 'translateY(0)';
            }, 100);
        });
    </script>
</body>
</html>