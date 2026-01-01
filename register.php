<?php
require_once 'config.php';

if (isLoggedIn()) {
    redirect(BASE_URL);
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = sanitize($_POST['full_name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate
    if (empty($full_name) || empty($email) || empty($phone) || empty($password)) {
        $error = 'Vui lòng nhập đầy đủ thông tin';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email không hợp lệ';
    } elseif (strlen($password) < 6) {
        $error = 'Mật khẩu phải có ít nhất 6 ký tự';
    } elseif ($password !== $confirm_password) {
        $error = 'Mật khẩu xác nhận không khớp';
    } else {
        // Kiểm tra email đã tồn tại
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            $error = 'Email đã được sử dụng';
        } else {
            // Tạo tài khoản mới
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("
                INSERT INTO users (email, password, full_name, phone, role_id) 
                VALUES (?, ?, ?, ?, 2)
            ");
            
            if ($stmt->execute([$email, $hashed_password, $full_name, $phone])) {
                flashMessage('success', 'Đăng ký thành công! Vui lòng đăng nhập.');
                redirect(BASE_URL . 'login.php');
            } else {
                $error = 'Có lỗi xảy ra, vui lòng thử lại';
            }
        }
    }
}

$pageTitle = 'Đăng ký';
include 'includes/header.php';
?>

<div class="container mt-5 mb-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-body p-5">
                    <h2 class="text-center mb-4">Đăng ký tài khoản</h2>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?= $success ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label class="form-label">Họ và tên <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" class="form-control" 
                                   value="<?= isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : '' ?>" 
                                   required autofocus>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" 
                                   value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" 
                                   required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Số điện thoại <span class="text-danger">*</span></label>
                            <input type="tel" name="phone" class="form-control" 
                                   value="<?= isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : '' ?>" 
                                   pattern="[0-9]{10,11}" 
                                   placeholder="0123456789"
                                   required>
                            <small class="text-muted">Nhập 10-11 số</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Mật khẩu <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control" 
                                   minlength="6" required>
                            <small class="text-muted">Tối thiểu 6 ký tự</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Xác nhận mật khẩu <span class="text-danger">*</span></label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="agree" required>
                            <label class="form-check-label" for="agree">
                                Tôi đồng ý với <a href="#">Điều khoản sử dụng</a> và 
                                <a href="#">Chính sách bảo mật</a>
                            </label>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100 mb-3">
                            <i class="bi bi-person-plus"></i> Đăng ký
                        </button>
                    </form>
                    
                    <hr class="my-4">
                    
                    <div class="text-center">
                        <p class="mb-0">Đã có tài khoản? 
                            <a href="<?= BASE_URL ?>login.php" class="text-decoration-none fw-bold">
                                Đăng nhập ngay
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>