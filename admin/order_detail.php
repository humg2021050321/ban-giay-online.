<?php
require_once '../config.php';
requireAdmin();

$order_id = (int)($_GET['id'] ?? 0);

// Lấy thông tin đơn hàng
$stmt = $conn->prepare("SELECT o.*, u.email FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE o.id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order) {
    flashMessage('error', 'Đơn hàng không tồn tại');
    redirect(ADMIN_URL . 'orders.php');
}

// Xử lý cập nhật trạng thái
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $new_status = $_POST['order_status'];
    $note = sanitize($_POST['note']);
    
    try {
        $conn->beginTransaction();
        
        $stmt = $conn->prepare("UPDATE orders SET order_status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$new_status, $order_id]);
        
        // Nếu hoàn thành, cập nhật payment
        if ($new_status === 'completed') {
            $conn->prepare("UPDATE orders SET payment_status = 'paid' WHERE id = ?")->execute([$order_id]);
        }
        
        // Nếu hủy, hoàn lại tồn kho
        if ($new_status === 'cancelled' && $order['order_status'] !== 'cancelled') {
            $stmt = $conn->prepare("SELECT product_variant_id, quantity FROM order_items WHERE order_id = ?");
            $stmt->execute([$order_id]);
            $items = $stmt->fetchAll();
            
            foreach ($items as $item) {
                $conn->prepare("UPDATE product_variants SET stock_quantity = stock_quantity + ? WHERE id = ?")
                     ->execute([$item['quantity'], $item['product_variant_id']]);
            }
        }
        
        // Lưu lịch sử
        $stmt = $conn->prepare("
            INSERT INTO order_status_history (order_id, status, note, created_by)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$order_id, $new_status, $note, $_SESSION['user_id']]);
        
        $conn->commit();
        flashMessage('success', 'Cập nhật đơn hàng thành công');
        redirect(ADMIN_URL . 'order_detail.php?id=' . $order_id);
        
    } catch (Exception $e) {
        $conn->rollBack();
        flashMessage('error', 'Lỗi: ' . $e->getMessage());
    }
}

// Lấy chi tiết sản phẩm
$stmt = $conn->prepare("
    SELECT oi.*, 
           (SELECT image_url FROM product_images WHERE product_id = oi.product_id AND is_main = 1 LIMIT 1) as image,
           p.slug
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");
$stmt->execute([$order_id]);
$items = $stmt->fetchAll();

// Lấy lịch sử
$stmt = $conn->prepare("
    SELECT osh.*, u.full_name as created_by_name
    FROM order_status_history osh
    LEFT JOIN users u ON osh.created_by = u.id
    WHERE osh.order_id = ?
    ORDER BY osh.created_at DESC
");
$stmt->execute([$order_id]);
$history = $stmt->fetchAll();

$status_names = [
    'pending' => 'Chờ xác nhận',
    'confirmed' => 'Đã xác nhận',
    'shipping' => 'Đang giao hàng',
    'completed' => 'Hoàn thành',
    'cancelled' => 'Đã hủy'
];

$payment_methods = [
    'cod' => 'COD - Thanh toán khi nhận hàng',
    'bank_transfer' => 'Chuyển khoản ngân hàng',
    'e_wallet' => 'Ví điện tử'
];

$pageTitle = 'Chi tiết đơn hàng #' . $order['order_code'];
include 'includes/admin_header.php';
?>

<div class="container-fluid mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2>Đơn hàng #<?= $order['order_code'] ?></h2>
            <small class="text-muted">Ngày đặt: <?= formatDate($order['created_at']) ?></small>
        </div>
        <div>
            <a href="<?= ADMIN_URL ?>orders.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Quay lại
            </a>
            <button class="btn btn-primary" onclick="window.print()">
                <i class="bi bi-printer"></i> In đơn hàng
            </button>
        </div>
    </div>

    <div class="row">
        <!-- Thông tin đơn hàng -->
        <div class="col-lg-8">
            <!-- Cập nhật trạng thái -->
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Cập nhật trạng thái</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="update_status" value="1">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Trạng thái đơn hàng</label>
                                <select name="order_status" class="form-select" required>
                                    <?php foreach ($status_names as $status => $name): ?>
                                    <option value="<?= $status ?>" <?= $order['order_status'] === $status ? 'selected' : '' ?>>
                                        <?= $name ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Trạng thái thanh toán</label>
                                <input type="text" class="form-control" 
                                       value="<?= match($order['payment_status']) {
                                           'paid' => 'Đã thanh toán',
                                           'failed' => 'Thất bại',
                                           default => 'Chưa thanh toán'
                                       } ?>" disabled>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Ghi chú</label>
                                <textarea name="note" class="form-control" rows="2" 
                                          placeholder="Thêm ghi chú về cập nhật này..."></textarea>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle"></i> Cập nhật
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Danh sách sản phẩm -->
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Sản phẩm trong đơn hàng</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Sản phẩm</th>
                                    <th>Đơn giá</th>
                                    <th>Số lượng</th>
                                    <th class="text-end">Thành tiền</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if ($item['image']): ?>
                                                <img src="<?= UPLOAD_URL . $item['image'] ?>" 
                                                     style="width: 60px; height: 60px; object-fit: cover;"
                                                     class="rounded me-3">
                                            <?php endif; ?>
                                            <div>
                                                <strong><?= htmlspecialchars($item['product_name']) ?></strong>
                                                <div class="text-muted small">
                                                    <?php if ($item['color_name']): ?>
                                                        Màu: <?= htmlspecialchars($item['color_name']) ?>
                                                    <?php endif; ?>
                                                    <?php if ($item['size_name']): ?>
                                                        | Size: <?= htmlspecialchars($item['size_name']) ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= formatPrice($item['price']) ?></td>
                                    <td>x<?= $item['quantity'] ?></td>
                                    <td class="text-end fw-bold"><?= formatPrice($item['subtotal']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <td colspan="3" class="text-end"><strong>Tạm tính:</strong></td>
                                    <td class="text-end"><?= formatPrice($order['subtotal']) ?></td>
                                </tr>
                                <tr>
                                    <td colspan="3" class="text-end"><strong>Phí vận chuyển:</strong></td>
                                    <td class="text-end">
                                        <?= $order['shipping_fee'] > 0 ? formatPrice($order['shipping_fee']) : '<span class="text-success">Miễn phí</span>' ?>
                                    </td>
                                </tr>
                                <?php if ($order['discount_amount'] > 0): ?>
                                <tr>
                                    <td colspan="3" class="text-end"><strong>Giảm giá:</strong></td>
                                    <td class="text-end text-danger">-<?= formatPrice($order['discount_amount']) ?></td>
                                </tr>
                                <?php endif; ?>
                                <tr class="table-warning">
                                    <td colspan="3" class="text-end"><strong class="fs-5">TỔNG CỘNG:</strong></td>
                                    <td class="text-end"><strong class="fs-5 text-danger"><?= formatPrice($order['total_amount']) ?></strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Lịch sử -->
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Lịch sử đơn hàng</h5>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <?php foreach ($history as $h): ?>
                        <div class="timeline-item">
                            <div class="timeline-marker">
                                <i class="bi bi-circle-fill"></i>
                            </div>
                            <div class="timeline-content">
                                <div class="d-flex justify-content-between mb-1">
                                    <strong><?= $status_names[$h['status']] ?? $h['status'] ?></strong>
                                    <small class="text-muted"><?= formatDate($h['created_at']) ?></small>
                                </div>
                                <?php if ($h['note']): ?>
                                    <p class="mb-1 text-muted"><?= htmlspecialchars($h['note']) ?></p>
                                <?php endif; ?>
                                <?php if ($h['created_by_name']): ?>
                                    <small class="text-muted">Bởi: <?= htmlspecialchars($h['created_by_name']) ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Thông tin khách hàng -->
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Thông tin khách hàng</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="small text-muted">Họ tên:</label>
                        <div class="fw-bold"><?= htmlspecialchars($order['full_name']) ?></div>
                    </div>
                    <div class="mb-3">
                        <label class="small text-muted">Số điện thoại:</label>
                        <div><?= htmlspecialchars($order['phone']) ?></div>
                    </div>
                    <div class="mb-3">
                        <label class="small text-muted">Email:</label>
                        <div><?= htmlspecialchars($order['email'] ?? 'Không có') ?></div>
                    </div>
                    <div class="mb-3">
                        <label class="small text-muted">Địa chỉ giao hàng:</label>
                        <div><?= nl2br(htmlspecialchars($order['address'])) ?></div>
                    </div>
                    <?php if ($order['note']): ?>
                    <div class="mb-0">
                        <label class="small text-muted">Ghi chú:</label>
                        <div><?= nl2br(htmlspecialchars($order['note'])) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Thông tin thanh toán -->
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Thông tin thanh toán</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="small text-muted">Phương thức:</label>
                        <div><?= $payment_methods[$order['payment_method']] ?? $order['payment_method'] ?></div>
                    </div>
                    <div class="mb-3">
                        <label class="small text-muted">Trạng thái thanh toán:</label>
                        <div>
                            <span class="badge bg-<?= match($order['payment_status']) {
                                'paid' => 'success',
                                'failed' => 'danger',
                                default => 'warning'
                            } ?>">
                                <?= match($order['payment_status']) {
                                    'paid' => 'Đã thanh toán',
                                    'failed' => 'Thất bại',
                                    default => 'Chưa thanh toán'
                                } ?>
                            </span>
                        </div>
                    </div>
                    <div class="mb-0">
                        <label class="small text-muted">Tổng tiền:</label>
                        <div class="fs-4 fw-bold text-danger"><?= formatPrice($order['total_amount']) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding: 20px 0;
}
.timeline-item {
    display: flex;
    margin-bottom: 25px;
    position: relative;
}
.timeline-marker {
    width: 30px;
    margin-right: 15px;
    flex-shrink: 0;
}
.timeline-marker i {
    color: #007bff;
}
.timeline-item:not(:last-child)::before {
    content: '';
    position: absolute;
    left: 14px;
    top: 25px;
    width: 2px;
    height: calc(100% + 5px);
    background: #dee2e6;
}
.timeline-content {
    flex-grow: 1;
    padding-bottom: 10px;
}

@media print {
    .sidebar, .btn, .card-header, nav, .timeline {
        display: none !important;
    }
    .card {
        border: none !important;
        box-shadow: none !important;
    }
}
</style>

<?php include 'includes/admin_footer.php'; ?>