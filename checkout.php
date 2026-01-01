<?php
require_once 'config.php';
requireLogin();

// Lấy giỏ hàng
$stmt = $conn->prepare("
    SELECT c.*, p.name, p.price, p.sale_price, p.id as product_id,
           co.name as color_name, s.name as size_name,
           pv.stock_quantity,
           (SELECT image_url FROM product_images WHERE product_id = p.id AND is_main = 1 LIMIT 1) as image
    FROM cart c
    JOIN product_variants pv ON c.product_variant_id = pv.id
    JOIN products p ON pv.product_id = p.id
    LEFT JOIN colors co ON pv.color_id = co.id
    LEFT JOIN sizes s ON pv.size_id = s.id
    WHERE c.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$cart_items = $stmt->fetchAll();

if (empty($cart_items)) {
    flashMessage('error', 'Giỏ hàng trống');
    redirect(BASE_URL . 'products.php');
}

// Tính tổng tiền tạm
$subtotal = 0;
foreach ($cart_items as $item) {
    $price = $item['sale_price'] ?: $item['price'];
    $subtotal += $price * $item['quantity'];
}

// Xử lý áp dụng mã giảm giá
if (isset($_POST['apply_coupon']) && isset($_POST['coupon_id'])) {
    $coupon_id = (int)$_POST['coupon_id'];
    $now = date('Y-m-d H:i:s');
    
    $stmt = $conn->prepare("SELECT * FROM coupons WHERE id = ? AND status = 'active'");
    $stmt->execute([$coupon_id]);
    $selected_coupon = $stmt->fetch();
    
    if ($selected_coupon) {
        // Kiểm tra lại các điều kiện
        if ($selected_coupon['start_date'] && $selected_coupon['start_date'] > $now) {
            flashMessage('error', 'Mã giảm giá chưa có hiệu lực');
        } elseif ($selected_coupon['end_date'] && $selected_coupon['end_date'] < $now) {
            flashMessage('error', 'Mã giảm giá đã hết hạn');
        } elseif ($selected_coupon['usage_limit'] && $selected_coupon['used_count'] >= $selected_coupon['usage_limit']) {
            flashMessage('error', 'Mã giảm giá đã hết lượt sử dụng');
        } elseif ($selected_coupon['min_order_value'] > $subtotal) {
            flashMessage('error', 'Đơn hàng chưa đạt giá trị tối thiểu ' . formatPrice($selected_coupon['min_order_value']));
        } else {
            // Lưu vào session
            $_SESSION['applied_coupon'] = $selected_coupon;
            $_SESSION['coupon_applied_time'] = time(); // Lưu thời gian áp dụng
            flashMessage('success', 'Áp dụng mã giảm giá thành công!');
        }
    } else {
        flashMessage('error', 'Mã giảm giá không tồn tại hoặc đã bị vô hiệu hóa');
    }
    
    // Redirect để tránh form resubmission
    redirect(BASE_URL . 'checkout.php');
}

// Xử lý xóa mã giảm giá
if (isset($_POST['remove_coupon'])) {
    unset($_SESSION['applied_coupon']);
    unset($_SESSION['coupon_applied_time']);
    flashMessage('info', 'Đã bỏ mã giảm giá');
    redirect(BASE_URL . 'checkout.php');
}

// Lấy coupon từ session - KHÔNG VALIDATE
$coupon = null;
$discount = 0;

if (isset($_SESSION['applied_coupon'])) {
    $coupon = $_SESSION['applied_coupon'];
    // TẮT VALIDATION - Chỉ hiển thị coupon đã áp dụng
}

// Lấy danh sách mã giảm giá khả dụng (chỉ khi chưa áp dụng mã nào)
$available_coupons = [];
if (!$coupon) {
    $now = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("
        SELECT * FROM coupons 
        WHERE status = 'active'
        AND (start_date IS NULL OR start_date <= ?)
        AND (end_date IS NULL OR end_date >= ?)
        AND (usage_limit IS NULL OR used_count < usage_limit)
        AND min_order_value <= ?
        ORDER BY discount_value DESC
    ");
    $stmt->execute([$now, $now, $subtotal]);
    $available_coupons = $stmt->fetchAll();
}

$shipping_fee = $subtotal >= 500000 ? 0 : 30000;

// Tính giảm giá nếu có coupon
if ($coupon) {
    if ($coupon['discount_type'] === 'percent') {
        $discount = $subtotal * ($coupon['discount_value'] / 100);
        if ($coupon['max_discount'] && $discount > $coupon['max_discount']) {
            $discount = $coupon['max_discount'];
        }
    } else {
        $discount = $coupon['discount_value'];
    }
    $discount = min($discount, $subtotal);
}

$total = $subtotal + $shipping_fee - $discount;

// Lấy thông tin user
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Xử lý đặt hàng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $full_name = sanitize($_POST['full_name']);
    $phone = sanitize($_POST['phone']);
    $address = sanitize($_POST['address']);
    $email = sanitize($_POST['email']);
    $note = sanitize($_POST['note'] ?? '');
    $payment_method = $_POST['payment_method'];
    
    if (empty($full_name) || empty($phone) || empty($address)) {
        flashMessage('error', 'Vui lòng nhập đầy đủ thông tin');
    } else {
        try {
            $conn->beginTransaction();
            
            $order_code = generateOrderCode();
            
            $stmt = $conn->prepare("
                INSERT INTO orders (
                    order_code, user_id, full_name, phone, address, email, note,
                    subtotal, shipping_fee, discount_amount, total_amount,
                    payment_method, payment_status, order_status, coupon_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', ?)
            ");
            
            $stmt->execute([
                $order_code, $_SESSION['user_id'], $full_name, $phone, $address, $email, $note,
                $subtotal, $shipping_fee, $discount, $total,
                $payment_method, $coupon ? $coupon['id'] : null
            ]);
            
            $order_id = $conn->lastInsertId();
            
            $stmt = $conn->prepare("
                INSERT INTO order_items (
                    order_id, product_id, product_name, product_variant_id,
                    color_name, size_name, price, quantity, subtotal
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            foreach ($cart_items as $item) {
                $price = $item['sale_price'] ?: $item['price'];
                $item_total = $price * $item['quantity'];
                
                $stmt->execute([
                    $order_id,
                    $item['product_id'],
                    $item['name'],
                    $item['product_variant_id'],
                    $item['color_name'],
                    $item['size_name'],
                    $price,
                    $item['quantity'],
                    $item_total
                ]);
                
                $stmt2 = $conn->prepare("
                    UPDATE product_variants 
                    SET stock_quantity = stock_quantity - ? 
                    WHERE id = ?
                ");
                $stmt2->execute([$item['quantity'], $item['product_variant_id']]);
            }
            
            $stmt = $conn->prepare("
                INSERT INTO order_status_history (order_id, status, note, created_by)
                VALUES (?, 'pending', 'Đơn hàng được tạo', ?)
            ");
            $stmt->execute([$order_id, $_SESSION['user_id']]);
            
            if ($coupon) {
                $conn->prepare("UPDATE coupons SET used_count = used_count + 1 WHERE id = ?")
                     ->execute([$coupon['id']]);
            }
            
            $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            
            // Xóa coupon sau khi đặt hàng thành công
            unset($_SESSION['applied_coupon']);
            unset($_SESSION['coupon_applied_time']);
            
            $conn->commit();
            
            flashMessage('success', 'Đặt hàng thành công! Mã đơn hàng: ' . $order_code);
            redirect(BASE_URL . 'order_detail.php?id=' . $order_id);
            
        } catch (Exception $e) {
            $conn->rollBack();
            flashMessage('error', 'Có lỗi xảy ra: ' . $e->getMessage());
        }
    }
}

$pageTitle = 'Thanh toán';
include 'includes/header.php';
?>

<div class="container mt-4 mb-5">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>">Trang chủ</a></li>
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>cart.php">Giỏ hàng</a></li>
            <li class="breadcrumb-item active">Thanh toán</li>
        </ol>
    </nav>

    <h2 class="mb-4">Thanh toán đơn hàng</h2>

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
        <!-- Thông tin giao hàng -->
        <div class="col-lg-7">
            <form method="POST" id="orderForm">
                <div class="card mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Thông tin giao hàng</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Họ và tên <span class="text-danger">*</span></label>
                                <input type="text" name="full_name" class="form-control" 
                                       value="<?= htmlspecialchars($user['full_name']) ?>" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Số điện thoại <span class="text-danger">*</span></label>
                                <input type="tel" name="phone" class="form-control" 
                                       value="<?= htmlspecialchars($user['phone']) ?>" required>
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" 
                                       value="<?= htmlspecialchars($user['email']) ?>">
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label class="form-label">Địa chỉ giao hàng <span class="text-danger">*</span></label>
                                <textarea name="address" class="form-control" rows="3" required><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                                <small class="text-muted">Vui lòng nhập đầy đủ: Số nhà, tên đường, phường/xã, quận/huyện, tỉnh/thành phố</small>
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label class="form-label">Ghi chú đơn hàng</label>
                                <textarea name="note" class="form-control" rows="2" placeholder="Ghi chú về đơn hàng, ví dụ: thời gian hay chỉ dẫn địa điểm giao hàng chi tiết hơn..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Phương thức thanh toán -->
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Phương thức thanh toán</h5>
                    </div>
                    <div class="card-body">
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="radio" name="payment_method" 
                                   id="cod" value="cod" checked>
                            <label class="form-check-label" for="cod">
                                <strong>Thanh toán khi nhận hàng (COD)</strong>
                                <p class="text-muted small mb-0">Thanh toán bằng tiền mặt khi nhận hàng</p>
                            </label>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="radio" name="payment_method" 
                                   id="bank" value="bank_transfer">
                            <label class="form-check-label" for="bank">
                                <strong>Chuyển khoản ngân hàng</strong>
                                <p class="text-muted small mb-0">Chuyển khoản trước, giao hàng sau khi xác nhận thanh toán</p>
                            </label>
                        </div>
                        
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="payment_method" 
                                   id="ewallet" value="e_wallet">
                            <label class="form-check-label" for="ewallet">
                                <strong>Ví điện tử (Momo, ZaloPay, VNPay)</strong>
                                <p class="text-muted small mb-0">Thanh toán qua ví điện tử</p>
                            </label>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Đơn hàng của bạn -->
        <div class="col-lg-5">
            <div class="card position-sticky" style="top: 20px;">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Đơn hàng của bạn</h5>
                </div>
                <div class="card-body">
                    <!-- Danh sách sản phẩm -->
                    <div class="mb-3" style="max-height: 250px; overflow-y: auto;">
                        <?php foreach ($cart_items as $item): 
                            $price = $item['sale_price'] ?: $item['price'];
                            $item_total = $price * $item['quantity'];
                        ?>
                        <div class="d-flex mb-3 pb-3 border-bottom">
                            <?php if ($item['image']): ?>
                                <img src="<?= UPLOAD_URL . $item['image'] ?>" 
                                     alt="<?= htmlspecialchars($item['name']) ?>"
                                     style="width: 60px; height: 60px; object-fit: cover;"
                                     class="rounded">
                            <?php endif; ?>
                            <div class="ms-2 flex-grow-1">
                                <h6 class="mb-1 small"><?= htmlspecialchars($item['name']) ?></h6>
                                <small class="text-muted">
                                    <?php if ($item['color_name']): ?>
                                        <?= htmlspecialchars($item['color_name']) ?>
                                    <?php endif; ?>
                                    <?php if ($item['size_name']): ?>
                                        - Size <?= htmlspecialchars($item['size_name']) ?>
                                    <?php endif; ?>
                                </small>
                                <div class="d-flex justify-content-between mt-1">
                                    <small>SL: <?= $item['quantity'] ?></small>
                                    <strong class="text-primary"><?= formatPrice($item_total) ?></strong>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Mã giảm giá -->
                    <div class="border rounded p-3 mb-3 bg-light">
                        <h6 class="mb-3">
                            <i class="bi bi-tags-fill text-danger"></i> Mã giảm giá
                        </h6>
                        
                        <?php if ($coupon && isset($coupon['code'])): ?>
                            <!-- Đã áp dụng mã -->
                            <div class="coupon-applied-box border border-success rounded p-2 bg-success bg-opacity-10">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="d-flex align-items-center mb-1">
                                            <span class="badge bg-success me-2"><?= htmlspecialchars($coupon['code']) ?></span>
                                            <strong class="text-success small">
                                                Giảm <?= formatPrice($discount) ?>
                                            </strong>
                                        </div>
                                        <p class="mb-0 small text-success"><?= htmlspecialchars($coupon['description'] ?? '') ?></p>
                                    </div>
                                    <form method="POST" class="d-inline">
                                        <button type="submit" name="remove_coupon" 
                                                class="btn btn-sm btn-outline-danger"
                                                title="Bỏ mã">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php elseif ($discount > 0): ?>
                            <!-- Có discount nhưng không có thông tin coupon - hiển thị từ session -->
                            <?php if (isset($_SESSION['applied_coupon'])): 
                                $coupon = $_SESSION['applied_coupon']; // Gán lại
                            ?>
                            <div class="coupon-applied-box border border-success rounded p-2 bg-success bg-opacity-10">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="d-flex align-items-center mb-1">
                                            <span class="badge bg-success me-2"><?= htmlspecialchars($coupon['code']) ?></span>
                                            <strong class="text-success small">
                                                Giảm <?= formatPrice($discount) ?>
                                            </strong>
                                        </div>
                                        <p class="mb-0 small text-success"><?= htmlspecialchars($coupon['description'] ?? '') ?></p>
                                    </div>
                                    <form method="POST" class="d-inline">
                                        <button type="submit" name="remove_coupon" 
                                                class="btn btn-sm btn-outline-danger"
                                                title="Bỏ mã">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <?php endif; ?>
                        <?php elseif (!empty($available_coupons)): ?>
                            <!-- Danh sách mã có thể chọn -->
                            <div style="max-height: 200px; overflow-y: auto;">
                                <?php foreach ($available_coupons as $c): ?>
                                <div class="coupon-card border rounded p-2 mb-2 bg-white" 
                                     style="cursor: pointer; transition: all 0.2s;"
                                     onclick="applyCoupon(<?= $c['id'] ?>)">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <div class="d-flex align-items-center mb-1">
                                                <span class="badge bg-danger me-2 small"><?= htmlspecialchars($c['code']) ?></span>
                                                <strong class="text-danger small">
                                                    <?php if ($c['discount_type'] === 'percent'): ?>
                                                        Giảm <?= $c['discount_value'] ?>%
                                                        <?php if ($c['max_discount']): ?>
                                                            <span class="text-muted">(tối đa <?= formatPrice($c['max_discount']) ?>)</span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        Giảm <?= formatPrice($c['discount_value']) ?>
                                                    <?php endif; ?>
                                                </strong>
                                            </div>
                                            <p class="mb-0 small text-muted"><?= htmlspecialchars($c['description']) ?></p>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-primary">
                                            Chọn
                                        </button>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted small mb-0">Không có mã giảm giá khả dụng</p>
                        <?php endif; ?>
                    </div>

                    <!-- Tổng tiền -->
                    <div class="border-top pt-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Tạm tính:</span>
                            <strong><?= formatPrice($subtotal) ?></strong>
                        </div>
                        
                        <div class="d-flex justify-content-between mb-2">
                            <span>Phí vận chuyển:</span>
                            <strong>
                                <?php if ($shipping_fee > 0): ?>
                                    <?= formatPrice($shipping_fee) ?>
                                <?php else: ?>
                                    <span class="text-success">Miễn phí</span>
                                <?php endif; ?>
                            </strong>
                        </div>
                        
                        <?php if ($discount > 0): ?>
                        <div class="d-flex justify-content-between mb-2 text-danger">
                            <span>Giảm giá:</span>
                            <strong>-<?= formatPrice($discount) ?></strong>
                        </div>
                        <?php endif; ?>
                        
                        <hr>
                        
                        <div class="d-flex justify-content-between mb-3">
                            <strong class="fs-5">Tổng cộng:</strong>
                            <h4 class="text-danger mb-0"><?= formatPrice($total) ?></h4>
                        </div>
                    </div>

                    <button type="submit" form="orderForm" name="place_order" class="btn btn-primary w-100 btn-lg">
                        <i class="bi bi-check-circle"></i> Đặt hàng
                    </button>

                    <div class="mt-3 text-center">
                        <small class="text-muted">
                            Bằng việc đặt hàng, bạn đồng ý với 
                            <a href="#">Điều khoản sử dụng</a> của chúng tôi
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Form ẩn để apply coupon -->
<form method="POST" id="couponForm">
    <input type="hidden" name="coupon_id" id="coupon_id_input">
    <input type="hidden" name="apply_coupon" value="1">
</form>

<style>
.coupon-card {
    transition: all 0.2s;
}
.coupon-card:hover {
    border-color: var(--bs-primary) !important;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
</style>

<script>
function applyCoupon(couponId) {
    if (confirm('Áp dụng mã giảm giá này?')) {
        document.getElementById('coupon_id_input').value = couponId;
        document.getElementById('couponForm').submit();
    }
}
</script>

<?php include 'includes/footer.php'; ?>