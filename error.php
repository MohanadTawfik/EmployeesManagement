<?php
require_once 'config.php';

$error_code = isset($_GET['code']) ? (int)$_GET['code'] : 404;
$error_messages = [
    403 => 'ممنوع الوصول - ليس لديك صلاحية لعرض هذه الصفحة',
    404 => 'الصفحة غير موجودة - الصفحة التي تبحث عنها غير متوفرة',
    500 => 'خطأ في الخادم - حدثت مشكلة في الخادم أثناء معالجة طلبك'
];

$error_title = [
    403 => 'ممنوع الوصول',
    404 => 'صفحة غير موجودة',
    500 => 'خطأ في الخادم'
];

$message = isset($error_messages[$error_code]) ? $error_messages[$error_code] : 'حدث خطأ غير متوقع';
$title = isset($error_title[$error_code]) ? $error_title[$error_code] : 'خطأ';
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?> - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .error-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4edf5 100%);
        }
        .error-content {
            text-align: center;
            padding: 2rem;
            max-width: 600px;
        }
        .error-code {
            font-size: 8rem;
            font-weight: bold;
            color: #3498db;
            line-height: 1;
            margin-bottom: 1rem;
        }
        .error-icon {
            font-size: 4rem;
            color: #e74c3c;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-content">
            <div class="error-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="error-code"><?php echo $error_code; ?></div>
            <h1 class="mb-4"><?php echo $title; ?></h1>
            <p class="lead mb-5"><?php echo $message; ?></p>
            <div class="d-flex justify-content-center gap-3">
                <a href="javascript:history.back()" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-right me-2"></i>العودة للخلف
                </a>
                <a href="index.php" class="btn btn-primary">
                    <i class="fas fa-home me-2"></i>الصفحة الرئيسية
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
