<?php
session_start();
header('Content-Type: application/json');

// I-check kung naka-login ang user at kung may data na ipinadala
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in.']);
    exit();
}

// ===================================================
// CONFIGURATION AND DATABASE CONNECTION
// ===================================================
$servername = 'localhost';
$db_username = 'root';
$db_password = ''; 
$database = 'smart_study';
$user_id = (int)($_SESSION['user_id']); 

$conn = mysqli_connect($servername, $db_username, $db_password, $database); 

if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit();
}

// ===================================================
// INPUT VALIDATION & SANITIZATION
// ===================================================

// Kukunin ang raw POST data
$data = json_decode(file_get_contents('php://input'), true);

$duration_minutes = (int)($data['duration'] ?? 0); // Duration in minutes
$points_earned = (int)($data['points'] ?? 0);      // Points earned
$subject = trim($data['subject'] ?? 'General Study'); // Study subject

// Simple validation
if ($duration_minutes <= 0 || $points_earned <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid duration or points. Session not saved.']);
    mysqli_close($conn);
    exit();
}

// I-sanitize ang subject
$subject = mysqli_real_escape_string($conn, substr($subject, 0, 100)); // Limit to 100 chars

// ===================================================
// DATABASE INSERTION
// ===================================================

$insert_query = "INSERT INTO study_sessions (user_id, subject, duration_minutes, points_earned, session_datetime) 
                 VALUES (?, ?, ?, ?, NOW())";

if ($stmt = $conn->prepare($insert_query)) {
    $stmt->bind_param("isii", $user_id, $subject, $duration_minutes, $points_earned);

    if ($stmt->execute()) {
        // I-update din ang total points sa users table
        $update_points_query = "UPDATE users SET points = points + ? WHERE id = ?";
        if ($update_stmt = $conn->prepare($update_points_query)) {
            $update_stmt->bind_param("ii", $points_earned, $user_id);
            $update_stmt->execute();
            $update_stmt->close();
        }

        echo json_encode(['success' => true, 'message' => 'Study session saved successfully! Points: +' . $points_earned]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save session.']);
    }

    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
}

mysqli_close($conn);
?>