<?php
require_once 'config.php';
requireLogin();

// Lọc theo trạng thái
$status_filter = $_GET['status'] ?? '';

$where = "user_id = ?";
$params = [$_SESSION['user_id']];

if ($status_filter) {
    $where .= " AND order_status = ?";
    $params[] = $status_filter;
}

// Phân trang
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Đếm tổng đơn hàng
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM orders WHERE $where");
$stmt->execute($params);
$total = $stmt->fetch()['total'];
$total_pages = ceil($total / $per_page);

// Lấy danh sách đơn hàng
$stmt = $conn->prepare("
    SELECT * FROM orders 
    WHERE $where 
    ORDER BY created_at DESC 
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$orders = $stmt->fetchAll();

$pageTitle = 'Đơn hàng của tôi';
include 'includes/header.php';
?>

<div class="container mt-4 mb-5">
    <h2 class="mb-4">Đơn hàng của tôi</h2>

    <?php 
    $flash = getFlashMessage();
    if ($flash): 
    ?>
        <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show">
            <?= $flash['message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Filter Tabs -->
    <ul class="nav nav-pills mb-4">
        <li class="nav-item">
            <a class="nav-link <?= !$status_filter ? 'active' : '' ?>" href="orders.php">
                Tất cả
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $status_filter === 'pending' ? 'active' : '' ?>" href="orders.php?status=pending">
                Chờ xác nhận
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $status_filter === 'confirmed' ? 'active' : '' ?>" href="orders.php?status=confirmed">
                Đã xác nhận
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $status_filter === 'shipping' ? 'active' : '' ?>" href="orders.php?status=shipping">
                Đang giao
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $status_filter === 'completed' ? 'active' : '' ?>" href="orders.php?status=completed">
                Hoàn thành
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $status_filter === 'cancelled' ? 'active' : '' ?>" href="orders.php?status=cancelled">
                Đã hủy
            </a>
        </li>
    </ul>

    <?php if (empty($orders)): ?>
        <div class="text-center py-5">
            <i class="bi bi-inbox" style="font-size: 5rem; color: #ccc;"></i>
            <h4 class="mt-3">Chưa có đơn hàng nào</h4>
            <p class="text-muted">Bạn chưa có đơn hàng nào trong danh sách này</p>
            <a href="<?= BASE_URL ?>products.php" class="btn btn-primary">
                <i class="bi bi-shop"></i> Tiếp tục mua sắm
            </a>
        </div>
    <?php else: ?>
        <?php foreach ($orders as $order): 
            $badge_class = match($order['order_status']) {
                'pending' => 'warning',
                'confirmed' => 'info',
                'shipping' => 'primary',
                'completed' => 'success',
                'cancelled' => 'danger',
                default => 'secondary'
            };
            
            $status_text = match($order['order_status']) {
                'pending' => 'Chờ xác nhận',
                'confirmed' => 'Đã xác nhận',
                'shipping' => 'Đang giao hàng',
                'completed' => 'Hoàn thành',
                'cancelled' => 'Đã hủy',
                default => $order['order_status']
            };
        ?>
        <div class="card mb-3">
            <div class="card-header bg-white">
                <div class="row align-items-center">
                    <div class="col-md-3">
                        <small class="text-muted">Mã đơn hàng:</small>
                        <div><strong><?= $order['order_code'] ?></strong></div>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted">Ngày đặt:</small>
                        <div><?= formatDate($order['created_at']) ?></div>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted">Tổng tiền:</small>
                        <div class="text-danger fw-bold"><?= formatPrice($order['total_amount']) ?></div>
                    </div>
                    <div class="col-md-3 text-end">
                        <span class="badge bg-<?= $badge_class ?> fs-6">
                            <?= $status_text ?>
                        </span>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <?php
                // Lấy sản phẩm trong đơn
                $stmt = $conn->prepare("
                    SELECT oi.*, 
                           (SELECT image_url FROM product_images 
                            WHERE product_id = oi.product_id AND is_main = 1 LIMIT 1) as image,
                           p.slug
                    FROM order_items oi
                    LEFT JOIN products p ON oi.product_id = p.id
                    WHERE oi.order_id = ?
                ");
                $stmt->execute([$order['id']]);
                $items = $stmt->fetchAll();
                ?>
                
                <?php foreach ($items as $item): ?>
                <div class="d-flex align-items-center mb-3 pb-3 <?= $item !== end($items) ? 'border-bottom' : '' ?>">
                    <?php if ($item['image']): ?>
                        <img src="<?= UPLOAD_URL . $item['image'] ?>" 
                             alt="<?= htmlspecialchars($item['product_name']) ?>"
                             style="width: 80px; height: 80px; object-fit: cover;"
                             class="rounded">
                    <?php else: ?>
                        <div style="width: 80px; height: 80px;" 
                             class="bg-light rounded d-flex align-items-center justify-content-center">
                            <i class="bi bi-image text-muted"></i>
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
                        <small class="text-muted">
                            <?php if ($item['color_name']): ?>
                                Màu: <?= htmlspecialchars($item['color_name']) ?>
                            <?php endif; ?>
                            <?php if ($item['size_name']): ?>
                                | Size: <?= htmlspecialchars($item['size_name']) ?>
                            <?php endif; ?>
                            | x<?= $item['quantity'] ?>
                        </small>
                    </div>
                    
                    <div class="text-end">
                        <div class="fw-bold"><?= formatPrice($item['subtotal']) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="card-footer bg-white">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <?php if ($order['order_status'] === 'pending'): ?>
                            <button class="btn btn-sm btn-danger" 
                                    onclick="if(confirm('Bạn có chắc muốn hủy đơn hàng này?')) location.href='order_cancel.php?id=<?= $order['id'] ?>'">
                                <i class="bi bi-x-circle"></i> Hủy đơn
                            </button>
                        <?php endif; ?>
                        
                        <?php if ($order['order_status'] === 'completed'): ?>
                            <?php
                            // Kiểm tra từng sản phẩm đã được đánh giá chưa
                            foreach ($items as $item):
                                $stmt = $conn->prepare("
                                    SELECT id FROM reviews 
                                    WHERE order_id = ? AND product_id = ? AND user_id = ?
                                ");
                                $stmt->execute([$order['id'], $item['product_id'], $_SESSION['user_id']]);
                                $has_review = $stmt->fetch();
                                
                                if (!$has_review):
                            ?>
                                <a href="review.php?order_id=<?= $order['id'] ?>&product_id=<?= $item['product_id'] ?>" 
                                   class="btn btn-sm btn-warning">
                                    <i class="bi bi-star"></i> Đánh giá
                                </a>
                            <?php 
                                    break; // Chỉ hiện 1 nút đánh giá
                                endif;
                            endforeach; 
                            ?>
                        <?php endif; ?>
                    </div>
                    
                    <div>
                        <a href="order_detail.php?id=<?= $order['id'] ?>" class="btn btn-sm btn-primary">
                            <i class="bi bi-eye"></i> Xem chi tiết
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <?php if ($page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                </li>
                <?php endif; ?>

                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                        <?= $i ?>
                    </a>
                </li>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                <li class="page-item">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>