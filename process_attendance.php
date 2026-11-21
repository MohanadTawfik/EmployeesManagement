<?php
require_once 'config.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// الحصول على بيانات المستخدم الحالي
try {
    $stmt = $pdo->prepare("SELECT u.*, r.name as role_name, r.permissions FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

    $permissions = json_decode($currentUser['permissions'], true);
} catch (PDOException $e) {
    die("خطأ في جلب بيانات المستخدم: " . $e->getMessage());
}

// التحقق من الصلاحيات
if (!isset($permissions['attendance']) && !isset($permissions['attendance_own'])) {
    $_SESSION['error_message'] = "ليس لديك صلاحية لتسجيل الحضور والانصراف";
    header("Location: index.php");
    exit;
}

// معالجة طلبات الحضور والانصراف
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action == 'checkin') {
            // تسجيل الحضور
            $today = date('Y-m-d');
            $current_time = date('H:i:s');

            // التحقق من أن الموظف لم يسجل الحضور اليوم
            $stmt = $pdo->prepare("SELECT id FROM attendance WHERE employee_id = ? AND date = ? AND check_in IS NOT NULL");
            $stmt->execute([$_SESSION['employee_id'], $today]);
            $existing_record = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing_record) {
                $_SESSION['error_message'] = "لقد قمت بتسجيل الحضور بالفعل اليوم";
            } else {
                // التحقق من وجود سجل انصراف بدون حضور
                $stmt = $pdo->prepare("SELECT id FROM attendance WHERE employee_id = ? AND date = ? AND check_out IS NOT NULL AND check_in IS NULL");
                $stmt->execute([$_SESSION['employee_id'], $today]);
                $checkout_only = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($checkout_only) {
                    // تحديث سجل الانصراف فقط
                    $stmt = $pdo->prepare("UPDATE attendance SET check_in = ? WHERE id = ?");
                    $stmt->execute([$current_time, $checkout_only['id']]);
                    $_SESSION['success_message'] = "تم تسجيل الحضور بنجاح";
                    header("Location: index.php?checkin_success=1");
                    exit;
                } else {
                    // إنشاء سجل حضور جديد
                    $stmt = $pdo->prepare("INSERT INTO attendance (employee_id, date, check_in) VALUES (?, ?, ?)");
                    $stmt->execute([$_SESSION['employee_id'], $today, $current_time]);
                    $_SESSION['success_message'] = "تم تسجيل الحضور بنجاح";
                    header("Location: index.php?checkin_success=1");
                    exit;
                }
            }
        } elseif ($action == 'checkout') {
            // تسجيل الانصراف
            $today = date('Y-m-d');
            $current_time = date('H:i:s');

            // البحث عن سجل الحضور اليوم
            $stmt = $pdo->prepare("SELECT id, check_out FROM attendance WHERE employee_id = ? AND date = ? AND check_in IS NOT NULL");
            $stmt->execute([$_SESSION['employee_id'], $today]);
            $attendance_record = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$attendance_record) {
                $_SESSION['error_message'] = "يجب تسجيل الحضور أولاً";
            } elseif ($attendance_record['check_out']) {
                $_SESSION['error_message'] = "لقد قمت بتسجيل الانصراف بالفعل اليوم";
            } else {
                // تحديث وقت الانصراف
                $stmt = $pdo->prepare("UPDATE attendance SET check_out = ? WHERE id = ?");
                $stmt->execute([$current_time, $attendance_record['id']]);
                $_SESSION['success_message'] = "تم تسجيل الانصراف بنجاح";
                header("Location: index.php?checkout_success=1");
                exit;
            }
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "خطأ في معالجة الطلب: " . $e->getMessage();
    }
}

// العودة إلى الصفحة الرئيسية
header("Location: index.php");
exit;
