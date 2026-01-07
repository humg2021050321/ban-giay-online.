<?php
require_once '../config.php';
requireAdmin();

$order_id = (int)($_GET['id'] ?? 0);
$new_status = $_GET['status'] ?? '';

if (!$order_id || !$new_status) {
    flashMessage('error', 'Thiếu thông tin');
    redirect(ADMIN_URL . 'orders.php');
}

// Kiểm tra đơn hàng
$stmt = $conn->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order) {
    flashMessage('error', 'Đơn hàng không tồn tại');
    redirect(ADMIN_URL . 'orders.php');
}

// Validate trạng thái
$valid_statuses = ['pending', 'confirmed', 'shipping', 'completed', 'cancelled'];
if (!in_array($new_status, $valid_statuses)) {
    flashMessage('error', 'Trạng thái không hợp lệ');
    redirect(ADMIN_URL . 'orders.php');
}

try {
    $conn->beginTransaction();
    
    // Cập nhật trạng thái
    $stmt = $conn->prepare("UPDATE orders SET order_status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$new_status, $order_id]);
    
    // Lưu lịch sử
    $status_notes = [
        'confirmed' => 'Đơn hàng đã được xác nhận',
        'shipping' => 'Đơn hàng đang được giao',
        'completed' => 'Đơn hàng đã hoàn thành',
        'cancelled' => 'Đơn hàng đã bị hủy'
    ];
    
    $stmt = $conn->prepare("
        INSERT INTO order_status_history (order_id, status, note, created_by)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([
        $order_id,
        $new_status,
        $status_notes[$new_status] ?? '',
        $_SESSION['user_id']
    ]);
    
    // Nếu hoàn thành, cập nhật payment status
    if ($new_status === 'completed') {
        $conn->prepare("UPDATE orders SET payment_status = 'paid' WHERE id = ?")->execute([$order_id]);
    }
    
    $conn->commit();
    flashMessage('success', 'Cập nhật trạng thái đơn hàng thành công');
    
} catch (Exception $e) {
    $conn->rollBack();
    flashMessage('error', 'Lỗi: ' . $e->getMessage());
}

redirect(ADMIN_URL . 'order_detail.php?id=' . $order_id);