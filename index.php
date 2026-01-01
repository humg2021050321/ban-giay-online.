<?php
require_once 'config.php';

// Lấy danh sách sản phẩm nổi bật
$stmt = $conn->query("
    SELECT p.*, b.name as brand_name, c.name as category_name,
           (SELECT image_url FROM product_images WHERE product_id = p.id AND is_main = 1 LIMIT 1) as image
    FROM products p
    LEFT JOIN brands b ON p.brand_id = b.id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.status = 'active' AND p.featured = 1
    ORDER BY p.created_at DESC
    LIMIT 8
");
$featuredProducts = $stmt->fetchAll();

// Lấy sản phẩm mới nhất
$stmt = $conn->query("
    SELECT p.*, b.name as brand_name, c.name as category_name,
           (SELECT image_url FROM product_images WHERE product_id = p.id AND is_main = 1 LIMIT 1) as image
    FROM products p
    LEFT JOIN brands b ON p.brand_id = b.id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.status = 'active'
    ORDER BY p.created_at DESC
    LIMIT 12
");
$newProducts = $stmt->fetchAll();

// Lấy danh mục với ảnh
$categories = $conn->query("SELECT * FROM categories WHERE status = 'active'")->fetchAll();
// Tự động fix đường dẫn ảnh cho categories
foreach ($categories as &$cat) {
    if (!empty($cat['image']) && strpos($cat['image'], 'uploads/') !== 0) {
        $cat['image'] = 'uploads/' . $cat['image'];
        // Cập nhật vào database luôn
        $stmt = $conn->prepare("UPDATE categories SET image = ? WHERE id = ?");
        $stmt->execute([$cat['image'], $cat['id']]);
    }
}
unset($cat);

// Lấy thương hiệu với ảnh
$brands = $conn->query("SELECT * FROM brands WHERE status = 'active' LIMIT 6")->fetchAll();
// Tự động fix đường dẫn logo cho brands
foreach ($brands as &$brand) {
    if (!empty($brand['logo']) && strpos($brand['logo'], 'uploads/') !== 0) {
        $brand['logo'] = 'uploads/' . $brand['logo'];
        // Cập nhật vào database luôn
        $stmt = $conn->prepare("UPDATE brands SET logo = ? WHERE id = ?");
        $stmt->execute([$brand['logo'], $brand['id']]);
    }
}
unset($brand);

// Lấy banner từ database (nếu có bảng banners)
// Nếu chưa có, bạn có thể tạo bảng banners hoặc dùng ảnh tĩnh
$banners = [];
// Kiểm tra xem có bảng banners không
try {
    $stmt = $conn->query("SELECT * FROM banners WHERE status = 'active' ORDER BY sort_order LIMIT 3");
    $banners = $stmt->fetchAll();
} catch (Exception $e) {
    // Nếu chưa có bảng banners, dùng banner mặc định
    $banners = [
        ['image' => 'assets/images/banner1.jpg', 'title' => 'Bộ sưu tập mùa hè 2025', 'description' => 'Giảm giá lên đến 50% cho tất cả sản phẩm', 'link' => 'products.php', 'bg_class' => 'bg-primary'],
        ['image' => 'assets/images/banner2.jpg', 'title' => 'Giày thể thao chính hãng', 'description' => 'Đa dạng mẫu mã, chất lượng đảm bảo', 'link' => 'products.php?category=giay-the-thao', 'bg_class' => 'bg-dark'],
        ['image' => 'assets/images/banner3.jpg', 'title' => 'Miễn phí vận chuyển', 'description' => 'Cho đơn hàng từ 500.000đ', 'link' => 'products.php', 'bg_class' => 'bg-success']
    ];
}

include 'includes/header.php';
?>

<div class="container mt-4">
    <?php 
    $flash = getFlashMessage();
    if ($flash): 
    ?>
        <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show">
            <?= $flash['message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Banner Slider -->
    <div id="heroCarousel" class="carousel slide mb-5" data-bs-ride="carousel">
        <div class="carousel-indicators">
            <?php foreach ($banners as $index => $banner): ?>
                <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="<?= $index ?>" 
                        class="<?= $index === 0 ? 'active' : '' ?>"></button>
            <?php endforeach; ?>
        </div>
        <div class="carousel-inner rounded">
            <?php foreach ($banners as $index => $banner): ?>
                <div class="carousel-item <?= $index === 0 ? 'active' : '' ?>">
                    <div class="banner-slide" style="background-image: url('<?= isset($banner['image']) ? htmlspecialchars($banner['image']) : 'assets/images/default-banner.jpg' ?>'); min-height: 400px; background-size: cover; background-position: center;">
                        <div class="banner-overlay"></div>
                        <div class="banner-content container text-white position-relative">
                            <h1 class="display-4 fw-bold"><?= htmlspecialchars($banner['title']) ?></h1>
                            <p class="lead"><?= htmlspecialchars($banner['description']) ?></p>
                            <a href="<?= htmlspecialchars($banner['link']) ?>" class="btn btn-light btn-lg">
                                <?= $index === 0 ? 'Mua ngay' : ($index === 1 ? 'Khám phá' : 'Xem sản phẩm') ?>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev">
            <span class="carousel-control-prev-icon"></span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next">
            <span class="carousel-control-next-icon"></span>
        </button>
    </div>

    <!-- Danh mục nổi bật -->
    <section class="mb-5">
        <h2 class="text-center mb-4">Danh mục sản phẩm</h2>
        <div class="row g-4 justify-content-center">
            <?php foreach ($categories as $cat): ?>
            <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                <a href="products.php?category=<?= $cat['slug'] ?>" class="text-decoration-none">
                    <div class="card h-100 text-center hover-shadow category-card">
                        <?php if (!empty($cat['image'])): ?>
                            <img src="<?= htmlspecialchars($cat['image']) ?>" 
                                 alt="<?= htmlspecialchars($cat['name']) ?>" 
                                 class="category-image">
                            <h6 class="card-title mb-0"><?= htmlspecialchars($cat['name']) ?></h6>
                        <?php else: ?>
                            <div class="card-body d-flex flex-column align-items-center justify-content-center py-4">
                                <i class="bi bi-bag fs-1 text-primary mb-3"></i>
                                <h6 class="card-title mb-0"><?= htmlspecialchars($cat['name']) ?></h6>
                            </div>
                        <?php endif; ?>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Sản phẩm nổi bật -->
    <?php if (!empty($featuredProducts)): ?>
    <section class="mb-5">
        <h2 class="text-center mb-4">Sản phẩm nổi bật</h2>
        <div class="row g-4">
            <?php foreach ($featuredProducts as $product): ?>
            <div class="col-md-6 col-lg-3">
                <?php include 'includes/product_card.php'; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Thương hiệu -->
    <section class="mb-5">
        <h2 class="text-center mb-4">Thương hiệu</h2>
        <div class="row g-3 text-center">
            <?php foreach ($brands as $brand): ?>
            <div class="col-4 col-md-2">
                <a href="products.php?brand=<?= $brand['slug'] ?>" class="text-decoration-none">
                    <div class="card h-100 hover-shadow brand-card">
                        <div class="card-body d-flex align-items-center justify-content-center p-3">
                            <?php if (!empty($brand['logo'])): ?>
                                <img src="<?= htmlspecialchars($brand['logo']) ?>" 
                                     alt="<?= htmlspecialchars($brand['name']) ?>" 
                                     class="brand-logo">
                            <?php else: ?>
                                <h6 class="mb-0"><?= htmlspecialchars($brand['name']) ?></h6>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Sản phẩm mới -->
    <section class="mb-5">
        <h2 class="text-center mb-4">Sản phẩm mới</h2>
        <div class="row g-4">
            <?php foreach ($newProducts as $product): ?>
            <div class="col-md-6 col-lg-3">
                <?php include 'includes/product_card.php'; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-4">
            <a href="products.php" class="btn btn-primary btn-lg">Xem tất cả sản phẩm</a>
        </div>
    </section>

    <!-- Ưu điểm -->
    <section class="mb-5">
        <div class="row g-4 text-center">
            <div class="col-md-3">
                <i class="bi bi-truck fs-1 text-primary"></i>
                <h5 class="mt-2">Miễn phí vận chuyển</h5>
                <p class="text-muted">Đơn hàng từ 500.000đ</p>
            </div>
            <div class="col-md-3">
                <i class="bi bi-arrow-clockwise fs-1 text-primary"></i>
                <h5 class="mt-2">Đổi trả dễ dàng</h5>
                <p class="text-muted">Trong vòng 7 ngày</p>
            </div>
            <div class="col-md-3">
                <i class="bi bi-shield-check fs-1 text-primary"></i>
                <h5 class="mt-2">Sản phẩm chính hãng</h5>
                <p class="text-muted">100% authentic</p>
            </div>
            <div class="col-md-3">
                <i class="bi bi-headset fs-1 text-primary"></i>
                <h5 class="mt-2">Hỗ trợ 24/7</h5>
                <p class="text-muted">Luôn sẵn sàng hỗ trợ</p>
            </div>
        </div>
    </section>
</div>

<style>
.hover-shadow {
    transition: all 0.3s ease;
}
.hover-shadow:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}
.category-card {
    min-height: 150px;
    overflow: hidden;
}
.category-card:hover {
    border-color: var(--bs-primary);
}
.category-card .card-body {
    padding: 0;
    position: relative;
}
.category-image {
    width: 100%;
    height: 150px;
    object-fit: cover;
}
.category-card h6 {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: rgba(0, 0, 0, 0.7);
    color: white;
    margin: 0;
    padding: 10px;
    text-align: center;
}
.brand-card {
    min-height: 100px;
}
.brand-logo {
    max-width: 100%;
    max-height: 60px;
    object-fit: contain;
}
.banner-slide {
    position: relative;
    display: flex;
    align-items: center;
}
.banner-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.4);
    border-radius: inherit;
}
.banner-content {
    z-index: 1;
    padding: 3rem 1rem;
}
</style>

<?php include 'includes/footer.php'; ?>