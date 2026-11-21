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

// معالجة تحديث بيانات الملف الشخصي
if (isset($_POST['update_profile'])) {
    try {
        $stmt = $pdo->prepare("UPDATE employees SET phone = ?, email = ?, address = ? WHERE id = ?");
        $stmt->execute([
            $_POST['phone'],
            $_POST['email'],
            $_POST['address'],
            $_SESSION['employee_id']
        ]);

        $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
        $stmt->execute([
            $_POST['email'],
            $_SESSION['user_id']
        ]);

        $success_message = "تم تحديث بيانات الملف الشخصي بنجاح";

        // تحديث بيانات الجلسة
        $_SESSION['email'] = $_POST['email'];
        
        // إعادة تحميل الصفحة لعرض البيانات المحدثة
        header("Location: profile.php?success=1");
        exit;
    } catch (PDOException $e) {
        $error_message = "خطأ في تحديث بيانات الملف الشخصي: " . $e->getMessage();
    }
}

// معالجة تغيير كلمة المرور
if (isset($_POST['change_password'])) {
    try {
        // التحقق من كلمة المرور الحالية
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!password_verify($_POST['current_password'], $user['password'])) {
            $error_message = "كلمة المرور الحالية غير صحيحة";
        } elseif ($_POST['new_password'] !== $_POST['confirm_password']) {
            $error_message = "كلمة المرور الجديدة وتأكيدها غير متطابقين";
        } elseif (strlen($_POST['new_password']) < 6) {
            $error_message = "كلمة المرور الجديدة يجب أن تكون 6 أحرف على الأقل";
        } else {
            $hashed_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([
                $hashed_password,
                $_SESSION['user_id']
            ]);

            $success_message = "تم تغيير كلمة المرور بنجاح";
        
        // إعادة تحميل الصفحة لعرض البيانات المحدثة
        header("Location: profile.php?password_success=1");
        exit;
        }
    } catch (PDOException $e) {
        $error_message = "خطأ في تغيير كلمة المرور: " . $e->getMessage();
    }
}

// معالجة تحديث الصورة الشخصية
if (isset($_POST['update_picture']) && isset($_FILES['profile_picture'])) {
    try {
        // التحقق من وجود ملف مرفوع
        if ($_FILES['profile_picture']['error'] == UPLOAD_ERR_OK) {
            // التحقق من نوع الملف
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $file_type = $_FILES['profile_picture']['type'];

            if (!in_array($file_type, $allowed_types)) {
                $error_message = "نوع الملف غير مدعوم. يرجى رفع صورة بصيغة JPEG أو PNG أو GIF";
            } else {
                // إنشاء اسم فريد للملف
                $file_extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
                $file_name = 'profile_' . $_SESSION['employee_id'] . '_' . time() . '.' . $file_extension;
                $upload_path = UPLOAD_PATH . $file_name;

                // التأكد من وجود مجلد الرفع
                if (!is_dir(UPLOAD_PATH)) {
                    mkdir(UPLOAD_PATH, 0755, true);
                }

                // نقل الملف إلى مجلد الرفع
                if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                    // حذف الصورة القديمة إذا وجدت
                    if (!empty($currentUser['profile_picture']) && file_exists(UPLOAD_PATH . $currentUser['profile_picture'])) {
                        unlink(UPLOAD_PATH . $currentUser['profile_picture']);
                    }

                    // تحديث مسار الصورة في قاعدة البيانات
                    $stmt = $pdo->prepare("UPDATE employees SET profile_picture = ? WHERE id = ?");
                    $stmt->execute([
                        $file_name,
                        $_SESSION['employee_id']
                    ]);

                    $success_message = "تم تحديث الصورة الشخصية بنجاح";

                    // تحديث بيانات المستخدم الحالي
                    $currentUser['profile_picture'] = $file_name;
                    
                    // إعادة تحميل الصفحة لعرض البيانات المحدثة
                    header("Location: profile.php?picture_success=1");
                    exit;
                } else {
                    $error_message = "خطأ في رفع الصورة";
                }
            }
        } else {
            $error_message = "يرجى اختيار صورة";
        }
    } catch (PDOException $e) {
        $error_message = "خطأ في تحديث الصورة الشخصية: " . $e->getMessage();
    }
}

// الحصول على إحصائيات الحضور
$attendance_stats = [];
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_days, SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days, SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days, SUM(CASE WHEN status = 'leave' THEN 1 ELSE 0 END) as leave_days FROM attendance WHERE employee_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
    $stmt->execute([$_SESSION['employee_id']]);
    $attendance_stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "خطأ في جلب إحصائيات الحضور: " . $e->getMessage();
}

// الحصول على العهد المسندة للموظف
$assigned_assets = [];
try {
    $stmt = $pdo->prepare("SELECT aa.*, a.name, a.description FROM asset_assignments aa JOIN assets a ON aa.asset_id = a.id WHERE aa.employee_id = ? AND aa.status = 'assigned' ORDER BY aa.assigned_date DESC");
    $stmt->execute([$_SESSION['employee_id']]);
    $assigned_assets = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "خطأ في جلب بيانات العهد: " . $e->getMessage();
}

// الحصول على الملاحظات للموظف
$notes = [];
try {
    $stmt = $pdo->prepare("SELECT n.*, a.first_name as author_first, a.last_name as author_last FROM notes n JOIN employees a ON n.author_id = a.id WHERE n.employee_id = ? ORDER BY n.created_at DESC LIMIT 5");
    $stmt->execute([$_SESSION['employee_id']]);
    $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "خطأ في جلب الملاحظات: " . $e->getMessage();
}
?>
<?php
$page_title = "الملف الشخصي";
include 'includes/header.php';
?>

<!-- المحتوى الرئيسي -->
<div class="container-fluid mt-4">
    <!-- رسائل النجاح والخطأ -->
    <?php
    // عرض رسائل النجاح
    if (isset($_GET['success'])) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
              <i class="fas fa-check-circle me-2"></i>تم تحديث بيانات الملف الشخصي بنجاح
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
    } elseif (isset($_GET['password_success'])) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
              <i class="fas fa-check-circle me-2"></i>تم تغيير كلمة المرور بنجاح
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
    } elseif (isset($_GET['picture_success'])) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
              <i class="fas fa-check-circle me-2"></i>تم تحديث الصورة الشخصية بنجاح
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

    <!-- معلومات الملف الشخصي -->
    <div class="row mb-4">
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-body text-center">
                    <?php if (!empty($currentUser['profile_picture'])): ?>
                        <img src="<?php echo UPLOAD_PATH . $currentUser['profile_picture']; ?>" class="profile-img mb-3" alt="الصورة الشخصية">
                    <?php else: ?>
                        <div class="profile-img mb-3 d-flex align-items-center justify-content-center bg-light">
                            <i class="fas fa-user fa-4x text-muted"></i>
                        </div>
                    <?php endif; ?>

                    <h5 class="card-title"><?php echo $currentUser['first_name'] . ' ' . $currentUser['last_name']; ?></h5>
                    <p class="card-text text-muted"><?php echo $currentUser['role_name']; ?></p>

                    <?php if (!empty($currentUser['department_name'])): ?>
                        <p class="card-text"><i class="fas fa-building"></i> <?php echo $currentUser['department_name']; ?></p>
                    <?php endif; ?>

                    <?php if (!empty($currentUser['position_name'])): ?>
                        <p class="card-text"><i class="fas fa-briefcase"></i> <?php echo $currentUser['position_name']; ?></p>
                    <?php endif; ?>

                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#updatePictureModal">
                        <i class="fas fa-camera"></i> تغيير الصورة
                    </button>
                </div>
            </div>
        </div>

        <div class="col-md-8 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white border-0 pt-4 pb-3">
                    <h5 class="card-title mb-0">المعلومات الشخصية</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-sm-3 text-muted">الاسم الكامل:</div>
                        <div class="col-sm-9"><?php echo $currentUser['first_name'] . ' ' . $currentUser['last_name']; ?></div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-sm-3 text-muted">الرقم الوطني:</div>
                        <div class="col-sm-9"><?php echo $currentUser['national_id']; ?></div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-sm-3 text-muted">تاريخ الميلاد:</div>
                        <div class="col-sm-9"><?php echo date('Y/m/d', strtotime($currentUser['birth_date'])); ?></div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-sm-3 text-muted">تاريخ التوظيف:</div>
                        <div class="col-sm-9"><?php echo date('Y/m/d', strtotime($currentUser['hire_date'])); ?></div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-sm-3 text-muted">البريد الإلكتروني:</div>
                        <div class="col-sm-9"><?php echo $currentUser['email']; ?></div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-sm-3 text-muted">رقم الهاتف:</div>
                        <div class="col-sm-9"><?php echo $currentUser['phone']; ?></div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-sm-3 text-muted">العنوان:</div>
                        <div class="col-sm-9"><?php echo $currentUser['address']; ?></div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-sm-3 text-muted">الحالة الوظيفية:</div>
                        <div class="col-sm-9">
                            <?php
                            if ($currentUser['status'] == 'active') {
                                echo '<span class="badge bg-success">نشط</span>';
                            } elseif ($currentUser['status'] == 'leave') {
                                echo '<span class="badge bg-warning">إجازة</span>';
                            } else {
                                echo '<span class="badge bg-danger">منتهية الخدمة</span>';
                            }
                            ?>
                        </div>
                    </div>

                    <div class="text-start mt-4">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                            <i class="fas fa-edit"></i> تعديل المعلومات
                        </button>

                        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
                            <i class="fas fa-key"></i> تغيير كلمة المرور
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- إحصائيات الحضور -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-white border-0 pt-4 pb-3">
                    <h5 class="card-title mb-0">إحصائيات الحضور (آخر 30 يوم)</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-3 mb-3">
                            <div class="card border-0 bg-light h-100">
                                <div class="card-body py-3">
                                    <h2 class="h4 mb-1"><?php echo $attendance_stats['total_days']; ?></h2>
                                    <p class="card-text text-muted mb-0">إجمالي الأيام</p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3 mb-3">
                            <div class="card border-0 bg-success text-white h-100">
                                <div class="card-body py-3">
                                    <h2 class="h4 mb-1"><?php echo $attendance_stats['present_days']; ?></h2>
                                    <p class="card-text mb-0">أيام الحضور</p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3 mb-3">
                            <div class="card border-0 bg-danger text-white h-100">
                                <div class="card-body py-3">
                                    <h2 class="h4 mb-1"><?php echo $attendance_stats['absent_days']; ?></h2>
                                    <p class="card-text mb-0">أيام الغياب</p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3 mb-3">
                            <div class="card border-0 bg-warning text-white h-100">
                                <div class="card-body py-3">
                                    <h2 class="h4 mb-1"><?php echo $attendance_stats['leave_days']; ?></h2>
                                    <p class="card-text mb-0">أيام الإجازة</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- العهد المسندة -->
    <?php if (count($assigned_assets) > 0): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-white border-0 pt-4 pb-3">
                        <h5 class="card-title mb-0">العهد المسندة إلي</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>اسم العهدة</th>
                                        <th>الوصف</th>
                                        <th>تاريخ التسليم</th>
                                        <th>الحالة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($assigned_assets as $asset): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($asset['name']); ?></td>
                                            <td><?php echo htmlspecialchars($asset['description']); ?></td>
                                            <td><?php echo date('Y/m/d', strtotime($asset['assigned_date'])); ?></td>
                                            <td><span class="badge bg-warning">مسلمة</span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- الملاحظات -->
    <?php if (count($notes) > 0): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-white border-0 pt-4 pb-3">
                        <h5 class="card-title mb-0">الملاحظات</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($notes as $note): ?>
                            <div class="card note-<?php echo $note['type']; ?> mb-3">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h6 class="card-title mb-0"><?php echo $note['author_first'] . ' ' . $note['author_last']; ?></h6>
                                        <small class="text-muted"><?php echo date('Y/m/d H:i', strtotime($note['created_at'])); ?></small>
                                    </div>
                                    <p class="card-text mb-0"><?php echo htmlspecialchars($note['content']); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- نافذة تعديل المعلومات الشخصية -->
<div class="modal fade" id="editProfileModal" tabindex="-1" aria-labelledby="editProfileModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editProfileModalLabel">تعديل المعلومات الشخصية</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="profile.php">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="email" class="form-label">البريد الإلكتروني</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo $currentUser['email']; ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="phone" class="form-label">رقم الهاتف</label>
                        <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo $currentUser['phone']; ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="address" class="form-label">العنوان</label>
                        <textarea class="form-control" id="address" name="address" rows="3" required><?php echo $currentUser['address']; ?></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" name="update_profile" class="btn btn-primary">حفظ التغييرات</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- نافذة تغيير كلمة المرور -->
<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="changePasswordModalLabel">تغيير كلمة المرور</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="profile.php">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="current_password" class="form-label">كلمة المرور الحالية</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                    </div>

                    <div class="mb-3">
                        <label for="new_password" class="form-label">كلمة المرور الجديدة</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                        <div class="form-text">يجب أن تكون 6 أحرف على الأقل</div>
                    </div>

                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">تأكيد كلمة المرور الجديدة</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" name="change_password" class="btn btn-primary">تغيير كلمة المرور</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- نافذة تحديث الصورة الشخصية -->
<div class="modal fade" id="updatePictureModal" tabindex="-1" aria-labelledby="updatePictureModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="updatePictureModalLabel">تغيير الصورة الشخصية</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="profile.php" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="profile_picture" class="form-label">اختر صورة</label>
                        <input type="file" class="form-control" id="profile_picture" name="profile_picture" accept="image/*" required>
                        <div class="form-text">الصيغ المسموحة: JPEG, PNG, GIF</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" name="update_picture" class="btn btn-primary">رفع الصورة</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>