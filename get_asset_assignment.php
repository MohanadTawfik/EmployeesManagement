<?php
require_once 'config.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'غير مصرح بالوصول']);
    exit;
}

// التحقق من وجود معرف العهدة
if (!isset($_GET['id']) || empty($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'معرف العهدة مطلوب']);
    exit;
}

$asset_id = $_GET['id'];

try {
    // جلب بيانات العهدة مع بيانات التسليم
    $stmt = $pdo->prepare("SELECT a.*, aa.assigned_date, aa.notes as assignment_notes
                            FROM assets a
                            JOIN asset_assignments aa ON a.id = aa.asset_id
                            WHERE a.id = ? AND aa.employee_id = ? AND aa.status = 'assigned'");
    $stmt->execute([$asset_id, $_SESSION['employee_id']]);
    $asset = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($asset) {
        // تنسيق التاريخ
        $asset['assigned_date'] = date('Y-m-d', strtotime($asset['assigned_date']));

        // إرجاع البيانات بصيغة JSON
        header('Content-Type: application/json');
        echo json_encode($asset);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'العهدة غير موجودة أو غير مسندة لك']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'خطأ في جلب البيانات: ' . $e->getMessage()]);
}
?>