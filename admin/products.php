<?php
require_once '../config.php';
requireAdmin();

// Xử lý xóa sản phẩm
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    try {
        $conn->beginTransaction();
        
        // Xóa hình ảnh
        $stmt = $conn->prepare("SELECT image_url FROM product_images WHERE product_id = ?");
        $stmt->execute([$id]);
        $images = $stmt->fetchAll();
        
        foreach ($images as $img) {
            $file_path = UPLOAD_DIR . $img['image_url'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
        
        // Xóa sản phẩm (cascade sẽ xóa các bảng liên quan)
        $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$id]);
        
        $conn->commit();
        flashMessage('success', 'Xóa sản phẩm thành công');
    } catch (Exception $e) {
        $conn->rollBack();
        flashMessage('error', 'Lỗi: ' . $e->getMessage());
    }
    
    redirect(ADMIN_URL . 'products.php');
}

// Lọc và tìm kiếm
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$status = $_GET['status'] ?? '';

$where = ["1=1"];
$params = [];

if ($search) {
    $where[] = "(p.name LIKE ? OR p.sku LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category) {
    $where[] = "p.category_id = ?";
    $params[] = $category;
}

if ($status) {
    $where[] = "p.status = ?";
    $params[] = $status;
}

$where_sql = implode(' AND ', $where);

// Phân trang
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Đếm tổng
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM products p WHERE $where_sql");
$stmt->execute($params);
$total = $stmt->fetch()['total'];
$total_pages = ceil($total / $per_page);

// Lấy danh sách
$stmt = $conn->prepare("
    SELECT p.*, c.name as category_name, b.name as brand_name,
           (SELECT image_url FROM product_images WHERE product_id = p.id AND is_main = 1 LIMIT 1) as image,
           (SELECT SUM(stock_quantity) FROM product_variants WHERE product_id = p.id) as total_stock
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN brands b ON p.brand_id = b.id
    WHERE $where_sql
    ORDER BY p.created_at DESC
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$products = $stmt->fetchAll();

// Lấy danh mục
$categories = $conn->query("SELECT * FROM categories ORDER BY name")->fetchAll();

$pageTitle = 'Quản lý sản phẩm';
include 'includes/admin_header.php';
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Quản lý sản phẩm</h2>
        <a href="product_form.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Thêm sản phẩm
        </a>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control" 
                           placeholder="Tìm theo tên hoặc SKU..." 
                           value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-3">
                    <select name="category" class="form-select">
                        <option value="">Tất cả danh mục</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $category == $cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select">
                        <option value="">Tất cả trạng thái</option>
                        <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Hoạt động</option>
                        <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Không hoạt động</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="bi bi-search"></i> Tìm kiếm
                    </button>
                    <a href="products.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-clockwise"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Products Table -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 80px;">Ảnh</th>
                            <th>Sản phẩm</th>
                            <th>Danh mục</th>
                            <th>Giá</th>
                            <th style="width: 100px;">Tồn kho</th>
                            <th style="width: 120px;">Trạng thái</th>
                            <th style="width: 150px;">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                Không có sản phẩm nào
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($products as $p): ?>
                            <tr>
                                <td>
                                    <?php if ($p['image']): ?>
                                        <img src="<?= UPLOAD_URL . $p['image'] ?>" 
                                             alt="<?= htmlspecialchars($p['name']) ?>"
                                             style="width: 60px; height: 60px; object-fit: cover;"
                                             class="rounded">
                                    <?php else: ?>
                                        <div style="width: 60px; height: 60px;" 
                                             class="bg-light rounded d-flex align-items-center justify-content-center">
                                            <i class="bi bi-image text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div>
                                        <strong><?= htmlspecialchars($p['name']) ?></strong>
                                        <?php if ($p['featured']): ?>
                                            <span class="badge bg-warning text-dark ms-1">Nổi bật</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($p['sku']): ?>
                                        <small class="text-muted">SKU: <?= htmlspecialchars($p['sku']) ?></small>
                                    <?php endif; ?>
                                    <div>
                                        <small class="text-muted">
                                            <?= htmlspecialchars($p['brand_name'] ?? '') ?>
                                        </small>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($p['category_name'] ?? '') ?></td>
                                <td>
                                    <?php if ($p['sale_price']): ?>
                                        <div class="text-danger fw-bold"><?= formatPrice($p['sale_price']) ?></div>
                                        <small class="text-decoration-line-through text-muted">
                                            <?= formatPrice($p['price']) ?>
                                        </small>
                                    <?php else: ?>
                                        <div class="fw-bold"><?= formatPrice($p['price']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $p['total_stock'] > 10 ? 'success' : ($p['total_stock'] > 0 ? 'warning' : 'danger') ?>">
                                        <?= $p['total_stock'] ?? 0 ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($p['status'] === 'active'): ?>
                                        <span class="badge bg-success">Hoạt động</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Ẩn</span>
                                    <?php endif; ?>
                                </td>
                                <td class="table-actions">
                                    <a href="<?= BASE_URL ?>product_detail.php?slug=<?= $p['slug'] ?>" 
                                       class="btn btn-sm btn-outline-info" target="_blank" title="Xem">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="product_form.php?id=<?= $p['id'] ?>" 
                                       class="btn btn-sm btn-outline-primary" title="Sửa">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="?delete=<?= $p['id'] ?>" 
                                       class="btn btn-sm btn-outline-danger" 
                                       title="Xóa"
                                       data-confirm="Bạn có chắc muốn xóa sản phẩm này?">
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
                <small>Hiển thị <?= count($products) ?> / <?= $total ?> sản phẩm</small>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/admin_footer.php'; ?>