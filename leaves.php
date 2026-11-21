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

// التحقق من صلاحيات المدير
if (!isset($permissions['all']) || !$permissions['all']) {
    header("Location: employee_leaves.php");
    exit;
}

// الحصول على قائمة الموظفين (للمديرين فقط)
$employees = [];
if (isset($permissions['leaves'])) {
    try {
        $stmt = $pdo->prepare("SELECT id, first_name, last_name FROM employees ORDER BY first_name");
        $stmt->execute();
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("خطأ في جلب قائمة الموظفين: " . $e->getMessage());
    }
}


// معالجة طلب إجازة جديد
if (isset($_POST['request_leave'])) {
    try {
        $start_date = new DateTime($_POST['start_date']);
        $end_date = new DateTime($_POST['end_date']);
        $interval = $start_date->diff($end_date);
        $days = $interval->days + 1; // +1 لتشمل يوم البداية

        $status = isset($_POST['status']) ? $_POST['status'] : 'pending';
        $approved_by = ($status == 'approved') ? $_SESSION['employee_id'] : null;

        $stmt = $pdo->prepare("INSERT INTO leaves (employee_id, type, start_date, end_date, days, reason, status, approved_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['employee_id'],
            $_POST['type'],
            $_POST['start_date'],
            $_POST['end_date'],
            $days,
            $_POST['reason'],
            $status,
            $approved_by
        ]);

        // إعادة تحميل الصفحة لعرض البيانات المحدثة
        header("Location: leaves.php?add_success=1");
        exit;
    } catch (PDOException $e) {
        $error_message = "خطأ في إضافة الإجازة للموظف: " . $e->getMessage();
    }
}

// معالجة تعديل طلب إجازة
if (isset($_POST['edit_leave'])) {
    try {
        $start_date = new DateTime($_POST['start_date']);
        $end_date = new DateTime($_POST['end_date']);
        $interval = $start_date->diff($end_date);
        $days = $interval->days + 1; // +1 لتشمل يوم البداية

        $status = isset($_POST['status']) ? $_POST['status'] : null;
        $approved_by = ($status == 'approved') ? $_SESSION['employee_id'] : null;

        $stmt = $pdo->prepare("UPDATE leaves SET type = ?, start_date = ?, end_date = ?, days = ?, reason = ?, status = ?, approved_by = ? WHERE id = ?");
        $stmt->execute([
            $_POST['type'],
            $_POST['start_date'],
            $_POST['end_date'],
            $days,
            $_POST['reason'],
            $status,
            $approved_by,
            $_POST['leave_id']
        ]);

        // إعادة تحميل الصفحة لعرض البيانات المحدثة
        header("Location: leaves.php?edit_success=1");
        exit;
    } catch (PDOException $e) {
        $error_message = "خطأ في تعديل طلب الإجازة: " . $e->getMessage();
    }
}

// معالجة الموافقة على طلب إجازة (للمديرين فقط)
if (isset($_POST['approve_leave']) && isset($permissions['leaves'])) {
    try {
        $stmt = $pdo->prepare("UPDATE leaves SET status = 'approved', approved_by = ? WHERE id = ?");
        $stmt->execute([
            $_SESSION['employee_id'],
            $_POST['leave_id']
        ]);

        // إعادة تحميل الصفحة لعرض البيانات المحدثة
        header("Location: leaves.php?approve_success=1");
        exit;
    } catch (PDOException $e) {
        $error_message = "خطأ في الموافقة على طلب الإجازة: " . $e->getMessage();
    }
}

// معالجة رفض طلب إجازة (للمديرين فقط)
if (isset($_POST['reject_leave']) && isset($permissions['leaves'])) {
    try {
        $stmt = $pdo->prepare("UPDATE leaves SET status = 'rejected' WHERE id = ?");
        $stmt->execute([$_POST['leave_id']]);

        // إعادة تحميل الصفحة لعرض البيانات المحدثة
        header("Location: leaves.php?reject_success=1");
        exit;
    } catch (PDOException $e) {
        $error_message = "خطأ في رفض طلب الإجازة: " . $e->getMessage();
    }
}

// معالجة حذف طلب إجازة
if (isset($_GET['delete'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM leaves WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        $leave = $stmt->fetch(PDO::FETCH_ASSOC);

        // التحقق من الصلاحيات (يمكن للموظف حذف طلباته فقط، ويمكن للمدير حذف أي طلب)
        if (!isset($permissions['all']) || !$permissions['all']) {
            if ($leave['employee_id'] != $_SESSION['employee_id']) {
                header("Location: leaves.php");
                exit;
            }
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

// الحصول على قائمة طلبات الإجازات
$leaves = [];
try {
    $query = "SELECT l.*, e.first_name, e.last_name, d.name as department_name,
              a.first_name as approved_by_first, a.last_name as approved_by_last
              FROM leaves l 
              JOIN employees e ON l.employee_id = e.id
              LEFT JOIN departments d ON e.department_id = d.id
              LEFT JOIN employees a ON l.approved_by = a.id
              WHERE 1=1";

    $params = [];

    // إذا كان المستخدم موظفاً عادياً، عرض طلباته فقط
    if (!isset($permissions['all']) || !$permissions['all']) {
        $query .= " AND l.employee_id = ?";
        $params[] = $_SESSION['employee_id'];
    }

    // فلترة حسب الحالة إذا تم تحديدها
    if (isset($_GET['status']) && !empty($_GET['status'])) {
        $query .= " AND l.status = ?";
        $params[] = $_GET['status'];
    }

    $query .= " ORDER BY l.created_at DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
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

        // التحقق من الصلاحيات
        if (!isset($permissions['all']) || !$permissions['all']) {
            if ($edit_leave['employee_id'] != $_SESSION['employee_id']) {
                header("Location: leaves.php");
                exit;
            }
        }

        // لا يمكن تعديل الطلبات المعتمدة
        if ($edit_leave['status'] == 'approved') {
            header("Location: leaves.php");
            exit;
        }
    } catch (PDOException $e) {
        $error_message = "خطأ في جلب بيانات طلب الإجازة: " . $e->getMessage();
    }
}
?>

<?php
$page_title = "الإجازات";
include 'includes/header.php';
?>


<!-- المحتوى الرئيسي -->
<div class="container-fluid mt-4">
    <!-- رسائل النجاح والخطأ -->
    <?php
    // عرض رسائل النجاح
    if (isset($_GET['add_success'])) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
              <i class="fas fa-check-circle me-2"></i>تم إضافة الإجازة للموظف بنجاح
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
    } elseif (isset($_GET['edit_success'])) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
              <i class="fas fa-check-circle me-2"></i>تم تعديل طلب الإجازة بنجاح
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
    } elseif (isset($_GET['approve_success'])) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
              <i class="fas fa-check-circle me-2"></i>تمت الموافقة على طلب الإجازة بنجاح
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
    } elseif (isset($_GET['reject_success'])) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
              <i class="fas fa-check-circle me-2"></i>تم رفض طلب الإجازة بنجاح
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
        <h1 class="h3">إدارة الإجازات</h1>
        <div class="d-flex gap-2">
            <!-- فلتر الحالة -->
            <div class="dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="statusFilterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <?php
                    $currentStatus = isset($_GET['status']) ? $_GET['status'] : 'all';
                    $statusText = [
                        'all' => 'الكل',
                        'pending' => 'المعلقة',
                        'approved' => 'الموافق عليها',
                        'rejected' => 'المرفوضة'
                    ];
                    echo $statusText[$currentStatus] ?? 'الكل';
                    ?>
                </button>
                <ul class="dropdown-menu" aria-labelledby="statusFilterDropdown">
                    <li><a class="dropdown-item <?php echo $currentStatus == 'all' ? 'active' : ''; ?>" href="leaves.php">الكل</a></li>
                    <li><a class="dropdown-item <?php echo $currentStatus == 'pending' ? 'active' : ''; ?>" href="leaves.php?status=pending">المعلقة</a></li>
                    <li><a class="dropdown-item <?php echo $currentStatus == 'approved' ? 'active' : ''; ?>" href="leaves.php?status=approved">الموافق عليها</a></li>
                    <li><a class="dropdown-item <?php echo $currentStatus == 'rejected' ? 'active' : ''; ?>" href="leaves.php?status=rejected">المرفوضة</a></li>
                </ul>
            </div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addLeaveModal">
                <i class="fas fa-plus"></i> إضافة إجازة لموظف
            </button>
        </div>
    </div>

    <!-- جدول طلبات الإجازات -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <?php if (isset($permissions['all']) && $permissions['all']): ?>
                                <th>الموظف</th>
                            <?php endif; ?>
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
                                    <?php if (isset($permissions['all']) && $permissions['all']): ?>
                                        <td><?php echo $leave['first_name'] . ' ' . $leave['last_name']; ?></td>
                                    <?php endif; ?>
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
                                            <?php if ($leave['status'] == 'pending' && isset($permissions['all']) && $permissions['all']): ?>
                                                <button type="button" class="btn btn-sm btn-success" onclick="openApproveModal(<?php echo $leave['id']; ?>, '<?php echo $leave['first_name'] . ' ' . $leave['last_name']; ?>', '<?php echo htmlspecialchars($leave['type']); ?>', '<?php echo date('d/m/Y', strtotime($leave['start_date'])); ?>', '<?php echo date('d/m/Y', strtotime($leave['end_date'])); ?>', '<?php echo $leave['days']; ?>', '<?php echo htmlspecialchars($leave['reason']); ?>')">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger" onclick="openRejectModal(<?php echo $leave['id']; ?>, '<?php echo $leave['first_name'] . ' ' . $leave['last_name']; ?>', '<?php echo htmlspecialchars($leave['type']); ?>', '<?php echo date('d/m/Y', strtotime($leave['start_date'])); ?>', '<?php echo date('d/m/Y', strtotime($leave['end_date'])); ?>', '<?php echo $leave['days']; ?>', '<?php echo htmlspecialchars($leave['reason']); ?>')">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            <?php endif; ?>

                                            <?php if ($leave['status'] != 'approved'): ?>
                                                <button type="button" class="btn btn-sm btn-primary" onclick="openEditModal(<?php echo $leave['id']; ?>, '<?php echo htmlspecialchars($leave['type']); ?>', '<?php echo date('Y-m-d', strtotime($leave['start_date'])); ?>', '<?php echo date('Y-m-d', strtotime($leave['end_date'])); ?>', '<?php echo htmlspecialchars($leave['reason']); ?>', '<?php echo $leave['status']; ?>')">
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
                                <td colspan="<?php echo (isset($permissions['all']) && $permissions['all']) ? '9' : '8'; ?>" class="text-center py-4">لا توجد طلبات إجازات</td>
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
                <h5 class="modal-title" id="addLeaveModalLabel">إضافة إجازة لموظف</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="leaves.php">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="employee_id" class="form-label">الموظف</label>
                        <select class="form-select" id="employee_id" name="employee_id" required>
                            <option value="" selected disabled>اختر الموظف</option>
                            <?php foreach ($employees as $employee): ?>
                                <option value="<?php echo $employee['id']; ?>"><?php echo $employee['first_name'] . ' ' . $employee['last_name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
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
                    <div class="mb-3">
                        <label for="status" class="form-label">حالة الإجازة</label>
                        <select class="form-select" id="status" name="status">
                            <option value="pending" selected>في الانتظار</option>
                            <option value="approved">معتمد</option>
                            <option value="rejected">مرفوض</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" name="request_leave" class="btn btn-primary">إضافة الإجازة</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- نافذة تعديل طلب الإجازة -->
<div class="modal fade" id="editLeaveModal" tabindex="-1" aria-labelledby="editLeaveModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editLeaveModalLabel">تعديل طلب الإجازة</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="leaves.php">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit-type" class="form-label">نوع الإجازة</label>
                        <select class="form-select" id="edit-type" name="type" required>
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
                            <label for="edit-start-date" class="form-label">تاريخ البدء</label>
                            <input type="date" class="form-control datepicker" id="edit-start-date" name="start_date" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit-end-date" class="form-label">تاريخ الانتهاء</label>
                            <input type="date" class="form-control datepicker" id="edit-end-date" name="end_date" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="edit-reason" class="form-label">السبب</label>
                        <textarea class="form-control" id="edit-reason" name="reason" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="edit-status" class="form-label">حالة الإجازة</label>
                        <select class="form-select" id="edit-status" name="status">
                            <option value="pending">في الانتظار</option>
                            <option value="approved">معتمد</option>
                            <option value="rejected">مرفوض</option>
                        </select>
                    </div>
                    <input type="hidden" name="leave_id" id="edit-leave-id">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" name="edit_leave" class="btn btn-primary">حفظ التغييرات</button>
                </div>
            </form>
        </div>
    </div>
</div>



<!-- نافذة الموافقة على طلب الإجازة -->
<div class="modal fade" id="approveLeaveModal" tabindex="-1" aria-labelledby="approveLeaveModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="approveLeaveModalLabel">الموافقة على طلب الإجازة</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>هل أنت متأكد من الموافقة على طلب الإجازة التالي؟</p>
                <ul>
                    <li>الموظف: <span id="approve-employee-name"></span></li>
                    <li>نوع الإجازة: <span id="approve-leave-type"></span></li>
                    <li>الفترة: من <span id="approve-start-date"></span> إلى <span id="approve-end-date"></span></li>
                    <li>المدة: <span id="approve-leave-days"></span> يوم</li>
                    <li>السبب: <span id="approve-leave-reason"></span></li>
                </ul>
            </div>
            <form method="post" action="leaves.php">
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" name="approve_leave" class="btn btn-success">موافقة</button>
                    <input type="hidden" name="leave_id" id="approve-leave-id">
                </div>
            </form>
        </div>
    </div>
</div>

<!-- نافذة رفض طلب الإجازة -->
<div class="modal fade" id="rejectLeaveModal" tabindex="-1" aria-labelledby="rejectLeaveModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="rejectLeaveModalLabel">رفض طلب الإجازة</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>هل أنت متأكد من رفض طلب الإجازة التالي؟</p>
                <ul>
                    <li>الموظف: <span id="reject-employee-name"></span></li>
                    <li>نوع الإجازة: <span id="reject-leave-type"></span></li>
                    <li>الفترة: من <span id="reject-start-date"></span> إلى <span id="reject-end-date"></span></li>
                    <li>المدة: <span id="reject-leave-days"></span> يوم</li>
                    <li>السبب: <span id="reject-leave-reason"></span></li>
                </ul>
            </div>
            <form method="post" action="leaves.php">
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" name="reject_leave" class="btn btn-danger">رفض</button>
                    <input type="hidden" name="leave_id" id="reject-leave-id">
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // تهيئة منتقي التاريخ
    document.addEventListener('DOMContentLoaded', function() {
        // التحقق من صحة التاريخ عند الإرسال
        var forms = document.querySelectorAll('form');
        forms.forEach(function(form) {
            form.addEventListener('submit', function(event) {
                var startDate = document.getElementById('start_date').value;
                var endDate = document.getElementById('end_date').value;

                // التحقق من أن تاريخ البدء ليس بعد تاريخ الانتهاء
                if (startDate && endDate && startDate > endDate) {
                    alert('يجب أن يكون تاريخ البدء قبل تاريخ الانتهاء أو مساوٍ له');
                    event.preventDefault();
                    return false;
                }
            });
        });
    });

    // دالة فتح نافذة الموافقة
    function openApproveModal(leaveId, employeeName, leaveType, startDate, endDate, days, reason) {
        document.getElementById('approve-leave-id').value = leaveId;
        document.getElementById('approve-employee-name').textContent = employeeName;
        document.getElementById('approve-leave-type').textContent = leaveType;
        document.getElementById('approve-start-date').textContent = startDate;
        document.getElementById('approve-end-date').textContent = endDate;
        document.getElementById('approve-leave-days').textContent = days;
        document.getElementById('approve-leave-reason').textContent = reason;

        var modal = new bootstrap.Modal(document.getElementById('approveLeaveModal'));
        modal.show();
    }

    // دالة فتح نافذة الرفض
    function openRejectModal(leaveId, employeeName, leaveType, startDate, endDate, days, reason) {
        document.getElementById('reject-leave-id').value = leaveId;
        document.getElementById('reject-employee-name').textContent = employeeName;
        document.getElementById('reject-leave-type').textContent = leaveType;
        document.getElementById('reject-start-date').textContent = startDate;
        document.getElementById('reject-end-date').textContent = endDate;
        document.getElementById('reject-leave-days').textContent = days;
        document.getElementById('reject-leave-reason').textContent = reason;

        var modal = new bootstrap.Modal(document.getElementById('rejectLeaveModal'));
        modal.show();
    }

    // دالة فتح نافذة التعديل
    function openEditModal(leaveId, type, startDate, endDate, reason, status) {
        document.getElementById('edit-leave-id').value = leaveId;
        document.getElementById('edit-type').value = type;
        document.getElementById('edit-start-date').value = startDate;
        document.getElementById('edit-end-date').value = endDate;
        document.getElementById('edit-reason').value = reason;
        document.getElementById('edit-status').value = status || 'pending';

        var modal = new bootstrap.Modal(document.getElementById('editLeaveModal'));
        modal.show();
    }
</script>
<?php include 'includes/footer.php'; ?>