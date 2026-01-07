<?php
require_once '../config.php';
requireAdmin();

// Lấy khoảng thời gian
$from_date = $_GET['from'] ?? date('Y-m-01');
$to_date = $_GET['to'] ?? date('Y-m-d');

// Doanh thu theo ngày
$revenue_daily = $conn->prepare("
    SELECT DATE(created_at) as date, 
           SUM(total_amount) as revenue,
           COUNT(*) as orders
    FROM orders
    WHERE created_at BETWEEN ? AND ?
    AND order_status IN ('completed', 'shipping')
    GROUP BY DATE(created_at)
    ORDER BY date ASC
");
$revenue_daily->execute([$from_date, $to_date]);
$daily_data = $revenue_daily->fetchAll();

// Sản phẩm bán chạy
$best_sellers = $conn->prepare("
    SELECT p.name, p.slug,
           SUM(oi.quantity) as total_sold,
           SUM(oi.subtotal) as revenue
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN orders o ON oi.order_id = o.id
    WHERE o.created_at BETWEEN ? AND ?
    AND o.order_status IN ('completed', 'shipping')
    GROUP BY p.id
    ORDER BY total_sold DESC
    LIMIT 10
");
$best_sellers->execute([$from_date, $to_date]);
$top_products = $best_sellers->fetchAll();

// Thống kê tổng quan
$overview = $conn->prepare("
    SELECT 
        COUNT(*) as total_orders,
        SUM(total_amount) as total_revenue,
        AVG(total_amount) as avg_order_value,
        COUNT(DISTINCT user_id) as total_customers
    FROM orders
    WHERE created_at BETWEEN ? AND ?
    AND order_status IN ('completed', 'shipping')
");
$overview->execute([$from_date, $to_date]);
$stats = $overview->fetch();

// Đơn hàng theo trạng thái
$order_status = $conn->prepare("
    SELECT order_status, COUNT(*) as count
    FROM orders
    WHERE created_at BETWEEN ? AND ?
    GROUP BY order_status
");
$order_status->execute([$from_date, $to_date]);
$status_data = $order_status->fetchAll(PDO::FETCH_KEY_PAIR);

// Top khách hàng
$top_customers = $conn->prepare("
    SELECT u.full_name, u.email,
           COUNT(o.id) as order_count,
           SUM(o.total_amount) as total_spent
    FROM users u
    JOIN orders o ON u.id = o.user_id
    WHERE o.created_at BETWEEN ? AND ?
    AND o.order_status IN ('completed', 'shipping')
    GROUP BY u.id
    ORDER BY total_spent DESC
    LIMIT 10
");
$top_customers->execute([$from_date, $to_date]);
$top_buyers = $top_customers->fetchAll();

$pageTitle = 'Thống kê & Báo cáo';
include 'includes/admin_header.php';
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Thống kê & Báo cáo</h2>
        <button class="btn btn-primary" onclick="window.print()">
            <i class="bi bi-printer"></i> In báo cáo
        </button>
    </div>

    <!-- Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Từ ngày</label>
                    <input type="date" name="from" class="form-control" value="<?= $from_date ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Đến ngày</label>
                    <input type="date" name="to" class="form-control" value="<?= $to_date ?>">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="bi bi-funnel"></i> Lọc
                    </button>
                    <a href="statistics.php" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Overview Stats -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card stat-card border-start border-primary border-4">
                <div class="card-body">
                    <h6 class="text-muted mb-2">Tổng doanh thu</h6>
                    <h3 class="text-primary mb-0"><?= formatPrice($stats['total_revenue'] ?? 0) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card border-start border-success border-4">
                <div class="card-body">
                    <h6 class="text-muted mb-2">Đơn hàng</h6>
                    <h3 class="text-success mb-0"><?= number_format($stats['total_orders'] ?? 0) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card border-start border-info border-4">
                <div class="card-body">
                    <h6 class="text-muted mb-2">Giá trị TB/đơn</h6>
                    <h3 class="text-info mb-0"><?= formatPrice($stats['avg_order_value'] ?? 0) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card border-start border-warning border-4">
                <div class="card-body">
                    <h6 class="text-muted mb-2">Khách hàng</h6>
                    <h3 class="text-warning mb-0"><?= number_format($stats['total_customers'] ?? 0) ?></h3>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Revenue Chart -->
        <div class="col-lg-8 mb-4">
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Biểu đồ doanh thu</h5>
                </div>
                <div class="card-body">
                    <canvas id="revenueChart" height="80"></canvas>
                </div>
            </div>
        </div>

        <!-- Order Status -->
        <div class="col-lg-4 mb-4">
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Đơn hàng theo trạng thái</h5>
                </div>
                <div class="card-body">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Best Sellers -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Sản phẩm bán chạy</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Sản phẩm</th>
                                    <th>Đã bán</th>
                                    <th>Doanh thu</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($top_products)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">
                                        Chưa có dữ liệu
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($top_products as $index => $p): ?>
                                    <tr>
                                        <td><?= $index + 1 ?></td>
                                        <td>
                                            <a href="<?= BASE_URL ?>product_detail.php?slug=<?= $p['slug'] ?>" 
                                               target="_blank" class="text-decoration-none">
                                                <?= htmlspecialchars($p['name']) ?>
                                            </a>
                                        </td>
                                        <td><span class="badge bg-info"><?= $p['total_sold'] ?></span></td>
                                        <td class="fw-bold text-primary"><?= formatPrice($p['revenue']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Customers -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Khách hàng thân thiết</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Khách hàng</th>
                                    <th>Đơn hàng</th>
                                    <th>Tổng chi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($top_buyers)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">
                                        Chưa có dữ liệu
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($top_buyers as $index => $c): ?>
                                    <tr>
                                        <td><?= $index + 1 ?></td>
                                        <td>
                                            <div><?= htmlspecialchars($c['full_name']) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($c['email']) ?></small>
                                        </td>
                                        <td><span class="badge bg-info"><?= $c['order_count'] ?></span></td>
                                        <td class="fw-bold text-success"><?= formatPrice($c['total_spent']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Revenue Chart
const revenueCtx = document.getElementById('revenueChart');
new Chart(revenueCtx, {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_map(fn($d) => date('d/m', strtotime($d['date'])), $daily_data)) ?>,
        datasets: [{
            label: 'Doanh thu',
            data: <?= json_encode(array_map(fn($d) => $d['revenue'], $daily_data)) ?>,
            backgroundColor: 'rgba(37, 99, 235, 0.8)',
            borderColor: 'rgb(37, 99, 235)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false },
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

// Status Chart
const statusCtx = document.getElementById('statusChart');
new Chart(statusCtx, {
    type: 'doughnut',
    data: {
        labels: ['Chờ xác nhận', 'Đã xác nhận', 'Đang giao', 'Hoàn thành', 'Đã hủy'],
        datasets: [{
            data: [
                <?= $status_data['pending'] ?? 0 ?>,
                <?= $status_data['confirmed'] ?? 0 ?>,
                <?= $status_data['shipping'] ?? 0 ?>,
                <?= $status_data['completed'] ?? 0 ?>,
                <?= $status_data['cancelled'] ?? 0 ?>
            ],
            backgroundColor: [
                'rgba(255, 193, 7, 0.8)',
                'rgba(13, 202, 240, 0.8)',
                'rgba(37, 99, 235, 0.8)',
                'rgba(25, 135, 84, 0.8)',
                'rgba(220, 53, 69, 0.8)'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});
</script>

<?php include 'includes/admin_footer.php'; ?>