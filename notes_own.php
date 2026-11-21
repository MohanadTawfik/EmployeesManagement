<?php

require_once 'config.php';


// الحصول على قائمة الملاحظات الخاصة بالموظف
$notes = [];
try {
    $query = "SELECT n.*, e.first_name, e.last_name, a.first_name as author_first, a.last_name as author_last
              FROM notes n
              JOIN employees e ON n.employee_id = e.id
              JOIN employees a ON n.author_id = a.id
              WHERE n.employee_id = ?
              ORDER BY n.created_at DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$_SESSION['employee_id']]);
    $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "خطأ في جلب الملاحظات: " . $e->getMessage();
}
?>

<?php
$page_title = "ملاحظاتي";
include 'includes/header.php';
?>

<!-- المحتوى الرئيسي -->
<div class="container-fluid mt-4">
    <!-- رسائل النجاح والخطأ -->
    <?php
    // عرض رسائل النجاح
    if (isset($_GET['add_success'])) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
              <i class="fas fa-check-circle me-2"></i>تمت إضافة الملاحظة بنجاح
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
    }
    ?>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- عنوان الصفحة -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">ملاحظاتي</h1>
    </div>

    <!-- قائمة الملاحظات -->
    <div class="row">
        <?php if (count($notes) > 0): ?>
            <?php foreach ($notes as $note): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100 shadow-sm note-<?php echo $note['type']; ?>">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <h6 class="card-title mb-0">
                                    <?php echo htmlspecialchars($note['first_name'] . ' ' . $note['last_name']); ?>
                                </h6>
                            </div>

                            <p class="card-text mb-2"><?php echo nl2br(htmlspecialchars($note['content'])); ?></p>

                            <div class="d-flex justify-content-between align-items-center mt-auto">
                                <small class="text-muted">
                                    <i class="fas fa-user"></i>
                                    <?php echo htmlspecialchars($note['author_first'] . ' ' . $note['author_last']); ?>
                                </small>
                                <small class="text-muted">
                                    <i class="fas fa-calendar"></i>
                                    <?php echo date('Y/m/d', strtotime($note['created_at'])); ?>
                                </small>
                            </div>

                            <div class="mt-2">
                                <?php if ($note['type'] == 'positive'): ?>
                                    <span class="badge bg-success">إيجابية</span>
                                <?php elseif ($note['type'] == 'negative'): ?>
                                    <span class="badge bg-danger">سلبية</span>
                                <?php else: ?>
                                    <span class="badge bg-warning">تحذير</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="alert alert-info text-center">
                    <i class="fas fa-info-circle fa-2x mb-3"></i>
                    <p class="mb-0">لا توجد ملاحظات حالياً</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>