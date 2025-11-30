<?php
// messaging/ajax_options.php
include 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo "error";
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

if ($action === 'toggle_active_status') {
    // Get current status
    $stmt = $conn->prepare("SELECT is_active_public FROM users WHERE id = ?");
    // Note: If you don't have an 'is_active_public' column, you might need to add it.
    // For now, we will assume we are toggling a session-based preference or just simulating success.
    // If you want to make it permanent, run: ALTER TABLE users ADD COLUMN is_active_public TINYINT DEFAULT 1;
    
    // For this example, we'll just return the opposite of what was sent to simulate the toggle
    $current_status = $_POST['current_status'] ?? '1';
    $new_status = ($current_status == '1') ? '0' : '1';
    
    // Optional: Update DB
    // $upd = $conn->prepare("UPDATE users SET is_active_public = ? WHERE id = ?");
    // $upd->bind_param("ii", $new_status, $user_id);
    // $upd->execute();
    
    echo $new_status; // Return the new status
}
elseif ($action === 'report_problem') {
    $problem = $_POST['problem'] ?? '';
    // Save to DB or Email
    // For now, just success
    echo "success";
}
?>