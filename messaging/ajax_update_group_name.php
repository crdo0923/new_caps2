<?php
// messaging/ajax_update_group_name.php
include '../config.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    echo "Unauthorized";
    exit;
}

$current_user_id = $_SESSION['user_id'];
$group_id = isset($_POST['group_id']) ? (int)$_POST['group_id'] : 0;
$new_name = isset($_POST['new_name']) ? trim($_POST['new_name']) : '';

if ($group_id === 0 || empty($new_name)) {
    echo "Invalid group ID or name.";
    exit;
}

// Check if the user is a member or admin of the group (optional: restrict to admins)
$stmt = $conn->prepare("SELECT COUNT(*) FROM group_members WHERE group_id = ? AND user_id = ?");
$stmt->bind_param("ii", $group_id, $current_user_id);
$stmt->execute();
$stmt->bind_result($is_member);
$stmt->fetch();
$stmt->close();

if ($is_member == 0) {
    echo "Not authorized to change group name.";
    exit;
}

// Update the group name
$stmt = $conn->prepare("UPDATE groups SET group_name = ? WHERE group_id = ?");
$stmt->bind_param("si", $new_name, $group_id);
if ($stmt->execute()) {
    echo "success";
} else {
    echo "Error updating group name: " . $stmt->error;
}
$stmt->close();
$conn->close();
?>