<?php
require_once '../config.php';
requireAdmin();

// Tìm kiếm và lọc
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';

$where = ["role_id = 2"]; // Chỉ lấy khách hàng
$params = [];

if ($search) {
    $where[] = "(full_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status_filter) {
    $where[] = "status = ?";
    $params[] = $status_filter;
}

$where_sql = implode(' AND ', $where);

// Phân trang
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Đếm tổng
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM users WHERE $where_sql");
$stmt->execute($params);
$total = $stmt->fetch()['total'];
$total_pages = ceil($total / $per_page);

// Lấy danh sách khách hàng kèm thống kê
$stmt = $conn->prepare("
    SELECT u.*,
           (SELECT COUNT(*) FROM orders WHERE user_id = u.id) as total_orders,
           (SELECT SUM(total_amount) FROM orders WHERE user_id = u.id AND order_status = 'completed') as total_spent,
           (SELECT MAX(created_at) FROM orders WHERE user_id = u.id) as last_order_date
    FROM users u
    WHERE $where_sql
    ORDER BY u.created_at DESC
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$customers = $stmt->fetchAll();

$pageTitle = 'Quản lý khách hàng';
include 'includes/admin_header.php';
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Quản lý khách hàng</h2>
    </div>

    <!-- Thống kê tổng quan -->
    <div class="row g-3 mb-4">
        <?php
        $stats = $conn->query("
            SELECT 
                COUNT(*) as total_customers,
                COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as new_customers,
                COUNT(CASE WHEN status = 'active' THEN 1 END) as active_customers,
                (SELECT COUNT(DISTINCT user_id) FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as active_buyers
            FROM users WHERE role_id = 2
        ")->fetch();
        ?>
        <div class="col-md-3">
            <div class="card stat-card border-start border-primary border-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Tổng khách hàng</h6>
                            <h3 class="mb-0"><?= number_format($stats['total_customers']) ?></h3>
                        </div>
                        <i class="bi bi-people fs-1 text-primary opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card border-start border-success border-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Mới (30 ngày)</h6>
                            <h3 class="mb-0"><?= number_format($stats['new_customers']) ?></h3>
                        </div>
                        <i class="bi bi-person-plus fs-1 text-success opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card border-start border-info border-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Hoạt động</h6>
                            <h3 class="mb-0"><?= number_format($stats['active_customers']) ?></h3>
                        </div>
                        <i class="bi bi-person-check fs-1 text-info opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card border-start border-warning border-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Đã mua (30 ngày)</h6>
                            <h3 class="mb-0"><?= number_format($stats['active_buyers']) ?></h3>
                        </div>
                        <i class="bi bi-bag-check fs-1 text-warning opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tìm kiếm & Lọc -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-6">
                    <input type="text" name="search" class="form-control" 
                           placeholder="Tìm theo tên, email, số điện thoại..." 
                           value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">Tất cả trạng thái</option>
                        <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Hoạt động</option>
                        <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Không hoạt động</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="bi bi-search"></i> Tìm kiếm
                    </button>
                    <a href="customers.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-clockwise"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Bảng khách hàng -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 60px;">ID</th>
                            <th>Khách hàng</th>
                            <th>Liên hệ</th>
                            <th style="width: 100px;">Đơn hàng</th>
                            <th style="width: 130px;">Tổng chi tiêu</th>
                            <th style="width: 140px;">Đơn cuối</th>
                            <th style="width: 140px;">Ngày đăng ký</th>
                            <th style="width: 100px;">Trạng thái</th>
                            <th style="width: 120px;">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($customers)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                <p>Không có khách hàng nào</p>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($customers as $c): ?>
                            <tr>
                                <td class="text-muted">#<?= $c['id'] ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2"
                                             style="width: 40px; height: 40px; flex-shrink: 0;">
                                            <strong><?= strtoupper(mb_substr($c['full_name'], 0, 1)) ?></strong>
                                        </div>
                                        <div>
                                            <strong><?= htmlspecialchars($c['full_name']) ?></strong>
                                            <?php if ($c['total_orders'] >= 10): ?>
                                                <span class="badge bg-warning text-dark ms-1" title="Khách hàng VIP">
                                                    <i class="bi bi-star-fill"></i> VIP
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <i class="bi bi-envelope"></i>
                                        <?= htmlspecialchars($c['email']) ?>
                                    </div>
                                    <?php if ($c['phone']): ?>
                                        <div class="text-muted small">
                                            <i class="bi bi-telephone"></i>
                                            <?= htmlspecialchars($c['phone']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($c['total_orders'] > 0): ?>
                                        <a href="orders.php?search=<?= urlencode($c['email']) ?>" 
                                           class="badge bg-info text-decoration-none">
                                            <?= $c['total_orders'] ?> đơn
                                        </a>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">0 đơn</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($c['total_spent']): ?>
                                        <strong class="text-success"><?= formatPrice($c['total_spent']) ?></strong>
                                    <?php else: ?>
                                        <span class="text-muted">0đ</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($c['last_order_date']): ?>
                                        <small class="text-muted"><?= formatDate($c['last_order_date']) ?></small>
                                    <?php else: ?>
                                        <small class="text-muted">Chưa mua</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small class="text-muted"><?= formatDate($c['created_at']) ?></small>
                                </td>
                                <td>
                                    <?php if ($c['status'] === 'active'): ?>
                                        <span class="badge bg-success">Hoạt động</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Khóa</span>
                                    <?php endif; ?>
                                </td>
                                <td class="table-actions">
                                    <button class="btn btn-sm btn-outline-info" 
                                            onclick="viewCustomerDetail(<?= $c['id'] ?>)" 
                                            title="Xem chi tiết">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <a href="orders.php?search=<?= urlencode($c['email']) ?>" 
                                       class="btn btn-sm btn-outline-primary" 
                                       title="Xem đơn hàng">
                                        <i class="bi bi-bag"></i>
                                    </a>
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
                <small>Hiển thị <?= count($customers) ?> / <?= $total ?> khách hàng</small>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Chi tiết khách hàng -->
<div class="modal fade" id="customerDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Chi tiết khách hàng</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="customerDetailContent">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function viewCustomerDetail(customerId) {
    const modal = new bootstrap.Modal(document.getElementById('customerDetailModal'));
    const content = document.getElementById('customerDetailContent');
    
    // Show loading
    content.innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
    `;
    
    modal.show();
    
    // Fetch customer details via AJAX
    fetch('get_customer_detail.php?id=' + customerId)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                content.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
                return;
            }
            
            content.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="fw-bold mb-3">Thông tin cá nhân</h6>
                        <table class="table table-sm">
                            <tr>
                                <td width="120"><strong>Họ tên:</strong></td>
                                <td>${data.full_name}</td>
                            </tr>
                            <tr>
                                <td><strong>Email:</strong></td>
                                <td>${data.email}</td>
                            </tr>
                            <tr>
                                <td><strong>Điện thoại:</strong></td>
                                <td>${data.phone || 'Chưa có'}</td>
                            </tr>
                            <tr>
                                <td><strong>Địa chỉ:</strong></td>
                                <td>${data.address || 'Chưa có'}</td>
                            </tr>
                            <tr>
                                <td><strong>Ngày đăng ký:</strong></td>
                                <td>${data.created_at}</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="fw-bold mb-3">Thống kê mua hàng</h6>
                        <table class="table table-sm">
                            <tr>
                                <td width="160"><strong>Tổng đơn hàng:</strong></td>
                                <td><span class="badge bg-info">${data.total_orders}</span></td>
                            </tr>
                            <tr>
                                <td><strong>Tổng chi tiêu:</strong></td>
                                <td><strong class="text-success">${data.total_spent}</strong></td>
                            </tr>
                            <tr>
                                <td><strong>Đơn hoàn thành:</strong></td>
                                <td>${data.completed_orders}</td>
                            </tr>
                            <tr>
                                <td><strong>Đơn bị hủy:</strong></td>
                                <td>${data.cancelled_orders}</td>
                            </tr>
                            <tr>
                                <td><strong>Giá trị TB/đơn:</strong></td>
                                <td>${data.avg_order_value}</td>
                            </tr>
                        </table>
                    </div>
                </div>
                <div class="text-end mt-3">
                    <a href="orders.php?search=${encodeURIComponent(data.email)}" class="btn btn-primary">
                        <i class="bi bi-bag"></i> Xem tất cả đơn hàng
                    </a>
                </div>
            `;
        })
        .catch(error => {
            content.innerHTML = `<div class="alert alert-danger">Lỗi tải dữ liệu: ${error.message}</div>`;
        });
}
</script>

<?php include 'includes/admin_footer.php'; ?>