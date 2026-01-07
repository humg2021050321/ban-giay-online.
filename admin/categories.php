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
        
        // Upload image
        $image = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $image = uploadFile($_FILES['image'], 'categories');
        }
        
        try {
            if ($id > 0) {
                // Sửa
                if ($image) {
                    // Xóa ảnh cũ
                    $old = $conn->prepare("SELECT image FROM categories WHERE id = ?");
                    $old->execute([$id]);
                    $old_img = $old->fetch();
                    if ($old_img && $old_img['image']) {
                        @unlink('../' . $old_img['image']);
                    }
                    
                    $stmt = $conn->prepare("UPDATE categories SET name = ?, slug = ?, description = ?, image = ?, status = ? WHERE id = ?");
                    $stmt->execute([$name, $slug, $description, $image, $status, $id]);
                } else {
                    $stmt = $conn->prepare("UPDATE categories SET name = ?, slug = ?, description = ?, status = ? WHERE id = ?");
                    $stmt->execute([$name, $slug, $description, $status, $id]);
                }
                flashMessage('success', 'Cập nhật danh mục thành công');
            } else {
                // Thêm mới
                $stmt = $conn->prepare("INSERT INTO categories (name, slug, description, image, status) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $slug, $description, $image, $status]);
                flashMessage('success', 'Thêm danh mục thành công');
            }
        } catch (Exception $e) {
            flashMessage('error', 'Lỗi: ' . $e->getMessage());
        }
        
        redirect(ADMIN_URL . 'categories.php');
    }
}

// Xử lý xóa
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    try {
        // Kiểm tra có sản phẩm không
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE category_id = ?");
        $stmt->execute([$id]);
        $count = $stmt->fetch()['count'];
        
        if ($count > 0) {
            flashMessage('error', 'Không thể xóa danh mục có sản phẩm');
        } else {
            // Xóa ảnh
            $stmt = $conn->prepare("SELECT image FROM categories WHERE id = ?");
            $stmt->execute([$id]);
            $cat = $stmt->fetch();
            if ($cat && $cat['image']) {
                @unlink('../' . $cat['image']);
            }
            
            $conn->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);
            flashMessage('success', 'Xóa danh mục thành công');
        }
    } catch (Exception $e) {
        flashMessage('error', 'Lỗi: ' . $e->getMessage());
    }
    
    redirect(ADMIN_URL . 'categories.php');
}

// Lấy danh sách danh mục
$categories = $conn->query("
    SELECT c.*, 
           (SELECT COUNT(*) FROM products WHERE category_id = c.id) as product_count
    FROM categories c
    ORDER BY c.name ASC
")->fetchAll();

$pageTitle = 'Quản lý danh mục';
include 'includes/admin_header.php';
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Quản lý danh mục</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#categoryModal">
            <i class="bi bi-plus-circle"></i> Thêm danh mục
        </button>
    </div>

    <!-- Categories Table -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 80px;">Ảnh</th>
                            <th>Tên danh mục</th>
                            <th>Slug</th>
                            <th>Số sản phẩm</th>
                            <th>Trạng thái</th>
                            <th style="width: 150px;">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($categories)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                Chưa có danh mục nào
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($categories as $cat): ?>
                            <tr>
                                <td>
                                    <?php if ($cat['image']): ?>
                                        <img src="../<?= htmlspecialchars($cat['image']) ?>" 
                                             style="width: 60px; height: 60px; object-fit: cover;"
                                             class="rounded"
                                             onerror="this.onerror=null; this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2260%22 height=%2260%22><rect width=%2260%22 height=%2260%22 fill=%22%23f0f0f0%22/><text x=%2250%%22 y=%2250%%22 text-anchor=%22middle%22 dy=%22.3em%22 fill=%22%23999%22 font-size=%2210%22>No Image</text></svg>';">
                                    <?php else: ?>
                                        <div style="width: 60px; height: 60px;" 
                                             class="bg-light rounded d-flex align-items-center justify-content-center">
                                            <i class="bi bi-image text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($cat['name']) ?></strong>
                                    <?php if ($cat['description']): ?>
                                        <div class="text-muted small"><?= htmlspecialchars($cat['description']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><code><?= htmlspecialchars($cat['slug']) ?></code></td>
                                <td>
                                    <span class="badge bg-info"><?= $cat['product_count'] ?> sản phẩm</span>
                                </td>
                                <td>
                                    <?php if ($cat['status'] === 'active'): ?>
                                        <span class="badge bg-success">Hiển thị</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Ẩn</span>
                                    <?php endif; ?>
                                </td>
                                <td class="table-actions">
                                    <button class="btn btn-sm btn-outline-primary" 
                                            onclick="editCategory(<?= htmlspecialchars(json_encode($cat)) ?>)">
                                        <i class="bi bi-pencil"></i> Sửa
                                    </button>
                                    <a href="?delete=<?= $cat['id'] ?>" 
                                       class="btn btn-sm btn-outline-danger"
                                       onclick="return confirm('Bạn có chắc muốn xóa danh mục này?')">
                                        <i class="bi bi-trash"></i> Xóa
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Thêm/Sửa -->
<div class="modal fade" id="categoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data" id="categoryForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Thêm danh mục</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="categoryId">
                    
                    <div class="mb-3">
                        <label class="form-label">Tên danh mục <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="categoryName" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Mô tả</label>
                        <textarea name="description" id="categoryDesc" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Hình ảnh</label>
                        <input type="file" name="image" class="form-control" accept="image/*">
                        <img id="currentImage" class="mt-2 rounded" style="max-width: 100px; display: none;">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Trạng thái</label>
                        <select name="status" id="categoryStatus" class="form-select">
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
function editCategory(cat) {
    document.getElementById('modalTitle').textContent = 'Sửa danh mục';
    document.getElementById('categoryId').value = cat.id;
    document.getElementById('categoryName').value = cat.name;
    document.getElementById('categoryDesc').value = cat.description || '';
    document.getElementById('categoryStatus').value = cat.status;
    
    const img = document.getElementById('currentImage');
    if (cat.image) {
        img.src = '../' + cat.image;
        img.style.display = 'block';
    } else {
        img.style.display = 'none';
    }
    
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.name = 'edit';
    submitBtn.innerHTML = '<i class="bi bi-check-circle"></i> Cập nhật';
    
    new bootstrap.Modal(document.getElementById('categoryModal')).show();
}

document.getElementById('categoryModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('categoryForm').reset();
    document.getElementById('modalTitle').textContent = 'Thêm danh mục';
    document.getElementById('categoryId').value = '';
    document.getElementById('currentImage').style.display = 'none';
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.name = 'add';
    submitBtn.innerHTML = '<i class="bi bi-check-circle"></i> Lưu';
});
</script>

<?php include 'includes/admin_footer.php'; ?>