<?php
session_start();
require __DIR__ . '/../plugins/conn.php';
require __DIR__ . '/../plugins/mailer.php';

$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$invite = null;
$error = '';
$success = '';
$username_error = '';
$fatal_invite_error = false;

$user_columns = [];
$cols_result = $conn->query('SHOW COLUMNS FROM users');
if ($cols_result) {
    while ($col = $cols_result->fetch_assoc()) {
        $user_columns[$col['Field']] = true;
    }
    $cols_result->free();
}
$has_profile_picture_col = isset($user_columns['profile_picture']);

if (isset($_GET['check_username']) && $_GET['check_username'] === '1') {
    header('Content-Type: application/json');
    $username_check = isset($_GET['username']) ? trim($_GET['username']) : '';
    if ($username_check === '') {
        echo json_encode(['success' => true, 'taken' => false]);
        exit;
    }
    $stmt_check = $conn->prepare('SELECT user_id FROM users WHERE username = ? LIMIT 1');
    if ($stmt_check) {
        $stmt_check->bind_param('s', $username_check);
        $stmt_check->execute();
        $stmt_check->store_result();
        $taken = $stmt_check->num_rows > 0;
        $stmt_check->close();
        echo json_encode(['success' => true, 'taken' => $taken]);
    } else {
        echo json_encode(['success' => false, 'taken' => false]);
    }
    exit;
}

if ($token !== '') {
    $stmt = $conn->prepare('SELECT id, email, full_name, role, expires_at, used_at FROM invitations WHERE token = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows === 1) {
            $invite = $result->fetch_assoc();
        }
        $stmt->close();
    }
}

if (!$invite) {
    $error = 'This registration link is invalid.';
    $fatal_invite_error = true;
} else {
    $now = new DateTimeImmutable();
    $expiresAt = new DateTimeImmutable($invite['expires_at']);

    if ($invite['used_at'] !== null) {
        $error = 'This registration link has already been used.';
        $fatal_invite_error = true;
    } elseif ($expiresAt <= $now) {
        $error = 'This registration link has expired.';
        $fatal_invite_error = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$fatal_invite_error && $invite) {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
    $middle_name = isset($_POST['middle_name']) ? trim($_POST['middle_name']) : '';
    $last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $address = isset($_POST['address']) ? trim($_POST['address']) : '';
    $password = isset($_POST['password']) ? (string)$_POST['password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? (string)$_POST['confirm_password'] : '';
    $profile_picture_name = '';

    if ($username === '' || $first_name === '' || $last_name === '' || $email === '' || $phone === '' || $address === '' || $password === '' || $confirm_password === '') {
        $error = 'Please fill in all required fields.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        $role = $invite['role'];
        if (!in_array($role, ['admin', 'staff'], true)) {
            $error = 'Invalid role for this invitation.';
        } else {
            if ($has_profile_picture_col && isset($_FILES['profile_picture']) && isset($_FILES['profile_picture']['error']) && $_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
                    $filename = $_FILES['profile_picture']['name'];
                    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                    $size = (int) $_FILES['profile_picture']['size'];
                    if (!in_array($ext, $allowed_ext, true)) {
                        $error = 'Invalid file type. Only JPG, JPEG, PNG, and GIF are allowed.';
                    } elseif ($size > 5 * 1024 * 1024) {
                        $error = 'File is too large. Maximum size is 5MB.';
                    } else {
                        $upload_dir = dirname(__DIR__) . '/assets/uploads/profile_pictures/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0775, true);
                        }
                        $new_name = 'user_invite_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                        $destination = $upload_dir . $new_name;
                        if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $destination)) {
                            $profile_picture_name = $new_name;
                        } else {
                            $error = 'Failed to upload profile picture.';
                        }
                    }
                } else {
                    $error = 'Error uploading file.';
                }
            }

            if ($error === '') {
                $conn->begin_transaction();
                try {
                    $check = $conn->prepare('SELECT user_id FROM users WHERE username = ? LIMIT 1');
                    if (!$check) {
                        throw new Exception('Error preparing username check.');
                    }
                    $check->bind_param('s', $username);
                    $check->execute();
                    $check->store_result();
                    if ($check->num_rows > 0) {
                        $username_error = 'username is already taken';
                    }
                    $check->close();

                    if ($username_error !== '') {
                        $conn->rollback();
                    } else {
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        if ($has_profile_picture_col) {
                            $insert = $conn->prepare('INSERT INTO users (username, first_name, last_name, password, role, email, profile_picture) VALUES (?, ?, ?, ?, ?, ?, ?)');
                        } else {
                            $insert = $conn->prepare('INSERT INTO users (username, first_name, last_name, password, role, email) VALUES (?, ?, ?, ?, ?, ?)');
                        }
                        if (!$insert) {
                            throw new Exception('Error preparing account creation: ' . $conn->error);
                        }
                        if ($has_profile_picture_col) {
                            $insert->bind_param('sssssss', $username, $first_name, $last_name, $hashed_password, $role, $email, $profile_picture_name);
                        } else {
                            $insert->bind_param('ssssss', $username, $first_name, $last_name, $hashed_password, $role, $email);
                        }
                        if (!$insert->execute()) {
                            throw new Exception('Error creating account.');
                        }
                        $user_id = $insert->insert_id;
                        $insert->close();

                        $updateInvite = $conn->prepare('UPDATE invitations SET used_at = NOW(), used_by_user_id = ? WHERE id = ?');
                        if (!$updateInvite) {
                            throw new Exception('Error preparing invitation update.');
                        }
                        $inviteId = (int)$invite['id'];
                        $updateInvite->bind_param('ii', $user_id, $inviteId);
                        if (!$updateInvite->execute()) {
                            throw new Exception('Error updating invitation.');
                        }
                        $updateInvite->close();

                        $conn->commit();

                        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
                        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
                        $rootPath = rtrim(preg_replace('#/superadmin$#', '', $scriptDir), '/');
                        if ($rootPath === '') {
                            $rootPath = '';
                        }
                        $loginUrl = $scheme . $host . $rootPath . '/index.php';
                        $logoFile = 'logo no bg.png';
                        $logoUrl = $scheme . $host . $rootPath . '/assets/img/' . rawurlencode($logoFile);

                        list($welcomeHtml, $welcomePlain) = build_account_welcome_email($username, $password, $role, $loginUrl, $logoUrl);
                        $mailError = '';
                        send_app_email($email, trim($first_name . ' ' . $last_name), 'CPD-NIR Account Created', $welcomeHtml, $welcomePlain, $mailError);

                        $success = 'Your account has been created successfully. This page will close automatically in 1 minute.';
                    }
                } catch (Exception $e) {
                    if ($username_error === '') {
                        $conn->rollback();
                        $error = $e->getMessage();
                    }
                }
            }
        }
    }
}

$prefill_email = $invite && $invite['email'] ? $invite['email'] : '';
$prefill_full_name = $invite && $invite['full_name'] ? $invite['full_name'] : '';
$role_label = $invite ? strtoupper($invite['role']) : '';
$logo_src = '../assets/img/logo no bg.png';
$expires_label = ($invite && !empty($invite['expires_at'])) ? date('M d, Y h:i A', strtotime($invite['expires_at'])) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CPD-NIR Account Creation Invitation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --brand-blue: #0f3c8a;
            --brand-blue-deep: #072356;
            --brand-gold: #f3b719;
            --surface: #ffffff;
            --line: #d6e1f2;
            --ink: #10244d;
            --muted: #65748f;
        }

        body {
            min-height: 100vh;
            font-family: 'Sora', sans-serif;
            color: var(--ink);
            background:
                radial-gradient(circle at 12% 12%, rgba(15, 60, 138, 0.14), transparent 38%),
                radial-gradient(circle at 88% 18%, rgba(243, 183, 25, 0.18), transparent 34%),
                linear-gradient(170deg, #edf3ff 0%, #f8fbff 40%, #eef5ff 100%);
        }

        .invite-shell {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 0.8rem;
        }

        .invite-card {
            width: 100%;
            max-width: 760px;
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 22px;
            box-shadow: 0 24px 70px rgba(12, 32, 75, 0.16);
            overflow: hidden;
        }

        .invite-head {
            padding: 1.5rem 1.5rem 1rem;
            border-bottom: 1px solid #e5ecf8;
            background: linear-gradient(180deg, #ffffff, #f9fbff);
        }

        .invite-logo {
            width: 86px;
            height: 86px;
            object-fit: contain;
            border-radius: 14px;
            border: 1px solid #dbe6f8;
            background: #fff;
            padding: 6px;
            box-shadow: 0 10px 24px rgba(12, 32, 75, 0.12);
        }

        .invite-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.34rem 0.8rem;
            border-radius: 999px;
            background: #eef4ff;
            border: 1px solid #d6e3fb;
            color: #355289;
            font-size: 0.74rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .invite-title {
            font-size: 1.35rem;
            font-weight: 800;
            letter-spacing: -0.01em;
            margin: 0.8rem 0 0.3rem;
        }

        .invite-subtitle {
            color: var(--muted);
            font-size: 0.93rem;
            margin: 0;
        }

        .invite-meta {
            margin-top: 0.9rem;
            display: flex;
            flex-wrap: wrap;
            gap: 0.7rem;
        }

        .meta-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            font-size: 0.78rem;
            color: #4d5d7c;
            background: #f5f8ff;
            border: 1px solid #e0e9fa;
            border-radius: 999px;
            padding: 0.34rem 0.72rem;
        }

        .invite-body {
            padding: 1.4rem 1.5rem 1.6rem;
        }

        .form-label {
            font-size: 0.82rem;
            font-weight: 700;
            color: #344054;
            margin-bottom: 0.36rem;
        }

        .form-control {
            border-color: #cfdbf1;
            border-radius: 12px;
            padding: 0.64rem 0.8rem;
            font-size: 0.92rem;
            background: #fcfdff;
        }

        .form-control:focus {
            border-color: #7ca0db;
            box-shadow: 0 0 0 0.2rem rgba(15, 60, 138, 0.15);
            background: #ffffff;
        }

        .is-invalid {
            border-color: #dc3545 !important;
            box-shadow: none !important;
        }

        .invalid-feedback {
            display: block;
            font-size: 0.8rem;
            margin-top: 0.3rem;
        }

        .btn-create {
            width: 100%;
            border: 1px solid #0a2a66;
            background: linear-gradient(135deg, #0b2f73, #0f4db5);
            color: #fff;
            border-radius: 12px;
            padding: 0.78rem 1rem;
            font-weight: 700;
            box-shadow: 0 12px 26px rgba(7, 35, 86, 0.28);
        }

        .btn-create:hover {
            color: #fff;
            background: linear-gradient(135deg, #08265c, #0b3f95);
        }
    </style>
</head>
<body>
    <div class="invite-shell">
        <div class="invite-card">
            <div class="invite-head text-center text-md-start">
                <div class="d-flex flex-column flex-md-row align-items-center align-items-md-start gap-3">
                    <img src="<?php echo htmlspecialchars($logo_src); ?>" alt="CPD-NIR Logo" class="invite-logo">
                    <div class="w-100">
                        <span class="invite-badge"><i class="bi bi-shield-lock"></i>Invitation Registration</span>
                        <h4 class="invite-title">Create Your CPD-NIR Account</h4>
                        <?php if ($role_label): ?>
                            <p class="invite-subtitle mb-0">This link is intended for a <strong><?php echo htmlspecialchars($role_label); ?></strong> account.</p>
                        <?php else: ?>
                            <p class="invite-subtitle mb-0">Complete the form below to finish your account setup securely.</p>
                        <?php endif; ?>

                        <div class="invite-meta justify-content-center justify-content-md-start">
                            <?php if ($prefill_email !== ''): ?>
                                <span class="meta-chip"><i class="bi bi-envelope"></i><?php echo htmlspecialchars($prefill_email); ?></span>
                            <?php endif; ?>
                            <?php if ($expires_label !== ''): ?>
                                <span class="meta-chip"><i class="bi bi-clock-history"></i>Expires <?php echo htmlspecialchars($expires_label); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="invite-body">
                        <?php if ($fatal_invite_error && $error): ?>
                            <div class="alert alert-danger" role="alert">
                                <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!$fatal_invite_error && $invite && !$success): ?>
                            <form method="POST" action="" enctype="multipart/form-data" autocomplete="off">
                                <input type="text" name="invite_fake_username" class="d-none" tabindex="-1" autocomplete="username" aria-hidden="true">
                                <input type="password" name="invite_fake_password" class="d-none" tabindex="-1" autocomplete="new-password" aria-hidden="true">
                                <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">First Name <span class="text-danger">*</span></label>
                                    <input type="text" name="first_name" class="form-control" required value="<?php echo htmlspecialchars($first_name ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Last Name <span class="text-danger">*</span></label>
                                    <input type="text" name="last_name" class="form-control" required value="<?php echo htmlspecialchars($last_name ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Middle Name (optional)</label>
                                    <input type="text" name="middle_name" class="form-control" value="<?php echo htmlspecialchars($middle_name ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" name="email" class="form-control" required value="<?php echo htmlspecialchars($email ?? $prefill_email, ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                                    <input type="text" name="phone" class="form-control" required value="<?php echo htmlspecialchars($phone ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Address <span class="text-danger">*</span></label>
                                    <textarea name="address" class="form-control" rows="2" required><?php echo htmlspecialchars($address ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Username <span class="text-danger">*</span></label>
                                    <input
                                        type="text"
                                        name="username"
                                        id="username"
                                        class="form-control<?php echo $username_error !== '' ? ' is-invalid' : ''; ?>"
                                        required
                                        value="<?php echo htmlspecialchars($username ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                        aria-required="true"
                                        autocomplete="off"
                                        autocapitalize="none"
                                        spellcheck="false"
                                    >
                                    <div id="username-error" class="invalid-feedback"><?php echo htmlspecialchars($username_error, ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Password <span class="text-danger">*</span></label>
                                    <input type="password" name="password" class="form-control" required autocomplete="new-password">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                    <input type="password" name="confirm_password" class="form-control" required autocomplete="new-password">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Profile Picture (optional)</label>
                                    <input type="file" name="profile_picture" class="form-control" accept="image/*">
                                    <div class="form-text">Accepted formats: JPG, PNG, GIF. Maximum file size: 5MB.</div>
                                </div>
                                <div class="col-12 pt-2">
                                <button type="submit" class="btn btn-create">
                                    Create Account
                                </button>
                                </div>
                                </div>
                            </form>
                        <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var errorMessage = <?php echo json_encode($error, JSON_UNESCAPED_UNICODE); ?>;
            var successMessage = <?php echo json_encode($success, JSON_UNESCAPED_UNICODE); ?>;
            var usernameErrorMessage = <?php echo json_encode($username_error, JSON_UNESCAPED_UNICODE); ?>;

            var usernameInput = document.getElementById('username');
            var usernameErrorSpan = document.getElementById('username-error');

            function setUsernameError(message) {
                if (!usernameInput || !usernameErrorSpan) return;
                if (message) {
                    usernameInput.classList.add('is-invalid');
                    usernameInput.setAttribute('aria-invalid', 'true');
                    usernameInput.setAttribute('aria-describedby', 'username-error');
                    usernameErrorSpan.textContent = message;
                } else {
                    usernameInput.classList.remove('is-invalid');
                    usernameInput.removeAttribute('aria-invalid');
                    usernameInput.removeAttribute('aria-describedby');
                    usernameErrorSpan.textContent = '';
                }
            }

            if (usernameErrorMessage) {
                setUsernameError(usernameErrorMessage);
            }

            if (usernameInput) {
                usernameInput.addEventListener('blur', function () {
                    var value = usernameInput.value.trim();
                    if (value === '') {
                        setUsernameError('');
                        return;
                    }
                    var url = window.location.pathname + window.location.search;
                    var separator = url.indexOf('?') === -1 ? '?' : '&';
                    var checkUrl = url + separator + 'check_username=1&username=' + encodeURIComponent(value);
                    fetch(checkUrl, { method: 'GET' })
                        .then(function (response) { return response.json(); })
                        .then(function (data) {
                            if (data && data.success && data.taken) {
                                setUsernameError('username is already taken');
                            } else {
                                setUsernameError('');
                            }
                        })
                        .catch(function () {});
                });

                usernameInput.addEventListener('input', function () {
                    if (usernameInput.classList.contains('is-invalid')) {
                        setUsernameError('');
                    }
                });
            }

            if (typeof Swal === 'undefined') {
                return;
            }

            if (successMessage) {
                Swal.fire({
                    title: 'Account Created',
                    text: successMessage,
                    icon: 'success',
                    confirmButtonText: 'OK',
                    timer: 60000,
                    timerProgressBar: true,
                    allowOutsideClick: false,
                    allowEscapeKey: false
                }).then(function () {
                    try {
                        window.close();
                    } catch (e) {
                    }
                    if (!window.closed) {
                        window.location.href = 'https://cpdnir.site/index.php';
                    }
                });
            } else if (errorMessage) {
                Swal.fire({
                    title: 'Account Creation Failed',
                    text: errorMessage,
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            }
        });
    </script>
</body>
</html>
