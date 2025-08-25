<?php
require_once './config/db_connection.php';

$error_message = '';
$success_message = '';

// Procesar formulario de login
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'login') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error_message = 'Por favor completa todos los campos';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM Usuario WHERE Mail = ? AND activo = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['Contrasena'])) {
            $_SESSION['user_id'] = $user['id_usuario'];
            $_SESSION['user_name'] = $user['Nombre'];
            $_SESSION['user_email'] = $user['Mail'];
            
            // Respuesta AJAX
            if (isset($_POST['ajax'])) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => 'Inicio de sesión exitoso',
                    'redirect' => isset($_GET['redirect']) ? $_GET['redirect'] : 'index.php'
                ]);
                exit;
            }
            
            // Redirección normal
            $redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'index.php';
            header("Location: $redirect");
            exit;
        } else {
            $error_message = 'Email o contraseña incorrectos';
            
            if (isset($_POST['ajax'])) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => $error_message
                ]);
                exit;
            }
        }
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
    <title>Iniciar Sesión - TickFast</title>
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
                <li><a href="register.php" class="nav-link">Registrarse</a></li>
            </ul>
        </nav>
    </header>

    <!-- Contenido Principal -->
    <main class="main-content">
        <div class="container">
            <div class="form-container">
                <h1 class="form-title">Iniciar Sesión</h1>
                
                <?php if ($error_message): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
                <?php endif; ?>
                
                <?php if ($success_message): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
                <?php endif; ?>
                
                <form method="POST" data-ajax="true">
                    <input type="hidden" name="action" value="login">
                    <input type="hidden" name="ajax" value="1">
                    
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" class="form-control" required
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                               placeholder="tu@email.com">
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Contraseña</label>
                        <input type="password" id="password" name="password" class="form-control" required
                               placeholder="Tu contraseña">
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="remember" value="1"> Recordarme
                        </label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        Iniciar Sesión
                    </button>
                </form>
                
                <div style="text-align: center; margin-top: 2rem;">
                    <p>¿No tienes cuenta? <a href="register.php" style="color: var(--accent-color);">Regístrate aquí</a></p>
                    <p><a href="forgot-password.php" style="color: var(--gray-medium);">¿Olvidaste tu contraseña?</a></p>
                </div>
                
                <!-- Demostración rápida -->
                <div style="background: var(--gray-light); padding: 1.5rem; border-radius: var(--border-radius); margin-top: 2rem;">
                    <h4 style="margin-bottom: 1rem; text-align: center;">Demo - Usuarios de prueba</h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; font-size: 0.9rem;">
                        <div>
                            <strong>Usuario Admin:</strong><br>
                            admin@tickfast.com<br>
                            admin123
                        </div>
                        <div>
                            <strong>Usuario Cliente:</strong><br>
                            cliente@test.com<br>
                            cliente123
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
            // Auto-completar con credenciales demo
            const demoButtons = document.querySelectorAll('[data-demo]');
            
            // Crear botones demo
            const demoContainer = document.createElement('div');
            demoContainer.style.cssText = 'display: flex; gap: 1rem; margin-top: 1rem;';
            
            const adminBtn = document.createElement('button');
            adminBtn.type = 'button';
            adminBtn.className = 'btn btn-secondary';
            adminBtn.style.cssText = 'flex: 1; font-size: 0.9rem;';
            adminBtn.textContent = 'Demo Admin';
            adminBtn.onclick = () => fillDemoCredentials('admin@tickfast.com', 'admin123');
            
            const clientBtn = document.createElement('button');
            clientBtn.type = 'button';
            clientBtn.className = 'btn btn-secondary';
            clientBtn.style.cssText = 'flex: 1; font-size: 0.9rem;';
            clientBtn.textContent = 'Demo Cliente';
            clientBtn.onclick = () => fillDemoCredentials('cliente@test.com', 'cliente123');
            
            demoContainer.appendChild(adminBtn);
            demoContainer.appendChild(clientBtn);
            
            const form = document.querySelector('form');
            form.appendChild(demoContainer);
            
            function fillDemoCredentials(email, password) {
                document.getElementById('email').value = email;
                document.getElementById('password').value = password;
                
                // Efecto visual
                document.getElementById('email').style.background = '#e8f5e8';
                document.getElementById('password').style.background = '#e8f5e8';
                
                setTimeout(() => {
                    document.getElementById('email').style.background = '';
                    document.getElementById('password').style.background = '';
                }, 1000);
            }
            
            // Validación en tiempo real
            const emailInput = document.getElementById('email');
            const passwordInput = document.getElementById('password');
            
            emailInput.addEventListener('blur', function() {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (this.value && !emailRegex.test(this.value)) {
                    this.style.borderColor = 'var(--danger)';
                    showFieldError(this, 'Email no válido');
                } else {
                    this.style.borderColor = '';
                    hideFieldError(this);
                }
            });
            
            passwordInput.addEventListener('input', function() {
                if (this.value.length > 0 && this.value.length < 6) {
                    this.style.borderColor = 'var(--warning)';
                    showFieldError(this, 'La contraseña debe tener al menos 6 caracteres');
                } else {
                    this.style.borderColor = '';
                    hideFieldError(this);
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
                formContainer.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                formContainer.style.opacity = '1';
                formContainer.style.transform = 'translateY(0)';
            }, 100);
        });
    </script>
</body>
</html>