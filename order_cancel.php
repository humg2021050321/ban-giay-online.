<?php
require_once 'config.php';
requireLogin();

$order_id = (int)($_GET['id'] ?? 0);

// Kiểm tra đơn hàng
$stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->execute([$order_id, $_SESSION['user_id']]);
$order = $stmt->fetch();

if (!$order) {
    flashMessage('error', 'Đơn hàng không tồn tại');
    redirect(BASE_URL . 'orders.php');
}

// Chỉ được hủy đơn pending
if ($order['order_status'] !== 'pending') {
    flashMessage('error', 'Không thể hủy đơn hàng này');
    redirect(BASE_URL . 'order_detail.php?id=' . $order_id);
}

try {
    $conn->beginTransaction();
    
    // Cập nhật trạng thái
    $stmt = $conn->prepare("UPDATE orders SET order_status = 'cancelled', updated_at = NOW() WHERE id = ?");
    $stmt->execute([$order_id]);
    
    // Hoàn lại tồn kho
    $stmt = $conn->prepare("SELECT product_variant_id, quantity FROM order_items WHERE order_id = ?");
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll();
    
    foreach ($items as $item) {
        $stmt = $conn->prepare("
            UPDATE product_variants 
            SET stock_quantity = stock_quantity + ? 
            WHERE id = ?
        ");
        $stmt->execute([$item['quantity'], $item['product_variant_id']]);
    }
    
    // Lưu lịch sử
    $stmt = $conn->prepare("
        INSERT INTO order_status_history (order_id, status, note, created_by)
        VALUES (?, 'cancelled', 'Khách hàng hủy đơn', ?)
    ");
    $stmt->execute([$order_id, $_SESSION['user_id']]);
    
    $conn->commit();
    flashMessage('success', 'Đã hủy đơn hàng thành công');
    
} catch (Exception $e) {
    $conn->rollBack();
    flashMessage('error', 'Lỗi: ' . $e->getMessage());
}

redirect(BASE_URL . 'orders.php');