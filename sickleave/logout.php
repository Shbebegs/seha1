<?php
// logout.php
session_start();
// امسح جميع بيانات الجلسة
$_SESSION = [];
// احذف الكوكي الخاصة بالجلسة
if (ini_get("session.use_cookies")) {
    setcookie(session_name(), '', time() - 42000, '/');
}
// دمّر الجلسة
session_destroy();
// أعد التوجيه إلى صفحة تسجيل الدخول
header('Location: login.php');
exit;
