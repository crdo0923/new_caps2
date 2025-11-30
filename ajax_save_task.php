<?php
// ajax_save_task.php — accepts POST from client (JS) and inserts a new task for the logged-in user
session_start();

// Include DB config if available
if (file_exists('messaging/config.php')) include 'messaging/config.php';
elseif (file_exists('../messaging/config.php')) include '../messaging/config.php';
else $conn = new mysqli('localhost', 'root', '', 'smart_study');

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
    exit;
}

$user_id = intval($_SESSION['user_id']);
$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$subject = trim($_POST['subject'] ?? 'General');
$priority = trim($_POST['priority'] ?? 'Medium');
$time = trim($_POST['time'] ?? '');
$duration = trim($_POST['duration'] ?? '');

if ($title === '') {
    echo json_encode(['status' => 'error', 'message' => 'Title is required']);
    exit;
}

// Basic sanitization/length checks
if (strlen($title) > 255) $title = substr($title, 0, 255);
if (strlen($subject) > 100) $subject = substr($subject, 0, 100);
if (strlen($priority) > 20) $priority = substr($priority, 0, 20);

// Insert into DB
$stmt = $conn->prepare("INSERT INTO tasks (user_id, title, description, subject, priority, time_sched, duration, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'DB prepare failed', 'debug' => $conn->error]);
    exit;
}

$stmt->bind_param('issssss', $user_id, $title, $description, $subject, $priority, $time, $duration);
if ($stmt->execute()) {
    $new_id = $stmt->insert_id;
    echo json_encode(['status' => 'success', 'task_id' => $new_id]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'DB execution failed', 'debug' => $stmt->error]);
}
$stmt->close();
$conn->close();
exit;
?>