<?php
// login.php

// تضمين ملف الوظائف المساعدة (تأكد من وجوده بنفس المسار)
require_once 'functions.php';

// إعدادات جلسة آمنة مع SameSite صارم
session_set_cookie_params([
    'lifetime' => 86400, // يوم واحد
    'path' => '/',
    'httponly' => true, // يمنع الوصول لملف تعريف الارتباط عبر JavaScript
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'), // إرسال ملف تعريف الارتباط عبر HTTPS فقط
    'samesite' => 'Lax' // SameSite Lax للحماية من CSRF مع بعض المرونة
]);
session_start();

// التحقق من صلاحية الجلسة أو إعادة توليد ID الجلسة لمنع هجمات تثبيت الجلسة
if (!isset($_SESSION['initiated'])) {
    session_regenerate_id(true);
    $_SESSION['initiated'] = true;
}

// بيانات الاتصال بقاعدة البيانات (كما هي)
$db_host = 'mysql.railway.internal';
$db_user = 'root';
$db_pass = 'mDxJcHtRORIlpLbtDJKKckeuLgozRUVO';
$db_name = 'railway';
$db_port = 3306;

// إنشاء اتصال آمن بقاعدة البيانات
try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn->connect_error) {
        throw new Exception("خطأ في الاتصال بقاعدة البيانات: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4"); // تعيين الترميز لضمان دعم اللغة العربية
} catch (Exception $e) {
    // في بيئة إنتاجية، سجل الخطأ ولا تعرض تفاصيله للمستخدم.
    error_log($e->getMessage());
    die("حدث خطأ غير متوقع. يرجى المحاولة لاحقًا.");
}

$msg = '';
$username_val = ''; // للحفاظ على اسم المستخدم في الحقل بعد المحاولة

// توليد رمز CSRF عند تحميل الصفحة
$csrf_token = generate_csrf_token();

// إذا كان المستخدم مسجل الدخول، قم بإعادة توجيهه
if (isset($_SESSION['admin_id'])) {
    header("Location: admin.php");
    exit;
}

// التعامل مع طلبات POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. التحقق من رمز CSRF
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        log_failed_login_attempt('CSRF Attack', $_SERVER['REMOTE_ADDR'], 'Invalid CSRF Token');
        $msg = "خطأ أمني: طلب غير صالح.";
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember_me = isset($_POST['remember_me']);

        $username_val = htmlspecialchars($username); // حفظ الاسم للعرض

        // 2. التحقق من المدخلات الأساسية
        if (empty($username) || empty($password)) {
            $msg = "الرجاء إدخال اسم المستخدم وكلمة المرور.";
        } else {
            // 3. التحقق من محاولات الدخول الفاشلة (هذا الجزء يحتاج إلى تنفيذ قوي في بيئة إنتاجية)
            // هذا مجرد placeholder كما ذكرنا سابقاً.
            $ip_address = $_SERVER['REMOTE_ADDR'];
            // $max_attempts = 5; 
            // $lockout_time = 300; 
            // $failed_attempts = get_failed_login_attempts($ip_address, $conn);
            // if ($failed_attempts >= $max_attempts) {
            //     log_failed_login_attempt($username, $ip_address, 'Too many failed attempts (locked out)');
            //     $msg = "لقد تجاوزت الحد الأقصى لمحاولات الدخول. يرجى المحاولة بعد 5 دقائق.";
            // } else {
                // 4. التحقق من بيانات الدخول
                $stmt = $conn->prepare("SELECT id, password FROM admins WHERE username = ? LIMIT 1");
                if ($stmt === false) {
                    error_log("Failed to prepare statement: " . $conn->error);
                    $msg = "حدث خطأ داخلي. يرجى المحاولة لاحقًا.";
                } else {
                    $stmt->bind_param("s", $username);
                    $stmt->execute();
                    $stmt->bind_result($uid, $pw_hash);

                    if ($stmt->fetch() && password_verify($password, $pw_hash)) {
                        // تسجيل دخول ناجح
                        $_SESSION['admin_id'] = $uid;
                        $_SESSION['last_activity'] = time();
                        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];

                        if ($remember_me) {
                            set_remember_me_cookie($uid);
                        } else {
                            clear_remember_me_cookie();
                        }

                        $stmt->close();
                        $conn->close();
                        header("Location: admin.php");
                        exit;
                    } else {
                        // تسجيل دخول فاشل
                        $msg = "بيانات الدخول غير صحيحة!";
                        log_failed_login_attempt($username, $ip_address, 'Invalid credentials');
                    }
                    $stmt->close();
                }
            // }
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
            --primary-color: #00bcd4; /* لون أزرق سماوي جديد */
            --secondary-color: #3f51b5; /* لون بنفسجي أعمق */
            --accent-color: #00e5ff; /* لون توهج فاتح */
            --dark-background: #0d1a2b; /* خلفية داكنة جداً */
            --light-text: #e0f2f7; /* لون نص فاتح */
            --input-bg: rgba(255, 255, 255, 0.08); /* خلفية حقول شفافة */
            --border-color: rgba(0, 229, 255, 0.3); /* حدود شفافة متوهجة */
            --form-bg: rgba(13, 26, 43, 0.7); /* خلفية الفورم شبه شفافة */
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
            perspective: 1000px; /* لتمكين تأثيرات 3D */
        }

        /* خلفية الفضاء النجمية مع حركة خفيفة */
        .galaxy-background {
            position: fixed;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            background: radial-gradient(circle at top left, var(--secondary-color) 0%, transparent 40%),
                        radial-gradient(circle at bottom right, var(--primary-color) 0%, transparent 50%),
                        url('https://www.transparenttextures.com/patterns/stardust.png') repeat; /* يمكنك استبدالها بصورة نجوم أفضل */
            background-size: 200% 200%;
            animation: moveBackground 60s linear infinite;
            z-index: -1;
        }

        @keyframes moveBackground {
            0% { background-position: 0% 0%; }
            100% { background-position: 100% 100%; }
        }

        /* حاوية الأشكال الهندسية المتوهجة العائمة */
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
            border-radius: 50%; /* لجعلها دوائر أو أشكال بيضاوية */
            opacity: 0;
            animation: floatAndGlow 20s infinite ease-in-out;
            box-shadow: 0 0 15px var(--accent-color), 0 0 25px rgba(0, 229, 255, 0.3);
        }
        /* أحجام ومواضع وتأخيرات مختلفة للأشكال */
        .shape:nth-child(1) { width: 80px; height: 80px; top: 10%; left: 15%; animation-delay: 0s; transform: rotate(0deg); }
        .shape:nth-child(2) { width: 120px; height: 120px; top: 30%; right: 20%; animation-delay: 5s; transform: rotate(45deg); border-radius: 30%; } /* شكل مربع/غير دائري */
        .shape:nth-child(3) { width: 60px; height: 60px; bottom: 5%; left: 40%; animation-delay: 10s; transform: rotate(90deg); }
        .shape:nth-child(4) { width: 100px; height: 100px; top: 50%; left: 5%; animation-delay: 15s; transform: rotate(135deg); border-radius: 40%; }
        .shape:nth-child(5) { width: 90px; height: 90px; top: 20%; right: 5%; animation-delay: 20s; transform: rotate(180deg); }

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
            box-shadow: var(--shadow-glow); /* توهج ثلاثي الأبعاد */
            border: 2px solid var(--border-color); /* حدود متوهجة */
            padding: 40px 35px 35px 35px; /* زيادة المساحة الداخلية */
            max-width: 450px; /* حجم أكبر */
            width: 100%;
            position: relative;
            z-index: 10;
            transform-style: preserve-3d; /* لتطبيق التحويلات 3D على العناصر الداخلية */
            animation: fadeInScale 1s ease-out;
        }

        @keyframes fadeInScale {
            from {
                opacity: 0;
                transform: scale(0.8) translateY(50px) rotateX(10deg);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0) rotateX(0deg);
            }
        }

        /* تحريك الصندوق بتأثير الماوس (Parallax effect) */
        .login-box-wrapper {
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
            height: 100vh;
            perspective: 1000px; /* لتفعيل الـ parallax */
        }


        .login-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 50px; /* أيقونة أكبر */
            color: var(--accent-color);
            background: rgba(0, 229, 255, 0.15); /* خلفية الأيقونة شفافة متوهجة */
            border-radius: 50%;
            width: 80px;
            height: 80px;
            margin: -75px auto 25px auto; /* لرفع الأيقونة خارج الصندوق قليلاً */
            box-shadow: var(--shadow-glow); /* توهج للأيقونة */
            border: 2px solid rgba(0, 229, 255, 0.5);
            animation: pulseIcon 2s infinite ease-in-out;
        }

        @keyframes pulseIcon {
            0% { transform: scale(1); box-shadow: 0 0 10px var(--accent-color); }
            50% { transform: scale(1.05); box-shadow: 0 0 25px var(--accent-color), 0 0 35px rgba(0, 229, 255, 0.4); }
            100% { transform: scale(1); box-shadow: 0 0 10px var(--accent-color); }
        }

        h3 {
            letter-spacing: 1px;
            font-weight: 700;
            color: var(--light-text);
            margin-bottom: 20px;
            font-size: 2rem; /* حجم أكبر للعنوان */
            text-shadow: 0 0 10px var(--accent-color); /* ظل نصي متوهج */
        }

        .login-box .form-control {
            border-radius: var(--border-radius-md);
            border: 1px solid var(--border-color);
            box-shadow: none;
            padding: 14px 20px;
            font-size: 1.1rem;
            background: var(--input-bg);
            color: var(--light-text);
            transition: border-color 0.3s, background 0.3s, box-shadow 0.3s;
            font-family: inherit;
        }

        .login-box .form-control::placeholder { /* لون placeholder */
            color: rgba(255, 255, 255, 0.6);
        }

        .login-box .form-control:focus {
            border-color: var(--accent-color);
            background: rgba(0, 229, 255, 0.1);
            box-shadow: 0 0 10px var(--accent-color);
            outline: none;
        }

        /* تحسينات Valid/Invalid Feedback */
        .form-control.is-valid, .form-control.is-invalid {
            background-position: left 1rem center; /* ضبط موضع الأيقونة في RTL */
        }
        .form-control.is-valid {
            border-color: #4CAF50; /* أخضر */
        }
        .form-control.is-invalid {
            border-color: #F44336; /* أحمر */
        }
        .invalid-feedback {
            color: #F44336;
            font-size: 0.9rem;
            margin-top: 5px;
        }


        .btn-primary {
            background: linear-gradient(90deg, var(--primary-color) 0%, var(--accent-color) 100%);
            border: none;
            font-weight: 700;
            border-radius: var(--border-radius-md);
            transition: background 0.3s ease, transform 0.2s ease, box-shadow 0.3s;
            font-size: 1.25rem;
            box-shadow: 0 0 15px rgba(0, 229, 255, 0.5); /* ظل متوهج للزر */
            padding: 14px 25px;
            color: var(--dark-background); /* لون نص الزر داكن */
            text-shadow: 0 0 5px rgba(255, 255, 255, 0.4);
        }

        .btn-primary:focus,
        .btn-primary:hover {
            background: linear-gradient(90deg, var(--secondary-color) 0%, var(--primary-color) 100%);
            transform: translateY(-3px) scale(1.02); /* رفع وتكبير طفيف */
            box-shadow: var(--shadow-glow); /* توهج أقوى عند التحويم */
            outline: none;
            color: var(--light-text); /* نص فاتح عند التحويم */
        }

        .btn-primary:active {
            transform: translateY(0);
            box-shadow: 0 0 10px rgba(0, 229, 255, 0.5);
        }

        .form-label {
            font-weight: bold;
            color: var(--light-text);
            margin-bottom: 8px;
            font-size: 1.1rem;
            text-shadow: 0 0 5px rgba(0, 229, 255, 0.3);
        }

        .alert {
            margin-bottom: 25px;
            font-size: 1.05rem;
            border-radius: var(--border-radius-sm);
            text-align: center;
            padding: 15px;
            animation: fadeIn .6s ease-out;
            background-color: rgba(244, 67, 54, 0.8); /* خلفية تنبيه شفافة */
            color: var(--light-text);
            border: 1px solid #F44336;
            box-shadow: 0 0 15px rgba(244, 67, 54, 0.6);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .form-check {
            margin-top: 15px;
            margin-bottom: 20px;
        }
        .form-check-input {
            margin-left: 0.5rem;
            border-color: var(--border-color);
            background-color: var(--input-bg);
            transition: border-color 0.2s, background-color 0.2s;
            cursor: pointer;
        }
        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--accent-color);
            box-shadow: 0 0 8px var(--accent-color);
        }
        .form-check-label {
            color: var(--light-text);
            cursor: pointer;
            font-size: 1rem;
            text-shadow: 0 0 3px rgba(0, 229, 255, 0.2);
        }

        @media (max-width: 480px) {
            .login-box {
                padding: 30px 6vw;
                border-radius: 20px;
            }
            .login-icon {
                font-size: 40px;
                width: 65px;
                height: 65px;
                margin-top: -60px;
            }
            h3 {
                font-size: 1.6rem;
            }
            .btn-primary {
                font-size: 1.1rem;
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

    <div class="login-box-wrapper">
        <div class="login-box">
            <div class="login-icon">
                <i class="fa-solid fa-user-astronaut"></i> </div>
            <h3 class="mb-4 text-center">بوابة الدخول الكونية</h3>
            <?php if ($msg): ?>
                <div class="alert alert-danger shadow-sm"><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>
            <form method="post" autocomplete="off" id="loginForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                <div class="mb-3">
                    <label class="form-label" for="username">اسم المستخدم</label>
                    <input type="text" id="username" name="username" class="form-control" autofocus required value="<?= $username_val ?>">
                    <div class="invalid-feedback">الرجاء إدخال اسم المستخدم.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password">كلمة المرور</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                    <div class="invalid-feedback">الرجاء إدخال كلمة المرور.</div>
                </div>
                <div class="form-check text-end">
                    <input class="form-check-input" type="checkbox" id="remember_me" name="remember_me">
                    <label class="form-check-label" for="remember_me">
                        تذكر بياناتي الفضائية
                    </label>
                </div>
                <button type="submit" class="btn btn-primary w-100 mt-3">
                    <i class="fa-solid fa-rocket ms-2"></i> انطلاق
                </button>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('loginForm');
            const usernameInput = document.getElementById('username');
            const passwordInput = document.getElementById('password');
            const loginBox = document.querySelector('.login-box');
            const loginBoxWrapper = document.querySelector('.login-box-wrapper');

            // Client-side validation (remains similar)
            form.addEventListener('submit', function(event) {
                usernameInput.classList.remove('is-valid', 'is-invalid');
                passwordInput.classList.remove('is-valid', 'is-invalid');

                let isValid = true;

                if (usernameInput.value.trim() === '') {
                    usernameInput.classList.add('is-invalid');
                    isValid = false;
                } else {
                    usernameInput.classList.add('is-valid');
                }

                if (passwordInput.value.trim() === '') {
                    passwordInput.classList.add('is-invalid');
                    isValid = false;
                } else {
                    passwordInput.classList.add('is-valid');
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

            // Parallax effect for the login box (3D mouse movement)
            loginBoxWrapper.addEventListener('mousemove', function(e) {
                const rect = loginBoxWrapper.getBoundingClientRect();
                const centerX = rect.left + rect.width / 2;
                const centerY = rect.top + rect.height / 2;

                const mouseX = e.clientX - centerX;
                const mouseY = e.clientY - centerY;

                const rotateY = (mouseX / centerX) * 10; // Rotate up to 10 degrees on Y-axis
                const rotateX = (mouseY / centerY) * -10; // Rotate up to 10 degrees on X-axis (inverted for natural feel)

                loginBox.style.transform = `
                    scale(1)
                    rotateX(${rotateX}deg)
                    rotateY(${rotateY}deg)
                `;
            });

            // Reset rotation when mouse leaves
            loginBoxWrapper.addEventListener('mouseleave', function() {
                loginBox.style.transform = `
                    scale(1)
                    rotateX(0deg)
                    rotateY(0deg)
                `;
                loginBox.style.transition = 'transform 0.5s ease-out'; // Smooth transition back
                setTimeout(() => {
                    loginBox.style.transition = 'none'; // Remove transition after reset
                }, 500);
            });
        });
    </script>
</body>


</html>
