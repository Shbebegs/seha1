<?php
/**
 * لوحة الإدارة المتقدمة - Admin Panel
 * إدارة المرضى والمستشفيات والأطباء والإجازات
 * مع توليد PDF احترافي
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
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    die('فشل الاتصال بقاعدة البيانات: ' . $e->getMessage());
}

$pdo->exec("SET time_zone = '+03:00'");

// ======================== دالة إضافة الأعمدة ========================
function ensureColumn($pdo, $table, $column, $definition) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    $stmt->execute([$table, $column]);
    if ((int)$stmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE $table ADD COLUMN $column $definition");
    }
}

// ======================== إنشاء الجداول ========================
$pdo->exec("CREATE TABLE IF NOT EXISTS hospitals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name_ar VARCHAR(200) NOT NULL,
    name_en VARCHAR(200) NOT NULL,
    license_number VARCHAR(50) NULL,
    logo_path VARCHAR(500) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS doctors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name_ar VARCHAR(150) NOT NULL,
    name_en VARCHAR(150) NULL,
    title_ar VARCHAR(150) NOT NULL,
    title_en VARCHAR(150) NULL,
    hospital_id INT NULL,
    note TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_doctors_hospital FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name_ar VARCHAR(150) NOT NULL,
    name_en VARCHAR(150) NULL,
    identity_number VARCHAR(50) NOT NULL UNIQUE,
    phone VARCHAR(30) NULL,
    employer_ar VARCHAR(200) NULL,
    employer_en VARCHAR(200) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$pdo->exec("CREATE TABLE IF NOT EXISTS sick_leaves (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_code VARCHAR(50) NOT NULL UNIQUE,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    hospital_id INT NULL,
    issue_date DATE NOT NULL,
    issue_time VARCHAR(10) NOT NULL,
    issue_period VARCHAR(10) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    days_count INT NOT NULL,
    is_companion TINYINT(1) DEFAULT 0,
    companion_name VARCHAR(150) NULL,
    companion_relation VARCHAR(150) NULL,
    is_paid TINYINT(1) DEFAULT 0,
    payment_amount DECIMAL(10,2) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_sick_leaves_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE RESTRICT,
    CONSTRAINT fk_sick_leaves_doctor FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE RESTRICT,
    CONSTRAINT fk_sick_leaves_hospital FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// إضافة الأعمدة الناقصة للجداول الموجودة
ensureColumn($pdo, 'patients', 'name_ar', "VARCHAR(150) NULL");
ensureColumn($pdo, 'patients', 'name_en', "VARCHAR(150) NULL");
ensureColumn($pdo, 'patients', 'employer_ar', "VARCHAR(200) NULL");
ensureColumn($pdo, 'patients', 'employer_en', "VARCHAR(200) NULL");
ensureColumn($pdo, 'doctors', 'name_ar', "VARCHAR(150) NULL");
ensureColumn($pdo, 'doctors', 'name_en', "VARCHAR(150) NULL");
ensureColumn($pdo, 'doctors', 'title_ar', "VARCHAR(150) NULL");
ensureColumn($pdo, 'doctors', 'title_en', "VARCHAR(150) NULL");
ensureColumn($pdo, 'doctors', 'hospital_id', "INT NULL");

// ======================== دوال مساعدة ========================
function gregorianToHijri($gregorian_date) {
    $date = DateTime::createFromFormat('Y-m-d', $gregorian_date);
    if (!$date) return null;
    
    $year = $date->format('Y');
    $month = $date->format('m');
    $day = $date->format('d');
    
    $gy = $year;
    $gm = $month;
    $gd = $day;
    
    $g_d_n = 365 * $gy + intval(($gy + 3) / 4) - intval(($gy + 99) / 100) + intval(($gy + 399) / 400);
    
    for ($i = 1; $i < $gm; ++$i) {
        $g_d_n += [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31][$i - 1];
    }
    
    if ($gm > 2 && (($gy % 4 == 0 && $gy % 100 != 0) || ($gy % 400 == 0))) {
        ++$g_d_n;
    }
    
    $g_d_n += $gd;
    
    $j_d_n = intval(($g_d_n - 1948440) * 10631 / 30670);
    $jy = 1 + 30 * $j_d_n + intval(($j_d_n % 10447) / 3030);
    
    $jp = ($g_d_n - 1) % 10631;
    $jm = 1 + intval(($jp % 325 + 1) / 30.6001);
    $jd = 1 + (($jp % 325) % 30);
    
    if ($jm > 12) $jm = 12;
    if ($jd > 30) $jd = 30;
    
    return str_pad($jd, 2, '0', STR_PAD_LEFT) . '-' . str_pad($jm, 2, '0', STR_PAD_LEFT) . '-' . $jy;
}

function getArabicMonthName($month) {
    $months = [
        'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو',
        'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'
    ];
    return $months[intval($month) - 1] ?? '';
}

function getArabicDayName($day) {
    $days = ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
    $date = DateTime::createFromFormat('Y-m-d', $day);
    if (!$date) return '';
    return $days[$date->format('w')];
}

function generateServiceCode($pdo, $prefix, $issueDate) {
    $prefix = strtoupper(trim($prefix));
    if (!in_array($prefix, ['GSL', 'PSL'])) {
        $prefix = 'GSL';
    }
    
    $issueDateObj = DateTime::createFromFormat('Y-m-d', (string)$issueDate, new DateTimeZone('Asia/Riyadh'));
    if (!$issueDateObj) {
        $issueDateObj = new DateTime('now', new DateTimeZone('Asia/Riyadh'));
    }
    $datePart = $issueDateObj->format('ymd');
    
    $stmt = $pdo->query("SELECT service_code FROM sick_leaves ORDER BY id DESC LIMIT 1");
    $last = $stmt->fetchColumn();
    $num = 1;
    if ($last && preg_match('/^(?:GSL|PSL)\d{6}(\d+)$/', $last, $m)) {
        $num = intval($m[1]) + 1;
    }
    
    return $prefix . $datePart . str_pad((string)$num, 5, '0', STR_PAD_LEFT);
}

function uploadHospitalLogo($file) {
    if (empty($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return null;
    }
    
    $tmp = $file['tmp_name'] ?? '';
    if (!$tmp || !is_uploaded_file($tmp)) {
        return null;
    }
    
    $dir = __DIR__ . '/hospital_logos';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    
    $ext = pathinfo($file['name'] ?? '', PATHINFO_EXTENSION);
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'svg'];
    if (!in_array(strtolower($ext), $allowed)) {
        return null;
    }
    
    $fileName = 'hospital_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = $dir . '/' . $fileName;
    
    if (move_uploaded_file($tmp, $dest)) {
        return 'hospital_logos/' . $fileName;
    }
    
    return null;
}

function downloadLogoFromUrl($url) {
    if (empty($url)) return null;
    
    $dir = __DIR__ . '/hospital_logos';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    
    $ext = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
    if (empty($ext)) $ext = 'png';
    
    $fileName = 'hospital_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $filepath = $dir . '/' . $fileName;
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $data = curl_exec($ch);
    curl_close($ch);
    
    if ($data && file_put_contents($filepath, $data)) {
        return 'hospital_logos/' . $fileName;
    }
    
    return null;
}

// ======================== معالجة الطلبات ========================
$action = $_GET['action'] ?? $_POST['action'] ?? 'dashboard';

if ($action === 'api_get_doctors_by_hospital') {
    $hospital_id = intval($_GET['hospital_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT id, name_ar, name_en, title_ar, title_en FROM doctors WHERE hospital_id = ? ORDER BY name_ar");
    $stmt->execute([$hospital_id]);
    $doctors = $stmt->fetchAll();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'doctors' => $doctors]);
    exit;
}

if ($action === 'api_get_patient_data') {
    $patient_id = intval($_GET['patient_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
    $stmt->execute([$patient_id]);
    $patient = $stmt->fetch();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'patient' => $patient]);
    exit;
}

if ($action === 'add_hospital') {
    $name_ar = trim($_POST['name_ar'] ?? '');
    $name_en = trim($_POST['name_en'] ?? '');
    $license_number = trim($_POST['license_number'] ?? '');
    $logo_url = trim($_POST['logo_url'] ?? '');
    
    if (empty($name_ar) || empty($name_en)) {
        $_SESSION['error'] = 'يجب إدخال اسم المستشفى بالعربية والإنجليزية';
    } else {
        $logo_path = null;
        if (!empty($logo_url)) {
            $logo_path = downloadLogoFromUrl($logo_url);
        } elseif (!empty($_FILES['logo_file']['name'])) {
            $logo_path = uploadHospitalLogo($_FILES['logo_file']);
        }
        
        $stmt = $pdo->prepare("INSERT INTO hospitals (name_ar, name_en, license_number, logo_path) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name_ar, $name_en, $license_number ?: null, $logo_path]);
        $_SESSION['success'] = 'تم إضافة المستشفى بنجاح';
    }
    header('Location: ?action=hospitals');
    exit;
}

if ($action === 'add_doctor') {
    $name_ar = trim($_POST['name_ar'] ?? '');
    $name_en = trim($_POST['name_en'] ?? '');
    $title_ar = trim($_POST['title_ar'] ?? '');
    $title_en = trim($_POST['title_en'] ?? '');
    $hospital_id = intval($_POST['hospital_id'] ?? 0) ?: null;
    $note = trim($_POST['note'] ?? '');
    
    if (empty($name_ar) || empty($title_ar)) {
        $_SESSION['error'] = 'يجب إدخال اسم الطبيب والمسمى الوظيفي بالعربية';
    } else {
        $stmt = $pdo->prepare("INSERT INTO doctors (name_ar, name_en, title_ar, title_en, hospital_id, note) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name_ar, $name_en ?: null, $title_ar, $title_en ?: null, $hospital_id, $note ?: null]);
        $_SESSION['success'] = 'تم إضافة الطبيب بنجاح';
    }
    header('Location: ?action=doctors');
    exit;
}

if ($action === 'add_patient') {
    $name_ar = trim($_POST['name_ar'] ?? '');
    $name_en = trim($_POST['name_en'] ?? '');
    $identity_number = trim($_POST['identity_number'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $employer_ar = trim($_POST['employer_ar'] ?? '');
    $employer_en = trim($_POST['employer_en'] ?? '');
    
    if (empty($name_ar) || empty($identity_number)) {
        $_SESSION['error'] = 'يجب إدخال اسم المريض ورقم الهوية';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO patients (name_ar, name_en, identity_number, phone, employer_ar, employer_en) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name_ar, $name_en ?: null, $identity_number, $phone ?: null, $employer_ar ?: null, $employer_en ?: null]);
            $_SESSION['success'] = 'تم إضافة المريض بنجاح';
        } catch (PDOException $e) {
            $_SESSION['error'] = 'رقم الهوية موجود بالفعل';
        }
    }
    header('Location: ?action=patients');
    exit;
}

if ($action === 'issue_leave') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $patient_id = intval($_POST['patient_id'] ?? 0);
        $doctor_id = intval($_POST['doctor_id'] ?? 0);
        $hospital_id = intval($_POST['hospital_id'] ?? 0) ?: null;
        $issue_date = $_POST['issue_date'] ?? '';
        $issue_time = $_POST['issue_time'] ?? '09:00';
        $issue_period = $_POST['issue_period'] ?? 'AM';
        $start_date = $_POST['start_date'] ?? '';
        $end_date = $_POST['end_date'] ?? '';
        $is_companion = isset($_POST['is_companion']) ? 1 : 0;
        $companion_name = trim($_POST['companion_name'] ?? '');
        $companion_relation = trim($_POST['companion_relation'] ?? '');
        $is_paid = isset($_POST['is_paid']) ? 1 : 0;
        $payment_amount = floatval($_POST['payment_amount'] ?? 0);
        
        if ($patient_id <= 0 || $doctor_id <= 0 || empty($issue_date) || empty($start_date) || empty($end_date)) {
            $_SESSION['error'] = 'يجب تعبئة جميع الحقول المطلوبة';
        } else {
            $start_dt = DateTime::createFromFormat('Y-m-d', $start_date);
            $end_dt = DateTime::createFromFormat('Y-m-d', $end_date);
            if (!$start_dt || !$end_dt) {
                $_SESSION['error'] = 'التواريخ غير صحيحة';
            } else {
                $interval = $start_dt->diff($end_dt);
                $days_count = $interval->days + 1;
                
                $service_code = generateServiceCode($pdo, 'GSL', $issue_date);
                
                $stmt = $pdo->prepare("INSERT INTO sick_leaves (service_code, patient_id, doctor_id, hospital_id, issue_date, issue_time, issue_period, start_date, end_date, days_count, is_companion, companion_name, companion_relation, is_paid, payment_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$service_code, $patient_id, $doctor_id, $hospital_id, $issue_date, $issue_time, $issue_period, $start_date, $end_date, $days_count, $is_companion, $companion_name ?: null, $companion_relation ?: null, $is_paid, $payment_amount]);
                
                $_SESSION['success'] = 'تم إصدار الإجازة بنجاح برمز: ' . $service_code;
                $_SESSION['last_leave_id'] = $pdo->lastInsertId();
                header('Location: ?action=issue_leave');
                exit;
            }
        }
    }
}

if ($action === 'generate_pdf') {
    $leave_id = intval($_GET['leave_id'] ?? 0);
    if ($leave_id <= 0) {
        $_SESSION['error'] = 'معرف الإجازة غير صحيح';
        header('Location: ?action=leaves');
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT sl.*, p.name_ar as patient_name_ar, p.name_en as patient_name_en, p.identity_number, p.employer_ar, p.employer_en, d.name_ar as doctor_name_ar, d.name_en as doctor_name_en, d.title_ar as doctor_title_ar, d.title_en as doctor_title_en, h.name_ar as hospital_name_ar, h.name_en as hospital_name_en, h.logo_path FROM sick_leaves sl LEFT JOIN patients p ON sl.patient_id = p.id LEFT JOIN doctors d ON sl.doctor_id = d.id LEFT JOIN hospitals h ON sl.hospital_id = h.id WHERE sl.id = ?");
    $stmt->execute([$leave_id]);
    $leave = $stmt->fetch();
    
    if (!$leave) {
        $_SESSION['error'] = 'الإجازة غير موجودة';
        header('Location: ?action=leaves');
        exit;
    }
    
    // توليد HTML للـ PDF
    $hijri_date = gregorianToHijri($leave['issue_date']);
    $day_name = getArabicDayName($leave['issue_date']);
    $month_name = getArabicMonthName(date('m', strtotime($leave['issue_date'])));
    $year = date('Y', strtotime($leave['issue_date']));
    
    $start_hijri = gregorianToHijri($leave['start_date']);
    $end_hijri = gregorianToHijri($leave['end_date']);
    
    $days_text = $leave['days_count'] == 1 ? "1 day" : $leave['days_count'] . " days";
    
    $logo_html = '';
    if (!empty($leave['logo_path']) && file_exists(__DIR__ . '/' . $leave['logo_path'])) {
        $logo_html = '<img src="' . $leave['logo_path'] . '" alt="Hospital Logo" style="width: 100%; height: 100%;" />';
    } else {
        $logo_html = '<img src="sehalogoright.svg" alt="Default Logo" style="width: 100%; height: 100%;" />';
    }
    
    $html = <<<HTML
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <title>تقرير إجازة مرضية</title>
    <style>
        body { font-family: 'Noto Sans Arabic', sans-serif; margin: 0; padding: 20px; }
        .container { width: 842px; margin: 0 auto; background: white; padding: 20px; }
        .header { text-align: center; margin-bottom: 30px; }
        .logo { width: 150px; height: 150px; margin: 0 auto; }
        .info-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .info-table td { border: 1px solid #ccc; padding: 10px; text-align: center; }
        .info-table .label { background-color: #f0f0f0; font-weight: bold; width: 25%; }
        .footer { margin-top: 40px; text-align: center; }
        @media print { body { margin: 0; padding: 0; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>تقرير إجازة مرضية</h1>
        </div>
        
        <div class="logo">
            $logo_html
        </div>
        
        <table class="info-table">
            <tr>
                <td class="label">اسم المريض</td>
                <td>{$leave['patient_name_ar']}</td>
                <td class="label">Patient Name</td>
                <td>{$leave['patient_name_en']}</td>
            </tr>
            <tr>
                <td class="label">رقم الهوية</td>
                <td>{$leave['identity_number']}</td>
                <td class="label">جهة العمل</td>
                <td>{$leave['employer_ar']}</td>
            </tr>
            <tr>
                <td class="label">اسم الطبيب</td>
                <td>{$leave['doctor_name_ar']}</td>
                <td class="label">Doctor Name</td>
                <td>{$leave['doctor_name_en']}</td>
            </tr>
            <tr>
                <td class="label">المسمى الوظيفي</td>
                <td>{$leave['doctor_title_ar']}</td>
                <td class="label">Title</td>
                <td>{$leave['doctor_title_en']}</td>
            </tr>
            <tr>
                <td class="label">المستشفى</td>
                <td>{$leave['hospital_name_ar']}</td>
                <td class="label">Hospital</td>
                <td>{$leave['hospital_name_en']}</td>
            </tr>
            <tr>
                <td class="label">تاريخ الإصدار</td>
                <td>$day_name $month_name $year</td>
                <td class="label">التاريخ الهجري</td>
                <td>$hijri_date</td>
            </tr>
            <tr>
                <td class="label">من تاريخ</td>
                <td>{$leave['start_date']}</td>
                <td class="label">إلى تاريخ</td>
                <td>{$leave['end_date']}</td>
            </tr>
            <tr>
                <td class="label">عدد الأيام</td>
                <td>$days_text</td>
                <td class="label">الوقت</td>
                <td>{$leave['issue_time']} {$leave['issue_period']}</td>
            </tr>
        </table>
        
        <div class="footer">
            <p>رمز الخدمة: {$leave['service_code']}</p>
        </div>
    </div>
    
    <script>
        window.print();
    </script>
</body>
</html>
HTML;
    
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
    exit;
}

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة الإدارة - Sick Leave Management</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Noto Sans Arabic', Arial, sans-serif; background: #f5f5f5; color: #333; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .header { background: #306db5; color: white; padding: 20px; border-radius: 8px; margin-bottom: 30px; }
        .header h1 { font-size: 28px; margin-bottom: 5px; }
        .nav { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
        .nav a, .nav button { padding: 10px 20px; background: #306db5; color: white; text-decoration: none; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; }
        .nav a:hover, .nav button:hover { background: #2c3e77; }
        .nav a.active { background: #2c3e77; }
        .content { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; color: #306db5; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-family: inherit; font-size: 14px; }
        .form-group textarea { resize: vertical; min-height: 100px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .btn { padding: 10px 20px; background: #306db5; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; font-weight: 600; }
        .btn:hover { background: #2c3e77; }
        .btn-danger { background: #e74c3c; }
        .btn-danger:hover { background: #c0392b; }
        .alert { padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        table th { background: #306db5; color: white; padding: 12px; text-align: right; }
        table td { padding: 12px; border-bottom: 1px solid #ddd; }
        table tr:hover { background: #f9f9f9; }
        .action-links { display: flex; gap: 10px; }
        .action-links a { padding: 5px 10px; background: #306db5; color: white; text-decoration: none; border-radius: 3px; font-size: 12px; }
        .action-links a:hover { background: #2c3e77; }
        .hidden { display: none; }
        .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-top: 20px; }
        .stat-card { background: #e3f2fd; padding: 20px; border-radius: 8px; text-align: center; }
        .stat-card h3 { color: #306db5; font-size: 32px; margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>لوحة إدارة الإجازات المرضية</h1>
            <p>Sick Leave Management System</p>
        </div>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error"><?= htmlspecialchars($_SESSION['error']) ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <div class="nav">
            <a href="?action=dashboard" class="<?= $action === 'dashboard' ? 'active' : '' ?>">لوحة التحكم</a>
            <a href="?action=hospitals" class="<?= $action === 'hospitals' ? 'active' : '' ?>">المستشفيات</a>
            <a href="?action=doctors" class="<?= $action === 'doctors' ? 'active' : '' ?>">الأطباء</a>
            <a href="?action=patients" class="<?= $action === 'patients' ? 'active' : '' ?>">المرضى</a>
            <a href="?action=issue_leave" class="<?= $action === 'issue_leave' ? 'active' : '' ?>">إصدار إجازة</a>
            <a href="?action=leaves" class="<?= $action === 'leaves' ? 'active' : '' ?>">الإجازات</a>
        </div>
        
        <div class="content">
            <?php
            switch ($action) {
                case 'dashboard':
                    $hospitals_count = $pdo->query("SELECT COUNT(*) FROM hospitals")->fetchColumn();
                    $doctors_count = $pdo->query("SELECT COUNT(*) FROM doctors")->fetchColumn();
                    $patients_count = $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();
                    $leaves_count = $pdo->query("SELECT COUNT(*) FROM sick_leaves")->fetchColumn();
                    ?>
                    <h2>لوحة التحكم</h2>
                    <div class="stats">
                        <div class="stat-card">
                            <h3><?= $hospitals_count ?></h3>
                            <p>المستشفيات</p>
                        </div>
                        <div class="stat-card" style="background: #f3e5f5;">
                            <h3 style="color: #7b1fa2;"><?= $doctors_count ?></h3>
                            <p>الأطباء</p>
                        </div>
                        <div class="stat-card" style="background: #e8f5e9;">
                            <h3 style="color: #388e3c;"><?= $patients_count ?></h3>
                            <p>المرضى</p>
                        </div>
                        <div class="stat-card" style="background: #fff3e0;">
                            <h3 style="color: #f57c00;"><?= $leaves_count ?></h3>
                            <p>الإجازات</p>
                        </div>
                    </div>
                    <?php
                    break;
                
                case 'hospitals':
                    $hospitals = $pdo->query("SELECT * FROM hospitals ORDER BY created_at DESC")->fetchAll();
                    ?>
                    <h2>إدارة المستشفيات</h2>
                    <form method="POST" action="?action=add_hospital" enctype="multipart/form-data" style="margin-bottom: 30px;">
                        <div class="form-row">
                            <div class="form-group">
                                <label>اسم المستشفى (عربي)</label>
                                <input type="text" name="name_ar" required>
                            </div>
                            <div class="form-group">
                                <label>Hospital Name (English)</label>
                                <input type="text" name="name_en" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>رقم الترخيص</label>
                                <input type="text" name="license_number">
                            </div>
                            <div class="form-group">
                                <label>رابط الشعار</label>
                                <input type="url" name="logo_url" placeholder="https://example.com/logo.png">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>أو رفع الشعار</label>
                            <input type="file" name="logo_file" accept="image/*">
                        </div>
                        <button type="submit" class="btn">إضافة المستشفى</button>
                    </form>
                    
                    <table>
                        <thead>
                            <tr>
                                <th>اسم المستشفى (عربي)</th>
                                <th>Hospital Name</th>
                                <th>رقم الترخيص</th>
                                <th>الشعار</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($hospitals as $h): ?>
                            <tr>
                                <td><?= htmlspecialchars($h['name_ar']) ?></td>
                                <td><?= htmlspecialchars($h['name_en']) ?></td>
                                <td><?= htmlspecialchars($h['license_number'] ?? '-') ?></td>
                                <td><?= !empty($h['logo_path']) ? '✓' : '-' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php
                    break;
                
                case 'doctors':
                    $hospitals = $pdo->query("SELECT id, name_ar, name_en FROM hospitals ORDER BY name_ar")->fetchAll();
                    $doctors = $pdo->query("SELECT d.*, h.name_ar as hospital_name FROM doctors d LEFT JOIN hospitals h ON d.hospital_id = h.id ORDER BY d.created_at DESC")->fetchAll();
                    ?>
                    <h2>إدارة الأطباء</h2>
                    <form method="POST" action="?action=add_doctor" style="margin-bottom: 30px;">
                        <div class="form-row">
                            <div class="form-group">
                                <label>اسم الطبيب (عربي)</label>
                                <input type="text" name="name_ar" required>
                            </div>
                            <div class="form-group">
                                <label>Doctor Name (English)</label>
                                <input type="text" name="name_en">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>المسمى الوظيفي (عربي)</label>
                                <input type="text" name="title_ar" required>
                            </div>
                            <div class="form-group">
                                <label>Title (English)</label>
                                <input type="text" name="title_en">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>المستشفى</label>
                                <select name="hospital_id">
                                    <option value="">-- اختر مستشفى --</option>
                                    <?php foreach ($hospitals as $h): ?>
                                    <option value="<?= $h['id'] ?>"><?= htmlspecialchars($h['name_ar']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>ملاحظات</label>
                                <textarea name="note"></textarea>
                            </div>
                        </div>
                        <button type="submit" class="btn">إضافة الطبيب</button>
                    </form>
                    
                    <table>
                        <thead>
                            <tr>
                                <th>اسم الطبيب</th>
                                <th>Doctor Name</th>
                                <th>المسمى الوظيفي</th>
                                <th>المستشفى</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($doctors as $d): ?>
                            <tr>
                                <td><?= htmlspecialchars($d['name_ar']) ?></td>
                                <td><?= htmlspecialchars($d['name_en'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($d['title_ar']) ?></td>
                                <td><?= htmlspecialchars($d['hospital_name'] ?? '-') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php
                    break;
                
                case 'patients':
                    $patients = $pdo->query("SELECT * FROM patients ORDER BY created_at DESC")->fetchAll();
                    ?>
                    <h2>إدارة المرضى</h2>
                    <form method="POST" action="?action=add_patient" style="margin-bottom: 30px;">
                        <div class="form-row">
                            <div class="form-group">
                                <label>اسم المريض (عربي)</label>
                                <input type="text" name="name_ar" required>
                            </div>
                            <div class="form-group">
                                <label>Patient Name (English)</label>
                                <input type="text" name="name_en">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>رقم الهوية</label>
                                <input type="text" name="identity_number" required>
                            </div>
                            <div class="form-group">
                                <label>رقم الهاتف</label>
                                <input type="tel" name="phone">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>جهة العمل (عربي)</label>
                                <input type="text" name="employer_ar">
                            </div>
                            <div class="form-group">
                                <label>Employer (English)</label>
                                <input type="text" name="employer_en">
                            </div>
                        </div>
                        <button type="submit" class="btn">إضافة المريض</button>
                    </form>
                    
                    <table>
                        <thead>
                            <tr>
                                <th>اسم المريض</th>
                                <th>Patient Name</th>
                                <th>رقم الهوية</th>
                                <th>جهة العمل</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($patients as $p): ?>
                            <tr>
                                <td><?= htmlspecialchars($p['name_ar']) ?></td>
                                <td><?= htmlspecialchars($p['name_en'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($p['identity_number']) ?></td>
                                <td><?= htmlspecialchars($p['employer_ar'] ?? '-') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php
                    break;
                
                case 'issue_leave':
                    $hospitals = $pdo->query("SELECT id, name_ar, name_en FROM hospitals ORDER BY name_ar")->fetchAll();
                    $patients = $pdo->query("SELECT id, name_ar, name_en, identity_number, employer_ar, employer_en FROM patients ORDER BY name_ar")->fetchAll();
                    $doctors = $pdo->query("SELECT id, name_ar, name_en, title_ar, title_en, hospital_id FROM doctors ORDER BY name_ar")->fetchAll();
                    ?>
                    <h2>إصدار إجازة مرضية</h2>
                    <form method="POST" action="?action=issue_leave" style="margin-bottom: 30px;">
                        <div class="form-row">
                            <div class="form-group">
                                <label>اختر المريض</label>
                                <select name="patient_id" id="patient_select" required onchange="loadPatientData()">
                                    <option value="">-- اختر مريض --</option>
                                    <?php foreach ($patients as $p): ?>
                                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name_ar']) ?> - <?= htmlspecialchars($p['identity_number']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>اختر المستشفى</label>
                                <select name="hospital_id" id="hospital_select" required onchange="loadDoctors()">
                                    <option value="">-- اختر مستشفى --</option>
                                    <?php foreach ($hospitals as $h): ?>
                                    <option value="<?= $h['id'] ?>"><?= htmlspecialchars($h['name_ar']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>اختر الطبيب</label>
                                <select name="doctor_id" id="doctor_select" required>
                                    <option value="">-- اختر طبيب --</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>تاريخ الإصدار</label>
                                <input type="date" name="issue_date" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>الوقت</label>
                                <input type="time" name="issue_time" value="09:00" required>
                            </div>
                            <div class="form-group">
                                <label>صباح / مساء</label>
                                <select name="issue_period" required>
                                    <option value="AM">صباح (AM)</option>
                                    <option value="PM">مساء (PM)</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>من تاريخ</label>
                                <input type="date" name="start_date" required>
                            </div>
                            <div class="form-group">
                                <label>إلى تاريخ</label>
                                <input type="date" name="end_date" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>
                                    <input type="checkbox" name="is_companion"> مرافق
                                </label>
                            </div>
                            <div class="form-group" id="companion_fields" style="display: none;">
                                <label>اسم المرافق</label>
                                <input type="text" name="companion_name">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>
                                    <input type="checkbox" name="is_paid"> مدفوعة
                                </label>
                            </div>
                            <div class="form-group">
                                <label>المبلغ</label>
                                <input type="number" name="payment_amount" step="0.01">
                            </div>
                        </div>
                        
                        <button type="submit" class="btn">إصدار الإجازة</button>
                    </form>
                    
                    <script>
                        function loadPatientData() {
                            const select = document.getElementById('patient_select');
                            const patientId = select.value;
                            if (patientId) {
                                fetch('?action=api_get_patient_data&patient_id=' + patientId)
                                    .then(r => r.json())
                                    .then(data => {
                                        if (data.patient) {
                                            console.log('Patient data loaded:', data.patient);
                                        }
                                    });
                            }
                        }
                        
                        function loadDoctors() {
                            const select = document.getElementById('hospital_select');
                            const hospitalId = select.value;
                            const doctorSelect = document.getElementById('doctor_select');
                            
                            if (hospitalId) {
                                fetch('?action=api_get_doctors_by_hospital&hospital_id=' + hospitalId)
                                    .then(r => r.json())
                                    .then(data => {
                                        doctorSelect.innerHTML = '<option value="">-- اختر طبيب --</option>';
                                        if (data.doctors) {
                                            data.doctors.forEach(d => {
                                                const option = document.createElement('option');
                                                option.value = d.id;
                                                option.textContent = d.name_ar;
                                                doctorSelect.appendChild(option);
                                            });
                                        }
                                    });
                            } else {
                                doctorSelect.innerHTML = '<option value="">-- اختر طبيب --</option>';
                            }
                        }
                        
                        document.querySelector('input[name="is_companion"]').addEventListener('change', function() {
                            document.getElementById('companion_fields').style.display = this.checked ? 'block' : 'none';
                        });
                    </script>
                    <?php
                    break;
                
                case 'leaves':
                    $leaves = $pdo->query("SELECT sl.*, p.name_ar as patient_name, d.name_ar as doctor_name, h.name_ar as hospital_name FROM sick_leaves sl LEFT JOIN patients p ON sl.patient_id = p.id LEFT JOIN doctors d ON sl.doctor_id = d.id LEFT JOIN hospitals h ON sl.hospital_id = h.id ORDER BY sl.created_at DESC")->fetchAll();
                    ?>
                    <h2>الإجازات المرضية</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>رمز الخدمة</th>
                                <th>اسم المريض</th>
                                <th>اسم الطبيب</th>
                                <th>المستشفى</th>
                                <th>من تاريخ</th>
                                <th>إلى تاريخ</th>
                                <th>عدد الأيام</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($leaves as $l): ?>
                            <tr>
                                <td><?= htmlspecialchars($l['service_code']) ?></td>
                                <td><?= htmlspecialchars($l['patient_name'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($l['doctor_name'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($l['hospital_name'] ?? '-') ?></td>
                                <td><?= $l['start_date'] ?></td>
                                <td><?= $l['end_date'] ?></td>
                                <td><?= $l['days_count'] ?></td>
                                <td>
                                    <div class="action-links">
                                        <a href="?action=generate_pdf&leave_id=<?= $l['id'] ?>" target="_blank">طباعة PDF</a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php
                    break;
                
                default:
                    echo '<p>الصفحة غير موجودة</p>';
            }
            ?>
        </div>
    </div>
</body>
</html>
