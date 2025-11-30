<?php
include 'config.php'; 

if (!isset($_SESSION['user_id'])) { echo "error"; exit; }

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

// 1. UPDATE PREFERENCES
if ($action === 'update_preference') {
    $field = $_POST['field'];
    $value = (int)$_POST['value'];
    $allowed = ['active_status', 'notification_sound', 'do_not_disturb', 'app_lock_enabled'];
    if (!in_array($field, $allowed)) { echo "invalid_field"; exit; }

    $stmt = $conn->prepare("UPDATE users SET $field = ? WHERE id = ?");
    $stmt->bind_param("ii", $value, $user_id);
    if ($stmt->execute()) echo "success";
    else echo "error";
    $stmt->close();
}

// 2. BLOCK USER
if ($action === 'block_user') {
    $target_id = $_POST['target_id'];
    $stmt = $conn->prepare("INSERT IGNORE INTO blocked_users (blocker_id, blocked_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $user_id, $target_id);
    if ($stmt->execute()) echo "blocked";
    else echo "error";
}

// 3. UNBLOCK USER
if ($action === 'unblock_user') {
    $target_id = $_POST['target_id'];
    $stmt = $conn->prepare("DELETE FROM blocked_users WHERE blocker_id = ? AND blocked_id = ?");
    $stmt->bind_param("ii", $user_id, $target_id);
    if ($stmt->execute()) echo "success";
    else echo "error";
}

// 4. ARCHIVE / UNARCHIVE
if ($action === 'update_chat_status') {
    $type = $_POST['chat_type'];
    $target_id = $_POST['target_id'];
    $status_field = $_POST['status_field'];
    $status_val = (int)$_POST['status_value'];

    $col = ($type === 'group') ? 'group_id' : 'partner_id';
    
    $sql = "INSERT INTO chat_settings (user_id, $col, $status_field) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE $status_field = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiii", $user_id, $target_id, $status_val, $status_val);
    
    if ($stmt->execute()) echo "success";
    else echo "error";
}
?>