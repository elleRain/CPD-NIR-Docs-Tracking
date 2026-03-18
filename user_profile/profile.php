<?php
session_start();
if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: ../index.php');
    exit();
}

$role = $_SESSION['role'];
if (!in_array($role, ['superadmin', 'admin', 'staff'], true)) {
    header('Location: ../index.php');
    exit();
}

include '../plugins/conn.php';

$user_id = (int)$_SESSION['user_id'];
$errors = [];
$success = '';

// Prepare defaults
$user = [
    'username' => '',
    'first_name' => '',
    'middle_name' => '',
    'last_name' => '',
    'email' => '',
    'role' => '',
    'profile_picture' => ''
];

// Check if user table has profile_picture column
$hasProfilePicture = false;
$colCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'profile_picture'");
if ($colCheck && $colCheck->num_rows > 0) {
    $hasProfilePicture = true;
}

// Fetch user data
$stmt = $conn->prepare('SELECT username, first_name, middle_name, last_name, email, role' . ($hasProfilePicture ? ', profile_picture' : '') . ' FROM users WHERE user_id = ? LIMIT 1');
if ($stmt) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $user = array_merge($user, $result->fetch_assoc());
    }
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $username = trim($_POST['username'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($username === '' || $first_name === '' || $last_name === '' || $email === '') {
        $errors[] = 'Please fill out the required fields: username, first name, last name, and email.';
    }

    // Username uniqueness check
    if (empty($errors)) {
        $stmt = $conn->prepare('SELECT user_id FROM users WHERE username = ? AND user_id != ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('si', $username, $user_id);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $errors[] = 'Username is already taken by another user.';
            }
            $stmt->close();
        }
    }

    if ($password !== '' || $confirm_password !== '') {
        if ($password !== $confirm_password) {
            $errors[] = 'New password and confirmation do not match.';
        } elseif (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters long.';
        }
    }

    $profile_picture_path = $user['profile_picture'];
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE) {
        $allowed = ['image/png', 'image/jpeg', 'image/jpg', 'image/gif'];
        if ($_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES['profile_picture']['tmp_name'];
            $type = mime_content_type($tmp);
            if (!in_array($type, $allowed, true)) {
                $errors[] = 'Only PNG, JPG, JPEG and GIF profile pictures are allowed.';
            } else {
                $uploadDir = '../uploads/profile/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $filename = $user_id . '_' . time() . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', basename($_FILES['profile_picture']['name']));
                $dest = $uploadDir . $filename;
                if (move_uploaded_file($tmp, $dest)) {
                    $profile_picture_path = 'uploads/profile/' . $filename;
                } else {
                    $errors[] = 'Failed to upload profile picture.';
                }
            }
        } else {
            $errors[] = 'Error uploading profile picture.';
        }
    }

    if (empty($errors)) {
        $updateFields = [];
        $paramTypes = '';
        $params = [];

        $updateFields[] = 'username = ?'; $paramTypes .= 's'; $params[] = $username;
        $updateFields[] = 'first_name = ?'; $paramTypes .= 's'; $params[] = $first_name;
        $updateFields[] = 'middle_name = ?'; $paramTypes .= 's'; $params[] = $middle_name;
        $updateFields[] = 'last_name = ?'; $paramTypes .= 's'; $params[] = $last_name;
        $updateFields[] = 'email = ?'; $paramTypes .= 's'; $params[] = $email;

        if ($hasProfilePicture) {
            $updateFields[] = 'profile_picture = ?'; $paramTypes .= 's'; $params[] = $profile_picture_path;
        }

        if ($password !== '') {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $updateFields[] = 'password = ?'; $paramTypes .= 's'; $params[] = $hashed;
        }

        $paramTypes .= 'i';
        $params[] = $user_id;

        $sql = 'UPDATE users SET ' . implode(', ', $updateFields) . ' WHERE user_id = ?';
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param($paramTypes, ...$params);
            if ($stmt->execute()) {
                $success = 'Profile updated successfully.';
                // Refresh displayed user
                $user['username'] = $username;
                $user['first_name'] = $first_name;
                $user['middle_name'] = $middle_name;
                $user['last_name'] = $last_name;
                $user['email'] = $email;
                if ($hasProfilePicture) {
                    $user['profile_picture'] = $profile_picture_path;
                }
            } else {
                $errors[] = 'Failed to save profile details.';
            }
            $stmt->close();
        } else {
            $errors[] = 'Failed to update profile (statement error).';
        }
    }
}

$displayName = trim($user['first_name'] . ' ' . $user['middle_name'] . ' ' . $user['last_name']);
$displayName = preg_replace('/\s+/', ' ', $displayName);
$profilePic = $user['profile_picture'] ?: 'assets/img/default-avatar.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile Settings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/custom.css" rel="stylesheet">
</head>
<body>
<?php
include __DIR__ . '/../includes/sidebar.php';
?>
<main class="main-content">
    <?php include __DIR__ . '/../includes/navbar.php'; ?>
    <div class="container-fluid mt-4">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-body p-4">
                        <h2 class="h4 fw-bold mb-3">Profile Settings</h2>
                        <p class="text-muted">Update your account information, change password, and upload a profile photo.</p>

                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger"><ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul></div>
                        <?php endif; ?>

                        <div class="row g-3 mb-4 align-items-center">
                            <div class="col-md-3 text-center">
                                <img id="profilePicPreview" src="<?php echo htmlspecialchars($profilePic, ENT_QUOTES); ?>" class="rounded-circle img-fluid" style="width:160px;height:160px;object-fit:cover;border:1px solid #dee2e6;" alt="Profile Picture">
                            </div>
                            <div class="col-md-9">
                                <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($displayName ?: $user['username']); ?></h5>
                                <p class="text-muted mb-1">Role: <?php echo htmlspecialchars(ucfirst($user['role'])); ?></p>
                                <p class="text-muted mb-0">Username: <?php echo htmlspecialchars($user['username']); ?></p>
                            </div>
                        </div>

                        <form method="POST" action="" enctype="multipart/form-data">
                            <input type="hidden" name="update_profile" value="1">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Username <span class="text-danger">*</span></label>
                                    <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($user['username'], ENT_QUOTES); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email'], ENT_QUOTES); ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">First Name <span class="text-danger">*</span></label>
                                    <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($user['first_name'], ENT_QUOTES); ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Middle Name</label>
                                    <input type="text" name="middle_name" class="form-control" value="<?php echo htmlspecialchars($user['middle_name'], ENT_QUOTES); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Last Name <span class="text-danger">*</span></label>
                                    <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($user['last_name'], ENT_QUOTES); ?>" required>
                                </div>

                                <div class="col-md-12">
                                    <label class="form-label">Profile Photo</label>
                                    <input type="file" name="profile_picture" class="form-control" accept="image/*" onchange="previewProfilePic(event)">
                                    <div class="form-text">Allowed types: JPG, PNG, GIF. Max 3MB.</div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">New Password</label>
                                    <input type="password" name="password" class="form-control" placeholder="Leave blank to keep current password">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Confirm Password</label>
                                    <input type="password" name="confirm_password" class="form-control" placeholder="Repeat password">
                                </div>
                            </div>

                            <div class="mt-4 d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
    function previewProfilePic(event) {
        const input = event.target;
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function (e) {
                document.getElementById('profilePicPreview').src = e.target.result;
            };
            reader.readAsDataURL(input.files[0]);
        }
    }
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
