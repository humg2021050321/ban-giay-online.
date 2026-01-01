<?php
require_once 'config.php';

$slug = $_GET['slug'] ?? '';

if (empty($slug)) {
    redirect(BASE_URL . 'products.php');
}

// Lấy thông tin sản phẩm
$stmt = $conn->prepare("
    SELECT p.*, b.name as brand_name, c.name as category_name
    FROM products p
    LEFT JOIN brands b ON p.brand_id = b.id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.slug = ? AND p.status = 'active'
");
$stmt->execute([$slug]);
$product = $stmt->fetch();

if (!$product) {
    flashMessage('error', 'Sản phẩm không tồn tại');
    redirect(BASE_URL . 'products.php');
}

// Cập nhật lượt xem
$conn->prepare("UPDATE products SET view_count = view_count + 1 WHERE id = ?")->execute([$product['id']]);

// Lấy hình ảnh sản phẩm
$images = $conn->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_main DESC, sort_order ASC");
$images->execute([$product['id']]);
$product_images = $images->fetchAll();

// Lấy variants (màu, size, tồn kho)
$stmt = $conn->prepare("
    SELECT pv.*, c.name as color_name, c.code as color_code, s.name as size_name
    FROM product_variants pv
    LEFT JOIN colors c ON pv.color_id = c.id
    LEFT JOIN sizes s ON pv.size_id = s.id
    WHERE pv.product_id = ?
    ORDER BY c.name, s.name
");
$stmt->execute([$product['id']]);
$variants = $stmt->fetchAll();

// Nhóm variants theo màu
$colors = [];
$sizes = [];
foreach ($variants as $v) {
    if ($v['color_name']) {
        $colors[$v['color_id']] = [
            'name' => $v['color_name'],
            'code' => $v['color_code']
        ];
    }
    if ($v['size_name']) {
        $sizes[$v['size_id']] = $v['size_name'];
    }
}

// Xử lý thêm vào giỏ hàng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    requireLogin();
    
    $variant_id = (int)$_POST['variant_id'];
    $quantity = max(1, (int)$_POST['quantity']);
    
    // Kiểm tra tồn kho
    $stmt = $conn->prepare("SELECT stock_quantity FROM product_variants WHERE id = ?");
    $stmt->execute([$variant_id]);
    $variant = $stmt->fetch();
    
    if (!$variant || $variant['stock_quantity'] < $quantity) {
        flashMessage('error', 'Sản phẩm không đủ số lượng');
    } else {
        // Kiểm tra đã có trong giỏ chưa
        $stmt = $conn->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_variant_id = ?");
        $stmt->execute([$_SESSION['user_id'], $variant_id]);
        $cart_item = $stmt->fetch();
        
        if ($cart_item) {
            // Cập nhật số lượng
            $new_qty = $cart_item['quantity'] + $quantity;
            if ($new_qty > $variant['stock_quantity']) {
                flashMessage('error', 'Vượt quá số lượng tồn kho');
            } else {
                $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
                $stmt->execute([$new_qty, $cart_item['id']]);
                flashMessage('success', 'Đã cập nhật số lượng trong giỏ hàng');
            }
        } else {
            // Thêm mới
            $stmt = $conn->prepare("INSERT INTO cart (user_id, product_variant_id, quantity) VALUES (?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $variant_id, $quantity]);
            flashMessage('success', 'Đã thêm vào giỏ hàng');
        }
    }
    redirect(BASE_URL . 'product_detail.php?slug=' . $slug);
}

// Lấy đánh giá
$reviews = $conn->prepare("
    SELECT r.*, u.full_name 
    FROM reviews r
    JOIN users u ON r.user_id = u.id
    WHERE r.product_id = ? AND r.status = 'approved'
    ORDER BY r.created_at DESC
");
$reviews->execute([$product['id']]);
$product_reviews = $reviews->fetchAll();

// Tính đánh giá trung bình
$stmt = $conn->prepare("
    SELECT AVG(rating) as avg_rating, COUNT(*) as review_count 
    FROM reviews 
    WHERE product_id = ? AND status = 'approved'
");
$stmt->execute([$product['id']]);
$rating_info = $stmt->fetch();

// Sản phẩm liên quan
$related = $conn->prepare("
    SELECT p.*, 
           (SELECT image_url FROM product_images WHERE product_id = p.id AND is_main = 1 LIMIT 1) as image
    FROM products p
    WHERE p.category_id = ? AND p.id != ? AND p.status = 'active'
    ORDER BY RAND()
    LIMIT 4
");
$related->execute([$product['category_id'], $product['id']]);
$related_products = $related->fetchAll();

$pageTitle = $product['name'];
include 'includes/header.php';
?>

<div class="container mt-4 mb-5">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>">Trang chủ</a></li>
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>products.php">Sản phẩm</a></li>
            <?php if ($product['category_name']): ?>
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>products.php?category=<?= $product['category_id'] ?>"><?= htmlspecialchars($product['category_name']) ?></a></li>
            <?php endif; ?>
            <li class="breadcrumb-item active"><?= htmlspecialchars($product['name']) ?></li>
        </ol>
    </nav>

    <?php 
    $flash = getFlashMessage();
    if ($flash): 
    ?>
        <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show">
            <?= $flash['message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Product Images -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-body">
                    <?php if (!empty($product_images)): ?>
                        <div id="productImages" class="carousel slide" data-bs-ride="carousel">
                            <div class="carousel-inner">
                                <?php foreach ($product_images as $index => $img): ?>
                                <div class="carousel-item <?= $index === 0 ? 'active' : '' ?>">
                                    <img src="<?= UPLOAD_URL . $img['image_url'] ?>" 
                                         class="d-block w-100 rounded" 
                                         alt="<?= htmlspecialchars($product['name']) ?>"
                                         style="height: 500px; object-fit: contain;">
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php if (count($product_images) > 1): ?>
                            <button class="carousel-control-prev" type="button" data-bs-target="#productImages" data-bs-slide="prev">
                                <span class="carousel-control-prev-icon bg-dark rounded-circle p-3"></span>
                            </button>
                            <button class="carousel-control-next" type="button" data-bs-target="#productImages" data-bs-slide="next">
                                <span class="carousel-control-next-icon bg-dark rounded-circle p-3"></span>
                            </button>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Thumbnails -->
                        <?php if (count($product_images) > 1): ?>
                        <div class="d-flex gap-2 mt-3 justify-content-center">
                            <?php foreach ($product_images as $index => $img): ?>
                            <img src="<?= UPLOAD_URL . $img['image_url'] ?>" 
                                 class="border rounded cursor-pointer" 
                                 style="width: 80px; height: 80px; object-fit: cover; cursor: pointer;"
                                 onclick="document.querySelector('#productImages').carousel.to(<?= $index ?>)">
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="bg-light d-flex align-items-center justify-content-center" style="height: 500px;">
                            <i class="bi bi-image text-muted" style="font-size: 5rem;"></i>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Product Info -->
        <div class="col-lg-6">
            <div class="mb-3">
                <?php if ($product['brand_name']): ?>
                    <a href="<?= BASE_URL ?>products.php?brand=<?= $product['brand_id'] ?>" class="text-decoration-none">
                        <span class="badge bg-secondary"><?= htmlspecialchars($product['brand_name']) ?></span>
                    </a>
                <?php endif; ?>
                <?php if ($product['sku']): ?>
                    <span class="text-muted ms-2">SKU: <?= htmlspecialchars($product['sku']) ?></span>
                <?php endif; ?>
            </div>

            <h2 class="mb-3"><?= htmlspecialchars($product['name']) ?></h2>

            <!-- Rating -->
            <?php if ($rating_info['review_count'] > 0): ?>
            <div class="mb-3">
                <div class="d-flex align-items-center">
                    <?php
                    $avg = round($rating_info['avg_rating']);
                    for ($i = 1; $i <= 5; $i++):
                        echo $i <= $avg ? '<i class="bi bi-star-fill text-warning"></i>' : '<i class="bi bi-star text-warning"></i>';
                    endfor;
                    ?>
                    <span class="ms-2"><?= number_format($rating_info['avg_rating'], 1) ?></span>
                    <span class="text-muted ms-2">(<?= $rating_info['review_count'] ?> đánh giá)</span>
                </div>
            </div>
            <?php endif; ?>

            <!-- Price -->
            <div class="mb-4">
                <?php if ($product['sale_price']): ?>
                    <h3 class="text-danger mb-1"><?= formatPrice($product['sale_price']) ?></h3>
                    <p class="text-muted">
                        <span class="text-decoration-line-through"><?= formatPrice($product['price']) ?></span>
                        <span class="badge bg-danger ms-2">
                            -<?= round((($product['price'] - $product['sale_price']) / $product['price']) * 100) ?>%
                        </span>
                    </p>
                <?php else: ?>
                    <h3 class="text-primary"><?= formatPrice($product['price']) ?></h3>
                <?php endif; ?>
            </div>

            <!-- Short Description -->
            <?php if ($product['short_description']): ?>
            <div class="mb-4">
                <p class="lead"><?= nl2br(htmlspecialchars($product['short_description'])) ?></p>
            </div>
            <?php endif; ?>

            <!-- Add to Cart Form -->
            <form method="POST" id="addToCartForm">
                <input type="hidden" name="add_to_cart" value="1">
                <input type="hidden" name="variant_id" id="selectedVariant">

                <!-- Colors -->
                <?php if (!empty($colors)): ?>
                <div class="mb-3">
                    <label class="form-label fw-bold">Màu sắc:</label>
                    <div class="d-flex gap-2 flex-wrap">
                        <?php foreach ($colors as $color_id => $color): ?>
                        <button type="button" class="btn btn-outline-secondary color-btn" 
                                data-color-id="<?= $color_id ?>"
                                style="min-width: 100px;">
                            <?php if ($color['code']): ?>
                                <span class="d-inline-block rounded-circle me-1" 
                                      style="width: 20px; height: 20px; background: <?= $color['code'] ?>; border: 1px solid #ddd;"></span>
                            <?php endif; ?>
                            <?= htmlspecialchars($color['name']) ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Sizes -->
                <?php if (!empty($sizes)): ?>
                <div class="mb-3">
                    <label class="form-label fw-bold">Kích thước:</label>
                    <div class="d-flex gap-2 flex-wrap">
                        <?php foreach ($sizes as $size_id => $size_name): ?>
                        <button type="button" class="btn btn-outline-secondary size-btn" 
                                data-size-id="<?= $size_id ?>"
                                style="min-width: 60px;">
                            <?= htmlspecialchars($size_name) ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Quantity -->
                <div class="mb-3">
                    <label class="form-label fw-bold">Số lượng:</label>
                    <div class="input-group" style="max-width: 150px;">
                        <button class="btn btn-outline-secondary" type="button" onclick="changeQty(-1)">
                            <i class="bi bi-dash"></i>
                        </button>
                        <input type="number" class="form-control text-center" name="quantity" id="quantity" value="1" min="1" max="99">
                        <button class="btn btn-outline-secondary" type="button" onclick="changeQty(1)">
                            <i class="bi bi-plus"></i>
                        </button>
                    </div>
                    <small class="text-muted" id="stockInfo"></small>
                </div>

                <!-- Action Buttons -->
                <div class="d-grid gap-2 mb-3">
                    <?php if (isLoggedIn()): ?>
                        <button type="submit" class="btn btn-primary btn-lg" id="addToCartBtn" disabled>
                            <i class="bi bi-cart-plus"></i> Thêm vào giỏ hàng
                        </button>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" 
                           class="btn btn-primary btn-lg">
                            <i class="bi bi-box-arrow-in-right"></i> Đăng nhập để mua hàng
                        </a>
                    <?php endif; ?>
                    <button type="button" class="btn btn-outline-secondary">
                        <i class="bi bi-heart"></i> Thêm vào yêu thích
                    </button>
                </div>
            </form>

            <!-- Product Features -->
            <div class="card bg-light">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-6">
                            <i class="bi bi-shield-check text-primary"></i>
                            <small class="ms-2">Chính hãng 100%</small>
                        </div>
                        <div class="col-6">
                            <i class="bi bi-truck text-primary"></i>
                            <small class="ms-2">Miễn phí vận chuyển</small>
                        </div>
                        <div class="col-6">
                            <i class="bi bi-arrow-clockwise text-primary"></i>
                            <small class="ms-2">Đổi trả trong 7 ngày</small>
                        </div>
                        <div class="col-6">
                            <i class="bi bi-telephone text-primary"></i>
                            <small class="ms-2">Hỗ trợ 24/7</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Product Details Tabs -->
    <div class="row mt-5">
        <div class="col-12">
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#description">
                        Mô tả sản phẩm
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#reviews">
                        Đánh giá (<?= $rating_info['review_count'] ?>)
                    </button>
                </li>
            </ul>

            <div class="tab-content border border-top-0 p-4">
                <!-- Description -->
                <div class="tab-pane fade show active" id="description">
                    <?php if ($product['description']): ?>
                        <div class="product-description">
                            <?= nl2br(htmlspecialchars($product['description'])) ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">Chưa có mô tả chi tiết cho sản phẩm này.</p>
                    <?php endif; ?>
                </div>

                <!-- Reviews -->
                <div class="tab-pane fade" id="reviews">
                    <?php if (!empty($product_reviews)): ?>
                        <?php foreach ($product_reviews as $review): ?>
                        <div class="border-bottom pb-3 mb-3">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <strong><?= htmlspecialchars($review['full_name']) ?></strong>
                                    <div class="mt-1">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="bi bi-star<?= $i <= $review['rating'] ? '-fill' : '' ?> text-warning"></i>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <small class="text-muted"><?= formatDate($review['created_at']) ?></small>
                            </div>
                            <?php if ($review['comment']): ?>
                                <p class="mb-0"><?= nl2br(htmlspecialchars($review['comment'])) ?></p>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted">Chưa có đánh giá nào cho sản phẩm này.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Related Products -->
    <?php if (!empty($related_products)): ?>
    <div class="mt-5">
        <h3 class="mb-4">Sản phẩm liên quan</h3>
        <div class="row g-4">
            <?php foreach ($related_products as $product): ?>
            <div class="col-md-6 col-lg-3">
                <?php include 'includes/product_card.php'; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
const variants = <?= json_encode($variants) ?>;
let selectedColor = null;
let selectedSize = null;

function updateVariantSelection() {
    const colorBtns = document.querySelectorAll('.color-btn');
    const sizeBtns = document.querySelectorAll('.size-btn');
    const addBtn = document.getElementById('addToCartBtn');
    const stockInfo = document.getElementById('stockInfo');
    const qtyInput = document.getElementById('quantity');
    
    // Find matching variant
    const variant = variants.find(v => 
        (!selectedColor || v.color_id == selectedColor) &&
        (!selectedSize || v.size_id == selectedSize)
    );
    
    if (variant) {
        document.getElementById('selectedVariant').value = variant.id;
        stockInfo.textContent = `Còn ${variant.stock_quantity} sản phẩm`;
        qtyInput.max = variant.stock_quantity;
        addBtn.disabled = false;
    } else {
        addBtn.disabled = true;
        stockInfo.textContent = '';
    }
}

// Color selection
document.querySelectorAll('.color-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.color-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        selectedColor = this.dataset.colorId;
        updateVariantSelection();
    });
});

// Size selection
document.querySelectorAll('.size-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.size-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        selectedSize = this.dataset.sizeId;
        updateVariantSelection();
    });
});

function changeQty(delta) {
    const input = document.getElementById('quantity');
    const newVal = parseInt(input.value) + delta;
    if (newVal >= 1 && newVal <= parseInt(input.max)) {
        input.value = newVal;
    }
}
</script>

<style>
.color-btn.active, .size-btn.active {
    background-color: var(--bs-primary);
    color: white;
    border-color: var(--bs-primary);
}
</style>

<?php include 'includes/footer.php'; ?>