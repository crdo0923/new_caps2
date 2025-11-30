<?php
// messaging/ajax_get_notifications.php

// 1. Clean Output (Mahalaga ito para hindi mag-error ang JS)
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

include 'config.php';
session_start();

$current_user_id = $_SESSION['user_id'] ?? 0;

if ($current_user_id == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

$response = [
    'status' => 'success',
    'notifications' => [],
    'total_unread' => 0
];

// 2. KUNIN ANG TOTAL UNREAD COUNT (Para sa Sidebar Badge)
// Ito ang numero sa gilid ng "Messaging" (Lahat ng hindi pa nababasa)
$sql_total = "SELECT COUNT(*) as total FROM messages WHERE receiver_id = ? AND is_read = 0";
$stmt_total = $conn->prepare($sql_total);
$stmt_total->bind_param("i", $current_user_id);
$stmt_total->execute();
$res_total = $stmt_total->get_result();
if ($row_total = $res_total->fetch_assoc()) {
    $response['total_unread'] = intval($row_total['total']);
}

// 3. KUNIN ANG NEW NOTIFICATIONS (Para sa Popup)
// Ito ang mag-aalert lang kung bago (is_notified = 0)
$sql_notif = "SELECT m.sender_id, u.firstname, u.lastname, COUNT(m.msg_id) as count
              FROM messages m
              JOIN users u ON m.sender_id = u.id
              WHERE m.receiver_id = ? 
                AND m.is_read = 0 
                AND m.is_notified = 0
                AND m.sender_id NOT IN (SELECT muted_user_id FROM muted_users WHERE user_id = ?)
              GROUP BY m.sender_id, u.firstname, u.lastname";

$stmt_notif = $conn->prepare($sql_notif);
$stmt_notif->bind_param("ii", $current_user_id, $current_user_id);
$stmt_notif->execute();
$result_notif = $stmt_notif->get_result();

if ($result_notif->num_rows > 0) {
    while ($row = $result_notif->fetch_assoc()) {
        $response['notifications'][] = [
            'sender_id' => $row['sender_id'],
            'sender_name' => $row['firstname'] . ' ' . $row['lastname'],
            'count' => $row['count']
        ];
    }
    
    // Mark as notified para hindi na mag-popup ulit (pero unread pa rin sa badge)
    $update_sql = "UPDATE messages SET is_notified = 1 
                   WHERE receiver_id = ? AND is_read = 0 AND is_notified = 0";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("i", $current_user_id);
    $update_stmt->execute();
}

echo json_encode($response);
?>