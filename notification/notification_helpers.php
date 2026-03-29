<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

function getCurrentUserId() {
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
}

function notificationTableHasColumn($conn, $table, $column) {
    if (!$conn) {
        return false;
    }

    $table = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $res && $res->num_rows > 0;
}

function getNotificationSchemaMode($conn) {
    static $mode = null;
    if ($mode !== null) {
        return $mode;
    }

    if (notificationTableHasColumn($conn, 'notifications', 'user_id')) {
        $mode = 'legacy';
        return $mode;
    }

    if (notificationTableHasColumn($conn, 'notifications', 'actor_user_id')) {
        $mode = 'targets';
        return $mode;
    }

    $mode = 'unknown';
    return $mode;
}

function createNotification($conn, $user_id, $title, $message, $url = '#', $type = 'info', $actor_id = 0, $related_document_id = 0) {
    if (!$user_id || !$conn) {
        return false;
    }

    $actor_id = (int)$actor_id;
    $related_document_id = (int)$related_document_id;
    $user_id = (int)$user_id;
    $type = trim($type);
    $title = trim($title);
    $message = trim($message);
    $url = trim($url);

    $mode = getNotificationSchemaMode($conn);

    if ($mode === 'legacy') {
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, actor_id, related_document_id, type, title, message, url, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('iiissss', $user_id, $actor_id, $related_document_id, $type, $title, $message, $url);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    if ($mode === 'targets') {
        $data_json = json_encode([
            'recipient_user_id' => $user_id,
            'related_document_id' => $related_document_id,
            'url' => $url,
        ]);

        $stmt = $conn->prepare("INSERT INTO notifications (type, title, message, data_json, link_url, actor_user_id, document_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('sssssii', $type, $title, $message, $data_json, $url, $actor_id, $related_document_id);
        $success = $stmt->execute();
        $notification_id = $success ? (int)$conn->insert_id : 0;
        $stmt->close();

        if (!$success || $notification_id <= 0) {
            return false;
        }

        $target_stmt = $conn->prepare("INSERT INTO notification_targets (notification_id, user_id, is_read, created_at) VALUES (?, ?, 0, NOW())");
        if (!$target_stmt) {
            return false;
        }

        $target_stmt->bind_param('ii', $notification_id, $user_id);
        $target_ok = $target_stmt->execute();
        $target_stmt->close();

        return $target_ok;
    }

    return false;
}

function getNotifications($conn, $user_id, $limit = 10) {
    $user_id = (int)$user_id;
    $limit = (int)$limit;

    $mode = getNotificationSchemaMode($conn);

    if ($mode === 'legacy') {
        $stmt = $conn->prepare("SELECT notification_id, actor_id, related_document_id, type, title, message, url, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('ii', $user_id, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $notifications = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $notifications;
    }

    if ($mode === 'targets') {
        $stmt = $conn->prepare("SELECT n.notification_id, n.actor_user_id AS actor_id, n.document_id AS related_document_id, n.type, n.title, n.message, n.link_url AS url, nt.is_read, n.created_at
                                FROM notification_targets nt
                                JOIN notifications n ON n.notification_id = nt.notification_id
                                WHERE nt.user_id = ? AND nt.is_deleted = 0
                                ORDER BY n.created_at DESC
                                LIMIT ?");
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('ii', $user_id, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $notifications = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $notifications;
    }

    return [];
}

function getUnreadNotificationsCount($conn, $user_id) {
    $user_id = (int)$user_id;
    $mode = getNotificationSchemaMode($conn);

    if ($mode === 'legacy') {
        $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM notifications WHERE user_id = ? AND is_read = 0");
        if (!$stmt) {
            return 0;
        }

        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();
        return $count ? (int)$count : 0;
    }

    if ($mode === 'targets') {
        $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM notification_targets WHERE user_id = ? AND is_read = 0 AND is_deleted = 0");
        if (!$stmt) {
            return 0;
        }

        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();
        return $count ? (int)$count : 0;
    }

    return 0;
}

function markNotificationRead($conn, $notification_id, $user_id) {
    $notification_id = (int)$notification_id;
    $user_id = (int)$user_id;

    $mode = getNotificationSchemaMode($conn);

    if ($mode === 'legacy') {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?");
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('ii', $notification_id, $user_id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    if ($mode === 'targets') {
        $stmt = $conn->prepare("UPDATE notification_targets SET is_read = 1, read_at = NOW() WHERE notification_id = ? AND user_id = ?");
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('ii', $notification_id, $user_id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    return false;
}

function markAllNotificationsRead($conn, $user_id) {
    $user_id = (int)$user_id;
    $mode = getNotificationSchemaMode($conn);

    if ($mode === 'legacy') {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('i', $user_id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    if ($mode === 'targets') {
        $stmt = $conn->prepare("UPDATE notification_targets SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0 AND is_deleted = 0");
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('i', $user_id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    return false;
}
