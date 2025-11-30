<?php
// messaging/ajax_kick_member.php
include 'config.php';
session_start();

if (!isset($_SESSION['user_id']) || !isset($_POST['group_id']) || !isset($_POST['user_id'])) {
    echo "error"; exit;
}

$admin_id = $_SESSION['user_id'];
$group_id = intval($_POST['group_id']);
$target_user_id = intval($_POST['user_id']);

// 1. SECURITY CHECK: Verify if requestor is the Group Creator (Admin)
$check_sql = "SELECT created_by FROM groups WHERE group_id = ?";
$stmt = $conn->prepare($check_sql);
$stmt->bind_param("i", $group_id);
$stmt->execute();
$res = $stmt->get_result();
$group = $res->fetch_assoc();

if (!$group || $group['created_by'] != $admin_id) {
    echo "unauthorized"; // Hindi ikaw ang admin!
    exit;
}

// 2. DELETE MEMBER
$del_sql = "DELETE FROM group_members WHERE group_id = ? AND user_id = ?";
$del_stmt = $conn->prepare($del_sql);
$del_stmt->bind_param("ii", $group_id, $target_user_id);

if ($del_stmt->execute()) {
    echo "success";
} else {
    echo "error";
}
?>