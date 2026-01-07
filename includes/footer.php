<footer class="mt-5 py-5">
        <div class="container">
            <div class="row">
                <div class="col-md-3 mb-4">
                    <h5 class="text-uppercase mb-3">
                        <i class="bi bi-shop"></i> SHOE SHOP
                    </h5>
                    <p class="small">
                        Chuyên cung cấp giày dép chính hãng với giá tốt nhất thị trường.
                        Đa dạng mẫu mã, chất lượng đảm bảo.
                    </p>
                    <div class="social-links mt-3">
                        <a href="#" class="text-white me-3"><i class="bi bi-facebook fs-4"></i></a>
                        <a href="#" class="text-white me-3"><i class="bi bi-instagram fs-4"></i></a>
                        <a href="#" class="text-white me-3"><i class="bi bi-youtube fs-4"></i></a>
                        <a href="#" class="text-white"><i class="bi bi-tiktok fs-4"></i></a>
                    </div>
                </div>
                
                <div class="col-md-3 mb-4">
                    <h6 class="text-uppercase mb-3">Về chúng tôi</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#" class="text-white-50 text-decoration-none">Giới thiệu</a></li>
                        <li class="mb-2"><a href="#" class="text-white-50 text-decoration-none">Liên hệ</a></li>
                        <li class="mb-2"><a href="#" class="text-white-50 text-decoration-none">Tuyển dụng</a></li>
                        <li class="mb-2"><a href="#" class="text-white-50 text-decoration-none">Tin tức</a></li>
                    </ul>
                </div>
                
                <div class="col-md-3 mb-4">
                    <h6 class="text-uppercase mb-3">Chính sách</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#" class="text-white-50 text-decoration-none">Chính sách đổi trả</a></li>
                        <li class="mb-2"><a href="#" class="text-white-50 text-decoration-none">Chính sách bảo mật</a></li>
                        <li class="mb-2"><a href="#" class="text-white-50 text-decoration-none">Điều khoản sử dụng</a></li>
                        <li class="mb-2"><a href="#" class="text-white-50 text-decoration-none">Hướng dẫn mua hàng</a></li>
                    </ul>
                </div>
                
                <div class="col-md-3 mb-4">
                    <h6 class="text-uppercase mb-3">Liên hệ</h6>
                    <ul class="list-unstyled small">
                        <li class="mb-2">
                            <i class="bi bi-geo-alt"></i>
                            Quận Bắc Từ Liêm,Hà Nội
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-telephone"></i>
                            <a href="tel:1900xxxx" class="text-white-50 text-decoration-none">1900-xxxx</a>
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-envelope"></i>
                            <a href="mailto:support@shoeshop.com" class="text-white-50 text-decoration-none">
                                quynhhuong@shoeshop.com
                            </a>
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-clock"></i>
                            Thứ 2 - CN: 8:00 - 22:00
                        </li>
                    </ul>
                </div>
            </div>
            
            <hr class="border-secondary my-4">
            
            <div class="row align-items-center">
                <div class="col-md-6 text-center text-md-start">
                    <p class="small mb-0">
                        © <?= date('Y') ?> Shoe Shop. All rights reserved.
                    </p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <p class="small mb-0">
                        <i class="bi bi-credit-card"></i>
                        <i class="bi bi-paypal ms-2"></i>
                        <i class="bi bi-wallet2 ms-2"></i>
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Auto hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });
    </script>
</body>
</html>