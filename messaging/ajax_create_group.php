<?php
// messaging/ajax_create_group.php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json'); // Set header to JSON

include 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Auth error']);
    exit;
}

$creator_id = $_SESSION['user_id'];
$group_name = $_POST['group_name'] ?? '';
$members = isset($_POST['members']) ? json_decode($_POST['members'], true) : [];

if (empty($group_name) || empty($members)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid input']);
    exit;
}

// 1. Create Group
$sql = "INSERT INTO `groups` (group_name, created_by) VALUES (?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $group_name, $creator_id);

if ($stmt->execute()) {
    $group_id = $stmt->insert_id;
    
    // 2. Add Creator to Group
    $members[] = $creator_id; 
    $members = array_unique($members);

    $sql_mem = "INSERT INTO group_members (group_id, user_id) VALUES (?, ?)";
    $stmt_mem = $conn->prepare($sql_mem);

    foreach ($members as $uid) {
        $stmt_mem->bind_param("ii", $group_id, $uid);
        $stmt_mem->execute();
    }

    // RETURN JSON DATA FOR REDIRECT: Ensure group_id is returned
    echo json_encode([
        'status' => 'success', 
        'group_id' => $group_id, 
        'group_name' => htmlspecialchars($group_name)
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'DB Error']);
}
?>