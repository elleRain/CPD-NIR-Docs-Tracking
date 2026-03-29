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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --brand-blue: #0f3c8a;
            --brand-blue-deep: #072356;
            --brand-gold: #f3b719;
            --ink: #101828;
            --muted: #667085;
            --surface: #ffffff;
            --line: #dbe3f2;
        }

        body {
            min-height: 100vh;
            font-family: 'Sora', sans-serif;
            color: var(--ink);
            background:
                radial-gradient(circle at 8% 15%, rgba(15, 60, 138, 0.15), transparent 40%),
                radial-gradient(circle at 85% 20%, rgba(243, 183, 25, 0.22), transparent 36%),
                linear-gradient(160deg, #eef3fb 0%, #f7f9fe 45%, #eef6ff 100%);
        }

        .landing-wrap {
            min-height: 100vh;
        }

        .hero-panel {
            position: relative;
            background:
                radial-gradient(circle at 20% 20%, rgba(93, 141, 232, 0.28), transparent 38%),
                radial-gradient(circle at 82% 18%, rgba(243, 183, 25, 0.2), transparent 34%),
                linear-gradient(145deg, rgba(7, 35, 86, 0.96), rgba(15, 60, 138, 0.94));
            color: #fff;
            overflow: hidden;
        }

        .hero-panel::after {
            box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.04) inset;
        }

        .hero-grid {
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(255, 255, 255, 0.05) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.05) 1px, transparent 1px);
            background-size: 42px 42px;
            opacity: 0.35;
            pointer-events: none;
        }

        .hero-panel::before,
        .hero-panel::after {
            content: "";
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.08);
            pointer-events: none;
        }

        .hero-panel::before {
            width: 260px;
            height: 260px;
            top: -90px;
            right: -50px;
        }

        .hero-panel::after {
            width: 220px;
            height: 220px;
            left: -80px;
            bottom: -60px;
        }

        .brand-chip {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.18);
            border: 1px solid rgba(255, 255, 255, 0.35);
        }

        .hero-content {
            max-width: 620px;
            position: relative;
            z-index: 1;
        }

        .hero-title {
            font-weight: 800;
            letter-spacing: -0.02em;
            line-height: 1.08;
        }

        .hero-subtitle {
            color: #d4e4ff;
            font-weight: 500;
            max-width: 40ch;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            font-weight: 500;
            color: #f7fbff;
            margin-bottom: 0.8rem;
        }

        .feature-item i {
            color: var(--brand-gold);
            font-size: 1.05rem;
        }

        .login-panel {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2.5rem 1.2rem;
        }

        .login-card {
            width: 100%;
            max-width: 440px;
            border-radius: 22px;
            border: 1px solid var(--line);
            background: var(--surface);
            box-shadow: 0 22px 58px rgba(12, 32, 75, 0.14);
            position: relative;
            overflow: hidden;
        }

        .login-card::before {
            content: "";
            position: absolute;
            top: -70px;
            right: -70px;
            width: 170px;
            height: 170px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(15, 60, 138, 0.14), rgba(15, 60, 138, 0));
            pointer-events: none;
        }

        .login-logo {
            width: 124px;
            height: 124px;
            object-fit: contain;
            background: #ffffff;
            border-radius: 18px;
            border: 1px solid #d4dff3;
            padding: 8px;
            box-shadow: 0 10px 24px rgba(0, 0, 0, 0.12);
        }

        .form-control {
            border-radius: 12px;
            border-color: #d2dced;
            padding: 0.7rem 0.85rem;
            font-size: 0.95rem;
            background: #fbfcff;
        }

        .form-control:focus {
            border-color: #7ca0db;
            box-shadow: 0 0 0 0.2rem rgba(15, 60, 138, 0.15);
        }

        .btn-brand {
            background: linear-gradient(135deg, #0b2f73, #0f4db5);
            border: 1px solid #0a2a66;
            border-radius: 12px;
            padding: 0.78rem 1rem;
            font-weight: 700;
            letter-spacing: 0.01em;
            color: #ffffff;
            box-shadow: 0 12px 26px rgba(7, 35, 86, 0.3);
        }

        .btn-brand:hover {
            background: linear-gradient(135deg, #08265c, #0b3f95);
            color: #ffffff;
        }

        .helper-link {
            color: var(--brand-blue);
        }

        .helper-link:hover {
            color: var(--brand-blue-deep);
        }

        .login-title {
            font-weight: 800;
            letter-spacing: -0.02em;
        }

        .form-label {
            color: #344054 !important;
            margin-bottom: 0.42rem;
        }

        .field-with-icon {
            position: relative;
        }

        .field-with-icon .bi {
            position: absolute;
            left: 0.85rem;
            top: 50%;
            transform: translateY(-50%);
            color: #7a8aa7;
            font-size: 0.95rem;
            pointer-events: none;
        }

        .field-with-icon .form-control {
            padding-left: 2.45rem;
        }

        @media (max-width: 991.98px) {
            .login-panel {
                padding: 2rem 0.9rem;
            }

            .login-card {
                max-width: 500px;
            }

            .hero-grid {
                display: none;
            }
        }

        @media (prefers-reduced-motion: no-preference) {
            .login-card,
            .hero-content {
                animation: fadeUp 500ms ease-out both;
            }

            @keyframes fadeUp {
                from {
                    opacity: 0;
                    transform: translateY(10px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row landing-wrap">
            <!-- Left Panel -->
            <div class="col-lg-8 d-none d-lg-flex flex-column justify-content-center px-5 hero-panel">
                <div class="hero-grid"></div>
                <div class="position-absolute top-0 start-0 p-5">
                     <div class="d-flex align-items-center">
                        <div class="brand-chip d-flex align-items-center justify-content-center me-2">
                            <i class="bi bi-file-earmark-text fs-5"></i>
                        </div>
                        <span class="fw-bold h5 mb-0">DTS System</span>
                     </div>
                </div>
                
                <div class="px-5 hero-content">
                    <h1 class="hero-title display-4 mb-3">Document Tracking System</h1>
                    <h3 class="h2 mb-4 hero-subtitle">Commission on Population and Development</h3>
                    <p class="lead mb-4 text-white-50">
                        Streamline your document management process with our secure and efficient tracking system.
                        Monitor document flow, ensure accountability, and enhance productivity across the organization.
                    </p>
                    <div class="mt-5">
                        <div class="feature-item">
                            <i class="bi bi-check-circle-fill"></i>
                            <span>Real-time Document Tracking</span>
                        </div>
                        <div class="feature-item">
                            <i class="bi bi-shield-lock-fill"></i>
                            <span>Secure Access Control</span>
                        </div>
                        <div class="feature-item mb-0">
                            <i class="bi bi-graph-up-arrow"></i>
                            <span>Efficient Workflow Management</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Panel (Login) -->
            <div class="col-lg-4 login-panel">
                <div class="login-card p-4">
                    <div class="text-center mb-4">
                            <img src="assets/img/logo%20no%20bg.png" 
                             alt="Commission on Population and Development Logo" 
                             class="login-logo mb-3">
                        
                        <h2 class="login-title mt-2">Welcome Back</h2>
                        <p class="text-muted mb-0">Sign in to access the CPD-NIR Document Tracking Portal</p>
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
                            <div class="field-with-icon">
                                <i class="bi bi-person"></i>
                                <input type="text" class="form-control" id="username" name="username" placeholder="Enter your username" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label text-muted small fw-bold">Password</label>
                            <div class="field-with-icon">
                                <i class="bi bi-lock"></i>
                                <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="remember">
                                <label class="form-check-label small text-muted" for="remember">Remember me</label>
                            </div>
                            <a href="#" class="small text-decoration-none helper-link">Forgot password?</a>
                        </div>

                        <button type="submit" class="btn btn-brand w-100">Sign In</button>
                    </form>

                    <div class="text-center mt-4">
                        <p class="small text-muted mb-0">Don't have an account? <a href="#" class="text-decoration-none helper-link">Contact your administrator</a></p>
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