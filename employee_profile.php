<?php
// التحقق من تسجيل الدخول
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'config.php';

// الحصول على بيانات المستخدم الحالي
try {
    $stmt = $pdo->prepare("SELECT u.*, e.*, r.name as role_name, r.permissions, d.name as department_name, p.title as position_name FROM users u JOIN employees e ON u.employee_id = e.id JOIN roles r ON u.role_id = r.id LEFT JOIN departments d ON e.department_id = d.id LEFT JOIN positions p ON e.position_id = p.id WHERE u.id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

    $permissions = json_decode($currentUser['permissions'], true);
} catch (PDOException $e) {
    die("خطأ في جلب بيانات المستخدم: " . $e->getMessage());
}

// التحقق من وجود صلاحية لعرض بيانات الموظفين
if (!isset($permissions['all']) || !$permissions['all']) {
    header("Location: index.php");
    exit;
}

// الحصول على بيانات الموظف المطلوب
$employee_id = isset($_GET['id']) ? $_GET['id'] : 0;
if ($employee_id <= 0) {
    header("Location: employees.php");
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT u.*, e.*, r.name as role_name, d.name as department_name, p.title as position_name FROM employees e LEFT JOIN users u ON e.id = u.employee_id LEFT JOIN roles r ON u.role_id = r.id LEFT JOIN departments d ON e.department_id = d.id LEFT JOIN positions p ON e.position_id = p.id WHERE e.id = ?");
    $stmt->execute([$employee_id]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$employee) {
        header("Location: employees.php");
        exit;
    }
} catch (PDOException $e) {
    die("خطأ في جلب بيانات الموظف: " . $e->getMessage());
}

// الحصول على سجل الحضور
try {
    $stmt = $pdo->prepare("SELECT * FROM attendance WHERE employee_id = ? ORDER BY date DESC LIMIT 30");
    $stmt->execute([$employee_id]);
    $attendance_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("خطأ في جلب سجل الحضور: " . $e->getMessage());
}

// الحصول على سجل الإجازات
try {
    $stmt = $pdo->prepare("SELECT * FROM leaves WHERE employee_id = ? ORDER BY start_date DESC");
    $stmt->execute([$employee_id]);
    $leave_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("خطأ في جلب سجل الإجازات: " . $e->getMessage());
}

// الحصول على الملاحظات
try {
    $stmt = $pdo->prepare("SELECT n.*, e.first_name, e.last_name FROM notes n JOIN employees e ON n.author_id = e.id WHERE n.employee_id = ? ORDER BY n.created_at DESC");
    $stmt->execute([$employee_id]);
    $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("خطأ في جلب الملاحظات: " . $e->getMessage());
}

// الحصول على العهد الموكلة للموظف
try {
    $stmt = $pdo->prepare("SELECT a.*, aa.assigned_date, aa.return_date, aa.status as assignment_status FROM assets a JOIN asset_assignments aa ON a.id = aa.asset_id WHERE aa.employee_id = ? AND aa.status = 'assigned'");
    $stmt->execute([$employee_id]);
    $assigned_assets = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("خطأ في جلب بيانات العهد: " . $e->getMessage());
}

// الحصول على الملفات المرفقة
try {
    $stmt = $pdo->prepare("SELECT * FROM attachments WHERE employee_id = ? ORDER BY created_at DESC");
    $stmt->execute([$employee_id]);
    $attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("خطأ في جلب الملفات المرفقة: " . $e->getMessage());
}

include 'includes/header.php';
?>

<div class="container-fluid">
    <?php
    // عرض رسائل النجاح
    if (isset($_GET['success'])) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
              <i class="fas fa-check-circle me-2"></i>تم تحديث بيانات الموظف بنجاح
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
    } elseif (isset($_GET['note_success'])) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
              <i class="fas fa-check-circle me-2"></i>تم إضافة الملاحظة بنجاح
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
    } elseif (isset($_GET['file_success'])) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
              <i class="fas fa-check-circle me-2"></i>تم رفع الملف بنجاح
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
    } elseif (isset($_GET['asset_success'])) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
              <i class="fas fa-check-circle me-2"></i>تم تسليم العهدة بنجاح
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
    }
    ?>
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">ملف الموظف: <?php echo $employee['first_name'] . ' ' . $employee['last_name']; ?></h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <div class="btn-group me-2">
                <a href="employees.php" class="btn btn-sm btn-primary rounded-pill me-2 px-3 py-2">
                    <i class="fas fa-arrow-right me-1"></i>العودة لقائمة الموظفين
                </a>

            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6 col-md-12">
            <div class="card mb-4 shadow-sm border-0 overflow-hidden">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="mb-0"><i class="fas fa-user me-2"></i>البيانات الشخصية</h5>
                </div>
                <div class="card-body text-center p-4">
                    <div class="position-relative d-inline-block mb-4">
                        <?php if (!empty($employee['profile_picture'])): ?>
                            <img src="uploads/<?php echo $employee['profile_picture']; ?>" class="rounded-circle profile-img shadow-sm" alt="الصورة الشخصية">
                        <?php else: ?>
                            <!-- <img src="assets/images/default-avatar.png" class="rounded-circle profile-img shadow-sm" alt="الصورة الشخصية"> -->
                            <div class="profile-img mb-3 d-flex align-items-center justify-content-center bg-light">
                                <i class="fas fa-user fa-4x text-muted"></i>
                            </div>
                        <?php endif; ?>

                        <?php
                        if ($employee['status'] == 'active') $statusColor = 'success';
                        elseif ($employee['status'] == 'leave') $statusColor = 'warning';
                        else $statusColor = 'danger';
                        ?>
                        <span class="position-absolute bottom-0 end-0 badge bg-<?php echo $statusColor; ?> rounded-pill p-2">
                            <i class="fas fa-circle"></i>
                        </span>
                    </div>
                    <h3 class="mb-2"><?php echo $employee['first_name'] . ' ' . $employee['last_name']; ?></h3>
                    <div class="d-flex justify-content-center mb-3">
                        <div class="badge bg-light text-dark px-3 py-2 me-2">
                            <i class="fas fa-briefcase me-1"></i> <?php echo $employee['position_name']; ?>
                        </div>
                        <div class="badge bg-light text-dark px-3 py-2">
                            <i class="fas fa-building me-1"></i> <?php echo $employee['department_name']; ?>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-gradient text-white py-2">
                            <h6 class="mb-0"><i class="fas fa-money-bill-wave me-2"></i>الراتب الإجمالي</h6>
                        </div>
                        <div class="card-body p-3 text-center">
                            <h4 class="mb-0 text-primary fw-bold"><?php echo number_format($employee['salary'] ?? 0, 2); ?> ريال</h4>
                        </div>
                    </div>

                    <div class="row g-2 mb-4">
                        <div class="col-6">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body p-3 text-center">
                                    <i class="fas fa-coins text-primary fa-2x mb-2"></i>
                                    <div class="small text-muted">الراتب الأساسي</div>
                                    <div class="fw-bold"><?php echo number_format($employee['basic_salary'] ?? 0, 2); ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body p-3 text-center">
                                    <i class="fas fa-home text-primary fa-2x mb-2"></i>
                                    <div class="small text-muted">بدل السكن</div>
                                    <div class="fw-bold"><?php echo number_format($employee['housing_allowance'] ?? 0, 2); ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body p-3 text-center">
                                    <i class="fas fa-car text-primary fa-2x mb-2"></i>
                                    <div class="small text-muted">بدل المواصلات</div>
                                    <div class="fw-bold"><?php echo number_format($employee['transportation_allowance'] ?? 0, 2); ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body p-3 text-center">
                                    <i class="fas fa-gift text-primary fa-2x mb-2"></i>
                                    <div class="small text-muted">بدلات أخرى</div>
                                    <div class="fw-bold"><?php echo number_format($employee['other_allowances'] ?? 0, 2); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <div class="accordion" id="personalInfoAccordion">
                        <div class="accordion-item border-0 mb-2">
                            <h2 class="accordion-header" id="headingOne">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                                    <i class="fas fa-id-card me-2"></i>المعلومات الشخصية
                                </button>
                            </h2>
                            <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne" data-bs-parent="#personalInfoAccordion">
                                <div class="accordion-body p-3">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="d-flex align-items-center mb-3">
                                                <i class="fas fa-id-card me-2 text-primary"></i>
                                                <div>
                                                    <small class="text-muted d-block">الرقم الوطني</small>
                                                    <strong><?php echo $employee['national_id']; ?></strong>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="d-flex align-items-center mb-3">
                                                <i class="fas fa-passport me-2 text-primary"></i>
                                                <div>
                                                    <small class="text-muted d-block">رقم الإقامة</small>
                                                    <strong><?php echo $employee['residency_number'] ?? 'غير محدد'; ?></strong>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="d-flex align-items-center mb-3">
                                                <i class="fas fa-birthday-cake me-2 text-primary"></i>
                                                <div>
                                                    <small class="text-muted d-block">تاريخ الميلاد</small>
                                                    <strong><?php echo date('Y-m-d', strtotime($employee['birth_date'])); ?></strong>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="d-flex align-items-center mb-3">
                                                <i class="fas fa-calendar-check me-2 text-primary"></i>
                                                <div>
                                                    <small class="text-muted d-block">تاريخ التوظيف</small>
                                                    <strong><?php echo date('Y-m-d', strtotime($employee['hire_date'])); ?></strong>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item border-0 mb-2">
                            <h2 class="accordion-header" id="headingTwo">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                                    <i class="fas fa-phone me-2"></i>معلومات الاتصال
                                </button>
                            </h2>
                            <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo" data-bs-parent="#personalInfoAccordion">
                                <div class="accordion-body p-3">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="d-flex align-items-center mb-3">
                                                <i class="fas fa-envelope me-2 text-primary"></i>
                                                <div>
                                                    <small class="text-muted d-block">البريد الإلكتروني</small>
                                                    <strong><?php echo $employee['email']; ?></strong>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="d-flex align-items-center mb-3">
                                                <i class="fas fa-phone me-2 text-primary"></i>
                                                <div>
                                                    <small class="text-muted d-block">رقم الهاتف</small>
                                                    <strong><?php echo $employee['phone']; ?></strong>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="d-flex align-items-center mb-3">
                                                <i class="fas fa-map-marker-alt me-2 text-primary"></i>
                                                <div>
                                                    <small class="text-muted d-block">العنوان</small>
                                                    <strong><?php echo $employee['address']; ?></strong>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item border-0 mb-2">
                            <h2 class="accordion-header" id="headingThree">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                                    <i class="fas fa-info-circle me-2"></i>معلومات الحساب
                                </button>
                            </h2>
                            <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree" data-bs-parent="#personalInfoAccordion">
                                <div class="accordion-body p-3">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="d-flex align-items-center mb-3">
                                                <i class="fas fa-user me-2 text-primary"></i>
                                                <div>
                                                    <small class="text-muted d-block">حساب المستخدم</small>
                                                    <strong><?php echo $employee['username']; ?></strong>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="d-flex align-items-center mb-3">
                                                <i class="fas fa-user-tag me-2 text-primary"></i>
                                                <div>
                                                    <small class="text-muted d-block">دور المستخدم</small>
                                                    <strong><?php echo $employee['role_name']; ?></strong>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="d-flex align-items-center mb-3">
                                                <i class="fas fa-info-circle me-2 text-primary"></i>
                                                <div>
                                                    <small class="text-muted d-block">الحالة الوظيفية</small>
                                                    <span class="badge bg-<?php echo $statusColor; ?> rounded-pill">
                                                        <?php
                                                        if ($employee['status'] == 'active') echo 'نشط';
                                                        elseif ($employee['status'] == 'leave') echo 'إجازة';
                                                        else echo 'منتهي الخدمة';
                                                        ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>



        <div class="col-lg-6 col-md-12">
            <!-- علامات التبويب -->
            <div class="card border-0 shadow-sm mb-4">
                <ul class="nav nav-tabs px-3 pt-3" id="profileTabs" role="tablist">
                    <li class="nav-item me-2" role="presentation">
                        <button class="nav-link active px-4 py-2 rounded-pill" id="attendance-tab" data-bs-toggle="tab" data-bs-target="#attendance" type="button" role="tab" aria-controls="attendance" aria-selected="true">
                            <i class="fas fa-clock me-2"></i>سجل الحضور
                        </button>
                    </li>
                    <li class="nav-item me-2" role="presentation">
                        <button class="nav-link px-4 py-2 rounded-pill" id="leaves-tab" data-bs-toggle="tab" data-bs-target="#leaves" type="button" role="tab" aria-controls="leaves" aria-selected="false">
                            <i class="fas fa-calendar-alt me-2"></i>سجل الإجازات
                        </button>
                    </li>
                    <li class="nav-item me-2" role="presentation">
                        <button class="nav-link px-4 py-2 rounded-pill" id="notes-tab" data-bs-toggle="tab" data-bs-target="#notes" type="button" role="tab" aria-controls="notes" aria-selected="false">
                            <i class="fas fa-sticky-note me-2"></i>الملاحظات
                        </button>
                    </li>
                    <li class="nav-item me-2" role="presentation">
                        <button class="nav-link px-4 py-2 rounded-pill" id="assets-tab" data-bs-toggle="tab" data-bs-target="#assets" type="button" role="tab" aria-controls="assets" aria-selected="false">
                            <i class="fas fa-box me-2"></i>العهد المسندة
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link px-4 py-2 rounded-pill" id="attachments-tab" data-bs-toggle="tab" data-bs-target="#attachments" type="button" role="tab" aria-controls="attachments" aria-selected="false">
                            <i class="fas fa-paperclip me-2"></i>الملفات المرفقة
                        </button>
                    </li>
                </ul>
            </div>

            <div class="tab-content mt-0" id="profileTabsContent">
                <!-- سجل الحضور -->
                <div class="tab-pane fade show active" id="attendance" role="tabpanel" aria-labelledby="attendance-tab">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light py-3">
                            <h5 class="mb-0"><i class="fas fa-clock me-2 text-primary"></i>سجل الحضور والانصراف</h5>
                        </div>
                        <div class="card-body p-4">
                            <?php if (count($attendance_records) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle">
                                        <thead>
                                            <tr>
                                                <th class="border-0 pb-3"><i class="fas fa-calendar me-1"></i>التاريخ</th>
                                                <th class="border-0 pb-3"><i class="fas fa-sign-in-alt me-1"></i>وقت الحضور</th>
                                                <th class="border-0 pb-3"><i class="fas fa-sign-out-alt me-1"></i>وقت الانصراف</th>
                                                <th class="border-0 pb-3"><i class="fas fa-info-circle me-1"></i>الحالة</th>
                                                <th class="border-0 pb-3"><i class="fas fa-sticky-note me-1"></i>ملاحظات</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($attendance_records as $record): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <i class="fas fa-calendar-day text-primary me-2"></i>
                                                            <?php echo date('Y-m-d', strtotime($record['date'])); ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <i class="fas fa-sign-in-alt text-success me-2"></i>
                                                            <?php echo $record['check_in']; ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <i class="fas fa-sign-out-alt text-danger me-2"></i>
                                                            <?php echo $record['check_out']; ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        if ($record['status'] == 'present') echo '<span class="badge bg-success rounded-pill px-3 py-1"><i class="fas fa-check-circle me-1"></i>حاضر</span>';
                                                        elseif ($record['status'] == 'absent') echo '<span class="badge bg-danger rounded-pill px-3 py-1"><i class="fas fa-times-circle me-1"></i>غائب</span>';
                                                        else echo '<span class="badge bg-warning rounded-pill px-3 py-1"><i class="fas fa-exclamation-circle me-1"></i>إجازة</span>';
                                                        ?>
                                                    </td>
                                                    <td><?php echo $record['notes']; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">لا توجد سجلات حضور</h5>
                                    <p class="text-muted">لم يتم تسجيل أي بيانات حضور لهذا الموظف حتى الآن</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- سجل الإجازات -->
                <div class="tab-pane fade" id="leaves" role="tabpanel" aria-labelledby="leaves-tab">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light py-3">
                            <h5 class="mb-0"><i class="fas fa-calendar-alt me-2 text-primary"></i>سجل الإجازات</h5>
                        </div>
                        <div class="card-body p-4">
                            <?php if (count($leave_records) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle">
                                        <thead>
                                            <tr>
                                                <th class="border-0 pb-3"><i class="fas fa-tag me-1"></i>نوع الإجازة</th>
                                                <th class="border-0 pb-3"><i class="fas fa-calendar-day me-1"></i>تاريخ البدء</th>
                                                <th class="border-0 pb-3"><i class="fas fa-calendar-check me-1"></i>تاريخ الانتهاء</th>
                                                <th class="border-0 pb-3"><i class="fas fa-hourglass-half me-1"></i>عدد الأيام</th>
                                                <th class="border-0 pb-3"><i class="fas fa-info-circle me-1"></i>الحالة</th>
                                                <th class="border-0 pb-3"><i class="fas fa-sticky-note me-1"></i>السبب</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($leave_records as $record): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <?php
                                                            if ($record['type'] == 'annual') echo '<i class="fas fa-calendar-day text-primary me-2"></i>';
                                                            elseif ($record['type'] == 'sick') echo '<i class="fas fa-medkit text-primary me-2"></i>';
                                                            elseif ($record['type'] == 'emergency') echo '<i class="fas fa-exclamation-triangle text-primary me-2"></i>';
                                                            else echo '<i class="fas fa-calendar text-primary me-2"></i>';
                                                            ?>
                                                            <?php
                                                            if ($record['type'] == 'annual') echo 'إجازة سنوية';
                                                            elseif ($record['type'] == 'sick') echo 'إجازة مرضية';
                                                            elseif ($record['type'] == 'emergency') echo 'إجازة طارئة';
                                                            else echo $record['type'];
                                                            ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <i class="fas fa-calendar-day text-success me-2"></i>
                                                            <?php echo date('Y-m-d', strtotime($record['start_date'])); ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <i class="fas fa-calendar-check text-danger me-2"></i>
                                                            <?php echo date('Y-m-d', strtotime($record['end_date'])); ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <i class="fas fa-hourglass-half text-warning me-2"></i>
                                                            <span class="badge bg-warning rounded-pill px-3 py-1"><?php echo $record['days']; ?> يوم</span>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        if ($record['status'] == 'approved') echo '<span class="badge bg-success rounded-pill px-3 py-1"><i class="fas fa-check-circle me-1"></i>موافق عليه</span>';
                                                        elseif ($record['status'] == 'rejected') echo '<span class="badge bg-danger rounded-pill px-3 py-1"><i class="fas fa-times-circle me-1"></i>مرفوض</span>';
                                                        else echo '<span class="badge bg-warning rounded-pill px-3 py-1"><i class="fas fa-exclamation-circle me-1"></i>في الانتظار</span>';
                                                        ?>
                                                    </td>
                                                    <td><?php echo $record['reason']; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">لا توجد سجلات إجازات</h5>
                                    <p class="text-muted">لم يتم تسجيل أي إجازات لهذا الموظف حتى الآن</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- الملاحظات -->
                <div class="tab-pane fade" id="notes" role="tabpanel" aria-labelledby="notes-tab">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light py-3 d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-sticky-note me-2 text-primary"></i>الملاحظات</h5>
                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addNoteModal">
                                <i class="fas fa-plus me-1"></i>إضافة ملاحظة
                            </button>
                        </div>
                        <div class="card-body p-4">
                            <?php if (count($notes) > 0): ?>
                                <div class="timeline">
                                    <?php foreach ($notes as $note): ?>
                                        <div class="timeline-item <?php if ($note['type'] == 'positive') echo 'border-success';
                                                                    elseif ($note['type'] == 'negative') echo 'border-danger';
                                                                    else echo 'border-warning'; ?> mb-4">
                                            <div class="timeline-marker <?php if ($note['type'] == 'positive') echo 'bg-success';
                                                                        elseif ($note['type'] == 'negative') echo 'bg-danger';
                                                                        else echo 'bg-warning'; ?>"></div>
                                            <div class="timeline-content">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <div>
                                                        <h6 class="mb-0 fw-bold"><?php echo $note['first_name'] . ' ' . $note['last_name']; ?></h6>
                                                        <small class="text-muted d-block"><?php echo date('Y-m-d H:i', strtotime($note['created_at'])); ?></small>
                                                    </div>
                                                    <div>
                                                        <span class="badge rounded-pill 
                                                            <?php
                                                            if ($note['type'] == 'positive') echo 'bg-success';
                                                            elseif ($note['type'] == 'negative') echo 'bg-danger';
                                                            else echo 'bg-warning';
                                                            ?>
                                                        ">
                                                            <?php
                                                            if ($note['type'] == 'positive') echo '<i class="fas fa-thumbs-up me-1"></i>إيجابية';
                                                            elseif ($note['type'] == 'negative') echo '<i class="fas fa-thumbs-down me-1"></i>سلبية';
                                                            else echo '<i class="fas fa-exclamation-triangle me-1"></i>تحذير';
                                                            ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="card border-0 shadow-sm mt-3">
                                                    <div class="card-body p-3">
                                                        <p class="mb-0"><?php echo $note['content']; ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-sticky-note fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">لا توجد ملاحظات</h5>
                                    <p class="text-muted">لم يتم إضافة أي ملاحظات لهذا الموظف حتى الآن</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- العهد -->
                <div class="tab-pane fade" id="assets" role="tabpanel" aria-labelledby="assets-tab">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">العهد الموكلة للموظف</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>اسم العهدة</th>
                                            <th>الوصف</th>
                                            <th>الرقم التسلسلي</th>
                                            <th>تاريخ التسليم</th>
                                            <th>تاريخ الإرجاع</th>
                                            <th>الحالة</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($assigned_assets) > 0): ?>
                                            <?php foreach ($assigned_assets as $asset): ?>
                                                <tr>
                                                    <td><?php echo $asset['name']; ?></td>
                                                    <td><?php echo $asset['description']; ?></td>
                                                    <td><?php echo $asset['serial_number']; ?></td>
                                                    <td><?php echo date('Y-m-d', strtotime($asset['assigned_date'])); ?></td>
                                                    <td><?php echo $asset['return_date'] ? date('Y-m-d', strtotime($asset['return_date'])) : '-'; ?></td>
                                                    <td>
                                                        <?php
                                                        if ($asset['assignment_status'] == 'assigned') echo '<span class="badge bg-primary">مسلم</span>';
                                                        else echo '<span class="badge bg-secondary">مرجع</span>';
                                                        ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="6" class="text-center">لا توجد عهد مسلمة للموظف</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- الملفات المرفقة -->
                <div class="tab-pane fade" id="attachments" role="tabpanel" aria-labelledby="attachments-tab">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-light py-3 d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-paperclip me-2 text-primary"></i>الملفات المرفقة</h5>
                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addAttachmentModal">
                                <i class="fas fa-plus me-1"></i>إضافة ملف
                            </button>
                        </div>
                        <div class="card-body p-4">
                            <?php if (count($attachments) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle">
                                        <thead>
                                            <tr>
                                                <th class="border-0 pb-3"><i class="fas fa-file me-1"></i>اسم الملف</th>
                                                <th class="border-0 pb-3"><i class="fas fa-tag me-1"></i>النوع</th>
                                                <th class="border-0 pb-3"><i class="fas fa-info-circle me-1"></i>الوصف</th>
                                                <th class="border-0 pb-3"><i class="fas fa-calendar-alt me-1"></i>تاريخ الرفع</th>
                                                <th class="border-0 pb-3"><i class="fas fa-cogs me-1"></i>الإجراءات</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($attachments as $attachment): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <?php
                                                            $fileIcon = 'fa-file';
                                                            if (strpos($attachment['type'], 'image') !== false) $fileIcon = 'fa-image';
                                                            elseif (strpos($attachment['type'], 'pdf') !== false) $fileIcon = 'fa-file-pdf';
                                                            elseif (strpos($attachment['type'], 'word') !== false || strpos($attachment['type'], 'document') !== false) $fileIcon = 'fa-file-word';
                                                            elseif (strpos($attachment['type'], 'excel') !== false || strpos($attachment['type'], 'spreadsheet') !== false) $fileIcon = 'fa-file-excel';
                                                            ?>
                                                            <i class="fas <?php echo $fileIcon; ?> text-primary me-2"></i>
                                                            <div>
                                                                <strong><?php echo $attachment['original_name']; ?></strong>
                                                                <small class="d-block text-muted"><?php echo $attachment['type']; ?></small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-light text-dark rounded-pill px-3 py-1"><?php echo $attachment['type']; ?></span>
                                                    </td>
                                                    <td><?php echo $attachment['description']; ?></td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <i class="fas fa-calendar-alt text-muted me-2"></i>
                                                            <?php echo date('Y-m-d H:i', strtotime($attachment['created_at'])); ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group" role="group">
                                                            <a href="<?php echo $attachment['path']; ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="عرض الملف">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                            <a href="<?php echo $attachment['path']; ?>" download="<?php echo $attachment['original_name']; ?>" class="btn btn-sm btn-outline-secondary" title="تحميل الملف">
                                                                <i class="fas fa-download"></i>
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">لا توجد ملفات مرفقة</h5>
                                    <p class="text-muted">لم يتم رفع أي ملفات لهذا الموظف حتى الآن</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- نافذة إضافة ملاحظة -->
<div class="modal fade" id="addNoteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title"><i class="fas fa-sticky-note me-2"></i>إضافة ملاحظة</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="notes.php">
                <div class="modal-body p-4">
                    <input type="hidden" name="employee_id" value="<?php echo $employee['id']; ?>">

                    <div class="mb-4">
                        <label for="note_type" class="form-label fw-bold">نوع الملاحظة</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-tag"></i></span>
                            <select class="form-select" id="note_type" name="type" required>
                                <option value="positive">إيجابية</option>
                                <option value="negative">سلبية</option>
                                <option value="warning">تحذير</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="note_content" class="form-label fw-bold">المحتوى</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-comment-alt"></i></span>
                            <textarea class="form-control" id="note_content" name="content" rows="4" required></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" name="add_note" class="btn btn-primary">حفظ الملاحظة</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- نافذة إضافة ملف مرفق -->
<div class="modal fade" id="addAttachmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title"><i class="fas fa-paperclip me-2"></i>إضافة ملف مرفق</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="attachments.php" enctype="multipart/form-data">
                <div class="modal-body p-4">
                    <input type="hidden" name="employee_id" value="<?php echo $employee['id']; ?>">

                    <div class="mb-4">
                        <label for="attachment_file" class="form-label fw-bold">اختر ملف</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-file-upload"></i></span>
                            <input type="file" class="form-control" id="attachment_file" name="attachment" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="attachment_description" class="form-label fw-bold">الوصف</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-align-left"></i></span>
                            <textarea class="form-control" id="attachment_description" name="description" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" name="add_attachment" class="btn btn-primary">رفع الملف</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>