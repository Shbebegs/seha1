<?php
/**
 * لوحة إدارة الإجازات المرضية - Admin Panel
 * ملف واحد شامل: إدارة المستشفيات + الأطباء + المرضى + إصدار الإجازات + طباعة PDF
 * القالب الأصلي مدمج بالضبط بدون أي تغيير
 */

ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
session_start();
date_default_timezone_set('Asia/Riyadh');

// ======================== إعدادات قاعدة البيانات ========================
$db_host = 'mysql.railway.internal';
$db_user = 'root';
$db_pass = 'xGnyGcxVAYSWwbRBSYpCDOwYkIvuTSbv';
$db_name = 'railway';
$db_port = 3306;

try {
    $pdo = new PDO(
        "mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4",
        $db_user, $db_pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
    );
} catch (PDOException $e) {
    die('فشل الاتصال بقاعدة البيانات: ' . $e->getMessage());
}
$pdo->exec("SET time_zone = '+03:00'");

// ======================== دوال البنية ========================
function tableExists(PDO $pdo, string $table): bool {
    $s = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?");
    $s->execute([$table]);
    return (int)$s->fetchColumn() > 0;
}
function ensureColumn(PDO $pdo, string $table, string $column, string $def): void {
    if (!tableExists($pdo, $table)) return;
    $s = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?");
    $s->execute([$table, $column]);
    if ((int)$s->fetchColumn() === 0) $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $def");
}

// ======================== إنشاء الجداول ========================
$pdo->exec("CREATE TABLE IF NOT EXISTS hospitals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name_ar VARCHAR(200) NOT NULL,
    name_en VARCHAR(200) DEFAULT '',
    license_number VARCHAR(50) DEFAULT '',
    logo_path VARCHAR(500) DEFAULT '',
    service_prefix ENUM('GSL','PSL') DEFAULT 'GSL',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS doctors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name_ar VARCHAR(150) NOT NULL,
    name_en VARCHAR(150) DEFAULT '',
    title_ar VARCHAR(150) NOT NULL,
    title_en VARCHAR(150) DEFAULT '',
    hospital_id INT NULL,
    note TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name_ar VARCHAR(150) NOT NULL,
    name_en VARCHAR(150) DEFAULT '',
    identity_number VARCHAR(50) NOT NULL,
    phone VARCHAR(30) DEFAULT '',
    nationality_ar VARCHAR(100) DEFAULT 'السعودية',
    nationality_en VARCHAR(100) DEFAULT 'Saudi Arabia',
    employer_ar VARCHAR(200) DEFAULT '',
    employer_en VARCHAR(200) DEFAULT '',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_identity (identity_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS sick_leaves (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_code VARCHAR(50) NOT NULL,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    hospital_id INT NULL,
    issue_date DATE NOT NULL,
    issue_time VARCHAR(10) DEFAULT '09:00',
    issue_period ENUM('AM','PM') DEFAULT 'AM',
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    days_count INT NOT NULL,
    is_companion TINYINT(1) DEFAULT 0,
    companion_name VARCHAR(150) DEFAULT '',
    companion_relation VARCHAR(150) DEFAULT '',
    is_paid TINYINT(1) DEFAULT 0,
    payment_amount DECIMAL(10,2) DEFAULT 0,
    deleted_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_service_code (service_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// إضافة الأعمدة الناقصة للجداول الموجودة مسبقاً
ensureColumn($pdo, 'hospitals', 'name_ar', "VARCHAR(200) NOT NULL DEFAULT ''");
ensureColumn($pdo, 'hospitals', 'name_en', "VARCHAR(200) DEFAULT ''");
ensureColumn($pdo, 'hospitals', 'license_number', "VARCHAR(50) DEFAULT ''");
ensureColumn($pdo, 'hospitals', 'logo_path', "VARCHAR(500) DEFAULT ''");
ensureColumn($pdo, 'hospitals', 'service_prefix', "ENUM('GSL','PSL') DEFAULT 'GSL'");
ensureColumn($pdo, 'doctors', 'name_ar', "VARCHAR(150) DEFAULT ''");
ensureColumn($pdo, 'doctors', 'name_en', "VARCHAR(150) DEFAULT ''");
ensureColumn($pdo, 'doctors', 'title_ar', "VARCHAR(150) DEFAULT ''");
ensureColumn($pdo, 'doctors', 'title_en', "VARCHAR(150) DEFAULT ''");
ensureColumn($pdo, 'doctors', 'hospital_id', "INT NULL");
ensureColumn($pdo, 'patients', 'name_ar', "VARCHAR(150) DEFAULT ''");
ensureColumn($pdo, 'patients', 'name_en', "VARCHAR(150) DEFAULT ''");
ensureColumn($pdo, 'patients', 'nationality_ar', "VARCHAR(100) DEFAULT 'السعودية'");
ensureColumn($pdo, 'patients', 'nationality_en', "VARCHAR(100) DEFAULT 'Saudi Arabia'");
ensureColumn($pdo, 'patients', 'employer_ar', "VARCHAR(200) DEFAULT ''");
ensureColumn($pdo, 'patients', 'employer_en', "VARCHAR(200) DEFAULT ''");
ensureColumn($pdo, 'sick_leaves', 'hospital_id', "INT NULL");
ensureColumn($pdo, 'sick_leaves', 'issue_time', "VARCHAR(10) DEFAULT '09:00'");
ensureColumn($pdo, 'sick_leaves', 'issue_period', "ENUM('AM','PM') DEFAULT 'AM'");
ensureColumn($pdo, 'sick_leaves', 'deleted_at', "DATETIME NULL");

// ======================== دوال مساعدة ========================
function gregorianToHijri($gDate) {
    if (empty($gDate)) return '';
    $d = DateTime::createFromFormat('Y-m-d', $gDate);
    if (!$d) return '';
    $gy = (int)$d->format('Y'); $gm = (int)$d->format('m'); $gd = (int)$d->format('d');
    
    if ($gy > 1582 || ($gy == 1582 && $gm > 10) || ($gy == 1582 && $gm == 10 && $gd > 14)) {
        $jd = (int)(1461 * ($gy + 4800 + (int)(($gm - 14) / 12))) / 4
            + (int)(367 * ($gm - 2 - 12 * (int)(($gm - 14) / 12))) / 12
            - (int)(3 * (int)(($gy + 4900 + (int)(($gm - 14) / 12)) / 100)) / 4
            + $gd - 32075;
    } else {
        $jd = 367 * $gy - (int)(7 * ($gy + (int)(($gm + 9) / 12))) / 4
            + (int)(275 * $gm / 9) + $gd + 1721013.5;
    }
    $jd = (int)$jd;
    
    $l = $jd - 1948440 + 10632;
    $n = (int)(($l - 1) / 10631);
    $l = $l - 10631 * $n + 354;
    $j = (int)((10985 - $l) / 5316) * (int)((50 * $l) / 17719) + (int)($l / 5670) * (int)((43 * $l) / 15238);
    $l = $l - (int)((30 - $j) / 15) * (int)((17719 * $j) / 50) - (int)($j / 16) * (int)((15238 * $j) / 43) + 29;
    $hm = (int)(24 * $l / 709);
    $hd = $l - (int)(709 * $hm / 24);
    $hy = 30 * $n + $j - 30;
    
    return str_pad($hd, 2, '0', STR_PAD_LEFT) . '-' . str_pad($hm, 2, '0', STR_PAD_LEFT) . '-' . $hy;
}

function getEnglishDayName($date) {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d ? $d->format('l') : '';
}

function getEnglishMonthName($date) {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d ? $d->format('F') : '';
}

function getYear($date) {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d ? $d->format('Y') : '';
}

function getDayNum($date) {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d ? $d->format('d') : '';
}

function formatDateDMY($date) {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d ? $d->format('d-m-Y') : '';
}

function generateServiceCode($pdo, $prefix, $issueDate = null) {
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

function e($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }

// ======================== معالجة الطلبات AJAX ========================
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// API: جلب أطباء مستشفى معين
if ($action === 'api_get_doctors') {
    header('Content-Type: application/json; charset=utf-8');
    $hid = intval($_GET['hospital_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT id, name_ar, name_en, title_ar, title_en FROM doctors WHERE hospital_id=? ORDER BY name_ar");
    $stmt->execute([$hid]);
    echo json_encode(['doctors' => $stmt->fetchAll()]);
    exit;
}

// API: جلب بيانات مريض
if ($action === 'api_get_patient') {
    header('Content-Type: application/json; charset=utf-8');
    $pid = intval($_GET['patient_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE id=?");
    $stmt->execute([$pid]);
    echo json_encode(['patient' => $stmt->fetch()]);
    exit;
}

// API: جلب بيانات مستشفى
if ($action === 'api_get_hospital') {
    header('Content-Type: application/json; charset=utf-8');
    $hid = intval($_GET['hospital_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM hospitals WHERE id=?");
    $stmt->execute([$hid]);
    echo json_encode(['hospital' => $stmt->fetch()]);
    exit;
}

// إضافة مستشفى
if ($action === 'add_hospital' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name_ar = trim($_POST['name_ar'] ?? '');
    $name_en = trim($_POST['name_en'] ?? '');
    $license = trim($_POST['license_number'] ?? '');
    $logo_url = trim($_POST['logo_url'] ?? '');
    $sp = ($_POST['service_prefix'] ?? 'GSL') === 'PSL' ? 'PSL' : 'GSL';
    
    if (empty($name_ar)) { $_SESSION['flash'] = ['error', 'يجب إدخال اسم المستشفى بالعربية']; header('Location: ?page=hospitals'); exit; }
    
    $logo_path = '';
    // رفع ملف
    if (!empty($_FILES['logo_file']['name']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
        $dir = __DIR__ . '/hospital_logos';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $ext = strtolower(pathinfo($_FILES['logo_file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','gif','svg','webp'])) {
            $fn = 'h_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            if (move_uploaded_file($_FILES['logo_file']['tmp_name'], $dir . '/' . $fn)) {
                $logo_path = 'hospital_logos/' . $fn;
            }
        }
    }
    // أو رابط
    elseif (!empty($logo_url)) {
        $dir = __DIR__ . '/hospital_logos';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $ext = strtolower(pathinfo(parse_url($logo_url, PHP_URL_PATH), PATHINFO_EXTENSION));
        if (empty($ext) || !in_array($ext, ['jpg','jpeg','png','gif','svg','webp'])) $ext = 'png';
        $fn = 'h_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $ch = curl_init($logo_url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_FOLLOWLOCATION => true]);
        $data = curl_exec($ch); curl_close($ch);
        if ($data && file_put_contents($dir . '/' . $fn, $data)) {
            $logo_path = 'hospital_logos/' . $fn;
        }
    }
    
    $stmt = $pdo->prepare("INSERT INTO hospitals (name_ar, name_en, license_number, logo_path, service_prefix) VALUES (?,?,?,?,?)");
    $stmt->execute([$name_ar, $name_en, $license, $logo_path, $sp]);
    $_SESSION['flash'] = ['success', 'تم إضافة المستشفى بنجاح'];
    header('Location: ?page=hospitals'); exit;
}

// تعديل مستشفى
if ($action === 'edit_hospital' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id'] ?? 0);
    $name_ar = trim($_POST['name_ar'] ?? '');
    $name_en = trim($_POST['name_en'] ?? '');
    $license = trim($_POST['license_number'] ?? '');
    $logo_url = trim($_POST['logo_url'] ?? '');
    $sp = ($_POST['service_prefix'] ?? 'GSL') === 'PSL' ? 'PSL' : 'GSL';
    
    if ($id <= 0 || empty($name_ar)) { $_SESSION['flash'] = ['error', 'بيانات غير صحيحة']; header('Location: ?page=hospitals'); exit; }
    
    $logo_path = '';
    if (!empty($_FILES['logo_file']['name']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
        $dir = __DIR__ . '/hospital_logos';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $ext = strtolower(pathinfo($_FILES['logo_file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','gif','svg','webp'])) {
            $fn = 'h_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            if (move_uploaded_file($_FILES['logo_file']['tmp_name'], $dir . '/' . $fn)) {
                $logo_path = 'hospital_logos/' . $fn;
            }
        }
    } elseif (!empty($logo_url)) {
        $dir = __DIR__ . '/hospital_logos';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $ext = strtolower(pathinfo(parse_url($logo_url, PHP_URL_PATH), PATHINFO_EXTENSION));
        if (empty($ext) || !in_array($ext, ['jpg','jpeg','png','gif','svg','webp'])) $ext = 'png';
        $fn = 'h_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $ch = curl_init($logo_url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_FOLLOWLOCATION => true]);
        $data = curl_exec($ch); curl_close($ch);
        if ($data && file_put_contents($dir . '/' . $fn, $data)) {
            $logo_path = 'hospital_logos/' . $fn;
        }
    }
    
    if (!empty($logo_path)) {
        $stmt = $pdo->prepare("UPDATE hospitals SET name_ar=?, name_en=?, license_number=?, logo_path=?, service_prefix=? WHERE id=?");
        $stmt->execute([$name_ar, $name_en, $license, $logo_path, $sp, $id]);
    } else {
        $stmt = $pdo->prepare("UPDATE hospitals SET name_ar=?, name_en=?, license_number=?, service_prefix=? WHERE id=?");
        $stmt->execute([$name_ar, $name_en, $license, $sp, $id]);
    }
    $_SESSION['flash'] = ['success', 'تم تعديل المستشفى بنجاح'];
    header('Location: ?page=hospitals'); exit;
}

// حذف مستشفى
if ($action === 'delete_hospital') {
    $id = intval($_GET['id'] ?? 0);
    if ($id > 0) {
        $pdo->prepare("UPDATE doctors SET hospital_id=NULL WHERE hospital_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM hospitals WHERE id=?")->execute([$id]);
        $_SESSION['flash'] = ['success', 'تم حذف المستشفى'];
    }
    header('Location: ?page=hospitals'); exit;
}

// إضافة طبيب
if ($action === 'add_doctor' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name_ar = trim($_POST['name_ar'] ?? '');
    $name_en = trim($_POST['name_en'] ?? '');
    $title_ar = trim($_POST['title_ar'] ?? '');
    $title_en = trim($_POST['title_en'] ?? '');
    $hospital_id = intval($_POST['hospital_id'] ?? 0) ?: null;
    $note = trim($_POST['note'] ?? '');
    
    if (empty($name_ar) || empty($title_ar)) { $_SESSION['flash'] = ['error', 'يجب إدخال اسم الطبيب والمسمى الوظيفي']; header('Location: ?page=doctors'); exit; }
    
    $stmt = $pdo->prepare("INSERT INTO doctors (name_ar, name_en, title_ar, title_en, hospital_id, note) VALUES (?,?,?,?,?,?)");
    $stmt->execute([$name_ar, $name_en, $title_ar, $title_en, $hospital_id, $note]);
    $_SESSION['flash'] = ['success', 'تم إضافة الطبيب بنجاح'];
    header('Location: ?page=doctors'); exit;
}

// تعديل طبيب
if ($action === 'edit_doctor' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id'] ?? 0);
    $name_ar = trim($_POST['name_ar'] ?? '');
    $name_en = trim($_POST['name_en'] ?? '');
    $title_ar = trim($_POST['title_ar'] ?? '');
    $title_en = trim($_POST['title_en'] ?? '');
    $hospital_id = intval($_POST['hospital_id'] ?? 0) ?: null;
    $note = trim($_POST['note'] ?? '');
    
    if ($id <= 0 || empty($name_ar) || empty($title_ar)) { $_SESSION['flash'] = ['error', 'بيانات غير صحيحة']; header('Location: ?page=doctors'); exit; }
    
    $stmt = $pdo->prepare("UPDATE doctors SET name_ar=?, name_en=?, title_ar=?, title_en=?, hospital_id=?, note=? WHERE id=?");
    $stmt->execute([$name_ar, $name_en, $title_ar, $title_en, $hospital_id, $note, $id]);
    $_SESSION['flash'] = ['success', 'تم تعديل الطبيب بنجاح'];
    header('Location: ?page=doctors'); exit;
}

// حذف طبيب
if ($action === 'delete_doctor') {
    $id = intval($_GET['id'] ?? 0);
    if ($id > 0) { $pdo->prepare("DELETE FROM doctors WHERE id=?")->execute([$id]); $_SESSION['flash'] = ['success', 'تم حذف الطبيب']; }
    header('Location: ?page=doctors'); exit;
}

// إضافة مريض
if ($action === 'add_patient' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name_ar = trim($_POST['name_ar'] ?? '');
    $name_en = trim($_POST['name_en'] ?? '');
    $identity = trim($_POST['identity_number'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $nat_ar = trim($_POST['nationality_ar'] ?? 'السعودية');
    $nat_en = trim($_POST['nationality_en'] ?? 'Saudi Arabia');
    $emp_ar = trim($_POST['employer_ar'] ?? '');
    $emp_en = trim($_POST['employer_en'] ?? '');
    
    if (empty($name_ar) || empty($identity)) { $_SESSION['flash'] = ['error', 'يجب إدخال اسم المريض ورقم الهوية']; header('Location: ?page=patients'); exit; }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO patients (name_ar, name_en, identity_number, phone, nationality_ar, nationality_en, employer_ar, employer_en) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->execute([$name_ar, $name_en, $identity, $phone, $nat_ar, $nat_en, $emp_ar, $emp_en]);
        $_SESSION['flash'] = ['success', 'تم إضافة المريض بنجاح'];
    } catch (PDOException $ex) {
        $_SESSION['flash'] = ['error', 'رقم الهوية موجود بالفعل'];
    }
    header('Location: ?page=patients'); exit;
}

// تعديل مريض
if ($action === 'edit_patient' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id'] ?? 0);
    $name_ar = trim($_POST['name_ar'] ?? '');
    $name_en = trim($_POST['name_en'] ?? '');
    $identity = trim($_POST['identity_number'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $nat_ar = trim($_POST['nationality_ar'] ?? 'السعودية');
    $nat_en = trim($_POST['nationality_en'] ?? 'Saudi Arabia');
    $emp_ar = trim($_POST['employer_ar'] ?? '');
    $emp_en = trim($_POST['employer_en'] ?? '');
    
    if ($id <= 0 || empty($name_ar) || empty($identity)) { $_SESSION['flash'] = ['error', 'بيانات غير صحيحة']; header('Location: ?page=patients'); exit; }
    
    $stmt = $pdo->prepare("UPDATE patients SET name_ar=?, name_en=?, identity_number=?, phone=?, nationality_ar=?, nationality_en=?, employer_ar=?, employer_en=? WHERE id=?");
    $stmt->execute([$name_ar, $name_en, $identity, $phone, $nat_ar, $nat_en, $emp_ar, $emp_en, $id]);
    $_SESSION['flash'] = ['success', 'تم تعديل بيانات المريض'];
    header('Location: ?page=patients'); exit;
}

// حذف مريض
if ($action === 'delete_patient') {
    $id = intval($_GET['id'] ?? 0);
    if ($id > 0) { $pdo->prepare("DELETE FROM patients WHERE id=?")->execute([$id]); $_SESSION['flash'] = ['success', 'تم حذف المريض']; }
    header('Location: ?page=patients'); exit;
}

// إصدار إجازة
if ($action === 'issue_leave' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id = intval($_POST['patient_id'] ?? 0);
    $doctor_id = intval($_POST['doctor_id'] ?? 0);
    $hospital_id = intval($_POST['hospital_id'] ?? 0) ?: null;
    $issue_date = $_POST['issue_date'] ?? '';
    $issue_time = $_POST['issue_time'] ?? '09:00';
    $issue_period = ($_POST['issue_period'] ?? 'AM') === 'PM' ? 'PM' : 'AM';
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $is_companion = isset($_POST['is_companion']) ? 1 : 0;
    $companion_name = trim($_POST['companion_name'] ?? '');
    $companion_relation = trim($_POST['companion_relation'] ?? '');
    $is_paid = isset($_POST['is_paid']) ? 1 : 0;
    $payment_amount = floatval($_POST['payment_amount'] ?? 0);
    $service_code_manual = trim($_POST['service_code_manual'] ?? '');
    
    if ($patient_id <= 0 || $doctor_id <= 0 || empty($issue_date) || empty($start_date) || empty($end_date)) {
        $_SESSION['flash'] = ['error', 'يجب تعبئة جميع الحقول المطلوبة'];
        header('Location: ?page=issue'); exit;
    }
    
    $s = DateTime::createFromFormat('Y-m-d', $start_date);
    $en = DateTime::createFromFormat('Y-m-d', $end_date);
    if (!$s || !$en) { $_SESSION['flash'] = ['error', 'التواريخ غير صحيحة']; header('Location: ?page=issue'); exit; }
    $days_count = $s->diff($en)->days + 1;
    
    // تحديد البريفكس من المستشفى
    $prefix = 'GSL';
    if ($hospital_id) {
        $sp = $pdo->prepare("SELECT service_prefix FROM hospitals WHERE id=?");
        $sp->execute([$hospital_id]);
        $r = $sp->fetchColumn();
        if ($r) $prefix = $r;
    }
    
    if (!empty($service_code_manual)) {
        $service_code = strtoupper($service_code_manual);
    } else {
        $service_code = generateServiceCode($pdo, $prefix, $issue_date);
    }
    
    $stmt = $pdo->prepare("INSERT INTO sick_leaves (service_code, patient_id, doctor_id, hospital_id, issue_date, issue_time, issue_period, start_date, end_date, days_count, is_companion, companion_name, companion_relation, is_paid, payment_amount) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([$service_code, $patient_id, $doctor_id, $hospital_id, $issue_date, $issue_time, $issue_period, $start_date, $end_date, $days_count, $is_companion, $companion_name, $companion_relation, $is_paid, $payment_amount]);
    
    $_SESSION['flash'] = ['success', 'تم إصدار الإجازة بنجاح - رمز: ' . $service_code];
    $_SESSION['last_leave_id'] = $pdo->lastInsertId();
    header('Location: ?page=issue'); exit;
}

// حذف إجازة (soft delete)
if ($action === 'delete_leave') {
    $id = intval($_GET['id'] ?? 0);
    if ($id > 0) { $pdo->prepare("UPDATE sick_leaves SET deleted_at=NOW() WHERE id=?")->execute([$id]); $_SESSION['flash'] = ['success', 'تم حذف الإجازة']; }
    header('Location: ?page=leaves'); exit;
}

// ======================== طباعة PDF - القالب الأصلي بالضبط ========================
if ($action === 'print_leave') {
    $lid = intval($_GET['id'] ?? 0);
    if ($lid <= 0) { header('Location: ?page=leaves'); exit; }
    
    $stmt = $pdo->prepare("
        SELECT sl.*, 
            p.name_ar AS p_name_ar, p.name_en AS p_name_en, p.identity_number AS p_id_num,
            p.nationality_ar AS p_nat_ar, p.nationality_en AS p_nat_en,
            p.employer_ar AS p_emp_ar, p.employer_en AS p_emp_en,
            d.name_ar AS d_name_ar, d.name_en AS d_name_en,
            d.title_ar AS d_title_ar, d.title_en AS d_title_en,
            h.name_ar AS h_name_ar, h.name_en AS h_name_en,
            h.license_number AS h_license, h.logo_path AS h_logo
        FROM sick_leaves sl
        LEFT JOIN patients p ON sl.patient_id = p.id
        LEFT JOIN doctors d ON sl.doctor_id = d.id
        LEFT JOIN hospitals h ON sl.hospital_id = h.id
        WHERE sl.id = ?
    ");
    $stmt->execute([$lid]);
    $lv = $stmt->fetch();
    if (!$lv) { header('Location: ?page=leaves'); exit; }
    
    // حساب كل القيم
    $service_code = e($lv['service_code']);
    $days = (int)$lv['days_count'];
    $days_en = $days . ' ' . ($days == 1 ? 'day' : 'days');
    $days_ar_num = $days;
    $days_ar_word = 'يوم';
    
    $start_dmy = formatDateDMY($lv['start_date']);
    $end_dmy = formatDateDMY($lv['end_date']);
    $issue_dmy = formatDateDMY($lv['issue_date']);
    
    $start_hijri = gregorianToHijri($lv['start_date']);
    $end_hijri = gregorianToHijri($lv['end_date']);
    $issue_hijri = gregorianToHijri($lv['issue_date']);
    
    $duration_en = $days_en . ' ( ' . $start_dmy . ' to ' . $end_dmy . ' )';
    $duration_ar_dates = '( ' . $start_hijri . ' الى ' . $end_hijri . ' )';
    
    $admission_dmy = $start_dmy;
    $admission_hijri = $start_hijri;
    $discharge_dmy = $end_dmy;
    $discharge_hijri = $end_hijri;
    
    $p_name_en = strtoupper(e($lv['p_name_en']));
    $p_name_ar = e($lv['p_name_ar']);
    $p_id_num = e($lv['p_id_num']);
    $p_nat_en = e($lv['p_nat_en']);
    $p_nat_ar = e($lv['p_nat_ar']);
    $p_emp_en = strtoupper(e($lv['p_emp_en']));
    $p_emp_ar = e($lv['p_emp_ar']);
    
    $d_name_en = strtoupper(e($lv['d_name_en']));
    $d_name_ar = e($lv['d_name_ar']);
    $d_title_en = e($lv['d_title_en']);
    $d_title_ar = e($lv['d_title_ar']);
    
    $h_name_ar = e($lv['h_name_ar']);
    $h_name_en = strtoupper(e($lv['h_name_en']));
    $h_license = e($lv['h_license']);
    $h_logo = $lv['h_logo'];
    
    // شعار المستشفى
    $logo_src = 'sehalogoright.svg'; // الافتراضي
    if (!empty($h_logo) && file_exists(__DIR__ . '/' . $h_logo)) {
        $logo_src = $h_logo;
    } elseif (!empty($h_logo) && (str_starts_with($h_logo, 'http://') || str_starts_with($h_logo, 'https://'))) {
        $logo_src = $h_logo;
    }
    
    // رقم الترخيص
    $license_html = '';
    if (!empty(trim($lv['h_license']))) {
        $license_html = '<span style="font-family: \'Noto Sans Arabic\', sans-serif; font-weight: 700;">رقم الترخيص :</span> <span style="font-family: \'Times New Roman\', serif; font-weight: 700;">' . $h_license . '</span>';
    }
    
    // الوقت والتاريخ
    $time_str = e($lv['issue_time']) . ' ' . e($lv['issue_period']);
    $day_name = getEnglishDayName($lv['issue_date']);
    $day_num = getDayNum($lv['issue_date']);
    $month_name = getEnglishMonthName($lv['issue_date']);
    $year = getYear($lv['issue_date']);
    $date_line = $day_name . ', ' . $day_num . ' ' . $month_name . ' ' . $year;
    
    // ======================== القالب الأصلي بالضبط ========================
    ?>
<!DOCTYPE html>
<html lang="ar">
  <head>
    <title>تقرير إجازة مرضية - Sick Leave Report</title>
    <meta property="og:title" content="Sick Leave Report" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta charset="utf-8" />

    <style data-tag="reset-style-sheet">
      html { line-height: 1.15; }
      body { margin: 0; }
      * { box-sizing: border-box; border-width: 0; border-style: solid; -webkit-font-smoothing: antialiased; }
      p, li, ul, pre, div, h1, h2, h3, h4, h5, h6, figure, blockquote, figcaption { margin: 0; padding: 0; }
      a { color: inherit; text-decoration: inherit; }
      html { scroll-behavior: smooth }
    </style>
    
    <style data-tag="default-style-sheet">
      html {
        font-family: Inter, sans-serif;
        font-size: 16px;
      }
      body {
        font-weight: 400;
        color: #191818;
        background: #FBFAF9;
      }
    </style>

    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700&display=swap" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=STIX+Two+Text:ital,wght@0,400;0,600;0,700;1,400&display=swap" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+Arabic:wght@400;600;700&display=swap" />

    <style>
      /* Layout Container */
      .group1-container1 {
        width: 100%;
        display: flex;
        overflow: auto;
        min-height: 100vh;
        align-items: center;
        flex-direction: column;
        background-color: #f0f0f0;
        padding-top: 20px;
        padding-bottom: 20px;
      }
      
      /* Main Document Sheet */
      .group1-thq-group1-elm {
        width: 842.25px;
        height: 1190.25px;
        display: flex;
        position: relative;
        align-items: flex-start;
        flex-shrink: 0;
        box-shadow: 0px 4px 15px rgba(0,0,0,0.1);
        background-color: white;
      }

      /* =========================================
         CENTRAL TABLE STYLES
         ========================================= */
      .info-table {
        position: absolute;
        top: 242px; 
        left: 36px; 
        width: 770px;
        border-collapse: separate;
        border-spacing: 0;
        border: 1px solid #cccccc; 
        border-radius: 8px; 
        overflow: hidden;
        background-color: transparent;
        z-index: 10;
      }

      .info-table td {
        border-bottom: 1px solid #cccccc;
        border-right: 1px solid #cccccc;
        height: 42px; 
        text-align: center;
        vertical-align: middle;
        padding: 4px 8px;
      }

      .info-table td:last-child { border-right: none; }
      .info-table tr:last-child td { border-bottom: none; }

      .info-table .en-title {
        width: 161px;
        color: rgba(54, 111, 181, 1);
        font-size: 13.5px;
        font-weight: 700;
        text-align: center;
        font-family: 'Times New Roman', serif;
      }

      .info-table .data-cell {
        width: 240px;
        color: rgba(44, 62, 119, 1);
        font-size: 13.5px;  
        font-family: 'Times New Roman', serif;
        font-weight: 400;
        text-align: center;
      }
      
      .info-table .date-cell { font-size: 13.9px; }
      .info-table .data-cell.ar-text { font-family: 'Noto Sans Arabic'; }

      .info-table .ar-title {
        width: 140px; 
        color: rgba(54, 111, 181, 1);
        font-size: 13.5px;
        font-weight: 700;
        text-align: center;
        font-family: 'Noto Sans Arabic';
        white-space: nowrap; 
      }

      .info-table tr.blue-row td {
        background-color: #2c3e77; 
        color: #ffffff; 
        border-bottom: 1px solid #cccccc;
        border-right: 1px solid #cccccc;
      }
      .info-table tr.blue-row td:last-child { border-right: none; }
      
      .info-table .blue-row .data-cell.ar-text {
         color: rgba(255, 255, 255, 1);
         font-size: 13.5px;
         font-family: 'Times New Roman', serif;
         font-weight: 400;
      }
      
      .info-table .blue-row .data-cell { color: rgba(255, 255, 255, 1); }
      .info-table tr.gray-row td { background-color: #f7f7f7; }

      .en-spaced { letter-spacing: 0.3px; }

      :root { --footer-offset: 40px; }

      .group1-thq-staticinfo-elm { top: 125px; left: 36.65px; width: 768.35px; height: 811.91px; display: flex; position: absolute; align-items: flex-start; pointer-events: none;}
      
      /* Side Placeholders */
      .top-right-placeholder {
        position: absolute;
        top: 36px;
        left: 592px;
        width: 214px;
        height: 107px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        z-index: 5;
      }

      .top-left-placeholder {
        position: absolute;
        top: 36px;
        left: 36px;
        width: 149.96px;
        height: 65.98px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        z-index: 5;
      }

      .bottom-right-placeholder {
        position: absolute;
        top: 1005px;
        left: 657.17px;
        width: 149.96px;
        height: 71.23px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        z-index: 5;
      }

      /* New Header Placeholder (5.7cm x 1.5cm converted to px) */
      .header-placeholder {
        top: -55px; 
        left: 320px; 
        width: 160px; 
        height: 50px; 
        position: absolute;  
        display: flex; 
        align-items: center; 
        justify-content: center;  
        font-size: 11px;
      }

      .group1-thq-text-elm41 { top: 40px; left: 289px; color: rgba(48, 109, 181, 1); width: 215px; position: absolute; font-size: 22.5px; font-weight: 700; text-align: center; line-height: 30px; }
      .group1-thq-text-elm44 { top: -10px; left: 310px; color: rgba(0, 0, 0, 1); position: absolute; font-size: 17.3px; font-weight: 400; text-align: left; font-family: 'Times New Roman', serif;}

      .group1-thq-hospitallogoandthename-elm { top: 760px; left: 438.94px; width: 403px; height: 202.78px; display: flex; position: absolute; align-items: flex-start; }
      .placeholder-logo-hospital {
        top: -12px; left: 133px; width: 136px; height: 136px; position: absolute;  display: flex; align-items: center; justify-content: center; font-size: 12px;
      }
      .group1-thq-text-elm18 { top: 120px; color: rgba(0, 0, 0, 1); width: 403px; height: auto; position: absolute; font-size: 12.8px; text-align: center; line-height: 22px; }

      .group1-thq-thedateofissueandalsotimeofissue-elm { top: calc(989.85px + var(--footer-offset)); left: 37.37px; width: 250px; height: 56px; display: flex; position: absolute; align-items: flex-start; }
      .group1-thq-text-elm22 { color: rgba(0, 0, 0, 1); font-size: 12.5px; font-weight: 700; text-align: left; line-height: 28px; font-family: 'Times New Roman', serif; font-weight: bold; position: absolute; white-space: nowrap;}

      .group1-thq-text-elm36 { top: calc(724.55px + var(--footer-offset)); left: 29.23px; color: rgba(0, 0, 0, 1); position: absolute; font-size: 12px; font-weight: 700; text-align: center; font-family: 'Noto Sans Arabic'; line-height: 23px; }
      .group1-thq-text-elm39 { top: calc(775.17px + var(--footer-offset)); left: 55px; color: rgba(0, 0, 0, 1); position: absolute; font-size: 12px; font-weight: 700; text-align: left; font-family: 'Times New Roman', serif; font-weight: bold; }
      .group1-thq-text-elm40 { top: calc(798.91px + var(--footer-offset)); left: 108.35px; color: rgba(20, 0, 255, 1); position: absolute; font-size: 11px; font-weight: 700; text-align: left; text-decoration: underline; pointer-events: auto; font-family: 'Times New Roman', serif; font-weight: bold; }

      .placeholder-136 {
        position: absolute; top: 620px; left: 122px; width: 136px; height: 136px; display: flex; align-items: center; justify-content: center; font-size: 12px; pointer-events: auto;
      }

      .vertical-divider {
        position: absolute; top: 735px; left: 436px; width: 1px; height: 7cm; background-color: #dddddd; 
      }

      /* Reduced margins to bring adjacent words closer */
      .thin-slash {
        font-weight: 300; 
        font-family: 'Inter', sans-serif;
        margin: 0 3px; 
        display: inline-block;
      }

      /* =========================================
         DOWNLOAD BUTTON STYLES
         ========================================= */
      .controls {
        position: fixed; bottom: 30px; right: 30px; display: flex; gap: 15px; z-index: 1000;
      }
      .download-btn {
        background-color: #306db5; color: white; padding: 14px 28px; border-radius: 10px; border: none; font-size: 16px; font-weight: 600; cursor: pointer; box-shadow: 0px 6px 15px rgba(0,0,0,0.3); font-family: 'Inter', sans-serif; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      }
      .download-btn:hover { background-color: #2c3e77; transform: translateY(-3px); box-shadow: 0px 8px 20px rgba(0,0,0,0.4); }
      .download-btn:active { transform: translateY(-1px); }

      /* =========================================
         PRINT SPECIFIC CSS (THE MAGIC FOR PDF)
         ========================================= */
      @media print {
        @page {
          size: 842.25px 1190.25px; 
          margin: 0;
        }
        body {
          -webkit-print-color-adjust: exact !important;
          print-color-adjust: exact !important;
          background: white !important;
        }
        .controls {
          display: none !important; 
        }
        .group1-container1 {
          padding: 0 !important;
          background-color: transparent !important;
        }
        .group1-thq-group1-elm {
          box-shadow: none !important;
          margin: 0 !important;
          /* Ensure exact scaling */
          transform: scale(1);
          transform-origin: top left;
        }
        /* Ensure links are preserved and styled for print */
        a {
            color: rgba(20, 0, 255, 1) !important;
            text-decoration: underline !important;
        }
      }
    </style>
  </head>
  <body>
    <!-- Floating Download Button triggering Browser Print -->
    <div class="controls">
        <button class="download-btn" onclick="window.print()">تحميل ملف PDF (جودة كانفا)</button>
    </div>

    <div class="group1-container1">
      <div class="group1-thq-group1-elm" id="report-content">
        
        <!-- Side Placeholders -->
        <div class="top-right-placeholder">
          <img src="sehalogoright.svg" alt="Logo Placeholder" style="width: 100%; height: 100%;" />
        </div>

        <div class="top-left-placeholder">
          <img src="sehalogoleft.svg" alt="Logo Placeholder" style="width: 100%; height: 100%;" />
        </div>

        <!-- Added Bottom Right Placeholder -->
        <div class="bottom-right-placeholder">
          <img src="bottomright.svg" alt="Signature Placeholder" style="width: 100%; height: 100%;" />
        </div>

        <!-- Headers -->
        <div class="group1-thq-staticinfo-elm">
          <!-- Placeholder above Kingdom text -->
          <div class="header-placeholder">
            <img src="header.svg" alt="Header Placeholder" style="width: 100%; height: 100%;" />
          </div>

          <span class="group1-thq-text-elm41">
            <span style="font-size: 22.5px; font-family: 'Noto sans arabic', serif; font-weight: 700; color: #306db5;">تقرير إجازة مرضية</span><br />
            <span style="font-size: 18.7px; font-family: 'Times New Roman', serif; font-weight: 700; color: #2c3e77;">Sick Leave Report</span>
          </span>
          <span class="group1-thq-text-elm44">Kingdom of Saudi Arabia</span>

          <!-- QR Code Placeholder -->
          <div class="placeholder-136">
            <img src="qr.svg" alt="QR Code" style="width: 130px; height: 130px;" />
          </div>

          <!-- Verification Text Footer -->
          <span class="group1-thq-text-elm36" dir="rtl">للتحقق من بيانات التقرير يرجى التأكد من زيارة موقع منصة صحة<br />الرسمي</span>
          <span class="group1-thq-text-elm39">To check the report please visit Seha's official website</span>
          <!-- THE CLICKABLE LINK -->
          <span class="group1-thq-text-elm40"><a href="https://seha-sa-iniquiries-slenquiry.up.railway.app/" target="_blank">www.seha.sa/#/inquiries/slenquiry</a></span>
        </div>

        <table class="info-table" cellpadding="0" cellspacing="0">
          <tbody>
            <tr>
              <td class="en-title">Leave ID</td>
              <td class="data-cell" colspan="2"><?= $service_code ?></td>
              <td class="ar-title">رمز الإجازة</td>
            </tr>
            
            <tr class="blue-row">
              <td class="en-title" style="color: white;">Leave Duration</td>
              <td class="data-cell"><?= $duration_en ?></td>
              <td class="data-cell ar-text" dir="rtl">
                <span style="font-family: 'Times New Roman', serif; font-size: 14.5px; font-weight: 400;"><?= $days_ar_num ?></span> 
                <span style="font-family: 'Noto Sans Arabic', sans-serif; font-size: 14.5px; font-weight: 400;"><?= $days_ar_word ?></span>
                <?= $duration_ar_dates ?>
              </td>
              <td class="ar-title" style="color: white;">مدة الإجازة</td>
            </tr>

            <tr>
              <td class="en-title">Admission Date</td>
              <td class="data-cell date-cell"><?= $admission_dmy ?></td>
              <td class="data-cell date-cell"><?= $admission_hijri ?></td>
              <td class="ar-title">تاريخ الدخول</td>
            </tr>

            <tr class="gray-row">
              <td class="en-title">Discharge Date</td>
              <td class="data-cell date-cell"><?= $discharge_dmy ?></td>
              <td class="data-cell date-cell"><?= $discharge_hijri ?></td>
              <td class="ar-title">تاريخ الخروج</td>
            </tr>

            <tr>
              <td class="en-title">Issue Date</td>
              <td class="data-cell" colspan="2"><?= $issue_dmy ?></td>
              <td class="ar-title">تاريخ الإصدار</td>
            </tr>

            <tr class="gray-row">
              <td class="en-title">Patient Name</td>
              <td class="data-cell en-spaced"><?= $p_name_en ?></td>
              <td class="data-cell ar-text"><?= $p_name_ar ?></td>
              <td class="ar-title">الاسم</td>
            </tr>

            <tr>
              <td class="en-title">National ID / Iqama</td>
              <td class="data-cell" colspan="2"><?= $p_id_num ?></td>
              <td class="ar-title">رقم الهوية<span class="thin-slash">/</span>الإقامة</td>
            </tr>

            <tr class="gray-row">
              <td class="en-title">Nationality</td>
              <td class="data-cell en-spaced"><?= $p_nat_en ?></td>
              <td class="data-cell ar-text"><?= $p_nat_ar ?></td>
              <td class="ar-title">الجنسية</td>
            </tr>

            <tr>
              <td class="en-title">Employer</td>
              <td class="data-cell en-spaced"><?= $p_emp_en ?></td>
              <td class="data-cell ar-text"><?= $p_emp_ar ?></td>
              <td class="ar-title">جهة العمل</td>
            </tr>

            <tr class="gray-row">
              <td class="en-title">Physician Name</td>
              <td class="data-cell en-spaced"><?= $d_name_en ?></td>
              <td class="data-cell ar-text"><?= $d_name_ar ?></td>
              <td class="ar-title">اسم الطبيب المعالج</td>
            </tr>

            <tr>
              <td class="en-title">Position</td>
              <td class="data-cell en-spaced"><?= $d_title_en ?></td>
              <td class="data-cell ar-text"><?= $d_title_ar ?></td>
              <td class="ar-title">المسمى الوظيفي</td>
            </tr>
          </tbody>
        </table>

        <!-- Vertical Divider Line -->
        <div class="vertical-divider"></div>

        <div class="group1-thq-hospitallogoandthename-elm">
          <div class="placeholder-logo-hospital">
            <img src="<?= e($logo_src) ?>" alt="Hospital Logo" style="width: 120px; height: 120px;" />
          </div>
          <span class="group1-thq-text-elm18">
            <span style="font-family: 'Noto Sans Arabic', sans-serif; font-weight: 700;"><?= $h_name_ar ?></span><br />
            <span class="en-spaced" style="font-family: 'Times New Roman', serif; font-weight: 700;"><?= $h_name_en ?></span><br />
            <?= $license_html ?>
          </span>
        </div>

        <!-- Issue Timestamp -->
        <div class="group1-thq-thedateofissueandalsotimeofissue-elm">
          <span class="group1-thq-text-elm22">
            <span><?= $time_str ?></span><br />
            <span><?= $date_line ?></span>
          </span>
        </div>

      </div>
    </div>

  </body>
</html>
    <?php
    exit;
}

// ======================== الصفحة الرئيسية - لوحة التحكم ========================
$page = $_GET['page'] ?? 'dashboard';

// جلب البيانات حسب الصفحة
$hospitals = $pdo->query("SELECT * FROM hospitals ORDER BY name_ar")->fetchAll();
$allDoctors = $pdo->query("SELECT d.*, h.name_ar AS h_name FROM doctors d LEFT JOIN hospitals h ON d.hospital_id=h.id ORDER BY d.name_ar")->fetchAll();
$allPatients = $pdo->query("SELECT * FROM patients ORDER BY name_ar")->fetchAll();
$allLeaves = $pdo->query("SELECT sl.*, p.name_ar AS p_name, d.name_ar AS d_name, h.name_ar AS h_name FROM sick_leaves sl LEFT JOIN patients p ON sl.patient_id=p.id LEFT JOIN doctors d ON sl.doctor_id=d.id LEFT JOIN hospitals h ON sl.hospital_id=h.id WHERE sl.deleted_at IS NULL ORDER BY sl.created_at DESC")->fetchAll();

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$lastLeaveId = $_SESSION['last_leave_id'] ?? null;
unset($_SESSION['last_leave_id']);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>لوحة إدارة الإجازات المرضية</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+Arabic:wght@300;400;500;600;700&display=swap" />
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Noto Sans Arabic',Arial,sans-serif;background:#f0f2f5;color:#333;font-size:14px}
.wrap{max-width:1300px;margin:0 auto;padding:15px}
.hdr{background:linear-gradient(135deg,#306db5,#2c3e77);color:#fff;padding:18px 25px;border-radius:10px;margin-bottom:20px;display:flex;justify-content:space-between;align-items:center}
.hdr h1{font-size:22px;font-weight:700}
.hdr small{opacity:.8;font-size:13px}
.nav{display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap}
.nav a{padding:10px 18px;background:#fff;color:#306db5;text-decoration:none;border-radius:8px;font-weight:600;font-size:13px;border:2px solid transparent;transition:.2s}
.nav a:hover{border-color:#306db5}
.nav a.active{background:#306db5;color:#fff}
.card{background:#fff;border-radius:10px;padding:20px;box-shadow:0 2px 8px rgba(0,0,0,.08);margin-bottom:20px}
.card h2{font-size:18px;color:#306db5;margin-bottom:15px;padding-bottom:10px;border-bottom:2px solid #e8e8e8}
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:15px;margin-bottom:20px}
.stat{background:#fff;border-radius:10px;padding:20px;text-align:center;box-shadow:0 2px 8px rgba(0,0,0,.08)}
.stat h3{font-size:28px;margin-bottom:5px}
.stat p{font-size:13px;color:#666}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px}
.form-group{margin-bottom:12px}
.form-group label{display:block;margin-bottom:4px;font-weight:600;color:#306db5;font-size:13px}
.form-group input,.form-group select,.form-group textarea{width:100%;padding:9px 12px;border:1px solid #ddd;border-radius:6px;font-family:inherit;font-size:13px;transition:.2s}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus{border-color:#306db5;outline:none;box-shadow:0 0 0 3px rgba(48,109,181,.1)}
.form-group textarea{resize:vertical;min-height:60px}
.btn{padding:9px 20px;background:#306db5;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:13px;font-weight:600;font-family:inherit;transition:.2s}
.btn:hover{background:#2c3e77}
.btn-sm{padding:5px 12px;font-size:12px}
.btn-danger{background:#e74c3c}
.btn-danger:hover{background:#c0392b}
.btn-success{background:#27ae60}
.btn-success:hover{background:#219a52}
.btn-print{background:#8e44ad}
.btn-print:hover{background:#7d3c98}
.alert{padding:12px 15px;border-radius:6px;margin-bottom:15px;font-size:13px}
.alert-success{background:#d4edda;color:#155724;border:1px solid #c3e6cb}
.alert-error{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb}
table{width:100%;border-collapse:collapse;margin-top:12px;font-size:13px}
table th{background:#306db5;color:#fff;padding:10px 8px;text-align:right;font-weight:600}
table td{padding:10px 8px;border-bottom:1px solid #eee}
table tr:hover{background:#f8f9fa}
.actions{display:flex;gap:5px}
.logo-preview{width:50px;height:50px;object-fit:contain;border-radius:4px;border:1px solid #eee}
.checkbox-group{display:flex;align-items:center;gap:8px;margin-bottom:12px}
.checkbox-group input[type=checkbox]{width:18px;height:18px}
.hidden{display:none}
@media(max-width:768px){.form-row{grid-template-columns:1fr}.stats{grid-template-columns:1fr 1fr}}
</style>
</head>
<body>
<div class="wrap">
    <div class="hdr">
        <div><h1>لوحة إدارة الإجازات المرضية</h1><small>Sick Leave Management System</small></div>
    </div>
    
    <?php if ($flash): ?>
        <div class="alert alert-<?= $flash[0] === 'success' ? 'success' : 'error' ?>"><?= e($flash[1]) ?></div>
    <?php endif; ?>
    
    <?php if ($lastLeaveId): ?>
        <div class="alert alert-success">
            <a href="?action=print_leave&id=<?= $lastLeaveId ?>" target="_blank" class="btn btn-print btn-sm" style="margin-left:10px">طباعة الإجازة الأخيرة PDF</a>
        </div>
    <?php endif; ?>
    
    <div class="nav">
        <a href="?page=dashboard" class="<?= $page==='dashboard'?'active':'' ?>">لوحة التحكم</a>
        <a href="?page=hospitals" class="<?= $page==='hospitals'?'active':'' ?>">المستشفيات</a>
        <a href="?page=doctors" class="<?= $page==='doctors'?'active':'' ?>">الأطباء</a>
        <a href="?page=patients" class="<?= $page==='patients'?'active':'' ?>">المرضى</a>
        <a href="?page=issue" class="<?= $page==='issue'?'active':'' ?>">إصدار إجازة</a>
        <a href="?page=leaves" class="<?= $page==='leaves'?'active':'' ?>">الإجازات</a>
    </div>

<?php if ($page === 'dashboard'): ?>
    <div class="stats">
        <div class="stat"><h3 style="color:#306db5"><?= count($hospitals) ?></h3><p>المستشفيات</p></div>
        <div class="stat"><h3 style="color:#7b1fa2"><?= count($allDoctors) ?></h3><p>الأطباء</p></div>
        <div class="stat"><h3 style="color:#388e3c"><?= count($allPatients) ?></h3><p>المرضى</p></div>
        <div class="stat"><h3 style="color:#f57c00"><?= count($allLeaves) ?></h3><p>الإجازات</p></div>
    </div>
    
    <div class="card">
        <h2>آخر الإجازات</h2>
        <table>
            <thead><tr><th>رمز الخدمة</th><th>المريض</th><th>الطبيب</th><th>المستشفى</th><th>من</th><th>إلى</th><th>أيام</th><th>إجراءات</th></tr></thead>
            <tbody>
            <?php foreach (array_slice($allLeaves, 0, 10) as $l): ?>
                <tr>
                    <td><?= e($l['service_code']) ?></td>
                    <td><?= e($l['p_name']) ?></td>
                    <td><?= e($l['d_name']) ?></td>
                    <td><?= e($l['h_name']) ?></td>
                    <td><?= e($l['start_date']) ?></td>
                    <td><?= e($l['end_date']) ?></td>
                    <td><?= $l['days_count'] ?></td>
                    <td><a href="?action=print_leave&id=<?= $l['id'] ?>" target="_blank" class="btn btn-print btn-sm">طباعة</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php elseif ($page === 'hospitals'): ?>
    <div class="card">
        <h2>إضافة مستشفى جديد</h2>
        <form method="POST" action="?action=add_hospital" enctype="multipart/form-data">
            <div class="form-row">
                <div class="form-group"><label>اسم المستشفى (عربي) *</label><input type="text" name="name_ar" required></div>
                <div class="form-group"><label>Hospital Name (English)</label><input type="text" name="name_en"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>رقم الترخيص</label><input type="text" name="license_number" placeholder="اتركه فارغاً إذا لا يوجد"></div>
                <div class="form-group"><label>نوع الخدمة (البريفكس)</label>
                    <select name="service_prefix"><option value="GSL">GSL - حكومي / مستشفى</option><option value="PSL">PSL - خاص / عيادة</option></select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>رابط الشعار (من الإنترنت)</label><input type="url" name="logo_url" placeholder="https://example.com/logo.png"></div>
                <div class="form-group"><label>أو رفع الشعار</label><input type="file" name="logo_file" accept="image/*"></div>
            </div>
            <button type="submit" class="btn">إضافة المستشفى</button>
        </form>
    </div>
    
    <div class="card">
        <h2>قائمة المستشفيات (<?= count($hospitals) ?>)</h2>
        <table>
            <thead><tr><th>الشعار</th><th>الاسم (عربي)</th><th>Name (EN)</th><th>الترخيص</th><th>البريفكس</th><th>إجراءات</th></tr></thead>
            <tbody>
            <?php foreach ($hospitals as $h): ?>
                <tr>
                    <td><?php if (!empty($h['logo_path']) && file_exists(__DIR__.'/'.$h['logo_path'])): ?><img src="<?= e($h['logo_path']) ?>" class="logo-preview"><?php else: ?>-<?php endif; ?></td>
                    <td><?= e($h['name_ar']) ?></td>
                    <td><?= e($h['name_en']) ?></td>
                    <td><?= !empty($h['license_number']) ? e($h['license_number']) : '-' ?></td>
                    <td><?= e($h['service_prefix'] ?? 'GSL') ?></td>
                    <td class="actions">
                        <a href="?action=delete_hospital&id=<?= $h['id'] ?>" onclick="return confirm('هل أنت متأكد من حذف هذا المستشفى؟')" class="btn btn-danger btn-sm">حذف</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php elseif ($page === 'doctors'): ?>
    <div class="card">
        <h2>إضافة طبيب جديد</h2>
        <form method="POST" action="?action=add_doctor">
            <div class="form-row">
                <div class="form-group"><label>اسم الطبيب (عربي) *</label><input type="text" name="name_ar" required></div>
                <div class="form-group"><label>Doctor Name (English)</label><input type="text" name="name_en"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>المسمى الوظيفي (عربي) *</label><input type="text" name="title_ar" required></div>
                <div class="form-group"><label>Title (English)</label><input type="text" name="title_en"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>المستشفى</label>
                    <select name="hospital_id"><option value="">-- بدون مستشفى --</option>
                    <?php foreach ($hospitals as $h): ?><option value="<?= $h['id'] ?>"><?= e($h['name_ar']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>ملاحظات</label><textarea name="note"></textarea></div>
            </div>
            <button type="submit" class="btn">إضافة الطبيب</button>
        </form>
    </div>
    
    <div class="card">
        <h2>قائمة الأطباء (<?= count($allDoctors) ?>)</h2>
        <table>
            <thead><tr><th>الاسم (عربي)</th><th>Name (EN)</th><th>المسمى</th><th>Title</th><th>المستشفى</th><th>إجراءات</th></tr></thead>
            <tbody>
            <?php foreach ($allDoctors as $d): ?>
                <tr>
                    <td><?= e($d['name_ar']) ?></td>
                    <td><?= e($d['name_en']) ?></td>
                    <td><?= e($d['title_ar']) ?></td>
                    <td><?= e($d['title_en']) ?></td>
                    <td><?= e($d['h_name']) ?></td>
                    <td class="actions">
                        <a href="?action=delete_doctor&id=<?= $d['id'] ?>" onclick="return confirm('هل أنت متأكد؟')" class="btn btn-danger btn-sm">حذف</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php elseif ($page === 'patients'): ?>
    <div class="card">
        <h2>إضافة مريض جديد</h2>
        <form method="POST" action="?action=add_patient">
            <div class="form-row">
                <div class="form-group"><label>اسم المريض (عربي) *</label><input type="text" name="name_ar" required></div>
                <div class="form-group"><label>Patient Name (English)</label><input type="text" name="name_en"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>رقم الهوية / الإقامة *</label><input type="text" name="identity_number" required></div>
                <div class="form-group"><label>رقم الهاتف</label><input type="tel" name="phone"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>الجنسية (عربي)</label><input type="text" name="nationality_ar" value="السعودية"></div>
                <div class="form-group"><label>Nationality (English)</label><input type="text" name="nationality_en" value="Saudi Arabia"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>جهة العمل (عربي)</label><input type="text" name="employer_ar"></div>
                <div class="form-group"><label>Employer (English)</label><input type="text" name="employer_en"></div>
            </div>
            <button type="submit" class="btn">إضافة المريض</button>
        </form>
    </div>
    
    <div class="card">
        <h2>قائمة المرضى (<?= count($allPatients) ?>)</h2>
        <table>
            <thead><tr><th>الاسم (عربي)</th><th>Name (EN)</th><th>رقم الهوية</th><th>الجنسية</th><th>جهة العمل</th><th>إجراءات</th></tr></thead>
            <tbody>
            <?php foreach ($allPatients as $p): ?>
                <tr>
                    <td><?= e($p['name_ar']) ?></td>
                    <td><?= e($p['name_en']) ?></td>
                    <td><?= e($p['identity_number']) ?></td>
                    <td><?= e($p['nationality_ar']) ?></td>
                    <td><?= e($p['employer_ar']) ?></td>
                    <td class="actions">
                        <a href="?action=delete_patient&id=<?= $p['id'] ?>" onclick="return confirm('هل أنت متأكد؟')" class="btn btn-danger btn-sm">حذف</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php elseif ($page === 'issue'): ?>
    <div class="card">
        <h2>إصدار إجازة مرضية جديدة</h2>
        <form method="POST" action="?action=issue_leave">
            <div class="form-row">
                <div class="form-group"><label>اختر المريض *</label>
                    <select name="patient_id" id="sel_patient" required>
                        <option value="">-- اختر مريض --</option>
                        <?php foreach ($allPatients as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= e($p['name_ar']) ?> - <?= e($p['identity_number']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>اختر المستشفى *</label>
                    <select name="hospital_id" id="sel_hospital" required onchange="loadDoctors()">
                        <option value="">-- اختر مستشفى --</option>
                        <?php foreach ($hospitals as $h): ?>
                        <option value="<?= $h['id'] ?>"><?= e($h['name_ar']) ?> (<?= e($h['service_prefix'] ?? 'GSL') ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group"><label>اختر الطبيب *</label>
                    <select name="doctor_id" id="sel_doctor" required>
                        <option value="">-- اختر المستشفى أولاً --</option>
                    </select>
                </div>
                <div class="form-group"><label>رمز الخدمة (اتركه فارغاً للتوليد التلقائي)</label>
                    <input type="text" name="service_code_manual" placeholder="مثال: GSL26043000781">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group"><label>تاريخ الإصدار *</label><input type="date" name="issue_date" required></div>
                <div class="form-group">
                    <div class="form-row" style="margin-bottom:0">
                        <div class="form-group"><label>الوقت *</label><input type="time" name="issue_time" value="09:00" required></div>
                        <div class="form-group"><label>صباح / مساء</label>
                            <select name="issue_period"><option value="AM">صباح (AM)</option><option value="PM">مساء (PM)</option></select>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group"><label>من تاريخ (بداية الإجازة) *</label><input type="date" name="start_date" id="start_date" required onchange="calcDays()"></div>
                <div class="form-group"><label>إلى تاريخ (نهاية الإجازة) *</label><input type="date" name="end_date" id="end_date" required onchange="calcDays()"></div>
            </div>
            
            <div id="days_display" style="margin-bottom:12px;font-weight:700;color:#306db5;font-size:15px"></div>
            
            <div class="checkbox-group">
                <input type="checkbox" name="is_companion" id="chk_companion" onchange="document.getElementById('companion_fields').classList.toggle('hidden')">
                <label for="chk_companion" style="color:#306db5;font-weight:600">مرافق</label>
            </div>
            <div id="companion_fields" class="hidden">
                <div class="form-row">
                    <div class="form-group"><label>اسم المرافق</label><input type="text" name="companion_name"></div>
                    <div class="form-group"><label>صلة القرابة</label><input type="text" name="companion_relation"></div>
                </div>
            </div>
            
            <div class="checkbox-group">
                <input type="checkbox" name="is_paid" id="chk_paid" onchange="document.getElementById('paid_fields').classList.toggle('hidden')">
                <label for="chk_paid" style="color:#306db5;font-weight:600">مدفوعة</label>
            </div>
            <div id="paid_fields" class="hidden">
                <div class="form-group"><label>المبلغ</label><input type="number" name="payment_amount" step="0.01" value="0"></div>
            </div>
            
            <button type="submit" class="btn" style="margin-top:10px;padding:12px 30px;font-size:15px">إصدار الإجازة</button>
        </form>
    </div>

<?php elseif ($page === 'leaves'): ?>
    <div class="card">
        <h2>جميع الإجازات (<?= count($allLeaves) ?>)</h2>
        <table>
            <thead><tr><th>رمز الخدمة</th><th>المريض</th><th>الطبيب</th><th>المستشفى</th><th>من</th><th>إلى</th><th>أيام</th><th>الحالة</th><th>إجراءات</th></tr></thead>
            <tbody>
            <?php foreach ($allLeaves as $l): ?>
                <tr>
                    <td><strong><?= e($l['service_code']) ?></strong></td>
                    <td><?= e($l['p_name']) ?></td>
                    <td><?= e($l['d_name']) ?></td>
                    <td><?= e($l['h_name']) ?></td>
                    <td><?= e($l['start_date']) ?></td>
                    <td><?= e($l['end_date']) ?></td>
                    <td><?= $l['days_count'] ?></td>
                    <td><?= $l['is_paid'] ? '<span style="color:green">مدفوعة</span>' : '<span style="color:red">غير مدفوعة</span>' ?></td>
                    <td class="actions">
                        <a href="?action=print_leave&id=<?= $l['id'] ?>" target="_blank" class="btn btn-print btn-sm">طباعة PDF</a>
                        <a href="?action=delete_leave&id=<?= $l['id'] ?>" onclick="return confirm('هل أنت متأكد من حذف هذه الإجازة؟')" class="btn btn-danger btn-sm">حذف</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php endif; ?>

</div>

<script>
function loadDoctors() {
    const hid = document.getElementById('sel_hospital').value;
    const sel = document.getElementById('sel_doctor');
    sel.innerHTML = '<option value="">جاري التحميل...</option>';
    if (!hid) { sel.innerHTML = '<option value="">-- اختر المستشفى أولاً --</option>'; return; }
    fetch('?action=api_get_doctors&hospital_id=' + hid)
        .then(r => r.json())
        .then(data => {
            sel.innerHTML = '<option value="">-- اختر طبيب --</option>';
            (data.doctors || []).forEach(d => {
                const o = document.createElement('option');
                o.value = d.id;
                o.textContent = d.name_ar + (d.title_ar ? ' - ' + d.title_ar : '');
                sel.appendChild(o);
            });
        })
        .catch(() => { sel.innerHTML = '<option value="">خطأ في التحميل</option>'; });
}

function calcDays() {
    const s = document.getElementById('start_date').value;
    const e = document.getElementById('end_date').value;
    const disp = document.getElementById('days_display');
    if (s && e) {
        const sd = new Date(s), ed = new Date(e);
        if (ed >= sd) {
            const diff = Math.round((ed - sd) / (1000*60*60*24)) + 1;
            const dayWord = diff === 1 ? 'day' : 'days';
            disp.textContent = 'عدد الأيام: ' + diff + ' ' + dayWord;
        } else {
            disp.textContent = 'تاريخ النهاية يجب أن يكون بعد البداية';
            disp.style.color = '#e74c3c';
            return;
        }
        disp.style.color = '#306db5';
    } else {
        disp.textContent = '';
    }
}
</script>
</body>
</html>
