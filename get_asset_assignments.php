<?php
require_once 'config.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'غير مصرح بالوصول']);
    exit;
}

// الحصول على تخصيصات العهدة
if (isset($_GET['asset_id'])) {
    try {
        $stmt = $pdo->prepare("
            SELECT aa.id, aa.asset_id, aa.employee_id, aa.assigned_date, aa.return_date, aa.status, aa.notes,
                   CONCAT(e.first_name, ' ', e.last_name) as employee_name
            FROM asset_assignments aa
            JOIN employees e ON aa.employee_id = e.id
            WHERE aa.asset_id = ?
            ORDER BY aa.assigned_date DESC
        ");
        $stmt->execute([$_GET['asset_id']]);
        $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: application/json');
        echo json_encode($assignments);
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['error' => $e->getMessage()]);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'لم يتم تحديد العهدة']);
}
?>
