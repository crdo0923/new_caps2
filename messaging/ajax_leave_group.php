<?php
// messaging/ajax_leave_group.php
include '../config.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    echo "Unauthorized";
    exit;
}

$current_user_id = $_SESSION['user_id'];
$group_id = isset($_POST['group_id']) ? (int)$_POST['group_id'] : 0;

if ($group_id === 0) {
    echo "Invalid group ID.";
    exit;
}

// Check if the user is a member of the group
$stmt = $conn->prepare("SELECT COUNT(*) FROM group_members WHERE group_id = ? AND user_id = ?");
$stmt->bind_param("ii", $group_id, $current_user_id);
$stmt->execute();
$stmt->bind_result($is_member);
$stmt->fetch();
$stmt->close();

if ($is_member == 0) {
    echo "Not a member of this group.";
    exit;
}

// Remove the user from the group
$stmt = $conn->prepare("DELETE FROM group_members WHERE group_id = ? AND user_id = ?");
$stmt->bind_param("ii", $group_id, $current_user_id);
if ($stmt->execute()) {
    // Optionally log this action or send a message to the group
    // Example: Insert into messages table that user has left
    $message = htmlspecialchars($_SESSION['firstname'] . " " . $_SESSION['lastname']) . " has left the group.";
    $insert_stmt = $conn->prepare("INSERT INTO messages (sender_id, group_id, message, timestamp) VALUES (?, ?, ?, NOW())");
    $insert_stmt->bind_param("iis", $current_user_id, $group_id, $message);
    $insert_stmt->execute();
    $insert_stmt->close();

    echo "success";
} else {
    echo "Error leaving group: " . $stmt->error;
}
$stmt->close();
$conn->close();
?>