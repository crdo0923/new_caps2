<?php
// messaging/ajax_search_users.php

// 1. Prevent HTML errors/warnings from breaking JSON
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

include 'config.php';

// 2. Safe Session Start (Check if already active from config.php)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

$current_user_id = $_SESSION['user_id'];
$query = $_POST['query'] ?? '';
$search_term = "%{$query}%";

// 3. IMPROVED SQL: Search Firstname, Lastname, OR Full Name (Concat)
$sql = "SELECT id, firstname, lastname, profile_photo FROM users 
        WHERE id != ? 
        AND (
            firstname LIKE ? 
            OR lastname LIKE ? 
            OR CONCAT(firstname, ' ', lastname) LIKE ?
        )
        LIMIT 10";

$stmt = $conn->prepare($sql);
// Bind 4 params: ID (int), Term (string), Term (string), Term (string)
$stmt->bind_param("isss", $current_user_id, $search_term, $search_term, $search_term);
$stmt->execute();
$result = $stmt->get_result();

$users = [];
while ($row = $result->fetch_assoc()) {
    // Profile Photo Logic
    $img = 'profile-img/' . $row['profile_photo'];
    // Check relative to the ajax file location
    if (empty($row['profile_photo']) || !file_exists('../' . $img)) {
        $row['avatar_html'] = "<div class='default-avatar-small'>" . strtoupper(substr($row['firstname'], 0, 1)) . "</div>";
    } else {
        $row['avatar_html'] = "<img src='$img' class='avatar-img-small'>";
    }
    $users[] = $row;
}

echo json_encode($users);
?>