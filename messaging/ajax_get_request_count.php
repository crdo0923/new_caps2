<?php
// messaging/ajax_get_request_count.php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    echo "0";
    exit;
}

$user_id = $_SESSION['user_id'];

// Bilangin lahat ng row sa settings na naka-mark as request (is_request = 1)
$sql = "SELECT COUNT(*) as count FROM chat_settings WHERE user_id = ? AND is_request = 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

echo $row['count'];
?>