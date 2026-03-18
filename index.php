<?php
include 'plugins/conn.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Tracking System - Login</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/custom.css">
</head>
<body>
    <div class="container-fluid h-100">
        <div class="row h-100">
            <!-- Left Panel -->
            <div class="col-lg-8 d-none d-lg-flex flex-column justify-content-center px-5 left-panel">
                <div class="position-absolute top-0 start-0 p-5">
                     <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center me-2" style="width: 40px; height: 40px;">
                            <i class="bi bi-file-earmark-text fs-5"></i>
                        </div>
                        <span class="fw-bold h5 mb-0">DTS System</span>
                     </div>
                </div>
                
                <div class="px-5">
                    <h1 class="display-4 fw-bold mb-3">Document Tracking System</h1>
                    <h3 class="h2 mb-4 text-info">Commission on Population and Development</h3>
                    <p class="lead mb-4">
                        Streamline your document management process with our secure and efficient tracking system.
                        Monitor document flow, ensure accountability, and enhance productivity across the organization.
                    </p>
                    <div class="feature-list mt-5">
                        <div class="d-flex align-items-center mb-3">
                            <i class="bi bi-check-circle-fill"></i>
                            <span>Real-time Document Tracking</span>
                        </div>
                        <div class="d-flex align-items-center mb-3">
                            <i class="bi bi-shield-lock-fill"></i>
                            <span>Secure Access Control</span>
                        </div>
                        <div class="d-flex align-items-center">
                            <i class="bi bi-graph-up-arrow"></i>
                            <span>Efficient Workflow Management</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Panel (Login) -->
            <div class="col-lg-4 d-flex align-items-center justify-content-center right-panel">
                <div class="login-card p-4">
                    <div class="text-center mb-4">
                        <!-- Logo Section: Replaced 2 logos with 1 CPD Logo -->
                        <img src="assets/img/473022962_535116549689015_1606086345882705132_n.jpg" 
                             alt="Commission on Population and Development Logo" 
                             class="logo-placeholder mb-3">
                        
                        <h2 class="fw-bold mt-2">Welcome Back</h2>
                        <p class="text-muted">Sign in to access the Admin Portal</p>
                    </div>

                    <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($_GET['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php endif; ?>

                    <form action="login.php" method="POST">
                        <div class="mb-3">
                            <label for="username" class="form-label text-muted small fw-bold">Username</label>
                            <input type="text" class="form-control" id="username" name="username" placeholder="Enter your username" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label text-muted small fw-bold">Password</label>
                            <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="remember">
                                <label class="form-check-label small text-muted" for="remember">Remember me</label>
                            </div>
                            <a href="#" class="small text-decoration-none">Forgot password?</a>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">Sign In</button>
                    </form>

                    <div class="text-center mt-4">
                        <p class="small text-muted mb-0">Don't have an account? <a href="#" class="text-decoration-none">Contact your administrator</a></p>
                        <p class="small text-muted mt-4">&copy; <?php echo date("Y"); ?> Document Tracking System</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>