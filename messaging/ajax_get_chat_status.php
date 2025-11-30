<?php
// messaging/ajax_get_chat_status.php
include 'config.php';

if (!isset($_SESSION['user_id'])) exit;

$current_user_id = $_SESSION['user_id'];
$chat_id = $_POST['chat_id'] ?? 0;
$chat_type = $_POST['chat_type'] ?? 'user';

if ($chat_id == 0) exit;

$response = [
    'status' => 'offline', 
    'text' => 'Offline',
    'is_blocked' => false, 
    'blocked_by' => 'none' // 'me' or 'them'
];

if ($chat_type === 'user') {
    // 1. CHECK IF I BLOCKED THEM
    $check_me = $conn->prepare("SELECT id FROM blocked_users WHERE blocker_id = ? AND blocked_id = ?");
    $check_me->bind_param("ii", $current_user_id, $chat_id);
    $check_me->execute();
    if ($check_me->get_result()->num_rows > 0) {
        $response['is_blocked'] = true;
        $response['blocked_by'] = 'me';
        $response['text'] = ''; // Hide status
    }

    // 2. CHECK IF THEY BLOCKED ME (Only if not already blocked by me)
    if (!$response['is_blocked']) {
        $check_them = $conn->prepare("SELECT id FROM blocked_users WHERE blocker_id = ? AND blocked_id = ?");
        $check_them->bind_param("ii", $chat_id, $current_user_id);
        $check_them->execute();
        if ($check_them->get_result()->num_rows > 0) {
            $response['is_blocked'] = true;
            $response['blocked_by'] = 'them';
            $response['text'] = ''; // Hide status
        }
    }

    // 3. GET ONLINE STATUS (Kung walang blocking)
    if (!$response['is_blocked']) {
        $stmt = $conn->prepare("SELECT last_activity FROM users WHERE id = ?");
        $stmt->bind_param("i", $chat_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            if ($row['last_activity']) {
                $last = strtotime($row['last_activity']);
                if ((time() - $last) < 120) {
                    $response['status'] = 'online';
                    $response['text'] = 'Active now';
                }
            }
        }
    }

} elseif ($chat_type === 'group') {
    // Group Status Logic
    $sql = "SELECT COUNT(*) as active_count FROM group_members gm
            JOIN users u ON gm.user_id = u.id
            WHERE gm.group_id = ? AND u.id != ? 
            AND u.last_activity > (NOW() - INTERVAL 2 MINUTE)";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $chat_id, $current_user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    
    if ($row['active_count'] > 0) {
        $response['status'] = 'online';
        $response['text'] = $row['active_count'] . " active now";
    } else {
        $response['text'] = "No one active";
    }
}

echo json_encode($response);
?>