<?php
// messaging/ajax_mark_read.php
include 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) exit;

$current_user_id = $_SESSION['user_id'];
$target_id = $_POST['target_id'] ?? 0; // Updated parameter name
$type = $_POST['type'] ?? 'user';      // Updated parameter name

if ($target_id == 0) exit;

if ($type === 'group') {
    // GROUP: Mark messages in this group as read
    // Note: Since the DB is simple, this marks it read for everyone. 
    // Ideally, you'd have a separate 'read_receipts' table for groups.
    $stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE group_id = ? AND sender_id != ? AND is_read = 0");
    $stmt->bind_param("ii", $target_id, $current_user_id);
    $stmt->execute();
    $stmt->close();
} else {
    // USER: Mark messages from this specific sender as read
    $stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
    $stmt->bind_param("ii", $target_id, $current_user_id);
    $stmt->execute();
    $stmt->close();
}

echo "success";
?>