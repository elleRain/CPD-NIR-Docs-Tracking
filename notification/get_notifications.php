<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

include_once __DIR__ . '/../plugins/conn.php';
include_once __DIR__ . '/notification_helpers.php';

$user_id = (int)$_SESSION['user_id'];
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

$notifications = getNotifications($conn, $user_id, $limit);
$unread_count = getUnreadNotificationsCount($conn, $user_id);

echo json_encode(['success' => true, 'unread' => $unread_count, 'notifications' => $notifications]);
exit();
