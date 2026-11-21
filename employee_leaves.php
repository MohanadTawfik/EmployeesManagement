<?php

require_once 'config.php';

// تحميل الصلاحيات من الجلسة
$permissions = [];
if (isset($_SESSION['role_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT permissions FROM roles WHERE id = ?");
        $stmt->execute([$_SESSION['role_id']]);
        $role = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($role) {
            $permissions = json_decode($role['permissions'], true) ?: [];

            // التحقق من صلاحيات المدير
            if (isset($permissions['all']) && $permissions['all']) {
                $permissions['employees'] = true;
                $permissions['attendance'] = true;
                $permissions['assets'] = true;
                $permissions['leaves'] = true;
                $permissions['notes'] = true;
                $permissions['profile'] = true;
                $permissions['all'] = true;
            }
            // صلاحيات الموظف العادي
            else if (isset($permissions['employee']) && $permissions['employee']) {
                $permissions['attendance_own'] = true;
                $permissions['leaves_own'] = true;
                $permissions['notes_own'] = true;
                $permissions['profile'] = true;
            }
        }
    } catch (PDOException $e) {
        die("خطأ في تحميل الصلاحيات: " . $e->getMessage());
    }
}

// التحقق من تسجيل الدخول
if (!isset($_SESSION['employee_id'])) {
    header("Location: login.php");
    exit;
}

// معالجة طلب إجازة جديد
if (isset($_POST['request_leave'])) {
    try {
        $start_date = new DateTime($_POST['start_date']);
        $end_date = new DateTime($_POST['end_date']);
        $interval = $start_date->diff($end_date);
        $days = $interval->days + 1; // +1 لتشمل يوم البداية

        $stmt = $pdo->prepare("INSERT INTO leaves (employee_id, type, start_date, end_date, days, reason) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_SESSION['employee_id'],
            $_POST['type'],
            $_POST['start_date'],
            $_POST['end_date'],
            $days,
            $_POST['reason']
        ]);

        // إعادة تحميل الصفحة لعرض البيانات المحدثة
        header("Location: employee_leaves.php?request_success=1");
        exit;
    } catch (PDOException $e) {
        $error_message = "خطأ في تقديم طلب الإجازة: " . $e->getMessage();
    }
}

// معالجة تعديل طلب إجازة
if (isset($_POST['edit_leave'])) {
    try {
        $start_date = new DateTime($_POST['start_date']);
        $end_date = new DateTime($_POST['end_date']);
        $interval = $start_date->diff($end_date);
        $days = $interval->days + 1; // +1 لتشمل يوم البداية

        $stmt = $pdo->prepare("UPDATE leaves SET type = ?, start_date = ?, end_date = ?, days = ?, reason = ? WHERE id = ? AND employee_id = ?");
        $stmt->execute([
            $_POST['type'],
            $_POST['start_date'],
            $_POST['end_date'],
            $days,
            $_POST['reason'],
            $_POST['leave_id'],
            $_SESSION['employee_id']
        ]);

        // إعادة تحميل الصفحة لعرض البيانات المحدثة
        header("Location: employee_leaves.php?edit_success=1");
        exit;
    } catch (PDOException $e) {
        $error_message = "خطأ في تعديل طلب الإجازة: " . $e->getMessage();
    }
}

// معالجة حذف طلب إجازة
if (isset($_GET['delete'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM leaves WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        $leave = $stmt->fetch(PDO::FETCH_ASSOC);

        // التحقق من ملكية الطلب
        if ($leave['employee_id'] != $_SESSION['employee_id']) {
            header("Location: employee_leaves.php");
            exit;
        }

        // لا يمكن حذف الطلبات المعتمدة
        if ($leave['status'] == 'approved') {
            $error_message = "لا يمكن حذف طلب الإجازة المعتمد";
        } else {
            $stmt = $pdo->prepare("DELETE FROM leaves WHERE id = ?");
            $stmt->execute([$_GET['delete']]);

            $success_message = "تم حذف طلب الإجازة بنجاح";
        }
    } catch (PDOException $e) {
        $error_message = "خطأ في حذف طلب الإجازة: " . $e->getMessage();
    }
}

// الحصول على قائمة طلبات الإجازات للموظف الحالي
$leaves = [];
try {
    $query = "SELECT l.*, a.first_name as approved_by_first, a.last_name as approved_by_last
              FROM leaves l
              LEFT JOIN employees a ON l.approved_by = a.id
              WHERE l.employee_id = ? ORDER BY l.created_at DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$_SESSION['employee_id']]);
    $leaves = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "خطأ في جلب طلبات الإجازات: " . $e->getMessage();
}

// الحصول على بيانات طلب إجازة محدد للتعديل
$edit_leave = null;
if (isset($_GET['edit'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM leaves WHERE id = ?");
        $stmt->execute([$_GET['edit']]);
        $edit_leave = $stmt->fetch(PDO::FETCH_ASSOC);

        // التحقق من ملكية الطلب
        if ($edit_leave['employee_id'] != $_SESSION['employee_id']) {
            header("Location: employee_leaves.php");
            exit;
        }

        // لا يمكن تعديل الطلبات المعتمدة
        if ($edit_leave['status'] == 'approved') {
            header("Location: employee_leaves.php");
            exit;
        }
    } catch (PDOException $e) {
        $error_message = "خطأ في جلب بيانات طلب الإجازة: " . $e->getMessage();
    }
}

$page_title = "إجازاتي";
include 'includes/header.php';
?>

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
        <h1 class="h3">إجازاتي</h1>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addLeaveModal">
            <i class="fas fa-plus"></i> طلب إجازة جديدة
        </button>
    </div>

    <!-- جدول طلبات الإجازات -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>نوع الإجازة</th>
                            <th>تاريخ البدء</th>
                            <th>تاريخ الانتهاء</th>
                            <th>المدة</th>
                            <th>السبب</th>
                            <th>الحالة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($leaves) > 0): ?>
                            <?php $counter = 1; ?>
                            <?php foreach ($leaves as $leave): ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td><?php echo htmlspecialchars($leave['type']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($leave['start_date'])); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($leave['end_date'])); ?></td>
                                    <td><?php echo $leave['days']; ?> يوم</td>
                                    <td><?php echo htmlspecialchars($leave['reason']); ?></td>
                                    <td>
                                        <?php if ($leave['status'] == 'approved'): ?>
                                            <span class="badge bg-success">معتمد</span>
                                            <?php if (!empty($leave['approved_by_first']) && !empty($leave['approved_by_last'])): ?>
                                                <br><small class="text-muted">بواسطة: <?php echo $leave['approved_by_first'] . ' ' . $leave['approved_by_last']; ?></small>
                                            <?php endif; ?>
                                        <?php elseif ($leave['status'] == 'rejected'): ?>
                                            <span class="badge bg-danger">مرفوض</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">في الانتظار</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <?php if ($leave['status'] != 'approved'): ?>
                                                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editLeaveModal<?php echo $leave['id']; ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <a href="?delete=<?php echo $leave['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('هل أنت متأكد من حذف هذا الطلب؟');">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>


                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">لا توجد طلبات إجازات</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- نافذة إضافة طلب إجازة جديد -->
<div class="modal fade" id="addLeaveModal" tabindex="-1" aria-labelledby="addLeaveModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addLeaveModalLabel">طلب إجازة جديدة</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="employee_leaves.php">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="type" class="form-label">نوع الإجازة</label>
                        <select class="form-select" id="type" name="type" required>
                            <option value="">اختر نوع الإجازة</option>
                            <option value="سنوية">سنوية</option>
                            <option value="مرضية">مرضية</option>
                            <option value="طارئة">طارئة</option>
                            <option value="بدون راتب">بدون راتب</option>
                            <option value="أخرى">أخرى</option>
                        </select>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="start_date" class="form-label">تاريخ البدء</label>
                            <input type="date" class="form-control datepicker" id="start_date" name="start_date" required>
                        </div>
                        <div class="col-md-6">
                            <label for="end_date" class="form-label">تاريخ الانتهاء</label>
                            <input type="date" class="form-control datepicker" id="end_date" name="end_date" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="reason" class="form-label">السبب</label>
                        <textarea class="form-control" id="reason" name="reason" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" name="request_leave" class="btn btn-primary">تقديم الطلب</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- نافذة تعديل طلب الإجازة -->
<div class="modal fade" id="editLeaveModal<?php echo $leave['id']; ?>" tabindex="-1" aria-labelledby="editLeaveModalLabel<?php echo $leave['id']; ?>" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editLeaveModalLabel<?php echo $leave['id']; ?>">تعديل طلب الإجازة</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="employee_leaves.php">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="type" class="form-label">نوع الإجازة</label>
                        <select class="form-select" id="type" name="type" required>
                            <option value="">اختر نوع الإجازة</option>
                            <option value="سنوية" <?php echo ($leave['type'] == 'سنوية') ? 'selected' : ''; ?>>سنوية</option>
                            <option value="مرضية" <?php echo ($leave['type'] == 'مرضية') ? 'selected' : ''; ?>>مرضية</option>
                            <option value="طارئة" <?php echo ($leave['type'] == 'طارئة') ? 'selected' : ''; ?>>طارئة</option>
                            <option value="بدون راتب" <?php echo ($leave['type'] == 'بدون راتب') ? 'selected' : ''; ?>>بدون راتب</option>
                            <option value="أخرى" <?php echo ($leave['type'] == 'أخرى') ? 'selected' : ''; ?>>أخرى</option>
                        </select>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="start_date" class="form-label">تاريخ البدء</label>
                            <input type="date" class="form-control datepicker" id="start_date" name="start_date" value="<?php echo date('Y-m-d', strtotime($leave['start_date'])); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="end_date" class="form-label">تاريخ الانتهاء</label>
                            <input type="date" class="form-control datepicker" id="end_date" name="end_date" value="<?php echo date('Y-m-d', strtotime($leave['end_date'])); ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="reason" class="form-label">السبب</label>
                        <textarea class="form-control" id="reason" name="reason" rows="3" required><?php echo htmlspecialchars($leave['reason']); ?></textarea>
                    </div>
                    <input type="hidden" name="leave_id" value="<?php echo $leave['id']; ?>">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" name="edit_leave" class="btn btn-primary">حفظ التغييرات</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // تهيئة منتقي التاريخ
    $(document).ready(function() {
        $('.datepicker').datepicker({
            format: 'dd/mm/yyyy',
            language: 'ar',
            autoclose: true,
            todayHighlight: true
        });

        // التحقق من صحة التاريخ عند الإرسال
        $('form').on('submit', function() {
            var startDate = $('#start_date').val();
            var endDate = $('#end_date').val();

            // التحقق من أن تاريخ البدء ليس بعد تاريخ الانتهاء
            if (startDate && endDate && startDate > endDate) {
                alert('يجب أن يكون تاريخ البدء قبل تاريخ الانتهاء أو مساوٍ له');
                return false;
            }
        });
    });
</script>

<?php include 'includes/footer.php'; ?>