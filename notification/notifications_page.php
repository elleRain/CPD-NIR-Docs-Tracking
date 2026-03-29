<?php
session_start();
if (!isset($_SESSION['username'])) {
    header('Location: ../index.php');
    exit();
}
include '../plugins/conn.php';
include 'notification_helpers.php';

$user_id = (int)$_SESSION['user_id'];
$notifications = getNotifications($conn, $user_id, 100);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - DTS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/custom.css" rel="stylesheet">
</head>
<body>
    <div class="container py-5">
        <h3 class="mb-4">Your Notifications</h3>
        <div class="mb-3">
            <button class="btn btn-sm btn-primary" onclick="markAllRead()">Mark All as Read</button>
            <a class="btn btn-sm btn-outline-secondary" href="../<?php echo $_SESSION['role']; ?>/<?php echo $_SESSION['role']; ?>_dashboard.php">Back to dashboard</a>
        </div>

        <?php if (empty($notifications)): ?>
            <div class="alert alert-info">No notifications found.</div>
        <?php else: ?>
            <div class="list-group">
                <?php foreach ($notifications as $notif): ?>
                    <a href="<?php echo htmlspecialchars($notif['url'] ?: '#'); ?>" class="list-group-item list-group-item-action <?php echo $notif['is_read'] ? '' : 'list-group-item-warning'; ?>" data-id="<?php echo $notif['notification_id']; ?>">
                        <div class="d-flex w-100 justify-content-between">
                            <h5 class="mb-1"><?php echo htmlspecialchars($notif['title']); ?></h5>
                            <small><?php echo date('M d, Y H:i', strtotime($notif['created_at'])); ?></small>
                        </div>
                        <p class="mb-1"><?php echo htmlspecialchars($notif['message']); ?></p>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
    function markAllRead() {
        fetch('mark_all_read.php', {method: 'POST'})
            .then(() => location.reload());
    }

    document.querySelectorAll('.list-group-item-action').forEach(item => {
        item.addEventListener('click', event => {
            const id = item.getAttribute('data-id');
            if (!id) return;
            fetch('mark_read.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'notification_id=' + encodeURIComponent(id)
            });
        });
    });
    </script>
</body>
</html>
