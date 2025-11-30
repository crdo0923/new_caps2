<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) die("error");

$user_id = $_SESSION['user_id'];
$msg_id = $_POST['msg_id'] ?? 0;
$emoji = $_POST['emoji'] ?? '';

if (!$msg_id || !$emoji) die("invalid");

// Check kung nag-react na
$check = $conn->prepare("SELECT reaction_id, reaction_emoji FROM message_reactions WHERE msg_id = ? AND user_id = ?");
$check->bind_param("ii", $msg_id, $user_id);
$check->execute();
$res = $check->get_result();

if ($res->num_rows > 0) {
    $row = $res->fetch_assoc();
    if ($row['reaction_emoji'] == $emoji) {
        // Kung parehas ang emoji, Tanggalin (Un-react)
        $conn->query("DELETE FROM message_reactions WHERE reaction_id = " . $row['reaction_id']);
    } else {
        // Kung iba, Palitan (Change reaction)
        $upd = $conn->prepare("UPDATE message_reactions SET reaction_emoji = ? WHERE reaction_id = ?");
        $upd->bind_param("si", $emoji, $row['reaction_id']);
        $upd->execute();
    }
} else {
    // Kung wala pa, Magdagdag (New reaction)
    $ins = $conn->prepare("INSERT INTO message_reactions (msg_id, user_id, reaction_emoji) VALUES (?, ?, ?)");
    $ins->bind_param("iis", $msg_id, $user_id, $emoji);
    $ins->execute();
}

echo "success";
?>