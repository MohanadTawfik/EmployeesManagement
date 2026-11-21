<?php
require_once 'config.php';

$page_title = "تسجيل الدخول";
$custom_layout = true; // استخدام تخطيط مخصص لصفحة تسجيل الدخول
include 'includes/header.php';
?>

<!-- <body class="bg-light"> -->
<div class="container-fluid vh-100 d-flex align-items-center justify-content-center">
    <div class="card shadow-lg p-4" style="width: 100%; max-width: 450px;">
        <div class="text-center mb-4">
            <h1 class="h3 fw-bold text-primary"><?php echo APP_NAME; ?></h1>
            <p class="text-muted">تسجيل الدخول إلى النظام</p>
        </div>

        <?php
        if (isset($_POST['login'])) {

            $username = $_POST['username'];
            $password = $_POST['password'];

            try {
                $stmt = $pdo->prepare("SELECT u.*, e.first_name, e.last_name FROM users u JOIN employees e ON u.employee_id = e.id WHERE u.username = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role_id'] = $user['role_id'];
                    $_SESSION['employee_id'] = $user['employee_id'];
                    $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];

                    header("Location: index.php");
                    exit;
                } else {
                    echo '<div class="alert alert-danger">اسم المستخدم أو كلمة المرور غير صحيحة</div>';
                }
            } catch (PDOException $e) {
                echo '<div class="alert alert-danger">خطأ في النظام: ' . $e->getMessage() . '</div>';
            }
        }
        ?>

        <form method="post" action="login.php">
            <div class="mb-3">
                <label for="username" class="form-label">اسم المستخدم</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                    <input type="text" class="form-control" id="username" name="username" required>
                </div>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">كلمة المرور</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
            </div>

            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="remember">
                <label class="form-check-label" for="remember">تذكرني</label>
            </div>

            <div class="d-grid gap-2 mb-3">
                <button type="submit" name="login" class="btn btn-primary">تسجيل الدخول</button>
            </div>
        </form>

        <div class="text-center text-muted small">
            <!-- <p>المستخدم الافتراضي: admin / كلمة المرور: password</p> -->
            <p>&copy; <?php echo date('Y'); ?> - <?php echo APP_NAME; ?></p>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<!-- </html> -->