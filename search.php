<?php
require_once 'config.php';

$keyword = isset($_GET['q']) ? trim($_GET['q']) : '';

$products = [];
$total = 0;

if (strlen($keyword) >= 2) {
    $search_term = "%$keyword%";
    
    // Đếm tổng số kết quả
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM products p
        LEFT JOIN brands b ON p.brand_id = b.id
        WHERE p.status = 'active' 
        AND (p.name LIKE ? OR p.sku LIKE ? OR b.name LIKE ?)
    ");
    $stmt->execute([$search_term, $search_term, $search_term]);
    $total = $stmt->fetch()['total'];
    
    // Lấy kết quả
    $stmt = $conn->prepare("
        SELECT p.*, b.name as brand_name, c.name as category_name,
               (SELECT image_url FROM product_images WHERE product_id = p.id AND is_main = 1 LIMIT 1) as image
        FROM products p
        LEFT JOIN brands b ON p.brand_id = b.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.status = 'active' 
        AND (p.name LIKE ? OR p.sku LIKE ? OR b.name LIKE ?)
        ORDER BY p.name ASC
        LIMIT 50
    ");
    $stmt->execute([$search_term, $search_term, $search_term]);
    $products = $stmt->fetchAll();
}

$pageTitle = 'Tìm kiếm: ' . htmlspecialchars($keyword);
include 'includes/header.php';
?>

<div class="container mt-4 mb-5">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>">Trang chủ</a></li>
            <li class="breadcrumb-item active">Tìm kiếm</li>
        </ol>
    </nav>

    <?php if (!empty($keyword)): ?>
        <?php if (strlen($keyword) < 2): ?>
            <div class="alert alert-warning text-center">
                <i class="bi bi-exclamation-triangle fs-3 d-block mb-2"></i>
                Vui lòng nhập ít nhất 2 ký tự để tìm kiếm
            </div>
        <?php elseif (empty($products)): ?>
            <div class="alert alert-info text-center">
                <i class="bi bi-search fs-3 d-block mb-2"></i>
                <h5>Không tìm thấy kết quả nào</h5>
                <p class="mb-3">Không tìm thấy sản phẩm nào phù hợp với từ khóa "<strong><?= htmlspecialchars($keyword) ?></strong>"</p>
                <p class="mb-0">Gợi ý:</p>
                <ul class="list-unstyled">
                    <li>Kiểm tra lại chính tả từ khóa</li>
                    <li>Thử sử dụng từ khóa khác</li>
                    <li>Thử sử dụng từ khóa chung chung hơn</li>
                </ul>
                <a href="<?= BASE_URL ?>products.php" class="btn btn-primary mt-3">
                    Xem tất cả sản phẩm
                </a>
            </div>
        <?php else: ?>
            <div class="mb-4">
                <h5>Tìm thấy <?= $total ?> kết quả cho "<strong><?= htmlspecialchars($keyword) ?></strong>"</h5>
            </div>

            <div class="row g-4">
                <?php foreach ($products as $product): ?>
                <div class="col-md-6 col-lg-3">
                    <?php include 'includes/product_card.php'; ?>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <!-- Sản phẩm gợi ý -->
        <div class="text-center mb-4">
            <h4>Sản phẩm gợi ý cho bạn</h4>
        </div>

        <?php
        $suggested = $conn->query("
            SELECT p.*, b.name as brand_name, c.name as category_name,
                   (SELECT image_url FROM product_images WHERE product_id = p.id AND is_main = 1 LIMIT 1) as image
            FROM products p
            LEFT JOIN brands b ON p.brand_id = b.id
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.status = 'active'
            ORDER BY p.view_count DESC, p.created_at DESC
            LIMIT 12
        ")->fetchAll();
        ?>

        <div class="row g-4">
            <?php foreach ($suggested as $product): ?>
            <div class="col-md-6 col-lg-3">
                <?php include 'includes/product_card.php'; ?>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.hover-shadow {
    transition: all 0.3s ease;
}
.hover-shadow:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}
</style>

<?php include 'includes/footer.php'; ?>