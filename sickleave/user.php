<?php
/**
 * بوابة المرضى - user.php
 * المرضى يُنشئون إجازات مرضية حقيقية بنفس قالب لوحة التحكم
 * (نسخة فخمة جداً وحديثة كلياً مبنية على Bootstrap 5.3 + إخفاء البيانات افتراضياً + تنبيه التعديل واتس)
 */

ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? '1' : '0');
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', '1');
session_start();

// إخفاء معلومات الخادم والمسارات
header_remove('X-Powered-By');
header_remove('Server');

date_default_timezone_set('Asia/Riyadh');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src \'self\' \'unsafe-inline\' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; font-src \'self\' https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src \'self\' data: https:; connect-src \'self\';');

// منع عرض أخطاء PHP للمستخدمين
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(0);

// ======================== إعدادات قاعدة البيانات ========================
$db_host = 'mysql.railway.internal';
$db_user = 'root';
$db_pass = 'ExvKbuJnGIvDATyXWCHtpjOFluFAgeqQ';
$db_name = 'railway';
$db_port = 3306;

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
    die('<div style="font-family:sans-serif;text-align:center;padding:50px;color:red;">فشل الاتصال بقاعدة البيانات</div>');
}

$pdo->exec("SET time_zone = '+03:00'");

// ======================== دوال الأمان ========================
function patient_csrf_token(): string {
    if (empty($_SESSION['patient_csrf_token'])) {
        $_SESSION['patient_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['patient_csrf_token'];
}

function patient_csrf_input(): string {
    return '<input type="hidden" name="csrf_token" value="' . patient_csrf_token() . '">';
}

function patient_verify_csrf(string $token): bool {
    return isset($_SESSION['patient_csrf_token']) && hash_equals($_SESSION['patient_csrf_token'], $token);
}

// ======================== دوال مساعدة ========================
function nowSaudiUser(): string {
    return (new DateTime('now', new DateTimeZone('Asia/Riyadh')))->format('Y-m-d H:i:s');
}

function isPatientLoggedIn(): bool {
    return isset($_SESSION['patient_logged_in']) && $_SESSION['patient_logged_in'] === true;
}

function checkPatientActive(PDO $pdo): bool {
    if (!isPatientLoggedIn()) return false;
    $uid = (int)$_SESSION['patient_user_id'];
    $stmt = $pdo->prepare("SELECT is_active FROM admin_users WHERE id = ?");
    $stmt->execute([$uid]);
    $row = $stmt->fetch();
    if (!$row || !$row['is_active']) {
        session_destroy();
        return false;
    }
    return true;
}

function gregorianToHijriUser($gYear, $gMonth, $gDay) {
    $gYear = (int)$gYear; $gMonth = (int)$gMonth; $gDay = (int)$gDay;
    $a = intval((14 - $gMonth) / 12);
    $y = $gYear + 4800 - $a;
    $m = $gMonth + 12 * $a - 3;
    $jdn = $gDay + intval((153 * $m + 2) / 5) + 365 * $y + intval($y / 4) - intval($y / 100) + intval($y / 400) - 32045;
    $epoch = 1948440;
    $days = $jdn - $epoch;
    $hYear = intval(floor(($days - 1) / 354.36667) + 1);
    $leapYears = [2, 5, 7, 10, 13, 16, 18, 21, 24, 26, 29];
    $hijriYearStart = function($year) use ($epoch, $leapYears) {
        $y2 = $year - 1;
        $cycle = intval($y2 / 30);
        $yearInCycle = $y2 % 30;
        $leapCount = 0;
        foreach ($leapYears as $ly) { if ($ly <= $yearInCycle) $leapCount++; }
        return $epoch + $cycle * 10631 + $yearInCycle * 354 + $leapCount;
    };
    while ($hijriYearStart($hYear + 1) <= $jdn) $hYear++;
    while ($hijriYearStart($hYear) > $jdn) $hYear--;
    $dayOfYear = $jdn - $hijriYearStart($hYear) + 1;
    $isLeap = in_array($hYear % 30, $leapYears);
    $hMonth = 1; $remaining = $dayOfYear;
    for ($mn = 1; $mn <= 12; $mn++) {
        $md = ($mn % 2 == 1) ? 30 : 29;
        if ($mn == 12 && $isLeap) $md = 30;
        if ($remaining <= $md) { $hMonth = $mn; $hDay = $remaining; break; }
        $remaining -= $md;
    }
    return ['year' => $hYear, 'month' => $hMonth, 'day' => $hDay ?? $remaining];
}

function toHijriStrUser($d) {
    if (!$d) return '';
    $parts = explode('-', $d);
    if (count($parts) !== 3) return $d;
    $h = gregorianToHijriUser((int)$parts[0], (int)$parts[1], (int)$parts[2]);
    return sprintf('%02d-%02d-%04d', $h['day'], $h['month'], $h['year']);
}

function fmtDateUser($d) {
    if (!$d) return '';
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt ? $dt->format('d/m/Y') : $d;
}

function fmtDateEnUser($d) {
    if (!$d) return '';
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt ? $dt->format('d-m-Y') : $d;
}

function getUsedDaysUser(PDO $pdo, int $patientId, int $userId): int {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(days_count),0) FROM sick_leaves WHERE patient_id = ? AND created_by_user_id = ? AND deleted_at IS NULL");
    $stmt->execute([$patientId, $userId]);
    return (int)$stmt->fetchColumn();
}

function generateServiceCodeUser($pdo, $prefix, $issueDate = null) {
    $prefix = strtoupper(trim($prefix));
    if (!in_array($prefix, ['GSL', 'PSL'])) $prefix = 'GSL';
    $issueDateObj = DateTime::createFromFormat('Y-m-d', (string)$issueDate, new DateTimeZone('Asia/Riyadh'));
    if (!$issueDateObj) $issueDateObj = new DateTime('now', new DateTimeZone('Asia/Riyadh'));
    $datePart = $issueDateObj->format('ymd');
    $stmt = $pdo->query("SELECT service_code FROM sick_leaves ORDER BY id DESC LIMIT 1");
    $last = $stmt->fetchColumn();
    $num = 1;
    if ($last && preg_match('/^(?:GSL|PSL)\d{6}(\d+)$/', $last, $m)) {
        $num = intval($m[1]) + 1;
    }
    return $prefix . $datePart . str_pad((string)$num, 5, '0', STR_PAD_LEFT);
}

function formatHijriDateSpanUser(string $date): string {
    $safeDate = htmlspecialchars($date, ENT_QUOTES);
    return '<span dir="ltr" style="unicode-bidi:isolate;direction:ltr;display:inline-block;">' . $safeDate . '</span>';
}

// ======================== معالجة الطلبات ========================
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// تسجيل الدخول
if ($action === 'patient_login') {
    if (!patient_verify_csrf($_POST['csrf_token'] ?? '')) {
        $loginError = 'طلب غير صالح. يرجى إعادة المحاولة.';
        unset($_SESSION['patient_csrf_token']);
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $maxAttempts = 5;
        $lockMinutes = 15;
        $_SESSION['patient_login_attempts'] = $_SESSION['patient_login_attempts'] ?? 0;
        $_SESSION['patient_login_lock_until'] = $_SESSION['patient_login_lock_until'] ?? null;

        if (!empty($_SESSION['patient_login_lock_until']) && time() < intval($_SESSION['patient_login_lock_until'])) {
            $remain = ceil((intval($_SESSION['patient_login_lock_until']) - time()) / 60);
            $loginError = "تم قفل تسجيل الدخول مؤقتاً. حاول بعد {$remain} دقيقة.";
        } elseif (empty($username) || empty($password)) {
            $loginError = 'يرجى إدخال اسم المستخدم وكلمة المرور.';
        } else {
            $stmtCheck = $pdo->prepare("SELECT u.*, pa.patient_id, pa.allowed_days, pa.expiry_date FROM admin_users u INNER JOIN patient_accounts pa ON pa.user_id = u.id WHERE u.username = ? AND u.is_active = 1");
            $stmtCheck->execute([$username]);
            $userCheck = $stmtCheck->fetch();

            if ($userCheck && password_verify($password, $userCheck['password_hash'])) {
                if (empty($userCheck['patient_id']) || (int)$userCheck['patient_id'] <= 0) {
                    $_SESSION['patient_login_attempts'] = intval($_SESSION['patient_login_attempts'] ?? 0) + 1;
                    $loginError = 'اسم المستخدم أو كلمة المرور غير صحيحة.';
                } elseif (!$userCheck['is_active']) {
                    $loginError = 'هذا الحساب معطّل. يرجى التواصل مع الإدارة.';
                } elseif (!empty($userCheck['expiry_date']) && $userCheck['expiry_date'] < date('Y-m-d')) {
                    $loginError = 'انتهت صلاحية هذا الحساب. يرجى التواصل مع الإدارة.';
                } else {
                    session_regenerate_id(true);
                    $_SESSION['patient_login_attempts'] = 0;
                    $_SESSION['patient_login_lock_until'] = null;
                    $_SESSION['patient_logged_in'] = true;
                    $_SESSION['patient_user_id'] = $userCheck['id'];
                    $_SESSION['patient_id'] = $userCheck['patient_id'];
                    $_SESSION['patient_display_name'] = $userCheck['display_name'];
                    $_SESSION['patient_username'] = $userCheck['username'];
                    $_SESSION['patient_allowed_days'] = (int)$userCheck['allowed_days'];
                    header('Location: user.php');
                    exit;
                }
            } else {
                $_SESSION['patient_login_attempts'] = intval($_SESSION['patient_login_attempts'] ?? 0) + 1;
                if ($_SESSION['patient_login_attempts'] >= $maxAttempts) {
                    $_SESSION['patient_login_lock_until'] = time() + ($lockMinutes * 60);
                    $_SESSION['patient_login_attempts'] = 0;
                    $loginError = 'تم تجاوز عدد المحاولات المسموح. تم القفل مؤقتاً 15 دقيقة.';
                } else {
                    $loginError = 'اسم المستخدم أو كلمة المرور غير صحيحة.';
                }
            }
        }
    }
}

// تسجيل الخروج
if ($action === 'logout') {
    session_destroy();
    header('Location: user.php');
    exit;
}

// التحقق من حالة الحساب في كل طلب
if (isPatientLoggedIn() && !checkPatientActive($pdo)) {
    header('Location: user.php?disabled=1');
    exit;
}

// جلب إشعارات المستخدم (AJAX)
if ($action === 'get_user_notifications' && isPatientLoggedIn()) {
    header('Content-Type: application/json; charset=utf-8');
    $uid = (int)$_SESSION['patient_user_id'];
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES admin_users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $stmt = $pdo->prepare("SELECT * FROM user_notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
    $stmt->execute([$uid]);
    $notifs = $stmt->fetchAll();
    $unreadStmt = $pdo->prepare("SELECT COUNT(*) FROM user_notifications WHERE user_id = ? AND is_read = 0");
    $unreadStmt->execute([$uid]);
    echo json_encode(['success' => true, 'notifications' => $notifs, 'unread_count' => (int)$unreadStmt->fetchColumn()]);
    exit;
}

// تحديد الإشعارات كمقروءة (AJAX)
if ($action === 'mark_user_notifications_read' && isPatientLoggedIn()) {
    header('Content-Type: application/json; charset=utf-8');
    $uid = (int)$_SESSION['patient_user_id'];
    $pdo->prepare("UPDATE user_notifications SET is_read = 1 WHERE user_id = ?")->execute([$uid]);
    echo json_encode(['success' => true]);
    exit;
}

// جلب الأطباء حسب المستشفى (AJAX)
if ($action === 'get_doctors_by_hospital' && isPatientLoggedIn()) {
    header('Content-Type: application/json; charset=utf-8');
    $hospitalId = (int)($_GET['hospital_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT id, name_ar, title_ar FROM doctors WHERE hospital_id = ? ORDER BY name_ar");
    $stmt->execute([$hospitalId]);
    echo json_encode(['success' => true, 'doctors' => $stmt->fetchAll()]);
    exit;
}

// إنشاء إجازة مرضية حقيقية (AJAX)
if ($action === 'create_sick_leave' && isPatientLoggedIn()) {
    header('Content-Type: application/json; charset=utf-8');

    $patientId = (int)$_SESSION['patient_id'];
    $userId    = (int)$_SESSION['patient_user_id'];

    $paStmt = $pdo->prepare("SELECT allowed_days FROM patient_accounts WHERE user_id = ?");
    $paStmt->execute([$userId]);
    $paRow = $paStmt->fetch();
    $allowedDays = $paRow ? (int)$paRow['allowed_days'] : 0;
    $_SESSION['patient_allowed_days'] = $allowedDays;

    $hospitalId  = (int)($_POST['hospital_id'] ?? 0);
    $doctorId    = (int)($_POST['doctor_id'] ?? 0);
    $startDate   = trim($_POST['start_date'] ?? '');
    $endDate     = trim($_POST['end_date'] ?? '');
    $daysCount   = (int)($_POST['days_count'] ?? 0);
    $timeMode    = in_array($_POST['time_mode'] ?? '', ['auto','random','manual']) ? $_POST['time_mode'] : 'auto';
    $manualTime  = trim($_POST['manual_time'] ?? '');
    $manualPeriod = in_array(strtoupper($_POST['manual_period'] ?? ''), ['AM','PM']) ? strtoupper($_POST['manual_period']) : 'AM';

    if (!$hospitalId || !$doctorId || !$startDate || !$endDate || $daysCount <= 0) {
        echo json_encode(['success' => false, 'message' => 'يرجى تعبئة جميع الحقول المطلوبة.']);
        exit;
    }

    $usedDays = getUsedDaysUser($pdo, $patientId, $userId);
    $remainingDays = $allowedDays - $usedDays;

    if ($daysCount > $remainingDays) {
        echo json_encode(['success' => false, 'message' => "عدد الأيام المطلوبة ($daysCount) يتجاوز الحصة المتبقية ($remainingDays يوم)."]);
        exit;
    }

    $issueTime = null;
    $issuePeriod = null;

    if ($timeMode === 'auto') {
        $now = new DateTime('now', new DateTimeZone('Asia/Riyadh'));
        $h = (int)$now->format('H');
        $issuePeriod = $h >= 12 ? 'PM' : 'AM';
        $h12 = $h > 12 ? $h - 12 : ($h === 0 ? 12 : $h);
        $issueTime = sprintf('%02d:%02d', $h12, (int)$now->format('i'));
    } elseif ($timeMode === 'random') {
        $randomHour = rand(8, 17);
        $randomMin  = rand(0, 59);
        $issuePeriod = $randomHour >= 12 ? 'PM' : 'AM';
        $h12 = $randomHour > 12 ? $randomHour - 12 : ($randomHour === 0 ? 12 : $randomHour);
        $issueTime = sprintf('%02d:%02d', $h12, $randomMin);
    } elseif ($timeMode === 'manual' && $manualTime) {
        $issueTime   = $manualTime;
        $issuePeriod = $manualPeriod;
    }

    $hospStmt = $pdo->prepare("SELECT * FROM hospitals WHERE id = ?");
    $hospStmt->execute([$hospitalId]);
    $hosp = $hospStmt->fetch();

    $docStmt = $pdo->prepare("SELECT * FROM doctors WHERE id = ?");
    $docStmt->execute([$doctorId]);
    $doc = $docStmt->fetch();

    $patStmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
    $patStmt->execute([$patientId]);
    $pat = $patStmt->fetch();

    if (!$hosp || !$doc || !$pat) {
        echo json_encode(['success' => false, 'message' => 'بيانات غير صالحة. يرجى المحاولة مجدداً.']);
        exit;
    }

    $prefix = $hosp['service_prefix'] ?? 'GSL';
    $serviceCode = generateServiceCodeUser($pdo, $prefix, $startDate);

    $issueDate = date('Y-m-d');
    $stmt = $pdo->prepare("INSERT INTO sick_leaves 
        (service_code, patient_id, doctor_id, hospital_id, created_by_user_id,
         issue_date, issue_time, issue_period, start_date, end_date, days_count,
         patient_name_en, doctor_name_en, doctor_title_en,
         hospital_name_ar, hospital_name_en, logo_path,
         employer_ar, employer_en)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([
        $serviceCode,
        $patientId,
        $doctorId,
        $hospitalId,
        $userId,
        $issueDate,
        $issueTime,
        $issuePeriod,
        $startDate,
        $endDate,
        $daysCount,
        $pat['name_en'] ?? '',
        $doc['name_en'] ?? '',
        $doc['title_en'] ?? '',
        $hosp['name_ar'] ?? '',
        $hosp['name_en'] ?? '',
        $hosp['logo_url'] ?? $hosp['logo_path'] ?? '',
        $pat['employer_ar'] ?? '',
        $pat['employer_en'] ?? '',
    ]);
    $leaveId = (int)$pdo->lastInsertId();

    echo json_encode([
        'success'        => true,
        'message'        => 'تم إنشاء الإجازة المرضية بنجاح وتوثيقها في السجل.',
        'leave_id'       => $leaveId,
        'service_code'   => $serviceCode,
        'remaining_days' => $remainingDays - $daysCount,
    ]);
    exit;
}

// توليد PDF للإجازة (نفس قالب لوحة التحكم المحمي)
if ($action === 'generate_pdf' && isPatientLoggedIn()) {
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '/';
    $leaveId = (int)($_GET['leave_id'] ?? 0);
    $userId  = (int)$_SESSION['patient_user_id'];
    $patientId = (int)$_SESSION['patient_id'];
    $pdfMode = $_GET['pdf_mode'] ?? 'preview';

    $stmt = $pdo->prepare("
        SELECT sl.*,
               p.name_ar AS p_name_ar, p.name_en AS p_name_en, p.identity_number,
               p.employer_ar AS p_employer_ar, p.employer_en AS p_employer_en,
               p.nationality_ar AS p_nationality_ar, p.nationality_en AS p_nationality_en,
               d.name_ar AS d_name_ar, d.name_en AS d_name_en,
               d.title_ar AS d_title_ar, d.title_en AS d_title_en,
               h.name_ar AS h_name_ar, h.name_en AS h_name_en,
               h.license_number AS h_license,
               h.logo_data AS h_logo_data, h.logo_url AS h_logo_url, h.logo_path AS h_logo_path,
               h.logo_scale AS h_logo_scale, h.logo_offset_x AS h_logo_offset_x, h.logo_offset_y AS h_logo_offset_y
        FROM sick_leaves sl
        LEFT JOIN patients p ON sl.patient_id = p.id
        LEFT JOIN doctors d ON sl.doctor_id = d.id
        LEFT JOIN hospitals h ON sl.hospital_id = h.id
        WHERE sl.id = ? AND sl.patient_id = ? AND sl.created_by_user_id = ? AND sl.deleted_at IS NULL
    ");
    $stmt->execute([$leaveId, $patientId, $userId]);
    $lv = $stmt->fetch();

    if (!$lv) {
        echo '<h2 style="text-align:center;padding:50px;font-family:sans-serif;">الإجازة غير موجودة</h2>';
        exit;
    }

    $sc       = htmlspecialchars($lv['service_code'] ?? '', ENT_QUOTES);
    $days     = (int)($lv['days_count'] ?? 1);
    $daysEn   = $days . ($days === 1 ? ' day' : ' days');
    $daysAr   = (string)$days;
    $daysArWord = 'يوم';

    $startG = $lv['start_date'] ?? '';
    $endG   = $lv['end_date'] ?? '';
    $issueG = $lv['issue_date'] ?? date('Y-m-d');

    $startEn  = fmtDateEnUser($startG);
    $endEn    = fmtDateEnUser($endG);
    $issueEn  = fmtDateEnUser($issueG);
    $startHj  = toHijriStrUser($startG);
    $endHj    = toHijriStrUser($endG);

    $patNameAr = htmlspecialchars($lv['p_name_ar'] ?? '', ENT_QUOTES);
    $patNameEn = strtoupper(htmlspecialchars($lv['p_name_en'] ?? '', ENT_QUOTES));
    $patId     = htmlspecialchars($lv['identity_number'] ?? '', ENT_QUOTES);
    $natAr     = htmlspecialchars($lv['p_nationality_ar'] ?? '', ENT_QUOTES);
    $natEn     = htmlspecialchars($lv['p_nationality_en'] ?? '', ENT_QUOTES);
    $empArRaw  = $lv['p_employer_ar'] ?? $lv['employer_ar'] ?? '';
    $empEnRaw  = $lv['p_employer_en'] ?? $lv['employer_en'] ?? '';
    $empAr     = htmlspecialchars($empArRaw !== '' ? $empArRaw : 'الى من يهمه الامر', ENT_QUOTES);
    $empEn     = htmlspecialchars($empEnRaw !== '' ? $empEnRaw : 'To Whom It May Concern', ENT_QUOTES);
    $docNameAr = htmlspecialchars($lv['d_name_ar'] ?? '', ENT_QUOTES);
    $docNameEn = strtoupper(htmlspecialchars($lv['d_name_en'] ?? '', ENT_QUOTES));
    $docTitleAr = htmlspecialchars($lv['d_title_ar'] ?? '', ENT_QUOTES);
    $docTitleEn = htmlspecialchars($lv['d_title_en'] ?? '', ENT_QUOTES);
    $hospNameAr = htmlspecialchars($lv['h_name_ar'] ?? '', ENT_QUOTES);
    $hospNameEn = htmlspecialchars($lv['h_name_en'] ?? '', ENT_QUOTES);
    $hospLicense = $lv['h_license'] ?? '';

    $hospLogoData = $lv['h_logo_data'] ?? '';
    $hospLogoUrl  = $lv['h_logo_url'] ?? '';
    $hospLogoPath = $lv['h_logo_path'] ?? '';
    $defaultLogo  = 'https://upload.wikimedia.org/wikipedia/ar/thumb/f/fe/Saudi_Ministry_of_Health_Logo.svg/3840px-Saudi_Ministry_of_Health_Logo.svg.png';
    $logoSrc = $defaultLogo;
    if (!empty($hospLogoData) && strpos($hospLogoData, 'data:image/') === 0) {
        $logoSrc = $hospLogoData;
    } elseif ($hospLogoPath && strpos($hospLogoPath, 'http') === 0) {
        $logoSrc = $hospLogoPath;
    } elseif ($hospLogoUrl && strpos($hospLogoUrl, 'http') === 0) {
        $logoSrc = $hospLogoUrl;
    }
    $hLogoScale = floatval($lv['h_logo_scale'] ?? 1);
    $hLogoOffX  = floatval($lv['h_logo_offset_x'] ?? 0);
    $hLogoOffY  = floatval($lv['h_logo_offset_y'] ?? 0);
    $logoTransform = "transform: translate({$hLogoOffX}px, {$hLogoOffY}px) scale({$hLogoScale});";
    $hospLogoHtml = '<div style="width:120px;height:120px;overflow:hidden;position:relative;"><img src="' . htmlspecialchars($logoSrc) . '" alt="Hospital Logo" style="width:100%;height:100%;object-fit:contain;position:absolute;top:0;left:0;' . $logoTransform . '" /></div>';

    $licenseHtml = '';
    if (!empty($hospLicense)) {
        $licenseHtml = '<span style="font-family: \'Noto Sans Arabic\', sans-serif; font-weight: 700;">رقم الترخيص :</span> <span style="font-family: \'Times New Roman\', serif; font-weight: 700;">' . htmlspecialchars($hospLicense) . '</span>';
    }

    $issuePeriod = $lv['issue_period'] ?? 'AM';
    $issueTimeRaw = $lv['issue_time'] ?? '09:00';
    if (preg_match('/^(\d{1,2}):(\d{2})/', $issueTimeRaw, $tm)) {
        $h = (int)$tm[1]; $mn = (int)$tm[2];
        if ($h > 12) $h -= 12;
        elseif ($h === 0) $h = 12;
        $issueTimeDisplay = sprintf('%02d:%02d', $h, $mn);
    } else {
        $issueTimeDisplay = $issueTimeRaw;
    }
    $issueDateObj = DateTime::createFromFormat('Y-m-d', $issueG);
    $dayNameEn   = $issueDateObj ? $issueDateObj->format('l') : '';
    $monthNameEn = $issueDateObj ? $issueDateObj->format('F') : '';
    $dayNum      = $issueDateObj ? $issueDateObj->format('d') : '';
    $yearNum     = $issueDateObj ? $issueDateObj->format('Y') : '';
    $timestampLine = $issueTimeDisplay . ' ' . $issuePeriod;
    $dateLine      = $dayNameEn . ', ' . $dayNum . ' ' . $monthNameEn . ' ' . $yearNum;

    $durationEn = $daysEn . ' ( ' . $startEn . ' to ' . $endEn . ' )';
    $durationAr = '<span style="font-family: \'Times New Roman\', serif; font-size: 14.5px; font-weight: 400;">' . $daysAr . '</span> <span style="font-family: \'Noto Sans Arabic\', sans-serif; font-size: 14.5px; font-weight: 400;">' . $daysArWord . '</span> ( ' . formatHijriDateSpanUser($startHj) . ' <span style="font-family: \'Noto Sans Arabic\', sans-serif; font-size: 13.5px; font-weight: 400;">إلى</span> ' . formatHijriDateSpanUser($endHj) . ' )';

   if ($pdfMode === 'download') {
        $scFile = preg_replace('/[^a-zA-Z0-9_-]/', '_', $sc);
        $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '/';

        $pdfHtml  = '<!DOCTYPE html><html lang="ar"><head><meta charset="utf-8"/>';
        $pdfHtml .= '<title>Sick Leave Report</title>';
        $pdfHtml .= '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700&display=swap" />';
        $pdfHtml .= '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=STIX+Two+Text:ital,wght@0,400;0,600;0,700;1,400&display=swap" />';
        $pdfHtml .= '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+Arabic:wght@400;600;700&display=swap" />';
        
        $pdfHtml .= '<style data-tag="reset-style-sheet">';
        $pdfHtml .= 'html{line-height:1.15}body{margin:0}*{box-sizing:border-box;border-width:0;border-style:solid}p,li,ul,pre,div,h1,h2,h3,h4,h5,h6,figure,blockquote,figcaption{margin:0;padding:0}a{color:inherit;text-decoration:inherit}';
        $pdfHtml .= '</style>';

        $pdfHtml .= '<style data-tag="default-style-sheet">';
        $pdfHtml .= 'html{font-family:Inter,sans-serif;font-size:16px}body{font-weight:400;color:#191818;background:#ffffff;margin:0;padding:0}';
        $pdfHtml .= '</style>';
        
        $pdfHtml .= '<style>';
        $pdfHtml .= '@font-face { font-family: "Times New Roman"; src: url("' . $baseUrl . 'times_regular.otf") format("opentype"); font-weight: 400; font-style: normal; }';
        $pdfHtml .= '@font-face { font-family: "Times New Roman"; src: url("' . $baseUrl . 'times_bold.otf") format("opentype"); font-weight: 700; font-style: normal; }';
        $pdfHtml .= '</style>';

        $pdfHtml .= '<style>';
        $pdfHtml .= '@page { size: 842.25px 1190.25px; margin: 0; }';
        $pdfHtml .= '.group1-container1 { width: 842.25px; height: 1190.25px; position: relative; background-color: transparent; margin: 0; padding: 0; }';
        $pdfHtml .= '.group1-thq-group1-elm { width: 842.25px; height: 1190.25px; position: relative; background-color: white; margin: 0; padding: 0; }';
        $pdfHtml .= '.info-table { position: absolute; top: 242px; left: 36px; width: 770px; border-collapse: separate; border-spacing: 0; border: 1px solid #cccccc; border-radius: 8px; overflow: hidden; background-color: transparent; z-index: 10; }';
        $pdfHtml .= '.info-table td { border-bottom: 1px solid #cccccc; border-right: 1px solid #cccccc; height: 42px; text-align: center; vertical-align: middle; padding: 4px 8px; }';
        $pdfHtml .= '.info-table td:last-child { border-right: none; } .info-table tr:last-child td { border-bottom: none; }';
        $pdfHtml .= '.info-table .en-title { width: 161px; color: rgba(54, 111, 181, 1); font-size: 13.5px; font-weight: 700; text-align: center; font-family: "Times New Roman", serif; }';
        $pdfHtml .= '.info-table .data-cell { width: 240px; color: rgba(44, 62, 119, 1); font-size: 13.5px; font-family: "Times New Roman", serif; font-weight: 400; text-align: center; }';
        $pdfHtml .= '.info-table .date-cell { font-size: 13.9px; } .info-table .data-cell.ar-text { font-family: "Noto Sans Arabic", sans-serif; }';
        $pdfHtml .= '.info-table .ar-title { width: 140px; color: rgba(54, 111, 181, 1); font-size: 13.5px; font-weight: 700; text-align: center; font-family: "Noto Sans Arabic", sans-serif; white-space: nowrap; }';
        $pdfHtml .= '.info-table tr.blue-row td { background-color: #2c3e77; color: #ffffff; border-bottom: 1px solid #cccccc; border-right: 1px solid #cccccc; }';
        $pdfHtml .= '.info-table tr.blue-row td:last-child { border-right: none; }';
        $pdfHtml .= '.info-table .blue-row .data-cell.ar-text { color: rgba(255, 255, 255, 1); font-size: 13.5px; font-family: "Times New Roman", serif; font-weight: 400; }';
        $pdfHtml .= '.info-table .blue-row .data-cell { color: rgba(255, 255, 255, 1); }';
        $pdfHtml .= '.info-table tr.gray-row td { background-color: #f7f7f7; }';
        $pdfHtml .= '.en-spaced { letter-spacing: 0.3px; } :root { --footer-offset: 40px; }';
        $pdfHtml .= '.group1-thq-staticinfo-elm { top: 125px; left: 36.65px; width: 768.35px; height: 811.91px; display: flex; position: absolute; align-items: flex-start; pointer-events: none; }';
        $pdfHtml .= '.top-right-placeholder { position: absolute; top: 36px; left: 543.36px; width: 262.43px; height: 107.22px; display: flex; align-items: center; justify-content: center; font-size: 14px; z-index: 5; }';
        $pdfHtml .= '.top-left-placeholder { position: absolute; top: 36px; left: 36px; width: 149.96px; height: 65.98px; display: flex; align-items: center; justify-content: center; font-size: 14px; z-index: 5; }';
        $pdfHtml .= '.bottom-right-placeholder { position: absolute; top: 1005px; left: 657.17px; width: 149.96px; height: 71.23px; display: flex; align-items: center; justify-content: center; font-size: 12px; z-index: 5; }';
        $pdfHtml .= '.header-placeholder { top: -50px; left: 320px; width: 163px; height: 40px; position: absolute; display: flex; align-items: center; justify-content: center; font-size: 11px; }';
        $pdfHtml .= '.group1-thq-text-elm41 { top: 40px; left: 289px; color: rgba(48, 109, 181, 1); width: 215px; position: absolute; font-size: 22.5px; font-weight: 700; text-align: center; line-height: 30px; }';
        $pdfHtml .= '.group1-thq-text-elm44 { top: -10px; left: 310px; color: rgba(0, 0, 0, 1); position: absolute; font-size: 17.3px; font-weight: 400; text-align: left; font-family: "Times New Roman", serif; }';
        $pdfHtml .= '.group1-thq-hospitallogoandthename-elm { top: 760px; left: 438.94px; width: 403px; height: 202.78px; display: flex; position: absolute; align-items: flex-start; }';
        $pdfHtml .= '.placeholder-logo-hospital { top: -12px; left: 133px; width: 136px; height: 136px; position: absolute; display: flex; align-items: center; justify-content: center; font-size: 12px; }';
        $pdfHtml .= '.group1-thq-text-elm18 { top: 113px; color: rgba(0, 0, 0, 1); width: 403px; height: auto; position: absolute; font-size: 12.8px; text-align: center; line-height: 22px; }';
        $pdfHtml .= '.group1-thq-thedateofissueandalsotimeofissue-elm { top: calc(989.85px + var(--footer-offset)); left: 37.37px; width: 250px; height: 56px; display: flex; position: absolute; align-items: flex-start; }';
        $pdfHtml .= '.group1-thq-text-elm22 { color: rgba(0, 0, 0, 1); font-size: 12.5px; font-weight: 700; text-align: left; line-height: 28px; font-family: "Times New Roman", serif; position: absolute; white-space: nowrap; }';
        $pdfHtml .= '.group1-thq-text-elm36 { top: calc(724.55px + var(--footer-offset)); left: 29.23px; color: rgba(0, 0, 0, 1); position: absolute; font-size: 12px; font-weight: 700; text-align: center; font-family: "Noto Sans Arabic", sans-serif; line-height: 23px; }';
        $pdfHtml .= '.group1-thq-text-elm39 { top: calc(770px + var(--footer-offset)); left: 55px; color: rgba(0, 0, 0, 1); position: absolute; font-size: 12px; font-weight: 700; text-align: left; font-family: "Times New Roman", serif; }';
        $pdfHtml .= '.group1-thq-text-elm40 { top: calc(791px + var(--footer-offset)); left: 108.35px; color: rgba(20, 0, 255, 1); position: absolute; font-size: 11px; font-weight: 700; text-align: left; text-decoration: underline; pointer-events: auto; font-family: "Times New Roman", serif; }';
        $pdfHtml .= '.placeholder-136 { position: absolute; top: 620px; left: 122px; width: 136px; height: 136px; display: flex; align-items: center; justify-content: center; font-size: 12px; pointer-events: auto; }';
        $pdfHtml .= '.vertical-divider { position: absolute; top: 735px; left: 431px; width: 1px; height: 6.8cm; background-color: #dddddd; }';
        $pdfHtml .= '.thin-slash { font-weight: 300; font-family: "Inter", sans-serif; margin: 0 3px; display: inline-block; }';
        $pdfHtml .= '</style></head><body>';

        $reportBodyPdf  = '<div class="group1-container1"><div class="group1-thq-group1-elm">';
        $reportBodyPdf .= '<div class="top-right-placeholder"><img src="' . $baseUrl . 'sehalogoright.png" style="width:100%;height:100%"/></div>';
        $reportBodyPdf .= '<div class="top-left-placeholder"><img src="' . $baseUrl . 'sehalogoleft.png" style="width:100%;height:100%"/></div>';
        $reportBodyPdf .= '<div class="bottom-right-placeholder"><img src="' . $baseUrl . 'bottomright.png" style="width:100%;height:100%"/></div>';
        $reportBodyPdf .= '<div class="group1-thq-staticinfo-elm">';
        $reportBodyPdf .= '<div class="header-placeholder"><img src="' . $baseUrl . 'header.png" style="width:100%;height:100%"/></div>';
        $reportBodyPdf .= '<span class="group1-thq-text-elm41"><span style="font-size:22.5px;font-family:\'Noto Sans Arabic\',sans-serif;font-weight:700;color:#306db5">تقرير إجازة مرضية</span><br/><span style="font-size:18.7px;font-family:\'Times New Roman\',serif;font-weight:700;color:#2c3e77">Sick Leave Report</span></span>';
        $reportBodyPdf .= '<span class="group1-thq-text-elm44">Kingdom of Saudi Arabia</span>';
        $reportBodyPdf .= '<div class="placeholder-136"><img src="' . $baseUrl . 'qr.svg" style="width:130px;height:130px"/></div>';
        $reportBodyPdf .= '<span class="group1-thq-text-elm36" dir="rtl">للتحقق من بيانات التقرير يرجى التأكد من زيارة موقع منصة صحة<br/>الرسمي</span>';
        $reportBodyPdf .= '<span class="group1-thq-text-elm39">To check the report please visit Seha\'s official website</span>';
        $reportBodyPdf .= '<span class="group1-thq-text-elm40"><a href="https://seha-sa-iniquiries-slenquiry.up.railway.app/" target="_blank">www.seha.sa/#/inquiries/slenquiry</a></span>';
        $reportBodyPdf .= '</div>';
        $reportBodyPdf .= '<table class="info-table" cellpadding="0" cellspacing="0"><tbody>';
        $reportBodyPdf .= '<tr><td class="en-title">Leave ID</td><td class="data-cell" colspan="2">' . $sc . '</td><td class="ar-title">رمز الإجازة</td></tr>';
        $reportBodyPdf .= '<tr class="blue-row"><td class="en-title" style="color:white">Leave Duration</td><td class="data-cell">' . $durationEn . '</td><td class="data-cell ar-text" dir="rtl">' . $durationAr . '</td><td class="ar-title" style="color:white">مدة الإجازة</td></tr>';
        $reportBodyPdf .= '<tr><td class="en-title">Admission Date</td><td class="data-cell date-cell">' . $startEn . '</td><td class="data-cell date-cell" dir="ltr">' . $startHj . '</td><td class="ar-title">تاريخ الدخول</td></tr>';
        $reportBodyPdf .= '<tr class="gray-row"><td class="en-title">Discharge Date</td><td class="data-cell date-cell">' . $endEn . '</td><td class="data-cell date-cell" dir="ltr">' . $endHj . '</td><td class="ar-title">تاريخ الخروج</td></tr>';
        $reportBodyPdf .= '<tr><td class="en-title">Issue Date</td><td class="data-cell" colspan="2">' . $issueEn . '</td><td class="ar-title">تاريخ الإصدار</td></tr>';
        $reportBodyPdf .= '<tr class="gray-row"><td class="en-title">Patient Name</td><td class="data-cell en-spaced">' . $patNameEn . '</td><td class="data-cell ar-text">' . $patNameAr . '</td><td class="ar-title">الاسم</td></tr>';
        $reportBodyPdf .= '<tr><td class="en-title">National ID / Iqama</td><td class="data-cell" colspan="2">' . $patId . '</td><td class="ar-title">رقم الهوية<span class="thin-slash">/</span>الإقامة</td></tr>';
        $reportBodyPdf .= '<tr class="gray-row"><td class="en-title">Nationality</td><td class="data-cell en-spaced">' . $natEn . '</td><td class="data-cell ar-text">' . $natAr . '</td><td class="ar-title">الجنسية</td></tr>';
        $reportBodyPdf .= '<tr><td class="en-title">Employer</td><td class="data-cell en-spaced">' . $empEn . '</td><td class="data-cell ar-text">' . $empAr . '</td><td class="ar-title">جهة العمل</td></tr>';
        $reportBodyPdf .= '<tr class="gray-row"><td class="en-title">Physician Name</td><td class="data-cell en-spaced">' . $docNameEn . '</td><td class="data-cell ar-text">' . $docNameAr . '</td><td class="ar-title">اسم الطبيب المعالج</td></tr>';
        $reportBodyPdf .= '<tr><td class="en-title">Position</td><td class="data-cell en-spaced">' . $docTitleEn . '</td><td class="data-cell ar-text">' . $docTitleAr . '</td><td class="ar-title">المسمى الوظيفي</td></tr>';
        $reportBodyPdf .= '</tbody></table>';
        $reportBodyPdf .= '<div class="vertical-divider"></div>';
        $reportBodyPdf .= '<div class="group1-thq-hospitallogoandthename-elm">';
        $reportBodyPdf .= '<div class="placeholder-logo-hospital">' . $hospLogoHtml . '</div>';
        $reportBodyPdf .= '<span class="group1-thq-text-elm18"><span style="font-family:\'Noto Sans Arabic\',sans-serif;font-weight:700">' . $hospNameAr . '</span><br/><span class="en-spaced" style="font-family:\'Times New Roman\',serif;font-weight:700">' . $hospNameEn . '</span><br/>';
        if (!empty($licenseHtml)) $reportBodyPdf .= $licenseHtml;
        $reportBodyPdf .= '</span></div>';
        $reportBodyPdf .= '<div class="group1-thq-thedateofissueandalsotimeofissue-elm"><span class="group1-thq-text-elm22"><span>' . $timestampLine . '</span><br/><span>' . $dateLine . '</span></span></div>';
        $reportBodyPdf .= '</div></div>';

        $pdfBody = str_replace(
            ['src="sehalogoright.png"', 'src="sehalogoleft.png"', 'src="bottomright.png"', 'src="header.png"', 'src="qr.svg"'],
            ['src="' . $baseUrl . 'sehalogoright.png"', 'src="' . $baseUrl . 'sehalogoleft.png"', 'src="' . $baseUrl . 'bottomright.png"', 'src="' . $baseUrl . 'header.png"', 'src="' . $baseUrl . 'qr.svg"'],
            str_replace(
                ['src="sehalogoright.svg"', 'src="sehalogoleft.svg"', 'src="bottomright.svg"', 'src="header.svg"'],
                ['src="sehalogoright.png"', 'src="sehalogoleft.png"', 'src="bottomright.png"', 'src="header.png"'],
                $reportBodyPdf
            )
        );
        
        $pdfHtml .= $pdfBody;
        $pdfHtml .= '</body></html>';

        @mkdir('/tmp/weasyprint', 0777, true);
        $tmpHtml = '/tmp/weasyprint/user_report_' . uniqid() . '.html';
        $tmpPdf  = '/tmp/weasyprint/user_report_' . uniqid() . '.pdf';
        file_put_contents($tmpHtml, $pdfHtml);

        $scriptPath = __DIR__ . '/generate_pdf.py';
        $pythonBin = 'python3';
        foreach (['/usr/bin/python3.13', '/usr/bin/python3.12', '/usr/bin/python3.11', '/usr/local/bin/python3', '/usr/bin/python3'] as $p) {
            if (is_file($p) && !is_link($p)) { $pythonBin = $p; break; }
            if (is_link($p)) { $real = realpath($p); if ($real && is_file($real)) { $pythonBin = $real; break; } }
        }
        
        $cmd = $pythonBin . ' "' . $scriptPath . '" "' . $tmpHtml . '" "' . $tmpPdf . '" 2>&1';
        $output = shell_exec($cmd);

        if (file_exists($tmpPdf) && filesize($tmpPdf) > 0) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="SickLeave_' . $scFile . '.pdf"');
            header('Content-Length: ' . filesize($tmpPdf));
            header('Cache-Control: no-cache, no-store, must-revalidate');
            readfile($tmpPdf);
            @unlink($tmpHtml);
            @unlink($tmpPdf);
            exit;
        } else {
            error_log('WeasyPrint Error: ' . $output);
        }
        @unlink($tmpHtml);
   }

   header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ar">
<head>
<title>تقرير إجازة مرضية - Sick Leave Report</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta charset="utf-8" />
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700&display=swap" />
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=STIX+Two+Text:ital,wght@0,400;0,600;0,700;1,400&display=swap" />
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+Arabic:wght@400;600;700&display=swap" />
<style>
@font-face { font-family: "Times New Roman"; src: url("<?= $baseUrl ?>times_regular.otf") format("opentype"); font-weight: 400; font-style: normal; }
@font-face { font-family: "Times New Roman"; src: url("<?= $baseUrl ?>times_bold.otf") format("opentype"); font-weight: 700; font-style: normal; }

html { line-height: 1.15; font-family: Inter, sans-serif; font-size: 16px; }
body { margin: 0; font-weight: 400; color: #191818; background: #FBFAF9; overflow-x: hidden; }
* { box-sizing: border-box; border-width: 0; border-style: solid; -webkit-font-smoothing: antialiased; }
p, li, ul, pre, div, h1, h2, h3, h4, h5, h6 { margin: 0; padding: 0; }
a { color: inherit; text-decoration: inherit; }

.group1-container1 { width: 100%; display: flex; overflow-x: hidden; min-height: 100vh; align-items: center; flex-direction: column; background-color: #f0f0f0; padding-top: 20px; padding-bottom: 20px; }
.group1-thq-group1-elm { width: 842.25px; height: 1190.25px; display: flex; position: relative; align-items: flex-start; flex-shrink: 0; box-shadow: 0px 4px 15px rgba(0,0,0,0.1); background-color: white; }
.info-table { position: absolute; top: 242px; left: 36px; width: 770px; border-collapse: separate; border-spacing: 0; border: 1px solid #cccccc; border-radius: 8px; overflow: hidden; z-index: 10; }
.info-table td { border-bottom: 1px solid #cccccc; border-right: 1px solid #cccccc; height: 42px; text-align: center; vertical-align: middle; padding: 4px 8px; }
.info-table td:last-child { border-right: none; } .info-table tr:last-child td { border-bottom: none; }
.info-table .en-title { width: 161px; color: rgba(54, 111, 181, 1); font-size: 13.5px; font-weight: 700; font-family: "Times New Roman", serif; }
.info-table .data-cell { width: 240px; color: rgba(44, 62, 119, 1); font-size: 13.5px; font-family: "Times New Roman", serif; font-weight: 400; }
.info-table .date-cell { font-size: 13.9px; } .info-table .data-cell.ar-text { font-family: "Noto Sans Arabic", sans-serif; }
.info-table .ar-title { width: 140px; color: rgba(54, 111, 181, 1); font-size: 13.5px; font-weight: 700; font-family: "Noto Sans Arabic", sans-serif; white-space: nowrap; }
.info-table tr.blue-row td { background-color: #2c3e77; color: #ffffff; border-bottom: 1px solid #cccccc; border-right: 1px solid #cccccc; }
.info-table tr.blue-row td:last-child { border-right: none; }
.info-table tr.gray-row td { background-color: #f7f7f7; }
.en-spaced { letter-spacing: 0.3px; } :root { --footer-offset: 40px; }

.group1-thq-staticinfo-elm { top: 125px; left: 36.65px; width: 768.35px; height: 811.91px; display: flex; position: absolute; align-items: flex-start; pointer-events: none; }
.top-right-placeholder { position: absolute; top: 36px; left: 543.36px; width: 262.43px; height: 107.22px; display: flex; align-items: center; justify-content: center; font-size: 14px; z-index: 5; }
.top-left-placeholder { position: absolute; top: 36px; left: 36px; width: 149.96px; height: 65.98px; display: flex; align-items: center; justify-content: center; font-size: 14px; z-index: 5; }
.bottom-right-placeholder { position: absolute; top: 1005px; left: 657.17px; width: 149.96px; height: 71.23px; display: flex; align-items: center; justify-content: center; font-size: 12px; z-index: 5; }
.header-placeholder { top: -50px; left: 320px; width: 163px; height: 40px; position: absolute; display: flex; align-items: center; justify-content: center; font-size: 11px; }

.group1-thq-text-elm41 { top: 40px; left: 289px; color: rgba(48, 109, 181, 1); width: 215px; position: absolute; font-size: 22.5px; font-weight: 700; text-align: center; line-height: 30px; }
.group1-thq-text-elm44 { top: -10px; left: 310px; color: rgba(0, 0, 0, 1); position: absolute; font-size: 17.3px; font-family: "Times New Roman", serif; }
.group1-thq-hospitallogoandthename-elm { top: 760px; left: 438.94px; width: 403px; height: 202.78px; display: flex; position: absolute; align-items: flex-start; }
.placeholder-logo-hospital { top: -12px; left: 133px; width: 136px; height: 136px; position: absolute; display: flex; align-items: center; justify-content: center; font-size: 12px; }
.group1-thq-text-elm18 { top: 113px; color: rgba(0, 0, 0, 1); width: 403px; position: absolute; font-size: 12.8px; text-align: center; line-height: 22px; }
.group1-thq-thedateofissueandalsotimeofissue-elm { top: calc(989.85px + var(--footer-offset)); left: 37.37px; width: 250px; height: 56px; display: flex; position: absolute; align-items: flex-start; }
.group1-thq-text-elm22 { color: rgba(0, 0, 0, 1); font-size: 12.5px; font-weight: 700; line-height: 28px; font-family: "Times New Roman", serif; position: absolute; white-space: nowrap; }
.group1-thq-text-elm36 { top: calc(724.55px + var(--footer-offset)); left: 29.23px; color: rgba(0, 0, 0, 1); position: absolute; font-size: 12px; font-weight: 700; text-align: center; font-family: "Noto Sans Arabic", sans-serif; line-height: 23px; }
.group1-thq-text-elm39 { top: calc(770px + var(--footer-offset)); left: 55px; color: rgba(0, 0, 0, 1); position: absolute; font-size: 12px; font-weight: 700; font-family: "Times New Roman", serif; }
.group1-thq-text-elm40 { top: calc(791px + var(--footer-offset)); left: 108.35px; color: rgba(20, 0, 255, 1); position: absolute; font-size: 11px; font-weight: 700; text-decoration: underline; pointer-events: auto; font-family: "Times New Roman", serif; }
.placeholder-136 { position: absolute; top: 620px; left: 122px; width: 136px; height: 136px; display: flex; align-items: center; justify-content: center; font-size: 12px; pointer-events: auto; }
.vertical-divider { position: absolute; top: 735px; left: 431px; width: 1px; height: 6.8cm; background-color: #dddddd; }
.thin-slash { font-weight: 300; font-family: "Inter", sans-serif; margin: 0 3px; display: inline-block; }
.controls { position: fixed; bottom: 30px; right: 30px; display: flex; gap: 15px; z-index: 1000; }
.download-btn { background-color: #0d9488; color: white; padding: 14px 28px; border-radius: 12px; border: none; font-size: 16px; font-weight: 600; cursor: pointer; box-shadow: 0px 6px 20px rgba(13,148,136,0.3); font-family: "Inter", sans-serif; transition: all 0.3s; }
.download-btn:hover { background-color: #0f766e; transform: translateY(-3px); }
@media screen and (max-width: 880px) {
  .group1-container1 { padding-top: 10px; padding-bottom: 10px; }
  .group1-thq-group1-elm { transform-origin: top center; transform: scale(calc(100vw / 860)); margin-bottom: calc(1190.25px * (100vw / 860) - 1190.25px); }
  .controls { bottom: 15px; right: 15px; left: 15px; justify-content: center; }
  .download-btn { width: 100%; text-align: center; font-size: 16px; padding: 14px; }
}
@media print {
  @page { size: 842.25px 1190.25px; margin: 0; }
  body { background: white !important; }
  .controls { display: none !important; }
  .group1-container1 { padding: 0 !important; background-color: transparent !important; }
  .group1-thq-group1-elm { box-shadow: none !important; margin: 0 !important; transform: scale(1) !important; }
}
</style>
<script>
function downloadPDF() {
  var btn = document.getElementById('btnDownloadPDF');
  btn.textContent = 'جاري التحميل...';
  btn.disabled = true;
  var url = window.location.href;
  if (url.indexOf('pdf_mode=') > -1) { url = url.replace(/pdf_mode=[^&]*/, 'pdf_mode=download'); }
  else { url += (url.indexOf('?') > -1 ? '&' : '?') + 'pdf_mode=download'; }
  var a = document.createElement('a'); a.href = url; a.download = '';
  document.body.appendChild(a); a.click(); document.body.removeChild(a);
  setTimeout(function() { btn.textContent = 'تحميل ملف PDF'; btn.disabled = false; }, 3000);
}
</script>
</head>
<body>
<div class="controls">
  <button id="btnDownloadPDF" class="download-btn" onclick="downloadPDF()">تحميل ملف PDF</button>
  <button class="download-btn" style="background-color:#1e293b;box-shadow:0px 6px 20px rgba(0,0,0,0.2);" onclick="window.print()">طباعة مباشرة</button>
  <button class="download-btn" style="background-color:#64748b;box-shadow:none;" onclick="history.back()">← رجوع</button>
</div>
<div class="group1-container1">
  <div class="group1-thq-group1-elm" id="report-content">
    <div class="top-right-placeholder"><img src="<?= $baseUrl ?>sehalogoright.png" alt="" style="width:100%;height:100%;" onerror="this.style.display='none'" /></div>
    <div class="top-left-placeholder"><img src="<?= $baseUrl ?>sehalogoleft.png" alt="" style="width:100%;height:100%;" onerror="this.style.display='none'" /></div>
    <div class="bottom-right-placeholder"><img src="<?= $baseUrl ?>bottomright.png" alt="" style="width:100%;height:100%;" onerror="this.style.display='none'" /></div>
    <div class="group1-thq-staticinfo-elm">
      <div class="header-placeholder"><img src="<?= $baseUrl ?>header.png" alt="" style="width:100%;height:100%;" onerror="this.style.display='none'" /></div>
      <span class="group1-thq-text-elm41">
        <span style="font-size:22.5px;font-family:'Noto Sans Arabic',sans-serif;font-weight:700;color:#0d9488;">تقرير إجازة مرضية</span><br/>
        <span style="font-size:18.7px;font-family:'Times New Roman',serif;font-weight:700;color:#1e293b;">Sick Leave Report</span>
      </span>
      <span class="group1-thq-text-elm44">Kingdom of Saudi Arabia</span>
      <div class="placeholder-136"><img src="<?= $baseUrl ?>qr.svg" alt="QR" style="width:130px;height:130px;" onerror="this.style.display='none'" /></div>
      <span class="group1-thq-text-elm36" dir="rtl">للتحقق من بيانات التقرير يرجى التأكد من زيارة موقع منصة صحة<br/>الرسمي</span>
      <span class="group1-thq-text-elm39">To check the report please visit Seha's official website</span>
      <span class="group1-thq-text-elm40"><a href="https://seha-sa-iniquiries-slenquiry.up.railway.app/" target="_blank">www.seha.sa/#/inquiries/slenquiry</a></span>
    </div>
    <table class="info-table" cellpadding="0" cellspacing="0"><tbody>
      <tr><td class="en-title">Leave ID</td><td class="data-cell" colspan="2"><?= $sc ?></td><td class="ar-title">رمز الإجازة</td></tr>
      <tr class="blue-row"><td class="en-title" style="color:white;">Leave Duration</td><td class="data-cell"><?= $durationEn ?></td><td class="data-cell ar-text" dir="rtl"><?= $durationAr ?></td><td class="ar-title" style="color:white;">مدة الإجازة</td></tr>
      <tr><td class="en-title">Admission Date</td><td class="data-cell date-cell"><?= $startEn ?></td><td class="data-cell date-cell" dir="ltr"><?= $startHj ?></td><td class="ar-title">تاريخ الدخول</td></tr>
      <tr class="gray-row"><td class="en-title">Discharge Date</td><td class="data-cell date-cell"><?= $endEn ?></td><td class="data-cell date-cell" dir="ltr"><?= $endHj ?></td><td class="ar-title">تاريخ الخروج</td></tr>
      <tr><td class="en-title">Issue Date</td><td class="data-cell" colspan="2"><?= $issueEn ?></td><td class="ar-title">تاريخ الإصدار</td></tr>
      <tr class="gray-row"><td class="en-title">Patient Name</td><td class="data-cell en-spaced"><?= $patNameEn ?></td><td class="data-cell ar-text"><?= $patNameAr ?></td><td class="ar-title">الاسم</td></tr>
      <tr><td class="en-title">National ID / Iqama</td><td class="data-cell" colspan="2"><?= $patId ?></td><td class="ar-title">رقم الهوية<span class="thin-slash">/</span>الإقامة</td></tr>
      <tr class="gray-row"><td class="en-title">Nationality</td><td class="data-cell en-spaced"><?= $natEn ?></td><td class="data-cell ar-text"><?= $natAr ?></td><td class="ar-title">الجنسية</td></tr>
      <tr><td class="en-title">Employer</td><td class="data-cell en-spaced"><?= $empEn ?></td><td class="data-cell ar-text"><?= $empAr ?></td><td class="ar-title">جهة العمل</td></tr>
      <tr class="gray-row"><td class="en-title">Physician Name</td><td class="data-cell en-spaced"><?= $docNameEn ?></td><td class="data-cell ar-text"><?= $docNameAr ?></td><td class="ar-title">اسم الطبيب المعالج</td></tr>
      <tr><td class="en-title">Position</td><td class="data-cell en-spaced"><?= $docTitleEn ?></td><td class="data-cell ar-text"><?= $docTitleAr ?></td><td class="ar-title">المسمى الوظيفي</td></tr>
    </tbody></table>
    <div class="vertical-divider"></div>
    <div class="group1-thq-hospitallogoandthename-elm">
      <div class="placeholder-logo-hospital"><?= $hospLogoHtml ?></div>
      <span class="group1-thq-text-elm18">
        <span style="font-family:'Noto Sans Arabic',sans-serif;font-weight:700;"><?= $hospNameAr ?></span><br/>
        <span class="en-spaced" style="font-family:'Times New Roman',serif;font-weight:700;"><?= $hospNameEn ?></span><br/>
        <?php if (!empty($licenseHtml)) echo $licenseHtml; ?>
      </span>
    </div>
    <div class="group1-thq-thedateofissueandalsotimeofissue-elm">
      <span class="group1-thq-text-elm22">
        <span><?= $timestampLine ?></span><br/>
        <span><?= $dateLine ?></span>
      </span>
    </div>
  </div>
</div>
</body>
</html>
<?php
    exit;
}

// ======================== تحميل البيانات للصفحة الرئيسية ========================
$patientData   = null;
$myLeaves      = [];
$hospitals     = [];
$usedDays      = 0;
$allowedDays   = 0;
$remainingDays = 0;

if (isPatientLoggedIn()) {
    $patientId = (int)$_SESSION['patient_id'];
    $userId    = (int)$_SESSION['patient_user_id'];

    $stmt2 = $pdo->prepare("SELECT allowed_days FROM patient_accounts WHERE user_id = ?");
    $stmt2->execute([$userId]);
    $paRow = $stmt2->fetch();
    if ($paRow) {
        $_SESSION['patient_allowed_days'] = (int)$paRow['allowed_days'];
        $allowedDays = (int)$paRow['allowed_days'];
    }

    $stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
    $stmt->execute([$patientId]);
    $patientData = $stmt->fetch();

    $stmt = $pdo->prepare("
        SELECT sl.*, h.name_ar AS h_name_ar, d.name_ar AS d_name_ar, d.title_ar AS d_title_ar
        FROM sick_leaves sl
        LEFT JOIN hospitals h ON sl.hospital_id = h.id
        LEFT JOIN doctors d ON sl.doctor_id = d.id
        WHERE sl.patient_id = ? AND sl.created_by_user_id = ? AND sl.deleted_at IS NULL
        ORDER BY sl.created_at DESC
    ");
    $stmt->execute([$patientId, $userId]);
    $myLeaves = $stmt->fetchAll();

    $hospitals = $pdo->query("SELECT id, name_ar, name_en FROM hospitals ORDER BY name_ar")->fetchAll();

    $usedDays = getUsedDaysUser($pdo, $patientId, $userId);
    $remainingDays = max(0, $allowedDays - $usedDays);
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl" data-bs-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>بوابة المرضى - Patient Portal</title>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap" rel="stylesheet">

<script>
// تفعيل المظهر المخزن مسبقاً فوراً لتجنب الوميض
(function() {
    const savedTheme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-bs-theme', savedTheme);
})();
</script>

<style>
/* ================= المتغيرات المخصصة والتطوير الجذري (Luxury SaaS) ================= */
:root {
    --premium-emerald: #0d9488;
    --premium-emerald-dark: #0f766e;
    --premium-emerald-light: #14b8a6;
    --bg-gradient: linear-gradient(140deg, #f8fafc 0%, #f1f5f9 100%);
    --premium-card-bg: rgba(255, 255, 255, 0.85);
    --premium-card-border: rgba(255, 255, 255, 0.6);
    --premium-shadow: 0 15px 40px rgba(13, 148, 136, 0.08);
    --premium-shadow-sm: 0 8px 20px rgba(0, 0, 0, 0.04);
    --input-bg-custom: #f8fafc;
    --text-color-custom: #0f172a;
}

[data-bs-theme="dark"] {
    --bg-gradient: linear-gradient(140deg, #090d16 0%, #0f172a 100%);
    --premium-card-bg: rgba(15, 23, 42, 0.85);
    --premium-card-border: rgba(255, 255, 255, 0.08);
    --premium-shadow: 0 15px 40px rgba(0, 0, 0, 0.6);
    --premium-shadow-sm: 0 8px 20px rgba(0, 0, 0, 0.4);
    --input-bg-custom: #1e293b;
    --text-color-custom: #f1f5f9;
}

body {
    font-family: 'Cairo', sans-serif;
    background: var(--bg-gradient);
    color: var(--text-color-custom);
    min-height: 100vh;
    transition: background 0.4s ease, color 0.4s ease;
    overflow-x: hidden;
}

/* ═══ صفحة تسجيل الدخول الفخمة ═══ */
.login-page-luxury {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(-45deg, #0f172a, #0d9488, #0f766e, #0284c7);
    background-size: 400% 400%;
    animation: gradientShift 15s ease infinite;
    padding: 20px;
}
.login-card-premium {
    background: var(--premium-card-bg);
    backdrop-filter: blur(25px);
    -webkit-backdrop-filter: blur(25px);
    border: 1px solid var(--premium-card-border);
    border-radius: 28px;
    box-shadow: 0 25px 70px rgba(0,0,0,0.5);
    width: 100%;
    max-width: 450px;
    padding: 50px 40px;
    animation: slideUp 0.6s cubic-bezier(0.34,1.56,0.64,1);
}
.login-icon-box {
    font-size: 54px;
    color: var(--premium-emerald);
    animation: float 4s ease-in-out infinite;
}

/* ═══ الشريط العلوي الفخم (Floating Glass Navbar) ═══ */
.navbar-premium {
    background: var(--premium-card-bg);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid var(--premium-card-border);
    border-radius: 20px;
    box-shadow: var(--premium-shadow);
    padding: 14px 24px;
    margin-top: 20px;
    margin-bottom: 30px;
    z-index: 1030;
    transition: all 0.3s ease;
}
.navbar-brand-custom {
    font-weight: 900;
    font-size: 20px;
    color: var(--premium-emerald) !important;
    display: flex;
    align-items: center;
    gap: 12px;
}
.brand-icon-lux {
    background: linear-gradient(135deg, var(--premium-emerald), var(--premium-emerald-light));
    color: white;
    width: 42px;
    height: 42px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 6px 15px rgba(13, 148, 136, 0.3);
}
.user-badge-premium {
    background: var(--input-bg-custom);
    border: 1px solid rgba(13, 148, 136, 0.3);
    padding: 8px 18px;
    border-radius: 50px;
    font-weight: 800;
    font-size: 14px;
}

/* ═══ البطاقات الزجاجية الفخمة ═══ */
.card-luxury {
    background: var(--premium-card-bg);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid var(--premium-card-border);
    border-radius: 24px;
    box-shadow: var(--premium-shadow);
    overflow: hidden;
    margin-bottom: 35px;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}
.card-luxury:hover {
    transform: translateY(-3px);
    box-shadow: 0 20px 50px rgba(13, 148, 136, 0.12);
}
.card-header-luxury {
    background: transparent;
    border-bottom: 1px solid rgba(13, 148, 136, 0.15);
    padding: 24px 30px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
}
.card-title-luxury {
    font-size: 20px;
    font-weight: 900;
    color: var(--premium-emerald);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 12px;
}

/* ═══ زر إظهار/إخفاء البيانات العريض والأنيق ═══ */
.btn-toggle-banner {
    background: var(--premium-card-bg);
    backdrop-filter: blur(10px);
    border: 2px solid var(--premium-emerald);
    color: var(--premium-emerald);
    padding: 16px 35px;
    border-radius: 50px;
    font-size: 16px;
    font-weight: 900;
    display: inline-flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
    box-shadow: var(--premium-shadow-sm);
    transition: all 0.3s ease;
}
.btn-toggle-banner:hover, .btn-toggle-banner.active {
    background: var(--premium-emerald);
    color: white;
    box-shadow: 0 10px 25px rgba(13, 148, 136, 0.3);
    transform: translateY(-2px);
}

/* ═══ إشعار البيانات الثابتة والتواصل واتس ═══ */
.fixed-notice-luxury {
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.08), rgba(239, 68, 68, 0.03));
    border: 1px solid rgba(245, 158, 11, 0.3);
    border-radius: 20px;
    padding: 22px 28px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 20px;
    margin-bottom: 30px;
}
.btn-whatsapp-premium {
    background: linear-gradient(135deg, #25d366, #128c7e);
    color: white !important;
    padding: 12px 28px;
    border-radius: 14px;
    font-weight: 800;
    font-size: 15px;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
    box-shadow: 0 8px 20px rgba(37, 211, 102, 0.3);
    transition: all 0.3s ease;
}
.btn-whatsapp-premium:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 25px rgba(37, 211, 102, 0.4);
}

/* ═══ الحقول والأزرار ═══ */
.form-control-lux, .form-select-lux {
    border-radius: 16px;
    border: 2px solid rgba(13, 148, 136, 0.2);
    padding: 15px 20px;
    background: var(--input-bg-custom);
    color: var(--text-color-custom);
    font-weight: 700;
    font-size: 15px;
    transition: all 0.3s ease;
    box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);
}
.form-control-lux:focus, .form-select-lux:focus {
    border-color: var(--premium-emerald);
    background: transparent;
    box-shadow: 0 0 0 4px rgba(13, 148, 136, 0.15);
}
.form-label-lux {
    font-weight: 800;
    font-size: 14px;
    margin-bottom: 10px;
}
.btn-emerald-lux {
    background: linear-gradient(135deg, var(--premium-emerald), var(--premium-emerald-dark));
    color: white !important;
    border: none;
    border-radius: 16px;
    padding: 16px 36px;
    font-weight: 900;
    font-size: 16px;
    box-shadow: 0 8px 25px rgba(13, 148, 136, 0.3);
    transition: all 0.3s ease;
}
.btn-emerald-lux:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 30px rgba(13, 148, 136, 0.4);
    filter: brightness(1.05);
}

/* ═══ الإحصائيات والبيانات الشخصية ═══ */
.stat-box-lux {
    background: var(--premium-card-bg);
    border: 1px solid var(--premium-card-border);
    border-radius: 20px;
    padding: 24px;
    display: flex;
    align-items: center;
    gap: 20px;
    box-shadow: var(--premium-shadow-sm);
    height: 100%;
}
.stat-icon-lux {
    width: 65px;
    height: 65px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    flex-shrink: 0;
}
.info-field-box {
    background: var(--input-bg-custom);
    border: 1px solid rgba(13, 148, 136, 0.15);
    border-radius: 16px;
    padding: 18px 22px;
    position: relative;
    height: 100%;
}
.badge-lock-fixed {
    position: absolute;
    top: 14px;
    left: 14px;
    font-size: 11px;
    font-weight: 900;
    background: rgba(245, 158, 11, 0.15);
    color: #d97706;
    padding: 4px 10px;
    border-radius: 8px;
    border: 1px solid rgba(245, 158, 11, 0.3);
}

/* ═══ الحركات الانتقالية ═══ */
@keyframes gradientShift { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }
@keyframes float { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-10px)} }
@keyframes slideUp { from{opacity:0;transform:translateY(40px)} to{opacity:1;transform:translateY(0)} }

/* ═══ التنبيهات المخصصة (Custom Overlay Toast) ═══ */
.toast-overlay-container {
    position: fixed;
    top: 100px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 99999;
    display: flex;
    flex-direction: column;
    gap: 12px;
    pointer-events: none;
    width: 90%;
    max-width: 480px;
}
.toast-lux {
    background: var(--premium-card-bg);
    backdrop-filter: blur(15px);
    border: 1px solid var(--premium-card-border);
    border-radius: 18px;
    padding: 20px 25px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.4);
    font-weight: 800;
    font-size: 15px;
    display: flex;
    align-items: center;
    gap: 15px;
    border-right: 6px solid var(--premium-emerald);
    animation: slideDown 0.4s cubic-bezier(0.34,1.56,0.64,1);
}
@keyframes slideDown { from{opacity:0;transform:translateY(-20px)} to{opacity:1;transform:translateY(0)} }
</style>
</head>
<body>

<?php if (!isPatientLoggedIn()): ?>
<div class="login-page-luxury">
    <div class="login-card-premium">
        <div class="text-center mb-5">
            <div class="login-icon-box mb-3">
                <i class="fa-solid fa-house-chimney-medical"></i>
            </div>
            <h2 class="fw-black text-gradient" style="font-weight: 900; color: var(--premium-emerald);">بوابة المرضى</h2>
            <p class="text-muted fw-bold mt-1">سجّل دخولك للوصول إلى ملفك الطبي وإصدار الإجازات</p>
        </div>

        <?php if (!empty($_GET['disabled'])): ?>
        <div class="alert alert-danger fw-bold rounded-4 p-3 mb-4"><i class="fa-solid fa-triangle-exclamation me-2"></i> تم تعطيل حسابك. يرجى التواصل مع الإدارة.</div>
        <?php endif; ?>

        <?php if (!empty($loginError)): ?>
        <div class="alert alert-danger fw-bold rounded-4 p-3 mb-4"><i class="fa-solid fa-circle-exclamation me-2"></i> <?= htmlspecialchars($loginError) ?></div>
        <?php endif; ?>

        <form method="POST" action="user.php">
            <input type="hidden" name="action" value="patient_login">
            <?= patient_csrf_input() ?>
            
            <div class="form-floating mb-4">
                <input type="text" id="username" name="username" class="form-control form-control-lux" placeholder="اسم المستخدم" autocomplete="username" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                <label for="username" class="fw-bold"><i class="fa-solid fa-user me-2 text-muted"></i> اسم المستخدم</label>
            </div>
            
            <div class="form-floating mb-4">
                <input type="password" id="password" name="password" class="form-control form-control-lux" placeholder="كلمة المرور" autocomplete="current-password" required>
                <label for="password" class="fw-bold"><i class="fa-solid fa-lock me-2 text-muted"></i> كلمة المرور</label>
            </div>
            
            <button type="submit" class="btn btn-emerald-lux w-100 mt-2">
                <i class="fa-solid fa-arrow-right-to-bracket me-2"></i> تسجيل الدخول
            </button>
        </form>
        
        <div class="text-center mt-5">
            <span class="text-muted fw-bold fs-7"><i class="fa-solid fa-headset me-1"></i> للحصول على حساب، يرجى التواصل مع الإدارة</span>
        </div>
    </div>
</div>

<?php else: ?>

<div class="container-fluid px-3 px-xl-5">
    <nav class="navbar navbar-expand-md navbar-premium d-flex align-items-center justify-content-between">
        <div class="navbar-brand-custom">
            <div class="brand-icon-lux"><i class="fa-solid fa-hospital-user fs-5"></i></div>
            <span>بوابة المرضى</span>
        </div>
        
        <div class="d-flex align-items-center gap-2 gap-sm-3">
            <button class="btn btn-outline-secondary border-0 rounded-circle w-40 h-40 d-flex align-items-center justify-content-center" onclick="toggleTheme()" title="تبديل المظهر">
                <i id="themeIcon" class="fa-solid fa-sun fs-5"></i>
            </button>

            <div class="position-relative">
                <button id="notifBell" class="btn btn-outline-secondary border-0 rounded-circle w-40 h-40 d-flex align-items-center justify-content-center position-relative" onclick="toggleNotifPanel()" title="الإشعارات">
                    <i class="fa-solid fa-bell fs-5"></i>
                    <span id="notifBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="display: none; font-family: sans-serif;"></span>
                </button>
                
                <div id="notifPanel" class="position-absolute start-0 mt-2 card-luxury p-0" style="display: none; width: 340px; z-index: 1050;">
                    <div class="p-3 bg-gradient d-flex align-items-center justify-content-between border-bottom">
                        <span class="fw-black"><i class="fa-solid fa-bell me-2 text-warning"></i> الإشعارات</span>
                        <button class="btn btn-sm btn-outline-secondary rounded-pill fw-bold fs-8 py-1 px-2" onclick="markAllRead()">تحديد كمقروء</button>
                    </div>
                    <div id="notifList" style="max-height: 320px; overflow-y: auto;">
                        <div class="text-center p-4 text-muted fw-bold fs-7">جاري التحميل...</div>
                    </div>
                </div>
            </div>

            <div class="user-badge-premium d-none d-sm-flex align-items-center gap-2">
                <i class="fa-solid fa-circle-user text-muted fs-5"></i>
                <span><?= htmlspecialchars($_SESSION['patient_display_name']) ?></span>
            </div>

            <form method="POST" action="user.php" class="m-0">
                <input type="hidden" name="action" value="logout">
                <button type="submit" class="btn btn-outline-danger rounded-pill fw-black px-3 py-2 fs-7 border-0 bg-danger-subtle">
                    <i class="fa-solid fa-power-off"></i>
                </button>
            </form>
        </div>
    </nav>
</div>

<div class="container-fluid px-3 px-xl-5 pb-5">

    <?php if ($remainingDays > 0): ?>
    <div class="card-luxury">
        <div class="card-header-luxury">
            <h3 class="card-title-luxury"><i class="fa-solid fa-file-signature"></i> إصدار إجازة مرضية جديدة (فورية)</h3>
            <span class="badge rounded-pill bg-success-subtle text-success fw-black px-4 py-2 fs-6 border border-success-subtle">
                <i class="fa-solid fa-check-double me-1"></i> الرصيد المتاح: <?= $remainingDays ?> يوم
            </span>
        </div>
        <div class="p-4 p-md-5">
            <form id="leaveForm">
                <div class="row g-4">
                    
                    <div class="col-12 col-lg-6">
                        <label class="form-label-lux"><i class="fa-regular fa-hospital text-muted me-1"></i> المستشفى / المنشأة الطبية <span class="text-danger">*</span></label>
                        <select class="form-select form-select-lux" id="hospitalSelect" name="hospital_id" required onchange="loadDoctors(this.value)">
                            <option value="">-- يرجى اختيار المنشأة --</option>
                            <?php foreach ($hospitals as $h): ?>
                            <option value="<?= $h['id'] ?>"><?= htmlspecialchars($h['name_ar']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12 col-lg-6">
                        <label class="form-label-lux"><i class="fa-solid fa-user-doctor text-muted me-1"></i> الطبيب المعالج <span class="text-danger">*</span></label>
                        <select class="form-select form-select-lux" id="doctorSelect" name="doctor_id" required>
                            <option value="">-- اختر المستشفى أولاً --</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-6 col-xl-3">
                        <label class="form-label-lux"><i class="fa-regular fa-calendar-plus text-muted me-1"></i> تاريخ بداية الإجازة <span class="text-danger">*</span></label>
                        <input type="date" class="form-control form-control-lux" id="startDate" name="start_date" required min="<?= date('Y-m-d') ?>" onchange="calcDays()">
                    </div>

                    <div class="col-12 col-md-6 col-xl-3">
                        <label class="form-label-lux"><i class="fa-regular fa-calendar-check text-muted me-1"></i> تاريخ نهاية الإجازة <span class="text-danger">*</span></label>
                        <input type="date" class="form-control form-control-lux" id="endDate" name="end_date" required min="<?= date('Y-m-d') ?>" onchange="calcDays()">
                    </div>

                    <div class="col-12 col-md-6 col-xl-3">
                        <label class="form-label-lux"><i class="fa-solid fa-calculator text-muted me-1"></i> المدة المحسوبة</label>
                        <input type="number" class="form-control form-control-lux text-primary fw-black" id="daysDisplay" readonly placeholder="تُحسب تلقائياً" style="cursor: not-allowed;">
                        <input type="hidden" id="daysCount" name="days_count">
                    </div>

                    <div class="col-12 col-md-6 col-xl-3">
                        <label class="form-label-lux"><i class="fa-regular fa-clock text-muted me-1"></i> توقيت التوثيق والإصدار</label>
                        <div class="btn-group w-100" role="group">
                            <button type="button" class="btn btn-outline-secondary time-tab active fw-black rounded-end-4" onclick="setTimeMode('auto',this)">تلقائي</button>
                            <button type="button" class="btn btn-outline-secondary time-tab fw-black" onclick="setTimeMode('random',this)">عشوائي</button>
                            <button type="button" class="btn btn-outline-secondary time-tab fw-black rounded-start-4" onclick="setTimeMode('manual',this)">يدوي</button>
                        </div>
                        <input type="hidden" id="timeModeInput" name="time_mode" value="auto">
                    </div>

                    <div class="col-12" id="daysWarning" style="display:none;"></div>

                    <div class="col-12" id="manualTimeFields" style="display:none;">
                        <div class="card p-3 rounded-4 bg-light-subtle border-0">
                            <div class="row g-3 align-items-center">
                                <div class="col-auto"><span class="fw-bold fs-7"><i class="fa-solid fa-stopwatch me-1"></i> حدد الوقت بدقة:</span></div>
                                <div class="col-12 col-sm"><input type="time" class="form-control form-control-lux" id="manualTimeInput" name="manual_time"></div>
                                <div class="col-12 col-sm-auto">
                                    <select class="form-select form-select-lux w-100" name="manual_period">
                                        <option value="AM">صباحاً (AM)</option>
                                        <option value="PM">مساءً (PM)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 mt-2">
                        <div id="autoTimeInfo" class="text-muted fw-bold fs-7"><i class="fa-solid fa-circle-info text-primary me-1"></i> سيُعتمد التوقيت الفعلي للحظة الضغط على الزر برمجياً.</div>
                    </div>

                </div>

                <div class="d-flex justify-content-end mt-5">
                    <button type="button" class="btn-emerald-lux" onclick="createLeave()" id="submitBtn">
                        <i class="fa-solid fa-cloud-arrow-up me-2"></i> اعتماد وإصدار الإجازة الفورية
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php else: ?>
    <div class="card-luxury p-5 text-center">
        <i class="fa-solid fa-ban text-danger fs-1 mb-3"></i>
        <h3 class="fw-black">تعذر إصدار إجازات إضافية</h3>
        <p class="text-muted fw-bold max-w-600 mx-auto mt-2 mb-4">
            <?php if ($allowedDays === 0): ?>
            لقد استخدمت رصيدك من الأيام كاملاً. إذا كنت تريد الإضافة تواصل معنا في الواتس.
            <?php else: ?>
            لقد استنفدت كامل رصيدك المسموح به (<?= $allowedDays ?> يوم). لطلب تمديد أو استثناء يرجى التواصل معنا.
            <?php endif; ?>
        </p>
        <a href="https://wa.me/966573436223" target="_blank" class="btn-whatsapp-premium mx-auto">
            <i class="fa-brands fa-whatsapp fs-4"></i> تواصل مع الدعم الفني (واتساب)
        </a>
    </div>
    <?php endif; ?>

    <div class="text-center my-5">
        <button type="button" id="toggleStatsBtn" class="btn-toggle-banner" onclick="toggleStatsInfo()">
            <i class="fa-solid fa-chart-pie fs-5"></i>
            <span>إظهار الإحصائيات والبيانات الشخصية للمريض</span>
        </button>
    </div>

    <div id="statsInfoContainer" style="display: none;">
        
        <div class="row g-4 mb-5">
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="stat-box-lux">
                    <div class="stat-icon-lux bg-primary-subtle text-primary"><i class="fa-solid fa-clipboard-check"></i></div>
                    <div>
                        <div class="fs-2 fw-black line-height-1"><?= $allowedDays ?></div>
                        <div class="text-muted fw-bold fs-7 mt-1">إجمالي الأيام المسموحة</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="stat-box-lux">
                    <div class="stat-icon-lux bg-success-subtle text-success"><i class="fa-solid fa-shield-check"></i></div>
                    <div>
                        <div class="fs-2 fw-black line-height-1"><?= $remainingDays ?></div>
                        <div class="text-muted fw-bold fs-7 mt-1">الأيام المتبقية الحالية</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="stat-box-lux">
                    <div class="stat-icon-lux bg-warning-subtle text-warning"><i class="fa-solid fa-calendar-days"></i></div>
                    <div>
                        <div class="fs-2 fw-black line-height-1"><?= $usedDays ?></div>
                        <div class="text-muted fw-bold fs-7 mt-1">الأيام المستخدمة</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="stat-box-lux">
                    <div class="stat-icon-lux bg-danger-subtle text-danger"><i class="fa-solid fa-file-contract"></i></div>
                    <div>
                        <div class="fs-2 fw-black line-height-1"><?= count($myLeaves) ?></div>
                        <div class="text-muted fw-bold fs-7 mt-1">إجمالي الإجازات المُصدرة</div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($patientData): ?>
        <div class="card-luxury">
            <div class="card-header-luxury">
                <h3 class="card-title-luxury"><i class="fa-solid fa-id-card-clip"></i> بياناتي الشخصية والوظيفية</h3>
            </div>
            <div class="p-4 p-md-5">
                
                <div class="fixed-notice-luxury">
                    <div class="d-flex align-items-center gap-3">
                        <i class="fa-solid fa-lock text-warning fs-1"></i>
                        <div>
                            <span class="fw-black d-block fs-5 text-warning-emphasis mb-1">البيانات ثابتة ومحمية بالنظام</span>
                            <span class="text-muted fw-bold fs-7">هذه البيانات مسجلة رسمياً ولا يمكن تغييرها ذاتياً. إذا كان هناك أي خطأ وتريد التعديل، تواصل معنا فوراً.</span>
                        </div>
                    </div>
                    <a href="https://wa.me/966573436223?text=أهلاً،%20أريد%20تعديل%20بياناتي%20في%20بوابة%20المرضى" target="_blank" class="btn-whatsapp-premium">
                        <i class="fa-brands fa-whatsapp fs-4"></i> التواصل واتس للتعديل
                    </a>
                </div>

                <div class="row g-4">
                    <div class="col-12 col-md-6 col-xl-4">
                        <div class="info-field-box">
                            <span class="badge-lock-fixed"><i class="fa-solid fa-thumbtack me-1"></i> ثابت</span>
                            <span class="text-muted fw-black fs-8 d-block text-uppercase mb-1">الاسم بالعربية</span>
                            <div class="fw-black fs-5"><?= htmlspecialchars($patientData['name_ar'] ?? $patientData['name'] ?? '') ?></div>
                            <?php if (!empty($patientData['name_en'])): ?>
                            <div class="text-muted fw-bold fs-7 text-start mt-1 font-monospace"><?= htmlspecialchars($patientData['name_en']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="col-12 col-md-6 col-xl-4">
                        <div class="info-field-box">
                            <span class="badge-lock-fixed"><i class="fa-solid fa-thumbtack me-1"></i> ثابت</span>
                            <span class="text-muted fw-black fs-8 d-block text-uppercase mb-1">رقم الهوية / الإقامة</span>
                            <div class="fw-black fs-5 text-end font-monospace"><?= htmlspecialchars($patientData['identity_number'] ?? '') ?></div>
                        </div>
                    </div>

                    <?php if (!empty($patientData['nationality_ar'])): ?>
                    <div class="col-12 col-md-6 col-xl-4">
                        <div class="info-field-box">
                            <span class="badge-lock-fixed"><i class="fa-solid fa-thumbtack me-1"></i> ثابت</span>
                            <span class="text-muted fw-black fs-8 d-block text-uppercase mb-1">الجنسية</span>
                            <div class="fw-black fs-5"><?= htmlspecialchars($patientData['nationality_ar']) ?></div>
                            <?php if (!empty($patientData['nationality_en'])): ?><div class="text-muted fw-bold fs-7 text-start mt-1 font-monospace"><?= htmlspecialchars($patientData['nationality_en']) ?></div><?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($patientData['employer_ar'])): ?>
                    <div class="col-12 col-md-6 col-xl-4">
                        <div class="info-field-box">
                            <span class="badge-lock-fixed"><i class="fa-solid fa-thumbtack me-1"></i> ثابت</span>
                            <span class="text-muted fw-black fs-8 d-block text-uppercase mb-1">جهة العمل</span>
                            <div class="fw-black fs-5"><?= htmlspecialchars($patientData['employer_ar']) ?></div>
                            <?php if (!empty($patientData['employer_en'])): ?><div class="text-muted fw-bold fs-7 text-start mt-1 font-monospace"><?= htmlspecialchars($patientData['employer_en']) ?></div><?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($patientData['phone'])): ?>
                    <div class="col-12 col-md-6 col-xl-4">
                        <div class="info-field-box">
                            <span class="badge-lock-fixed"><i class="fa-solid fa-thumbtack me-1"></i> ثابت</span>
                            <span class="text-muted fw-black fs-8 d-block text-uppercase mb-1">رقم الجوال</span>
                            <div class="fw-black fs-5 text-end font-monospace"><?= htmlspecialchars($patientData['phone']) ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="mt-5 p-4 rounded-4 bg-light-subtle border">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-black fs-6">حصة الإجازات المرضية المستهلكة</span>
                        <span class="fw-black fs-6 text-primary"><?= $usedDays ?> / <?= $allowedDays ?> يوم</span>
                    </div>
                    <?php
                      $pct = $allowedDays > 0 ? min(100, round($usedDays / $allowedDays * 100)) : 0;
                      $barBg = $pct >= 90 ? 'bg-danger' : ($pct >= 60 ? 'bg-warning' : 'bg-success');
                    ?>
                    <div class="progress rounded-pill h-15 bg-secondary-subtle">
                        <div class="progress-bar <?= $barBg ?> progress-bar-striped progress-bar-animated rounded-pill" style="width: <?= $pct ?>%"></div>
                    </div>
                    <div class="d-flex gap-3 mt-3 fw-bold fs-7 text-muted">
                        <span>مستخدم: <span class="text-danger fw-black"><?= $usedDays ?></span></span>
                        <span>|</span>
                        <span>متبقي: <span class="text-success fw-black"><?= $remainingDays ?></span></span>
                        <span>|</span>
                        <span>المسموح الكلي: <span class="text-primary fw-black"><?= $allowedDays ?></span></span>
                    </div>
                </div>

            </div>
        </div>
        <?php endif; ?>

    </div>
    <div class="card-luxury">
        <div class="card-header-luxury">
            <h3 class="card-title-luxury"><i class="fa-solid fa-folder-open"></i> السجل التاريخي لإجازاتي المرضية</h3>
            <span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis fw-black px-3 py-2 fs-7 border">
                <?= count($myLeaves) ?> وثيقة معتمدة
            </span>
        </div>
        <div class="p-0">
            <?php if (empty($myLeaves)): ?>
            <div class="text-center p-5 text-muted">
                <i class="fa-regular fa-folder-open fs-1 mb-3 d-block"></i>
                <h5 class="fw-black mb-1">لا توجد وثائق في السجل</h5>
                <p class="fw-bold fs-7 mb-0">لم تقم بإصدار أي إجازة مرضية حتى الآن.</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle m-0 fs-7 fw-bold text-nowrap">
                    <thead class="table-light text-uppercase fs-8 text-muted border-bottom">
                        <tr>
                            <th class="py-3 px-4 text-center">#</th>
                            <th class="py-3 px-3">الرمز الموحد</th>
                            <th class="py-3 px-3">المنشأة الطبية</th>
                            <th class="py-3 px-3">الطبيب المعالج</th>
                            <th class="py-3 px-3 text-center">من تاريخ</th>
                            <th class="py-3 px-3 text-center">إلى تاريخ</th>
                            <th class="py-3 px-3 text-center">المدة</th>
                            <th class="py-3 px-3 text-center">توقيت الإصدار</th>
                            <th class="py-3 px-4 text-center">الوثيقة (PDF)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($myLeaves as $i => $lv): ?>
                        <tr>
                            <td class="py-3 px-4 text-center text-muted fw-black"><?= $i + 1 ?></td>
                            <td class="py-3 px-3 text-start font-monospace text-primary fw-black"><?= htmlspecialchars($lv['service_code'] ?? '') ?></td>
                            <td class="py-3 px-3 fw-black"><?= htmlspecialchars($lv['h_name_ar'] ?? '') ?></td>
                            <td class="py-3 px-3">
                                <div class="fw-black text-body"><?= htmlspecialchars($lv['d_name_ar'] ?? '') ?></div>
                                <div class="text-muted fs-8 fw-bold mt-1"><?= htmlspecialchars($lv['d_title_ar'] ?? '') ?></div>
                            </td>
                            <td class="py-3 px-3 text-center font-monospace"><?= fmtDateUser($lv['start_date']) ?></td>
                            <td class="py-3 px-3 text-center font-monospace"><?= fmtDateUser($lv['end_date']) ?></td>
                            <td class="py-3 px-3 text-center">
                                <span class="badge rounded-pill bg-primary-subtle text-primary fw-black px-3 py-2 fs-7 border border-primary-subtle">
                                    <?= $lv['days_count'] ?> يوم
                                </span>
                            </td>
                            <td class="py-3 px-3 text-center font-monospace fs-7">
                                <?= htmlspecialchars($lv['issue_time'] ?? '') ?>
                                <?= $lv['issue_period'] === 'AM' ? 'ص' : ($lv['issue_period'] === 'PM' ? 'م' : '') ?>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <a href="user.php?action=generate_pdf&leave_id=<?= $lv['id'] ?>&pdf_mode=download" class="btn btn-sm btn-outline-primary rounded-pill fw-black px-3 py-1">
                                    <i class="fa-solid fa-file-pdf me-1"></i> تحميل PDF
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<div class="toast-overlay-container" id="toastContainer"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
const MAX_DAYS = <?= $remainingDays ?>;

// ================= إظهار / إخفاء الإحصائيات والبيانات بذكاء =================
function toggleStatsInfo() {
    const container = document.getElementById('statsInfoContainer');
    const btn = document.getElementById('toggleStatsBtn');
    const isHidden = container.style.display === 'none';
    
    if (isHidden) {
        container.style.display = 'block';
        btn.innerHTML = '<i class="fa-solid fa-eye-slash fs-5"></i><span>إخفاء الإحصائيات والبيانات الشخصية للمريض</span>';
        btn.classList.add('active');
    } else {
        container.style.display = 'none';
        btn.innerHTML = '<i class="fa-solid fa-chart-pie fs-5"></i><span>إظهار الإحصائيات والبيانات الشخصية للمريض</span>';
        btn.classList.remove('active');
    }
}

// ================= إدارة الوضع الليلي الأصيل (Bootstrap 5 Dark Mode) =================
function toggleTheme() {
    const htmlTag = document.documentElement;
    const current = htmlTag.getAttribute('data-bs-theme');
    const newTheme = current === 'dark' ? 'light' : 'dark';
    
    htmlTag.setAttribute('data-bs-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    updateThemeIcon(newTheme);
}

function updateThemeIcon(theme) {
    const icon = document.getElementById('themeIcon');
    if (icon) { icon.className = theme === 'dark' ? 'fa-solid fa-moon fs-5' : 'fa-solid fa-sun fs-5'; }
}

document.addEventListener('DOMContentLoaded', () => {
    const current = document.documentElement.getAttribute('data-bs-theme') || 'light';
    updateThemeIcon(current);
});

// ================= دوال التحكم بالنموذج والإرسال =================
function loadDoctors(hospitalId) {
    const sel = document.getElementById('doctorSelect');
    sel.innerHTML = '<option value="">جاري جلب الأطباء...</option>';
    if (!hospitalId) { sel.innerHTML = '<option value="">-- اختر المستشفى أولاً --</option>'; return; }
    
    fetch('user.php?action=get_doctors_by_hospital&hospital_id=' + hospitalId)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.doctors.length > 0) {
                sel.innerHTML = '<option value="">-- يرجى اختيار الطبيب --</option>';
                data.doctors.forEach(d => {
                    sel.innerHTML += `<option value="${d.id}">${d.name_ar} — (${d.title_ar})</option>`;
                });
            } else {
                sel.innerHTML = '<option value="">لا يوجد أطباء متاحين حالياً</option>';
            }
        })
        .catch(() => { sel.innerHTML = '<option value="">فشل التحميل</option>'; });
}

function calcDays() {
    const start = document.getElementById('startDate').value;
    const end   = document.getElementById('endDate').value;
    const display = document.getElementById('daysDisplay');
    const hidden  = document.getElementById('daysCount');
    const warning = document.getElementById('daysWarning');
    
    if (!start || !end) { display.value = ''; hidden.value = ''; return; }
    const s = new Date(start), e = new Date(end);
    if (e < s) {
        display.value = ''; hidden.value = '';
        warning.style.display = 'block';
        warning.className = 'alert alert-danger fw-bold rounded-4 p-3';
        warning.innerHTML = '<i class="fa-solid fa-triangle-exclamation me-2"></i> <b>خطأ:</b> تاريخ النهاية يجب أن يكون بعد تاريخ البداية.';
        return;
    }
    const diff = Math.round((e - s) / (1000 * 60 * 60 * 24)) + 1;
    display.value = diff + ' أيام';
    hidden.value  = diff;
    
    if (diff > MAX_DAYS) {
        warning.style.display = 'block';
        warning.className = 'alert alert-warning fw-bold rounded-4 p-3';
        warning.innerHTML = `<i class="fa-solid fa-circle-exclamation me-2"></i> <b>تنبيه:</b> أنت تطلب أياماً أكثر من رصيدك المتاح. لطلب أيام إضافية تواصل معنا على رقمنا واتس. المدة المطلوبة (${diff} أيام) تتجاوز الرصيد المتاح (${MAX_DAYS} يوم).`;
    } else {
        warning.style.display = 'none';
    }
    document.getElementById('endDate').min = start;
}

function setTimeMode(mode, btn) {
    document.querySelectorAll('.time-tab').forEach(t => t.classList.remove('active', 'btn-secondary', 'text-white'));
    document.querySelectorAll('.time-tab').forEach(t => t.classList.add('btn-outline-secondary'));
    
    btn.classList.remove('btn-outline-secondary');
    btn.classList.add('active', 'btn-secondary', 'text-white');
    
    document.getElementById('timeModeInput').value = mode;
    const mf = document.getElementById('manualTimeFields');
    const ai = document.getElementById('autoTimeInfo');
    
    if (mode === 'manual') { mf.style.display = 'block'; ai.style.display = 'none'; }
    else if (mode === 'random') { mf.style.display = 'none'; ai.style.display = 'block'; ai.innerHTML = '<i class="fa-solid fa-dice text-primary me-1"></i> سيتم توليد توقيت عشوائي ذكي خلال ساعات الدوام الرسمي.'; }
    else { mf.style.display = 'none'; ai.style.display = 'block'; ai.innerHTML = '<i class="fa-solid fa-circle-info text-primary me-1"></i> سيُعتمد التوقيت الفعلي للحظة الضغط على الزر برمجياً.'; }
}

function createLeave() {
    const hospitalId = document.getElementById('hospitalSelect').value;
    const doctorId   = document.getElementById('doctorSelect').value;
    const startDate  = document.getElementById('startDate').value;
    const endDate    = document.getElementById('endDate').value;
    const daysCount  = document.getElementById('daysCount').value;

    if (!hospitalId) { showToast('يرجى اختيار المنشأة الطبية أولاً.', 'error'); return; }
    if (!doctorId)   { showToast('يرجى تحديد الطبيب المعالج.', 'error'); return; }
    if (!startDate)  { showToast('تاريخ بداية الإجازة مطلوب.', 'error'); return; }
    if (!endDate)    { showToast('تاريخ نهاية الإجازة مطلوب.', 'error'); return; }
    if (!daysCount || parseInt(daysCount) <= 0) { showToast('يرجى ضبط التواريخ بشكل صحيح.', 'error'); return; }
    if (parseInt(daysCount) > MAX_DAYS) { showToast(`المدة المطلوبة تتجاوز رصيدك الحالي المتبقي (${MAX_DAYS} يوم).`, 'error'); return; }

    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin me-2"></i> جاري التوثيق والإصدار...';

    const formData = new FormData(document.getElementById('leaveForm'));
    formData.append('action', 'create_sick_leave');

    fetch('user.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast('✅ ' + data.message + ' (الرمز: ' + data.service_code + ')', 'success');
                setTimeout(() => { location.reload(); }, 1500);
            } else {
                showToast(data.message || 'حدث خطأ غير متوقع.', 'error');
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-cloud-arrow-up me-2"></i> اعتماد وإصدار الإجازة الفورية';
            }
        })
        .catch(() => {
            showToast('مشكلة في الاتصال بالخادم. يرجى المحاولة لاحقاً.', 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-cloud-arrow-up me-2"></i> اعتماد وإصدار الإجازة الفورية';
        });
}

function showToast(msg, type = 'success') {
    const container = document.getElementById('toastContainer');
    const icons = { success: '<i class="fa-solid fa-circle-check text-success fs-3"></i>', error: '<i class="fa-solid fa-circle-xmark text-danger fs-3"></i>', warning: '<i class="fa-solid fa-triangle-exclamation text-warning fs-3"></i>' };
    const toast = document.createElement('div');
    toast.className = 'toast-lux';
    if(type === 'error') toast.style.borderRightColor = '#ef4444';
    if(type === 'warning') toast.style.borderRightColor = '#f59e0b';
    
    toast.innerHTML = `${icons[type] || '<i class="fa-solid fa-comment fs-3"></i>'}<span style="flex:1;">${msg}</span>`;
    container.appendChild(toast);
    
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(-10px)';
        toast.style.transition = 'all 0.4s ease';
        setTimeout(() => toast.remove(), 400);
    }, 5000);
}

// ================= نظام الإشعارات =================
let notifPanelOpen = false;

function toggleNotifPanel() {
    const panel = document.getElementById('notifPanel');
    notifPanelOpen = !notifPanelOpen;
    panel.style.display = notifPanelOpen ? 'block' : 'none';
    if (notifPanelOpen) loadNotifications();
}

document.addEventListener('click', function(e) {
    const bell = document.getElementById('notifBell');
    const panel = document.getElementById('notifPanel');
    if (panel && bell && !bell.contains(e.target) && !panel.contains(e.target)) {
        panel.style.display = 'none';
        notifPanelOpen = false;
    }
});

async function loadNotifications() {
    try {
        const res = await fetch('user.php?action=get_user_notifications');
        const data = await res.json();
        if (data.success) {
            const list = document.getElementById('notifList');
            const badge = document.getElementById('notifBadge');
            if (data.unread_count > 0) {
                badge.style.display = 'inline-block';
                badge.textContent = data.unread_count > 9 ? '9+' : data.unread_count;
            } else {
                badge.style.display = 'none';
            }
            if (!data.notifications || data.notifications.length === 0) {
                list.innerHTML = '<div class="text-center p-4 text-muted fw-bold fs-7">لا توجد إشعارات حالياً</div>';
                return;
            }
            list.innerHTML = data.notifications.map(n => `
                <div class="p-3 border-bottom ${n.is_read == 0 ? 'bg-primary-subtle border-primary' : ''}">
                    <div class="fs-7 fw-bold text-body line-height-1.5">${escapeHtml(n.message)}</div>
                    <div class="text-muted fs-8 font-monospace mt-2">${n.created_at}</div>
                </div>
            `).join('');
        }
    } catch(e) {}
}

async function markAllRead() {
    try {
        const fd = new FormData();
        fd.append('action', 'mark_user_notifications_read');
        await fetch('user.php', { method: 'POST', body: fd });
        document.getElementById('notifBadge').style.display = 'none';
        loadNotifications();
    } catch(e) {}
}

function escapeHtml(str) {
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}

(async function() {
    try {
        const res = await fetch('user.php?action=get_user_notifications');
        const data = await res.json();
        if (data.success && data.unread_count > 0) {
            const badge = document.getElementById('notifBadge');
            if (badge) {
                badge.style.display = 'inline-block';
                badge.textContent = data.unread_count > 9 ? '9+' : data.unread_count;
            }
        }
    } catch(e) {}
})();
</script>

<?php endif; ?>
</body>
</html>
