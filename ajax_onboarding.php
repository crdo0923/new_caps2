<?php
session_start();
header('Content-Type: application/json');

$payload = json_decode(file_get_contents('php://input'), true);
$action = $payload['action'] ?? null;
$dont_show = (int)($payload['dont_show'] ?? 0);

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];

$servername = 'localhost'; $db_username = 'root'; $db_password = ''; $database = 'smart_study';
$conn = new mysqli($servername, $db_username, $db_password, $database);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'DB connection failed']);
    exit;
}

// Support 'mark_seen' (persist seen) and 'reset' (re-enable tutorial)
if ($action === 'mark_seen') {
    // Ensure column exists
    $col_check = $conn->query("SHOW COLUMNS FROM users LIKE 'onboarding_seen'");
    if (!$col_check || $col_check->num_rows === 0) {
        // Try to create column (best-effort)
        $alter = "ALTER TABLE users ADD COLUMN onboarding_seen TINYINT(1) NOT NULL DEFAULT 0";
        if (!$conn->query($alter)) {
            // Cannot alter table - fallback to session flag
            $_SESSION['onboarding_seen'] = $dont_show ? 1 : 0;
            echo json_encode(['success' => true, 'message' => 'Saved preference in session (DB schema missing).']);
            $conn->close();
            exit;
        }
    }

    // Update user record
    $stmt = $conn->prepare("UPDATE users SET onboarding_seen = ? WHERE id = ?");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Prepare failed']);
        $conn->close();
        exit;
    }
    $val = 1; // mark as seen
    $stmt->bind_param('ii', $val, $user_id);
    $ok = $stmt->execute();
    $stmt->close();

    if ($ok) {
        echo json_encode(['success' => true, 'message' => 'Onboarding preference saved']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update preference']);
    }
    $conn->close();
    exit;
}

if ($action === 'reset') {
    // Ensure column exists
    $col_check = $conn->query("SHOW COLUMNS FROM users LIKE 'onboarding_seen'");
    if (!$col_check || $col_check->num_rows === 0) {
        $alter = "ALTER TABLE users ADD COLUMN onboarding_seen TINYINT(1) NOT NULL DEFAULT 0";
        if (!$conn->query($alter)) {
            // Could not alter: fallback to session flag
            $_SESSION['onboarding_seen'] = 0;
            echo json_encode(['success' => true, 'message' => 'Reset in session (DB missing)']);
            $conn->close();
            exit;
        }
    }

    $stmt = $conn->prepare("UPDATE users SET onboarding_seen = 0 WHERE id = ?");
    if (!$stmt) { echo json_encode(['success' => false, 'message' => 'Prepare failed']); $conn->close(); exit; }
    $stmt->bind_param('i', $user_id);
    $ok = $stmt->execute();
    $stmt->close();

    if ($ok) echo json_encode(['success' => true, 'message' => 'Tutorial reset']);
    else echo json_encode(['success' => false, 'message' => 'Failed to reset']);
    $conn->close();
    exit;
}

// Unsupported action
echo json_encode(['success' => false, 'message' => 'Unsupported action']);
$conn->close();
exit;
?>