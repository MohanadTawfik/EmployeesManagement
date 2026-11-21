<?php
require_once 'config.php';

// معالجة إضافة عهدة جديدة
if (isset($_POST['add_asset'])) {
    try {
        $stmt = $pdo->prepare("INSERT INTO assets (name, description, serial_number, purchase_date, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['name'],
            $_POST['description'] ?? '',
            $_POST['serial_number'] ?? '',
            $_POST['purchase_date'],
            $_POST['status']
        ]);

        // إعادة تحميل الصفحة لعرض البيانات المحدثة
        header("Location: assets.php?add_success=1");
        exit;
    } catch (PDOException $e) {
        $error_message = "خطأ في إضافة العهدة: " . $e->getMessage();
    }
}

// معالجة تعديل بيانات عهدة
if (isset($_POST['edit_asset'])) {
    try {
        $stmt = $pdo->prepare("UPDATE assets SET name = ?, description = ?, serial_number = ?, purchase_date = ?, status = ? WHERE id = ?");
        $stmt->execute([
            $_POST['name'],
            $_POST['description'] ?? '',
            $_POST['serial_number'] ?? '',
            $_POST['purchase_date'],
            $_POST['status'],
            $_POST['asset_id']
        ]);

        // إعادة تحميل الصفحة لعرض البيانات المحدثة
        header("Location: assets.php?edit_success=1");
        exit;
    } catch (PDOException $e) {
        $error_message = "خطأ في تحديث بيانات العهدة: " . $e->getMessage();
    }
}

// معالجة حذف عهدة
if (isset($_GET['delete'])) {
    try {
        // التحقق من عدم وجود تخصيصات نشطة للعهدة
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM asset_assignments WHERE asset_id = ? AND status = 'assigned'");
        $stmt->execute([$_GET['delete']]);
        $active_assignments = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        if ($active_assignments > 0) {
            $error_message = "لا يمكن حذف العهدة لأنها مسلمة حالياً لموظف";
        } else {
            $stmt = $pdo->prepare("DELETE FROM asset_assignments WHERE asset_id = ?");
            $stmt->execute([$_GET['delete']]);

            $stmt = $pdo->prepare("DELETE FROM assets WHERE id = ?");
            $stmt->execute([$_GET['delete']]);

            // إعادة تحميل الصفحة لعرض البيانات المحدثة
            header("Location: assets.php?delete_success=1");
            exit;
        }
    } catch (PDOException $e) {
        $error_message = "خطأ في حذف العهدة: " . $e->getMessage();
    }
}

// معالجة تخصيص عهدة لموظف
if (isset($_POST['assign_asset'])) {
    try {
        $stmt = $pdo->prepare("INSERT INTO asset_assignments (asset_id, employee_id, assigned_date, status, notes) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['asset_id'],
            $_POST['employee_id'],
            $_POST['assigned_date'],
            'assigned',
            $_POST['notes']
        ]);

        // تحديث حالة العهدة إلى "مستخدمة"
        $stmt = $pdo->prepare("UPDATE assets SET status = 'in_use' WHERE id = ?");
        $stmt->execute([$_POST['asset_id']]);

        $success_message = "تم تسليم العهدة للموظف بنجاح";
    } catch (PDOException $e) {
        $error_message = "خطأ في تسليم العهدة: " . $e->getMessage();
    }
}

// معالجة استرجاع عهدة من موظف
if (isset($_POST['return_asset'])) {
    try {
        $assignment_id = $_POST['assignment_id'];
        $asset_id = $_POST['asset_id'];

        // تحديث تخصيص العهدة
        $stmt = $pdo->prepare("UPDATE asset_assignments SET return_date = ?, status = 'returned', notes = CONCAT(IFNULL(notes, ''), '\n', ?) WHERE id = ?");
        $stmt->execute([
            $_POST['return_date'],
            $_POST['notes'],
            $assignment_id
        ]);

        // التحقق من عدم وجود تخصيصات أخرى نشطة للعهدة
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM asset_assignments WHERE asset_id = ? AND status = 'assigned'");
        $stmt->execute([$asset_id]);
        $active_assignments = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        // تحديث حالة العهدة إلى "متاحة" إذا لم يكن هناك تخصيصات أخرى نشطة
        if ($active_assignments == 0) {
            $stmt = $pdo->prepare("UPDATE assets SET status = 'available' WHERE id = ?");
            $stmt->execute([$asset_id]);
        }

        $success_message = "تم استرجاع العهدة بنجاح";
    } catch (PDOException $e) {
        $error_message = "خطأ في استرجاع العهدة: " . $e->getMessage();
    }
}

// الحصول على قائمة العهد
$assets = [];
try {

    $stmt = $pdo->query("SELECT a.*, 
                        (SELECT COUNT(*) FROM asset_assignments aa WHERE aa.asset_id = a.id AND aa.status = 'assigned') as assigned_count 
                        FROM assets a ORDER BY a.name");

    $assets = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "خطأ في جلب بيانات العهد: " . $e->getMessage();
}

// الحصول على قائمة الموظفين
$employees = [];
try {
    $stmt = $pdo->query("SELECT id, first_name, last_name FROM employees WHERE status = 'active' ORDER BY first_name");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "خطأ في جلب بيانات الموظفين: " . $e->getMessage();
}

// الحصول على بيانات عهدة محددة للتعديل
$edit_asset = null;
if (isset($_GET['edit'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM assets WHERE id = ?");
        $stmt->execute([$_GET['edit']]);
        $edit_asset = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error_message = "خطأ في جلب بيانات العهدة: " . $e->getMessage();
    }
}

// الحصول على بيانات تخصيص عهدة محددة
$assignment = null;
if (isset($_GET['assignment'])) {
    try {
        $stmt = $pdo->prepare("SELECT aa.*, a.name as asset_name, e.first_name, e.last_name FROM asset_assignments aa JOIN assets a ON aa.asset_id = a.id JOIN employees e ON aa.employee_id = e.id WHERE aa.id = ?");
        $stmt->execute([$_GET['assignment']]);
        $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error_message = "خطأ في جلب بيانات تخصيص العهدة: " . $e->getMessage();
    }
}
?>

<?php
$page_title = "إدارة العهد";
include 'includes/header.php';
?>

<!-- المحتوى الرئيسي -->
<div class="container-fluid mt-4">
    <!-- رسائل النجاح والخطأ -->
    <?php
    // عرض رسائل النجاح
    if (isset($_GET['add_success'])) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
              <i class="fas fa-check-circle me-2"></i>تم إضافة العهدة بنجاح
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
    } elseif (isset($_GET['edit_success'])) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
              <i class="fas fa-check-circle me-2"></i>تم تحديث بيانات العهدة بنجاح
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
    } elseif (isset($_GET['delete_success'])) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
              <i class="fas fa-check-circle me-2"></i>تم حذف العهدة بنجاح
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
        <?php if (isset($permissions['all']) && $permissions['all']): ?>
            <h1 class="h3">إدارة العهد</h1>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAssetModal">
                <i class="fas fa-plus"></i> إضافة عهدة جديدة
            </button>
        <?php elseif (isset($permissions['assets_own']) && $permissions['assets_own']): ?>
            <h1 class="h3">العهد المسندة إلي</h1>
        <?php else: ?>
            <h1 class="h3">إدارة العهد</h1>
        <?php endif; ?>
    </div>

    <!-- جدول العهد -->
    <div class="card shadow-sm mb-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>اسم العهدة</th>
                            <th>الوصف</th>
                            <th>الرقم التسلسلي</th>
                            <th>تاريخ الشراء</th>
                            <th>الحالة</th>
                            <th>عدد التخصيصات</th>

                            <th>الإجراءات</th>

                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($assets) > 0): ?>
                            <?php $counter = 1; ?>
                            <?php foreach ($assets as $asset): ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td><?php echo $asset['name']; ?></td>
                                    <td><?php echo $asset['description']; ?></td>
                                    <td><?php echo $asset['serial_number']; ?></td>
                                    <td><?php echo date('Y-m-d', strtotime($asset['purchase_date'])); ?></td>
                                    <td>
                                        <?php
                                        $status_class = '';
                                        $status_text = '';

                                        switch ($asset['status']) {
                                            case 'available':
                                                $status_class = 'badge bg-success';
                                                $status_text = 'متاحة';
                                                break;
                                            case 'in_use':
                                                $status_class = 'badge bg-warning';
                                                $status_text = 'مستخدمة';
                                                break;
                                            case 'maintenance':
                                                $status_class = 'badge bg-info';
                                                $status_text = 'صيانة';
                                                break;
                                            case 'obsolete':
                                                $status_class = 'badge bg-danger';
                                                $status_text = 'مهملة';
                                                break;
                                        }
                                        ?>
                                        <span class="<?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                    </td>
                                    <td><?php echo $asset['assigned_count']; ?></td>
                                    <td>
                                        <?php if (isset($permissions['all']) && $permissions['all']): ?>
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#assignAssetModal" data-asset-id="<?php echo $asset['id']; ?>" data-asset-name="<?php echo $asset['name']; ?>" <?php echo ($asset['status'] == 'available') ? '' : 'disabled'; ?>>
                                                    <i class="fas fa-hand-holding"></i> تسليم
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editAssetModal" data-asset-id="<?php echo $asset['id']; ?>">
                                                    <i class="fas fa-edit"></i> تعديل
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmDelete(<?php echo $asset['id']; ?>, '<?php echo $asset['name']; ?>')" <?php echo ($asset['assigned_count'] > 0) ? 'disabled' : ''; ?>>
                                                    <i class="fas fa-trash"></i> حذف
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#viewAssignmentsModal" data-asset-id="<?php echo $asset['id']; ?>" data-asset-name="<?php echo $asset['name']; ?>">
                                                    <i class="fas fa-list"></i> التخصيصات
                                                </button>
                                            </div>
                                        <?php elseif (isset($permissions['assets_own']) && $permissions['assets_own']): ?>
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#viewAssignmentsModal" data-asset-id="<?php echo $asset['id']; ?>" data-asset-name="<?php echo $asset['name']; ?>">
                                                    <i class="fas fa-list"></i> تفاصيل العهدة
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="12" class="text-center py-4">لا توجد عهد مسجلة</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- نافذة إضافة عهدة جديدة -->
<div class="modal fade" id="addAssetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">إضافة عهدة جديدة</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="assets.php">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">اسم العهدة</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>

                            <div class="mb-3">
                                <label for="serial_number" class="form-label">الرقم التسلسلي</label>
                                <input type="text" class="form-control" id="serial_number" name="serial_number">
                            </div>

                            <div class="mb-3">
                                <label for="purchase_date" class="form-label">تاريخ الشراء</label>
                                <input type="date" class="form-control" id="purchase_date" name="purchase_date" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="description" class="form-label">الوصف</label>
                                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="status" class="form-label">الحالة</label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="available" selected>متاحة</option>
                                    <option value="maintenance">صيانة</option>
                                    <option value="obsolete">مهملة</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" name="add_asset" class="btn btn-primary">إضافة</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- نافذة تعديل عهدة -->
<div class="modal fade" id="editAssetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">تعديل بيانات العهدة</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="assets.php">
                <div class="modal-body">
                    <input type="hidden" id="edit_asset_id" name="asset_id">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_name" class="form-label">اسم العهدة</label>
                                <input type="text" class="form-control" id="edit_name" name="name" required>
                            </div>

                            <div class="mb-3">
                                <label for="edit_serial_number" class="form-label">الرقم التسلسلي</label>
                                <input type="text" class="form-control" id="edit_serial_number" name="serial_number">
                            </div>

                            <div class="mb-3">
                                <label for="edit_purchase_date" class="form-label">تاريخ الشراء</label>
                                <input type="date" class="form-control" id="edit_purchase_date" name="purchase_date" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_description" class="form-label">الوصف</label>
                                <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="edit_status" class="form-label">الحالة</label>
                                <select class="form-select" id="edit_status" name="status" required>
                                    <option value="available">متاحة</option>
                                    <option value="in_use">مستخدمة</option>
                                    <option value="maintenance">صيانة</option>
                                    <option value="obsolete">مهملة</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" name="edit_asset" class="btn btn-primary">حفظ التعديلات</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- نافذة تسليم عهدة لموظف -->
<div class="modal fade" id="assignAssetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">تسليم عهدة لموظف</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="assets.php">
                <div class="modal-body">
                    <div class="alert alert-info">
                        سيتم تسليم العهدة: <strong id="asset_name_display"></strong>
                    </div>

                    <input type="hidden" id="assign_asset_id" name="asset_id" value="">
                    <input type="hidden" id="assignment_id" name="assignment_id" value="">

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
                        <label for="assigned_date" class="form-label">تاريخ التسليم</label>
                        <input type="date" class="form-control" id="assigned_date" name="assigned_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="notes" class="form-label">ملاحظات</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" name="assign_asset" class="btn btn-primary">تسليم</button>

                </div>
            </form>
        </div>
    </div>
</div>

<!-- نافذة عرض تخصيصات العهدة -->
<div class="modal fade" id="viewAssignmentsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">تخصيصات العهدة: <span id="assignments_asset_name"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>الموظف</th>
                                <th>تاريخ التسليم</th>
                                <th>تاريخ الاسترجاع</th>
                                <th>الحالة</th>
                                <th>ملاحظات</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody id="assignments_table_body">
                            <!-- سيتم تحميل البيانات عبر JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
            </div>
        </div>
    </div>
</div>

<!-- نافذة استرجاع عهدة -->
<div class="modal fade" id="returnAssetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">استرجاع عهدة</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="assets.php">
                <div class="modal-body">
                    <input type="hidden" id="return_assignment_id" name="assignment_id">
                    <input type="hidden" id="return_asset_id" name="asset_id">

                    <div class="mb-3">
                        <label for="return_date" class="form-label">تاريخ الاسترجاع</label>
                        <input type="date" class="form-control" id="return_date" name="return_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="return_notes" class="form-label">ملاحظات</label>
                        <textarea class="form-control" id="return_notes" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" name="return_asset" class="btn btn-primary">استرجاع</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- نافذة تأكيد الحذف -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">تأكيد الحذف</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                هل أنت متأكد من حذف العهدة: <strong id="delete_asset_name"></strong>؟
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <a href="#" id="confirm_delete_link" class="btn btn-danger">حذف</a>
            </div>
        </div>
    </div>
</div>

<script>
    // دالة تأكيد الحذف
    function confirmDelete(id, name) {
        document.getElementById('delete_asset_name').textContent = name;
        document.getElementById('confirm_delete_link').href = 'assets.php?delete=' + id;

        var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
        deleteModal.show();
    }

    // تحميل بيانات العهدة عند فتح نافذة التعديل
    document.getElementById('editAssetModal').addEventListener('show.bs.modal', function(event) {
        var button = event.relatedTarget;
        var assetId = button.getAttribute('data-asset-id');

        // جلب بيانات العهدة عبر AJAX
        fetch('get_asset.php?id=' + assetId)
            .then(response => response.json())
            .then(data => {
                document.getElementById('edit_asset_id').value = data.id;
                document.getElementById('edit_name').value = data.name;
                document.getElementById('edit_description').value = data.description;
                document.getElementById('edit_category').value = data.category || '';
                document.getElementById('edit_brand').value = data.brand || '';
                document.getElementById('edit_model').value = data.model || '';
                document.getElementById('edit_serial_number').value = data.serial_number || '';
                document.getElementById('edit_purchase_date').value = data.purchase_date;
                document.getElementById('edit_value').value = data.value || '';
                document.getElementById('edit_warranty_expiry').value = data.warranty_expiry || '';
                document.getElementById('edit_location').value = data.location || '';
                document.getElementById('edit_status').value = data.status;
            })
            .catch(error => console.error('Error:', error));
    });

    // تحميل بيانات العهدة عند فتح نافذة التسليم
    document.getElementById('assignAssetModal').addEventListener('show.bs.modal', function(event) {
        var button = event.relatedTarget;
        var assetId = button.getAttribute('data-asset-id');
        var assetName = button.getAttribute('data-asset-name');

        document.getElementById('assign_asset_id').value = assetId;
        document.getElementById('asset_name_display').textContent = assetName;
    });

    // تحميل تخصيصات العهدة عند فتح نافذة عرض التخصيصات
    document.getElementById('viewAssignmentsModal').addEventListener('show.bs.modal', function(event) {
        var button = event.relatedTarget;
        var assetId = button.getAttribute('data-asset-id');
        var assetName = button.getAttribute('data-asset-name');

        document.getElementById('assignments_asset_name').textContent = assetName;

        // جلب تخصيصات العهدة عبر AJAX
        fetch('get_asset_assignments.php?asset_id=' + assetId)
            .then(response => response.json())
            .then(data => {
                var tableBody = document.getElementById('assignments_table_body');
                tableBody.innerHTML = '';

                if (data.length > 0) {
                    data.forEach(function(assignment) {
                        var statusClass = '';
                        var statusText = '';

                        switch (assignment.status) {
                            case 'assigned':
                                statusClass = 'badge bg-warning';
                                statusText = 'مسلمة';
                                break;
                            case 'returned':
                                statusClass = 'badge bg-success';
                                statusText = 'مسترجعة';
                                break;
                        }

                        var row = document.createElement('tr');
                        row.innerHTML = `
                                <td>${assignment.employee_name}</td>
                                <td>${assignment.assigned_date}</td>
                                <td>${assignment.return_date || '-'}</td>
                                <td><span class="${statusClass}">${statusText}</span></td>
                                <td>${assignment.notes || '-'}</td>
                                <td>
                                    ${assignment.status === 'assigned' ? `
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#returnAssetModal" 
                                                data-assignment-id="${assignment.id}" data-asset-id="${assignment.asset_id}">
                                            <i class="fas fa-undo"></i> استرجاع
                                        </button>
                                                    
                                    ` : '-'}
                                </td>
                            `;

                        tableBody.appendChild(row);
                    });
                } else {
                    var row = document.createElement('tr');
                    row.innerHTML = '<td colspan="6" class="text-center">لا توجد تخصيصات لهذه العهدة</td>';
                    tableBody.appendChild(row);
                }
            })
            .catch(error => console.error('Error:', error));
    });

    // تحميل بيانات التخصيص عند فتح نافذة الاسترجاع
    document.getElementById('returnAssetModal').addEventListener('show.bs.modal', function(event) {
        var button = event.relatedTarget;
        var assignmentId = button.getAttribute('data-assignment-id');
        var assetId = button.getAttribute('data-asset-id');

        document.getElementById('return_assignment_id').value = assignmentId;
        document.getElementById('return_asset_id').value = assetId;
    });
</script>
<?php include 'includes/footer.php'; ?>