<?php
require_once 'config.php';
requireLogin();

// Xử lý cập nhật giỏ hàng
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update':
                $cart_id = (int)$_POST['cart_id'];
                $quantity = (int)$_POST['quantity'];
                
                // Nếu số lượng <= 0, xóa sản phẩm
                if ($quantity <= 0) {
                    $stmt = $conn->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
                    $stmt->execute([$cart_id, $_SESSION['user_id']]);
                    flashMessage('success', 'Đã xóa sản phẩm khỏi giỏ hàng');
                } else {
                    // Kiểm tra tồn kho
                    $stmt = $conn->prepare("
                        SELECT pv.stock_quantity 
                        FROM cart c
                        JOIN product_variants pv ON c.product_variant_id = pv.id
                        WHERE c.id = ? AND c.user_id = ?
                    ");
                    $stmt->execute([$cart_id, $_SESSION['user_id']]);
                    $stock = $stmt->fetch();
                    
                    if ($stock && $quantity > $stock['stock_quantity']) {
                        flashMessage('error', 'Số lượng vượt quá tồn kho');
                    } else {
                        $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?");
                        $stmt->execute([$quantity, $cart_id, $_SESSION['user_id']]);
                        flashMessage('success', 'Đã cập nhật giỏ hàng');
                    }
                }
                break;
                
            case 'remove':
                $cart_id = (int)$_POST['cart_id'];
                
                $stmt = $conn->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
                $stmt->execute([$cart_id, $_SESSION['user_id']]);
                
                flashMessage('success', 'Đã xóa sản phẩm khỏi giỏ hàng');
                break;
                
            case 'clear':
                $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                
                flashMessage('success', 'Đã xóa toàn bộ giỏ hàng');
                break;
        }
        redirect(BASE_URL . 'cart.php');
    }
}

// Lấy giỏ hàng
$stmt = $conn->prepare("
    SELECT c.*, p.name, p.price, p.sale_price, p.slug,
           co.name as color_name, s.name as size_name,
           pv.stock_quantity,
           (SELECT image_url FROM product_images WHERE product_id = p.id AND is_main = 1 LIMIT 1) as image
    FROM cart c
    JOIN product_variants pv ON c.product_variant_id = pv.id
    JOIN products p ON pv.product_id = p.id
    LEFT JOIN colors co ON pv.color_id = co.id
    LEFT JOIN sizes s ON pv.size_id = s.id
    WHERE c.user_id = ?
    ORDER BY c.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$cart_items = $stmt->fetchAll();

// Tính tổng tiền
$subtotal = 0;
foreach ($cart_items as $item) {
    $price = $item['sale_price'] ?: $item['price'];
    $subtotal += $price * $item['quantity'];
}

$shipping_fee = $subtotal >= 500000 ? 0 : 30000;
$total = $subtotal + $shipping_fee;

$pageTitle = 'Giỏ hàng';
include 'includes/header.php';
?>

<div class="container mt-4 mb-5">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>">Trang chủ</a></li>
            <li class="breadcrumb-item active">Giỏ hàng</li>
        </ol>
    </nav>

    <h2 class="mb-4">Giỏ hàng của bạn</h2>

    <?php 
    $flash = getFlashMessage();
    if ($flash): 
    ?>
        <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show">
            <?= $flash['message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (empty($cart_items)): ?>
        <div class="text-center py-5">
            <i class="bi bi-cart-x" style="font-size: 5rem; color: #ccc;"></i>
            <h4 class="mt-3">Giỏ hàng trống</h4>
            <p class="text-muted">Bạn chưa có sản phẩm nào trong giỏ hàng</p>
            <a href="<?= BASE_URL ?>products.php" class="btn btn-primary">
                <i class="bi bi-shop"></i> Tiếp tục mua sắm
            </a>
        </div>
    <?php else: ?>
        <div class="row">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header bg-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Sản phẩm (<?= count($cart_items) ?>)</h5>
                            <form method="POST" onsubmit="return confirm('Bạn có chắc muốn xóa toàn bộ giỏ hàng?')">
                                <input type="hidden" name="action" value="clear">
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-trash"></i> Xóa tất cả
                                </button>
                            </form>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Sản phẩm</th>
                                        <th>Đơn giá</th>
                                        <th style="width: 200px;">Số lượng</th>
                                        <th>Thành tiền</th>
                                        <th style="width: 50px;"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cart_items as $item): 
                                        $price = $item['sale_price'] ?: $item['price'];
                                        $item_total = $price * $item['quantity'];
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <a href="<?= BASE_URL ?>product_detail.php?slug=<?= $item['slug'] ?>">
                                                    <?php if ($item['image']): ?>
                                                        <img src="<?= UPLOAD_URL . $item['image'] ?>" 
                                                             alt="<?= htmlspecialchars($item['name']) ?>"
                                                             style="width: 80px; height: 80px; object-fit: cover;">
                                                    <?php else: ?>
                                                        <div style="width: 80px; height: 80px; background: #f0f0f0;" 
                                                             class="d-flex align-items-center justify-content-center">
                                                            <i class="bi bi-image text-muted"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </a>
                                                <div class="ms-3">
                                                    <h6 class="mb-1">
                                                        <a href="<?= BASE_URL ?>product_detail.php?slug=<?= $item['slug'] ?>" 
                                                           class="text-decoration-none text-dark">
                                                            <?= htmlspecialchars($item['name']) ?>
                                                        </a>
                                                    </h6>
                                                    <small class="text-muted">
                                                        <?php if ($item['color_name']): ?>
                                                            Màu: <?= htmlspecialchars($item['color_name']) ?>
                                                        <?php endif; ?>
                                                        <?php if ($item['size_name']): ?>
                                                            | Size: <?= htmlspecialchars($item['size_name']) ?>
                                                        <?php endif; ?>
                                                    </small>
                                                    <?php if ($item['stock_quantity'] < 5): ?>
                                                        <div class="badge bg-warning text-dark mt-1">
                                                            Chỉ còn <?= $item['stock_quantity'] ?> sản phẩm
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="align-middle">
                                            <?php if ($item['sale_price']): ?>
                                                <div class="text-danger fw-bold"><?= formatPrice($item['sale_price']) ?></div>
                                                <small class="text-decoration-line-through text-muted">
                                                    <?= formatPrice($item['price']) ?>
                                                </small>
                                            <?php else: ?>
                                                <div class="fw-bold"><?= formatPrice($item['price']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="align-middle">
                                            <div class="d-flex align-items-center gap-2">
                                                <!-- Nút giảm -->
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="update">
                                                    <input type="hidden" name="cart_id" value="<?= $item['id'] ?>">
                                                    <input type="hidden" name="quantity" value="<?= $item['quantity'] - 1 ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-secondary"
                                                            <?= $item['quantity'] <= 1 ? 'onclick="return confirm(\'Xóa sản phẩm này?\')"' : '' ?>>
                                                        <i class="bi bi-dash"></i>
                                                    </button>
                                                </form>
                                                
                                                <!-- Input số lượng -->
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="update">
                                                    <input type="hidden" name="cart_id" value="<?= $item['id'] ?>">
                                                    <input type="number" 
                                                           name="quantity" 
                                                           value="<?= $item['quantity'] ?>" 
                                                           min="0" 
                                                           max="<?= $item['stock_quantity'] ?>"
                                                           class="form-control form-control-sm text-center" 
                                                           style="width: 60px;"
                                                           onchange="this.form.submit()">
                                                </form>
                                                
                                                <!-- Nút tăng -->
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="update">
                                                    <input type="hidden" name="cart_id" value="<?= $item['id'] ?>">
                                                    <input type="hidden" name="quantity" value="<?= $item['quantity'] + 1 ?>">
                                                    <button type="submit" 
                                                            class="btn btn-sm btn-outline-secondary"
                                                            <?= $item['quantity'] >= $item['stock_quantity'] ? 'disabled' : '' ?>>
                                                        <i class="bi bi-plus"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                        <td class="align-middle">
                                            <strong class="text-primary"><?= formatPrice($item_total) ?></strong>
                                        </td>
                                        <td class="align-middle">
                                            <form method="POST" onsubmit="return confirm('Xóa sản phẩm này?')">
                                                <input type="hidden" name="action" value="remove">
                                                <input type="hidden" name="cart_id" value="<?= $item['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <a href="<?= BASE_URL ?>products.php" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left"></i> Tiếp tục mua sắm
                </a>
            </div>

            <!-- Order Summary -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Thông tin đơn hàng</h5>
                    </div>
                    <div class="card-body">
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
                        
                        <?php if ($subtotal < 500000 && $subtotal > 0): ?>
                            <div class="alert alert-info small mb-3">
                                <i class="bi bi-info-circle"></i>
                                Mua thêm <?= formatPrice(500000 - $subtotal) ?> để được miễn phí vận chuyển
                            </div>
                        <?php endif; ?>
                        
                        <hr>
                        <div class="d-flex justify-content-between mb-3">
                            <strong>Tổng cộng:</strong>
                            <h4 class="text-danger mb-0"><?= formatPrice($total) ?></h4>
                        </div>
                        
                        <a href="<?= BASE_URL ?>checkout.php" class="btn btn-primary w-100 btn-lg">
                            <i class="bi bi-credit-card"></i> Thanh toán
                        </a>
                        
                        <div class="mt-3 text-center">
                            <small class="text-muted">
                                <i class="bi bi-shield-check"></i>
                                Giao dịch an toàn & bảo mật
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>