<?php
// messaging/ajax_delete_message.php

// 1. START SESSION & SET UP ERRORS
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Binalik sa 0 ang error reporting para hindi mag-output ng Warning ang unlink/include
error_reporting(0);
ini_set('display_errors', 0);

// 2. ROBUST CONFIG INCLUDE FIX (Lines 14-18)
// Ito ang nagche-check sa iba't ibang path para siguradong mahanap ang config
if (file_exists('../config.php')) {
    include '../config.php';
} elseif (file_exists('../../config.php')) {
    include '../../config.php';
} elseif (file_exists('config.php')) {
    include 'config.php';
} else {
    // Kung hindi mahanap ang config, i-die bago pa mag-prepare (iwas Fatal Error)
    die("error_config_not_found");
}

if (!isset($_SESSION['user_id'])) {
    die("error_not_logged_in_genuine"); 
}

$current_user_id = $_SESSION['user_id'];
$msg_id = $_POST['msg_id'] ?? 0;
$delete_type = $_POST['delete_type'] ?? ''; 

if ($msg_id == 0 || empty($delete_type)) {
    die("error_invalid_request");
}

// 3. Check Ownership & Get Content (Line 31 Fix starts here)
$stmt_check = $conn->prepare("SELECT sender_id, receiver_id, message FROM messages WHERE msg_id = ?");
$stmt_check->bind_param("i", $msg_id);
$stmt_check->execute();
$result = $stmt_check->get_result();

if ($result->num_rows == 0) {
    die("error_msg_not_found");
}

$row = $result->fetch_assoc();
$sender_id = $row['sender_id'];
$receiver_id = $row['receiver_id'];
$message_content = $row['message'];

$sql = ""; // Initialize SQL string

// 4. --- DELETE LOGIC (Setting the SQL Query) ---
if ($delete_type === 'me') {
    if ($current_user_id == $sender_id) {
        $sql = "UPDATE messages SET deleted_by_sender = 1 WHERE msg_id = ?";
    } elseif ($current_user_id == $receiver_id) {
        $sql = "UPDATE messages SET deleted_by_receiver = 1 WHERE msg_id = ?";
    } else {
        die("error_not_participant");
    }
} elseif ($delete_type === 'everyone') {
    if ($current_user_id == $sender_id) {
        
        // A. Delete File from Server (Unlink)
        if (strpos($message_content, 'uploads/') === 0) {
            $file_path = "../" . $message_content; 
            if (file_exists($file_path)) {
                @unlink($file_path); 
            }
        }

        // B. Update DB marker
        $sql = "UPDATE messages SET message = '[[MESSAGE_UNSENT]]' WHERE msg_id = ?";
        
    } else {
        die("error_not_sender");
    }
} else {
    die("error_invalid_type");
}

// 5. --- EXECUTION PHASE (Unified) ---

if (!empty($sql)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $msg_id);

    if ($stmt->execute()) {
        echo "success";
    } else {
        echo "error_db_fail: " . $conn->error;
    }
} else {
    die("error_internal_state");
}

if (isset($stmt)) $stmt->close();
$conn->close();
?>