<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = (int)$_SESSION['user_id'];

include_once __DIR__ . '/../plugins/conn.php';
include_once __DIR__ . '/notification_helpers.php';

$updated = markAllNotificationsRead($conn, $user_id);

if ($updated) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Could not update']);
}
exit();
