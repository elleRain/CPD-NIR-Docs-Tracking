<?php
session_start();
require __DIR__ . '/../plugins/conn.php';
require __DIR__ . '/../plugins/mailer.php';

$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$invite = null;
$error = '';
$success = '';
$username_error = '';

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
} else {
    $now = new DateTimeImmutable();
    $expiresAt = new DateTimeImmutable($invite['expires_at']);

    if ($invite['used_at'] !== null) {
        $error = 'This registration link has already been used.';
    } elseif ($expiresAt <= $now) {
        $error = 'This registration link has expired.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error && $invite) {
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CPD-NIR Account Creation Invitation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/custom.css">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow-sm invite-card border-0">
                    <div class="card-header bg-white text-center border-0 pt-4 pb-3">
                        <img src="<?php echo htmlspecialchars($logo_src); ?>" alt="CPD-NIR Logo" class="invite-logo mb-2">
                        <h4 class="mb-1 text-primary fw-semibold">CPD-NIR Account Creation Invitation</h4>
                        <?php if ($role_label): ?>
                            <div class="invite-subtitle text-muted">
                                This secure link is for a <?php echo htmlspecialchars($role_label); ?> account.
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger" role="alert">
                                <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!$error && $invite && !$success): ?>
                            <form method="POST" action="" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label class="form-label">First Name <span class="text-danger">*</span></label>
                                    <input type="text" name="first_name" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Middle Name (optional)</label>
                                    <input type="text" name="middle_name" class="form-control">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Last Name <span class="text-danger">*</span></label>
                                    <input type="text" name="last_name" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" name="email" class="form-control" required value="<?php echo htmlspecialchars($prefill_email); ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                                    <input type="text" name="phone" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Address <span class="text-danger">*</span></label>
                                    <textarea name="address" class="form-control" rows="2" required></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Username <span class="text-danger">*</span></label>
                                    <input
                                        type="text"
                                        name="username"
                                        id="username"
                                        class="form-control<?php echo $username_error !== '' ? ' username-error-state' : ''; ?>"
                                        required
                                        value="<?php echo $username_error === '' ? htmlspecialchars($username ?? '', ENT_QUOTES, 'UTF-8') : ''; ?>"
                                        aria-required="true"
                                        <?php if ($username_error !== ''): ?>
                                            aria-invalid="true"
                                            aria-describedby="username-error"
                                            placeholder="username is already taken"
                                        <?php endif; ?>
                                    >
                                    <span id="username-error" class="visually-hidden">
                                        <?php echo htmlspecialchars($username_error, ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Password <span class="text-danger">*</span></label>
                                    <input type="password" name="password" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                    <input type="password" name="confirm_password" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Profile Picture (optional)</label>
                                    <input type="file" name="profile_picture" class="form-control" accept="image/*">
                                    <div class="form-text">JPG, PNG, or GIF. Max size 5MB.</div>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">
                                    Create Account
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
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
                    if (!usernameInput.dataset.originalPlaceholder) {
                        usernameInput.dataset.originalPlaceholder = usernameInput.placeholder;
                    }
                    usernameInput.classList.add('username-error-state');
                    usernameInput.setAttribute('aria-invalid', 'true');
                    usernameInput.setAttribute('aria-describedby', 'username-error');
                    usernameInput.value = '';
                    usernameInput.placeholder = message;
                    usernameErrorSpan.textContent = message;
                } else {
                    usernameInput.classList.remove('username-error-state');
                    usernameInput.removeAttribute('aria-invalid');
                    usernameInput.removeAttribute('aria-describedby');
                    if (usernameInput.dataset.originalPlaceholder) {
                        usernameInput.placeholder = usernameInput.dataset.originalPlaceholder;
                    }
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
                    if (usernameInput.classList.contains('username-error-state')) {
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
