<?php
ini_set('display_errors', 'on');
error_reporting(E_ALL);


// إعدادات الاتصال بقاعدة البيانات
// Database connection settings

define('DB_HOST', 'localhost');
define('DB_NAME', 'employees_management');
define('DB_USER', 'root');
define('DB_PASS', '');

// إعدادات الجلسة
// Session settings
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// إعدادات أخرى
// Other settings
define('APP_NAME', 'نظام إدارة الموظفين');
define('APP_VERSION', '1.0.0');
define('UPLOAD_PATH', 'uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB

// المنطقة الزمنية
// Timezone
date_default_timezone_set('Asia/Riyadh');

// الاتصال بقاعدة البيانات
// Connect to database
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("فشل الاتصال بقاعدة البيانات: " . $e->getMessage());
}
