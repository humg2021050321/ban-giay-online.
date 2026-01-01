<?php
require_once 'config.php';
requireLogin();

$order_id = (int)($_GET['id'] ?? 0);

// Lấy thông tin đơn hàng
$stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->execute([$order_id, $_SESSION['user_id']]);
$order = $stmt->fetch();

if (!$order) {
    flashMessage('error', 'Đơn hàng không tồn tại');
    redirect(BASE_URL . 'orders.php');
}

// Lấy chi tiết sản phẩm
$stmt = $conn->prepare("
    SELECT oi.*, 
           (SELECT image_url FROM product_images 
            WHERE product_id = oi.product_id AND is_main = 1 LIMIT 1) as image,
           p.slug
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");
$stmt->execute([$order_id]);
$items = $stmt->fetchAll();

// Lấy lịch sử trạng thái
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
    'cod' => 'Thanh toán khi nhận hàng (COD)',
    'bank_transfer' => 'Chuyển khoản ngân hàng',
    'e_wallet' => 'Ví điện tử'
];

$pageTitle = 'Chi tiết đơn hàng #' . $order['order_code'];
include 'includes/header.php';
?>

<div class="container mt-4 mb-5">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>">Trang chủ</a></li>
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>orders.php">Đơn hàng</a></li>
            <li class="breadcrumb-item active">Chi tiết đơn hàng</li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Đơn hàng #<?= $order['order_code'] ?></h2>
        <a href="<?= BASE_URL ?>orders.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Quay lại
        </a>
    </div>

    <?php 
    $flash = getFlashMessage();
    if ($flash): 
    ?>
        <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show">
            <?= $flash['message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Thông tin đơn hàng -->
        <div class="col-lg-8">
            <!-- Trạng thái -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-2">Trạng thái đơn hàng</h5>
                            <span class="badge bg-<?= match($order['order_status']) {
                                'pending' => 'warning',
                                'confirmed' => 'info',
                                'shipping' => 'primary',
                                'completed' => 'success',
                                'cancelled' => 'danger',
                                default => 'secondary'
                            } ?> fs-5">
                                <?= $status_names[$order['order_status']] ?? $order['order_status'] ?>
                            </span>
                        </div>
                        <div>
                            <?php if ($order['order_status'] === 'pending'): ?>
                                <a href="order_cancel.php?id=<?= $order['id'] ?>" 
                                   class="btn btn-danger"
                                   onclick="return confirm('Bạn có chắc muốn hủy đơn hàng này?')">
                                    <i class="bi bi-x-circle"></i> Hủy đơn hàng
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Timeline trạng thái -->
                    <div class="mt-4">
                        <div class="timeline">
                            <?php 
                            $statuses = ['pending', 'confirmed', 'shipping', 'completed'];
                            $current_index = array_search($order['order_status'], $statuses);
                            
                            foreach ($statuses as $index => $status):
                                $is_completed = $index <= $current_index && $order['order_status'] !== 'cancelled';
                                $is_current = $status === $order['order_status'];
                            ?>
                            <div class="timeline-item <?= $is_completed ? 'completed' : '' ?> <?= $is_current ? 'current' : '' ?>">
                                <div class="timeline-marker">
                                    <?php if ($is_completed): ?>
                                        <i class="bi bi-check-circle-fill"></i>
                                    <?php else: ?>
                                        <i class="bi bi-circle"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="timeline-content">
                                    <strong><?= $status_names[$status] ?></strong>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sản phẩm -->
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Sản phẩm đã đặt</h5>
                </div>
                <div class="card-body">
                    <?php foreach ($items as $item): ?>
                    <div class="d-flex mb-3 pb-3 border-bottom">
                        <?php if ($item['image']): ?>
                            <img src="<?= UPLOAD_URL . $item['image'] ?>" 
                                 alt="<?= htmlspecialchars($item['product_name']) ?>"
                                 style="width: 100px; height: 100px; object-fit: cover;"
                                 class="rounded">
                        <?php else: ?>
                            <div style="width: 100px; height: 100px;" 
                                 class="bg-light rounded d-flex align-items-center justify-content-center">
                                <i class="bi bi-image text-muted fs-3"></i>
                            </div>
                        <?php endif; ?>
                        
                        <div class="ms-3 flex-grow-1">
                            <h6 class="mb-1">
                                <?php if ($item['slug']): ?>
                                    <a href="<?= BASE_URL ?>product_detail.php?slug=<?= $item['slug'] ?>" 
                                       class="text-decoration-none text-dark">
                                        <?= htmlspecialchars($item['product_name']) ?>
                                    </a>
                                <?php else: ?>
                                    <?= htmlspecialchars($item['product_name']) ?>
                                <?php endif; ?>
                            </h6>
                            <div class="text-muted">
                                <?php if ($item['color_name']): ?>
                                    Màu: <?= htmlspecialchars($item['color_name']) ?>
                                <?php endif; ?>
                                <?php if ($item['size_name']): ?>
                                    | Size: <?= htmlspecialchars($item['size_name']) ?>
                                <?php endif; ?>
                            </div>
                            <div class="mt-2">
                                <span class="text-muted">Đơn giá: <?= formatPrice($item['price']) ?></span>
                                <span class="text-muted ms-3">Số lượng: x<?= $item['quantity'] ?></span>
                            </div>
                        </div>
                        
                        <div class="text-end">
                            <strong class="text-primary fs-5"><?= formatPrice($item['subtotal']) ?></strong>
                            <?php if ($order['order_status'] === 'completed'): ?>
                                <div class="mt-2">
                                    <a href="review.php?order_id=<?= $order['id'] ?>&product_id=<?= $item['product_id'] ?>" 
                                       class="btn btn-sm btn-warning">
                                        <i class="bi bi-star"></i> Đánh giá
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Lịch sử -->
            <?php if (!empty($history)): ?>
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Lịch sử đơn hàng</h5>
                </div>
                <div class="card-body">
                    <?php foreach ($history as $h): ?>
                    <div class="mb-3 pb-3 <?= $h !== end($history) ? 'border-bottom' : '' ?>">
                        <div class="d-flex justify-content-between">
                            <strong><?= $status_names[$h['status']] ?? $h['status'] ?></strong>
                            <small class="text-muted"><?= formatDate($h['created_at']) ?></small>
                        </div>
                        <?php if ($h['note']): ?>
                            <p class="mb-0 mt-1"><?= htmlspecialchars($h['note']) ?></p>
                        <?php endif; ?>
                        <?php if ($h['created_by_name']): ?>
                            <small class="text-muted">Bởi: <?= htmlspecialchars($h['created_by_name']) ?></small>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Thông tin thanh toán & giao hàng -->
        <div class="col-lg-4">
            <!-- Tổng tiền -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Thông tin thanh toán</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Tạm tính:</span>
                        <strong><?= formatPrice($order['subtotal']) ?></strong>
                    </div>
                    
                    <div class="d-flex justify-content-between mb-2">
                        <span>Phí vận chuyển:</span>
                        <strong>
                            <?php if ($order['shipping_fee'] > 0): ?>
                                <?= formatPrice($order['shipping_fee']) ?>
                            <?php else: ?>
                                <span class="text-success">Miễn phí</span>
                            <?php endif; ?>
                        </strong>
                    </div>
                    
                    <?php if ($order['discount_amount'] > 0): ?>
                    <div class="d-flex justify-content-between mb-2 text-danger">
                        <span>Giảm giá:</span>
                        <strong>-<?= formatPrice($order['discount_amount']) ?></strong>
                    </div>
                    <?php endif; ?>
                    
                    <hr>
                    
                    <div class="d-flex justify-content-between mb-3">
                        <strong class="fs-5">Tổng cộng:</strong>
                        <h4 class="text-danger mb-0"><?= formatPrice($order['total_amount']) ?></h4>
                    </div>

                    <div class="alert alert-info mb-0">
                        <small>
                            <strong>Phương thức thanh toán:</strong><br>
                            <?= $payment_methods[$order['payment_method']] ?? $order['payment_method'] ?>
                        </small>
                    </div>
                </div>
            </div>

            <!-- Thông tin giao hàng -->
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Thông tin giao hàng</h5>
                </div>
                <div class="card-body">
                    <p class="mb-2">
                        <strong>Người nhận:</strong><br>
                        <?= htmlspecialchars($order['full_name']) ?>
                    </p>
                    <p class="mb-2">
                        <strong>Số điện thoại:</strong><br>
                        <?= htmlspecialchars($order['phone']) ?>
                    </p>
                    <?php if ($order['email']): ?>
                    <p class="mb-2">
                        <strong>Email:</strong><br>
                        <?= htmlspecialchars($order['email']) ?>
                    </p>
                    <?php endif; ?>
                    <p class="mb-2">
                        <strong>Địa chỉ:</strong><br>
                        <?= nl2br(htmlspecialchars($order['address'])) ?>
                    </p>
                    <?php if ($order['note']): ?>
                    <p class="mb-0">
                        <strong>Ghi chú:</strong><br>
                        <?= nl2br(htmlspecialchars($order['note'])) ?>
                    </p>
                    <?php endif; ?>
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
    align-items: flex-start;
    margin-bottom: 30px;
    position: relative;
}

.timeline-marker {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #e9ecef;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
    flex-shrink: 0;
}

.timeline-item.completed .timeline-marker {
    background: #28a745;
    color: white;
}

.timeline-item.current .timeline-marker {
    background: #007bff;
    color: white;
}

.timeline-item:not(:last-child)::after {
    content: '';
    position: absolute;
    left: 19px;
    top: 40px;
    width: 2px;
    height: calc(100% + 10px);
    background: #dee2e6;
}

.timeline-item.completed:not(:last-child)::after {
    background: #28a745;
}
</style>

<?php include 'includes/footer.php'; ?>