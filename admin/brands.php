<?php
require_once '../config.php';
requireAdmin();

// Xử lý thêm/sửa
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add']) || isset($_POST['edit'])) {
        $id = isset($_POST['edit']) ? (int)$_POST['id'] : 0;
        $name = sanitize($_POST['name']);
        $description = sanitize($_POST['description']);
        $status = $_POST['status'];
        $slug = generateSlug($name);
        
        // Upload logo
        $logo = null;
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $logo = uploadFile($_FILES['logo'], 'brands');
        }
        
        try {
            if ($id > 0) {
                // Sửa
                if ($logo) {
                    // Xóa logo cũ
                    $old = $conn->prepare("SELECT logo FROM brands WHERE id = ?");
                    $old->execute([$id]);
                    $old_logo = $old->fetch();
                    if ($old_logo && $old_logo['logo']) {
                        @unlink('../' . $old_logo['logo']);
                    }
                    
                    $stmt = $conn->prepare("UPDATE brands SET name = ?, slug = ?, description = ?, logo = ?, status = ? WHERE id = ?");
                    $stmt->execute([$name, $slug, $description, $logo, $status, $id]);
                } else {
                    $stmt = $conn->prepare("UPDATE brands SET name = ?, slug = ?, description = ?, status = ? WHERE id = ?");
                    $stmt->execute([$name, $slug, $description, $status, $id]);
                }
                flashMessage('success', 'Cập nhật thương hiệu thành công');
            } else {
                // Thêm mới
                $stmt = $conn->prepare("INSERT INTO brands (name, slug, description, logo, status) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $slug, $description, $logo, $status]);
                flashMessage('success', 'Thêm thương hiệu thành công');
            }
        } catch (Exception $e) {
            flashMessage('error', 'Lỗi: ' . $e->getMessage());
        }
        
        redirect(ADMIN_URL . 'brands.php');
    }
}

// Xử lý xóa
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    try {
        // Kiểm tra có sản phẩm không
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE brand_id = ?");
        $stmt->execute([$id]);
        $count = $stmt->fetch()['count'];
        
        if ($count > 0) {
            flashMessage('error', 'Không thể xóa thương hiệu có sản phẩm');
        } else {
            // Xóa logo
            $stmt = $conn->prepare("SELECT logo FROM brands WHERE id = ?");
            $stmt->execute([$id]);
            $brand = $stmt->fetch();
            if ($brand && $brand['logo']) {
                @unlink('../' . $brand['logo']);
            }
            
            $conn->prepare("DELETE FROM brands WHERE id = ?")->execute([$id]);
            flashMessage('success', 'Xóa thương hiệu thành công');
        }
    } catch (Exception $e) {
        flashMessage('error', 'Lỗi: ' . $e->getMessage());
    }
    
    redirect(ADMIN_URL . 'brands.php');
}

// Lấy danh sách thương hiệu
$brands = $conn->query("
    SELECT b.*, 
           (SELECT COUNT(*) FROM products WHERE brand_id = b.id) as product_count
    FROM brands b
    ORDER BY b.name ASC
")->fetchAll();

$pageTitle = 'Quản lý thương hiệu';
include 'includes/admin_header.php';
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Quản lý thương hiệu</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#brandModal">
            <i class="bi bi-plus-circle"></i> Thêm thương hiệu
        </button>
    </div>

    <!-- Brands Grid -->
    <div class="row g-4">
        <?php if (empty($brands)): ?>
        <div class="col-12">
            <div class="card">
                <div class="card-body text-center py-5 text-muted">
                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                    <p>Chưa có thương hiệu nào</p>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#brandModal">
                        Thêm thương hiệu đầu tiên
                    </button>
                </div>
            </div>
        </div>
        <?php else: ?>
            <?php foreach ($brands as $brand): ?>
            <div class="col-md-6 col-lg-4 col-xl-3">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <?php if ($brand['logo']): ?>
                            <img src="../<?= htmlspecialchars($brand['logo']) ?>" 
                                 alt="<?= htmlspecialchars($brand['name']) ?>"
                                 style="max-width: 150px; max-height: 100px; object-fit: contain;"
                                 class="mb-3"
                                 onerror="this.onerror=null; this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22150%22 height=%22100%22><rect width=%22150%22 height=%22100%22 fill=%22%23f0f0f0%22/><text x=%2250%%22 y=%2250%%22 text-anchor=%22middle%22 dy=%22.3em%22 fill=%22%23999%22>No Image</text></svg>';">
                        <?php else: ?>
                            <div class="bg-light rounded d-flex align-items-center justify-content-center mb-3"
                                 style="height: 100px;">
                                <i class="bi bi-image text-muted fs-1"></i>
                            </div>
                        <?php endif; ?>
                        
                        <h5 class="card-title"><?= htmlspecialchars($brand['name']) ?></h5>
                        
                        <?php if ($brand['description']): ?>
                            <p class="card-text text-muted small">
                                <?= htmlspecialchars(substr($brand['description'], 0, 100)) ?>
                                <?= strlen($brand['description']) > 100 ? '...' : '' ?>
                            </p>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <span class="badge bg-info"><?= $brand['product_count'] ?> sản phẩm</span>
                            <?php if ($brand['status'] === 'active'): ?>
                                <span class="badge bg-success">Hiển thị</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Ẩn</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button class="btn btn-sm btn-outline-primary" 
                                    onclick="editBrand(<?= htmlspecialchars(json_encode($brand)) ?>)">
                                <i class="bi bi-pencil"></i> Sửa
                            </button>
                            <a href="?delete=<?= $brand['id'] ?>" 
                               class="btn btn-sm btn-outline-danger"
                               onclick="return confirm('Bạn có chắc muốn xóa thương hiệu này?')">
                                <i class="bi bi-trash"></i> Xóa
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Thêm/Sửa -->
<div class="modal fade" id="brandModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data" id="brandForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Thêm thương hiệu</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="brandId">
                    
                    <div class="mb-3">
                        <label class="form-label">Tên thương hiệu <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="brandName" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Mô tả</label>
                        <textarea name="description" id="brandDesc" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Logo</label>
                        <input type="file" name="logo" class="form-control" accept="image/*">
                        <img id="currentLogo" class="mt-2 rounded" style="max-width: 100px; display: none;">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Trạng thái</label>
                        <select name="status" id="brandStatus" class="form-select">
                            <option value="active">Hiển thị</option>
                            <option value="inactive">Ẩn</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                    <button type="submit" name="add" id="submitBtn" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> Lưu
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editBrand(brand) {
    document.getElementById('modalTitle').textContent = 'Sửa thương hiệu';
    document.getElementById('brandId').value = brand.id;
    document.getElementById('brandName').value = brand.name;
    document.getElementById('brandDesc').value = brand.description || '';
    document.getElementById('brandStatus').value = brand.status;
    
    const logo = document.getElementById('currentLogo');
    if (brand.logo) {
        logo.src = '../' + brand.logo;
        logo.style.display = 'block';
    } else {
        logo.style.display = 'none';
    }
    
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.name = 'edit';
    submitBtn.innerHTML = '<i class="bi bi-check-circle"></i> Cập nhật';
    
    new bootstrap.Modal(document.getElementById('brandModal')).show();
}

document.getElementById('brandModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('brandForm').reset();
    document.getElementById('modalTitle').textContent = 'Thêm thương hiệu';
    document.getElementById('brandId').value = '';
    document.getElementById('currentLogo').style.display = 'none';
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.name = 'add';
    submitBtn.innerHTML = '<i class="bi bi-check-circle"></i> Lưu';
});
</script>

<?php include 'includes/admin_footer.php'; ?>