<?php
require_once 'config.php';
requireLogin();

$order_id = (int)($_GET['order_id'] ?? 0);
$product_id = (int)($_GET['product_id'] ?? 0);

// Kiểm tra đơn hàng
$stmt = $conn->prepare("
    SELECT o.*, oi.product_name, p.slug
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE o.id = ? AND o.user_id = ? AND oi.product_id = ?
");
$stmt->execute([$order_id, $_SESSION['user_id'], $product_id]);
$order_item = $stmt->fetch();

if (!$order_item) {
    flashMessage('error', 'Đơn hàng không hợp lệ');
    redirect(BASE_URL . 'orders.php');
}

// Kiểm tra đã đánh giá chưa
$stmt = $conn->prepare("
    SELECT id FROM reviews 
    WHERE order_id = ? AND product_id = ? AND user_id = ?
");
$stmt->execute([$order_id, $product_id, $_SESSION['user_id']]);
$existing_review = $stmt->fetch();

if ($existing_review) {
    flashMessage('info', 'Bạn đã đánh giá sản phẩm này rồi');
    redirect(BASE_URL . 'order_detail.php?id=' . $order_id);
}

// Xử lý submit đánh giá
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating = (int)$_POST['rating'];
    $comment = sanitize($_POST['comment']);
    
    if ($rating < 1 || $rating > 5) {
        flashMessage('error', 'Vui lòng chọn số sao');
    } else {
        try {
            $stmt = $conn->prepare("
                INSERT INTO reviews (product_id, user_id, order_id, rating, comment, status)
                VALUES (?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([$product_id, $_SESSION['user_id'], $order_id, $rating, $comment]);
            
            flashMessage('success', 'Cảm ơn bạn đã đánh giá! Đánh giá của bạn sẽ được hiển thị sau khi được duyệt.');
            redirect(BASE_URL . 'order_detail.php?id=' . $order_id);
        } catch (Exception $e) {
            flashMessage('error', 'Có lỗi xảy ra: ' . $e->getMessage());
        }
    }
}

$pageTitle = 'Đánh giá sản phẩm';
include 'includes/header.php';
?>

<div class="container mt-4 mb-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>">Trang chủ</a></li>
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>orders.php">Đơn hàng</a></li>
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>order_detail.php?id=<?= $order_id ?>">Chi tiết đơn hàng</a></li>
                    <li class="breadcrumb-item active">Đánh giá sản phẩm</li>
                </ol>
            </nav>

            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-star"></i> Đánh giá sản phẩm
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Thông tin sản phẩm -->
                    <div class="mb-4 p-3 bg-light rounded">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-box-seam fs-2 text-primary me-3"></i>
                            <div>
                                <strong class="d-block"><?= htmlspecialchars($order_item['product_name']) ?></strong>
                                <small class="text-muted">Đơn hàng: <?= $order_item['order_code'] ?></small>
                            </div>
                        </div>
                    </div>

                    <?php 
                    $flash = getFlashMessage();
                    if ($flash): 
                    ?>
                        <div class="alert alert-<?= $flash['type'] ?>">
                            <?= $flash['message'] ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <!-- Đánh giá sao -->
                        <div class="mb-4">
                            <label class="form-label fw-bold">
                                Đánh giá của bạn <span class="text-danger">*</span>
                            </label>
                            <div class="rating-input">
                                <input type="radio" name="rating" value="5" id="star5" required>
                                <label for="star5" title="5 sao">
                                    <i class="bi bi-star-fill"></i>
                                </label>
                                
                                <input type="radio" name="rating" value="4" id="star4">
                                <label for="star4" title="4 sao">
                                    <i class="bi bi-star-fill"></i>
                                </label>
                                
                                <input type="radio" name="rating" value="3" id="star3">
                                <label for="star3" title="3 sao">
                                    <i class="bi bi-star-fill"></i>
                                </label>
                                
                                <input type="radio" name="rating" value="2" id="star2">
                                <label for="star2" title="2 sao">
                                    <i class="bi bi-star-fill"></i>
                                </label>
                                
                                <input type="radio" name="rating" value="1" id="star1">
                                <label for="star1" title="1 sao">
                                    <i class="bi bi-star-fill"></i>
                                </label>
                            </div>
                            <div id="ratingText" class="mt-2 text-muted small"></div>
                        </div>

                        <!-- Nhận xét -->
                        <div class="mb-4">
                            <label class="form-label fw-bold">Nhận xét của bạn</label>
                            <textarea name="comment" class="form-control" rows="5" 
                                      placeholder="Chia sẻ trải nghiệm của bạn về sản phẩm này...&#10;&#10;• Chất lượng sản phẩm như thế nào?&#10;• Có đúng với mô tả không?&#10;• Bạn có hài lòng với sản phẩm không?"></textarea>
                            <small class="text-muted">Tùy chọn - Nhưng đánh giá chi tiết sẽ giúp ích cho người mua khác</small>
                        </div>

                        <!-- Lưu ý -->
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            <strong>Lưu ý:</strong>
                            <ul class="mb-0 mt-2">
                                <li>Đánh giá của bạn sẽ được kiểm duyệt trước khi hiển thị công khai</li>
                                <li>Vui lòng đánh giá trung thực và khách quan</li>
                                <li>Không sử dụng từ ngữ thô tục, xúc phạm</li>
                            </ul>
                        </div>

                        <!-- Buttons -->
                        <div class="d-flex justify-content-between">
                            <a href="<?= BASE_URL ?>order_detail.php?id=<?= $order_id ?>" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Quay lại
                            </a>
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-send"></i> Gửi đánh giá
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.rating-input {
    display: flex;
    flex-direction: row-reverse;
    justify-content: flex-end;
    gap: 5px;
}

.rating-input input {
    display: none;
}

.rating-input label {
    cursor: pointer;
    font-size: 2.5rem;
    color: #ddd;
    transition: color 0.2s;
}

.rating-input label:hover,
.rating-input label:hover ~ label,
.rating-input input:checked ~ label {
    color: #ffc107;
}

.rating-input label i {
    pointer-events: none;
}
</style>

<script>
// Show rating text
const ratingInputs = document.querySelectorAll('input[name="rating"]');
const ratingText = document.getElementById('ratingText');

const ratingTexts = {
    5: '⭐⭐⭐⭐⭐ Tuyệt vời! Rất hài lòng với sản phẩm',
    4: '⭐⭐⭐⭐ Tốt! Sản phẩm khá ổn',
    3: '⭐⭐⭐ Trung bình, có thể cải thiện',
    2: '⭐⭐ Không hài lòng lắm',
    1: '⭐ Rất tệ, không đạt yêu cầu'
};

ratingInputs.forEach(input => {
    input.addEventListener('change', function() {
        ratingText.textContent = ratingTexts[this.value];
        ratingText.className = 'mt-2 fw-bold ' + (this.value >= 4 ? 'text-success' : this.value >= 3 ? 'text-warning' : 'text-danger');
    });
});
</script>

<?php include 'includes/footer.php'; ?>