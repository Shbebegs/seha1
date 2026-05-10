<?php
/**
 * لوحة تحكم الإجازات المرضية - النسخة المحسّنة v3
 * ملف واحد شامل يحتوي على PHP + HTML + CSS + JavaScript
 * 
 * الميزات:
 * 1. ربط المستشفيات بالأطباء
 * 2. الأسماء الإنجليزية للأطباء والمرضى
 * 3. تحويل التواريخ الهجرية تلقائياً
 * 4. حساب الأيام تلقائياً مع إضافة s
 * 5. تحديد الأيام والتواريخ والأوقات
 * 6. إخفاء رقم الترخيص إذا لم يكن موجوداً
 * 7. رفع شعار المستشفى
 * 8. توليد PDF احترافي
 * 9. ربط لوحة التحكم بالقالب
 */

ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? '1' : '0');
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', '1');
session_start();

date_default_timezone_set('Asia/Riyadh');
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(self), camera=()');

// ======================== إعدادات قاعدة البيانات ========================
$db_host = 'mysql.railway.internal';
$db_user = 'root';
$db_pass = 'CSCoMqXcUDBrzyRPMgjIxRVziMqcOFoK';
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
    die(json_encode(['success' => false, 'message' => 'فشل الاتصال بقاعدة البيانات']));
}

$pdo->exec("SET time_zone = '+03:00'");

// ======================== إنشاء الجداول ========================

// جدول المستشفيات
$pdo->exec("CREATE TABLE IF NOT EXISTS hospitals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name_ar VARCHAR(200) NOT NULL,
    name_en VARCHAR(200) NOT NULL,
    license_number VARCHAR(50) NULL,
    logo_url VARCHAR(500) NULL,
    logo_local_path VARCHAR(500) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// جدول الأطباء
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

// جدول المرضى
$pdo->exec("CREATE TABLE IF NOT EXISTS patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name_ar VARCHAR(150) NOT NULL,
    name_en VARCHAR(150) NULL,
    identity_number VARCHAR(50) NOT NULL,
    phone VARCHAR(30) NULL,
    folder_link VARCHAR(500) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_patients_identity_number (identity_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// جدول الإجازات المرضية
$pdo->exec("CREATE TABLE IF NOT EXISTS sick_leaves (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_code VARCHAR(50) NOT NULL UNIQUE,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    hospital_id INT NULL,
    employer_ar VARCHAR(200) NULL,
    employer_en VARCHAR(200) NULL,
    issue_date DATE NOT NULL,
    issue_time VARCHAR(10) NULL,
    issue_period VARCHAR(10) NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    days_count INT NOT NULL,
    is_companion TINYINT(1) DEFAULT 0,
    companion_name VARCHAR(150) NULL,
    companion_relation VARCHAR(150) NULL,
    is_paid TINYINT(1) DEFAULT 0,
    payment_amount DECIMAL(10,2) DEFAULT 0,
    deleted_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_sick_leaves_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE RESTRICT,
    CONSTRAINT fk_sick_leaves_doctor FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE RESTRICT,
    CONSTRAINT fk_sick_leaves_hospital FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// ======================== دوال تحويل التواريخ الهجرية ========================

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

function formatDaysCount($count) {
    return $count == 1 ? "1 day" : $count . " days";
}

function ensureLogoDirectory() {
    $dir = __DIR__ . '/logos';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}

function downloadLogoFromUrl($url, $hospital_id) {
    $logoDir = ensureLogoDirectory();
    $ext = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
    if (empty($ext)) $ext = 'png';
    
    $filename = 'hospital_' . $hospital_id . '_' . time() . '.' . $ext;
    $filepath = $logoDir . '/' . $filename;
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $data = curl_exec($ch);
    curl_close($ch);
    
    if ($data && file_put_contents($filepath, $data)) {
        return 'logos/' . $filename;
    }
    
    return null;
}

// ======================== معالجة الطلبات AJAX ========================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    $action = $_POST['action'];
    
    // إضافة مستشفى
    if ($action === 'add_hospital') {
        $name_ar = $_POST['name_ar'] ?? '';
        $name_en = $_POST['name_en'] ?? '';
        $license_number = $_POST['license_number'] ?? '';
        $logo_url = $_POST['logo_url'] ?? '';
        
        if (empty($name_ar)) {
            echo json_encode(['success' => false, 'message' => 'يجب إدخال اسم المستشفى بالعربية']);
            exit;
        }
        
        try {
            $stmt = $pdo->prepare("INSERT INTO hospitals (name_ar, name_en, license_number) VALUES (?, ?, ?)");
            $stmt->execute([$name_ar, $name_en, $license_number]);
            $hospital_id = $pdo->lastInsertId();
            
            if (!empty($logo_url)) {
                $logo_path = downloadLogoFromUrl($logo_url, $hospital_id);
                if ($logo_path) {
                    $stmt = $pdo->prepare("UPDATE hospitals SET logo_url = ?, logo_local_path = ? WHERE id = ?");
                    $stmt->execute([$logo_url, $logo_path, $hospital_id]);
                }
            }
            
            echo json_encode(['success' => true, 'message' => 'تمت إضافة المستشفى بنجاح', 'hospital_id' => $hospital_id]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    // إضافة طبيب
    if ($action === 'add_doctor') {
        $name_ar = $_POST['name_ar'] ?? '';
        $name_en = $_POST['name_en'] ?? '';
        $title_ar = $_POST['title_ar'] ?? '';
        $title_en = $_POST['title_en'] ?? '';
        $hospital_id = $_POST['hospital_id'] ?? null;
        
        if (empty($name_ar) || empty($title_ar)) {
            echo json_encode(['success' => false, 'message' => 'يجب إدخال الاسم والمسمى الوظيفي بالعربية']);
            exit;
        }
        
        try {
            $stmt = $pdo->prepare("INSERT INTO doctors (name_ar, name_en, title_ar, title_en, hospital_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name_ar, $name_en, $title_ar, $title_en, $hospital_id ?: null]);
            $doctor_id = $pdo->lastInsertId();
            
            echo json_encode(['success' => true, 'message' => 'تمت إضافة الطبيب بنجاح', 'doctor_id' => $doctor_id]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    // إضافة مريض
    if ($action === 'add_patient') {
        $name_ar = $_POST['name_ar'] ?? '';
        $name_en = $_POST['name_en'] ?? '';
        $identity_number = $_POST['identity_number'] ?? '';
        $phone = $_POST['phone'] ?? '';
        
        if (empty($name_ar) || empty($identity_number)) {
            echo json_encode(['success' => false, 'message' => 'يجب إدخال الاسم ورقم الهوية']);
            exit;
        }
        
        try {
            $stmt = $pdo->prepare("INSERT INTO patients (name_ar, name_en, identity_number, phone) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name_ar, $name_en, $identity_number, $phone]);
            $patient_id = $pdo->lastInsertId();
            
            echo json_encode(['success' => true, 'message' => 'تمت إضافة المريض بنجاح', 'patient_id' => $patient_id]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    // جلب أطباء المستشفى
    if ($action === 'get_hospital_doctors') {
        $hospital_id = $_POST['hospital_id'] ?? null;
        
        try {
            if ($hospital_id) {
                $stmt = $pdo->prepare("SELECT id, name_ar, name_en, title_ar, title_en FROM doctors WHERE hospital_id = ? ORDER BY name_ar");
                $stmt->execute([$hospital_id]);
            } else {
                $stmt = $pdo->query("SELECT id, name_ar, name_en, title_ar, title_en FROM doctors ORDER BY name_ar");
            }
            
            $doctors = $stmt->fetchAll();
            echo json_encode(['success' => true, 'doctors' => $doctors]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    // جلب جميع المستشفيات
    if ($action === 'get_hospitals') {
        try {
            $stmt = $pdo->query("SELECT id, name_ar, name_en, license_number FROM hospitals ORDER BY name_ar");
            $hospitals = $stmt->fetchAll();
            echo json_encode(['success' => true, 'hospitals' => $hospitals]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    // جلب جميع المرضى
    if ($action === 'get_patients') {
        try {
            $stmt = $pdo->query("SELECT id, name_ar, name_en, identity_number FROM patients ORDER BY name_ar");
            $patients = $stmt->fetchAll();
            echo json_encode(['success' => true, 'patients' => $patients]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    // إنشاء إجازة
    if ($action === 'create_leave') {
        $patient_id = $_POST['patient_id'] ?? null;
        $doctor_id = $_POST['doctor_id'] ?? null;
        $hospital_id = $_POST['hospital_id'] ?? null;
        $employer_ar = $_POST['employer_ar'] ?? '';
        $employer_en = $_POST['employer_en'] ?? '';
        $issue_date = $_POST['issue_date'] ?? date('Y-m-d');
        $issue_time = $_POST['issue_time'] ?? '09:00';
        $issue_period = $_POST['issue_period'] ?? 'AM';
        $start_date = $_POST['start_date'] ?? $issue_date;
        $end_date = $_POST['end_date'] ?? $start_date;
        
        if (!$patient_id || !$doctor_id) {
            echo json_encode(['success' => false, 'message' => 'يجب تحديد المريض والطبيب']);
            exit;
        }
        
        try {
            // حساب عدد الأيام
            $start = new DateTime($start_date);
            $end = new DateTime($end_date);
            $interval = $start->diff($end);
            $days_count = $interval->days + 1;
            
            // إنشاء رمز الخدمة
            $service_code = 'GSL' . date('ymd', strtotime($issue_date)) . str_pad(rand(10000, 99999), 5, '0', STR_PAD_LEFT);
            
            $stmt = $pdo->prepare("INSERT INTO sick_leaves (
                service_code, patient_id, doctor_id, hospital_id, employer_ar, employer_en,
                issue_date, issue_time, issue_period, start_date, end_date, days_count
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([
                $service_code, $patient_id, $doctor_id, $hospital_id,
                $employer_ar, $employer_en, $issue_date, $issue_time, $issue_period,
                $start_date, $end_date, $days_count
            ]);
            
            $leave_id = $pdo->lastInsertId();
            
            echo json_encode([
                'success' => true,
                'message' => 'تمت إنشاء الإجازة بنجاح',
                'leave_id' => $leave_id,
                'service_code' => $service_code
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    // جلب جميع الإجازات
    if ($action === 'get_leaves') {
        try {
            $stmt = $pdo->query("
                SELECT sl.*, 
                       p.name_ar AS patient_name_ar, p.name_en AS patient_name_en,
                       d.name_ar AS doctor_name_ar, d.name_en AS doctor_name_en,
                       h.name_ar AS hospital_name_ar
                FROM sick_leaves sl
                LEFT JOIN patients p ON sl.patient_id = p.id
                LEFT JOIN doctors d ON sl.doctor_id = d.id
                LEFT JOIN hospitals h ON sl.hospital_id = h.id
                WHERE sl.deleted_at IS NULL
                ORDER BY sl.created_at DESC
            ");
            $leaves = $stmt->fetchAll();
            echo json_encode(['success' => true, 'leaves' => $leaves]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    // توليد PDF
    if ($action === 'generate_pdf') {
        $leave_id = $_POST['leave_id'] ?? null;
        
        if (!$leave_id) {
            echo json_encode(['success' => false, 'message' => 'معرف الإجازة غير صحيح']);
            exit;
        }
        
        try {
            $stmt = $pdo->prepare("
                SELECT sl.*, 
                       p.name_ar AS patient_name_ar, p.name_en AS patient_name_en, p.identity_number,
                       d.name_ar AS doctor_name_ar, d.name_en AS doctor_name_en, d.title_ar, d.title_en,
                       h.name_ar AS hospital_name_ar, h.name_en AS hospital_name_en, h.license_number, h.logo_local_path
                FROM sick_leaves sl
                LEFT JOIN patients p ON sl.patient_id = p.id
                LEFT JOIN doctors d ON sl.doctor_id = d.id
                LEFT JOIN hospitals h ON sl.hospital_id = h.id
                WHERE sl.id = ?
            ");
            $stmt->execute([$leave_id]);
            $leave = $stmt->fetch();
            
            if (!$leave) {
                echo json_encode(['success' => false, 'message' => 'الإجازة غير موجودة']);
                exit;
            }
            
            // تحويل التاريخ الهجري
            $hijri_start = gregorianToHijri($leave['start_date']);
            $hijri_end = gregorianToHijri($leave['end_date']);
            
            // إنشاء HTML للـ PDF
            $html = generatePdfHtml($leave, $hijri_start, $hijri_end);
            
            // حفظ HTML مؤقتاً
            $temp_file = tempnam(sys_get_temp_dir(), 'leave_') . '.html';
            file_put_contents($temp_file, $html);
            
            // تحويل إلى PDF
            $pdf_file = sys_get_temp_dir() . '/leave_' . $leave_id . '_' . time() . '.pdf';
            
            // محاولة استخدام wkhtmltopdf
            $cmd = "wkhtmltopdf --quiet --enable-local-file-access '$temp_file' '$pdf_file' 2>/dev/null";
            exec($cmd, $output, $return_code);
            
            if ($return_code === 0 && file_exists($pdf_file)) {
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="sick_leave_' . $leave['service_code'] . '.pdf"');
                readfile($pdf_file);
                
                unlink($temp_file);
                unlink($pdf_file);
                exit;
            }
            
            // إذا فشل wkhtmltopdf، أرسل HTML مباشرة
            header('Content-Type: text/html; charset=utf-8');
            echo $html;
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}

function generatePdfHtml($leave, $hijri_start, $hijri_end) {
    $days_text = $leave['days_count'] == 1 ? 'day' : 'days';
    $license_display = !empty($leave['license_number']) ? 'رقم الترخيص : ' . htmlspecialchars($leave['license_number']) : '';
    
    $patient_name_ar = htmlspecialchars($leave['patient_name_ar'] ?? '');
    $patient_name_en = htmlspecialchars($leave['patient_name_en'] ?? '');
    $doctor_name_ar = htmlspecialchars($leave['doctor_name_ar'] ?? '');
    $doctor_name_en = htmlspecialchars($leave['doctor_name_en'] ?? '');
    $title_ar = htmlspecialchars($leave['title_ar'] ?? '');
    $title_en = htmlspecialchars($leave['title_en'] ?? '');
    $hospital_name_ar = htmlspecialchars($leave['hospital_name_ar'] ?? '');
    $hospital_name_en = htmlspecialchars($leave['hospital_name_en'] ?? '');
    $employer_ar = htmlspecialchars($leave['employer_ar'] ?? '');
    $employer_en = htmlspecialchars($leave['employer_en'] ?? '');
    
    $html = <<<HTML
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تقرير إجازة مرضية</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Arial', 'Noto Sans Arabic', sans-serif; direction: rtl; background: white; }
        .container { width: 100%; max-width: 900px; margin: 0 auto; padding: 20px; }
        .header { text-align: center; margin-bottom: 30px; }
        .title { font-size: 28px; font-weight: bold; color: #306db5; margin: 10px 0; }
        .subtitle { font-size: 16px; color: #2c3e77; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #cccccc; padding: 12px; text-align: center; font-size: 14px; }
        th { background-color: #2c3e77; color: white; font-weight: bold; }
        tr:nth-child(even) { background-color: #f7f7f7; }
        tr.blue-row { background-color: #2c3e77; color: white; }
        tr.blue-row td { color: white; border-color: #cccccc; }
        .footer { text-align: center; margin-top: 40px; font-size: 12px; border-top: 1px solid #ddd; padding-top: 20px; }
        .hospital-info { text-align: center; margin: 30px 0; }
        .hospital-info div { margin: 5px 0; }
        .arabic-text { font-family: 'Noto Sans Arabic', Arial; }
        .english-text { font-family: 'Times New Roman', Arial; }
        @media print {
            body { background: white; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="title">تقرير إجازة مرضية</div>
            <div class="subtitle">Sick Leave Report</div>
        </div>
        
        <table cellpadding="0" cellspacing="0">
            <tr>
                <td class="english-text">Leave ID</td>
                <td colspan="2">{$leave['service_code']}</td>
                <td class="arabic-text">رمز الإجازة</td>
            </tr>
            <tr class="blue-row">
                <td class="english-text">Leave Duration</td>
                <td class="english-text">{$leave['days_count']} {$days_text} ( {$leave['start_date']} to {$leave['end_date']} )</td>
                <td class="arabic-text">{$leave['days_count']} {$days_text} ( {$hijri_start} الى {$hijri_end} )</td>
                <td class="arabic-text">مدة الإجازة</td>
            </tr>
            <tr>
                <td class="english-text">Admission Date</td>
                <td class="english-text">{$leave['start_date']}</td>
                <td class="arabic-text">{$hijri_start}</td>
                <td class="arabic-text">تاريخ الدخول</td>
            </tr>
            <tr>
                <td class="english-text">Discharge Date</td>
                <td class="english-text">{$leave['end_date']}</td>
                <td class="arabic-text">{$hijri_end}</td>
                <td class="arabic-text">تاريخ الخروج</td>
            </tr>
            <tr>
                <td class="english-text">Issue Date</td>
                <td colspan="2">{$leave['issue_date']}</td>
                <td class="arabic-text">تاريخ الإصدار</td>
            </tr>
            <tr>
                <td class="english-text">Patient Name</td>
                <td class="english-text">{$patient_name_en}</td>
                <td class="arabic-text">{$patient_name_ar}</td>
                <td class="arabic-text">الاسم</td>
            </tr>
            <tr>
                <td class="english-text">National ID / Iqama</td>
                <td colspan="2">{$leave['identity_number']}</td>
                <td class="arabic-text">رقم الهوية/الإقامة</td>
            </tr>
            <tr>
                <td class="english-text">Employer</td>
                <td class="english-text">{$employer_en}</td>
                <td class="arabic-text">{$employer_ar}</td>
                <td class="arabic-text">جهة العمل</td>
            </tr>
            <tr>
                <td class="english-text">Physician Name</td>
                <td class="english-text">{$doctor_name_en}</td>
                <td class="arabic-text">{$doctor_name_ar}</td>
                <td class="arabic-text">اسم الطبيب المعالج</td>
            </tr>
            <tr>
                <td class="english-text">Position</td>
                <td class="english-text">{$title_en}</td>
                <td class="arabic-text">{$title_ar}</td>
                <td class="arabic-text">المسمى الوظيفي</td>
            </tr>
        </table>
        
        <div class="hospital-info">
            <div class="arabic-text" style="font-weight: bold; font-size: 16px;">{$hospital_name_ar}</div>
            <div class="english-text" style="font-weight: bold; font-size: 16px;">{$hospital_name_en}</div>
            <div class="arabic-text">{$license_display}</div>
        </div>
        
        <div class="footer">
            <p>تم الإصدار في: {$leave['issue_date']} - {$leave['issue_time']} {$leave['issue_period']}</p>
            <p>Issued on: {$leave['issue_date']} - {$leave['issue_time']} {$leave['issue_period']}</p>
        </div>
    </div>
</body>
</html>
HTML;
    
    return $html;
}

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام إدارة الإجازات المرضية</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Noto Sans Arabic', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #306db5 0%, #2c3e77 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 { font-size: 32px; margin-bottom: 10px; }
        .header p { font-size: 16px; opacity: 0.9; }
        
        .tabs {
            display: flex;
            border-bottom: 2px solid #e0e0e0;
            background: #f5f5f5;
            flex-wrap: wrap;
        }
        
        .tab-button {
            flex: 1;
            min-width: 150px;
            padding: 15px 20px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            color: #666;
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
        }
        
        .tab-button.active {
            color: #306db5;
            border-bottom-color: #306db5;
            background: white;
        }
        
        .tab-button:hover { background: white; }
        
        .tab-content {
            display: none;
            padding: 30px;
            animation: fadeIn 0.3s ease;
        }
        
        .tab-content.active { display: block; }
        
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-family: inherit;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #306db5;
            box-shadow: 0 0 0 3px rgba(48, 109, 181, 0.1);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-row.full { grid-template-columns: 1fr; }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-right: 10px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #306db5 0%, #2c3e77 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(48, 109, 181, 0.3);
        }
        
        .btn-success {
            background: #4caf50;
            color: white;
        }
        
        .btn-success:hover {
            background: #45a049;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
        }
        
        .alert.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            display: block;
        }
        
        .alert.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            display: block;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .data-table th {
            background: #f5f5f5;
            padding: 15px;
            text-align: right;
            font-weight: 600;
            border-bottom: 2px solid #ddd;
            font-size: 14px;
        }
        
        .data-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            font-size: 13px;
        }
        
        .data-table tr:hover { background: #f9f9f9; }
        
        .action-btn {
            padding: 6px 12px;
            margin: 0 3px;
            font-size: 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .action-btn.pdf {
            background: #ff6b6b;
            color: white;
        }
        
        .action-btn.pdf:hover {
            background: #ee5a52;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>نظام إدارة الإجازات المرضية</h1>
            <p>Sick Leave Management System v3</p>
        </div>
        
        <div class="tabs">
            <button class="tab-button active" onclick="switchTab('hospitals')">المستشفيات</button>
            <button class="tab-button" onclick="switchTab('doctors')">الأطباء</button>
            <button class="tab-button" onclick="switchTab('patients')">المرضى</button>
            <button class="tab-button" onclick="switchTab('leaves')">إصدار إجازة</button>
            <button class="tab-button" onclick="switchTab('list')">قائمة الإجازات</button>
        </div>
        
        <!-- المستشفيات -->
        <div id="hospitals" class="tab-content active">
            <h2>إدارة المستشفيات</h2>
            <div class="alert" id="hospital-alert"></div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>اسم المستشفى (عربي) *</label>
                    <input type="text" id="hospital-name-ar" placeholder="مثال: مستشفى الملك فهد">
                </div>
                <div class="form-group">
                    <label>اسم المستشفى (إنجليزي)</label>
                    <input type="text" id="hospital-name-en" placeholder="Example: King Fahd Hospital">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>رقم الترخيص (اختياري)</label>
                    <input type="text" id="hospital-license" placeholder="مثال: 4800031024">
                </div>
                <div class="form-group">
                    <label>رابط شعار المستشفى (اختياري)</label>
                    <input type="url" id="hospital-logo-url" placeholder="https://example.com/logo.png">
                </div>
            </div>
            
            <button class="btn btn-primary" onclick="addHospital()">إضافة مستشفى</button>
            
            <table class="data-table" id="hospitals-table">
                <thead>
                    <tr>
                        <th>الاسم (عربي)</th>
                        <th>الاسم (إنجليزي)</th>
                        <th>رقم الترخيص</th>
                    </tr>
                </thead>
                <tbody id="hospitals-tbody">
                </tbody>
            </table>
        </div>
        
        <!-- الأطباء -->
        <div id="doctors" class="tab-content">
            <h2>إدارة الأطباء</h2>
            <div class="alert" id="doctor-alert"></div>
            
            <div class="form-group">
                <label>المستشفى (اختياري)</label>
                <select id="doctor-hospital">
                    <option value="">-- بدون تحديد --</option>
                </select>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>اسم الطبيب (عربي) *</label>
                    <input type="text" id="doctor-name-ar" placeholder="مثال: محمد عبدالله">
                </div>
                <div class="form-group">
                    <label>اسم الطبيب (إنجليزي)</label>
                    <input type="text" id="doctor-name-en" placeholder="Example: Mohammed Abdullah">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>المسمى الوظيفي (عربي) *</label>
                    <input type="text" id="doctor-title-ar" placeholder="مثال: استشاري">
                </div>
                <div class="form-group">
                    <label>المسمى الوظيفي (إنجليزي)</label>
                    <input type="text" id="doctor-title-en" placeholder="Example: Consultant">
                </div>
            </div>
            
            <button class="btn btn-primary" onclick="addDoctor()">إضافة طبيب</button>
            
            <table class="data-table" id="doctors-table">
                <thead>
                    <tr>
                        <th>الاسم (عربي)</th>
                        <th>الاسم (إنجليزي)</th>
                        <th>المسمى (عربي)</th>
                        <th>المسمى (إنجليزي)</th>
                    </tr>
                </thead>
                <tbody id="doctors-tbody">
                </tbody>
            </table>
        </div>
        
        <!-- المرضى -->
        <div id="patients" class="tab-content">
            <h2>إدارة المرضى</h2>
            <div class="alert" id="patient-alert"></div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>اسم المريض (عربي) *</label>
                    <input type="text" id="patient-name-ar" placeholder="مثال: أسامة عبدالله">
                </div>
                <div class="form-group">
                    <label>اسم المريض (إنجليزي)</label>
                    <input type="text" id="patient-name-en" placeholder="Example: Osama Abdullah">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>رقم الهوية/الإقامة *</label>
                    <input type="text" id="patient-identity" placeholder="مثال: 1019874138">
                </div>
                <div class="form-group">
                    <label>رقم الهاتف (اختياري)</label>
                    <input type="tel" id="patient-phone" placeholder="مثال: 0501234567">
                </div>
            </div>
            
            <button class="btn btn-primary" onclick="addPatient()">إضافة مريض</button>
            
            <table class="data-table" id="patients-table">
                <thead>
                    <tr>
                        <th>الاسم (عربي)</th>
                        <th>الاسم (إنجليزي)</th>
                        <th>رقم الهوية</th>
                        <th>رقم الهاتف</th>
                    </tr>
                </thead>
                <tbody id="patients-tbody">
                </tbody>
            </table>
        </div>
        
        <!-- إصدار إجازة -->
        <div id="leaves" class="tab-content">
            <h2>إصدار إجازة مرضية</h2>
            <div class="alert" id="leave-alert"></div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>المستشفى (اختياري)</label>
                    <select id="leave-hospital" onchange="updateDoctorsList()">
                        <option value="">-- اختر المستشفى --</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>الطبيب *</label>
                    <select id="leave-doctor">
                        <option value="">-- اختر الطبيب --</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>المريض *</label>
                    <select id="leave-patient">
                        <option value="">-- اختر المريض --</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>جهة العمل (عربي)</label>
                    <input type="text" id="leave-employer-ar" placeholder="مثال: الى من يهمه الامر">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>جهة العمل (إنجليزي)</label>
                    <input type="text" id="leave-employer-en" placeholder="Example: TO WHOM IT MAY CONCERN">
                </div>
                <div class="form-group">
                    <label>تاريخ الإصدار</label>
                    <input type="date" id="leave-issue-date">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>الوقت</label>
                    <input type="time" id="leave-issue-time">
                </div>
                <div class="form-group">
                    <label>صباح/مساء</label>
                    <select id="leave-issue-period">
                        <option value="AM">صباحاً (AM)</option>
                        <option value="PM">مساءً (PM)</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>تاريخ البداية</label>
                    <input type="date" id="leave-start-date">
                </div>
                <div class="form-group">
                    <label>تاريخ النهاية</label>
                    <input type="date" id="leave-end-date">
                </div>
            </div>
            
            <button class="btn btn-primary" onclick="createLeave()">إصدار الإجازة</button>
        </div>
        
        <!-- قائمة الإجازات -->
        <div id="list" class="tab-content">
            <h2>قائمة الإجازات المرضية</h2>
            
            <table class="data-table" id="leaves-table">
                <thead>
                    <tr>
                        <th>رمز الإجازة</th>
                        <th>المريض</th>
                        <th>الطبيب</th>
                        <th>عدد الأيام</th>
                        <th>تاريخ الإصدار</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody id="leaves-tbody">
                </tbody>
            </table>
        </div>
    </div>
    
    <script>
        // تهيئة التطبيق
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('leave-issue-date').value = today;
            document.getElementById('leave-start-date').value = today;
            document.getElementById('leave-end-date').value = today;
            document.getElementById('leave-issue-time').value = '09:00';
            
            loadHospitals();
            loadDoctors();
            loadPatients();
            loadLeaves();
        });
        
        function switchTab(tabName) {
            const tabs = document.querySelectorAll('.tab-content');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            const buttons = document.querySelectorAll('.tab-button');
            buttons.forEach(btn => btn.classList.remove('active'));
            
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }
        
        function showAlert(elementId, message, type) {
            const alert = document.getElementById(elementId);
            alert.textContent = message;
            alert.className = 'alert ' + type;
            setTimeout(() => {
                alert.className = 'alert';
            }, 5000);
        }
        
        // ======================== المستشفيات ========================
        
        function addHospital() {
            const nameAr = document.getElementById('hospital-name-ar').value;
            const nameEn = document.getElementById('hospital-name-en').value;
            const license = document.getElementById('hospital-license').value;
            const logoUrl = document.getElementById('hospital-logo-url').value;
            
            if (!nameAr) {
                showAlert('hospital-alert', 'يجب إدخال اسم المستشفى بالعربية', 'error');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'add_hospital');
            formData.append('name_ar', nameAr);
            formData.append('name_en', nameEn);
            formData.append('license_number', license);
            formData.append('logo_url', logoUrl);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showAlert('hospital-alert', data.message, 'success');
                    document.getElementById('hospital-name-ar').value = '';
                    document.getElementById('hospital-name-en').value = '';
                    document.getElementById('hospital-license').value = '';
                    document.getElementById('hospital-logo-url').value = '';
                    loadHospitals();
                } else {
                    showAlert('hospital-alert', data.message, 'error');
                }
            });
        }
        
        function loadHospitals() {
            const formData = new FormData();
            formData.append('action', 'get_hospitals');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const tbody = document.getElementById('hospitals-tbody');
                    tbody.innerHTML = '';
                    
                    const select = document.getElementById('leave-hospital');
                    const docSelect = document.getElementById('doctor-hospital');
                    select.innerHTML = '<option value="">-- اختر المستشفى --</option>';
                    docSelect.innerHTML = '<option value="">-- بدون تحديد --</option>';
                    
                    data.hospitals.forEach(hospital => {
                        const row = tbody.insertRow();
                        row.innerHTML = `
                            <td>${hospital.name_ar}</td>
                            <td>${hospital.name_en}</td>
                            <td>${hospital.license_number || '-'}</td>
                        `;
                        
                        const option = document.createElement('option');
                        option.value = hospital.id;
                        option.textContent = hospital.name_ar;
                        select.appendChild(option);
                        
                        const docOption = document.createElement('option');
                        docOption.value = hospital.id;
                        docOption.textContent = hospital.name_ar;
                        docSelect.appendChild(docOption);
                    });
                }
            });
        }
        
        // ======================== الأطباء ========================
        
        function addDoctor() {
            const nameAr = document.getElementById('doctor-name-ar').value;
            const nameEn = document.getElementById('doctor-name-en').value;
            const titleAr = document.getElementById('doctor-title-ar').value;
            const titleEn = document.getElementById('doctor-title-en').value;
            const hospitalId = document.getElementById('doctor-hospital').value;
            
            if (!nameAr || !titleAr) {
                showAlert('doctor-alert', 'يجب إدخال الاسم والمسمى الوظيفي بالعربية', 'error');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'add_doctor');
            formData.append('name_ar', nameAr);
            formData.append('name_en', nameEn);
            formData.append('title_ar', titleAr);
            formData.append('title_en', titleEn);
            formData.append('hospital_id', hospitalId);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showAlert('doctor-alert', data.message, 'success');
                    document.getElementById('doctor-name-ar').value = '';
                    document.getElementById('doctor-name-en').value = '';
                    document.getElementById('doctor-title-ar').value = '';
                    document.getElementById('doctor-title-en').value = '';
                    loadDoctors();
                } else {
                    showAlert('doctor-alert', data.message, 'error');
                }
            });
        }
        
        function loadDoctors() {
            const formData = new FormData();
            formData.append('action', 'get_hospital_doctors');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const tbody = document.getElementById('doctors-tbody');
                    tbody.innerHTML = '';
                    
                    const select = document.getElementById('leave-doctor');
                    select.innerHTML = '<option value="">-- اختر الطبيب --</option>';
                    
                    data.doctors.forEach(doctor => {
                        const row = tbody.insertRow();
                        row.innerHTML = `
                            <td>${doctor.name_ar}</td>
                            <td>${doctor.name_en || '-'}</td>
                            <td>${doctor.title_ar}</td>
                            <td>${doctor.title_en || '-'}</td>
                        `;
                        
                        const option = document.createElement('option');
                        option.value = doctor.id;
                        option.textContent = doctor.name_ar + (doctor.name_en ? ' / ' + doctor.name_en : '');
                        select.appendChild(option);
                    });
                }
            });
        }
        
        function updateDoctorsList() {
            const hospitalId = document.getElementById('leave-hospital').value;
            const formData = new FormData();
            formData.append('action', 'get_hospital_doctors');
            formData.append('hospital_id', hospitalId);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                const select = document.getElementById('leave-doctor');
                select.innerHTML = '<option value="">-- اختر الطبيب --</option>';
                if (data.success && data.doctors) {
                    data.doctors.forEach(doctor => {
                        const option = document.createElement('option');
                        option.value = doctor.id;
                        option.textContent = doctor.name_ar + (doctor.name_en ? ' / ' + doctor.name_en : '');
                        select.appendChild(option);
                    });
                }
            });
        }
        
        // ======================== المرضى ========================
        
        function addPatient() {
            const nameAr = document.getElementById('patient-name-ar').value;
            const nameEn = document.getElementById('patient-name-en').value;
            const identity = document.getElementById('patient-identity').value;
            const phone = document.getElementById('patient-phone').value;
            
            if (!nameAr || !identity) {
                showAlert('patient-alert', 'يجب إدخال الاسم ورقم الهوية', 'error');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'add_patient');
            formData.append('name_ar', nameAr);
            formData.append('name_en', nameEn);
            formData.append('identity_number', identity);
            formData.append('phone', phone);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showAlert('patient-alert', data.message, 'success');
                    document.getElementById('patient-name-ar').value = '';
                    document.getElementById('patient-name-en').value = '';
                    document.getElementById('patient-identity').value = '';
                    document.getElementById('patient-phone').value = '';
                    loadPatients();
                } else {
                    showAlert('patient-alert', data.message, 'error');
                }
            });
        }
        
        function loadPatients() {
            const formData = new FormData();
            formData.append('action', 'get_patients');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const tbody = document.getElementById('patients-tbody');
                    tbody.innerHTML = '';
                    
                    const select = document.getElementById('leave-patient');
                    select.innerHTML = '<option value="">-- اختر المريض --</option>';
                    
                    data.patients.forEach(patient => {
                        const row = tbody.insertRow();
                        row.innerHTML = `
                            <td>${patient.name_ar}</td>
                            <td>${patient.name_en || '-'}</td>
                            <td>${patient.identity_number}</td>
                            <td>-</td>
                        `;
                        
                        const option = document.createElement('option');
                        option.value = patient.id;
                        option.textContent = patient.name_ar + ' (' + patient.identity_number + ')';
                        select.appendChild(option);
                    });
                }
            });
        }
        
        // ======================== الإجازات ========================
        
        function createLeave() {
            const patientId = document.getElementById('leave-patient').value;
            const doctorId = document.getElementById('leave-doctor').value;
            const hospitalId = document.getElementById('leave-hospital').value;
            const employerAr = document.getElementById('leave-employer-ar').value;
            const employerEn = document.getElementById('leave-employer-en').value;
            const issueDate = document.getElementById('leave-issue-date').value;
            const issueTime = document.getElementById('leave-issue-time').value;
            const issuePeriod = document.getElementById('leave-issue-period').value;
            const startDate = document.getElementById('leave-start-date').value;
            const endDate = document.getElementById('leave-end-date').value;
            
            if (!patientId || !doctorId) {
                showAlert('leave-alert', 'يجب تحديد المريض والطبيب', 'error');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'create_leave');
            formData.append('patient_id', patientId);
            formData.append('doctor_id', doctorId);
            formData.append('hospital_id', hospitalId);
            formData.append('employer_ar', employerAr);
            formData.append('employer_en', employerEn);
            formData.append('issue_date', issueDate);
            formData.append('issue_time', issueTime);
            formData.append('issue_period', issuePeriod);
            formData.append('start_date', startDate);
            formData.append('end_date', endDate);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showAlert('leave-alert', data.message + ' - الرمز: ' + data.service_code, 'success');
                    loadLeaves();
                    setTimeout(() => {
                        document.getElementById('leave-patient').value = '';
                        document.getElementById('leave-doctor').value = '';
                        document.getElementById('leave-employer-ar').value = '';
                        document.getElementById('leave-employer-en').value = '';
                    }, 1000);
                } else {
                    showAlert('leave-alert', data.message, 'error');
                }
            });
        }
        
        function loadLeaves() {
            const formData = new FormData();
            formData.append('action', 'get_leaves');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const tbody = document.getElementById('leaves-tbody');
                    tbody.innerHTML = '';
                    
                    data.leaves.forEach(leave => {
                        const row = tbody.insertRow();
                        row.innerHTML = `
                            <td>${leave.service_code}</td>
                            <td>${leave.patient_name_ar}</td>
                            <td>${leave.doctor_name_ar}</td>
                            <td>${leave.days_count}</td>
                            <td>${leave.issue_date}</td>
                            <td>
                                <button class="action-btn pdf" onclick="generatePDF(${leave.id})">تحميل PDF</button>
                            </td>
                        `;
                    });
                }
            });
        }
        
        function generatePDF(leaveId) {
            const formData = new FormData();
            formData.append('action', 'generate_pdf');
            formData.append('leave_id', leaveId);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(r => r.blob())
            .then(blob => {
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'sick_leave_' + leaveId + '.pdf';
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
            })
            .catch(err => console.error('خطأ في تحميل PDF:', err));
        }
    </script>
</body>
</html>
