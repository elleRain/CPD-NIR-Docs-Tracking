<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    return;
}

include_once __DIR__ . '/../plugins/conn.php';
include_once __DIR__ . '/notification_helpers.php';

$user_id = (int)$_SESSION['user_id'];
$unread_count = getUnreadNotificationsCount($conn, $user_id);
$notifications = getNotifications($conn, $user_id, 5);
?>
<div class="dropdown">
    <button class="btn navbar-user-btn position-relative" type="button" id="notifDropdown" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifications">
        <i class="bi bi-bell-fill fs-5"></i>
        <?php if ($unread_count > 0): ?>
            <span class="notif-badge"><?php echo $unread_count; ?></span>
        <?php endif; ?>
    </button>
    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 notification-dropdown" aria-labelledby="notifDropdown" style="min-width: 300px;">
        <li class="dropdown-header d-flex justify-content-between align-items-center px-3 py-2">
            <span class="fw-bold">Notifications</span>
            <a href="../notification/mark_all_read.php" class="small notif-mark-all" onclick="markAllRead(event)">Mark all read</a>
        </li>
        <?php if (empty($notifications)): ?>
            <li class="px-3 py-3 text-center text-muted">No notifications yet.</li>
        <?php else: ?>
            <?php foreach ($notifications as $notif): ?>
                <li>
                    <a href="<?php echo htmlspecialchars($notif['url'] ?: '#'); ?>" class="dropdown-item notification-item <?php echo $notif['is_read'] ? '' : 'unread'; ?>" data-notification-id="<?php echo $notif['notification_id']; ?>">
                        <div class="d-flex justify-content-between">
                            <strong><?php echo htmlspecialchars($notif['title']); ?></strong>
                            <small class="text-muted"><?php echo date('M d', strtotime($notif['created_at'])); ?></small>
                        </div>
                        <small class="text-secondary d-block text-truncate" style="max-width: 90%;"><?php echo htmlspecialchars($notif['message']); ?></small>
                    </a>
                </li>
            <?php endforeach; ?>
        <?php endif; ?>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item text-center" href="../notification/notifications_page.php">View all notifications</a></li>
    </ul>
</div>
<script>
function markNotificationAsRead(notificationId) {
    fetch('../notification/mark_read.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'notification_id=' + encodeURIComponent(notificationId)
    });
}

function markAllRead(event) {
    event.preventDefault();
    fetch('../notification/mark_all_read.php', { method: 'POST' })
        .then(() => location.reload());
}

document.addEventListener('DOMContentLoaded', function () {
    var nodes = document.querySelectorAll('.notification-item');
    nodes.forEach(function (node) {
        node.addEventListener('click', function () {
            var id = this.getAttribute('data-notification-id');
            if (id) {
                markNotificationAsRead(id);
            }
        });
    });
});
</script>
