<?php
// functions.php

// دالة لتسجيل محاولات الدخول الفاشلة
function log_failed_login_attempt($username, $ip_address, $reason) {
    // يمكنك هنا تسجيل هذه المحاولات في قاعدة بيانات أو ملف سجل.
    // لغرض العرض، سنستخدم ملفًا بسيطًا.
    $log_file = 'failed_login_attempts.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] IP: $ip_address, Username: '$username', Reason: $reason\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

// دالة للتحقق من عدد محاولات الدخول الفاشلة لعنوان IP معين
function get_failed_login_attempts($ip_address, $conn) {
    // يمكنك تخزين محاولات الدخول في جدول منفصل في قاعدة البيانات
    // هذا مثال بسيط جداً.
    // في بيئة إنتاجية، ستحتاج إلى جدول خاص لتتبع محاولات الدخول الفاشلة بشكل أكثر تفصيلاً ودقة
    // (مثل الوقت، عدد المحاولات، وقت القفل، إلخ).
    
    // لغرض العرض، سنفترض أننا نتحقق من ملف السجل لتبسيط المثال.
    // **تنبيه:** هذا ليس حلاً آمناً أو فعالاً للإنتاج!
    // في بيئة إنتاجية، يجب تخزين هذه البيانات في قاعدة بيانات مع مؤقت لإعادة تعيين العداد.

    $log_file = 'failed_login_attempts.log';
    if (!file_exists($log_file)) {
        return 0;
    }
    $attempts = 0;
    $lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $time_window = time() - (5 * 60); // آخر 5 دقائق

    foreach ($lines as $line) {
        if (strpos($line, "IP: $ip_address") !== false) {
            // محاولة بسيطة جداً لاستخلاص الوقت.
            // يجب أن يكون لديك صيغة تسجيل قياسية لسهولة التحليل.
            preg_match('/\[(.*?)\]/', $line, $matches);
            if (isset($matches[1])) {
                $log_timestamp = strtotime($matches[1]);
                if ($log_timestamp > $time_window) {
                    $attempts++;
                }
            }
        }
    }
    return $attempts;
}

// دالة لقفل عنوان IP مؤقتًا
function lock_ip_address($ip_address) {
    // يمكنك إضافة عنوان IP إلى قائمة سوداء مؤقتة في قاعدة بيانات أو ذاكرة تخزين مؤقت (مثل Redis)
    // وتحديد مدة القفل.
    // لغرض العرض، لن نقوم بتنفيذ قفل فعلي هنا، ولكن هذه هي النقطة التي ستقوم بها بذلك.
}

// دالة لتوليد رمز CSRF
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// دالة للتحقق من رمز CSRF
function verify_csrf_token($token) {
    if (empty($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        return false;
    }
    // يمكنك إزالة الرمز بعد التحقق منه لمنع إعادة الاستخدام (Token Expiration)
    // unset($_SESSION['csrf_token']); 
    return true;
}

// دالة لتعيين ملف تعريف الارتباط "تذكرني"
function set_remember_me_cookie($user_id) {
    $selector = base64_encode(random_bytes(9));
    $validator = base64_encode(random_bytes(33));
    $hashed_validator = hash('sha256', $validator);

    // في بيئة إنتاجية: قم بتخزين $selector و $hashed_validator في جدول قاعدة بيانات
    // لتتبع جلسات "تذكرني" لكل مستخدم. يجب أن يكون لكل زوج selector/validator صلاحية واحدة.
    // يجب أيضًا أن يكون هناك مؤشر لانتهاء صلاحية الرمز.
    // للحفاظ على بساطة المثال، لن نقوم بحفظها في DB هنا.

    $cookie_value = $selector . ':' . $validator;
    setcookie('remember_me', $cookie_value, [
        'expires' => time() + (30 * 24 * 60 * 60), // 30 يوم
        'path' => '/',
        'httponly' => true,
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'samesite' => 'Strict'
    ]);
}

// دالة لإزالة ملف تعريف الارتباط "تذكرني"
function clear_remember_me_cookie() {
    setcookie('remember_me', '', [
        'expires' => time() - 3600, // انتهت صلاحيتها
        'path' => '/',
        'httponly' => true,
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'samesite' => 'Strict'
    ]);
}

// دالة للتحقق من ملف تعريف الارتباط "تذكرني"
function check_remember_me_cookie($conn) {
    if (isset($_COOKIE['remember_me'])) {
        list($selector, $validator) = explode(':', $_COOKIE['remember_me']);

        // في بيئة إنتاجية: استرجع الـ hashed_validator من قاعدة البيانات باستخدام الـ $selector.
        // ثم قارن الـ $validator المقدم مع الـ hashed_validator المسترجع.
        // إذا تطابقا، قم بتسجيل دخول المستخدم.
        // لغرض العرض، لن نقوم بتنفيذ منطق DB هنا، فقط نوضح الفكرة.

        // مثال بسيط (ليس للإنتاج):
        // لنفترض أن لدينا طريقة ما للتحقق من الـ selector والـ validator.
        // إذا كانا صالحين، نرجع ID المستخدم.
        // return $user_id; 
        
        // هنا، سنفترض أننا لا نقوم بالتحقق من DB في هذا المثال المبسط
        // وبالتالي، لن نقوم بتسجيل الدخول تلقائياً بناءً على "تذكرني" فقط لتبسيط الكود.
        // في بيئة إنتاجية، يجب عليك ربط هذا بسجل دخول المستخدمين.
    }
    return null;
}
?>