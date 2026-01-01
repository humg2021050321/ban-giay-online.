<?php
require_once 'config.php';

// Nếu đã đăng nhập, chuyển về trang chủ
if (isLoggedIn()) {
    redirect(BASE_URL);
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error = 'Vui lòng nhập đầy đủ thông tin';
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role_id'] = $user['role_id'];
            
            flashMessage('success', 'Đăng nhập thành công!');
            
            // Redirect về trang trước đó hoặc trang chủ
            $redirect_to = isset($_GET['redirect']) ? $_GET['redirect'] : BASE_URL;
            redirect($redirect_to);
        } else {
            $error = 'Email hoặc mật khẩu không đúng';
        }
    }
}

$pageTitle = 'Đăng nhập';
include 'includes/header.php';
?>

<div class="container mt-5 mb-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow">
                <div class="card-body p-5">
                    <h2 class="text-center mb-4">Đăng nhập</h2>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>
                    
                    <?php 
                    $flash = getFlashMessage();
                    if ($flash): 
                    ?>
                        <div class="alert alert-<?= $flash['type'] ?>">
                            <?= $flash['message'] ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" 
                                   value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" 
                                   required autofocus>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Mật khẩu</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="remember">
                            <label class="form-check-label" for="remember">Ghi nhớ đăng nhập</label>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100 mb-3">
                            <i class="bi bi-box-arrow-in-right"></i> Đăng nhập
                        </button>
                        
                        <div class="text-center">
                            <a href="#" class="text-decoration-none">Quên mật khẩu?</a>
                        </div>
                    </form>
                    
                    <hr class="my-4">
                    
                    <div class="text-center">
                        <p class="mb-0">Chưa có tài khoản? 
                            <a href="<?= BASE_URL ?>register.php" class="text-decoration-none fw-bold">
                                Đăng ký ngay
                            </a>
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Thông tin demo -->
            <div class="card mt-3 bg-light">
                <div class="card-body">
                    <h6 class="card-title">Thông tin đăng nhập demo:</h6>
                    <p class="mb-1"><strong>Admin:</strong></p>
                    <p class="mb-1">Email: admin@shoeshop.com</p>
                    <p class="mb-0">Password: password</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>