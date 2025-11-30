<?php
// messaging/ajax_toggle_mute.php
error_reporting(0); // Iwas error output
ini_set('display_errors', 0);

include 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) die("error");

$user_id = $_SESSION['user_id'];
$target_id = $_POST['user_id'] ?? 0;

if ($target_id == 0) die("error");

// Check kung naka-mute na
$check = $conn->prepare("SELECT id FROM muted_users WHERE user_id = ? AND muted_user_id = ?");
$check->bind_param("ii", $user_id, $target_id);
$check->execute();
$res = $check->get_result();

if ($res->num_rows > 0) {
    // UNMUTE (Delete row)
    $del = $conn->prepare("DELETE FROM muted_users WHERE user_id = ? AND muted_user_id = ?");
    $del->bind_param("ii", $user_id, $target_id);
    $del->execute();
    echo "unmuted";
} else {
    // MUTE (Insert row)
    $ins = $conn->prepare("INSERT INTO muted_users (user_id, muted_user_id) VALUES (?, ?)");
    $ins->bind_param("ii", $user_id, $target_id);
    $ins->execute();
    echo "muted";
}
?>