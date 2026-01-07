<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? $pageTitle . ' - ' : '' ?>Admin - Shoe Shop</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <style>
        :root {
            --sidebar-width: 250px;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: #1e293b;
            color: white;
            overflow-y: auto;
            transition: all 0.3s;
            z-index: 1000;
        }
        
        .sidebar-header {
            padding: 1.5rem;
            background: #0f172a;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-menu {
            padding: 1rem 0;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        
        .sidebar-menu a i {
            width: 25px;
            margin-right: 10px;
            font-size: 1.2rem;
        }
        
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            background: #f8fafc;
        }
        
        .top-navbar {
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 1rem 1.5rem;
            position: sticky;
            top: 0;
            z-index: 999;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                margin-left: calc(var(--sidebar-width) * -1);
            }
            
            .sidebar.show {
                margin-left: 0;
            }
            
            .main-content {
                margin-left: 0;
            }
        }
        
        .stat-card {
            border-left: 4px solid;
        }
        
        .table-actions a,
        .table-actions button {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h4 class="mb-0">
                <i class="bi bi-shop"></i>
                SHOE SHOP
            </h4>
            <small class="text-white-50">Admin Panel</small>
        </div>
        
        <div class="sidebar-menu">
            <a href="<?= ADMIN_URL ?>" class="<?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>
            
            <div class="px-3 py-2 text-uppercase small text-white-50 fw-bold">Sản phẩm</div>
            
            <a href="<?= ADMIN_URL ?>products.php" class="<?= basename($_SERVER['PHP_SELF']) == 'products.php' ? 'active' : '' ?>">
                <i class="bi bi-box-seam"></i>
                <span>Quản lý sản phẩm</span>
            </a>
            
            <a href="<?= ADMIN_URL ?>categories.php" class="<?= basename($_SERVER['PHP_SELF']) == 'categories.php' ? 'active' : '' ?>">
                <i class="bi bi-grid-3x3"></i>
                <span>Danh mục</span>
            </a>
            
            <a href="<?= ADMIN_URL ?>brands.php" class="<?= basename($_SERVER['PHP_SELF']) == 'brands.php' ? 'active' : '' ?>">
                <i class="bi bi-award"></i>
                <span>Thương hiệu</span>
            </a>
            
            <div class="px-3 py-2 text-uppercase small text-white-50 fw-bold mt-3">Bán hàng</div>
            
            <a href="<?= ADMIN_URL ?>orders.php" class="<?= basename($_SERVER['PHP_SELF']) == 'orders.php' ? 'active' : '' ?>">
                <i class="bi bi-bag-check"></i>
                <span>Đơn hàng</span>
                <?php
                $pending = $conn->query("SELECT COUNT(*) as c FROM orders WHERE order_status = 'pending'")->fetch()['c'];
                if ($pending > 0):
                ?>
                    <span class="badge bg-danger ms-auto"><?= $pending ?></span>
                <?php endif; ?>
            </a>
            
            <a href="<?= ADMIN_URL ?>customers.php" class="<?= basename($_SERVER['PHP_SELF']) == 'customers.php' ? 'active' : '' ?>">
                <i class="bi bi-people"></i>
                <span>Khách hàng</span>
            </a>
            
            <a href="<?= ADMIN_URL ?>coupons.php" class="<?= basename($_SERVER['PHP_SELF']) == 'coupons.php' ? 'active' : '' ?>">
                <i class="bi bi-ticket-perforated"></i>
                <span>Mã giảm giá</span>
            </a>
            
            <div class="px-3 py-2 text-uppercase small text-white-50 fw-bold mt-3">Nội dung</div>
            
            <a href="<?= ADMIN_URL ?>reviews.php" class="<?= basename($_SERVER['PHP_SELF']) == 'reviews.php' ? 'active' : '' ?>">
                <i class="bi bi-star"></i>
                <span>Đánh giá</span>
            </a>
            
            <div class="px-3 py-2 text-uppercase small text-white-50 fw-bold mt-3">Báo cáo</div>
            
            <a href="<?= ADMIN_URL ?>statistics.php" class="<?= basename($_SERVER['PHP_SELF']) == 'statistics.php' ? 'active' : '' ?>">
                <i class="bi bi-graph-up"></i>
                <span>Thống kê</span>
            </a>
            
            <hr class="border-secondary my-3">
            
            <a href="<?= BASE_URL ?>">
                <i class="bi bi-house-door"></i>
                <span>Về trang chủ</span>
            </a>
            
            <a href="<?= BASE_URL ?>logout.php" class="text-danger">
                <i class="bi bi-box-arrow-right"></i>
                <span>Đăng xuất</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navbar -->
        <nav class="top-navbar d-flex justify-content-between align-items-center">
            <div>
                <button class="btn btn-link d-lg-none" id="sidebarToggle">
                    <i class="bi bi-list fs-4"></i>
                </button>
                <span class="fs-5 fw-bold"><?= $pageTitle ?? 'Dashboard' ?></span>
            </div>
            
            <div class="d-flex align-items-center">
                <a href="<?= BASE_URL ?>" class="btn btn-sm btn-outline-primary me-3" target="_blank">
                    <i class="bi bi-eye"></i> Xem shop
                </a>
                
                <div class="dropdown">
                    <a class="btn btn-link text-decoration-none text-dark dropdown-toggle" 
                       href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle fs-5"></i>
                        <span class="ms-2"><?= htmlspecialchars($_SESSION['full_name']) ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="<?= BASE_URL ?>profile.php">
                            <i class="bi bi-person"></i> Thông tin cá nhân
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>logout.php">
                            <i class="bi bi-box-arrow-right"></i> Đăng xuất
                        </a></li>
                    </ul>
                </div>
            </div>
        </nav>

        <!-- Flash Messages -->
        <?php 
        $flash = getFlashMessage();
        if ($flash): 
        ?>
        <div class="container-fluid mt-3">
            <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show">
                <?= $flash['message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
        <?php endif; ?>

        <!-- Page Content -->