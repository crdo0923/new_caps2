<?php
// messaging/ajax_update_name.php

// 1. FIX: Use absolute path relative to this file to find config.php
// Assuming config.php is in the 'messaging' folder with this file:
if (file_exists('config.php')) {
    include 'config.php';
} 
// Or if config.php is in the parent directory:
elseif (file_exists('../config.php')) {
    include '../config.php';
} else {
    die("Error: config.php not found.");
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) { echo "auth_error"; exit; }

$user_id = $_SESSION['user_id'];
$type = $_POST['type'] ?? ''; 
$target_id = $_POST['target_id'] ?? 0;
$new_name = trim($_POST['new_name'] ?? '');

if (empty($target_id)) { echo "error_no_id"; exit; }
// For groups, name is required. For users, empty means reset.
if ($type === 'group' && empty($new_name)) { echo "empty_group_name"; exit; }
// 1. RENAME PERSONAL CHAT (NICKNAME)
if ($type === 'user') {
    // ... (keep connection check) ...

    // [NEW RESET LOGIC]
    if ($new_name === "") {
        // If empty name sent -> RESET (Delete nickname column or set to NULL)
        $stmt = $conn->prepare("UPDATE chat_settings SET nickname = NULL WHERE user_id = ? AND partner_id = ?");
        $stmt->bind_param("ii", $user_id, $target_id);
    } else {
        // Normal Update/Insert Logic
        $check = $conn->prepare("SELECT id FROM chat_settings WHERE user_id = ? AND partner_id = ?");
        $check->bind_param("ii", $user_id, $target_id);
        $check->execute();
        
        if ($check->get_result()->num_rows > 0) {
            $stmt = $conn->prepare("UPDATE chat_settings SET nickname = ? WHERE user_id = ? AND partner_id = ?");
            $stmt->bind_param("sii", $new_name, $user_id, $target_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO chat_settings (user_id, partner_id, nickname) VALUES (?, ?, ?)");
            $stmt->bind_param("iis", $user_id, $target_id, $new_name);
        }
    }

    if ($stmt->execute()) echo "success";
    else echo "db_error: " . $conn->error;
}

// 2. RENAME GROUP CHAT
elseif ($type === 'group') {
    $chk = $conn->prepare("SELECT id FROM group_members WHERE group_id = ? AND user_id = ?");
    $chk->bind_param("ii", $target_id, $user_id);
    $chk->execute();
    
    if ($chk->get_result()->num_rows > 0) {
        $stmt = $conn->prepare("UPDATE groups SET group_name = ? WHERE group_id = ?");
        $stmt->bind_param("si", $new_name, $target_id);
        if ($stmt->execute()) echo "success";
        else echo "db_error";
    } else {
        echo "not_member";
    }
}
?>