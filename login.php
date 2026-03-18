<?php
session_start();
include 'plugins/conn.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Use prepared statements to prevent SQL injection
    $stmt = $conn->prepare("SELECT user_id, username, password, role FROM users WHERE username = ?");
    if ($stmt === false) {
        die("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        
        // Verify password hash
        if (password_verify($password, $row['password'])) {
            // Password is correct, set session variables
            $_SESSION['user_id'] = $row['user_id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['role'] = $row['role']; // Use 'role' instead of 'type'

            // Redirect based on role
            if ($row['role'] == 'superadmin') {
                header("Location: dashboards/superadmin_dashboard.php");
            } elseif ($row['role'] == 'admin') {
                header("Location: dashboards/admin_dashboard.php");
            } elseif ($row['role'] == 'staff') {
                header("Location: dashboards/staff_dashboard.php");
            } else {
                // Fallback or specific dashboard
                header("Location: index.php?error=Unknown Role");
            }
            exit();
        } else {
            header("Location: index.php?error=Invalid Username or Password");
            exit();
        }
    } else {
        header("Location: index.php?error=Invalid Username or Password");
        exit();
    }
    $stmt->close();
} else {
    header("Location: index.php");
    exit();
}
$conn->close();
?>