<?php
require_once 'config.php';

// Lấy tham số lọc
$category_slug = isset($_GET['category']) ? $_GET['category'] : '';
$brand_slug = isset($_GET['brand']) ? $_GET['brand'] : '';
$min_price = isset($_GET['min_price']) ? (int)$_GET['min_price'] : 0;
$max_price = isset($_GET['max_price']) ? (int)$_GET['max_price'] : 10000000;
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Build query
$where = ["p.status = 'active'"];
$params = [];

if ($category_slug) {
    $where[] = "c.slug = ?";
    $params[] = $category_slug;
}

if ($brand_slug) {
    $where[] = "b.slug = ?";
    $params[] = $brand_slug;
}

if ($min_price > 0) {
    $where[] = "COALESCE(p.sale_price, p.price) >= ?";
    $params[] = $min_price;
}

if ($max_price < 10000000) {
    $where[] = "COALESCE(p.sale_price, p.price) <= ?";
    $params[] = $max_price;
}

$where_sql = implode(' AND ', $where);

// Sorting
$order_by = match($sort) {
    'price_asc' => 'COALESCE(p.sale_price, p.price) ASC',
    'price_desc' => 'COALESCE(p.sale_price, p.price) DESC',
    'name' => 'p.name ASC',
    'popular' => 'p.view_count DESC',
    default => 'p.created_at DESC'
};

// Đếm tổng số sản phẩm
$count_sql = "
    SELECT COUNT(*) as total
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN brands b ON p.brand_id = b.id
    WHERE $where_sql
";
$stmt = $conn->prepare($count_sql);
$stmt->execute($params);
$total = $stmt->fetch()['total'];
$total_pages = ceil($total / $per_page);

// Lấy danh sách sản phẩm
$sql = "
    SELECT p.*, b.name as brand_name, c.name as category_name,
           (SELECT image_url FROM product_images WHERE product_id = p.id AND is_main = 1 LIMIT 1) as image
    FROM products p
    LEFT JOIN brands b ON p.brand_id = b.id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE $where_sql
    ORDER BY $order_by
    LIMIT $per_page OFFSET $offset
";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Lấy danh mục và thương hiệu để hiển thị filter
$categories = $conn->query("SELECT * FROM categories WHERE status = 'active'")->fetchAll();
$brands = $conn->query("SELECT * FROM brands WHERE status = 'active'")->fetchAll();

// Lấy tên category/brand hiện tại
$current_category = '';
$current_brand = '';

if ($category_slug) {
    $stmt = $conn->prepare("SELECT name FROM categories WHERE slug = ?");
    $stmt->execute([$category_slug]);
    $cat = $stmt->fetch();
    $current_category = $cat ? $cat['name'] : '';
}

if ($brand_slug) {
    $stmt = $conn->prepare("SELECT name FROM brands WHERE slug = ?");
    $stmt->execute([$brand_slug]);
    $br = $stmt->fetch();
    $current_brand = $br ? $br['name'] : '';
}

$pageTitle = 'Sản phẩm';
if ($current_category) $pageTitle = $current_category;
if ($current_brand) $pageTitle = $current_brand;

include 'includes/header.php';
?>

<div class="container mt-4 mb-5">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>">Trang chủ</a></li>
            <li class="breadcrumb-item active">Sản phẩm</li>
            <?php if ($current_category): ?>
                <li class="breadcrumb-item active"><?= htmlspecialchars($current_category) ?></li>
            <?php endif; ?>
            <?php if ($current_brand): ?>
                <li class="breadcrumb-item active"><?= htmlspecialchars($current_brand) ?></li>
            <?php endif; ?>
        </ol>
    </nav>

    <div class="row">
        <!-- Sidebar Filter -->
        <div class="col-lg-3 mb-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-funnel"></i> Bộ lọc</h5>
                </div>
                <div class="card-body">
                    <form method="GET" action="" id="filterForm">
                        <!-- Danh mục -->
                        <div class="mb-4">
                            <h6 class="fw-bold">Danh mục</h6>
                            <div class="list-group">
                                <a href="products.php" class="list-group-item list-group-item-action <?= !$category_slug ? 'active' : '' ?>">
                                    Tất cả
                                </a>
                                <?php foreach ($categories as $cat): ?>
                                <a href="products.php?category=<?= $cat['slug'] ?><?= $brand_slug ? '&brand='.$brand_slug : '' ?>" 
                                   class="list-group-item list-group-item-action <?= $category_slug == $cat['slug'] ? 'active' : '' ?>">
                                    <?= htmlspecialchars($cat['name']) ?>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Thương hiệu -->
                        <div class="mb-4">
                            <h6 class="fw-bold">Thương hiệu</h6>
                            <?php foreach ($brands as $brand): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="brand" 
                                       value="<?= $brand['slug'] ?>" id="brand_<?= $brand['id'] ?>"
                                       <?= $brand_slug == $brand['slug'] ? 'checked' : '' ?>
                                       onchange="this.form.submit()">
                                <label class="form-check-label" for="brand_<?= $brand['id'] ?>">
                                    <?= htmlspecialchars($brand['name']) ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                            <?php if ($brand_slug): ?>
                            <button type="button" class="btn btn-sm btn-link p-0" onclick="document.querySelector('input[name=brand]:checked').checked=false; this.form.submit();">
                                Xóa lọc
                            </button>
                            <?php endif; ?>
                        </div>

                        <!-- Khoảng giá -->
                        <div class="mb-4">
                            <h6 class="fw-bold">Khoảng giá</h6>
                            <div class="mb-2">
                                <label class="form-label small">Từ:</label>
                                <input type="number" name="min_price" class="form-control form-control-sm" 
                                       value="<?= $min_price ?>" min="0" step="100000">
                            </div>
                            <div class="mb-2">
                                <label class="form-label small">Đến:</label>
                                <input type="number" name="max_price" class="form-control form-control-sm" 
                                       value="<?= $max_price ?>" min="0" step="100000">
                            </div>
                            <button type="submit" class="btn btn-sm btn-primary w-100">Áp dụng</button>
                        </div>

                        <input type="hidden" name="category" value="<?= $category_slug ?>">
                        <input type="hidden" name="sort" value="<?= $sort ?>">
                    </form>
                </div>
            </div>
        </div>

        <!-- Products List -->
        <div class="col-lg-9">
            <!-- Toolbar -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-0">
                        <?php if ($current_category || $current_brand): ?>
                            <?= htmlspecialchars($current_category ?: $current_brand) ?>
                        <?php else: ?>
                            Tất cả sản phẩm
                        <?php endif; ?>
                    </h4>
                    <small class="text-muted">Tìm thấy <?= $total ?> sản phẩm</small>
                </div>

                <div class="d-flex align-items-center">
                    <label class="me-2 small">Sắp xếp:</label>
                    <select class="form-select form-select-sm" style="width: auto;" onchange="location.href=this.value">
                        <option value="?<?= http_build_query(array_merge($_GET, ['sort' => 'newest'])) ?>" 
                                <?= $sort == 'newest' ? 'selected' : '' ?>>Mới nhất</option>
                        <option value="?<?= http_build_query(array_merge($_GET, ['sort' => 'popular'])) ?>" 
                                <?= $sort == 'popular' ? 'selected' : '' ?>>Phổ biến</option>
                        <option value="?<?= http_build_query(array_merge($_GET, ['sort' => 'price_asc'])) ?>" 
                                <?= $sort == 'price_asc' ? 'selected' : '' ?>>Giá tăng dần</option>
                        <option value="?<?= http_build_query(array_merge($_GET, ['sort' => 'price_desc'])) ?>" 
                                <?= $sort == 'price_desc' ? 'selected' : '' ?>>Giá giảm dần</option>
                        <option value="?<?= http_build_query(array_merge($_GET, ['sort' => 'name'])) ?>" 
                                <?= $sort == 'name' ? 'selected' : '' ?>>Tên A-Z</option>
                    </select>
                </div>
            </div>

            <?php if (empty($products)): ?>
                <div class="alert alert-info text-center">
                    <i class="bi bi-info-circle fs-1 d-block mb-2"></i>
                    <h5>Không tìm thấy sản phẩm nào</h5>
                    <p>Vui lòng thử lại với bộ lọc khác</p>
                    <a href="products.php" class="btn btn-primary">Xem tất cả sản phẩm</a>
                </div>
            <?php else: ?>
                <!-- Products Grid -->
                <div class="row g-4">
                    <?php foreach ($products as $product): ?>
                    <div class="col-md-6 col-lg-4">
                        <?php include 'includes/product_card.php'; ?>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <nav class="mt-5">
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
    </div>
</div>

<?php include 'includes/footer.php'; ?>