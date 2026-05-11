<?php
// login.php - تسجيل دخول المشرف (PDO)

// تضمين ملف الوظائف المساعدة
require_once 'functions.php';

// إعدادات جلسة آمنة
session_set_cookie_params([
    'lifetime' => 86400,
    'path' => '/',
    'httponly' => true,
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'samesite' => 'Lax'
]);
session_start();

if (!isset($_SESSION['initiated'])) {
    session_regenerate_id(true);
    $_SESSION['initiated'] = true;
}

// بيانات الاتصال بقاعدة البيانات
$db_host = 'mysql.railway.internal';
$db_user = 'root';
$db_pass = 'ExvKbuJnGIvDATyXWCHtpjOFluFAgeqQ';
$db_name = 'railway';
$db_port = 3306;

// إنشاء اتصال آمن بقاعدة البيانات باستخدام PDO
try {
    $pdo = new PDO(
        "mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    error_log($e->getMessage());
    die("حدث خطأ غير متوقع. يرجى المحاولة لاحقًا.");
}

$msg = '';
$username_val = '';

// توليد رمز CSRF
$csrf_token = generate_csrf_token();

// إذا كان المستخدم مسجل الدخول
if (isset($_SESSION['admin_id'])) {
    header("Location: admin.php");
    exit;
}

// التعامل مع طلبات POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        log_failed_login_attempt('CSRF Attack', $_SERVER['REMOTE_ADDR'], 'Invalid CSRF Token');
        $msg = "خطأ أمني: طلب غير صالح.";
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember_me = isset($_POST['remember_me']);

        $username_val = htmlspecialchars($username);

        if (empty($username) || empty($password)) {
            $msg = "الرجاء إدخال اسم المستخدم وكلمة المرور.";
        } else {
            $ip_address = $_SERVER['REMOTE_ADDR'];

            $stmt = $pdo->prepare("SELECT id, password_hash, role, display_name FROM admin_users WHERE username = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                // تسجيل دخول ناجح
                session_regenerate_id(true);
                $_SESSION['admin_id'] = $user['id'];
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_user_id'] = $user['id'];
                $_SESSION['admin_username'] = $username;
                $_SESSION['admin_display_name'] = $user['display_name'];
                $_SESSION['admin_role'] = $user['role'];
                $_SESSION['last_activity'] = time();
                $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];

                if ($remember_me) {
                    set_remember_me_cookie($user['id']);
                } else {
                    clear_remember_me_cookie();
                }

                header("Location: admin.php");
                exit;
            } else {
                $msg = "بيانات الدخول غير صحيحة!";
                log_failed_login_attempt($username, $ip_address, 'Invalid credentials');
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <title>تسجيل دخول المشرف - عالم الخيال</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary-color: #00bcd4;
            --secondary-color: #3f51b5;
            --accent-color: #00e5ff;
            --dark-background: #0d1a2b;
            --light-text: #e0f2f7;
            --input-bg: rgba(255, 255, 255, 0.08);
            --border-color: rgba(0, 229, 255, 0.3);
            --form-bg: rgba(13, 26, 43, 0.7);
            --shadow-glow: 0 0 20px rgba(0, 229, 255, 0.6), 0 0 30px rgba(0, 229, 255, 0.4);
            --shadow-hover: 0 0 15px var(--accent-color);
            --border-radius-lg: 30px;
            --border-radius-md: 15px;
            --border-radius-sm: 8px;
        }

        body {
            background-color: var(--dark-background);
            min-height: 100vh;
            font-family: 'Cairo', Tahoma, Arial, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            overflow: hidden;
            perspective: 1000px;
        }

        .galaxy-background {
            position: fixed;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            background: radial-gradient(circle at top left, var(--secondary-color) 0%, transparent 40%),
                        radial-gradient(circle at bottom right, var(--primary-color) 0%, transparent 50%);
            background-size: 200% 200%;
            animation: moveBackground 60s linear infinite;
            z-index: -1;
        }

        @keyframes moveBackground {
            0% { background-position: 0% 0%; }
            100% { background-position: 100% 100%; }
        }

        .floating-shapes {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            pointer-events: none;
            overflow: hidden;
        }

        .shape {
            position: absolute;
            background: linear-gradient(45deg, rgba(0, 229, 255, 0.5), rgba(63, 81, 181, 0.5));
            border-radius: 50%;
            opacity: 0;
            animation: floatAndGlow 20s infinite ease-in-out;
            box-shadow: 0 0 15px var(--accent-color), 0 0 25px rgba(0, 229, 255, 0.3);
        }
        .shape:nth-child(1) { width: 80px; height: 80px; top: 10%; left: 15%; animation-delay: 0s; }
        .shape:nth-child(2) { width: 120px; height: 120px; top: 30%; right: 20%; animation-delay: 5s; border-radius: 30%; }
        .shape:nth-child(3) { width: 60px; height: 60px; bottom: 5%; left: 40%; animation-delay: 10s; }
        .shape:nth-child(4) { width: 100px; height: 100px; top: 50%; left: 5%; animation-delay: 15s; border-radius: 40%; }
        .shape:nth-child(5) { width: 90px; height: 90px; top: 20%; right: 5%; animation-delay: 20s; }

        @keyframes floatAndGlow {
            0% { transform: translate(0, 0) rotate(0deg) scale(0.8); opacity: 0; }
            20% { opacity: 0.7; }
            50% { transform: translate(50px, -50px) rotate(180deg) scale(1.2); opacity: 0.5; }
            80% { opacity: 0.7; }
            100% { transform: translate(0, 0) rotate(360deg) scale(0.8); opacity: 0; }
        }

        .login-box {
            background: var(--form-bg);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-glow);
            border: 2px solid var(--border-color);
            padding: 40px 35px 35px 35px;
            max-width: 450px;
            width: 100%;
            position: relative;
            z-index: 10;
            transform-style: preserve-3d;
            animation: fadeInScale 1s ease-out;
        }

        @keyframes fadeInScale {
            from { opacity: 0; transform: scale(0.8) translateY(50px) rotateX(10deg); }
            to { opacity: 1; transform: scale(1) translateY(0) rotateX(0deg); }
        }

        .login-box-wrapper {
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
            padding: 20px;
        }

        .login-box h3 {
            color: var(--accent-color);
            font-weight: 700;
            margin-bottom: 25px;
            text-shadow: 0 0 10px rgba(0, 229, 255, 0.5);
            text-align: center;
        }

        .login-box .form-label {
            color: var(--light-text);
            font-weight: 500;
        }

        .login-box .form-control {
            background-color: var(--input-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            color: var(--light-text);
            padding: 12px 15px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .login-box .form-control:focus {
            background-color: rgba(255, 255, 255, 0.12);
            border-color: var(--accent-color);
            box-shadow: var(--shadow-hover);
            color: #fff;
        }

        .login-box .form-control::placeholder {
            color: rgba(224, 242, 247, 0.5);
        }

        .login-box .form-check-label {
            color: var(--light-text);
            font-size: 0.9rem;
        }

        .login-box .form-check-input {
            background-color: var(--input-bg);
            border-color: var(--border-color);
        }

        .login-box .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .login-box .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            border-radius: var(--border-radius-md);
            padding: 12px;
            font-size: 1.1rem;
            font-weight: 700;
            letter-spacing: 1px;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0, 188, 212, 0.4);
        }

        .login-box .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 188, 212, 0.6);
        }

        .login-box .btn-primary:active {
            transform: translateY(0);
        }

        .alert-danger {
            background-color: rgba(220, 53, 69, 0.2);
            border: 1px solid rgba(220, 53, 69, 0.5);
            color: #ff8a8a;
            border-radius: var(--border-radius-sm);
            text-align: center;
        }

        .input-group-text {
            background-color: transparent;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            color: var(--accent-color);
        }

        .toggle-password {
            cursor: pointer;
            color: var(--accent-color);
            background: transparent;
            border: 1px solid var(--border-color);
            border-radius: 0 var(--border-radius-sm) var(--border-radius-sm) 0 !important;
            transition: all 0.3s ease;
        }

        .toggle-password:hover {
            background-color: rgba(0, 229, 255, 0.1);
            color: #fff;
        }

        .is-invalid {
            border-color: #dc3545 !important;
            box-shadow: 0 0 10px rgba(220, 53, 69, 0.3) !important;
        }

        .is-valid {
            border-color: var(--accent-color) !important;
            box-shadow: 0 0 10px rgba(0, 229, 255, 0.3) !important;
        }

        @media (max-width: 576px) {
            .login-box {
                margin: 15px;
                padding: 30px 20px;
                border-radius: var(--border-radius-md);
            }
        }
    </style>
</head>

<body>
    <div class="galaxy-background"></div>
    <div class="floating-shapes">
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
    </div>

    <div class="login-box-wrapper" id="loginBoxWrapper">
        <div class="login-box" id="loginBox">
            <h3><i class="fas fa-user-shield me-2"></i>تسجيل دخول المشرف</h3>

            <?php if (!empty($msg)): ?>
                <div class="alert alert-danger mb-3"><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>

            <form method="POST" action="login.php" id="loginForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                <div class="mb-3">
                    <label for="username" class="form-label"><i class="fas fa-user me-1"></i>اسم المستخدم</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-at"></i></span>
                        <input type="text" class="form-control" id="username" name="username"
                            placeholder="أدخل اسم المستخدم" value="<?= $username_val ?>" required autofocus autocomplete="username">
                    </div>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label"><i class="fas fa-lock me-1"></i>كلمة المرور</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-key"></i></span>
                        <input type="password" class="form-control" id="password" name="password"
                            placeholder="أدخل كلمة المرور" required autocomplete="current-password">
                        <button class="btn toggle-password" type="button" id="togglePassword">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="rememberMe" name="remember_me">
                    <label class="form-check-label" for="rememberMe">تذكرني</label>
                </div>

                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-sign-in-alt me-2"></i>تسجيل الدخول
                </button>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');
            const usernameInput = document.getElementById('username');
            const loginForm = document.getElementById('loginForm');
            const loginBox = document.getElementById('loginBox');
            const loginBoxWrapper = document.getElementById('loginBoxWrapper');

            togglePassword.addEventListener('click', function () {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                this.querySelector('i').classList.toggle('fa-eye');
                this.querySelector('i').classList.toggle('fa-eye-slash');
            });

            loginForm.addEventListener('submit', function (event) {
                let isValid = true;
                if (usernameInput.value.trim() === '') {
                    usernameInput.classList.add('is-invalid');
                    isValid = false;
                } else {
                    usernameInput.classList.remove('is-invalid');
                }
                if (passwordInput.value.trim() === '') {
                    passwordInput.classList.add('is-invalid');
                    isValid = false;
                } else {
                    passwordInput.classList.remove('is-invalid');
                }
                if (!isValid) {
                    event.preventDefault();
                }
            });

            usernameInput.addEventListener('input', function() {
                if (usernameInput.value.trim() !== '') {
                    usernameInput.classList.remove('is-invalid');
                    usernameInput.classList.add('is-valid');
                } else {
                    usernameInput.classList.remove('is-valid');
                    usernameInput.classList.add('is-invalid');
                }
            });

            passwordInput.addEventListener('input', function() {
                if (passwordInput.value.trim() !== '') {
                    passwordInput.classList.remove('is-invalid');
                    passwordInput.classList.add('is-valid');
                } else {
                    passwordInput.classList.remove('is-valid');
                    passwordInput.classList.add('is-invalid');
                }
            });

            loginBoxWrapper.addEventListener('mousemove', function(e) {
                const rect = loginBoxWrapper.getBoundingClientRect();
                const centerX = rect.left + rect.width / 2;
                const centerY = rect.top + rect.height / 2;
                const mouseX = e.clientX - centerX;
                const mouseY = e.clientY - centerY;
                const rotateY = (mouseX / centerX) * 10;
                const rotateX = (mouseY / centerY) * -10;
                loginBox.style.transform = `scale(1) rotateX(${rotateX}deg) rotateY(${rotateY}deg)`;
            });

            loginBoxWrapper.addEventListener('mouseleave', function() {
                loginBox.style.transform = `scale(1) rotateX(0deg) rotateY(0deg)`;
                loginBox.style.transition = 'transform 0.5s ease-out';
                setTimeout(() => { loginBox.style.transition = 'none'; }, 500);
            });
        });
    </script>
    <script>
    (function(){
        document.addEventListener('contextmenu', function(e){ e.preventDefault(); });
        document.addEventListener('keydown', function(e){
            if(e.key==='F12'||(e.ctrlKey&&e.shiftKey&&(e.key==='I'||e.key==='J'||e.key==='C'))||(e.ctrlKey&&e.key==='u')||(e.ctrlKey&&e.key==='s')){
                e.preventDefault(); return false;
            }
        });
    })();
    </script>
</body>
</html>
