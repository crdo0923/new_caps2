<?php
// ajax_task_action.php
session_start();

// 1. CONFIG PATH FINDER
if (file_exists('messaging/config.php')) {
    include 'messaging/config.php';
} elseif (file_exists('../messaging/config.php')) {
    include '../messaging/config.php';
} else {
    // Fallback
    $conn = new mysqli('localhost', 'root', '', 'smart_study');
}

if (!isset($_SESSION['user_id'])) { die("error_auth"); }

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';
$task_id = intval($_POST['task_id'] ?? 0);

if ($task_id === 0) { die("error_invalid_id"); }

// --- DELETE ---
if ($action === 'delete') {
    $stmt = $conn->prepare("DELETE FROM tasks WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $task_id, $user_id);
    if ($stmt->execute()) echo "success";
    else echo "error_db";
}

// --- COMPLETE ---
if ($action === 'complete') {
    // accept optional duration_spent (seconds)
    $duration_spent = intval($_POST['duration_spent'] ?? 0);

    // fetch task to determine priority and ensure ownership
    $get = $conn->prepare("SELECT priority, status FROM tasks WHERE id = ? AND user_id = ? LIMIT 1");
    $get->bind_param('ii', $task_id, $user_id);
    $get->execute();
    $res = $get->get_result();
    if (!$res || $res->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Task not found']);
        exit;
    }
    $task = $res->fetch_assoc();
    if ($task['status'] === 'completed') {
        echo json_encode(['status' => 'error', 'message' => 'Already completed']);
        exit;
    }

    $priority = $task['priority'] ?? 'Medium';

    // map priority -> base points
    $base = 10;
    if (strtolower($priority) === 'high') $base = 30;
    elseif (strtolower($priority) === 'medium') $base = 15;
    elseif (strtolower($priority) === 'low') $base = 7;

    // compute duration minutes and ratio (cap at 2x)
    $minutes = max(1, intval(round($duration_spent / 60)));
    $ratio = $minutes / 25.0;
    if ($ratio < 0.25) $ratio = 0.25;
    if ($ratio > 2) $ratio = 2;

    $awarded = max(1, intval(round($base * $ratio)));

    // Begin transaction: mark completed and update user points
    $conn->begin_transaction();
    try {
        $up = $conn->prepare("UPDATE tasks SET status = 'completed' WHERE id = ? AND user_id = ?");
        $up->bind_param('ii', $task_id, $user_id);
        $up->execute();

        $uget = $conn->prepare("SELECT points FROM users WHERE id = ? LIMIT 1");
        $uget->bind_param('i', $user_id); $uget->execute();
        $urow = $uget->get_result()->fetch_assoc();
        $oldPoints = intval($urow['points'] ?? 0);

        $un = $conn->prepare("UPDATE users SET points = points + ? WHERE id = ?");
        $un->bind_param('ii', $awarded, $user_id); $un->execute();

        // commit
        $conn->commit();

        // return updated total
        $total = $oldPoints + $awarded;
        echo json_encode(['status' => 'success', 'awarded_points' => $awarded, 'total_points' => $total]);
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'DB error', 'debug' => $e->getMessage()]);
        exit;
    }
}

// --- EDIT (NEW CODE) ---
if ($action === 'edit') {
    $title = $_POST['title'] ?? '';
    $desc = $_POST['description'] ?? '';
    
    $stmt = $conn->prepare("UPDATE tasks SET title = ?, description = ? WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ssii", $title, $desc, $task_id, $user_id);
    if ($stmt->execute()) echo "success";
    else echo "error_db";
}

// --- CHANGE SUBJECT ---
if ($action === 'change_subject') {
    $new_subject = trim($_POST['subject'] ?? '');
    if ($new_subject === '') { echo json_encode(['status' => 'error', 'message' => 'Subject required']); exit; }
    $stmt = $conn->prepare("UPDATE tasks SET subject = ? WHERE id = ? AND user_id = ?");
    $stmt->bind_param('sii', $new_subject, $task_id, $user_id);
    if ($stmt->execute()) echo json_encode(['status' => 'success', 'subject' => $new_subject]);
    else echo json_encode(['status' => 'error', 'message' => 'DB error', 'debug' => $stmt->error]);
    exit;
}
?>