<?php
require_once 'config.php';

// حساب إحصائيات لوحة التحكم
$stats = [];

// عدد الموظفين
try {
    if (isset($permissions['all']) && $permissions['all']) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM employees WHERE status = 'active'");
        $stats['employees'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    } else {
        // للموظف العادي، عرض بياناته الشخصية بدلاً من عدد الموظفين
        $stmt = $pdo->prepare("SELECT first_name, last_name, email, phone, hire_date FROM employees WHERE id = ?");
        $stmt->execute([$_SESSION['employee_id']]);
        $stats['employee_info'] = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['employees'] = 1; // للموظف العادي، عرض 1 بدلاً من عدد الموظفين
    }
} catch (PDOException $e) {
    $stats['employees'] = 0;
}

// عدد الحضور اليوم
try {
    if (isset($permissions['all']) && $permissions['all']) {
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT employee_id) as count FROM attendance WHERE date = CURDATE() AND status = 'present'");
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM attendance WHERE employee_id = ? AND date = CURDATE() AND status = 'present'");
        $stmt->execute([$_SESSION['employee_id']]);
    }
    $attendanceResult = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['attendance_today'] = $attendanceResult ? $attendanceResult['count'] : 0;
} catch (PDOException $e) {
    $stats['attendance_today'] = 0;
}

// عدد العهد المستخدمة
try {
    if (isset($permissions['all']) && $permissions['all']) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM asset_assignments WHERE status = 'assigned'");
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM asset_assignments WHERE employee_id = ? AND status = 'assigned'");
        $stmt->execute([$_SESSION['employee_id']]);
    }
    $stats['assigned_assets'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
} catch (PDOException $e) {
    $stats['assigned_assets'] = 0;
}

// عدد الإجازات المعلقة
try {
    if (isset($permissions['all']) && $permissions['all']) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM leaves WHERE status = 'pending'");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['pending_leaves'] = $result ? $result['count'] : 0;
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM leaves WHERE employee_id = ? AND status = 'pending'");
        $stmt->execute([$_SESSION['employee_id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['pending_leaves'] = $result ? $result['count'] : 0;
    }
} catch (PDOException $e) {
    $stats['pending_leaves'] = 0;
}
?>

<?php
$page_title = "لوحة التحكم";
$extra_js = ["https://cdn.jsdelivr.net/npm/chart.js"];
include 'includes/header.php';
?>


<!-- المحتوى الرئيسي -->
<div class="container-fluid mt-4">
    <!-- رسائل النجاح والخطأ -->
    <?php
    // عرض رسائل النجاح
    if (isset($_GET['checkin_success'])) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
              <i class="fas fa-check-circle me-2"></i>تم تسجيل الحضور بنجاح
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
    } elseif (isset($_GET['checkout_success'])) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
              <i class="fas fa-check-circle me-2"></i>تم تسجيل الانصراف بنجاح
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
    }
    ?>
    
    <!-- رسالة الترحيب -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 bg-gradient-primary text-white">
                <div class="card-body py-4">
                    <h1 class="h3 mb-0">مرحباً بك، <?php echo $_SESSION['full_name']; ?>!</h1>
                    <p class="mb-0 opacity-75"><?php echo date('l، d F Y'); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- بطاقات الإحصائيات -->
    <?php if (isset($permissions['all']) && $permissions['all']): ?>
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <div class="small fw-bold text-primary text-uppercase mb-1">الموظفون</div>
                                <div class="h5 mb-0 fw-bold"><?php echo $stats['employees']; ?></div>
                            </div>
                            <div class="ms-2"><i class="fas fa-users fa-2x text-primary opacity-75"></i></div>
                        </div>
                        <div class="mt-2">
                            <a href="employees.php" class="btn btn-sm btn-outline-primary">عرض الموظفين</a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <div class="small fw-bold text-success text-uppercase mb-1">الحضور اليوم</div>
                                <div class="h5 mb-0 fw-bold"><?php echo $stats['attendance_today']; ?></div>
                            </div>
                            <div class="ms-2"><i class="fas fa-user-check fa-2x text-success opacity-75"></i></div>
                        </div>
                        <div class="mt-2">
                            <a href="attendance.php" class="btn btn-sm btn-outline-success">عرض الحضور</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <div class="small fw-bold text-warning text-uppercase mb-1">العهد المستخدمة</div>
                                <div class="h5 mb-0 fw-bold"><?php echo $stats['assigned_assets']; ?></div>
                            </div>
                            <div class="ms-2"><i class="fas fa-box fa-2x text-warning opacity-75"></i></div>
                        </div>
                        <div class="mt-2">
                            <a href="assets.php" class="btn btn-sm btn-outline-warning">عرض العهد</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <div class="small fw-bold text-danger text-uppercase mb-1">الإجازات المعلقة</div>
                                <div class="h5 mb-0 fw-bold"><?php echo $stats['pending_leaves']; ?></div>
                            </div>
                            <div class="ms-2"><i class="fas fa-calendar-times fa-2x text-danger opacity-75"></i></div>
                        </div>
                        <?php if ($stats['pending_leaves'] > 0): ?>
                            <div class="mt-2">
                                <a href="leaves.php?status=pending" class="btn btn-sm btn-outline-danger">عرض الإجازات المعلقة</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>



        </div>
    <?php endif; ?>

    <!-- تسجيل الحضور والانصراف السريع -->
    <div class="row mb-4">
        <div class="col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 pt-4 pb-3">
                    <h5 class="card-title mb-0"><i class="fas fa-clock text-primary"></i> تسجيل الحضور والانصراف</h5>
                </div>
                <div class="card-body">
                    <?php
                    // التحقق إذا كان المستخدم قد سجل حضوره اليوم
                    $attendanceRecord = null;
                    try {
                        $stmt = $pdo->prepare("SELECT * FROM attendance WHERE employee_id = ? AND date = CURDATE()");
                        $stmt->execute([$_SESSION['employee_id']]);
                        $attendanceRecord = $stmt->fetch(PDO::FETCH_ASSOC);
                    } catch (PDOException $e) {
                        echo "<div class='alert alert-danger'>خطأ: " . $e->getMessage() . "</div>";
                    }

                    if ($attendanceRecord) {
                        if ($attendanceRecord['check_out']) {
                            echo "<div class='alert alert-info'>لقد سجلت حضورك وانصرافك اليوم</div>";
                            echo "<p>وقت الحضور: " . $attendanceRecord['check_in'] . "</p>";
                            echo "<p>وقت الانصراف: " . $attendanceRecord['check_out'] . "</p>";
                        } else {
                            echo "<div class='alert alert-warning'>لقد سجلت حضورك اليوم ولم تسجل انصرافك بعد</div>";
                            echo "<p>وقت الحضور: " . $attendanceRecord['check_in'] . "</p>";
                            echo "<form method='post' action='process_attendance.php'>
                                    <input type='hidden' name='action' value='checkout'>
                                    <button type='submit' class='btn btn-danger w-100'>
                                        <i class='fas fa-sign-out-alt'></i> تسجيل الانصراف
                                    </button>
                                </form>";
                        }
                    } else {
                        echo "<form method='post' action='process_attendance.php'>
                                <input type='hidden' name='action' value='checkin'>
                                <button type='submit' class='btn btn-success w-100'>
                                    <i class='fas fa-sign-in-alt'></i> تسجيل الحضور
                                </button>
                            </form>";
                    }
                    ?>
                </div>
            </div>
        </div>

        <!-- الإشعارات والملاحظات -->
        <div class="col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 pt-4 pb-3">
                    <h5 class="card-title mb-0"><i class="fas fa-bell text-primary"></i> الإشعارات والملاحظات</h5>
                </div>
                <div class="card-body">
                    <?php
                    // جلب أحدث الملاحظات للموظف
                    try {
                        $stmt = $pdo->prepare("SELECT n.*, e.first_name, e.last_name FROM notes n JOIN employees e ON n.author_id = e.id WHERE n.employee_id = ? ORDER BY n.created_at DESC LIMIT 5");
                        $stmt->execute([$_SESSION['employee_id']]);
                        $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        if (count($notes) > 0) {
                            foreach ($notes as $note) {
                                $typeClass = $note['type'] == 'positive' ? 'success' : ($note['type'] == 'negative' ? 'danger' : 'warning');
                                echo "<div class='alert alert-" . $typeClass . " d-flex align-items-center' role='alert'>
                                        <div class='flex-grow-1'>
                                            <div class='fw-bold'>" . $note['first_name'] . " " . $note['last_name'] . "</div>
                                            <div>" . $note['content'] . "</div>
                                            <small class='text-muted'>" . date('Y-m-d H:i', strtotime($note['created_at'])) . "</small>
                                        </div>
                                    </div>";
                            }
                        } else {
                            echo "<div class='alert alert-info'>لا توجد إشعارات حالياً</div>";
                        }
                    } catch (PDOException $e) {
                        echo "<div class='alert alert-danger'>خطأ: " . $e->getMessage() . "</div>";
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>

    <!-- إحصائيات الموظف العادي -->
    <?php if (!isset($permissions['all']) || !$permissions['all']): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 pt-4 pb-3">
                        <h5 class="card-title mb-0"><i class="fas fa-chart-pie text-primary"></i> إحصائياتي</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <div class="card border-0 bg-success text-white">
                                    <div class="card-body text-center">
                                        <i class="fas fa-check-circle fa-2x mb-2"></i>
                                        <h5 class="card-title">أيام الحضور</h5>
                                        <?php
                                        try {
                                            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM attendance WHERE employee_id = ? AND status = 'present' AND YEAR(date) = YEAR(CURDATE()) AND MONTH(date) = MONTH(CURDATE())");
                                            $stmt->execute([$_SESSION['employee_id']]);
                                            $presentDays = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                                            echo "<h2>" . $presentDays . "</h2>";
                                        } catch (PDOException $e) {
                                            echo "<h2>0</h2>";
                                        }
                                        ?>
                                        <p class="card-text">هذا الشهر</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="card border-0 bg-danger text-white">
                                    <div class="card-body text-center">
                                        <i class="fas fa-times-circle fa-2x mb-2"></i>
                                        <h5 class="card-title">أيام الغياب</h5>
                                        <?php
                                        try {
                                            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM attendance WHERE employee_id = ? AND status = 'absent' AND YEAR(date) = YEAR(CURDATE()) AND MONTH(date) = MONTH(CURDATE())");
                                            $stmt->execute([$_SESSION['employee_id']]);
                                            $absentDays = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                                            echo "<h2>" . $absentDays . "</h2>";
                                        } catch (PDOException $e) {
                                            echo "<h2>0</h2>";
                                        }
                                        ?>
                                        <p class="card-text">هذا الشهر</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="card border-0 bg-warning text-white">
                                    <div class="card-body text-center">
                                        <i class="fas fa-calendar-alt fa-2x mb-2"></i>
                                        <h5 class="card-title">أيام الإجازة</h5>
                                        <?php
                                        try {
                                            $stmt = $pdo->prepare("SELECT SUM(days) as total FROM leaves WHERE employee_id = ? AND status = 'approved' AND YEAR(start_date) = YEAR(CURDATE())");
                                            $stmt->execute([$_SESSION['employee_id']]);
                                            $leaveDays = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                                            echo "<h2>" . ($leaveDays ?: 0) . "</h2>";
                                        } catch (PDOException $e) {
                                            echo "<h2>0</h2>";
                                        }
                                        ?>
                                        <p class="card-text">هذا العام</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="card border-0 bg-info text-white">
                                    <div class="card-body text-center">
                                        <i class="fas fa-box fa-2x mb-2"></i>
                                        <h5 class="card-title">العهد المسندة</h5>
                                        <?php
                                        try {
                                            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM asset_assignments WHERE employee_id = ? AND status = 'assigned'");
                                            $stmt->execute([$_SESSION['employee_id']]);
                                            $assignedAssets = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                                            echo "<h2>" . $assignedAssets . "</h2>";
                                        } catch (PDOException $e) {
                                            echo "<h2>0</h2>";
                                        }
                                        ?>
                                        <p class="card-text">حالياً</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- إحصائيات مفصلة للمدير -->
    <?php if (isset($permissions['all']) && $permissions['all']): ?>
        <div class="row mb-4">
            <div class="col-lg-6 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0 pt-4 pb-3">
                        <h5 class="card-title mb-0"><i class="fas fa-chart-bar text-primary"></i> إحصائيات الحضور الشهرية</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="attendanceChart" height="200"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0 pt-4 pb-3">
                        <h5 class="card-title mb-0"><i class="fas fa-chart-pie text-primary"></i> توزيع الموظفين حسب الأقسام</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="departmentChart" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-lg-4 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0 pt-4 pb-3">
                        <h5 class="card-title mb-0"><i class="fas fa-trophy text-warning"></i> أفضل الموظفين هذا الشهر</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>الموظف</th>
                                        <th>أيام الحضور</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    try {
                                        $stmt = $pdo->query("SELECT e.first_name, e.last_name, COUNT(a.id) as attendance_days 
                                            FROM employees e 
                                            LEFT JOIN attendance a ON e.id = a.employee_id AND a.status = 'present' 
                                                AND YEAR(a.date) = YEAR(CURDATE()) AND MONTH(a.date) = MONTH(CURDATE())
                                            WHERE e.status = 'active'
                                            GROUP BY e.id
                                            ORDER BY attendance_days DESC
                                            LIMIT 5");
                                        $topEmployees = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                        if (count($topEmployees) > 0) {
                                            foreach ($topEmployees as $employee) {
                                                echo "<tr>
                                                    <td>" . $employee['first_name'] . " " . $employee['last_name'] . "</td>
                                                    <td>" . $employee['attendance_days'] . "</td>
                                                </tr>";
                                            }
                                        } else {
                                            echo "<tr><td colspan='2' class='text-center'>لا توجد بيانات</td></tr>";
                                        }
                                    } catch (PDOException $e) {
                                        echo "<tr><td colspan='2' class='text-center text-danger'>خطأ: " . $e->getMessage() . "</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0 pt-4 pb-3">
                        <h5 class="card-title mb-0"><i class="fas fa-exclamation-triangle text-danger"></i> الإجازات الأحدث</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>الموظف</th>
                                        <th>المدة</th>
                                        <th>الحالة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    try {
                                        $stmt = $pdo->query("SELECT l.*, e.first_name, e.last_name 
                                            FROM leaves l 
                                            JOIN employees e ON l.employee_id = e.id 
                                            ORDER BY l.created_at DESC 
                                            LIMIT 5");
                                        $recentLeaves = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                        if (count($recentLeaves) > 0) {
                                            foreach ($recentLeaves as $leave) {
                                                $statusClass = $leave['status'] == 'approved' ? 'success' : ($leave['status'] == 'rejected' ? 'danger' : 'warning');
                                                $statusText = $leave['status'] == 'approved' ? 'موافق' : ($leave['status'] == 'rejected' ? 'مرفوض' : 'معلق');

                                                echo "<tr>
                                                    <td>" . $leave['first_name'] . " " . $leave['last_name'] . "</td>
                                                    <td>" . $leave['days'] . " يوم</td>
                                                    <td><span class='badge bg-" . $statusClass . "'>" . $statusText . "</span></td>
                                                </tr>";
                                            }
                                        } else {
                                            echo "<tr><td colspan='3' class='text-center'>لا توجد بيانات</td></tr>";
                                        }
                                    } catch (PDOException $e) {
                                        echo "<tr><td colspan='3' class='text-center text-danger'>خطأ: " . $e->getMessage() . "</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0 pt-4 pb-3">
                        <h5 class="card-title mb-0"><i class="fas fa-box text-info"></i> العهد الأكثر استخداماً</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>العهدة</th>
                                        <th>عدد مرات الاستخدام</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    try {
                                        $stmt = $pdo->query("SELECT a.name, COUNT(aa.id) as usage_count 
                                            FROM assets a 
                                            JOIN asset_assignments aa ON a.id = aa.asset_id 
                                            GROUP BY a.id 
                                            ORDER BY usage_count DESC 
                                            LIMIT 5");
                                        $topAssets = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                        if (count($topAssets) > 0) {
                                            foreach ($topAssets as $asset) {
                                                echo "<tr>
                                                    <td>" . $asset['name'] . "</td>
                                                    <td>" . $asset['usage_count'] . "</td>
                                                </tr>";
                                            }
                                        } else {
                                            echo "<tr><td colspan='2' class='text-center'>لا توجد بيانات</td></tr>";
                                        }
                                    } catch (PDOException $e) {
                                        echo "<tr><td colspan='2' class='text-center text-danger'>خطأ: " . $e->getMessage() . "</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- جدول الحضور الأخير -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 pt-4 pb-3 d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0"><i class="fas fa-clock text-primary"></i> سجل الحضور الأخير</h5>
                        <a href="attendance.php" class="btn btn-sm btn-outline-primary">عرض الكل</a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>الموظف</th>
                                        <th>التاريخ</th>
                                        <th>وقت الحضور</th>
                                        <th>وقت الانصراف</th>
                                        <th>الحالة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    try {
                                        $stmt = $pdo->query("SELECT a.*, e.first_name, e.last_name FROM attendance a JOIN employees e ON a.employee_id = e.id ORDER BY a.date DESC, a.check_in DESC LIMIT 10");
                                        $attendanceRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                        if (count($attendanceRecords) > 0) {
                                            foreach ($attendanceRecords as $record) {
                                                $statusClass = $record['status'] == 'present' ? 'success' : ($record['status'] == 'absent' ? 'danger' : 'warning');
                                                $statusText = $record['status'] == 'present' ? 'حاضر' : ($record['status'] == 'absent' ? 'غائب' : 'إجازة');

                                                echo "<tr>
                                                    <td>" . $record['first_name'] . " " . $record['last_name'] . "</td>
                                                    <td>" . $record['date'] . "</td>
                                                    <td>" . ($record['check_in'] ?: '-') . "</td>
                                                    <td>" . ($record['check_out'] ?: '-') . "</td>
                                                    <td><span class='badge bg-" . $statusClass . "'>" . $statusText . "</span></td>
                                                </tr>";
                                            }
                                        } else {
                                            echo "<tr><td colspan='5' class='text-center'>لا توجد سجلات حضور</td></tr>";
                                        }
                                    } catch (PDOException $e) {
                                        echo "<tr><td colspan='5' class='text-center text-danger'>خطأ: " . $e->getMessage() . "</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 pt-4 pb-3 d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0"><i class="fas fa-clock text-primary"></i> سجل الحضور الخاص بي</h5>
                        <a href="attendance.php" class="btn btn-sm btn-outline-primary">عرض الكل</a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>التاريخ</th>
                                        <th>وقت الحضور</th>
                                        <th>وقت الانصراف</th>
                                        <th>الحالة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    try {
                                        $stmt = $pdo->prepare("SELECT * FROM attendance WHERE employee_id = ? ORDER BY date DESC, check_in DESC LIMIT 10");
                                        $stmt->execute([$_SESSION['employee_id']]);
                                        $attendanceRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                        if (count($attendanceRecords) > 0) {
                                            foreach ($attendanceRecords as $record) {
                                                $statusClass = $record['status'] == 'present' ? 'success' : ($record['status'] == 'absent' ? 'danger' : 'warning');
                                                $statusText = $record['status'] == 'present' ? 'حاضر' : ($record['status'] == 'absent' ? 'غائب' : 'إجازة');

                                                echo "<tr>
                                                    <td>" . $record['date'] . "</td>
                                                    <td>" . ($record['check_in'] ?: '-') . "</td>
                                                    <td>" . ($record['check_out'] ?: '-') . "</td>
                                                    <td><span class='badge bg-" . $statusClass . "'>" . $statusText . "</span></td>
                                                </tr>";
                                            }
                                        } else {
                                            echo "<tr><td colspan='4' class='text-center'>لا توجد سجلات حضور</td></tr>";
                                        }
                                    } catch (PDOException $e) {
                                        echo "<tr><td colspan='4' class='text-center text-danger'>خطأ: " . $e->getMessage() . "</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- تذييل الصفحة -->
<footer class="bg-light text-center text-muted py-3 mt-5">
    <div class="container-fluid">
        <p class="mb-0">&copy; <?php echo date('Y'); ?> - <?php echo APP_NAME; ?> - الإصدار <?php echo APP_VERSION; ?></p>
    </div>
    <?php include 'includes/footer.php'; ?>

<?php if (isset($permissions['all']) && $permissions['all']): ?>
    <!-- بيانات الرسوم البيانية -->
    <script>
        // بيانات إحصائيات الحضور الشهرية
        const attendanceCtx = document.getElementById('attendanceChart').getContext('2d');
        const attendanceChart = new Chart(attendanceCtx, {
            type: 'bar',
            data: {
                labels: ['حاضر', 'غائب', 'إجازة'],
                datasets: [{
                    label: 'إحصائيات الحضور لهذا الشهر',
                    data: [
                        <?php
                        try {
                            $stmt = $pdo->query("SELECT COUNT(*) as count FROM attendance WHERE status = 'present' AND YEAR(date) = YEAR(CURDATE()) AND MONTH(date) = MONTH(CURDATE())");
                            $present = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

                            $stmt = $pdo->query("SELECT COUNT(*) as count FROM attendance WHERE status = 'absent' AND YEAR(date) = YEAR(CURDATE()) AND MONTH(date) = MONTH(CURDATE())");
                            $absent = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

                            $stmt = $pdo->query("SELECT COUNT(*) as count FROM attendance WHERE status = 'leave' AND YEAR(date) = YEAR(CURDATE()) AND MONTH(date) = MONTH(CURDATE())");
                            $leave = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

                            echo "$present, $absent, $leave";
                        } catch (PDOException $e) {
                            echo "0, 0, 0";
                        }
                        ?>
                    ],
                    backgroundColor: [
                        'rgba(75, 192, 192, 0.2)',
                        'rgba(255, 99, 132, 0.2)',
                        'rgba(255, 206, 86, 0.2)'
                    ],
                    borderColor: [
                        'rgba(75, 192, 192, 1)',
                        'rgba(255, 99, 132, 1)',
                        'rgba(255, 206, 86, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // بيانات توزيع الموظفين حسب الأقسام
        const departmentCtx = document.getElementById('departmentChart').getContext('2d');
        const departmentChart = new Chart(departmentCtx, {
            type: 'pie',
            data: {
                labels: [
                    <?php
                    try {
                        $stmt = $pdo->query("SELECT d.name, COUNT(e.id) as employee_count 
                            FROM departments d 
                            LEFT JOIN employees e ON d.id = e.department_id AND e.status = 'active' 
                            GROUP BY d.id 
                            ORDER BY employee_count DESC");
                        $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        foreach ($departments as $department) {
                            echo "'" . $department['name'] . "', ";
                        }
                    } catch (PDOException $e) {
                        echo "'لا توجد بيانات'";
                    }
                    ?>
                ],
                datasets: [{
                    data: [
                        <?php
                        try {
                            foreach ($departments as $department) {
                                echo $department['employee_count'] . ", ";
                            }
                        } catch (Exception $e) {
                            echo "0";
                        }
                        ?>
                    ],
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.2)',
                        'rgba(54, 162, 235, 0.2)',
                        'rgba(255, 206, 86, 0.2)',
                        'rgba(75, 192, 192, 0.2)',
                        'rgba(153, 102, 255, 0.2)',
                        'rgba(255, 159, 64, 0.2)'
                    ],
                    borderColor: [
                        'rgba(255, 99, 132, 1)',
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 206, 86, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(153, 102, 255, 1)',
                        'rgba(255, 159, 64, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
    </script>
<?php endif; ?>

    </html>