<?php
require_once '../config.php';
requireAdmin();

// Xử lý duyệt/từ chối
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $action = $_GET['action'];
    
    if (in_array($action, ['approve', 'reject'])) {
        $status = $action === 'approve' ? 'approved' : 'rejected';
        $stmt = $conn->prepare("UPDATE reviews SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        
        flashMessage('success', $action === 'approve' ? 'Đã duyệt đánh giá' : 'Đã từ chối đánh giá');
    }
    
    redirect(ADMIN_URL . 'reviews.php');
}

// Xử lý xóa
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->prepare("DELETE FROM reviews WHERE id = ?")->execute([$id]);
    flashMessage('success', 'Đã xóa đánh giá');
    redirect(ADMIN_URL . 'reviews.php');
}

// Lọc
$status_filter = $_GET['status'] ?? '';
$rating_filter = $_GET['rating'] ?? '';

$where = ["1=1"];
$params = [];

if ($status_filter) {
    $where[] = "r.status = ?";
    $params[] = $status_filter;
}

if ($rating_filter) {
    $where[] = "r.rating = ?";
    $params[] = $rating_filter;
}

$where_sql = implode(' AND ', $where);

// Phân trang
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Đếm tổng
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM reviews r WHERE $where_sql");
$stmt->execute($params);
$total = $stmt->fetch()['total'];
$total_pages = ceil($total / $per_page);

// Lấy danh sách đánh giá
$stmt = $conn->prepare("
    SELECT r.*, u.full_name, p.name as product_name, p.slug as product_slug
    FROM reviews r
    JOIN users u ON r.user_id = u.id
    JOIN products p ON r.product_id = p.id
    WHERE $where_sql
    ORDER BY r.created_at DESC
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$reviews = $stmt->fetchAll();

// Thống kê
$stats = $conn->query("
    SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
        COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved,
        COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected,
        AVG(rating) as avg_rating
    FROM reviews
")->fetch();

$pageTitle = 'Quản lý đánh giá';
include 'includes/admin_header.php';
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Quản lý đánh giá</h2>
    </div>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted mb-2">Tổng đánh giá</h6>
                            <h3><?= number_format($stats['total']) ?></h3>
                        </div>
                        <i class="bi bi-star fs-1 text-warning opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted mb-2">Chờ duyệt</h6>
                            <h3 class="text-warning"><?= number_format($stats['pending']) ?></h3>
                        </div>
                        <i class="bi bi-clock-history fs-1 text-warning opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted mb-2">Đã duyệt</h6>
                            <h3 class="text-success"><?= number_format($stats['approved']) ?></h3>
                        </div>
                        <i class="bi bi-check-circle fs-1 text-success opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted mb-2">Đánh giá TB</h6>
                            <h3 class="text-info"><?= number_format($stats['avg_rating'], 1) ?> ⭐</h3>
                        </div>
                        <i class="bi bi-star-fill fs-1 text-info opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <select name="status" class="form-select">
                        <option value="">Tất cả trạng thái</option>
                        <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Chờ duyệt</option>
                        <option value="approved" <?= $status_filter === 'approved' ? 'selected' : '' ?>>Đã duyệt</option>
                        <option value="rejected" <?= $status_filter === 'rejected' ? 'selected' : '' ?>>Từ chối</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <select name="rating" class="form-select">
                        <option value="">Tất cả đánh giá</option>
                        <option value="5" <?= $rating_filter === '5' ? 'selected' : '' ?>>5 sao</option>
                        <option value="4" <?= $rating_filter === '4' ? 'selected' : '' ?>>4 sao</option>
                        <option value="3" <?= $rating_filter === '3' ? 'selected' : '' ?>>3 sao</option>
                        <option value="2" <?= $rating_filter === '2' ? 'selected' : '' ?>>2 sao</option>
                        <option value="1" <?= $rating_filter === '1' ? 'selected' : '' ?>>1 sao</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="bi bi-filter"></i> Lọc
                    </button>
                    <a href="reviews.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-clockwise"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Reviews List -->
    <div class="row g-4">
        <?php if (empty($reviews)): ?>
        <div class="col-12">
            <div class="card">
                <div class="card-body text-center py-5 text-muted">
                    <i class="bi bi-chat-square-text fs-1 d-block mb-2"></i>
                    Không có đánh giá nào
                </div>
            </div>
        </div>
        <?php else: ?>
            <?php foreach ($reviews as $review): ?>
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="d-flex align-items-center">
                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3"
                                     style="width: 50px; height: 50px;">
                                    <strong><?= strtoupper(substr($review['full_name'], 0, 1)) ?></strong>
                                </div>
                                <div>
                                    <strong><?= htmlspecialchars($review['full_name']) ?></strong>
                                    <div class="text-muted small"><?= formatDate($review['created_at']) ?></div>
                                </div>
                            </div>
                            <span class="badge bg-<?= match($review['status']) {
                                'pending' => 'warning',
                                'approved' => 'success',
                                'rejected' => 'danger',
                                default => 'secondary'
                            } ?>">
                                <?= match($review['status']) {
                                    'pending' => 'Chờ duyệt',
                                    'approved' => 'Đã duyệt',
                                    'rejected' => 'Từ chối',
                                    default => $review['status']
                                } ?>
                            </span>
                        </div>

                        <div class="mb-2">
                            <a href="<?= BASE_URL ?>product_detail.php?slug=<?= $review['product_slug'] ?>" 
                               target="_blank" class="text-decoration-none">
                                <strong><?= htmlspecialchars($review['product_name']) ?></strong>
                            </a>
                        </div>

                        <div class="mb-3">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="bi bi-star<?= $i <= $review['rating'] ? '-fill' : '' ?> text-warning"></i>
                            <?php endfor; ?>
                            <span class="ms-2 fw-bold"><?= $review['rating'] ?>/5</span>
                        </div>

                        <?php if ($review['comment']): ?>
                        <div class="mb-3">
                            <p class="mb-0"><?= nl2br(htmlspecialchars($review['comment'])) ?></p>
                        </div>
                        <?php endif; ?>

                        <div class="d-flex gap-2">
                            <?php if ($review['status'] === 'pending'): ?>
                                <a href="?action=approve&id=<?= $review['id'] ?>" 
                                   class="btn btn-sm btn-success">
                                    <i class="bi bi-check-circle"></i> Duyệt
                                </a>
                                <a href="?action=reject&id=<?= $review['id'] ?>" 
                                   class="btn btn-sm btn-danger">
                                    <i class="bi bi-x-circle"></i> Từ chối
                                </a>
                            <?php else: ?>
                                <a href="?action=approve&id=<?= $review['id'] ?>" 
                                   class="btn btn-sm btn-outline-success">
                                    <i class="bi bi-check-circle"></i> Duyệt lại
                                </a>
                            <?php endif; ?>
                            <a href="?delete=<?= $review['id'] ?>" 
                               class="btn btn-sm btn-outline-danger ms-auto"
                               data-confirm="Xóa đánh giá này?">
                                <i class="bi bi-trash"></i> Xóa
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

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
</div>

<?php include 'includes/admin_footer.php'; ?>