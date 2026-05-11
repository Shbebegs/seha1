<?php
// إخفاء أخطاء PHP عن المستخدمين
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header_remove('X-Powered-By');
header_remove('Server');

define('ERROR_LOG_FILE', __DIR__ . '/error_log.txt');
function log_error($msg) {
    @file_put_contents(ERROR_LOG_FILE, date('[Y-m-d H:i:s] ') . $msg . "\n", FILE_APPEND);
}

// ==== وظائف الاتصال بقاعدتين باستخدام PDO ====
function connect_db1() {
    try {
        $pdo = new PDO(
            "mysql:host=mysql.railway.internal;port=3306;dbname=railway;charset=utf8mb4",
            'root',
            'ExvKbuJnGIvDATyXWCHtpjOFluFAgeqQ',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
        );
        ensure_leave_queries_table($pdo);
        return $pdo;
    } catch (PDOException $e) {
        log_error("DB1 Connection error: " . $e->getMessage());
        return null;
    }
}

function connect_db2() {
    try {
        $pdo = new PDO(
            "mysql:host=c9cujduvu830eexs.cbetxkdyhwsb.us-east-1.rds.amazonaws.com;port=3306;dbname=cdidptf4q81rafg8;charset=utf8mb4",
            'q2xjpqcepsmd4v12',
            'v8lcs6awp4vj9u28',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
        );
        ensure_leave_queries_table($pdo);
        return $pdo;
    } catch (PDOException $e) {
        log_error("DB2 Connection error: " . $e->getMessage());
        return null;
    }
}

function ensure_leave_queries_table($pdo) {
    if (!$pdo) return;
    try {
        $pdo->exec("
          CREATE TABLE IF NOT EXISTS leave_queries (
            id INT AUTO_INCREMENT PRIMARY KEY,
            leave_id INT NOT NULL,
            queried_at DATETIME NOT NULL,
            source VARCHAR(20) NOT NULL DEFAULT 'external',
            INDEX (leave_id)
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    } catch (PDOException $e) {
        log_error("CreateTable leave_queries error: " . $e->getMessage());
    }
}

// ==== جلب وإعداد المعطيات من المستخدم ====
$code = trim($_POST['code']   ?? '');
$id   = trim($_POST['id']     ?? '');

if ($code === '') {
    echo json_encode(['status' => 'error', 'msg' => 'فضلاً اكتب رمز الخدمة']);
    exit;
}
if ($id === '') {
    echo json_encode(['status' => 'error', 'msg' => 'فضلاً اكتب رقم الهوية']);
    exit;
}
if (!preg_match('/^[A-Za-z0-9\-]{1,30}$/', $code)) {
    echo json_encode(['status' => 'error', 'msg' => 'رمز الخدمة غير صالح']);
    exit;
}
if (!preg_match('/^[0-9A-Za-z\-]{1,20}$/', $id)) {
    echo json_encode(['status' => 'error', 'msg' => 'رقم الهوية غير صالح']);
    exit;
}
$code = strtoupper($code);

// ==== دوال البحث وتسجيل الاستعلام (PDO) ====
function search_active_leave($pdo, $code, $id) {
    if (!$pdo) return null;
    $sql = "
      SELECT
        sl.id                 AS leave_id,
        sl.service_code       AS service_code,
        p.identity_number     AS identity_number,
        COALESCE(p.name_ar, p.name) AS patient_name,
        sl.issue_date         AS issue_date,
        sl.start_date         AS start_date,
        sl.end_date           AS end_date,
        sl.days_count         AS days_count,
        COALESCE(d.name_ar, d.name) AS doctor_name,
        COALESCE(d.title_ar, d.title) AS doctor_title,
        sl.is_companion       AS is_companion,
        sl.companion_name     AS companion_name,
        sl.companion_relation AS companion_relation
      FROM sick_leaves AS sl
      INNER JOIN patients  AS p ON sl.patient_id = p.id
      INNER JOIN doctors   AS d ON sl.doctor_id  = d.id
      WHERE sl.service_code   = ?
        AND p.identity_number = ?
        AND sl.deleted_at IS NULL
      LIMIT 1
    ";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$code, $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (PDOException $e) {
        log_error("Active search error: " . $e->getMessage());
        return null;
    }
}

function search_archived_leave($pdo, $code, $id) {
    if (!$pdo) return null;
    $sql = "
      SELECT sl.id AS leave_id
      FROM sick_leaves AS sl
      INNER JOIN patients  AS p ON sl.patient_id = p.id
      WHERE sl.service_code   = ?
        AND p.identity_number = ?
        AND sl.deleted_at IS NOT NULL
      LIMIT 1
    ";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$code, $id]);
        $row = $stmt->fetch();
        return $row['leave_id'] ?? null;
    } catch (PDOException $e) {
        log_error("Archived search error: " . $e->getMessage());
        return null;
    }
}

function log_leave_query($pdo, $leave_id, $source = 'external') {
    if (!$pdo) return;
    try {
        $stmt = $pdo->prepare("INSERT INTO leave_queries (leave_id, queried_at, source) VALUES (?, NOW(), ?)");
        $stmt->execute([$leave_id, $source]);
    } catch (PDOException $e) {
        log_error("Log query error: " . $e->getMessage());
    }
}

// ==== بناء HTML النتيجة ====
function build_result_html($row) {
    $serviceCode    = htmlspecialchars($row['service_code']);
    $identityNumber = htmlspecialchars($row['identity_number']);
    $patientName    = htmlspecialchars($row['patient_name']);
    $issueDate      = htmlspecialchars($row['issue_date']);
    $startDate      = htmlspecialchars($row['start_date']);
    $endDate        = htmlspecialchars($row['end_date']);
    $daysCount      = htmlspecialchars($row['days_count']);
    $doctorName     = htmlspecialchars($row['doctor_name']);
    $doctorTitle    = htmlspecialchars($row['doctor_title']);
    $companionBlock = '';
    if (!empty($row['is_companion']) && !empty($row['companion_name']) && !empty($row['companion_relation'])) {
        $compName = htmlspecialchars($row['companion_name']);
        $compRel  = htmlspecialchars($row['companion_relation']);
        $companionBlock = "
          <div class=\"col-md-6\"><span>اسم المرافق: </span>{$compName}</div>
          <div class=\"col-md-6\"><span>صلة القرابة: </span>{$compRel}</div>
        ";
    }
    return "
    <div class=\"row justify-content-center mt-1\">
      <div class=\"col-md-5 p-4\">
        <div class=\"form-group mb-3\" style=\"padding-bottom: 10px;\">
          <input type=\"text\" maxlength=\"20\" placeholder=\"رمز الخدمة\" class=\"form-control\" value=\"{$serviceCode}\" readonly>
        </div>
        <div class=\"form-group mb-3\">
          <input type=\"text\" maxlength=\"10\" pattern=\"\\d*\" placeholder=\"رقم الهوية / الإقامة\" class=\"form-control\" value=\"{$identityNumber}\" readonly>
        </div>
        <div class=\"results-inquiery row\">
          <div class=\"col-md-6\"><span>الاسم: </span>{$patientName}</div>
          {$companionBlock}
          <div class=\"col-md-6\"><span>تاريخ إصدار تقرير الإجازة:</span> {$issueDate}</div>
          <div class=\"col-md-6\"><span>تبدأ من:</span> {$startDate}</div>
          <div class=\"col-md-6\"><span>وحتى:</span> {$endDate}</div>
          <div class=\"col-md-6\"><span>المدة بالأيام:</span> {$daysCount}</div>
          <div class=\"col-md-6\"><span>اسم الطبيب:</span> {$doctorName}</div>
          <div class=\"col-md-6\"><span>المسمى الوظيفي:</span> {$doctorTitle}</div>
        </div>
        <a href=\"index.html\" class=\"btn btn-primary mt-3\">استعلام جديد</a>
      </div>
    </div>
    ";
}

// ==== البحث عبر القاعدة الأولى ====
$row = null;
$leave_id = null;
$pdo1 = connect_db1();

if (!$pdo1) {
    echo json_encode(['status' => 'error', 'msg' => 'تعذّر الاتصال بقاعدة البيانات الرئيسية.']);
    exit;
}

try {
    $row = search_active_leave($pdo1, $code, $id);
} catch (Throwable $e) {
    log_error("Exception DB1 active search: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'msg' => 'خطأ داخلي.']);
    exit;
}

if ($row) {
    $leave_id = $row['leave_id'];
    log_leave_query($pdo1, $leave_id, 'external');
    echo json_encode(['status' => 'ok', 'html' => build_result_html($row)]);
    exit;
}

// بحث في المؤرشفة
$archived_id = null;
try {
    $archived_id = search_archived_leave($pdo1, $code, $id);
} catch (Throwable $e) {
    log_error("Exception DB1 archived search: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'msg' => 'خطأ داخلي.']);
    exit;
}

if ($archived_id) {
    log_leave_query($pdo1, $archived_id, 'external');
    echo json_encode(['status' => 'notfound']);
    exit;
}

// ==== البحث عبر القاعدة الثانية ====
$pdo2 = connect_db2();
if (!$pdo2) {
    // إذا لم تتصل القاعدة الثانية، أرجع notfound بدلاً من خطأ
    echo json_encode(['status' => 'notfound']);
    exit;
}

try {
    $row = search_active_leave($pdo2, $code, $id);
} catch (Throwable $e) {
    log_error("Exception DB2 active search: " . $e->getMessage());
    echo json_encode(['status' => 'notfound']);
    exit;
}

if ($row) {
    $leave_id = $row['leave_id'];
    log_leave_query($pdo2, $leave_id, 'external');
    echo json_encode(['status' => 'ok', 'html' => build_result_html($row)]);
    exit;
}

$archived_id = null;
try {
    $archived_id = search_archived_leave($pdo2, $code, $id);
} catch (Throwable $e) {
    log_error("Exception DB2 archived search: " . $e->getMessage());
}

if ($archived_id) {
    log_leave_query($pdo2, $archived_id, 'external');
    echo json_encode(['status' => 'notfound']);
    exit;
}

echo json_encode(['status' => 'notfound']);
exit;
?>
