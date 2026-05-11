<?php
// توجيه الزائر مباشرة إلى صفحة الاستعلام
// استخدام مسار نسبي لتجنب مشاكل البورت خلف البروكسي
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
          (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') 
          ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
// إزالة البورت من الهوست إذا كان موجوداً (لأن Railway يستخدم بروكسي)
$host = preg_replace('/:\d+$/', '', $host);
header("Location: {$scheme}://{$host}/sickleave/index.html", true, 302);
exit;
