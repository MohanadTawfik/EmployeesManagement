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
            else {
                // الموظف يمكنه رؤية ملفه الشخصي فقط
                $permissions['profile'] = true;

                // يمكنه تسجيل الحضور والانصراف لنفسه
                $permissions['attendance_own'] = true;

                // يمكنه طلب إجازات لنفسه
                $permissions['leaves_own'] = true;

                // يمكنه رؤية العهد المسندة إليه
                $permissions['assets_own'] = true;

                // يمكنه رؤية الملاحظات الموجهة إليه
                $permissions['notes_own'] = true;
            }
        }
    } catch (PDOException $e) {
        // في حالة الخطأ، يتم تعيين صلاحيات فارغة
        $permissions = [];
    }
}

// التحقق من الصلاحيات
if (!isset($permissions['attendance']) && !isset($permissions['attendance_own'])) {
    $_SESSION['error_message'] = "ليس لديك صلاحية للوصول إلى صفحة الحضور والانصراف";
    header("Location: index.php");
    exit;
}

// معالجة تسجيل الحضور والانصراف
if (isset($_POST['record_attendance'])) {
    try {
        $employee_id = $_POST['employee_id'];
        $date = $_POST['date'];
        $status = $_POST['status'];
        $check_in = !empty($_POST['check_in']) ? $_POST['check_in'] : null;
        $check_out = !empty($_POST['check_out']) ? $_POST['check_out'] : null;
        $notes = $_POST['notes'];

        // التحقق من الصلاحيات للموظف العادي
        if (!isset($permissions['attendance']) && isset($permissions['attendance_own'])) {
            if ($employee_id != $_SESSION['employee_id']) {
                $_SESSION['error_message'] = "ليس لديك صلاحية لتسجيل حضور موظف آخر";
                header("Location: attendance.php");
                exit;
            }
        }

        // التحقق من وجود سجل سابق
        $stmt = $pdo->prepare("SELECT id FROM attendance WHERE employee_id = ? AND date = ?");
        $stmt->execute([$employee_id, $date]);
        $existing_record = $stmt->fetch(PDO::FETCH_ASSOC);

        // تحقق مما إذا كان هذا طلب تعديل
        if (isset($_POST['attendance_id'])) {
            // تعديل سجل موجود
            $stmt = $pdo->prepare("UPDATE attendance SET employee_id = ?, date = ?, check_in = ?, check_out = ?, status = ?, notes = ? WHERE id = ?");
            $stmt->execute([$employee_id, $date, $check_in, $check_out, $status, $notes, $_POST['attendance_id']]);
            // إعادة تحميل الصفحة لعرض البيانات المحدثة
            header("Location: attendance.php?update_success=1");
            exit;
        } else {
            if ($existing_record) {
                // تحديث السجل الموجود
                $stmt = $pdo->prepare("UPDATE attendance SET check_in = ?, check_out = ?, status = ?, notes = ? WHERE id = ?");
                $stmt->execute([$check_in, $check_out, $status, $notes, $existing_record['id']]);
                // إعادة تحميل الصفحة لعرض البيانات المحدثة
                header("Location: attendance.php?update_success=1");
                exit;
            } else {
                // إضافة سجل جديد
                $stmt = $pdo->prepare("INSERT INTO attendance (employee_id, date, check_in, check_out, status, notes) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$employee_id, $date, $check_in, $check_out, $status, $notes]);
                // إعادة تحميل الصفحة لعرض البيانات المحدثة
                header("Location: attendance.php?add_success=1");
                exit;
            }
        }
    } catch (PDOException $e) {
        $error_message = "خطأ في تسجيل الحضور: " . $e->getMessage();
    }
}

// معالجة حذف سجل حضور
if (isset($_GET['delete'])) {
    try {
        // التحقق من الصلاحيات للموظف العادي
        if (!isset($permissions['attendance']) && isset($permissions['attendance_own'])) {
            $stmt = $pdo->prepare("SELECT employee_id FROM attendance WHERE id = ?");
            $stmt->execute([$_GET['delete']]);
            $attendance = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$attendance || $attendance['employee_id'] != $_SESSION['employee_id']) {
                $_SESSION['error_message'] = "ليس لديك صلاحية لحذف سجل حضور موظف آخر";
                header("Location: attendance.php");
                exit;
            }
        }

        $stmt = $pdo->prepare("DELETE FROM attendance WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        $success_message = "تم حذف سجل الحضور بنجاح";
    } catch (PDOException $e) {
        $error_message = "خطأ في حذف سجل الحضور: " . $e->getMessage();
    }
}

// الحصول على قائمة الموظفين
$employees = [];
try {
    if (isset($permissions['attendance'])) {
        // المدير يمكنه رؤية كل الموظفين
        $stmt = $pdo->query("SELECT id, first_name, last_name FROM employees WHERE status = 'active' ORDER BY first_name");
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // الموظف العادي يرى نفسه فقط
        $stmt = $pdo->prepare("SELECT id, first_name, last_name FROM employees WHERE id = ? AND status = 'active'");
        $stmt->execute([$_SESSION['employee_id']]);
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $error_message = "خطأ في جلب بيانات الموظفين: " . $e->getMessage();
}

// الحصول على سجلات الحضور
$attendance_records = [];
$filter_date = isset($_GET['filter_date']) ? $_GET['filter_date'] : date('Y-m-d');
$filter_employee = isset($_GET['filter_employee']) ? $_GET['filter_employee'] : '';

try {
    $query = "SELECT a.*, e.first_name, e.last_name FROM attendance a JOIN employees e ON a.employee_id = e.id WHERE 1=1";
    $params = [];

    // للموظف العادي، عرض سجلات حضوره فقط
    if (!isset($permissions['attendance']) && isset($permissions['attendance_own'])) {
        $query .= " AND a.employee_id = ?";
        $params[] = $_SESSION['employee_id'];
    }

    if (!empty($filter_date)) {
        $query .= " AND a.date = ?";
        $params[] = $filter_date;
    }

    if (!empty($filter_employee)) {
        // للموظف العادي، لا يمكنه تصفية حسب موظف آخر
        if (isset($permissions['attendance'])) {
            $query .= " AND a.employee_id = ?";
            $params[] = $filter_employee;
        }
    }

    $query .= " ORDER BY a.date DESC, e.first_name";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $attendance_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "خطأ في جلب سجلات الحضور: " . $e->getMessage();
}

// الحصول على بيانات سجل حضور محدد للتعديل
$edit_attendance = null;
if (isset($_GET['edit'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM attendance WHERE id = ?");
        $stmt->execute([$_GET['edit']]);
        $edit_attendance = $stmt->fetch(PDO::FETCH_ASSOC);

        // التحقق من الصلاحيات للموظف العادي
        if (!isset($permissions['attendance']) && isset($permissions['attendance_own'])) {
            if (!$edit_attendance || $edit_attendance['employee_id'] != $_SESSION['employee_id']) {
                $_SESSION['error_message'] = "ليس لديك صلاحية لتعديل سجل حضور موظف آخر";
                header("Location: attendance.php");
                exit;
            }
        }
    } catch (PDOException $e) {
        $error_message = "خطأ في جلب بيانات سجل الحضور: " . $e->getMessage();
    }
}
?>

<?php
$page_title = "الحضور والانصراف";
include 'includes/header.php';
?>

<!-- المحتوى الرئيسي -->
<div class="container-fluid mt-4">
    <!-- رسائل النجاح والخطأ -->
    <?php
    // عرض رسائل النجاح
    if (isset($_GET['add_success'])) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
              <i class="fas fa-check-circle me-2"></i>تم تسجيل الحضور بنجاح
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
    } elseif (isset($_GET['update_success'])) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
              <i class="fas fa-check-circle me-2"></i>تم تحديث سجل الحضور بنجاح
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
        <h1 class="h3">إدارة الحضور والانصراف</h1>
        <?php if (isset($permissions['attendance'])): ?>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAttendanceModal">
                <i class="fas fa-plus"></i> تسجيل حضور جديد
            </button>
        <?php endif; ?>
    </div>

    <!-- قسم تسجيل الحضور والانصراف للموظفين -->
    <?php if (isset($permissions['attendance_own'])): ?>
        <div class="alert alert-light border mb-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <?php
                    // التحقق إذا كان المستخدم قد سجل حضوره اليوم
                    $attendanceRecord = null;
                    try {
                        $stmt = $pdo->prepare("SELECT * FROM attendance WHERE employee_id = ? AND date = CURDATE()");
                        $stmt->execute([$_SESSION['employee_id']]);
                        $attendanceRecord = $stmt->fetch(PDO::FETCH_ASSOC);
                    } catch (PDOException $e) {
                        echo "<span class='text-danger'>خطأ: " . $e->getMessage() . "</span>";
                    }

                    if ($attendanceRecord) {
                        if ($attendanceRecord['check_out']) {
                            echo "<span class='text-success'><i class='fas fa-check-circle'></i> تم تسجيل الحضور والانصراف اليوم</span>";
                        } else {
                            echo "<span class='text-warning'><i class='fas fa-clock'></i> تم تسجيل الحضور: " . $attendanceRecord['check_in'] . "</span>";
                        }
                    } else {
                        echo "<span class='text-muted'>لم يتم تسجيل الحضور اليوم</span>";
                    }
                    ?>
                </div>
                <div>
                    <?php
                    if ($attendanceRecord) {
                        if (!$attendanceRecord['check_out']) {
                            echo "<form method='post' action='process_attendance.php' class='d-inline'>
                                    <input type='hidden' name='action' value='checkout'>
                                    <button type='submit' class='btn btn-sm btn-danger'>
                                        <i class='fas fa-sign-out-alt'></i> تسجيل الانصراف
                                    </button>
                                </form>";
                        }
                    } else {
                        echo "<form method='post' action='process_attendance.php' class='d-inline'>
                                <input type='hidden' name='action' value='checkin'>
                                <button type='submit' class='btn btn-sm btn-success'>
                                    <i class='fas fa-sign-in-alt'></i> تسجيل الحضور
                                </button>
                            </form>";
                    }
                    ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- فلاتر البحث -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="get" action="attendance.php">
                <div class="row g-3">
                    <div class="col-md-5">
                        <label for="filter_date" class="form-label">التاريخ</label>
                        <input type="date" class="form-control" id="filter_date" name="filter_date" value="<?php echo $filter_date; ?>">
                    </div>
                    <div class="col-md-5">
                        <label for="filter_employee" class="form-label">الموظف</label>
                        <select class="form-select" id="filter_employee" name="filter_employee">
                            <option value="">الكل</option>
                            <?php foreach ($employees as $employee): ?>
                                <option value="<?php echo $employee['id']; ?>" <?php echo ($filter_employee == $employee['id']) ? 'selected' : ''; ?>>
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

    <!-- جدول سجلات الحضور -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>الموظف</th>
                            <th>التاريخ</th>
                            <th>وقت الدخول</th>
                            <th>وقت الخروج</th>
                            <th>الحالة</th>
                            <th>ملاحظات</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($attendance_records) > 0): ?>
                            <?php $counter = 1; ?>
                            <?php foreach ($attendance_records as $record): ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td><?php echo $record['first_name'] . ' ' . $record['last_name']; ?></td>
                                    <td><?php echo date('Y-m-d', strtotime($record['date'])); ?></td>
                                    <td><?php echo $record['check_in']; ?></td>
                                    <td><?php echo $record['check_out']; ?></td>
                                    <td>
                                        <?php
                                        $status_class = '';
                                        $status_text = '';

                                        switch ($record['status']) {
                                            case 'present':
                                                $status_class = 'badge bg-success';
                                                $status_text = 'حاضر';
                                                break;
                                            case 'absent':
                                                $status_class = 'badge bg-danger';
                                                $status_text = 'غائب';
                                                break;
                                            case 'leave':
                                                $status_class = 'badge bg-warning';
                                                $status_text = 'إجازة';
                                                break;
                                        }
                                        ?>
                                        <span class="<?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                    </td>
                                    <td><?php echo $record['notes']; ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <?php if (isset($permissions['attendance'])): ?>
                                                <a href="attendance.php?edit=<?php echo $record['id']; ?>" class="btn btn-sm btn-outline-primary" title="تعديل">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php if (isset($permissions['attendance'])): ?>
                                                <a href="attendance.php?delete=<?php echo $record['id']; ?>" class="btn btn-sm btn-outline-danger" title="حذف" onclick="return confirm('هل أنت متأكد من حذف هذا السجل؟');">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">لا توجد سجلات حضور</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- نافذة إضافة/تعديل سجل حضور -->
<div class="modal fade" id="addAttendanceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title col-11"><?php echo isset($edit_attendance) ? 'تعديل سجل الحضور' : 'تسجيل حضور'; ?></h5>
                <button type="button" class="btn-close col-1" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="attendance.php">
                <?php if (isset($edit_attendance)): ?>
                    <input type="hidden" name="attendance_id" value="<?php echo $edit_attendance['id']; ?>">
                <?php endif; ?>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="employee_id" class="form-label">الموظف</label>
                            <?php if (isset($permissions['attendance'])): ?>
                                <select class="form-select" id="employee_id" name="employee_id" required>
                                    <option value="">اختر الموظف</option>
                                    <?php foreach ($employees as $employee): ?>
                                        <option value="<?php echo $employee['id']; ?>" <?php echo (isset($edit_attendance) && $edit_attendance['employee_id'] == $employee['id']) ? 'selected' : ''; ?>>
                                            <?php echo $employee['first_name'] . ' ' . $employee['last_name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <input type="hidden" name="employee_id" value="<?php echo $_SESSION['employee_id']; ?>" required>
                                <input type="text" class="form-control" value="<?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?>" readonly>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label for="date" class="form-label">التاريخ</label>
                            <input type="date" class="form-control" id="date" name="date" value="<?php echo isset($edit_attendance) ? $edit_attendance['date'] : date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="check_in" class="form-label">وقت الدخول</label>
                            <input type="time" class="form-control" id="check_in" name="check_in" value="<?php echo isset($edit_attendance) ? $edit_attendance['check_in'] : ''; ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="check_out" class="form-label">وقت الخروج</label>
                            <input type="time" class="form-control" id="check_out" name="check_out" value="<?php echo isset($edit_attendance) ? $edit_attendance['check_out'] : ''; ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="status" class="form-label">الحالة</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="present" <?php echo (isset($edit_attendance) && $edit_attendance['status'] == 'present') ? 'selected' : ''; ?>>حاضر</option>
                                <option value="absent" <?php echo (isset($edit_attendance) && $edit_attendance['status'] == 'absent') ? 'selected' : ''; ?>>غائب</option>
                                <option value="leave" <?php echo (isset($edit_attendance) && $edit_attendance['status'] == 'leave') ? 'selected' : ''; ?>>إجازة</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label for="notes" class="form-label">ملاحظات</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo isset($edit_attendance) ? $edit_attendance['notes'] : ''; ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" name="record_attendance" class="btn btn-primary">حفظ</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        <?php if (isset($edit_attendance)): ?>
            var myModal = new bootstrap.Modal(document.getElementById('addAttendanceModal'));
            myModal.show();
        <?php endif; ?>
    });
</script>

<?php include 'includes/footer.php'; ?>