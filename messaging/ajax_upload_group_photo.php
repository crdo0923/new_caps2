<?php
// messaging/ajax_upload_group_photo.php
include '../config.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    echo "Unauthorized";
    exit;
}

$current_user_id = $_SESSION['user_id'];
$group_id = isset($_POST['group_id']) ? (int)$_POST['group_id'] : 0;

if ($group_id === 0) {
    echo "Invalid group ID.";
    exit;
}

// Check if the user is a member of the group
$stmt = $conn->prepare("SELECT COUNT(*) FROM group_members WHERE group_id = ? AND user_id = ?");
$stmt->bind_param("ii", $group_id, $current_user_id);
$stmt->execute();
$stmt->bind_result($is_member);
$stmt->fetch();
$stmt->close();

if ($is_member == 0) {
    echo "Not authorized to change group photo.";
    exit;
}

// Handle file upload
if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['file'];
    $uploadDir = '../uploads/group_photos/'; // Ensure this directory exists and is writable
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    if (!in_array($fileExt, $allowedExts)) {
        echo "Invalid file type. Only JPG, JPEG, PNG, GIF, WEBP are allowed.";
        exit;
    }

    $newFileName = uniqid('group_') . '.' . $fileExt;
    $uploadPath = $uploadDir . $newFileName;
    $dbPath = 'uploads/group_photos/' . $newFileName; // Path to store in DB

    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        // Update group_photo in the groups table
        $stmt = $conn->prepare("UPDATE groups SET group_photo = ? WHERE group_id = ?");
        $stmt->bind_param("si", $dbPath, $group_id);
        if ($stmt->execute()) {
            echo "success";
        } else {
            echo "Error updating database: " . $stmt->error;
        }
        $stmt->close();
    } else {
        echo "Failed to move uploaded file.";
    }
} else {
    echo "No file uploaded or upload error.";
}

$conn->close();
?>