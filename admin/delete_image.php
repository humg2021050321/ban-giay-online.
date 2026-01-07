<?php
require_once '../config.php';
requireAdmin();

$id = (int)($_GET['id'] ?? 0);
$product_id = (int)($_GET['product_id'] ?? 0);

if (!$id || !$product_id) {
    flashMessage('error', 'Thiếu thông tin');
    redirect(ADMIN_URL . 'products.php');
}

try {
    // Lấy thông tin ảnh
    $stmt = $conn->prepare("SELECT * FROM product_images WHERE id = ? AND product_id = ?");
    $stmt->execute([$id, $product_id]);
    $image = $stmt->fetch();
    
    if (!$image) {
        flashMessage('error', 'Hình ảnh không tồn tại');
        redirect(ADMIN_URL . 'product_form.php?id=' . $product_id);
    }
    
    // Xóa file
    $file_path = UPLOAD_DIR . $image['image_url'];
    if (file_exists($file_path)) {
        unlink($file_path);
    }
    
    // Xóa record
    $conn->prepare("DELETE FROM product_images WHERE id = ?")->execute([$id]);
    
    // Nếu là ảnh chính, set ảnh khác làm chính
    if ($image['is_main']) {
        $conn->prepare("
            UPDATE product_images 
            SET is_main = 1 
            WHERE product_id = ? 
            ORDER BY sort_order ASC 
            LIMIT 1
        ")->execute([$product_id]);
    }
    
    flashMessage('success', 'Đã xóa hình ảnh');
} catch (Exception $e) {
    flashMessage('error', 'Lỗi: ' . $e->getMessage());
}

redirect(ADMIN_URL . 'product_form.php?id=' . $product_id);