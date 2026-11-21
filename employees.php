<?php
require_once 'config.php';

// التحقق من تسجيل الدخول والصلاحيات
// session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// معالجة إضافة موظف جديد
if (isset($_POST['add_employee'])) {
    try {
        // التحقق من وجود القسم والمنصب
        if (!empty($_POST['department_id'])) {
            $stmt = $pdo->prepare("SELECT id FROM departments WHERE id = ?");
            $stmt->execute([$_POST['department_id']]);
            if (!$stmt->fetch()) {
                throw new Exception("القسم المحدد غير موجود");
            }
        }

        if (!empty($_POST['position_id'])) {
            $stmt = $pdo->prepare("SELECT id FROM positions WHERE id = ?");
            $stmt->execute([$_POST['position_id']]);
            if (!$stmt->fetch()) {
                throw new Exception("المنصب المحدد غير موجود");
            }
        }

        // حساب الراتب الإجمالي
        $basic_salary = !empty($_POST['basic_salary']) ? $_POST['basic_salary'] : 0;
        $housing_allowance = !empty($_POST['housing_allowance']) ? $_POST['housing_allowance'] : 0;
        $transportation_allowance = !empty($_POST['transportation_allowance']) ? $_POST['transportation_allowance'] : 0;
        $other_allowances = !empty($_POST['other_allowances']) ? $_POST['other_allowances'] : 0;
        $total_salary = $basic_salary + $housing_allowance + $transportation_allowance + $other_allowances;

        // إضافة الموظف
        $stmt = $pdo->prepare("INSERT INTO employees (first_name, last_name, national_id, residency_number, birth_date, hire_date, department_id, position_id, basic_salary, housing_allowance, transportation_allowance, other_allowances, salary, phone, email, address, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['first_name'],
            $_POST['last_name'],
            $_POST['national_id'],
            $_POST['residency_number'],
            $_POST['birth_date'],
            $_POST['hire_date'],
            !empty($_POST['department_id']) ? $_POST['department_id'] : null,
            !empty($_POST['position_id']) ? $_POST['position_id'] : null,
            $basic_salary,
            $housing_allowance,
            $transportation_allowance,
            $other_allowances,
            $total_salary,
            $_POST['phone'],
            $_POST['email'],
            $_POST['address'],
            $_POST['status']
        ]);

        $employee_id = $pdo->lastInsertId();

        // إنشاء حساب مستخدم للموظف
        if (!empty($_POST['username']) && !empty($_POST['password'])) {
            $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role_id, employee_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['username'],
                $_POST['email'],
                $hashed_password,
                $_POST['role_id'],
                $employee_id
            ]);
        }

        // إعادة تحميل الصفحة لعرض البيانات المحدثة
        header("Location: employees.php?add_success=1");
        exit;
    } catch (PDOException $e) {
        $error_message = "خطأ في إضافة الموظف: " . $e->getMessage();
    }
}

// معالجة تعديل بيانات موظف
if (isset($_POST['edit_employee'])) {
    try {
        // حساب الراتب الإجمالي
        $basic_salary = !empty($_POST['basic_salary']) ? $_POST['basic_salary'] : 0;
        $housing_allowance = !empty($_POST['housing_allowance']) ? $_POST['housing_allowance'] : 0;
        $transportation_allowance = !empty($_POST['transportation_allowance']) ? $_POST['transportation_allowance'] : 0;
        $other_allowances = !empty($_POST['other_allowances']) ? $_POST['other_allowances'] : 0;
        $total_salary = $basic_salary + $housing_allowance + $transportation_allowance + $other_allowances;

        $stmt = $pdo->prepare("UPDATE employees SET first_name = ?, last_name = ?, national_id = ?, residency_number = ?, birth_date = ?, hire_date = ?, department_id = ?, position_id = ?, basic_salary = ?, housing_allowance = ?, transportation_allowance = ?, other_allowances = ?, salary = ?, phone = ?, email = ?, address = ?, status = ? WHERE id = ?");
        $stmt->execute([
            $_POST['first_name'],
            $_POST['last_name'],
            $_POST['national_id'],
            $_POST['residency_number'],
            $_POST['birth_date'],
            $_POST['hire_date'],
            $_POST['department_id'],
            $_POST['position_id'],
            $basic_salary,
            $housing_allowance,
            $transportation_allowance,
            $other_allowances,
            $total_salary,
            $_POST['phone'],
            $_POST['email'],
            $_POST['address'],
            $_POST['status'],
            $_POST['employee_id']
        ]);



        // إنشاء حساب مستخدم للموظف
        if (!empty($_POST['username']) && !empty($_POST['password'])) {
            // التحقق من كلمة المرور الحالية
            $stmt = $pdo->prepare("SELECT * FROM users WHERE employee_id = ?");
            $stmt->execute([$_POST['employee_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {

                $stmt_role = $pdo->prepare("SELECT id FROM roles WHERE name = 'موظف'");
                $stmt_role->execute();
                $role = $stmt_role->fetch(PDO::FETCH_ASSOC);

                $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role_id, employee_id) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    $_POST['username'],
                    $_POST['email'],
                    $hashed_password,
                    $role['id'],
                    $_POST['employee_id']
                ]);
            }
        }

        // تحديث بيانات المستخدم إذا تم إرسالها
        if (!empty($_POST['username'])) {
            $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ? WHERE employee_id = ?");
            $stmt->execute([
                $_POST['username'],
                $_POST['email'],
                $_POST['employee_id']
            ]);
        }

        // تحديث كلمة المرور إذا تم إرسالها
        if (!empty($_POST['password'])) {
            $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE employee_id = ?");
            $stmt->execute([
                $hashed_password,
                $_POST['employee_id']
            ]);
        }

        // إعادة تحميل الصفحة لعرض البيانات المحدثة
        header("Location: employees.php?edit_success=1");
        exit;
    } catch (PDOException $e) {
        $error_message = "خطأ في تحديث بيانات الموظف: " . $e->getMessage();
    }
}

// معالجة حذف موظف
if (isset($_GET['delete'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE employee_id = ?");
        $stmt->execute([$_GET['delete']]);

        $stmt = $pdo->prepare("DELETE FROM employees WHERE id = ?");
        $stmt->execute([$_GET['delete']]);

        // إعادة تحميل الصفحة لعرض البيانات المحدثة
        header("Location: employees.php?delete_success=1");
        exit;

        $success_message = "تم حذف الموظف بنجاح";
    } catch (PDOException $e) {
        $error_message = "خطأ في حذف الموظف: " . $e->getMessage();
    }
}

// الحصول على قائمة الموظفين
$employees = [];
try {
    $stmt = $pdo->query("SELECT e.*, d.name as department_name, p.title as position_name, u.username FROM employees e LEFT JOIN departments d ON e.department_id = d.id LEFT JOIN positions p ON e.position_id = p.id LEFT JOIN users u ON e.id = u.employee_id ORDER BY e.first_name");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "خطأ في جلب بيانات الموظفين: " . $e->getMessage();
}

// الحصول على قائمة الأقسام
$departments = [];
try {
    $stmt = $pdo->query("SELECT * FROM departments ORDER BY name");
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "خطأ في جلب بيانات الأقسام: " . $e->getMessage();
}

// الحصول على قائمة المناصب
$positions = [];
try {
    $stmt = $pdo->query("SELECT * FROM positions ORDER BY title");
    $positions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "خطأ في جلب بيانات المناصب: " . $e->getMessage();
}

// الحصول على قائمة الأدوار
$roles = [];
try {
    $stmt = $pdo->query("SELECT * FROM roles ORDER BY name");
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "خطأ في جلب بيانات الأدوار: " . $e->getMessage();
}

// الحصول على بيانات موظف محدد للتعديل
$edit_employee = null;
if (isset($_GET['edit'])) {
    try {
        $stmt = $pdo->prepare("SELECT e.*, u.username, u.role_id FROM employees e LEFT JOIN users u ON e.id = u.employee_id WHERE e.id = ?");
        $stmt->execute([$_GET['edit']]);
        $edit_employee = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error_message = "خطأ في جلب بيانات الموظف: " . $e->getMessage();
    }
}
?>

<?php
$page_title = "إدارة الموظفين";
include 'includes/header.php';
?>

<div class="container-fluid">
    <?php
    // عرض رسائل النجاح
    if (isset($_GET['add_success'])) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
              <i class="fas fa-check-circle me-2"></i>تم إضافة الموظف بنجاح
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
    } elseif (isset($_GET['edit_success'])) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
              <i class="fas fa-check-circle me-2"></i>تم تحديث بيانات الموظف بنجاح
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
    } elseif (isset($_GET['delete_success'])) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
              <i class="fas fa-check-circle me-2"></i>تم حذف الموظف بنجاح
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
    }
    ?>
</div>

<style>
    /* ضمان اتجاه RTL للصفحة بأكملها */
    html,
    body {
        direction: rtl;
        text-align: right;
    }

    .card {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        text-align: right;
    }

    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
    }

    .table th {
        border-top: none;
        font-weight: 600;
        color: #495057;
        background-color: #f8f9fa;
        text-align: right;
    }

    .table td {
        text-align: right;
    }

    .btn {
        transition: all 0.2s ease;
        margin-left: 5px;
        margin-right: 0;
    }

    .btn-group .btn:first-child {
        margin-left: 0;
        margin-right: 0;
    }

    .btn-group .btn:not(:first-child) {
        margin-left: 0;
        margin-right: -1px;
    }

    .btn:hover {
        transform: translateY(-2px);
    }

    .avatar-md {
        width: 40px;
        height: 40px;
        font-size: 16px;
        margin-left: 10px;
        margin-right: 0;
    }

    .badge {
        font-size: 0.75rem;
        padding: 0.375rem 0.75rem;
    }

    .nav-tabs .nav-link {
        font-weight: 500;
        border-radius: 0.375rem 0.375rem 0 0;
        margin-left: 5px;
        margin-right: 0;
    }

    .nav-tabs .nav-link:first-child {
        margin-left: 0;
    }

    .nav-tabs .nav-link.active {
        font-weight: 600;
    }

    .form-control:focus,
    .form-select:focus {
        border-color: #86b7fe;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        text-align: right;
    }

    .form-control,
    .form-select {
        text-align: right;
    }

    .input-group-text {
        background-color: #f8f9fa;
        border-left: none;
        border-right: 1px solid #ced4da;
    }

    .input-group .form-control,
    .input-group .form-select {
        border-left: 1px solid #ced4da;
        border-right: none;
    }

    .input-group> :not(:first-child):not(.dropdown-menu):not(.valid-tooltip):not(.valid-feedback):not(.invalid-tooltip):not(.invalid-feedback) {
        margin-right: -1px;
        border-top-right-radius: 0;
        border-bottom-right-radius: 0;
        border-top-left-radius: 0.375rem;
        border-bottom-left-radius: 0.375rem;
    }

    .input-group> :first-child {
        border-top-left-radius: 0;
        border-bottom-left-radius: 0;
        border-top-right-radius: 0.375rem;
        border-bottom-right-radius: 0.375rem;
    }

    .modal-content {
        border-radius: 0.5rem;
        text-align: right;
    }

    .modal-header {
        border-radius: 0.5rem 0.5rem 0 0;
    }

    .modal-footer {
        justify-content: flex-start;
    }

    .modal-footer .btn {
        margin-left: 10px;
        margin-right: 0;
    }

    .modal-footer .btn:first-child {
        margin-left: 0;
    }

    .alert {
        border: none;
        border-radius: 0.5rem;
        text-align: right;
    }

    #searchEmployees {
        border-radius: 0.375rem 0 0 0.375rem;
    }

    .input-group .input-group-text {
        border-radius: 0 0.375rem 0.375rem 0;
    }

    .dropdown-menu {
        text-align: right;
        right: 0;
        left: auto;
    }

    .dropdown-item {
        text-align: right;
    }
</style>


<!-- المحتوى الرئيسي -->
<div class="container-fluid mt-4">
    <!-- رسائل النجاح والخطأ -->
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <div class="d-flex align-items-center">
                <i class="fas fa-check-circle me-2"></i>
                <div><?php echo $success_message; ?></div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <div class="d-flex align-items-center">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <div><?php echo $error_message; ?></div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
    <?php endif; ?>

    <!-- عنوان الصفحة وأزرار التحكم -->
    <div class="row mb-4">
        <div class="col-md-6">
            <h1 class="h3 mb-0"><i class="fas fa-users me-2"></i>إدارة الموظفين</h1>
            <p class="text-muted">إدارة بيانات الموظفين ومعلوماتهم الوظيفية</p>
        </div>
        <div class="col-md-6 text-md-end mt-3 mt-md-0">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEmployeeModal">
                <i class="fas fa-plus me-2"></i>إضافة موظف جديد
            </button>
        </div>
    </div>


    <!-- جدول الموظفين -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3 border-0">
            <div class="row align-items-center">
                <div class="col">
                    <h5 class="mb-0">قائمة الموظفين</h5>
                </div>
                <div class="col-auto">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" class="form-control" id="searchEmployees" placeholder="البحث عن موظف...">
                    </div>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="employeesTable">
                    <thead class="table-light">
                        <tr>
                            <th width="60">#</th>
                            <th>الموظف</th>
                            <th>معلومات الاتصال</th>
                            <th>الراتب</th>
                            <th>الوظيفة</th>
                            <th>الحالة</th>
                            <th width="150">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($employees) > 0): ?>
                            <?php foreach ($employees as $index => $employee): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-md bg-primary rounded-circle text-white d-flex align-items-center justify-content-center me-3">
                                                <?php echo mb_substr($employee['first_name'], 0, 1) . mb_substr($employee['last_name'], 0, 1); ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold"><?php echo $employee['first_name'] . ' ' . $employee['last_name']; ?></div>
                                                <div class="text-muted small">الرقم الوطني: <?php echo $employee['national_id']; ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="small">
                                            <div><i class="fas fa-envelope me-1 text-muted"></i> <?php echo $employee['email']; ?></div>
                                            <div><i class="fas fa-id-card me-1 text-muted"></i> <?php echo $employee['residency_number'] ?? 'غير محدد'; ?></div>
                                            <div><i class="fas fa-phone me-1 text-muted"></i> <?php echo $employee['phone']; ?></div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-primary"><?php echo number_format($employee['salary'], 2); ?> ريال</div>
                                        <div class="small text-muted">أساسي: <?php echo number_format($employee['basic_salary'], 2); ?></div>
                                    </td>
                                    <td>
                                        <div class="small">
                                            <div><i class="fas fa-building me-1 text-muted"></i> <?php echo $employee['department_name'] ?? 'غير محدد'; ?></div>
                                            <div><i class="fas fa-briefcase me-1 text-muted"></i> <?php echo $employee['position_name'] ?? 'غير محدد'; ?></div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($employee['status'] == 'active'): ?>
                                            <span class="badge bg-success rounded-pill"><i class="fas fa-check-circle me-1"></i>نشط</span>
                                        <?php elseif ($employee['status'] == 'leave'): ?>
                                            <span class="badge bg-warning rounded-pill"><i class="fas fa-clock me-1"></i>إجازة</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger rounded-pill"><i class="fas fa-times-circle me-1"></i>منتهي الخدمة</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex justify-content-center">
                                            <a href="employee_profile.php?id=<?php echo $employee['id']; ?>" class="btn btn-sm btn-outline-primary me-1" title="عرض الملف الشخصي">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-outline-secondary me-1" data-bs-toggle="modal" data-bs-target="#editEmployeeModal<?php echo $employee['id']; ?>" title="تعديل">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <a href="employees.php?delete=<?php echo $employee['id']; ?>" class="btn btn-sm btn-outline-danger" title="حذف" onclick="return confirm('هل أنت متأكد من حذف هذا الموظف؟');">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <div class="text-muted mb-3">
                                        <i class="fas fa-users fa-3x"></i>
                                    </div>
                                    <h5>لا يوجد موظفين</h5>
                                    <p class="text-muted">قم بإضافة موظف جديد للبدء</p>
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEmployeeModal">
                                        <i class="fas fa-plus me-2"></i>إضافة موظف جديد
                                    </button>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- نافذة إضافة موظف جديد -->
<div class="modal fade" id="addEmployeeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white border-0 py-3">
                <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>إضافة موظف جديد</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="employees.php" id="addEmployeeForm">
                <div class="modal-body p-4">
                    <ul class="nav nav-tabs mb-4" id="employeeTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="personal-tab" data-bs-toggle="tab" data-bs-target="#personal" type="button" role="tab" aria-controls="personal" aria-selected="true">المعلومات الشخصية</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="job-tab" data-bs-toggle="tab" data-bs-target="#job" type="button" role="tab" aria-controls="job" aria-selected="false">المعلومات الوظيفية</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="salary-tab" data-bs-toggle="tab" data-bs-target="#salary" type="button" role="tab" aria-controls="salary" aria-selected="false">الراتب والبدلات</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="account-tab" data-bs-toggle="tab" data-bs-target="#account" type="button" role="tab" aria-controls="account" aria-selected="false">حساب المستخدم</button>
                        </li>
                    </ul>
                    <div class="tab-content" id="employeeTabsContent">
                        <div class="tab-pane fade show active" id="personal" role="tabpanel" aria-labelledby="personal-tab">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="first_name" class="form-label fw-bold">الاسم الأول <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                                        <input type="text" class="form-control" id="first_name" name="first_name" required>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="last_name" class="form-label fw-bold">اسم العائلة <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                                        <input type="text" class="form-control" id="last_name" name="last_name" required>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="national_id" class="form-label fw-bold">الرقم الوطني</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                        <input type="text" class="form-control" id="national_id" name="national_id">
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label fw-bold">البريد الإلكتروني</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                        <input type="email" class="form-control" id="email" name="email">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="birth_date" class="form-label fw-bold">تاريخ الميلاد</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                                        <input type="date" class="form-control" id="birth_date" name="birth_date">
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label fw-bold">رقم الهاتف</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                        <input type="tel" class="form-control" id="phone" name="phone">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-12 mb-3">
                                    <label for="address" class="form-label fw-bold">العنوان</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                                        <textarea class="form-control" id="address" name="address" rows="2"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="job" role="tabpanel" aria-labelledby="job-tab">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="hire_date" class="form-label fw-bold">تاريخ التوظيف <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-calendar-check"></i></span>
                                        <input type="date" class="form-control" id="hire_date" name="hire_date" required>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="residency_number" class="form-label fw-bold">رقم الإقامة</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-passport"></i></span>
                                        <input type="text" class="form-control" id="residency_number" name="residency_number">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="department_id" class="form-label fw-bold">القسم <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-building"></i></span>
                                        <select class="form-select" id="department_id" name="department_id" required>
                                            <option value="">اختر القسم</option>
                                            <?php foreach ($departments as $department): ?>
                                                <option value="<?php echo $department['id']; ?>"><?php echo $department['name']; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="position_id" class="form-label fw-bold">المنصب <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-briefcase"></i></span>
                                        <select class="form-select" id="position_id" name="position_id" required>
                                            <option value="">اختر المنصب</option>
                                            <?php foreach ($positions as $position): ?>
                                                <option value="<?php echo $position['id']; ?>"><?php echo $position['title']; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="status" class="form-label fw-bold">الحالة الوظيفية</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-info-circle"></i></span>
                                        <select class="form-select" id="status" name="status">
                                            <option value="active" selected>نشط</option>
                                            <option value="leave">إجازة</option>
                                            <option value="terminated">منتهي الخدمة</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="salary" role="tabpanel" aria-labelledby="salary-tab">
                            <div class="alert alert-info mb-4">
                                <i class="fas fa-info-circle me-2"></i>
                                سيتم حساب الراتب الإجمالي تلقائياً بناءً على المبالغ المدخلة
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="basic_salary" class="form-label fw-bold">الراتب الأساسي</label>
                                    <div class="input-group">
                                        <span class="input-group-text">ريال</span>
                                        <input type="number" class="form-control" id="basic_salary" name="basic_salary" step="0.01" min="0">
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="housing_allowance" class="form-label fw-bold">بدل السكن</label>
                                    <div class="input-group">
                                        <span class="input-group-text">ريال</span>
                                        <input type="number" class="form-control" id="housing_allowance" name="housing_allowance" step="0.01" min="0">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="transportation_allowance" class="form-label fw-bold">بدل المواصلات</label>
                                    <div class="input-group">
                                        <span class="input-group-text">ريال</span>
                                        <input type="number" class="form-control" id="transportation_allowance" name="transportation_allowance" step="0.01" min="0">
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="other_allowances" class="form-label fw-bold">بدلات أخرى</label>
                                    <div class="input-group">
                                        <span class="input-group-text">ريال</span>
                                        <input type="number" class="form-control" id="other_allowances" name="other_allowances" step="0.01" min="0">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="salary" class="form-label fw-bold">الراتب الإجمالي</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-primary text-white">ريال</span>
                                        <input type="number" class="form-control bg-light" id="salary" name="salary" step="0.01" min="0" readonly>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="account" role="tabpanel" aria-labelledby="account-tab">
                            <div class="alert alert-info mb-4">
                                <i class="fas fa-info-circle me-2"></i>
                                يمكن ترك هذه الحقول فارغة إذا لم تكن بحاجة إلى إنشاء حساب مستخدم للموظف
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="username" class="form-label fw-bold">اسم المستخدم</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                                        <input type="text" class="form-control" id="username" name="username">
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label fw-bold">كلمة المرور</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                        <input type="password" class="form-control" id="password" name="password">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="role_id" class="form-label fw-bold">دور المستخدم</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-user-tag"></i></span>
                                        <select class="form-select" id="role_id" name="role_id">
                                            <?php foreach ($roles as $role): ?>
                                                <option value="<?php echo $role['id']; ?>"><?php echo $role['name']; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" name="add_employee" class="btn btn-primary">حفظ</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- نافذة تعديل الموظف -->
<?php foreach ($employees as $employee): ?>
    <div class="modal fade" id="editEmployeeModal<?php echo $employee['id']; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-info text-white border-0 py-3">
                    <h5 class="modal-title"><i class="fas fa-user-edit me-2"></i>تعديل بيانات الموظف: <?php echo $employee['first_name'] . ' ' . $employee['last_name']; ?></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" action="employees.php" id="editEmployeeForm<?php echo $employee['id']; ?>">
                    <div class="modal-body p-4">
                        <input type="hidden" name="employee_id" value="<?php echo $employee['id']; ?>">

                        <ul class="nav nav-tabs mb-4" id="editEmployeeTabs<?php echo $employee['id']; ?>" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="edit-personal-tab-<?php echo $employee['id']; ?>" data-bs-toggle="tab" data-bs-target="#edit-personal-<?php echo $employee['id']; ?>" type="button" role="tab" aria-controls="edit-personal-<?php echo $employee['id']; ?>" aria-selected="true">المعلومات الشخصية</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="edit-job-tab-<?php echo $employee['id']; ?>" data-bs-toggle="tab" data-bs-target="#edit-job-<?php echo $employee['id']; ?>" type="button" role="tab" aria-controls="edit-job-<?php echo $employee['id']; ?>" aria-selected="false">المعلومات الوظيفية</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="edit-salary-tab-<?php echo $employee['id']; ?>" data-bs-toggle="tab" data-bs-target="#edit-salary-<?php echo $employee['id']; ?>" type="button" role="tab" aria-controls="edit-salary-<?php echo $employee['id']; ?>" aria-selected="false">الراتب والبدلات</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="edit-account-tab-<?php echo $employee['id']; ?>" data-bs-toggle="tab" data-bs-target="#edit-account-<?php echo $employee['id']; ?>" type="button" role="tab" aria-controls="edit-account-<?php echo $employee['id']; ?>" aria-selected="false">حساب المستخدم</button>
                            </li>
                        </ul>
                        <div class="tab-content" id="editEmployeeTabsContent<?php echo $employee['id']; ?>">
                            <div class="tab-pane fade show active" id="edit-personal-<?php echo $employee['id']; ?>" role="tabpanel" aria-labelledby="edit-personal-tab-<?php echo $employee['id']; ?>">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="first_name_<?php echo $employee['id']; ?>" class="form-label fw-bold">الاسم الأول <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                                            <input type="text" class="form-control" id="first_name_<?php echo $employee['id']; ?>" name="first_name" value="<?php echo $employee['first_name']; ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="last_name_<?php echo $employee['id']; ?>" class="form-label fw-bold">اسم العائلة <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                                            <input type="text" class="form-control" id="last_name_<?php echo $employee['id']; ?>" name="last_name" value="<?php echo $employee['last_name']; ?>" required>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="national_id_<?php echo $employee['id']; ?>" class="form-label fw-bold">الرقم الوطني</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                            <input type="text" class="form-control" id="national_id_<?php echo $employee['id']; ?>" name="national_id" value="<?php echo $employee['national_id']; ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="email_<?php echo $employee['id']; ?>" class="form-label fw-bold">البريد الإلكتروني</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                            <input type="email" class="form-control" id="email_<?php echo $employee['id']; ?>" name="email" value="<?php echo $employee['email']; ?>">
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="birth_date_<?php echo $employee['id']; ?>" class="form-label fw-bold">تاريخ الميلاد</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                                            <input type="date" class="form-control" id="birth_date_<?php echo $employee['id']; ?>" name="birth_date" value="<?php echo $employee['birth_date']; ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="phone_<?php echo $employee['id']; ?>" class="form-label fw-bold">رقم الهاتف</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                            <input type="tel" class="form-control" id="phone_<?php echo $employee['id']; ?>" name="phone" value="<?php echo $employee['phone']; ?>">
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-12 mb-3">
                                        <label for="address_<?php echo $employee['id']; ?>" class="form-label fw-bold">العنوان</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                                            <textarea class="form-control" id="address_<?php echo $employee['id']; ?>" name="address" rows="2"><?php echo $employee['address']; ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="edit-job-<?php echo $employee['id']; ?>" role="tabpanel" aria-labelledby="edit-job-tab-<?php echo $employee['id']; ?>">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="hire_date_<?php echo $employee['id']; ?>" class="form-label fw-bold">تاريخ التوظيف <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-calendar-check"></i></span>
                                            <input type="date" class="form-control" id="hire_date_<?php echo $employee['id']; ?>" name="hire_date" value="<?php echo $employee['hire_date']; ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="residency_number_<?php echo $employee['id']; ?>" class="form-label fw-bold">رقم الإقامة</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-passport"></i></span>
                                            <input type="text" class="form-control" id="residency_number_<?php echo $employee['id']; ?>" name="residency_number" value="<?php echo $employee['residency_number']; ?>">
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="department_id_<?php echo $employee['id']; ?>" class="form-label fw-bold">القسم <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-building"></i></span>
                                            <select class="form-select" id="department_id_<?php echo $employee['id']; ?>" name="department_id" required>
                                                <option value="">اختر القسم</option>
                                                <?php foreach ($departments as $department): ?>
                                                    <option value="<?php echo $department['id']; ?>" <?php echo $employee['department_id'] == $department['id'] ? 'selected' : ''; ?>><?php echo $department['name']; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="position_id_<?php echo $employee['id']; ?>" class="form-label fw-bold">المنصب <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-briefcase"></i></span>
                                            <select class="form-select" id="position_id_<?php echo $employee['id']; ?>" name="position_id" required>
                                                <option value="">اختر المنصب</option>
                                                <?php foreach ($positions as $position): ?>
                                                    <option value="<?php echo $position['id']; ?>" <?php echo $employee['position_id'] == $position['id'] ? 'selected' : ''; ?>><?php echo $position['title']; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="status_<?php echo $employee['id']; ?>" class="form-label fw-bold">الحالة الوظيفية</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-info-circle"></i></span>
                                            <select class="form-select" id="status_<?php echo $employee['id']; ?>" name="status">
                                                <option value="active" <?php echo $employee['status'] == 'active' ? 'selected' : ''; ?>>نشط</option>
                                                <option value="leave" <?php echo $employee['status'] == 'leave' ? 'selected' : ''; ?>>إجازة</option>
                                                <option value="terminated" <?php echo $employee['status'] == 'terminated' ? 'selected' : ''; ?>>منتهي الخدمة</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="edit-salary-<?php echo $employee['id']; ?>" role="tabpanel" aria-labelledby="edit-salary-tab-<?php echo $employee['id']; ?>">
                                <div class="alert alert-info mb-4">
                                    <i class="fas fa-info-circle me-2"></i>
                                    سيتم حساب الراتب الإجمالي تلقائياً بناءً على المبالغ المدخلة
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="basic_salary_<?php echo $employee['id']; ?>" class="form-label fw-bold">الراتب الأساسي</label>
                                        <div class="input-group">
                                            <span class="input-group-text">ريال</span>
                                            <input type="number" class="form-control" id="basic_salary_<?php echo $employee['id']; ?>" name="basic_salary" value="<?php echo $employee['basic_salary']; ?>" step="0.01" min="0">
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="housing_allowance_<?php echo $employee['id']; ?>" class="form-label fw-bold">بدل السكن</label>
                                        <div class="input-group">
                                            <span class="input-group-text">ريال</span>
                                            <input type="number" class="form-control" id="housing_allowance_<?php echo $employee['id']; ?>" name="housing_allowance" value="<?php echo $employee['housing_allowance']; ?>" step="0.01" min="0">
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="transportation_allowance_<?php echo $employee['id']; ?>" class="form-label fw-bold">بدل المواصلات</label>
                                        <div class="input-group">
                                            <span class="input-group-text">ريال</span>
                                            <input type="number" class="form-control" id="transportation_allowance_<?php echo $employee['id']; ?>" name="transportation_allowance" value="<?php echo $employee['transportation_allowance']; ?>" step="0.01" min="0">
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="other_allowances_<?php echo $employee['id']; ?>" class="form-label fw-bold">بدلات أخرى</label>
                                        <div class="input-group">
                                            <span class="input-group-text">ريال</span>
                                            <input type="number" class="form-control" id="other_allowances_<?php echo $employee['id']; ?>" name="other_allowances" value="<?php echo $employee['other_allowances']; ?>" step="0.01" min="0">
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="salary_<?php echo $employee['id']; ?>" class="form-label fw-bold">الراتب الإجمالي</label>
                                        <div class="input-group">
                                            <span class="input-group-text">ريال</span>
                                            <input type="number" class="form-control" id="salary_<?php echo $employee['id']; ?>" name="salary" value="<?php echo $employee['salary']; ?>" step="0.01" min="0" readonly>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="edit-account-<?php echo $employee['id']; ?>" role="tabpanel" aria-labelledby="edit-account-tab-<?php echo $employee['id']; ?>">
                                <div class="alert alert-info mb-4">
                                    <i class="fas fa-info-circle me-2"></i>
                                    اترك حقل كلمة المرور فارغاً إذا لم ترد تغييره
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="username_<?php echo $employee['id']; ?>" class="form-label fw-bold">اسم المستخدم</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                                            <input type="text" class="form-control" id="username_<?php echo $employee['id']; ?>" name="username" value="<?php echo $employee['username']; ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="password_<?php echo $employee['id']; ?>" class="form-label fw-bold">كلمة المرور</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                            <input type="password" class="form-control" id="password_<?php echo $employee['id']; ?>" name="password" placeholder="اتركه فارغاً إذا لم ترد تغييره">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>


                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">إلغاء</button>
                            <button type="submit" name="edit_employee" class="btn btn-primary">حفظ</button>
                        </div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php include 'includes/footer.php'; ?>

<script>
    // وظيفة البحث في جدول الموظفين
    document.getElementById('searchEmployees').addEventListener('keyup', function() {
        var searchValue = this.value.toLowerCase();
        var tableRows = document.querySelectorAll('#employeesTable tbody tr');

        tableRows.forEach(function(row) {
            var rowText = row.textContent.toLowerCase();

            if (rowText.includes(searchValue)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });

    // حساب الراتب الإجمالي تلقائياً
    function calculateTotalSalary(prefix = '') {
        const basicSalary = parseFloat(document.getElementById(prefix + 'basic_salary').value) || 0;
        const housingAllowance = parseFloat(document.getElementById(prefix + 'housing_allowance').value) || 0;
        const transportationAllowance = parseFloat(document.getElementById(prefix + 'transportation_allowance').value) || 0;
        const otherAllowances = parseFloat(document.getElementById(prefix + 'other_allowances').value) || 0;

        const totalSalary = basicSalary + housingAllowance + transportationAllowance + otherAllowances;

        if (document.getElementById(prefix + 'salary')) {
            document.getElementById(prefix + 'salary').value = totalSalary.toFixed(2);
        }
    }

    // إضافة مستمعي الأحداث لحساب الراتب الإجمالي
    document.addEventListener('DOMContentLoaded', function() {
        // لنموذج إضافة موظف جديد
        const salaryInputs = ['basic_salary', 'housing_allowance', 'transportation_allowance', 'other_allowances'];

        salaryInputs.forEach(function(id) {
            const element = document.getElementById(id);
            if (element) {
                element.addEventListener('input', function() {
                    calculateTotalSalary();
                });
            }
        });

        // لنماذج تعديل الموظفين
        <?php foreach ($employees as $employee): ?>
            const editSalaryInputs<?php echo $employee['id']; ?> = ['basic_salary_<?php echo $employee['id']; ?>', 'housing_allowance_<?php echo $employee['id']; ?>', 'transportation_allowance_<?php echo $employee['id']; ?>', 'other_allowances_<?php echo $employee['id']; ?>'];

            editSalaryInputs<?php echo $employee['id']; ?>.forEach(function(id) {
                const element = document.getElementById(id);
                if (element) {
                    element.addEventListener('input', function() {
                        calculateTotalSalary(id.replace(/_[^_]*$/, '_'));
                    });
                }
            });
        <?php endforeach; ?>
    });
</script>

</html>