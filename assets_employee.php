<?php
require_once 'config.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// التحقق من أن المستخدم موظف وليس مدير
if (isset($permissions['all']) && $permissions['all']) {
    header('Location: assets.php');
    exit;
}

// الحصول على العهد المسندة للموظف
$assets = [];
try {
    $stmt = $pdo->prepare("SELECT a.*, aa.assigned_date, aa.notes as assignment_notes
                        FROM assets a
                            JOIN asset_assignments aa ON a.id = aa.asset_id
                            WHERE aa.employee_id = ? AND aa.status = 'assigned'
                            ORDER BY a.name");
    $stmt->execute([$_SESSION['employee_id']]);
    $assets = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "خطأ في جلب بيانات العهد: " . $e->getMessage();
}

$page_title = "العهد المسندة إلي";
include 'includes/header.php';
?>

<!-- المحتوى الرئيسي -->
<div class="container-fluid mt-4">
    <!-- رسائل النجاح والخطأ -->
    <?php
    // عرض رسائل النجاح
    if (isset($_GET['return_success'])) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
              <i class="fas fa-check-circle me-2"></i>تم استلام العهدة بنجاح
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

    <!-- عنوان الصفحة -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">العهد المسندة إلي</h1>
    </div>

    <!-- بطاقات العهد -->
    <div class="row">
        <?php if (count($assets) > 0): ?>
            <?php foreach ($assets as $asset): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100 shadow-sm asset-card">
                        <div class="card-header bg-gradient-primary text-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><?php echo $asset['name']; ?></h5>
                            <span class="badge bg-light text-primary">مسلمة لك</span>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-12">
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="fas fa-barcode text-muted me-2"></i>
                                        <div>
                                            <small class="text-muted d-block">الرقم التسلسلي</small>
                                            <span class="fw-bold"><?php echo $asset['serial_number'] ?: 'غير محدد'; ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-12">
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="fas fa-calendar-alt text-muted me-2"></i>
                                        <div>
                                            <small class="text-muted d-block">تاريخ التسليم</small>
                                            <span class="fw-bold"><?php echo date('Y-m-d', strtotime($asset['assigned_date'])); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-12">
                                    <div class="d-flex align-items-start">
                                        <i class="fas fa-info-circle text-muted me-2 mt-1"></i>
                                        <div>
                                            <small class="text-muted d-block">الوصف</small>
                                            <span><?php echo $asset['description'] ?: 'لا يوجد وصف'; ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <?php if (!empty($asset['assignment_notes'])): ?>
                            <div class="row mb-3">
                                <div class="col-12">
                                    <div class="d-flex align-items-start">
                                        <i class="fas fa-sticky-note text-muted me-2 mt-1"></i>
                                        <div>
                                            <small class="text-muted d-block">ملاحظات التسليم</small>
                                            <span><?php echo $asset['assignment_notes']; ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer bg-light d-flex justify-content-between align-items-center">
                            <div>
                                <?php
                                $status_class = '';
                                $status_text = '';
                                $status_icon = '';

                                switch ($asset['status']) {
                                    case 'available':
                                        $status_class = 'badge bg-success';
                                        $status_text = 'متاحة';
                                        $status_icon = 'fa-check-circle';
                                        break;
                                    case 'in_use':
                                        $status_class = 'badge bg-warning';
                                        $status_text = 'مستخدمة';
                                        $status_icon = 'fa-clock';
                                        break;
                                    case 'maintenance':
                                        $status_class = 'badge bg-info';
                                        $status_text = 'صيانة';
                                        $status_icon = 'fa-tools';
                                        break;
                                    case 'obsolete':
                                        $status_class = 'badge bg-danger';
                                        $status_text = 'مهملة';
                                        $status_icon = 'fa-times-circle';
                                        break;
                                }
                                ?>
                                <span class="<?php echo $status_class; ?>"><i class="fas <?php echo $status_icon; ?> ms-1"></i> <?php echo $status_text; ?></span>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#viewAssetModal" data-asset-id="<?php echo $asset['id']; ?>" data-asset-name="<?php echo $asset['name']; ?>">
                                <i class="fas fa-list"></i> تفاصيل
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12 text-center py-5">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle fa-3x mb-3"></i>
                    <h5>لا توجد عهد مسندة إليك</h5>
                    <p class="mb-0">لم يتم تسليم أي عهد لك حالياً</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- نافذة عرض تفاصيل العهدة -->
<div class="modal fade" id="viewAssetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">تفاصيل العهدة</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="asset_details">
                    <!-- سيتم تحميل البيانات عبر JavaScript -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
            </div>
        </div>
    </div>
</div>

<style>
.asset-card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    border: none;
    overflow: hidden;
}

.asset-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}

.bg-gradient-primary {
    background: linear-gradient(45deg, #007bff, #0056b3);
}

.card-header {
    border-bottom: none;
}

.card-footer {
    border-top: 1px solid rgba(0,0,0,.05);
}
</style>

<script>
    // تحميل بيانات العهدة عند فتح النافذة
    document.getElementById('viewAssetModal').addEventListener('show.bs.modal', function(event) {
        var button = event.relatedTarget;
        var assetId = button.getAttribute('data-asset-id');
        var assetName = button.getAttribute('data-asset-name');

        // تحديث عنوان النافذة
        document.querySelector('#viewAssetModal .modal-title').textContent = 'تفاصيل العهدة: ' + assetName;

        // جلب بيانات العهدة عبر AJAX
        fetch('get_asset_assignment.php?id=' + assetId)
            .then(response => response.json())
            .then(data => {
                var detailsHtml = `
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <h6 class="text-muted">معلومات أساسية</h6>
                            <table class="table table-sm">
                                <tr>
                                    <td class="text-muted">اسم العهدة:</td>
                                    <td class="fw-bold">${data.name}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">الرقم التسلسلي:</td>
                                    <td>${data.serial_number || 'غير محدد'}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">تاريخ الشراء:</td>
                                    <td>${data.purchase_date}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">الحالة:</td>
                                    <td>
                                        ${getStatusBadge(data.status)}
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6 mb-3">
                            <h6 class="text-muted">معلومات التسليم</h6>
                            <table class="table table-sm">
                                <tr>
                                    <td class="text-muted">تاريخ التسليم:</td>
                                    <td class="fw-bold">${data.assigned_date || "غير محدد"}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">ملاحظات التسليم:</td>
                                    <td>${data.assignment_notes || 'لا توجد ملاحظات'}</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-12">
                            <h6 class="text-muted">الوصف</h6>
                            <p>${data.description || 'لا يوجد وصف'}</p>
                        </div>
                    </div>
                `;

                document.getElementById('asset_details').innerHTML = detailsHtml;
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('asset_details').innerHTML = '<div class="alert alert-danger">حدث خطأ أثناء تحميل بيانات العهدة</div>';
            });
    });

    // دالة للحصول على شارة الحالة
    function getStatusBadge(status) {
        switch(status) {
            case 'available':
                return '<span class="badge bg-success"><i class="fas fa-check-circle ms-1"></i> متاحة</span>';
            case 'in_use':
                return '<span class="badge bg-warning"><i class="fas fa-clock ms-1"></i> مستخدمة</span>';
            case 'maintenance':
                return '<span class="badge bg-info"><i class="fas fa-tools ms-1"></i> صيانة</span>';
            case 'obsolete':
                return '<span class="badge bg-danger"><i class="fas fa-times-circle ms-1"></i> مهملة</span>';
            default:
                return '<span class="badge bg-secondary">' + status + '</span>';
        }
    }
</script>

<?php include 'includes/footer.php'; ?>
