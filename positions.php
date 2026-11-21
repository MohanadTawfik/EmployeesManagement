<?php

require_once 'config.php';


// معالجة إضافة منصب جديد
if (isset($_POST['add_position'])) {
    try {
        $stmt = $pdo->prepare("INSERT INTO positions (title, description, min_salary, max_salary) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $_POST['title'],
            $_POST['description'],
            !empty($_POST['min_salary']) ? $_POST['min_salary'] : null,
            !empty($_POST['max_salary']) ? $_POST['max_salary'] : null
        ]);

        // إعادة تحميل الصفحة لعرض البيانات المحدثة
        header("Location: positions.php?add_success=1");
        exit;
    } catch (PDOException $e) {
        $error_message = "خطأ في إضافة المنصب: " . $e->getMessage();
    }
}

// معالجة تعديل بيانات منصب
if (isset($_POST['edit_position'])) {
    try {
        $stmt = $pdo->prepare("UPDATE positions SET title = ?, description = ?, min_salary = ?, max_salary = ? WHERE id = ?");
        $stmt->execute([
            $_POST['title'],
            $_POST['description'],
            !empty($_POST['min_salary']) ? $_POST['min_salary'] : null,
            !empty($_POST['max_salary']) ? $_POST['max_salary'] : null,
            $_POST['position_id']
        ]);

        // إعادة تحميل الصفحة لعرض البيانات المحدثة
        header("Location: positions.php?edit_success=1");
        exit;
    } catch (PDOException $e) {
        $error_message = "خطأ في تحديث بيانات المنصب: " . $e->getMessage();
    }
}

// معالجة حذف منصب
if (isset($_GET['delete'])) {
    try {
        // التحقق من عدم وجود موظفين في المنصب
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM employees WHERE position_id = ?");
        $stmt->execute([$_GET['delete']]);
        $employees_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        if ($employees_count > 0) {
            $error_message = "لا يمكن حذف المنصب لأنه مرتبط بموظفين";
        } else {
            $stmt = $pdo->prepare("DELETE FROM positions WHERE id = ?");
            $stmt->execute([$_GET['delete']]);

            // إعادة تحميل الصفحة لعرض البيانات المحدثة
            header("Location: positions.php?delete_success=1");
            exit;
        }
    } catch (PDOException $e) {
        $error_message = "خطأ في حذف المنصب: " . $e->getMessage();
    }
}

// الحصول على قائمة المناصب
$positions = [];
try {
    $stmt = $pdo->query("SELECT p.*, (SELECT COUNT(*) FROM employees e WHERE e.position_id = p.id) as employees_count FROM positions p ORDER BY p.title");
    $positions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "خطأ في جلب بيانات المناصب: " . $e->getMessage();
}

// الحصول على بيانات منصب محدد للتعديل
$edit_position = null;
if (isset($_GET['edit'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM positions WHERE id = ?");
        $stmt->execute([$_GET['edit']]);
        $edit_position = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error_message = "خطأ في جلب بيانات المنصب: " . $e->getMessage();
    }
}
?>

<?php
$page_title = "إدارة المناصب";
include 'includes/header.php';
?>

<div class="container-fluid">
    <?php
    // عرض رسائل النجاح
    if (isset($_GET['add_success'])) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
              <i class="fas fa-check-circle me-2"></i>تم إضافة المنصب بنجاح
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
    } elseif (isset($_GET['edit_success'])) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
              <i class="fas fa-check-circle me-2"></i>تم تحديث بيانات المنصب بنجاح
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
    } elseif (isset($_GET['delete_success'])) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
              <i class="fas fa-check-circle me-2"></i>تم حذف المنصب بنجاح
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
    }
    ?>
</div>

<!-- المحتوى الرئيسي -->
<div class="container-fluid mt-4">
    <!-- رسائل النجاح والخطأ -->
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- عنوان الصفحة وأزرار التحكم -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">إدارة المناصب</h1>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPositionModal">
            <i class="fas fa-plus"></i> إضافة منصب جديد
        </button>
    </div>

    <!-- بطاقات المناصب -->
    <div class="row">
        <?php if (count($positions) > 0): ?>
            <?php foreach ($positions as $position): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <h5 class="card-title mb-0"><?php echo htmlspecialchars($position['title']); ?></h5>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-start">
                                        <li>
                                            <a class="dropdown-item" href="?edit=<?php echo $position['id']; ?>" data-bs-toggle="modal" data-bs-target="#editPositionModal<?php echo $position['id']; ?>">
                                                <i class="fas fa-edit"></i> تعديل
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item text-danger" href="?delete=<?php echo $position['id']; ?>" onclick="return confirm('هل أنت متأكد من حذف هذا المنصب؟');">
                                                <i class="fas fa-trash"></i> حذف
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <?php if (!empty($position['description'])): ?>
                                <p class="card-text text-muted mb-2"><?php echo htmlspecialchars($position['description']); ?></p>
                            <?php endif; ?>

                            <?php if (!is_null($position['min_salary']) || !is_null($position['max_salary'])): ?>
                                <p class="card-text text-muted mb-2">
                                    <i class="fas fa-money-bill-wave"></i>
                                    <?php if (!is_null($position['min_salary']) && !is_null($position['max_salary'])): ?>
                                        <?php echo number_format($position['min_salary'], 2); ?> - <?php echo number_format($position['max_salary'], 2); ?> ريال
                                    <?php elseif (!is_null($position['min_salary'])): ?>
                                        من <?php echo number_format($position['min_salary'], 2); ?> ريال
                                    <?php else: ?>
                                        حتى <?php echo number_format($position['max_salary'], 2); ?> ريال
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>

                            <div class="mt-3 d-flex justify-content-between align-items-center">
                                <a href="employees.php?position=<?php echo $position['id']; ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-users"></i> عرض الموظفين
                                </a>
                                <span class="badge bg-secondary"><?php echo $position['employees_count']; ?> موظف</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- نافذة تعديل المنصب -->
                <div class="modal fade" id="editPositionModal<?php echo $position['id']; ?>" tabindex="-1" aria-labelledby="editPositionModalLabel<?php echo $position['id']; ?>" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="editPositionModalLabel<?php echo $position['id']; ?>">تعديل بيانات المنصب</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form method="post" action="positions.php">
                                <div class="modal-body">
                                    <input type="hidden" name="position_id" value="<?php echo $position['id']; ?>">

                                    <div class="mb-3">
                                        <label for="title<?php echo $position['id']; ?>" class="form-label">عنوان المنصب</label>
                                        <input type="text" class="form-control" id="title<?php echo $position['id']; ?>" name="title" value="<?php echo htmlspecialchars($position['title']); ?>" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="description<?php echo $position['id']; ?>" class="form-label">وصف المنصب</label>
                                        <textarea class="form-control" id="description<?php echo $position['id']; ?>" name="description" rows="3"><?php echo htmlspecialchars($position['description']); ?></textarea>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="min_salary<?php echo $position['id']; ?>" class="form-label">الحد الأدنى للراتب</label>
                                            <input type="number" class="form-control" id="min_salary<?php echo $position['id']; ?>" name="min_salary" value="<?php echo $position['min_salary']; ?>" step="0.01" min="0">
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label for="max_salary<?php echo $position['id']; ?>" class="form-label">الحد الأقصى للراتب</label>
                                            <input type="number" class="form-control" id="max_salary<?php echo $position['id']; ?>" name="max_salary" value="<?php echo $position['max_salary']; ?>" step="0.01" min="0">
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                                    <button type="submit" name="edit_position" class="btn btn-primary">حفظ التغييرات</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="alert alert-info text-center">
                    <i class="fas fa-info-circle fa-2x mb-3"></i>
                    <h5>لا توجد مناصب حالياً</h5>
                    <p>قم بإضافة أول منصب للبدء في إدارة المناصب في النظام</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- نافذة إضافة منصب جديد -->
<div class="modal fade" id="addPositionModal" tabindex="-1" aria-labelledby="addPositionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addPositionModalLabel">إضافة منصب جديد</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="positions.php">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="title" class="form-label">عنوان المنصب</label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">وصف المنصب</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="min_salary" class="form-label">الحد الأدنى للراتب</label>
                            <input type="number" class="form-control" id="min_salary" name="min_salary" step="0.01" min="0">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="max_salary" class="form-label">الحد الأقصى للراتب</label>
                            <input type="number" class="form-control" id="max_salary" name="max_salary" step="0.01" min="0">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" name="add_position" class="btn btn-primary">إضافة المنصب</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>