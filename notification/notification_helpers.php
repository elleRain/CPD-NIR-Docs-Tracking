<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

function getCurrentUserId() {
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
}

function createNotification($conn, $user_id, $title, $message, $url = '#', $type = 'info', $actor_id = 0, $related_document_id = 0) {
    if (!$user_id || !$conn) {
        return false;
    }

    $stmt = $conn->prepare("INSERT INTO notifications (user_id, actor_id, related_document_id, type, title, message, url, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    if (!$stmt) {
        return false;
    }

    $actor_id = (int)$actor_id;
    $related_document_id = (int)$related_document_id;
    $user_id = (int)$user_id;
    $type = trim($type);
    $title = trim($title);
    $message = trim($message);
    $url = trim($url);

    $stmt->bind_param('iiissss', $user_id, $actor_id, $related_document_id, $type, $title, $message, $url);
    $success = $stmt->execute();
    $stmt->close();

    return $success;
}

function getNotifications($conn, $user_id, $limit = 10) {
    $user_id = (int)$user_id;
    $limit = (int)$limit;

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

function getUnreadNotificationsCount($conn, $user_id) {
    $user_id = (int)$user_id;
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

function markNotificationRead($conn, $notification_id, $user_id) {
    $notification_id = (int)$notification_id;
    $user_id = (int)$user_id;

    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?");
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ii', $notification_id, $user_id);
    $success = $stmt->execute();
    $stmt->close();

    return $success;
}

function markAllNotificationsRead($conn, $user_id) {
    $user_id = (int)$user_id;
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('i', $user_id);
    $success = $stmt->execute();
    $stmt->close();

    return $success;
}
