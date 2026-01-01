<?php
// config.php - Cấu hình database và các thông số hệ thống

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'shoe_shop');

// Kết nối database
try {
    $conn = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch(PDOException $e) {
    die("Lỗi kết nối database: " . $e->getMessage());
}

// Cấu hình session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cấu hình URL gốc
define('BASE_URL', 'http://localhost/shoe_shop/');
define('ADMIN_URL', BASE_URL . 'admin/');

// Cấu hình upload
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('UPLOAD_URL', BASE_URL . 'uploads/');

// Tạo thư mục upload nếu chưa có
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true);
    mkdir(UPLOAD_DIR . 'products/', 0777, true);
    mkdir(UPLOAD_DIR . 'categories/', 0777, true);
    mkdir(UPLOAD_DIR . 'brands/', 0777, true);
}

// Hàm tiện ích
function redirect($url) {
    header("Location: " . $url);
    exit;
}

function flashMessage($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1;
}

function requireLogin() {
    if (!isLoggedIn()) {
        flashMessage('error', 'Vui lòng đăng nhập để tiếp tục');
        redirect(BASE_URL . 'login.php');
    }
}

function requireAdmin() {
    if (!isAdmin()) {
        flashMessage('error', 'Bạn không có quyền truy cập');
        redirect(BASE_URL);
    }
}

function formatPrice($price) {
    return number_format($price, 0, ',', '.') . 'đ';
}

function formatDate($date) {
    return date('d/m/Y H:i', strtotime($date));
}

function uploadFile($file, $directory = 'products') {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowedTypes)) {
        return false;
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $uploadPath = UPLOAD_DIR . $directory . '/' . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        return $directory . '/' . $filename;
    }
    
    return false;
}

function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function generateSlug($string) {
    $string = mb_strtolower($string, 'UTF-8');
    $string = preg_replace('/[àáạảãâầấậẩẫăằắặẳẵ]/u', 'a', $string);
    $string = preg_replace('/[èéẹẻẽêềếệểễ]/u', 'e', $string);
    $string = preg_replace('/[ìíịỉĩ]/u', 'i', $string);
    $string = preg_replace('/[òóọỏõôồốộổỗơờớợởỡ]/u', 'o', $string);
    $string = preg_replace('/[ùúụủũưừứựửữ]/u', 'u', $string);
    $string = preg_replace('/[ỳýỵỷỹ]/u', 'y', $string);
    $string = preg_replace('/[đ]/u', 'd', $string);
    $string = preg_replace('/[^a-z0-9\s-]/', '', $string);
    $string = preg_replace('/[\s-]+/', '-', $string);
    $string = trim($string, '-');
    return $string;
}

function generateOrderCode() {
    return 'ORD' . date('ymd') . rand(1000, 9999);
}

function getCartCount() {
    global $conn;
    if (!isLoggedIn()) return 0;
    
    $stmt = $conn->prepare("SELECT SUM(quantity) as total FROM cart WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $result = $stmt->fetch();
    return $result['total'] ?? 0;
}
?>