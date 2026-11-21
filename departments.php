<?php


require_once 'config.php';



// معالجة إضافة قسم جديد
if (isset($_POST['add_department'])) {
    try {
        $stmt = $pdo->prepare("INSERT INTO departments (name, manager_id) VALUES (?, ?)");
        $stmt->execute([
            $_POST['name'],
            !empty($_POST['manager_id']) ? $_POST['manager_id'] : null
        ]);

        // إعادة تحميل الصفحة لعرض البيانات المحدثة
        header("Location: departments.php?add_success=1");
        exit;
    } catch (PDOException $e) {
        $error_message = "خطأ في إضافة القسم: " . $e->getMessage();
    }
}

// معالجة تعديل بيانات قسم
if (isset($_POST['edit_department'])) {
    try {
        $stmt = $pdo->prepare("UPDATE departments SET name = ?, manager_id = ? WHERE id = ?");
        $stmt->execute([
            $_POST['name'],
            !empty($_POST['manager_id']) ? $_POST['manager_id'] : null,
            $_POST['department_id']
        ]);

        // إعادة تحميل الصفحة لعرض البيانات المحدثة
        header("Location: departments.php?edit_success=1");
        exit;
    } catch (PDOException $e) {
        $error_message = "خطأ في تحديث بيانات القسم: " . $e->getMessage();
    }
}

// معالجة حذف قسم
if (isset($_GET['delete'])) {
    try {
        // التحقق من عدم وجود موظفين في القسم
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM employees WHERE department_id = ?");
        $stmt->execute([$_GET['delete']]);
        $employees_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        if ($employees_count > 0) {
            $error_message = "لا يمكن حذف القسم لأنه يحتوي على موظفين";
        } else {
            $stmt = $pdo->prepare("DELETE FROM departments WHERE id = ?");
            $stmt->execute([$_GET['delete']]);

            // إعادة تحميل الصفحة لعرض البيانات المحدثة
            header("Location: departments.php?delete_success=1");
            exit;
        }
    } catch (PDOException $e) {
        $error_message = "خطأ في حذف القسم: " . $e->getMessage();
    }
}

// الحصول على قائمة الأقسام
$departments = [];
try {
    $stmt = $pdo->query("SELECT d.*, e.first_name, e.last_name FROM departments d LEFT JOIN employees e ON d.manager_id = e.id ORDER BY d.name");
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "خطأ في جلب بيانات الأقسام: " . $e->getMessage();
}

// الحصول على قائمة الموظفين لاختيار مدير القسم
$employees = [];
try {
    $stmt = $pdo->query("SELECT id, first_name, last_name FROM employees WHERE status = 'active' ORDER BY first_name");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "خطأ في جلب بيانات الموظفين: " . $e->getMessage();
}

// الحصول على بيانات قسم محدد للتعديل
$edit_department = null;
if (isset($_GET['edit'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM departments WHERE id = ?");
        $stmt->execute([$_GET['edit']]);
        $edit_department = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error_message = "خطأ في جلب بيانات القسم: " . $e->getMessage();
    }
}
?>

<?php
$page_title = "إدارة الأقسام";
include 'includes/header.php';
?>

<div class="container-fluid">
    <?php
    // عرض رسائل النجاح
    if (isset($_GET['add_success'])) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
              <i class="fas fa-check-circle me-2"></i>تم إضافة القسم بنجاح
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
    } elseif (isset($_GET['edit_success'])) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
              <i class="fas fa-check-circle me-2"></i>تم تحديث بيانات القسم بنجاح
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
    } elseif (isset($_GET['delete_success'])) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
              <i class="fas fa-check-circle me-2"></i>تم حذف القسم بنجاح
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
        <h1 class="h3">إدارة الأقسام</h1>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDepartmentModal">
            <i class="fas fa-plus"></i> إضافة قسم جديد
        </button>
    </div>

    <!-- بطاقات الأقسام -->
    <div class="row">
        <?php if (count($departments) > 0): ?>
            <?php foreach ($departments as $department): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <h5 class="card-title mb-0"><?php echo htmlspecialchars($department['name']); ?></h5>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-start">
                                        <li>
                                            <a class="dropdown-item" href="?edit=<?php echo $department['id']; ?>" data-bs-toggle="modal" data-bs-target="#editDepartmentModal<?php echo $department['id']; ?>">
                                                <i class="fas fa-edit"></i> تعديل
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item text-danger" href="?delete=<?php echo $department['id']; ?>" onclick="return confirm('هل أنت متأكد من حذف هذا القسم؟');">
                                                <i class="fas fa-trash"></i> حذف
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <p class="card-text text-muted mb-2">
                                <?php if (!empty($department['first_name']) && !empty($department['last_name'])): ?>
                                    <i class="fas fa-user-tie"></i> مدير القسم: <?php echo htmlspecialchars($department['first_name'] . ' ' . $department['last_name']); ?>
                                <?php else: ?>
                                    <i class="fas fa-user-tie"></i> مدير القسم: غير محدد
                                <?php endif; ?>
                            </p>

                            <div class="mt-3">
                                <a href="employees.php?department=<?php echo $department['id']; ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-users"></i> عرض الموظفين
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- نافذة تعديل القسم -->
                <div class="modal fade" id="editDepartmentModal<?php echo $department['id']; ?>" tabindex="-1" aria-labelledby="editDepartmentModalLabel<?php echo $department['id']; ?>" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="editDepartmentModalLabel<?php echo $department['id']; ?>">تعديل بيانات القسم</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form method="post" action="departments.php">
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label for="name<?php echo $department['id']; ?>" class="form-label">اسم القسم</label>
                                        <input type="text" class="form-control" id="name<?php echo $department['id']; ?>" name="name" value="<?php echo htmlspecialchars($department['name']); ?>" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="manager_id<?php echo $department['id']; ?>" class="form-label">مدير القسم</label>
                                        <select class="form-select" id="manager_id<?php echo $department['id']; ?>" name="manager_id">
                                            <option value="">اختر مدير القسم</option>
                                            <?php foreach ($employees as $employee): ?>
                                                <option value="<?php echo $employee['id']; ?>" <?php echo ($department['manager_id'] == $employee['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <input type="hidden" name="department_id" value="<?php echo $department['id']; ?>">
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                                    <button type="submit" name="edit_department" class="btn btn-primary">حفظ التغييرات</button>
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
                    <h5>لا توجد أقسام حالياً</h5>
                    <p>قم بإضافة قسم جديد للبدء في تنظيم الموظفين</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- نافذة إضافة قسم جديد -->
<div class="modal fade" id="addDepartmentModal" tabindex="-1" aria-labelledby="addDepartmentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addDepartmentModalLabel">إضافة قسم جديد</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="departments.php">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">اسم القسم</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>

                    <div class="mb-3">
                        <label for="manager_id" class="form-label">مدير القسم</label>
                        <select class="form-select" id="manager_id" name="manager_id">
                            <option value="">اختر مدير القسم</option>
                            <?php foreach ($employees as $employee): ?>
                                <option value="<?php echo $employee['id']; ?>"><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" name="add_department" class="btn btn-primary">إضافة القسم</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>