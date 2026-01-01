<?php
require_once 'config.php';

// Xóa tất cả session
session_destroy();

// Tạo session mới để hiển thị thông báo
session_start();
flashMessage('success', 'Đã đăng xuất thành công');

// Chuyển về trang đăng nhập
redirect(BASE_URL . 'login.php');
?>