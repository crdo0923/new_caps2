<?php
// messaging/ajax_log_call_history.php
include 'config.php';

if (!isset($_SESSION['user_id']) || !isset($_POST['duration'])) { exit; }

$duration = (int)$_POST['duration'];
$current_user_id = (int)$_SESSION['user_id'];
$chat_id = (int)$_POST['chat_id']; 
$chat_type = $_POST['chat_type'];
$caller_id = (int)$_POST['caller_id']; // ID ng taong nag-initiate ng call

// Special marker format: [[CALL_LOG:DURATION:CALLER_ID]]
$message = "[[CALL_LOG:$duration:$caller_id]]";

$group_id = ($chat_type === 'group') ? $chat_id : NULL;

// 1. Gagamit lang tayo ng ISANG INSERT at i-marka itong READ (is_read = 1)
$sql = "INSERT INTO messages (sender_id, receiver_id, group_id, message, is_read) 
        VALUES (?, ?, ?, ?, 1)"; // <-- FIXED: is_read = 1 para hindi maging unread
$stmt = $conn->prepare($sql);

if ($chat_type === 'user') {
    $partner_id = $chat_id; 
    
    // Log the message mula sa TAONG NAG-END ng call ($current_user_id) patungo sa partner.
    // Ito ay iisang entry lang.
    $receiver_id = $partner_id;
    $stmt->bind_param("iiss", $current_user_id, $receiver_id, $group_id, $message);
    
} elseif ($chat_type === 'group') {
    // Para sa Group chat (receiver_id is NULL)
    $receiver_id = NULL; 
    $stmt->bind_param("iiss", $current_user_id, $receiver_id, $group_id, $message);
}

$stmt->execute();
if (isset($stmt)) $stmt->close();
echo "success";
?>  