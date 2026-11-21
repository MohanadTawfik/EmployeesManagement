<?php
require_once 'config.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// الحصول على صلاحيات المستخدم
$permissions = [];
try {
    $stmt = $pdo->prepare("SELECT permissions FROM roles WHERE id = ?");
    $stmt->execute([$_SESSION['role_id']]);
    $role = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($role) {
        $permissions = json_decode($role['permissions'], true) ?: [];
    }
} catch (PDOException $e) {
    $permissions = [];
}

// معالجة رفع ملف جديد
if (isset($_POST['upload']) && isset($_FILES['attachment'])) {
    $employee_id = $_POST['employee_id'];
    $description = $_POST['description'];

    try {
        // التحقق من صلاحية رفع الملف
        if (!isset($permissions['attachments']) || !$permissions['attachments']) {
            // الموظفون العاديون يمكنهم رفع ملفات لأنفسهم فقط
            if ($employee_id != $_SESSION['employee_id']) {
                throw new Exception("لا تملك صلاحية رفع ملفات لهذا الموظف");
            }
        }

        // التحقق من وجود الملف
        if ($_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("خطأ في رفع الملف: " . $_FILES['attachment']['error']);
        }

        // التحقق من حجم الملف
        if ($_FILES['attachment']['size'] > MAX_FILE_SIZE) {
            throw new Exception("حجم الملف يتجاوز الحد الأقصى المسموح به (" . (MAX_FILE_SIZE / 1024 / 1024) . " ميجابايت)");
        }

        // التحقق من نوع الملف
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $file_type = $finfo->file($_FILES['attachment']['tmp_name']);

        if (!in_array($file_type, $allowed_types)) {
            throw new Exception("نوع الملف غير مسموح به");
        }

        // إنشاء اسم فريد للملف
        $file_extension = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
        $file_name = uniqid() . '.' . $file_extension;
        $upload_path = UPLOAD_PATH . $file_name;

        // التأكد من وجود مجلد الرفع
        if (!is_dir(UPLOAD_PATH)) {
            mkdir(UPLOAD_PATH, 0755, true);
        }

        // رفع الملف
        if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $upload_path)) {
            throw new Exception("فشل في رفع الملف");
        }

        // حفظ معلومات الملف في قاعدة البيانات
        $stmt = $pdo->prepare("INSERT INTO attachments (employee_id, filename, original_name, type, path, description, uploaded_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([
            $employee_id,
            $file_name,
            $_FILES['attachment']['name'],
            $file_type,
            $upload_path,
            $description,
            $_SESSION['user_id']
        ]);

        // إعادة تحميل الصفحة لعرض البيانات المحدثة
        header("Location: attachments.php?upload_success=1");
        exit;

        // تسجيل العملية
        $stmt = $pdo->prepare("INSERT INTO system_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$_SESSION['user_id'], 'upload_attachment', "تم رفع ملف: {$_FILES['attachment']['name']} للموظف: $employee_id"]);

    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// حذف ملف
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $attachment_id = $_GET['delete'];

    try {
        // الحصول على معلومات الملف
        $stmt = $pdo->prepare("SELECT * FROM attachments WHERE id = ?");
        $stmt->execute([$attachment_id]);
        $attachment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$attachment) {
            throw new Exception("الملف غير موجود");
        }

        // التحقق من الصلاحيات
        if (!isset($permissions['attachments']) || !$permissions['attachments']) {
            // الموظفون العاديون يمكنهم حذف ملفاتهم فقط
            if ($attachment['employee_id'] != $_SESSION['employee_id']) {
                throw new Exception("لا تملك صلاحية حذف هذا الملف");
            }
        }

        // حذف الملف من الخادم
        if (file_exists($attachment['path'])) {
            unlink($attachment['path']);
        }

        // حذف الملف من قاعدة البيانات
        $stmt = $pdo->prepare("DELETE FROM attachments WHERE id = ?");
        $stmt->execute([$attachment_id]);

        // إعادة تحميل الصفحة لعرض البيانات المحدثة
        header("Location: attachments.php?delete_success=1");
        exit;

        // تسجيل العملية
        $stmt = $pdo->prepare("INSERT INTO system_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$_SESSION['user_id'], 'delete_attachment', "تم حذف ملف: {$attachment['original_name']}"]);

    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// الحصول على قائمة الملفات
try {
    if (isset($permissions['all']) && $permissions['all']) {
        // المدير يرى جميع الملفات
        $stmt = $pdo->prepare("SELECT a.*, e.first_name, e.last_name FROM attachments a JOIN employees e ON a.employee_id = e.id ORDER BY a.created_at DESC");
        $stmt->execute();
    } else {
        // الموظف يرى ملفاته فقط
        $stmt = $pdo->prepare("SELECT a.*, e.first_name, e.last_name FROM attachments a JOIN employees e ON a.employee_id = e.id WHERE a.employee_id = ? ORDER BY a.created_at DESC");
        $stmt->execute([$_SESSION['employee_id']]);
    }

    $attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $attachments = [];
    $error_message = "خطأ في جلب الملفات: " . $e->getMessage();
}

// الحصول على قائمة الموظفين (للإدارة)
$employees = [];
if (isset($permissions['all']) && $permissions['all']) {
    try {
        $stmt = $pdo->query("SELECT id, first_name, last_name FROM employees WHERE status = 'active' ORDER BY first_name");
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // تجاهل الخطأ
    }
} else {
    // للموظف العادي، نضيفه فقط في القائمة
    $employees = [
        ['id' => $_SESSION['employee_id'], 'first_name' => explode(' ', $_SESSION['full_name'])[0], 'last_name' => explode(' ', $_SESSION['full_name'])[1] ?? '']
    ];
}

$page_title = "الملفات المرفقة";
include 'includes/header.php';
?>

<div class="row">
    <div class="col-lg-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">الملفات المرفقة</h1>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
                <i class="fas fa-plus me-2"></i>رفع ملف جديد
            </button>
        </div>

        <?php
        // عرض رسائل النجاح
        if (isset($_GET['upload_success'])) {
            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                  <i class="fas fa-check-circle me-2"></i>تم رفع الملف بنجاح
                  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>';
        } elseif (isset($_GET['delete_success'])) {
            echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                  <i class="fas fa-check-circle me-2"></i>تم حذف الملف بنجاح
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

        <?php if (empty($attachments)): ?>
            <div class="text-center py-5">
                <i class="fas fa-file fa-3x text-muted mb-3"></i>
                <h4 class="text-muted">لا توجد ملفات مرفقة</h4>
                <p class="text-muted">قم برفع ملف جديد باستخدام الزر أعلاه</p>
            </div>
        <?php else: ?>
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>اسم الملف</th>
                                    <th>الموظف</th>
                                    <th>الوصف</th>
                                    <th>نوع الملف</th>
                                    <th>الحجم</th>
                                    <th>تاريخ الرفع</th>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($attachments as $attachment): ?>
                                    <tr>
                                        <td>
                                            <a href="<?php echo $attachment['path']; ?>" target="_blank">
                                                <?php echo htmlspecialchars($attachment['original_name']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo htmlspecialchars($attachment['first_name'] . ' ' . $attachment['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($attachment['description']); ?></td>
                                        <td><?php echo htmlspecialchars($attachment['type']); ?></td>
                                        <td><?php echo number_format(filesize($attachment['path']) / 1024 / 1024, 2); ?> ميجابايت</td>
                                        <td><?php echo date('Y/m/d H:i', strtotime($attachment['created_at'])); ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="<?php echo $attachment['path']; ?>" class="btn btn-outline-primary" target="_blank" title="عرض">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="<?php echo $attachment['path']; ?>" class="btn btn-outline-success" download title="تحميل">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                                <?php if (isset($permissions['all']) && $permissions['all'] || $attachment['employee_id'] == $_SESSION['employee_id']): ?>
                                                    <a href="attachments.php?delete=<?php echo $attachment['id']; ?>" class="btn btn-outline-danger delete-btn" data-item-name="الملف" title="حذف">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal رفع ملف جديد -->
<div class="modal fade" id="uploadModal" tabindex="-1" aria-labelledby="uploadModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="uploadModalLabel">رفع ملف جديد</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="employee_id" class="form-label">الموظف</label>
                        <select class="form-select" id="employee_id" name="employee_id" required>
                            <?php foreach ($employees as $employee): ?>
                                <option value="<?php echo $employee['id']; ?>" <?php echo $employee['id'] == $_SESSION['employee_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="attachment" class="form-label">الملف</label>
                        <input class="form-control" type="file" id="attachment" name="attachment" required>
                        <div class="form-text">أنواع الملفات المسموحة: الصور، PDF، مستندات Word. الحد الأقصى: <?php echo (MAX_FILE_SIZE / 1024 / 1024); ?> ميجابايت</div>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">الوصف</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" name="upload" class="btn btn-primary loading-btn">رفع الملف</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
