<?php
require_once 'config.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'غير مصرح بالوصول']);
    exit;
}

// الحصول على بيانات العهدة
if (isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM assets WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $asset = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($asset) {
            header('Content-Type: application/json');
            echo json_encode($asset);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'العهدة غير موجودة']);
        }
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['error' => $e->getMessage()]);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'لم يتم تحديد العهدة']);
}
?>
