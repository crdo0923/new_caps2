<?php
// messaging/ajax_handle_request.php
include 'config.php';

if (!isset($_SESSION['user_id'])) exit;

$user_id = $_SESSION['user_id'];
$partner_id = $_POST['partner_id'] ?? 0;
$action = $_POST['action'] ?? ''; 

if (!$partner_id || !$action) exit;

if ($action === 'accept') {
    // Accept: Set is_request = 0
    $stmt = $conn->prepare("UPDATE chat_settings SET is_request = 0 WHERE user_id = ? AND partner_id = ?");
    $stmt->bind_param("ii", $user_id, $partner_id);
    if ($stmt->execute()) echo "success";
} 
elseif ($action === 'decline') {
    // Decline: Delete settings (Hide chat)
    $stmt = $conn->prepare("DELETE FROM chat_settings WHERE user_id = ? AND partner_id = ?");
    $stmt->bind_param("ii", $user_id, $partner_id);
    if ($stmt->execute()) echo "success";
}
?>