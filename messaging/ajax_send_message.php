<?php
// messaging/ajax_send_message.php
include 'config.php';

if (!isset($_SESSION['user_id'])) exit;

$sender_id = $_SESSION['user_id'];
$receiver_id = $_POST['receiver_id'] ?? 0;
$group_id = $_POST['group_id'] ?? 0;
$message = $_POST['message'] ?? '';
$reply_to_id = $_POST['reply_to_id'] ?? NULL;

if ($reply_to_id !== NULL && !is_numeric($reply_to_id)) {
    $reply_to_id = NULL;
}

if (!empty($message)) {
    if ($group_id > 0) {
        // GROUP MESSAGE

        // --- SECURITY CHECK: Is user still a member? ---
        // Ito ang pipigil sa mga na-kick na mag-send ng message
        $check_mem = $conn->prepare("SELECT id FROM group_members WHERE group_id = ? AND user_id = ?");
        $check_mem->bind_param("ii", $group_id, $sender_id);
        $check_mem->execute();
        $is_member = $check_mem->get_result()->num_rows > 0;
        
        if (!$is_member) {
            echo "error_kicked"; // Custom error message
            exit; 
        }
        // -----------------------------------------------

        $sql = "INSERT INTO messages (sender_id, group_id, message, reply_to_msg_id) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iisi", $sender_id, $group_id, $message, $reply_to_id);
        if ($stmt->execute()) echo "success"; else echo "error";
        
    } elseif ($receiver_id > 0) {
        // DIRECT MESSAGE

        // *** SECURITY CHECK: BLOCKING ***
        // Check kung may blocking relationship (kahit sino sa dalawa)
        $chkBlock = $conn->prepare("SELECT id FROM blocked_users WHERE (blocker_id = ? AND blocked_id = ?) OR (blocker_id = ? AND blocked_id = ?)");
        $chkBlock->bind_param("iiii", $sender_id, $receiver_id, $receiver_id, $sender_id);
        $chkBlock->execute();
        if ($chkBlock->get_result()->num_rows > 0) {
            echo "error_blocked"; // Stop sending
            exit;
        }

        // 1. Update Sender Settings (Auto-Accept if replying)
        $updSelf = $conn->prepare("UPDATE chat_settings SET is_request = 0 WHERE user_id = ? AND partner_id = ?");
        $updSelf->bind_param("ii", $sender_id, $receiver_id);
        $updSelf->execute();

        // 2. Check Receiver Settings
        $check = $conn->prepare("SELECT id FROM chat_settings WHERE user_id = ? AND partner_id = ?");
        $check->bind_param("ii", $receiver_id, $sender_id);
        $check->execute();
        
        if ($check->get_result()->num_rows == 0) {
            // Create connection as Request
            $ins = $conn->prepare("INSERT INTO chat_settings (user_id, partner_id, is_request, is_archived) VALUES (?, ?, 1, 0)");
            $ins->bind_param("ii", $receiver_id, $sender_id);
            $ins->execute();
        } else {
            // Unarchive if needed
            $upd = $conn->prepare("UPDATE chat_settings SET is_archived = 0 WHERE user_id = ? AND partner_id = ?");
            $upd->bind_param("ii", $receiver_id, $sender_id);
            $upd->execute();
        }

        // 3. Check Sender Settings (Create if not exists)
        $checkSelf = $conn->prepare("SELECT id FROM chat_settings WHERE user_id = ? AND partner_id = ?");
        $checkSelf->bind_param("ii", $sender_id, $receiver_id);
        $checkSelf->execute();
        if ($checkSelf->get_result()->num_rows == 0) {
            $insSelf = $conn->prepare("INSERT INTO chat_settings (user_id, partner_id, is_request, is_archived) VALUES (?, ?, 0, 0)");
            $insSelf->bind_param("ii", $sender_id, $receiver_id);
            $insSelf->execute();
        }

        // 4. Insert Message
        $sql = "INSERT INTO messages (sender_id, receiver_id, message, reply_to_msg_id) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iisi", $sender_id, $receiver_id, $message, $reply_to_id);
        if ($stmt->execute()) echo "success"; else echo "error";
    }
}
?>