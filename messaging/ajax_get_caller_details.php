<?php
include 'config.php';

if (!isset($_POST['user_id'])) {
    echo json_encode(['error' => 'No ID']);
    exit;
}

$user_id = $_POST['user_id'];

$stmt = $conn->prepare("SELECT firstname, lastname, profile_photo FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();

if ($row = $res->fetch_assoc()) {
    $name = htmlspecialchars($row['firstname'] . ' ' . $row['lastname']);
    $photo = $row['profile_photo'];
    
    // Avatar Logic (Gaya ng sa iba mong pages)
    if (!empty($photo) && file_exists('../profile-img/' . $photo)) {
        $avatar_html = "<img src='profile-img/$photo' style='width:100%; height:100%; object-fit:cover;'>";
    } else {
        $initial = strtoupper(substr($name, 0, 1));
        $avatar_html = "<div style='width:100%; height:100%; background:#6366f1; color:white; display:flex; align-items:center; justify-content:center; font-size:1.5rem; font-weight:bold;'>$initial</div>";
    }

    echo json_encode([
        'status' => 'success',
        'name' => $name,
        'avatar' => $avatar_html
    ]);
} else {
    echo json_encode(['status' => 'error']);
}
?>