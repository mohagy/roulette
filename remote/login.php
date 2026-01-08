<?php
// Remote Employee Login System
session_start();

// If already logged in, redirect to bet distribution
if (isset($_SESSION['user_id']) && isset($_SESSION['remote_session_token'])) {
    header('Location: bet_distribution.php');
    exit;
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "roulette";

date_default_timezone_set('America/Guyana');

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    if (empty($username) || empty($password)) {
        $error_message = 'Please enter both username and password.';
    } else {
        // Check user credentials
        $stmt = $conn->prepare("SELECT user_id, username, password, role FROM users WHERE username = ? AND status = 'active'");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Verify password (assuming plain text for now - should use password_verify in production)
            if ($password === $user['password']) {
                // Check if user has remote access permissions
                $stmt = $conn->prepare("
                    SELECT COUNT(*) as permission_count 
                    FROM remote_access_permissions 
                    WHERE user_id = ? 
                    AND status = 'active' 
                    AND (expires_at IS NULL OR expires_at > NOW())
                ");
                $stmt->bind_param("i", $user['user_id']);
                $stmt->execute();
                $permission_result = $stmt->get_result();
                $permission_data = $permission_result->fetch_assoc();
                
                if ($permission_data['permission_count'] > 0) {
                    // Create session
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    
                    // Create remote session token
                    $session_token = bin2hex(random_bytes(32));
                    $_SESSION['remote_session_token'] = $session_token;
                    
                    // Get client information
                    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
                    
                    // Insert remote session record
                    $stmt = $conn->prepare("
                        INSERT INTO remote_sessions (user_id, session_token, ip_address, user_agent) 
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->bind_param("isss", $user['user_id'], $session_token, $ip_address, $user_agent);
                    $stmt->execute();
                    $session_id = $conn->insert_id;
                    
                    // Log login activity
                    $stmt = $conn->prepare("
                        INSERT INTO remote_activity_logs (session_id, user_id, activity_type, page_url, action_details) 
                        VALUES (?, ?, 'login', ?, ?)
                    ");
                    $page_url = $_SERVER['REQUEST_URI'];
                    $action_details = json_encode([
                        'login_time' => date('Y-m-d H:i:s'),
                        'ip_address' => $ip_address,
                        'user_agent' => $user_agent
                    ]);
                    $stmt->bind_param("iiss", $session_id, $user['user_id'], $page_url, $action_details);
                    $stmt->execute();
                    
                    // Redirect to bet distribution
                    header('Location: bet_distribution.php');
                    exit;
                } else {
                    $error_message = 'You do not have remote access permissions. Please contact your administrator.';
                }
            } else {
                $error_message = 'Invalid username or password.';
            }
        } else {
            $error_message = 'Invalid username or password.';
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Remote Employee Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Nunito', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 40px;
            width: 100%;
            max-width: 450px;
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-header h2 {
            color: #333;
            font-weight: 700;
            margin-bottom: 10px;
        }
        .login-header p {
            color: #666;
            font-size: 14px;
        }
        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .input-group-text {
            border-radius: 10px 0 0 10px;
            border: 2px solid #e9ecef;
            border-right: none;
            background: #f8f9fa;
        }
        .input-group .form-control {
            border-radius: 0 10px 10px 0;
            border-left: none;
        }
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            font-size: 16px;
            width: 100%;
            color: white;
            transition: all 0.3s ease;
        }
        .btn-login:hover {
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
            color: white;
        }
        .alert {
            border-radius: 10px;
            border: none;
        }
        .security-info {
            background: #e3f2fd;
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
            font-size: 12px;
            color: #1976d2;
        }
        .feature-list {
            list-style: none;
            padding: 0;
            margin-top: 15px;
        }
        .feature-list li {
            padding: 5px 0;
            color: #666;
            font-size: 13px;
        }
        .feature-list li i {
            color: #28a745;
            margin-right: 8px;
        }
        .system-status {
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 10px 15px;
            border-radius: 10px;
            font-size: 12px;
        }
        .status-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            background: #28a745;
            border-radius: 50%;
            margin-right: 5px;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
    </style>
</head>
<body>
    <!-- System Status -->
    <div class="system-status">
        <span class="status-indicator"></span>
        Remote System Online
        <br>
        <small><?php echo date('M d, Y g:i A'); ?> GMT-4</small>
    </div>

    <div class="login-container">
        <div class="login-header">
            <i class="fas fa-desktop fa-3x text-primary mb-3"></i>
            <h2>Remote Employee Access</h2>
            <p>Secure login for remote bet distribution monitoring</p>
        </div>

        <?php if ($error_message): ?>
        <div class="alert alert-danger" role="alert">
            <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
        </div>
        <?php endif; ?>

        <form method="post" action="" id="loginForm">
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fas fa-user"></i>
                    </span>
                    <input type="text" class="form-control" id="username" name="username" required 
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                           placeholder="Enter your username">
                </div>
            </div>

            <div class="mb-4">
                <label for="password" class="form-label">Password</label>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fas fa-lock"></i>
                    </span>
                    <input type="password" class="form-control" id="password" name="password" required 
                           placeholder="Enter your password">
                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword()">
                        <i class="fas fa-eye" id="passwordToggle"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-login">
                <i class="fas fa-sign-in-alt"></i> Login to Remote System
            </button>
        </form>

        <div class="security-info">
            <h6><i class="fas fa-shield-alt"></i> Security Features</h6>
            <ul class="feature-list">
                <li><i class="fas fa-check"></i> Session monitoring and tracking</li>
                <li><i class="fas fa-check"></i> Activity logging and compliance</li>
                <li><i class="fas fa-check"></i> Real-time productivity monitoring</li>
                <li><i class="fas fa-check"></i> Secure encrypted connections</li>
                <li><i class="fas fa-check"></i> Georgetown timezone synchronization</li>
            </ul>
        </div>

        <div class="text-center mt-3">
            <small class="text-muted">
                Need access? Contact your administrator<br>
                <a href="../admin/index.php" class="text-decoration-none">Admin Panel</a>
            </small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword() {
            const passwordField = document.getElementById('password');
            const passwordToggle = document.getElementById('passwordToggle');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                passwordToggle.className = 'fas fa-eye-slash';
            } else {
                passwordField.type = 'password';
                passwordToggle.className = 'fas fa-eye';
            }
        }

        // Auto-focus username field
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('username').focus();
        });

        // Form validation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value.trim();
            
            if (!username || !password) {
                e.preventDefault();
                alert('Please enter both username and password.');
                return false;
            }
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Logging in...';
            submitBtn.disabled = true;
        });

        // Detect browser capabilities
        if ('geolocation' in navigator) {
            navigator.geolocation.getCurrentPosition(function(position) {
                // Store location data for security logging
                sessionStorage.setItem('user_location', JSON.stringify({
                    latitude: position.coords.latitude,
                    longitude: position.coords.longitude,
                    accuracy: position.coords.accuracy
                }));
            });
        }

        // Log browser information
        const browserInfo = {
            userAgent: navigator.userAgent,
            language: navigator.language,
            platform: navigator.platform,
            cookieEnabled: navigator.cookieEnabled,
            onLine: navigator.onLine,
            screenResolution: screen.width + 'x' + screen.height,
            timezone: Intl.DateTimeFormat().resolvedOptions().timeZone
        };
        
        sessionStorage.setItem('browser_info', JSON.stringify(browserInfo));
    </script>
</body>
</html>
