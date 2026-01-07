<?php
require_once '../config.php';
requireAdmin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_edit = $id > 0;

// Lấy thông tin sản phẩm nếu edit
$product = null;
$product_images = [];
$variants = [];

if ($is_edit) {
    $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        flashMessage('error', 'Sản phẩm không tồn tại');
        redirect(ADMIN_URL . 'products.php');
    }
    
    // Lấy hình ảnh
    $stmt = $conn->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order");
    $stmt->execute([$id]);
    $product_images = $stmt->fetchAll();
    
    // Lấy variants
    $stmt = $conn->prepare("
        SELECT pv.*, c.name as color_name, s.name as size_name
        FROM product_variants pv
        LEFT JOIN colors c ON pv.color_id = c.id
        LEFT JOIN sizes s ON pv.size_id = s.id
        WHERE pv.product_id = ?
    ");
    $stmt->execute([$id]);
    $variants = $stmt->fetchAll();
}

// Xử lý form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $sku = sanitize($_POST['sku']);
    $category_id = (int)$_POST['category_id'];
    $brand_id = (int)$_POST['brand_id'];
    $price = (float)$_POST['price'];
    $sale_price = !empty($_POST['sale_price']) ? (float)$_POST['sale_price'] : null;
    $short_description = sanitize($_POST['short_description']);
    $description = sanitize($_POST['description']);
    $status = $_POST['status'];
    $featured = isset($_POST['featured']) ? 1 : 0;
    
    $slug = generateSlug($name);
    
    // Validate
    $errors = [];
    if (empty($name)) $errors[] = 'Tên sản phẩm không được trống';
    if ($price <= 0) $errors[] = 'Giá phải lớn hơn 0';
    if ($sale_price && $sale_price >= $price) $errors[] = 'Giá sale phải nhỏ hơn giá gốc';
    
    // Kiểm tra SKU trùng
    if (!empty($sku)) {
        $stmt = $conn->prepare("SELECT id FROM products WHERE sku = ? AND id != ?");
        $stmt->execute([$sku, $id]);
        if ($stmt->fetch()) {
            $errors[] = 'SKU đã tồn tại';
        }
    }
    
    if (!empty($errors)) {
        flashMessage('error', implode('<br>', $errors));
    } else {
        try {
            $conn->beginTransaction();
            
            if ($is_edit) {
                // Cập nhật
                $stmt = $conn->prepare("
                    UPDATE products SET
                        name = ?, slug = ?, sku = ?, category_id = ?, brand_id = ?,
                        price = ?, sale_price = ?, short_description = ?, description = ?,
                        status = ?, featured = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $name, $slug, $sku, $category_id, $brand_id,
                    $price, $sale_price, $short_description, $description,
                    $status, $featured, $id
                ]);
                $product_id = $id;
            } else {
                // Thêm mới
                $stmt = $conn->prepare("
                    INSERT INTO products (
                        name, slug, sku, category_id, brand_id,
                        price, sale_price, short_description, description,
                        status, featured
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $name, $slug, $sku, $category_id, $brand_id,
                    $price, $sale_price, $short_description, $description,
                    $status, $featured
                ]);
                $product_id = $conn->lastInsertId();
            }
            
            // Upload hình ảnh
            if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
                foreach ($_FILES['images']['name'] as $key => $filename) {
                    if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                        $file = [
                            'name' => $_FILES['images']['name'][$key],
                            'type' => $_FILES['images']['type'][$key],
                            'tmp_name' => $_FILES['images']['tmp_name'][$key],
                            'error' => $_FILES['images']['error'][$key],
                            'size' => $_FILES['images']['size'][$key]
                        ];
                        
                        $uploaded = uploadFile($file, 'products');
                        if ($uploaded) {
                            $is_main = empty($product_images) && $key === 0 ? 1 : 0;
                            $stmt = $conn->prepare("
                                INSERT INTO product_images (product_id, image_url, is_main, sort_order)
                                VALUES (?, ?, ?, ?)
                            ");
                            $stmt->execute([$product_id, $uploaded, $is_main, $key]);
                        }
                    }
                }
            }
            
            // Xử lý variants
            if (isset($_POST['variants'])) {
                // Xóa variants cũ
                $conn->prepare("DELETE FROM product_variants WHERE product_id = ?")->execute([$product_id]);
                
                foreach ($_POST['variants'] as $v) {
                    if (!empty($v['color_id']) || !empty($v['size_id'])) {
                        $stmt = $conn->prepare("
                            INSERT INTO product_variants (product_id, color_id, size_id, stock_quantity)
                            VALUES (?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $product_id,
                            !empty($v['color_id']) ? $v['color_id'] : null,
                            !empty($v['size_id']) ? $v['size_id'] : null,
                            (int)$v['stock']
                        ]);
                    }
                }
            }
            
            $conn->commit();
            flashMessage('success', $is_edit ? 'Cập nhật sản phẩm thành công' : 'Thêm sản phẩm thành công');
            redirect(ADMIN_URL . 'product_form.php?id=' . $product_id);
            
        } catch (Exception $e) {
            $conn->rollBack();
            flashMessage('error', 'Lỗi: ' . $e->getMessage());
        }
    }
}

// Lấy danh mục, thương hiệu, màu, size
$categories = $conn->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$brands = $conn->query("SELECT * FROM brands ORDER BY name")->fetchAll();
$colors = $conn->query("SELECT * FROM colors ORDER BY name")->fetchAll();
$sizes = $conn->query("SELECT * FROM sizes ORDER BY name")->fetchAll();

$pageTitle = $is_edit ? 'Sửa sản phẩm' : 'Thêm sản phẩm';
include 'includes/admin_header.php';
?>

<div class="container-fluid mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><?= $is_edit ? 'Sửa sản phẩm' : 'Thêm sản phẩm mới' ?></h2>
        <a href="<?= ADMIN_URL ?>products.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Quay lại
        </a>
    </div>

    <form method="POST" enctype="multipart/form-data">
        <div class="row">
            <!-- Thông tin chính -->
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Thông tin sản phẩm</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Tên sản phẩm <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" 
                                   value="<?= $product ? htmlspecialchars($product['name']) : '' ?>" 
                                   required>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">SKU</label>
                                <input type="text" name="sku" class="form-control" 
                                       value="<?= $product ? htmlspecialchars($product['sku']) : '' ?>"
                                       placeholder="VD: NIKE-AIR-001">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Thương hiệu</label>
                                <select name="brand_id" class="form-select">
                                    <option value="">Chọn thương hiệu</option>
                                    <?php foreach ($brands as $brand): ?>
                                    <option value="<?= $brand['id'] ?>" 
                                            <?= $product && $product['brand_id'] == $brand['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($brand['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Mô tả ngắn</label>
                            <textarea name="short_description" class="form-control" rows="3" 
                                      maxlength="500"><?= $product ? htmlspecialchars($product['short_description']) : '' ?></textarea>
                            <small class="text-muted">Tối đa 500 ký tự</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Mô tả chi tiết</label>
                            <textarea name="description" class="form-control" rows="8"><?= $product ? htmlspecialchars($product['description']) : '' ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Hình ảnh -->
                <div class="card mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Hình ảnh sản phẩm</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($product_images)): ?>
                        <div class="mb-3">
                            <label class="form-label">Hình ảnh hiện tại:</label>
                            <div class="d-flex gap-2 flex-wrap">
                                <?php foreach ($product_images as $img): ?>
                                <div class="position-relative">
                                    <img src="<?= UPLOAD_URL . $img['image_url'] ?>" 
                                         style="width: 100px; height: 100px; object-fit: cover;"
                                         class="rounded">
                                    <?php if ($img['is_main']): ?>
                                        <span class="position-absolute top-0 start-0 badge bg-success m-1">Chính</span>
                                    <?php endif; ?>
                                    <a href="delete_image.php?id=<?= $img['id'] ?>&product_id=<?= $id ?>" 
                                       class="position-absolute top-0 end-0 btn btn-sm btn-danger m-1"
                                       onclick="return confirm('Xóa ảnh này?')">
                                        <i class="bi bi-x"></i>
                                    </a>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label">Thêm hình ảnh mới:</label>
                            <input type="file" name="images[]" class="form-control" 
                                   accept="image/*" multiple>
                            <small class="text-muted">
                                Có thể chọn nhiều ảnh. Ảnh đầu tiên sẽ là ảnh chính.
                                Định dạng: JPG, PNG, GIF. Tối đa 2MB/ảnh.
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Biến thể (Màu, Size, Tồn kho) -->
                <div class="card">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Biến thể sản phẩm</h5>
                        <button type="button" class="btn btn-sm btn-primary" onclick="addVariant()">
                            <i class="bi bi-plus"></i> Thêm biến thể
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="variantsContainer">
                            <?php if (!empty($variants)): ?>
                                <?php foreach ($variants as $index => $v): ?>
                                <div class="variant-row mb-3 pb-3 border-bottom">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <label class="form-label">Màu sắc</label>
                                            <select name="variants[<?= $index ?>][color_id]" class="form-select">
                                                <option value="">Không chọn</option>
                                                <?php foreach ($colors as $color): ?>
                                                <option value="<?= $color['id'] ?>" 
                                                        <?= $v['color_id'] == $color['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($color['name']) ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Kích thước</label>
                                            <select name="variants[<?= $index ?>][size_id]" class="form-select">
                                                <option value="">Không chọn</option>
                                                <?php foreach ($sizes as $size): ?>
                                                <option value="<?= $size['id'] ?>" 
                                                        <?= $v['size_id'] == $size['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($size['name']) ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Tồn kho</label>
                                            <input type="number" name="variants[<?= $index ?>][stock]" 
                                                   class="form-control" value="<?= $v['stock_quantity'] ?>" min="0">
                                        </div>
                                        <div class="col-md-2 d-flex align-items-end">
                                            <button type="button" class="btn btn-danger w-100" 
                                                    onclick="removeVariant(this)">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted">Chưa có biến thể. Nhấn "Thêm biến thể" để thêm.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Giá -->
                <div class="card mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Giá bán</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Giá gốc <span class="text-danger">*</span></label>
                            <input type="number" name="price" class="form-control" 
                                   value="<?= $product ? $product['price'] : '' ?>" 
                                   min="0" step="1000" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Giá khuyến mãi</label>
                            <input type="number" name="sale_price" class="form-control" 
                                   value="<?= $product && $product['sale_price'] ? $product['sale_price'] : '' ?>" 
                                   min="0" step="1000">
                            <small class="text-muted">Để trống nếu không có giảm giá</small>
                        </div>
                    </div>
                </div>

                <!-- Danh mục & Trạng thái -->
                <div class="card mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Phân loại</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Danh mục <span class="text-danger">*</span></label>
                            <select name="category_id" class="form-select" required>
                                <option value="">Chọn danh mục</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" 
                                        <?= $product && $product['category_id'] == $cat['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Trạng thái</label>
                            <select name="status" class="form-select">
                                <option value="active" <?= !$product || $product['status'] === 'active' ? 'selected' : '' ?>>
                                    Hoạt động
                                </option>
                                <option value="inactive" <?= $product && $product['status'] === 'inactive' ? 'selected' : '' ?>>
                                    Ẩn
                                </option>
                            </select>
                        </div>

                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="featured" 
                                   id="featured" value="1" 
                                   <?= $product && $product['featured'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="featured">
                                Sản phẩm nổi bật
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="card">
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-check-circle"></i>
                                <?= $is_edit ? 'Cập nhật' : 'Thêm mới' ?>
                            </button>
                            <?php if ($is_edit): ?>
                            <a href="<?= BASE_URL ?>product_detail.php?slug=<?= $product['slug'] ?>" 
                               class="btn btn-outline-info" target="_blank">
                                <i class="bi bi-eye"></i> Xem sản phẩm
                            </a>
                            <?php endif; ?>
                            <a href="<?= ADMIN_URL ?>products.php" class="btn btn-outline-secondary">
                                Hủy
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
let variantIndex = <?= !empty($variants) ? count($variants) : 0 ?>;

function addVariant() {
    const container = document.getElementById('variantsContainer');
    const html = `
        <div class="variant-row mb-3 pb-3 border-bottom">
            <div class="row">
                <div class="col-md-4">
                    <label class="form-label">Màu sắc</label>
                    <select name="variants[${variantIndex}][color_id]" class="form-select">
                        <option value="">Không chọn</option>
                        <?php foreach ($colors as $color): ?>
                        <option value="<?= $color['id'] ?>"><?= htmlspecialchars($color['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Kích thước</label>
                    <select name="variants[${variantIndex}][size_id]" class="form-select">
                        <option value="">Không chọn</option>
                        <?php foreach ($sizes as $size): ?>
                        <option value="<?= $size['id'] ?>"><?= htmlspecialchars($size['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tồn kho</label>
                    <input type="number" name="variants[${variantIndex}][stock]" 
                           class="form-control" value="0" min="0">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="button" class="btn btn-danger w-100" onclick="removeVariant(this)">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', html);
    variantIndex++;
}

function removeVariant(btn) {
    btn.closest('.variant-row').remove();
}
</script>

<?php include 'includes/admin_footer.php'; ?>