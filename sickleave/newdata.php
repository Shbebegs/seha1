<?php
// =========================================
//  ملف: migrate_db.php
//  المهام: إنشاء وتحديث جميع جداول النظام الطبي
//         مع ضمان وجود الأعمدة الضرورية
// =========================================

date_default_timezone_set('Asia/Riyadh');

// ==== 1. إعدادات الاتصال بقاعدة البيانات ====
$db_host = 'ocvwlym0zv3tcn68.cbetxkdyhwsb.us-east-1.rds.amazonaws.com';
$db_user = 'smrg7ak77778emkb';
$db_pass = 'fw69cuijof4ahuhb';
$db_name = 'ygscnjzq8ueid5yz';
$db_port = 3306;

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);
if ($conn->connect_errno) {
    die("<h2 style='color: red; text-align: center;'>فشل الاتصال بقاعدة البيانات: " . $conn->connect_error . "</h2>");
}
$conn->set_charset('utf8mb4');

echo "<h2 style='text-align: center; color: #0288d1;'>بدء تحديث هيكل قاعدة البيانات...</h2>";
echo "<pre style='background-color: #f8f8f8; padding: 15px; border-radius: 8px; margin: 20px auto; max-width: 800px; text-align: left;'>";

// ==== 2. الجداول الأساسية ====
// جدول المرضى
$sql_patients = "
CREATE TABLE IF NOT EXISTS patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    identity_number VARCHAR(20) NOT NULL UNIQUE,
    phone VARCHAR(20) DEFAULT NULL
) ENGINE=InnoDB CHARSET=utf8mb4";
if ($conn->query($sql_patients)) {
    echo " - جدول 'patients' جاهز.\n";
} else {
    echo " - <span style='color: red;'>خطأ إنشاء جدول 'patients': " . $conn->error . "</span>\n";
}

// جدول الأطباء
$sql_doctors = "
CREATE TABLE IF NOT EXISTS doctors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    title VARCHAR(100) NOT NULL
) ENGINE=InnoDB CHARSET=utf8mb4";
if ($conn->query($sql_doctors)) {
    echo " - جدول 'doctors' جاهز.\n";
} else {
    echo " - <span style='color: red;'>خطأ إنشاء جدول 'doctors': " . $conn->error . "</span>\n";
}

// جدول الإجازات المرضية
$sql_sickleaves = "
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
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT NULL,
    deleted_at DATETIME DEFAULT NULL,
    is_deleted TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY(patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY(doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
) ENGINE=InnoDB CHARSET=utf8mb4";
if ($conn->query($sql_sickleaves)) {
    echo " - جدول 'sick_leaves' جاهز.\n";
} else {
    echo " - <span style='color: red;'>خطأ إنشاء جدول 'sick_leaves': " . $conn->error . "</span>\n";
}

// جدول استعلامات الإجازات
$sql_leavequeries = "
CREATE TABLE IF NOT EXISTS leave_queries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    leave_id INT NOT NULL,
    queried_at DATETIME NOT NULL,
    source VARCHAR(20) NOT NULL DEFAULT 'external',
    FOREIGN KEY(leave_id) REFERENCES sick_leaves(id) ON DELETE CASCADE
) ENGINE=InnoDB CHARSET=utf8mb4";
if ($conn->query($sql_leavequeries)) {
    echo " - جدول 'leave_queries' جاهز.\n";
} else {
    echo " - <span style='color: red;'>خطأ إنشاء جدول 'leave_queries': " . $conn->error . "</span>\n";
}

// ==== 3. جدول مدفوعات المرضى ====
$sql_create_payments = "
CREATE TABLE IF NOT EXISTS patient_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    leave_id INT DEFAULT NULL,
    payment_date DATETIME NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    payment_method VARCHAR(50) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (leave_id) REFERENCES sick_leaves(id) ON DELETE SET NULL
) ENGINE=InnoDB CHARSET=utf8mb4";
if ($conn->query($sql_create_payments)) {
    echo " - جدول 'patient_payments' جاهز.\n";
} else {
    echo " - <span style='color: red;'>خطأ إنشاء جدول 'patient_payments': " . $conn->error . "</span>\n";
}

// ==== 4. جدول الإشعارات (notifications) ====
$sql_create_notifications = "
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(50) NOT NULL,
    entity_id INT NOT NULL,
    patient_id INT DEFAULT NULL,
    doctor_id INT DEFAULT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    expires_at DATETIME DEFAULT NULL,
    action_taken TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
) ENGINE=InnoDB CHARSET=utf8mb4";
if ($conn->query($sql_create_notifications)) {
    echo " - جدول 'notifications' جاهز.\n";
} else {
    echo " - <span style='color: red;'>خطأ إنشاء جدول 'notifications': " . $conn->error . "</span>\n";
}

// ==== 5. إضافة عمود leave_id لجدول notifications إذا لم يكن موجودًا ====
$check_leaveid = $conn->query("SHOW COLUMNS FROM notifications LIKE 'leave_id'");
if ($check_leaveid->num_rows == 0) {
    if ($conn->query("ALTER TABLE notifications ADD COLUMN leave_id INT DEFAULT NULL")) {
        echo " - تم إضافة العمود 'leave_id' إلى جدول 'notifications'.\n";
    } else {
        echo " - <span style='color: red;'>فشل إضافة العمود 'leave_id': " . $conn->error . "</span>\n";
    }
} else {
    echo " - العمود 'leave_id' موجود بالفعل في جدول 'notifications'.\n";
}

// ==== 6. إضافة الأعمدة التكميلية لـ sick_leaves إن لم تكن موجودة ====
// إضافة عمود payment_amount
$check_column = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '{$db_name}' AND TABLE_NAME = 'sick_leaves' AND COLUMN_NAME = 'payment_amount'");
if ($check_column->num_rows == 0) {
    if ($conn->query("ALTER TABLE sick_leaves ADD COLUMN payment_amount DECIMAL(10, 2) DEFAULT 0.00")) {
        echo " - تم إضافة عمود 'payment_amount' إلى جدول 'sick_leaves'.\n";
    } else {
        echo " - <span style='color: red;'>فشل إضافة عمود 'payment_amount': " . $conn->error . "</span>\n";
    }
} else {
    echo " - عمود 'payment_amount' موجود بالفعل في جدول 'sick_leaves'.\n";
}

// إضافة عمود is_paid
$check_column = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '{$db_name}' AND TABLE_NAME = 'sick_leaves' AND COLUMN_NAME = 'is_paid'");
if ($check_column->num_rows == 0) {
    if ($conn->query("ALTER TABLE sick_leaves ADD COLUMN is_paid TINYINT(1) NOT NULL DEFAULT 0")) {
        echo " - تم إضافة عمود 'is_paid' إلى جدول 'sick_leaves'.\n";
    } else {
        echo " - <span style='color: red;'>فشل إضافة عمود 'is_paid': " . $conn->error . "</span>\n";
    }
} else {
    echo " - عمود 'is_paid' موجود بالفعل في جدول 'sick_leaves'.\n";
}

// ==== 7. إضافة عمود note لجدول sick_leaves إذا لم يكن موجودًا ====
$check_column = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '{$db_name}' AND TABLE_NAME = 'sick_leaves' AND COLUMN_NAME = 'note'");
if ($check_column->num_rows == 0) {
    if ($conn->query("ALTER TABLE sick_leaves ADD COLUMN note TEXT DEFAULT NULL")) {
        echo " - تم إضافة عمود 'note' إلى جدول 'sick_leaves'.\n";
    } else {
        echo " - <span style='color: red;'>فشل إضافة عمود 'note': " . $conn->error . "</span>\n";
    }
} else {
    echo " - عمود 'note' موجود بالفعل في جدول 'sick_leaves'.\n";
}

echo "\n<h2 style='text-align: center; color: #2e7d32;'>عملية تحديث قاعدة البيانات اكتملت بنجاح.</h2>";
echo "</pre>";


// التحقق وإضافة عمود note إذا لم يكن موجودًا
$res = $conn->query("SHOW COLUMNS FROM doctors LIKE 'note'");
if ($res->num_rows == 0) {
    if ($conn->query("ALTER TABLE doctors ADD COLUMN note TEXT DEFAULT NULL")) {
        echo "تم إضافة العمود note بنجاح إلى جدول doctors.";
    } else {
        echo "فشل إضافة العمود note: " . $conn->error;
    }
} else {
    echo "العمود note موجود بالفعل في جدول doctors.";
}


$conn->close();
?>
