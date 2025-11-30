<?php
// messaging/ajax_fetch_media.php

// 1. ERROR REPORTING (Para makita natin kung may error)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Hide from output json
header('Content-Type: application/json');

// 2. ROBUST CONFIG INCLUDE (The Fix)
if (file_exists('config.php')) {
    include 'config.php';
} elseif (file_exists('../config.php')) {
    include '../config.php';
} else {
    echo json_encode(['media' => '<p>Error: Config not found</p>', 'files' => '']);
    exit;
}

if (session_status() === PHP_SESSION_NONE) session_start();

$current_user_id = $_SESSION['user_id'] ?? 0;
$target_id = $_POST['target_id'] ?? 0;
$chat_type = $_POST['chat_type'] ?? 'user';

// Validate inputs
if ($current_user_id == 0 || $target_id == 0) {
    echo json_encode(['media' => "<div class='empty-state'>Chat ID missing ($target_id)</div>", 'files' => '']);
    exit;
}

// 3. BUILD QUERY
if ($chat_type === 'group') {
    $sql = "SELECT msg_id, message, timestamp FROM messages 
            WHERE group_id = ? 
            AND message LIKE 'uploads/%' 
            AND message != '[[MESSAGE_UNSENT]]'
            ORDER BY timestamp DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $target_id);
} else {
    $sql = "SELECT msg_id, message, timestamp FROM messages 
            WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
            AND message LIKE 'uploads/%' 
            AND message != '[[MESSAGE_UNSENT]]'
            ORDER BY timestamp DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiii", $current_user_id, $target_id, $target_id, $current_user_id);
}

if (!$stmt->execute()) {
    echo json_encode(['media' => '<p>DB Error</p>', 'files' => '']);
    exit;
}

$result = $stmt->get_result();

// 4. PROCESS FILES
$media_groups = [];
$file_groups = [];
$image_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$has_items = false;

function formatSize($bytes) {
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' bytes';
}

while ($row = $result->fetch_assoc()) {
    // Fix Path: Assuming uploads folder is in root, and this script is in /messaging/
    $relative_path_from_script = "../" . $row['message']; 
    
    if (!file_exists($relative_path_from_script)) {
        // Debug: Uncomment to check path issues
        // continue; 
    }

    $has_items = true;
    $month = date("F Y", strtotime($row['timestamp']));
    $ext = strtolower(pathinfo($row['message'], PATHINFO_EXTENSION));
    $filename = basename($row['message']);
    
    // Safely get filesize
    $filesize = file_exists($relative_path_from_script) ? formatSize(filesize($relative_path_from_script)) : 'Unknown size';

    if (in_array($ext, $image_exts)) {
        $media_groups[$month][] = [
            'src' => $row['message'], // Path for HTML <img>
            'id' => $row['msg_id']
        ];
    } else {
        $icon = "<i class='bx bx-file'></i>";
        if (in_array($ext, ['pdf'])) $icon = "<i class='bx bx-file'></i>";
        if (in_array($ext, ['doc', 'docx'])) $icon = "<i class='bx bx-file-text'></i>";
        if (in_array($ext, ['zip', 'rar'])) $icon = "<i class='bx bx-archive'></i>";
        
        $file_groups[$month][] = [
            'name' => $filename,
            'size' => $filesize,
            'path' => $row['message'],
            'icon' => $icon,
            'ext' => strtoupper($ext)
        ];
    }
}

// 5. GENERATE HTML
$media_html = "";
$files_html = "";

// Build Media Grid
if (!empty($media_groups)) {
    foreach ($media_groups as $month => $items) {
        $media_html .= "<h4 class='media-month-header'>$month</h4>";
        $media_html .= "<div class='media-grid-container'>";
        foreach ($items as $item) {
            $media_html .= "<div class='media-item'><img src='{$item['src']}' loading='lazy' onclick='window.showImagePreview(this.src, this)' data-msg-id='{$item['id']}'></div>";
        }
        $media_html .= "</div>";
    }
} else {
    $media_html = "<div class='empty-state'>No photos found.</div>";
}

// Build Files List
if (!empty($file_groups)) {
    foreach ($file_groups as $month => $items) {
        $files_html .= "<h4 class='media-month-header'>$month</h4>";
        foreach ($items as $item) {
            $files_html .= "
            <a href='{$item['path']}' target='_blank' class='file-list-item'>
                <div class='file-icon-box'>{$item['icon']}</div>
                <div class='file-info'>
                    <div class='file-name'>{$item['name']}</div>
                    <div class='file-meta'>{$item['size']} • {$item['ext']}</div>
                </div>
            </a>";
        }
    }
} else {
    $files_html = "<div class='empty-state'>No files found.</div>";
}

echo json_encode(['media' => $media_html, 'files' => $files_html]);
?>