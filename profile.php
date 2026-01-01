<?php
require_once 'config.php';
requireLogin();

// Lấy thông tin user
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Xử lý cập nhật thông tin
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $full_name = sanitize($_POST['full_name']);
        $phone = sanitize($_POST['phone']);
        $address = sanitize($_POST['address']);
        
        if (empty($full_name) || empty($phone)) {
            flashMessage('error', 'Vui lòng nhập đầy đủ thông tin');
        } else {
            $stmt = $conn->prepare("
                UPDATE users 
                SET full_name = ?, phone = ?, address = ?
                WHERE id = ?
            ");
            
            if ($stmt->execute([$full_name, $phone, $address, $_SESSION['user_id']])) {
                $_SESSION['full_name'] = $full_name;
                flashMessage('success', 'Cập nhật thông tin thành công');
                redirect(BASE_URL . 'profile.php');
            } else {
                flashMessage('error', 'Có lỗi xảy ra');
            }
        }
    }
    
    // Đổi mật khẩu
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            flashMessage('error', 'Vui lòng nhập đầy đủ thông tin');
        } elseif (!password_verify($current_password, $user['password'])) {
            flashMessage('error', 'Mật khẩu hiện tại không đúng');
        } elseif (strlen($new_password) < 6) {
            flashMessage('error', 'Mật khẩu mới phải có ít nhất 6 ký tự');
        } elseif ($new_password !== $confirm_password) {
            flashMessage('error', 'Mật khẩu xác nhận không khớp');
        } else {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            
            if ($stmt->execute([$hashed, $_SESSION['user_id']])) {
                flashMessage('success', 'Đổi mật khẩu thành công');
                redirect(BASE_URL . 'profile.php');
            } else {
                flashMessage('error', 'Có lỗi xảy ra');
            }
        }
    }
}

// Thống kê đơn hàng
$stats = $conn->prepare("
    SELECT 
        COUNT(*) as total_orders,
        SUM(CASE WHEN order_status = 'completed' THEN total_amount ELSE 0 END) as total_spent
    FROM orders 
    WHERE user_id = ?
");
$stats->execute([$_SESSION['user_id']]);
$order_stats = $stats->fetch();

$pageTitle = 'Thông tin cá nhân';
include 'includes/header.php';
?>

<div class="container mt-4 mb-5">
    <h2 class="mb-4">Tài khoản của tôi</h2>

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
        <!-- Sidebar -->
        <div class="col-lg-3 mb-4">
            <div class="card">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <i class="bi bi-person-circle" style="font-size: 5rem; color: #6c757d;"></i>
                    </div>
                    <h5><?= htmlspecialchars($user['full_name']) ?></h5>
                    <p class="text-muted small mb-3"><?= htmlspecialchars($user['email']) ?></p>
                    
                    <div class="d-grid gap-2">
                        <button class="btn btn-sm btn-primary" data-bs-toggle="pill" data-bs-target="#profile">
                            <i class="bi bi-person"></i> Thông tin
                        </button>
                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="pill" data-bs-target="#password">
                            <i class="bi bi-key"></i> Đổi mật khẩu
                        </button>
                        <a href="<?= BASE_URL ?>orders.php" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-bag"></i> Đơn hàng
                        </a>
                    </div>
                </div>
            </div>

            <!-- Thống kê -->
            <div class="card mt-3">
                <div class="card-body">
                    <h6 class="card-title">Thống kê</h6>
                    <div class="mb-2">
                        <small class="text-muted">Tổng đơn hàng:</small>
                        <div class="fw-bold"><?= number_format($order_stats['total_orders']) ?></div>
                    </div>
                    <div>
                        <small class="text-muted">Tổng chi tiêu:</small>
                        <div class="fw-bold text-primary"><?= formatPrice($order_stats['total_spent']) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-lg-9">
            <div class="tab-content">
                <!-- Thông tin cá nhân -->
                <div class="tab-pane fade show active" id="profile">
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Thông tin cá nhân</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="update_profile" value="1">
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Họ và tên <span class="text-danger">*</span></label>
                                        <input type="text" name="full_name" class="form-control" 
                                               value="<?= htmlspecialchars($user['full_name']) ?>" required>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control" 
                                               value="<?= htmlspecialchars($user['email']) ?>" disabled>
                                        <small class="text-muted">Email không thể thay đổi</small>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Số điện thoại <span class="text-danger">*</span></label>
                                        <input type="tel" name="phone" class="form-control" 
                                               value="<?= htmlspecialchars($user['phone']) ?>" required>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Ngày đăng ký</label>
                                        <input type="text" class="form-control" 
                                               value="<?= formatDate($user['created_at']) ?>" disabled>
                                    </div>
                                    
                                    <div class="col-12 mb-3">
                                        <label class="form-label">Địa chỉ</label>
                                        <textarea name="address" class="form-control" rows="3"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle"></i> Cập nhật thông tin
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Đổi mật khẩu -->
                <div class="tab-pane fade" id="password">
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Đổi mật khẩu</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="change_password" value="1">
                                
                                <div class="mb-3">
                                    <label class="form-label">Mật khẩu hiện tại <span class="text-danger">*</span></label>
                                    <input type="password" name="current_password" class="form-control" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Mật khẩu mới <span class="text-danger">*</span></label>
                                    <input type="password" name="new_password" class="form-control" 
                                           minlength="6" required>
                                    <small class="text-muted">Tối thiểu 6 ký tự</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Xác nhận mật khẩu mới <span class="text-danger">*</span></label>
                                    <input type="password" name="confirm_password" class="form-control" required>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-key"></i> Đổi mật khẩu
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>