<?php
require_once '../config.php';
requireAdmin();

// Thống kê tổng quan
$stats = [];

// Tổng doanh thu
$stmt = $conn->query("
    SELECT SUM(total_amount) as total_revenue 
    FROM orders 
    WHERE order_status IN ('completed', 'shipping') 
    AND payment_status = 'paid'
");
$stats['revenue'] = $stmt->fetch()['total_revenue'] ?? 0;

// Doanh thu tháng này
$stmt = $conn->query("
    SELECT SUM(total_amount) as month_revenue 
    FROM orders 
    WHERE order_status IN ('completed', 'shipping') 
    AND payment_status = 'paid'
    AND MONTH(created_at) = MONTH(CURRENT_DATE())
    AND YEAR(created_at) = YEAR(CURRENT_DATE())
");
$stats['month_revenue'] = $stmt->fetch()['month_revenue'] ?? 0;

// Tổng đơn hàng
$stmt = $conn->query("SELECT COUNT(*) as total FROM orders");
$stats['total_orders'] = $stmt->fetch()['total'];

// Đơn hàng chờ xử lý
$stmt = $conn->query("SELECT COUNT(*) as total FROM orders WHERE order_status = 'pending'");
$stats['pending_orders'] = $stmt->fetch()['total'];

// Tổng sản phẩm
$stmt = $conn->query("SELECT COUNT(*) as total FROM products WHERE status = 'active'");
$stats['total_products'] = $stmt->fetch()['total'];

// Tổng khách hàng
$stmt = $conn->query("SELECT COUNT(*) as total FROM users WHERE role_id = 2");
$stats['total_customers'] = $stmt->fetch()['total'];

// Đơn hàng mới nhất
$recent_orders = $conn->query("
    SELECT o.*, u.full_name, u.phone 
    FROM orders o
    JOIN users u ON o.user_id = u.id
    ORDER BY o.created_at DESC
    LIMIT 10
")->fetchAll();

// Sản phẩm sắp hết hàng
$low_stock = $conn->query("
    SELECT p.name, p.slug, 
           SUM(pv.stock_quantity) as total_stock,
           GROUP_CONCAT(CONCAT(c.name, ' - ', s.name, ': ', pv.stock_quantity) SEPARATOR ', ') as variants
    FROM products p
    JOIN product_variants pv ON p.id = pv.product_id
    LEFT JOIN colors c ON pv.color_id = c.id
    LEFT JOIN sizes s ON pv.size_id = s.id
    WHERE p.status = 'active'
    GROUP BY p.id
    HAVING total_stock < 10
    ORDER BY total_stock ASC
    LIMIT 10
")->fetchAll();

// Doanh thu 7 ngày gần nhất
$revenue_chart = $conn->query("
    SELECT DATE(created_at) as date, 
           SUM(total_amount) as revenue,
           COUNT(*) as orders
    FROM orders
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    AND order_status IN ('completed', 'shipping')
    GROUP BY DATE(created_at)
    ORDER BY date ASC
")->fetchAll();

$pageTitle = 'Dashboard';
include 'includes/admin_header.php';
?>

<div class="container-fluid mt-4">
    <h2 class="mb-4">Dashboard</h2>

    <!-- Statistics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle mb-2 text-white-50">Tổng doanh thu</h6>
                            <h3 class="mb-0"><?= formatPrice($stats['revenue']) ?></h3>
                        </div>
                        <i class="bi bi-currency-dollar fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle mb-2 text-white-50">Doanh thu tháng</h6>
                            <h3 class="mb-0"><?= formatPrice($stats['month_revenue']) ?></h3>
                        </div>
                        <i class="bi bi-graph-up fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle mb-2 text-white-50">Đơn hàng</h6>
                            <h3 class="mb-0"><?= number_format($stats['total_orders']) ?></h3>
                            <?php if ($stats['pending_orders'] > 0): ?>
                                <small class="badge bg-danger"><?= $stats['pending_orders'] ?> chờ xử lý</small>
                            <?php endif; ?>
                        </div>
                        <i class="bi bi-bag-check fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle mb-2 text-white-50">Khách hàng</h6>
                            <h3 class="mb-0"><?= number_format($stats['total_customers']) ?></h3>
                            <small><?= number_format($stats['total_products']) ?> sản phẩm</small>
                        </div>
                        <i class="bi bi-people fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Revenue Chart -->
        <div class="col-lg-8 mb-4">
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Doanh thu 7 ngày gần nhất</h5>
                </div>
                <div class="card-body">
                    <canvas id="revenueChart" height="100"></canvas>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="col-lg-4 mb-4">
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Trạng thái đơn hàng</h5>
                </div>
                <div class="card-body">
                    <?php
                    $order_status = $conn->query("
                        SELECT order_status, COUNT(*) as count 
                        FROM orders 
                        GROUP BY order_status
                    ")->fetchAll();
                    
                    $status_names = [
                        'pending' => 'Chờ xác nhận',
                        'confirmed' => 'Đã xác nhận',
                        'shipping' => 'Đang giao',
                        'completed' => 'Hoàn thành',
                        'cancelled' => 'Đã hủy'
                    ];
                    
                    foreach ($order_status as $status):
                    ?>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span><?= $status_names[$status['order_status']] ?? $status['order_status'] ?></span>
                            <span class="badge bg-primary rounded-pill"><?= $status['count'] ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Recent Orders -->
        <div class="col-lg-8 mb-4">
            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Đơn hàng mới nhất</h5>
                    <a href="orders.php" class="btn btn-sm btn-primary">Xem tất cả</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Mã đơn</th>
                                    <th>Khách hàng</th>
                                    <th>Tổng tiền</th>
                                    <th>Trạng thái</th>
                                    <th>Thời gian</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_orders as $order): ?>
                                <tr>
                                    <td>
                                        <a href="order_detail.php?id=<?= $order['id'] ?>">
                                            <?= $order['order_code'] ?>
                                        </a>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($order['full_name']) ?><br>
                                        <small class="text-muted"><?= $order['phone'] ?></small>
                                    </td>
                                    <td><?= formatPrice($order['total_amount']) ?></td>
                                    <td>
                                        <?php
                                        $badge_class = match($order['order_status']) {
                                            'pending' => 'warning',
                                            'confirmed' => 'info',
                                            'shipping' => 'primary',
                                            'completed' => 'success',
                                            'cancelled' => 'danger',
                                            default => 'secondary'
                                        };
                                        ?>
                                        <span class="badge bg-<?= $badge_class ?>">
                                            <?= $status_names[$order['order_status']] ?? $order['order_status'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small><?= formatDate($order['created_at']) ?></small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Low Stock Products -->
        <div class="col-lg-4 mb-4">
            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Sản phẩm sắp hết</h5>
                    <a href="products.php" class="btn btn-sm btn-primary">Quản lý</a>
                </div>
                <div class="card-body">
                    <?php if (empty($low_stock)): ?>
                        <p class="text-muted text-center">Tất cả sản phẩm còn đủ hàng</p>
                    <?php else: ?>
                        <?php foreach ($low_stock as $product): ?>
                        <div class="border-bottom pb-2 mb-2">
                            <a href="<?= BASE_URL ?>product_detail.php?slug=<?= $product['slug'] ?>" 
                               class="text-decoration-none text-dark">
                                <strong><?= htmlspecialchars($product['name']) ?></strong>
                            </a>
                            <div class="d-flex justify-content-between align-items-center mt-1">
                                <small class="text-muted">Tồn kho: </small>
                                <span class="badge bg-<?= $product['total_stock'] < 5 ? 'danger' : 'warning' ?>">
                                    <?= $product['total_stock'] ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Revenue Chart
const ctx = document.getElementById('revenueChart');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= json_encode(array_map(fn($r) => date('d/m', strtotime($r['date'])), $revenue_chart)) ?>,
        datasets: [{
            label: 'Doanh thu',
            data: <?= json_encode(array_map(fn($r) => $r['revenue'], $revenue_chart)) ?>,
            borderColor: 'rgb(37, 99, 235)',
            backgroundColor: 'rgba(37, 99, 235, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return new Intl.NumberFormat('vi-VN', { 
                            style: 'currency', 
                            currency: 'VND' 
                        }).format(context.parsed.y);
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return new Intl.NumberFormat('vi-VN', { 
                            notation: 'compact',
                            compactDisplay: 'short'
                        }).format(value) + 'đ';
                    }
                }
            }
        }
    }
});
</script>

<?php include 'includes/admin_footer.php'; ?>