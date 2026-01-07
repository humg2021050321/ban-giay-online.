<?php
require_once '../config.php';
requireAdmin();

// Xử lý thêm/sửa mã giảm giá
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add']) || isset($_POST['edit'])) {
        $id = isset($_POST['edit']) ? (int)$_POST['id'] : 0;
        $code = strtoupper(sanitize($_POST['code']));
        $description = sanitize($_POST['description']);
        $discount_type = $_POST['discount_type'];
        $discount_value = (float)$_POST['discount_value'];
        $min_order_value = (float)($_POST['min_order_value'] ?? 0);
        $max_discount = !empty($_POST['max_discount']) ? (float)$_POST['max_discount'] : null;
        $usage_limit = !empty($_POST['usage_limit']) ? (int)$_POST['usage_limit'] : null;
        $start_date = $_POST['start_date'] ? $_POST['start_date'] : null;
        $end_date = $_POST['end_date'] ? $_POST['end_date'] : null;
        $status = $_POST['status'];
        
        try {
            if ($id > 0) {
                // Cập nhật
                $stmt = $conn->prepare("
                    UPDATE coupons SET 
                        code = ?, description = ?, discount_type = ?, discount_value = ?,
                        min_order_value = ?, max_discount = ?, usage_limit = ?,
                        start_date = ?, end_date = ?, status = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $code, $description, $discount_type, $discount_value,
                    $min_order_value, $max_discount, $usage_limit,
                    $start_date, $end_date, $status, $id
                ]);
                flashMessage('success', 'Cập nhật mã giảm giá thành công');
            } else {
                // Kiểm tra mã đã tồn tại
                $check = $conn->prepare("SELECT id FROM coupons WHERE code = ?");
                $check->execute([$code]);
                if ($check->fetch()) {
                    flashMessage('error', 'Mã giảm giá đã tồn tại');
                    redirect(ADMIN_URL . 'coupons.php');
                }
                
                // Thêm mới
                $stmt = $conn->prepare("
                    INSERT INTO coupons (
                        code, description, discount_type, discount_value,
                        min_order_value, max_discount, usage_limit,
                        start_date, end_date, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $code, $description, $discount_type, $discount_value,
                    $min_order_value, $max_discount, $usage_limit,
                    $start_date, $end_date, $status
                ]);
                flashMessage('success', 'Thêm mã giảm giá thành công');
            }
        } catch (Exception $e) {
            flashMessage('error', 'Lỗi: ' . $e->getMessage());
        }
        
        redirect(ADMIN_URL . 'coupons.php');
    }
}

// Xử lý xóa
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    try {
        $conn->prepare("DELETE FROM coupons WHERE id = ?")->execute([$id]);
        flashMessage('success', 'Xóa mã giảm giá thành công');
    } catch (Exception $e) {
        flashMessage('error', 'Lỗi: ' . $e->getMessage());
    }
    
    redirect(ADMIN_URL . 'coupons.php');
}

// Lấy danh sách mã giảm giá
$coupons = $conn->query("SELECT * FROM coupons ORDER BY created_at DESC")->fetchAll();

$pageTitle = 'Quản lý mã giảm giá';
include 'includes/admin_header.php';
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Quản lý mã giảm giá</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#couponModal">
            <i class="bi bi-plus-circle"></i> Tạo mã giảm giá
        </button>
    </div>

    <!-- Coupons Table -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 150px;">Mã</th>
                            <th>Mô tả</th>
                            <th style="width: 150px;">Giảm giá</th>
                            <th style="width: 150px;">Điều kiện</th>
                            <th style="width: 120px;">Sử dụng</th>
                            <th style="width: 150px;">Thời gian</th>
                            <th style="width: 120px;">Trạng thái</th>
                            <th style="width: 150px;">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($coupons)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                <p>Chưa có mã giảm giá nào</p>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#couponModal">
                                    Tạo mã giảm giá đầu tiên
                                </button>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($coupons as $coupon): ?>
                            <tr>
                                <td>
                                    <code class="fs-6 fw-bold"><?= htmlspecialchars($coupon['code']) ?></code>
                                </td>
                                <td>
                                    <?= htmlspecialchars($coupon['description']) ?>
                                </td>
                                <td>
                                    <?php if ($coupon['discount_type'] === 'percent'): ?>
                                        <strong class="text-danger fs-5"><?= $coupon['discount_value'] ?>%</strong>
                                    <?php else: ?>
                                        <strong class="text-danger fs-5"><?= formatPrice($coupon['discount_value']) ?></strong>
                                    <?php endif; ?>
                                    <?php if ($coupon['max_discount']): ?>
                                        <div class="small text-muted">Tối đa: <?= formatPrice($coupon['max_discount']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($coupon['min_order_value'] > 0): ?>
                                        <small class="d-block">Đơn tối thiểu:</small>
                                        <strong><?= formatPrice($coupon['min_order_value']) ?></strong>
                                    <?php else: ?>
                                        <small class="text-muted">Không giới hạn</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="mb-1">
                                        <strong><?= $coupon['used_count'] ?></strong> / 
                                        <span class="text-muted"><?= $coupon['usage_limit'] ? $coupon['usage_limit'] : '∞' ?></span>
                                    </div>
                                    <?php 
                                    $percent = $coupon['usage_limit'] ? ($coupon['used_count'] / $coupon['usage_limit'] * 100) : 0;
                                    $percent = min($percent, 100);
                                    ?>
                                    <div class="progress" style="height: 6px;">
                                        <div class="progress-bar <?= $percent >= 100 ? 'bg-danger' : 'bg-primary' ?>" 
                                             style="width: <?= $percent ?>%"></div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($coupon['start_date']): ?>
                                        <small class="d-block text-muted">
                                            <i class="bi bi-calendar-check"></i>
                                            <?= date('d/m/Y', strtotime($coupon['start_date'])) ?>
                                        </small>
                                    <?php endif; ?>
                                    <?php if ($coupon['end_date']): ?>
                                        <small class="d-block text-muted">
                                            <i class="bi bi-calendar-x"></i>
                                            <?= date('d/m/Y', strtotime($coupon['end_date'])) ?>
                                        </small>
                                    <?php endif; ?>
                                    <?php if (!$coupon['start_date'] && !$coupon['end_date']): ?>
                                        <small class="text-muted">Không giới hạn</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $now = date('Y-m-d H:i:s');
                                    $is_expired = $coupon['end_date'] && $coupon['end_date'] < $now;
                                    $is_not_started = $coupon['start_date'] && $coupon['start_date'] > $now;
                                    $is_full = $coupon['usage_limit'] && $coupon['used_count'] >= $coupon['usage_limit'];
                                    
                                    if ($is_expired):
                                    ?>
                                        <span class="badge bg-secondary">Hết hạn</span>
                                    <?php elseif ($is_full): ?>
                                        <span class="badge bg-danger">Hết lượt</span>
                                    <?php elseif ($is_not_started): ?>
                                        <span class="badge bg-info">Chưa bắt đầu</span>
                                    <?php elseif ($coupon['status'] === 'active'): ?>
                                        <span class="badge bg-success">Hoạt động</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Tắt</span>
                                    <?php endif; ?>
                                </td>
                                <td class="table-actions">
                                    <button class="btn btn-sm btn-outline-primary" 
                                            onclick='editCoupon(<?= json_encode($coupon) ?>)' 
                                            title="Sửa">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <a href="?delete=<?= $coupon['id'] ?>" 
                                       class="btn btn-sm btn-outline-danger"
                                       title="Xóa"
                                       data-confirm="Xóa mã giảm giá này?">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Thêm/Sửa -->
<div class="modal fade" id="couponModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="couponForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Tạo mã giảm giá</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="couponId">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Mã giảm giá <span class="text-danger">*</span></label>
                            <input type="text" name="code" id="couponCode" class="form-control text-uppercase" 
                                   required placeholder="VD: SUMMER2024">
                            <small class="text-muted">Chữ hoa, không dấu, không khoảng trắng</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Loại giảm giá <span class="text-danger">*</span></label>
                            <select name="discount_type" id="discountType" class="form-select" required>
                                <option value="percent">Phần trăm (%)</option>
                                <option value="fixed">Số tiền cố định (đ)</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Giá trị giảm <span class="text-danger">*</span></label>
                            <input type="number" name="discount_value" id="discountValue" 
                                   class="form-control" min="0" step="0.01" required>
                            <small class="text-muted" id="discountHint">VD: Nhập 10 để giảm 10%</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Giảm tối đa (với %)</label>
                            <input type="number" name="max_discount" id="maxDiscount" 
                                   class="form-control" min="0" step="1000"
                                   placeholder="Để trống nếu không giới hạn">
                            <small class="text-muted">Chỉ áp dụng khi giảm theo %</small>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label class="form-label">Mô tả</label>
                            <textarea name="description" id="couponDesc" class="form-control" rows="2" 
                                      placeholder="Mô tả ngắn về mã giảm giá này..."></textarea>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Đơn hàng tối thiểu</label>
                            <input type="number" name="min_order_value" id="minOrder" 
                                   class="form-control" min="0" step="1000" value="0">
                            <small class="text-muted">Nhập 0 = không giới hạn</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Giới hạn số lần sử dụng</label>
                            <input type="number" name="usage_limit" id="usageLimit" 
                                   class="form-control" min="1"
                                   placeholder="Để trống = không giới hạn">
                            <small class="text-muted">Tổng số lần được dùng</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Ngày bắt đầu</label>
                            <input type="datetime-local" name="start_date" id="startDate" class="form-control">
                            <small class="text-muted">Để trống = hiệu lực ngay</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Ngày kết thúc</label>
                            <input type="datetime-local" name="end_date" id="endDate" class="form-control">
                            <small class="text-muted">Để trống = không hết hạn</small>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label class="form-label">Trạng thái</label>
                            <select name="status" id="couponStatus" class="form-select">
                                <option value="active">Hoạt động</option>
                                <option value="inactive">Không hoạt động</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                    <button type="submit" name="add" id="submitBtn" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> Tạo mã giảm giá
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Auto uppercase code
document.getElementById('couponCode').addEventListener('input', function() {
    this.value = this.value.toUpperCase();
});

// Change hint based on discount type
document.getElementById('discountType').addEventListener('change', function() {
    const hint = document.getElementById('discountHint');
    const maxDiscount = document.getElementById('maxDiscount').parentElement;
    
    if (this.value === 'percent') {
        hint.textContent = 'VD: Nhập 10 để giảm 10%';
        maxDiscount.style.display = 'block';
    } else {
        hint.textContent = 'VD: Nhập 50000 để giảm 50,000đ';
        maxDiscount.style.display = 'none';
    }
});

function editCoupon(coupon) {
    document.getElementById('modalTitle').textContent = 'Sửa mã giảm giá';
    document.getElementById('couponId').value = coupon.id;
    document.getElementById('couponCode').value = coupon.code;
    document.getElementById('couponDesc').value = coupon.description || '';
    document.getElementById('discountType').value = coupon.discount_type;
    document.getElementById('discountValue').value = coupon.discount_value;
    document.getElementById('minOrder').value = coupon.min_order_value || 0;
    document.getElementById('maxDiscount').value = coupon.max_discount || '';
    document.getElementById('usageLimit').value = coupon.usage_limit || '';
    
    // Format datetime for input
    if (coupon.start_date) {
        const start = new Date(coupon.start_date);
        document.getElementById('startDate').value = start.toISOString().slice(0, 16);
    }
    if (coupon.end_date) {
        const end = new Date(coupon.end_date);
        document.getElementById('endDate').value = end.toISOString().slice(0, 16);
    }
    
    document.getElementById('couponStatus').value = coupon.status;
    
    // Trigger change event
    document.getElementById('discountType').dispatchEvent(new Event('change'));
    
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.name = 'edit';
    submitBtn.innerHTML = '<i class="bi bi-check-circle"></i> Cập nhật';
    
    new bootstrap.Modal(document.getElementById('couponModal')).show();
}

// Reset form when modal closes
document.getElementById('couponModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('couponForm').reset();
    document.getElementById('modalTitle').textContent = 'Tạo mã giảm giá';
    document.getElementById('couponId').value = '';
    
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.name = 'add';
    submitBtn.innerHTML = '<i class="bi bi-check-circle"></i> Tạo mã giảm giá';
    
    // Reset hint
    document.getElementById('discountHint').textContent = 'VD: Nhập 10 để giảm 10%';
});
</script>

<?php include 'includes/admin_footer.php'; ?>