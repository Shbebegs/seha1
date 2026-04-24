<?php
/* install_schema.php
 * يشغَّل مرة واحدة لإنشاء الجداول وإضافة مشرف افتراضي
 * بعد نجاحه احذف هذا الملف لأمان أفضل.
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

// ===== إعدادات قاعدة البيانات (حسب ما أرسلت) =====
$db_host = 'mysql.railway.internal';
$db_user = 'root';
$db_pass = 'mDxJcHtRORIlpLbtDJKKckeuLgozRUVO';
$db_name = 'railway';
$db_port = 3306;

// ===== الاتصال =====
$mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);
if ($mysqli->connect_errno) {
    exit("فشل الاتصال بقاعدة البيانات: " . $mysqli->connect_error);
}
$mysqli->set_charset('utf8mb4');

// تأمين وضعية SQL
$mysqli->query("SET sql_mode = ''");

// ===== SQL: إنشاء الجداول =====
$sql = [];

// جدول المشرفين (للـ login.php)
$sql[] = "
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

$sql[] = "
CREATE TABLE IF NOT EXISTS patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    identity_number VARCHAR(20) NOT NULL UNIQUE,
    phone VARCHAR(20) DEFAULT NULL,
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

$sql[] = "
CREATE TABLE IF NOT EXISTS doctors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    title VARCHAR(100) NOT NULL,
    note VARCHAR(255) DEFAULT NULL,
    INDEX idx_name (name),
    INDEX idx_title (title)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

$sql[] = "
CREATE TABLE IF NOT EXISTS sick_leaves (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_code VARCHAR(30) NOT NULL UNIQUE,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    issue_date DATE NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    days_count INT NOT NULL,
    is_companion TINYINT(1) NOT NULL DEFAULT 0,
    companion_name VARCHAR(100) DEFAULT NULL,
    companion_relation VARCHAR(100) DEFAULT NULL,
    is_paid TINYINT(1) NOT NULL DEFAULT 0,
    payment_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL,
    deleted_at DATETIME DEFAULT NULL,
    is_deleted TINYINT(1) NOT NULL DEFAULT 0,
    CONSTRAINT fk_sl_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    CONSTRAINT fk_sl_doctor FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
    INDEX idx_patient (patient_id),
    INDEX idx_doctor (doctor_id),
    INDEX idx_dates (issue_date, start_date, end_date),
    INDEX idx_isdeleted_paid (is_deleted, is_paid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

$sql[] = "
CREATE TABLE IF NOT EXISTS leave_queries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    leave_id INT NOT NULL,
    queried_at DATETIME NOT NULL,
    source VARCHAR(20) NOT NULL DEFAULT 'external',
    CONSTRAINT fk_lq_leave FOREIGN KEY (leave_id) REFERENCES sick_leaves(id) ON DELETE CASCADE,
    INDEX idx_leave (leave_id),
    INDEX idx_queried (queried_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

$sql[] = "
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('query','payment') NOT NULL,
    leave_id INT DEFAULT NULL,
    message VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL,
    remind_at DATETIME DEFAULT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    CONSTRAINT fk_notif_leave FOREIGN KEY (leave_id) REFERENCES sick_leaves(id) ON DELETE CASCADE,
    INDEX idx_type (type),
    INDEX idx_created (created_at),
    INDEX idx_isread (is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

// ===== تنفيذ الإنشاء =====
foreach ($sql as $q) {
    if (!$mysqli->query($q)) {
        exit("خطأ أثناء إنشاء الجداول: " . $mysqli->error);
    }
}

// ===== إضافة مشرف افتراضي إن لم يوجد =====
$defaultUser = '0s1_m1';
$defaultPassPlain = '0s1_m1almuhaia2030'; // غيّرها بعد أول دخول
$hash = password_hash($defaultPassPlain, PASSWORD_DEFAULT);

// تحقق هل يوجد مستخدم admin
$stmt = $mysqli->prepare("SELECT id FROM admins WHERE username = ? LIMIT 1");
$stmt->bind_param("s", $defaultUser);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    $stmt->close();
    $ins = $mysqli->prepare("INSERT INTO admins (username, password) VALUES (?, ?)");
    $ins->bind_param("ss", $defaultUser, $hash);
    if (!$ins->execute()) {
        exit("تم إنشاء الجداول، لكن فشل إنشاء المشرف: " . $mysqli->error);
    }
    $ins->close();
    $createdAdmin = true;
} else {
    $stmt->close();
    $createdAdmin = false;
}

$mysqli->close();

// ===== إخراج رسالة نجاح =====
?>
<!doctype html>
<html lang="ar" dir="rtl">
<meta charset="utf-8">
<title>تثبيت قاعدة البيانات</title>
<body style="font-family: Tahoma, Arial; direction: rtl; padding:20px">
    <h2>تم إنشاء/تحديث الجداول بنجاح ✅</h2>
    <?php if ($createdAdmin): ?>
        <p>تم إضافة مشرف افتراضي:</p>
        <ul>
            <li>اسم المستخدم: <b><?= htmlspecialchars($defaultUser) ?></b></li>
            <li>كلمة المرور: <b><?= htmlspecialchars($defaultPassPlain) ?></b></li>
        </ul>
        <p>❗ أنصح بتغيير كلمة المرور بعد أول تسجيل دخول.</p>
    <?php else: ?>
        <p>تمت مراجعة جدول <code>admins</code> — يوجد مستخدم بالفعل، لم تتم إضافة مشرف جديد.</p>
    <?php endif; ?>
    <hr>
    <p>الآن يمكنك الذهاب إلى <code>login.php</code>. بعد التأكد من العمل، احذف هذا الملف <code>install_schema.php</code>.</p>
</body>
</html>
