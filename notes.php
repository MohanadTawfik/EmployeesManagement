<?php

require_once 'config.php';

// معالجة إضافة ملاحظة جديدة
if (isset($_POST['add_note'])) {
    try {
        $stmt = $pdo->prepare("INSERT INTO notes (employee_id, author_id, content, type) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $_POST['employee_id'],
            $_SESSION['employee_id'],
            $_POST['content'],
            $_POST['type']
        ]);

        // إعادة تحميل الصفحة لعرض البيانات المحدثة
        header("Location: notes.php?add_success=1");
        exit;
    } catch (PDOException $e) {
        $error_message = "خطأ في إضافة الملاحظة: " . $e->getMessage();
    }
}

// معالجة تعديل ملاحظة
if (isset($_POST['edit_note'])) {
    try {
        $stmt = $pdo->prepare("UPDATE notes SET content = ?, type = ? WHERE id = ?");
        $stmt->execute([
            $_POST['content'],
            $_POST['type'],
            $_POST['note_id']
        ]);

        // إعادة تحميل الصفحة لعرض البيانات المحدثة
        header("Location: notes.php?edit_success=1");
        exit;
    } catch (PDOException $e) {
        $error_message = "خطأ في تحديث الملاحظة: " . $e->getMessage();
    }
}

// معالجة حذف ملاحظة
if (isset($_GET['delete'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM notes WHERE id = ?");
        $stmt->execute([$_GET['delete']]);

        // إعادة تحميل الصفحة لعرض البيانات المحدثة
        header("Location: notes.php?delete_success=1");
        exit;
    } catch (PDOException $e) {
        $error_message = "خطأ في حذف الملاحظة: " . $e->getMessage();
    }
}

// الحصول على قائمة الموظفين
$employees = [];
try {
    $stmt = $pdo->query("SELECT id, first_name, last_name FROM employees WHERE status = 'active' ORDER BY first_name");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "خطأ في جلب بيانات الموظفين: " . $e->getMessage();
}

// الحصول على قائمة الملاحظات
$notes = [];
$employee_id = isset($_GET['employee_id']) ? $_GET['employee_id'] : '';

try {
    $query = "SELECT n.*, e.first_name, e.last_name, a.first_name as author_first, a.last_name as author_last 
              FROM notes n 
              JOIN employees e ON n.employee_id = e.id 
              JOIN employees a ON n.author_id = a.id 
              WHERE 1=1";

    $params = [];

    // عرض الملاحظات حسب الموظف المحدد
    if (!empty($employee_id)) {
        $query .= " AND n.employee_id = ?";
        $params[] = $employee_id;
    }

    $query .= " ORDER BY n.created_at DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "خطأ في جلب الملاحظات: " . $e->getMessage();
}

// الحصول على بيانات ملاحظة محددة للتعديل
$edit_note = null;
if (isset($_GET['edit'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM notes WHERE id = ?");
        $stmt->execute([$_GET['edit']]);
        $edit_note = $stmt->fetch(PDO::FETCH_ASSOC);

        // لا حاجة للتحقق من الصلاحيات للتعديل، حيث أن هذه الصفحة مخصصة للمديرين فقط
    } catch (PDOException $e) {
        $error_message = "خطأ في جلب بيانات الملاحظة: " . $e->getMessage();
    }
}
?>

<?php
$page_title = "الملاحظات";
include 'includes/header.php';
?>

<!-- المحتوى الرئيسي -->
<div class="container-fluid mt-4">
    <!-- رسائل النجاح والخطأ -->
    <?php
    // عرض رسائل النجاح
    if (isset($_GET['add_success'])) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
              <i class="fas fa-check-circle me-2"></i>تم إضافة الملاحظة بنجاح
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
    } elseif (isset($_GET['edit_success'])) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
              <i class="fas fa-check-circle me-2"></i>تم تحديث الملاحظة بنجاح
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
    } elseif (isset($_GET['delete_success'])) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
              <i class="fas fa-check-circle me-2"></i>تم حذف الملاحظة بنجاح
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

    <!-- عنوان الصفحة وأزرار التحكم -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">الملاحظات</h1>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addNoteModal">
            <i class="fas fa-plus"></i> إضافة ملاحظة جديدة
        </button>
    </div>

    <!-- فلتر الموظفين (للمديرين فقط) -->
    <?php if (isset($permissions['all']) && $permissions['all']): ?>
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <form method="get" action="notes.php">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="employee_id" class="form-label">الموظف</label>
                            <select class="form-select" id="employee_id" name="employee_id">
                                <option value="">الكل</option>
                                <?php foreach ($employees as $employee): ?>
                                    <option value="<?php echo $employee['id']; ?>" <?php echo ($employee_id == $employee['id']) ? 'selected' : ''; ?>>
                                        <?php echo $employee['first_name'] . ' ' . $employee['last_name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i> بحث
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

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
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-start">
                                        <li>
                                            <a class="dropdown-item" href="?edit=<?php echo $note['id']; ?>" data-bs-toggle="modal" data-bs-target="#editNoteModal<?php echo $note['id']; ?>">
                                                <i class="fas fa-edit"></i> تعديل
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item text-danger" href="?delete=<?php echo $note['id']; ?>" onclick="return confirm('هل أنت متأكد من حذف هذه الملاحظة؟');">
                                                <i class="fas fa-trash"></i> حذف
                                            </a>
                                        </li>
                                    </ul>
                                </div>
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

                <!-- نافذة تعديل الملاحظة -->
                <div class="modal fade" id="editNoteModal<?php echo $note['id']; ?>" tabindex="-1" aria-labelledby="editNoteModalLabel<?php echo $note['id']; ?>" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="editNoteModalLabel<?php echo $note['id']; ?>">تعديل الملاحظة</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form method="post" action="notes.php">
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label for="content<?php echo $note['id']; ?>" class="form-label">المحتوى</label>
                                        <textarea class="form-control" id="content<?php echo $note['id']; ?>" name="content" rows="4" required><?php echo htmlspecialchars($note['content']); ?></textarea>
                                    </div>

                                    <div class="mb-3">
                                        <label for="type<?php echo $note['id']; ?>" class="form-label">النوع</label>
                                        <select class="form-select" id="type<?php echo $note['id']; ?>" name="type" required>
                                            <option value="positive" <?php echo ($note['type'] == 'positive') ? 'selected' : ''; ?>>إيجابية</option>
                                            <option value="negative" <?php echo ($note['type'] == 'negative') ? 'selected' : ''; ?>>سلبية</option>
                                            <option value="warning" <?php echo ($note['type'] == 'warning') ? 'selected' : ''; ?>>تحذير</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <input type="hidden" name="note_id" value="<?php echo $note['id']; ?>">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                                    <button type="submit" name="edit_note" class="btn btn-primary">حفظ التغييرات</button>
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
                    <p class="mb-0">لا توجد ملاحظات حالياً</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- نافذة إضافة ملاحظة جديدة -->
<div class="modal fade" id="addNoteModal" tabindex="-1" aria-labelledby="addNoteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addNoteModalLabel">إضافة ملاحظة جديدة</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="notes.php">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="employee_id" class="form-label">الموظف</label>
                        <select class="form-select" id="employee_id" name="employee_id" required>
                            <option value="">اختر الموظف</option>
                            <?php foreach ($employees as $employee): ?>
                                <option value="<?php echo $employee['id']; ?>" <?php echo ($employee_id == $employee['id']) ? 'selected' : ''; ?>>
                                    <?php echo $employee['first_name'] . ' ' . $employee['last_name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="content" class="form-label">المحتوى</label>
                        <textarea class="form-control" id="content" name="content" rows="4" required></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="type" class="form-label">النوع</label>
                        <select class="form-select" id="type" name="type" required>
                            <option value="positive">إيجابية</option>
                            <option value="negative">سلبية</option>
                            <option value="warning">تحذير</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" name="add_note" class="btn btn-primary">إضافة الملاحظة</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>