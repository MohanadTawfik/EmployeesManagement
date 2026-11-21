<?php
require_once 'config.php';

// التحقق من تسجيل الدخول (باستثناء صفحة تسجيل الدخول نفسها)
if (!isset($_SESSION['user_id']) && basename($_SERVER['PHP_SELF']) != 'login.php') {
    header('Location: login.php');
    exit;
}

// الحصول على صلاحيات المستخدم
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

?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?><?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <?php if (isset($extra_css)): ?>
        <?php foreach ($extra_css as $css_file): ?>
            <link href="<?php echo $css_file; ?>" rel="stylesheet">
        <?php endforeach; ?>
    <?php endif; ?>
    <style>
        body {
            direction: rtl;
            text-align: right;
        }
    </style>
</head>

<body dir="rtl">
    <?php if (isset($_SESSION['user_id']) && !isset($custom_layout)): ?>
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
            <div class="container-fluid">
                <a class="navbar-brand" href="index.php">
                    <i class="fas fa-users me-2"></i><?php echo APP_NAME; ?>
                </a>

                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" href="index.php">
                                <i class="fas fa-tachometer-alt me-1"></i> الرئيسية
                            </a>
                        </li>

                        <?php if (isset($permissions['employees']) && $permissions['employees']): ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle <?php echo in_array(basename($_SERVER['PHP_SELF']), ['employees.php', 'departments.php', 'positions.php']) ? 'active' : ''; ?>" href="#" id="employeesDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-users me-1"></i> الموظفون
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="employeesDropdown">
                                    <li><a class="dropdown-item" href="employees.php">إدارة الموظفين</a></li>
                                    <li><a class="dropdown-item" href="departments.php">الأقسام</a></li>
                                    <li><a class="dropdown-item" href="positions.php">المناصب</a></li>
                                </ul>
                            </li>
                        <?php endif; ?>

                        <?php if ((isset($permissions['attendance']) && $permissions['attendance']) || (isset($permissions['attendance_own']) && $permissions['attendance_own'])): ?>
                            <li class="nav-item">
                                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'attendance.php' ? 'active' : ''; ?>" href="attendance.php">
                                    <i class="fas fa-clock me-1"></i> الحضور والانصراف
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php if (isset($permissions['assets']) && $permissions['assets']): ?>
                            <li class="nav-item">
                                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'assets.php' ? 'active' : ''; ?>" href="assets.php">
                                    <i class="fas fa-box me-1"></i> إدارة العهد
                                </a>
                            </li>
                        <?php elseif (isset($permissions['assets_own']) && $permissions['assets_own']): ?>
                            <li class="nav-item">
                                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'assets_employee.php' ? 'active' : ''; ?>" href="assets_employee.php">
                                    <i class="fas fa-box me-1"></i> العهد المسندة إلي
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php if ((isset($permissions['leaves']) && $permissions['leaves'])): ?>
                            <li class="nav-item">
                                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'leaves.php' ? 'active' : ''; ?>" href="leaves.php">
                                    <i class="fas fa-calendar-alt me-1"></i> الإجازات
                                </a>
                            </li>
                        <?php endif; ?>
                        <?php if ((isset($permissions['leaves_own']) && $permissions['leaves_own'])): ?>
                            <li class="nav-item">
                                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'employee_leaves.php' ? 'active' : ''; ?>" href="employee_leaves.php">
                                    <i class="fas fa-calendar-alt me-1"></i> إجازاتي
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php if (isset($permissions['notes']) && $permissions['notes']): ?>
                            <li class="nav-item">
                                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'notes.php' ? 'active' : ''; ?>" href="notes.php">
                                    <i class="fas fa-sticky-note me-1"></i> الملاحظات
                                </a>
                            </li>
                        <?php elseif (isset($permissions['notes_own']) && $permissions['notes_own']): ?>
                            <li class="nav-item">
                                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'notes_own.php' ? 'active' : ''; ?>" href="notes_own.php">
                                    <i class="fas fa-sticky-note me-1"></i> ملاحظاتي
                                </a>
                            </li>
                        <?php endif; ?>



                    </ul>

                    <ul class="navbar-nav">

                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user-circle me-1"></i> <?php echo $_SESSION['full_name']; ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                <?php if (isset($permissions['profile']) && $permissions['profile']): ?>
                                    <li><a class="dropdown-item" href="profile.php">
                                            <i class="fas fa-user me-2"></i> الملف الشخصي
                                        </a></li>
                                <?php endif; ?>

                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li><a class="dropdown-item" href="logout.php">
                                        <i class="fas fa-sign-out-alt me-2"></i> تسجيل الخروج
                                    </a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>

    <?php endif; ?>
    <?php if (isset($_SESSION['user_id']) && !isset($custom_layout)): ?>
        <div class="container-fluid py-4">
        <?php endif; ?>