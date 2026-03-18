<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'superadmin') {
    header("Location: ../index.php");
    exit();
}
include '../plugins/conn.php';
require_once __DIR__ . '/../plugins/mailer.php';

$roles = ['superadmin', 'admin', 'staff'];
$errors = [];
$success = '';
$invite_url = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'generate_invite') {
        $role = isset($_POST['role']) ? trim($_POST['role']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $full_name = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';

        if ($role === '') {
            $errors[] = 'Please select a role before generating a link.';
        } elseif (!in_array($role, ['admin', 'staff'], true)) {
            $errors[] = 'Invalid role selected for invitation.';
        } else {
            $token = bin2hex(random_bytes(32));
            $expires_at = (new DateTime('+4 hours'))->format('Y-m-d H:i:s');

            $stmt = $conn->prepare('INSERT INTO invitations (token, email, full_name, role, expires_at) VALUES (?, ?, ?, ?, ?)');
            if ($stmt) {
                $stmt->bind_param('sssss', $token, $email, $full_name, $role, $expires_at);
                if ($stmt->execute()) {
                    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
                    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                    $basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
                    $invite_url = $scheme . $host . $basePath . '/invite_register.php?token=' . urlencode($token);
                    $success = 'Invitation link generated successfully.';

                    if ($email !== '') {
                        $subject = 'CPD-NIR Account Registration Link';
                        $htmlBody = '<p>Good day' . ($full_name !== '' ? ' ' . htmlspecialchars($full_name, ENT_QUOTES, 'UTF-8') : '') . ',</p>'
                            . '<p>You have been invited to create an account in the CPD-NIR Inventory System.</p>'
                            . '<p><a href="' . htmlspecialchars($invite_url, ENT_QUOTES, 'UTF-8') . '">Click here to register your account</a></p>'
                            . '<p>This link will expire in 4 hours.</p>';

                        $plainBody = "Good day" . ($full_name !== '' ? " " . $full_name : '') . "\n\n"
                            . "You have been invited to create an account in the CPD-NIR Inventory System.\n"
                            . "Registration link: " . $invite_url . "\n"
                            . "This link will expire in 4 hours.";

                        $mailError = '';
                        $ok = send_app_email($email, $full_name, $subject, $htmlBody, $plainBody, $mailError);
                        if (!$ok && $mailError !== '') {
                            $errors[] = 'Invitation link created, but email was not sent: ' . $mailError;
                        }
                    }
                } else {
                    $errors[] = 'Error saving invitation link: ' . $stmt->error;
                }
                $stmt->close();
            } else {
                $errors[] = 'Error preparing invitation link: ' . $conn->error;
            }
        }
    } elseif ($action === 'create') {
        $username = isset($_POST['username']) ? trim($_POST['username']) : '';
        $full_name = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $role = isset($_POST['role']) ? trim($_POST['role']) : '';
        $password = isset($_POST['password']) ? (string)$_POST['password'] : '';
        $confirm_password = isset($_POST['confirm_password']) ? (string)$_POST['confirm_password'] : '';

        if ($username === '' || $full_name === '' || $role === '' || $password === '' || $confirm_password === '') {
            $errors[] = 'Please fill in all required fields.';
        } elseif ($password !== $confirm_password) {
            $errors[] = 'Passwords do not match.';
        } elseif (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters.';
        } elseif (!in_array($role, $roles, true)) {
            $errors[] = 'Invalid role selected.';
        } else {
            $stmt = $conn->prepare('SELECT user_id FROM users WHERE username = ? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('s', $username);
                $stmt->execute();
                $stmt->store_result();
                if ($stmt->num_rows > 0) {
                    $errors[] = 'Username is already taken.';
                } else {
                    $nameParts = preg_split('/\s+/', $full_name);
                    $first_name = $nameParts[0] ?? $full_name;
                    $last_name = $nameParts[count($nameParts) - 1] ?? $full_name;
                    $middle_name = '';
                    if (count($nameParts) > 2) {
                        $middle_name = implode(' ', array_slice($nameParts, 1, -1));
                    }

                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt_insert = $conn->prepare('INSERT INTO users (username, first_name, middle_name, last_name, password, role, email) VALUES (?, ?, ?, ?, ?, ?, ?)');
                    if ($stmt_insert) {
                        $stmt_insert->bind_param('sssssss', $username, $first_name, $middle_name, $last_name, $hashed_password, $role, $email);
                        if ($stmt_insert->execute()) {
                            $success = 'User created successfully.';

                            if ($email !== '') {
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
                                $mailErrorCreate = '';
                                send_app_email($email, $full_name, 'CPD-NIR Account Created', $welcomeHtml, $welcomePlain, $mailErrorCreate);
                            }
                        } else {
                            $errors[] = 'Error creating account.';
                        }
                        $stmt_insert->close();
                    } else {
                        $errors[] = 'Error preparing account creation.';
                    }
                }
                $stmt->close();
            } else {
                $errors[] = 'Error checking username.';
            }
        }
    } elseif ($action === 'edit') {
        $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        $full_name = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $role = isset($_POST['role']) ? trim($_POST['role']) : '';

        if ($user_id <= 0 || $full_name === '' || $role === '') {
            $errors[] = 'Invalid data for update.';
        } elseif (!in_array($role, $roles, true)) {
            $errors[] = 'Invalid role selected.';
        } elseif ($user_id === (int)$_SESSION['user_id'] && $role !== 'superadmin') {
            $errors[] = 'You cannot change your own role.';
        } else {
            $nameParts = preg_split('/\s+/', $full_name);
            $first_name = $nameParts[0] ?? $full_name;
            $last_name = $nameParts[count($nameParts) - 1] ?? $full_name;
            $middle_name = '';
            if (count($nameParts) > 2) {
                $middle_name = implode(' ', array_slice($nameParts, 1, -1));
            }

            $stmt = $conn->prepare('UPDATE users SET first_name = ?, middle_name = ?, last_name = ?, email = ?, role = ? WHERE user_id = ?');
            if ($stmt) {
                $stmt->bind_param('sssssi', $first_name, $middle_name, $last_name, $email, $role, $user_id);
                if ($stmt->execute()) {
                    $success = 'User updated successfully.';
                } else {
                    $errors[] = 'Failed to update user.';
                }
                $stmt->close();
            } else {
                $errors[] = 'Failed to update user.';
            }
        }
    } elseif ($action === 'reset_password') {
        $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        $password = isset($_POST['password']) ? (string)$_POST['password'] : '';
        $confirm_password = isset($_POST['confirm_password']) ? (string)$_POST['confirm_password'] : '';

        if ($user_id <= 0 || $password === '' || $confirm_password === '') {
            $errors[] = 'Invalid data for password reset.';
        } elseif ($password !== $confirm_password) {
            $errors[] = 'Passwords do not match.';
        } elseif (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters.';
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare('UPDATE users SET password = ? WHERE user_id = ?');
            if ($stmt) {
                $stmt->bind_param('si', $password_hash, $user_id);
                if ($stmt->execute()) {
                    $success = 'Password reset successfully.';
                } else {
                    $errors[] = 'Failed to reset password.';
                }
                $stmt->close();
            } else {
                $errors[] = 'Failed to reset password.';
            }
        }
    } elseif ($action === 'delete') {
        $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;

        if ($user_id <= 0) {
            $errors[] = 'Invalid user selected.';
        } else {
            if ($user_id === (int)$_SESSION['user_id']) {
                $errors[] = 'You cannot delete your own account.';
            } else {
                $stmt = $conn->prepare('DELETE FROM users WHERE user_id = ?');
                if ($stmt) {
                    $stmt->bind_param('i', $user_id);
                    if ($stmt->execute()) {
                        $success = 'User deleted successfully.';
                    } else {
                        $errors[] = 'Failed to delete user.';
                    }
                    $stmt->close();
                } else {
                    $errors[] = 'Failed to delete user.';
                }
            }
        }
    }
}

$total_users = 0;
$total_admins = 0;
$total_staff = 0;
$total_superadmins = 0;

$result = $conn->query("SELECT COUNT(*) FROM users");
if ($result) {
    $row = $result->fetch_row();
    $total_users = (int)$row[0];
}

$result = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
if ($result) {
    $row = $result->fetch_row();
    $total_admins = (int)$row[0];
}

$result = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'staff'");
if ($result) {
    $row = $result->fetch_row();
    $total_staff = (int)$row[0];
}

$result = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'superadmin'");
if ($result) {
    $row = $result->fetch_row();
    $total_superadmins = (int)$row[0];
}

$users_sql = "SELECT user_id, username, CONCAT_WS(' ', first_name, NULLIF(middle_name, ''), last_name) AS full_name, email, role FROM users ORDER BY user_id DESC";
$stmt = $conn->prepare($users_sql);
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = false;
}

$users = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}

if (isset($stmt) && $stmt) {
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - DTS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/custom.css">
</head>
<body>

    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="main-content">
        <?php include __DIR__ . '/../includes/navbar.php'; ?>
        <div class="container-fluid px-0">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php foreach ($errors as $error): ?>
                        <div><?php echo htmlspecialchars($error); ?></div>
                    <?php endforeach; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if ($success !== ''): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon bg-blue-light">
                            <i class="bi bi-people-fill"></i>
                        </div>
                        <p class="text-secondary text-xs mb-1">Total Users</p>
                        <h4 class="fw-bold mb-0"><?php echo $total_users; ?></h4>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon bg-purple-light">
                            <i class="bi bi-person-badge-fill"></i>
                        </div>
                        <p class="text-secondary text-xs mb-1">Super Admins</p>
                        <h4 class="fw-bold mb-0"><?php echo $total_superadmins; ?></h4>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon bg-orange-light">
                            <i class="bi bi-shield-lock-fill"></i>
                        </div>
                        <p class="text-secondary text-xs mb-1">Admins</p>
                        <h4 class="fw-bold mb-0"><?php echo $total_admins; ?></h4>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon bg-green-light">
                            <i class="bi bi-person-check-fill"></i>
                        </div>
                        <p class="text-secondary text-xs mb-1">Staff</p>
                        <h4 class="fw-bold mb-0"><?php echo $total_staff; ?></h4>
                    </div>
                </div>
            </div>

            <?php if ($invite_url !== ''): ?>
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <div class="d-flex flex-column flex-lg-row align-items-lg-center gap-3">
                            <div class="d-flex align-items-center gap-3 flex-grow-1">
                                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                    <i class="bi bi-link-45deg"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                                        <div>
                                            <div class="fw-semibold text-primary text-xs text-uppercase">
                                                Registration Link Generated
                                            </div>
                                            <div class="text-xs text-secondary">
                                                Share this one-time link with the intended user only.
                                            </div>
                                        </div>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle text-xs">
                                            Active – expires in 4 hours
                                        </span>
                                    </div>
                                    <div class="mt-3">
                                        <div class="input-group">
                                            <span class="input-group-text bg-light border-end-0">
                                                <i class="bi bi-globe2 text-muted"></i>
                                            </span>
                                            <input
                                                id="invite_link_input"
                                                type="text"
                                                class="form-control border-start-0 font-monospace text-truncate"
                                                value="<?php echo htmlspecialchars($invite_url); ?>"
                                                readonly
                                            >
                                            <button
                                                id="copy_invite_link"
                                                type="button"
                                                class="btn btn-outline-primary fw-semibold"
                                                style="min-width: 140px;"
                                            >
                                                <i class="bi bi-clipboard-check me-1"></i>
                                                Copy Link
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="table-card">
                <div class="table-header">
                    <div>
                        <h6 class="mb-0 fw-semibold">User Accounts</h6>
                        <p class="text-secondary text-xs mb-0">Manage user accounts and roles</p>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-primary btn-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#inviteLinkModal">
                            <i class="bi bi-link-45deg me-2"></i>
                            <span>Generate Link</span>
                        </button>
                        <button type="button" class="btn btn-primary btn-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#addUserModal">
                            <i class="bi bi-person-plus me-2"></i>
                            <span>Add Account</span>
                        </button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4 py-3">ID</th>
                                <th class="py-3">Username</th>
                                <th class="py-3">Full Name</th>
                                <th class="py-3">Email</th>
                                <th class="py-3">Role</th>
                                <th class="py-3 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($users)): ?>
                                <?php foreach ($users as $user): ?>
                                    <?php
                                            $role_class = 'role-staff';
                                            if ($user['role'] === 'superadmin') {
                                                $role_class = 'role-superadmin';
                                            } elseif ($user['role'] === 'admin') {
                                                $role_class = 'role-admin';
                                            }
                                        ?>
                                    <tr>
                                        <td class="ps-4">
                                            <?php echo (int)$user['user_id']; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar avatar-sm bg-light rounded-circle d-flex align-items-center justify-content-center me-3">
                                                    <i class="bi bi-person text-secondary"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-semibold"><?php echo htmlspecialchars($user['username']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($user['full_name']); ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($user['email']); ?>
                                        </td>
                                        <td>
                                            <span class="role-badge <?php echo $role_class; ?>">
                                                <?php echo ucfirst(htmlspecialchars($user['role'])); ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group" role="group">
                                                <button type="button"
                                                    class="btn btn-outline-secondary btn-sm edit-user-btn"
                                                    data-user-id="<?php echo (int)$user['user_id']; ?>"
                                                    data-username="<?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-full-name="<?php echo htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-email="<?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-role="<?php echo htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8'); ?>">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button type="button"
                                                    class="btn btn-outline-warning btn-sm reset-password-btn"
                                                    data-user-id="<?php echo (int)$user['user_id']; ?>"
                                                    data-username="<?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?>">
                                                    <i class="bi bi-key"></i>
                                                </button>
                                                <button type="button"
                                                    class="btn btn-outline-danger btn-sm delete-user-btn"
                                                    data-user-id="<?php echo (int)$user['user_id']; ?>"
                                                    data-username="<?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?>">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="text-center py-4 text-secondary">
                                        No users found.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="users.php">
                    <input type="hidden" name="action" value="create">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addUserModalLabel">Add New Account</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="addUsername" class="form-label">Username</label>
                            <input type="text" class="form-control" id="addUsername" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="addFullName" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="addFullName" name="full_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="addEmail" class="form-label">Email</label>
                            <input type="email" class="form-control" id="addEmail" name="email">
                        </div>
                        <div class="mb-3">
                            <label for="addRole" class="form-label">Role</label>
                            <select class="form-select" id="addRole" name="role" required>
                                <option value="">Select role</option>
                                <?php foreach ($roles as $role_option): ?>
                                    <option value="<?php echo htmlspecialchars($role_option); ?>">
                                        <?php echo ucfirst($role_option); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="addPassword" class="form-label">Password</label>
                            <input type="password" class="form-control" id="addPassword" name="password" required>
                        </div>
                        <div class="mb-3">
                            <label for="addConfirmPassword" class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" id="addConfirmPassword" name="confirm_password" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="users.php">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="user_id" id="editUserId">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editUserModalLabel">Edit Account</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" id="editUsername" disabled>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="editFullName" name="full_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" id="editEmail" name="email">
                        </div>
                        <div class="mb-3">
                            <label for="editRole" class="form-label">Role</label>
                            <select class="form-select" id="editRole" name="role" required>
                                <?php foreach ($roles as $role_option): ?>
                                    <option value="<?php echo htmlspecialchars($role_option); ?>">
                                        <?php echo ucfirst($role_option); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-labelledby="resetPasswordModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="users.php">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="user_id" id="resetUserId">
                    <div class="modal-header">
                        <h5 class="modal-title" id="resetPasswordModalLabel">Reset Password</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" id="resetUsername" disabled>
                        </div>
                        <div class="mb-3">
                            <label for="resetPassword" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="resetPassword" name="password" required>
                        </div>
                        <div class="mb-3">
                            <label for="resetConfirmPassword" class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" id="resetConfirmPassword" name="confirm_password" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Reset Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="users.php">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="user_id" id="deleteUserId">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteUserModalLabel">Delete User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-1">Are you sure you want to delete this user?</p>
                        <p class="mb-0 fw-semibold" id="deleteUsername"></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="inviteLinkModal" tabindex="-1" aria-labelledby="inviteLinkModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="inviteLinkModalLabel">Generate Registration Link</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="users.php">
                    <input type="hidden" name="action" value="generate_invite">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Role <span class="text-danger">*</span></label>
                            <select name="role" id="invite_role" class="form-select" required>
                                <option value="">Select role</option>
                                <option value="admin">Admin</option>
                                <option value="staff">Staff</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" id="btn_generate_invite" class="btn btn-primary" disabled>
                            <i class="bi bi-link-45deg me-1"></i>Generate Link
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var editUserModalEl = document.getElementById('editUserModal');
            var resetPasswordModalEl = document.getElementById('resetPasswordModal');
            var deleteUserModalEl = document.getElementById('deleteUserModal');

            var editUserModal = editUserModalEl ? new bootstrap.Modal(editUserModalEl) : null;
            var resetPasswordModal = resetPasswordModalEl ? new bootstrap.Modal(resetPasswordModalEl) : null;
            var deleteUserModal = deleteUserModalEl ? new bootstrap.Modal(deleteUserModalEl) : null;

            document.querySelectorAll('.edit-user-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var userId = this.getAttribute('data-user-id');
                    var username = this.getAttribute('data-username');
                    var fullName = this.getAttribute('data-full-name');
                    var email = this.getAttribute('data-email');
                    var role = this.getAttribute('data-role');

                    document.getElementById('editUserId').value = userId;
                    document.getElementById('editUsername').value = username;
                    document.getElementById('editFullName').value = fullName || '';
                    document.getElementById('editEmail').value = email || '';

                    var roleSelect = document.getElementById('editRole');
                    if (roleSelect) {
                        for (var i = 0; i < roleSelect.options.length; i++) {
                            if (roleSelect.options[i].value === role) {
                                roleSelect.selectedIndex = i;
                                break;
                            }
                        }
                    }

                    if (editUserModal) {
                        editUserModal.show();
                    }
                });
            });

            document.querySelectorAll('.reset-password-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var userId = this.getAttribute('data-user-id');
                    var username = this.getAttribute('data-username');

                    document.getElementById('resetUserId').value = userId;
                    document.getElementById('resetUsername').value = username;

                    if (resetPasswordModal) {
                        resetPasswordModal.show();
                    }
                });
            });

            document.querySelectorAll('.delete-user-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var userId = this.getAttribute('data-user-id');
                    var username = this.getAttribute('data-username');

                    document.getElementById('deleteUserId').value = userId;
                    document.getElementById('deleteUsername').textContent = username;

                    if (deleteUserModal) {
                        deleteUserModal.show();
                    }
                });
            });

            var roleSelect = document.getElementById('invite_role');
            var btnGenerateInvite = document.getElementById('btn_generate_invite');
            var copyBtn = document.getElementById('copy_invite_link');
            var inviteInput = document.getElementById('invite_link_input');

            if (roleSelect && btnGenerateInvite) {
                btnGenerateInvite.disabled = (roleSelect.value === '');
                roleSelect.addEventListener('change', function () {
                    btnGenerateInvite.disabled = (this.value === '');
                });
            }

            if (copyBtn && inviteInput) {
                copyBtn.addEventListener('click', function () {
                    inviteInput.select();
                    inviteInput.setSelectionRange(0, inviteInput.value.length);
                    try {
                        var successful = document.execCommand('copy');
                        if (successful) {
                            copyBtn.classList.remove('btn-outline-primary');
                            copyBtn.classList.add('btn-success');
                            copyBtn.innerHTML = '<i class="bi bi-check2 me-1"></i>Copied';
                            setTimeout(function () {
                                copyBtn.classList.remove('btn-success');
                                copyBtn.classList.add('btn-outline-primary');
                                copyBtn.innerHTML = '<i class="bi bi-clipboard me-1"></i>Copy';
                            }, 2000);
                        }
                    } catch (e) {
                    }
                });
            }
        });
    </script>
</body>
</html>

