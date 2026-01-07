<div class="card h-100 product-card">
    <a href="<?= BASE_URL ?>product_detail.php?slug=<?= $product['slug'] ?>" class="text-decoration-none">
        <?php if ($product['image']): ?>
            <img src="<?= UPLOAD_URL . $product['image'] ?>" class="card-img-top product-image" alt="<?= htmlspecialchars($product['name']) ?>">
        <?php else: ?>
            <div class="card-img-top product-image bg-light d-flex align-items-center justify-content-center">
                <i class="bi bi-image text-muted" style="font-size: 3rem;"></i>
            </div>
        <?php endif; ?>
        
        <?php if ($product['sale_price']): ?>
            <div class="badge bg-danger position-absolute top-0 end-0 m-2">
                -<?= round((($product['price'] - $product['sale_price']) / $product['price']) * 100) ?>%
            </div>
        <?php endif; ?>
        
        <div class="card-body d-flex flex-column">
            <?php if (isset($product['brand_name'])): ?>
                <small class="text-muted"><?= htmlspecialchars($product['brand_name']) ?></small>
            <?php endif; ?>
            
            <h6 class="card-title mt-1 product-title">
                <?= htmlspecialchars($product['name']) ?>
            </h6>
            
            <div class="mb-2">
                <?php if ($product['sale_price']): ?>
                    <span class="text-danger fw-bold fs-5"><?= formatPrice($product['sale_price']) ?></span>
                    <span class="text-decoration-line-through text-muted small ms-2">
                        <?= formatPrice($product['price']) ?>
                    </span>
                <?php else: ?>
                    <span class="text-primary fw-bold fs-5"><?= formatPrice($product['price']) ?></span>
                <?php endif; ?>
            </div>
            
            <!-- Rating Section with Fixed Height -->
            <div class="rating-section">
                <?php
                // Lấy đánh giá trung bình
                $stmt = $conn->prepare("
                    SELECT AVG(rating) as avg_rating, COUNT(*) as review_count 
                    FROM reviews 
                    WHERE product_id = ? AND status = 'approved'
                ");
                $stmt->execute([$product['id']]);
                $rating = $stmt->fetch();
                
                if ($rating['review_count'] > 0):
                ?>
                    <div>
                        <?php
                        $stars = round($rating['avg_rating']);
                        for ($i = 1; $i <= 5; $i++):
                            if ($i <= $stars):
                        ?>
                            <i class="bi bi-star-fill text-warning"></i>
                        <?php else: ?>
                            <i class="bi bi-star text-warning"></i>
                        <?php 
                            endif;
                        endfor; 
                        ?>
                        <small class="text-muted">(<?= $rating['review_count'] ?>)</small>
                    </div>
                <?php else: ?>
                    <!-- Empty space to maintain consistent height -->
                    <div style="height: 20px;"></div>
                <?php endif; ?>
            </div>
        </div>
    </a>
    
    <div class="card-footer bg-white border-top-0">
        <?php if (isLoggedIn()): ?>
            <a href="<?= BASE_URL ?>product_detail.php?slug=<?= $product['slug'] ?>" 
               class="btn btn-primary w-100">
                <i class="bi bi-cart-plus"></i> Thêm vào giỏ
            </a>
        <?php else: ?>
            <a href="<?= BASE_URL ?>login.php" class="btn btn-outline-primary w-100">
                Đăng nhập để mua
            </a>
        <?php endif; ?>
    </div>
</div>

<style>
.product-card {
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.product-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.15);
}

.product-card .card-body {
    flex-grow: 1;
    display: flex;
    flex-direction: column;
}

.product-image {
    width: 100%;
    height: 250px;
    object-fit: cover;
}

.product-title {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    color: #333;
    min-height: 48px;
    line-height: 1.5;
}

.product-card:hover .product-title {
    color: var(--primary-color);
}

.rating-section {
    min-height: 28px;
    display: flex;
    align-items: center;
    margin-top: auto;
    padding-top: 8px;
}

.rating-section i {
    font-size: 14px;
}

.product-card .card-footer {
    margin-top: auto;
}
</style>