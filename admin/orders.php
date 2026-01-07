<?php
require_once '../config.php';
requireAdmin();

// Lọc theo trạng thái
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

$where = ["1=1"];
$params = [];

if ($status_filter) {
    $where[] = "o.order_status = ?";
    $params[] = $status_filter;
}

if ($search) {
    $where[] = "(o.order_code LIKE ? OR o.full_name LIKE ? OR o.phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_sql = implode(' AND ', $where);

// Phân trang
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Đếm tổng
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM orders o WHERE $where_sql");
$stmt->execute($params);
$total = $stmt->fetch()['total'];
$total_pages = ceil($total / $per_page);

// Lấy danh sách đơn hàng
$stmt = $conn->prepare("
    SELECT o.*, u.email
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    WHERE $where_sql
    ORDER BY o.created_at DESC
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Thống kê theo trạng thái
$stats = $conn->query("
    SELECT order_status, COUNT(*) as count 
    FROM orders 
    GROUP BY order_status
")->fetchAll(PDO::FETCH_KEY_PAIR);

$status_names = [
    'pending' => 'Chờ xác nhận',
    'confirmed' => 'Đã xác nhận',
    'shipping' => 'Đang giao',
    'completed' => 'Hoàn thành',
    'cancelled' => 'Đã hủy'
];

$pageTitle = 'Quản lý đơn hàng';
include 'includes/admin_header.php';
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Quản lý đơn hàng</h2>
    </div>

    <!-- Status tabs -->
    <ul class="nav nav-pills mb-4">
        <li class="nav-item">
            <a class="nav-link <?= !$status_filter ? 'active' : '' ?>" href="orders.php">
                Tất cả
                <span class="badge bg-secondary"><?= array_sum($stats) ?></span>
            </a>
        </li>
        <?php foreach ($status_names as $status => $name): ?>
        <li class="nav-item">
            <a class="nav-link <?= $status_filter === $status ? 'active' : '' ?>" 
               href="orders.php?status=<?= $status ?>">
                <?= $name ?>
                <?php if (isset($stats[$status]) && $stats[$status] > 0): ?>
                    <span class="badge bg-<?= match($status) {
                        'pending' => 'warning',
                        'confirmed' => 'info',
                        'shipping' => 'primary',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'secondary'
                    } ?>"><?= $stats[$status] ?></span>
                <?php endif; ?>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>

    <!-- Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <?php if ($status_filter): ?>
                    <input type="hidden" name="status" value="<?= $status_filter ?>">
                <?php endif; ?>
                <div class="col-md-8">
                    <input type="text" name="search" class="form-control" 
                           placeholder="Tìm theo mã đơn, tên khách hàng, số điện thoại..." 
                           value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="bi bi-search"></i> Tìm kiếm
                    </button>
                    <a href="orders.php<?= $status_filter ? '?status='.$status_filter : '' ?>" 
                       class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-clockwise"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Orders Table -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Mã đơn</th>
                            <th>Khách hàng</th>
                            <th>Tổng tiền</th>
                            <th>Thanh toán</th>
                            <th>Trạng thái</th>
                            <th>Ngày đặt</th>
                            <th style="width: 150px;">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                Không có đơn hàng nào
                            </td>
                        </tr>
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
                                
                                $payment_badge = match($order['payment_status']) {
                                    'paid' => 'success',
                                    'failed' => 'danger',
                                    default => 'warning'
                                };
                            ?>
                            <tr>
                                <td>
                                    <a href="order_detail.php?id=<?= $order['id'] ?>" 
                                       class="fw-bold text-decoration-none">
                                        <?= $order['order_code'] ?>
                                    </a>
                                </td>
                                <td>
                                    <div><?= htmlspecialchars($order['full_name']) ?></div>
                                    <small class="text-muted"><?= $order['phone'] ?></small>
                                </td>
                                <td class="fw-bold"><?= formatPrice($order['total_amount']) ?></td>
                                <td>
                                    <span class="badge bg-<?= $payment_badge ?>">
                                        <?= match($order['payment_status']) {
                                            'paid' => 'Đã thanh toán',
                                            'failed' => 'Thất bại',
                                            default => 'Chưa thanh toán'
                                        } ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $badge_class ?>">
                                        <?= $status_names[$order['order_status']] ?? $order['order_status'] ?>
                                    </span>
                                </td>
                                <td>
                                    <small><?= formatDate($order['created_at']) ?></small>
                                </td>
                                <td class="table-actions">
                                    <a href="order_detail.php?id=<?= $order['id'] ?>" 
                                       class="btn btn-sm btn-outline-primary" title="Xem chi tiết">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <?php if ($order['order_status'] === 'pending'): ?>
                                        <a href="order_update.php?id=<?= $order['id'] ?>&status=confirmed" 
                                           class="btn btn-sm btn-outline-success" 
                                           title="Xác nhận"
                                           onclick="return confirm('Xác nhận đơn hàng này?')">
                                            <i class="bi bi-check-circle"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($order['order_status'] === 'confirmed'): ?>
                                        <a href="order_update.php?id=<?= $order['id'] ?>&status=shipping" 
                                           class="btn btn-sm btn-outline-info" 
                                           title="Chuyển sang đang giao"
                                           onclick="return confirm('Đơn hàng đã giao cho shipper?')">
                                            <i class="bi bi-truck"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($order['order_status'] === 'shipping'): ?>
                                        <a href="order_update.php?id=<?= $order['id'] ?>&status=completed" 
                                           class="btn btn-sm btn-outline-success" 
                                           title="Hoàn thành"
                                           onclick="return confirm('Đơn hàng đã giao thành công?')">
                                            <i class="bi bi-check2-all"></i>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($total_pages > 1): ?>
        <div class="card-footer">
            <nav>
                <ul class="pagination justify-content-center mb-0">
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
            <div class="text-center text-muted mt-2">
                <small>Hiển thị <?= count($orders) ?> / <?= $total ?> đơn hàng</small>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/admin_footer.php'; ?>