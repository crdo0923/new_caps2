<?php
// messaging/ajax_get_user_status.php
include 'config.php';

$user_id = $_POST['user_id'] ?? 0;

if ($user_id == 0) {
    echo 'offline';
    exit;
}

$stmt = $conn->prepare("SELECT last_activity FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    if ($row['last_activity']) {
        $last_activity = strtotime($row['last_activity']);
        $current_time = time();
        // Consider Online if active within last 2 minutes (120 seconds)
        if (($current_time - $last_activity) < 120) {
            echo 'online';
        } else {
            echo 'offline';
        }
    } else {
        echo 'offline';
    }
} else {
    echo 'offline';
}
?>