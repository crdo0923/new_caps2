<?php
// messaging/ajax_fetch_messages.php
include 'config.php';

// 1. Suppress Errors (Para hindi masira ang HTML response)
error_reporting(0);
ini_set('display_errors', 0);

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) exit;

$current_user_id = $_SESSION['user_id']; 
$chat_id = isset($_POST['chat_id']) ? (int)$_POST['chat_id'] : 0; 
$chat_type = $_POST['chat_type'] ?? 'user';

if ($chat_id == 0) exit;

// ==========================================
// 1. FETCH MESSAGES
// ==========================================
if ($chat_type === 'group') {
    $sql = "SELECT m.msg_id, m.sender_id, m.message, m.timestamp, m.deleted_by_sender, m.deleted_by_receiver, m.is_read, u.firstname, u.lastname, u.profile_photo, r.message AS reply_text, ru.firstname AS reply_user FROM messages m LEFT JOIN users u ON m.sender_id = u.id LEFT JOIN messages r ON m.reply_to_msg_id = r.msg_id LEFT JOIN users ru ON r.sender_id = ru.id WHERE m.group_id = ? ORDER BY m.timestamp ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $chat_id);
} else {
    // DIRECT CHAT (FIXED JOIN)
    $sql = "SELECT m.msg_id, m.sender_id, m.message, m.timestamp, m.deleted_by_sender, m.deleted_by_receiver, m.is_read, u.firstname, u.lastname, u.profile_photo, r.message AS reply_text, ru.firstname AS reply_user FROM messages m LEFT JOIN users u ON m.sender_id = u.id LEFT JOIN messages r ON m.reply_to_msg_id = r.msg_id LEFT JOIN users ru ON r.sender_id = ru.id WHERE ( (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?) ) AND (m.group_id IS NULL OR m.group_id = 0) ORDER BY m.timestamp ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiii", $current_user_id, $chat_id, $chat_id, $current_user_id);
}

if (!$stmt->execute()) exit;
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) { $messages[] = $row; }
$stmt->close(); 

// ==========================================
// 2. FETCH REACTIONS (Existing Logic)
// ==========================================
$reactions_data = [];
if ($chat_type === 'group') {
    $react_sql = "SELECT mr.msg_id, mr.reaction_emoji, mr.user_id FROM message_reactions mr JOIN messages m ON mr.msg_id = m.msg_id WHERE m.group_id = ?";
    $stmt_r = $conn->prepare($react_sql);
    if($stmt_r) $stmt_r->bind_param("i", $chat_id);
} else {
    $react_sql = "SELECT mr.msg_id, mr.reaction_emoji, mr.user_id FROM message_reactions mr JOIN messages m ON mr.msg_id = m.msg_id WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?)";
    $stmt_r = $conn->prepare($react_sql);
    if($stmt_r) $stmt_r->bind_param("iiii", $current_user_id, $chat_id, $chat_id, $current_user_id);
}

if (isset($stmt_r) && $stmt_r && $stmt_r->execute()) {
    $res_r = $stmt_r->get_result();
    while ($row = $res_r->fetch_assoc()) { $reactions_data[$row['msg_id']][] = $row; }
    $stmt_r->close();
}

// ==========================================
// 3. RENDER MESSAGES
// ==========================================
$output = "";
$unread_flag_shown = false; 

if (!empty($messages)) {
    foreach ($messages as $row) {
        // Deletion Checks
        if ($chat_type === 'user') {
             if (($row['sender_id'] == $current_user_id && $row['deleted_by_sender']) || 
                ($row['sender_id'] != $current_user_id && $row['deleted_by_receiver'])) {
                continue;
            }
        }

        $msg_id = $row['msg_id'];
        $is_outgoing = ($row['sender_id'] == $current_user_id);
        $type = $is_outgoing ? 'outgoing' : 'incoming';
        $time = date('h:i A', strtotime($row['timestamp']));
        $message = htmlspecialchars($row['message']);

        // Unread Divider
        if ($row['sender_id'] != $current_user_id && $row['is_read'] == 0 && !$unread_flag_shown) {
            $output .= "<div class='unread-divider'><span>Unread Messages</span></div>";
            $unread_flag_shown = true;
        }

        // Check Special Message Types
        if ($message === '[[MESSAGE_UNSENT]]') {
            $output .= "<div class='message-wrapper $type'><div class='message-bubble $type unsent-bubble'><p><i class='bx bx-block'></i> Unsent</p></div></div>";
            continue;
        } elseif (strpos($message, '[[CALL_LOG:') === 0) {
            preg_match('/\[\[CALL_LOG:(\d+):(\d+)\]\]/', $message, $matches);
            
            if (count($matches) === 3) {
                $duration = (int)$matches[1];
                $log_caller_id = (int)$matches[2];
                
                $is_caller = ($log_caller_id == $current_user_id);
                $alignment_class = $is_caller ? 'outgoing' : 'incoming';
                
                $mins = floor($duration / 60);
                $secs = $duration % 60;
                $duration_formatted = $mins . 'm ' . ($secs > 0 ? ' ' . $secs . 's' : '');
                
                $icon = '<i class="bx bx-phone"></i>'; 
                $main_title = "Completed call"; 

                // --- FINAL COMPACT UI (Visible Time Stamp) ---
                $log_output = "
                <div class='message-wrapper $alignment_class'>
                    <div class='message-bubble call-log-card' style='
                        background: #6366f1; 
                        padding: 8px 10px; 
                        max-width: 180px; 
                        display: flex; 
                        flex-direction: column; 
                        gap: 4px; 
                    '>
                        
                        <div class='call-log-header' style='display: flex; align-items: center; gap: 8px; margin-bottom: 3px;'>
                            <span style='font-size: 1.2rem; color: #d1d5db; line-height: 1;'>$icon</span>
                            <div class='log-text-content' style='line-height: 1.2;'>
                                <p style='font-weight: 600; margin: 0; font-size: 0.9rem; color:white;'>$main_title</p>
                            </div>
                        </div>
                        
                        <div style='display: flex; justify-content: space-between; align-items: baseline; width: 100%;'>
                            <p style='font-size: 0.75rem; color: #e5e7eb; margin: 0; font-weight: 500;'>Duration: $duration_formatted</p>
                            
                            <span class='message-time' 
                                  style='
                                      font-size: 0.6rem; /* Smallest text */
                                      color: rgba(255, 255, 255, 0.5); 
                                      white-space: nowrap;
                                  '>
                                $time
                            </span>
                        </div>
                    </div>
                </div>";
                $output .= $log_output;
                continue; 
            }
        }
        
        // 4. STANDARD MESSAGE (Existing logic)
        if (true) { 
            $reply_html = '';
            if (!empty($row['reply_text'])) {
                $reply_sender = ($row['reply_user'] == ($_SESSION['firstname'] ?? '')) ? 'You' : ($row['reply_user'] ?? 'User');
                $reply_content = htmlspecialchars($row['reply_text']);
                if (strpos($reply_content, 'uploads/') === 0) $reply_content = "<i class='bx bx-camera'></i> [Photo]";
                $reply_html = "<div class='inline-reply-preview'><strong style='color:var(--primary-color)'>{$reply_sender}</strong><div style='color:var(--text-gray)'>{$reply_content}</div></div>";
            }

            $msg_content = '';
            if (strpos($message, 'uploads/') === 0) {
                $filepath = "../" . $message;
                $filename = basename($message);
                $fileExt = strtolower(pathinfo($message, PATHINFO_EXTENSION));

                if (in_array($fileExt, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                    $msg_content = "<img src='$message' class='chat-image' data-msg-id='$msg_id' data-sender-id='{$row['sender_id']}' loading='lazy' onclick='window.showImagePreview(this.src, this)'>";
                } else {
                    $msg_content = "<a href='$message' target='_blank' class='chat-file-link'><i class='bx bx-file' style='margin-right:6px;'></i> $filename</a>";
                }
            } else {
                $msg_content = "<p>$message</p>";
            }

            $reaction_html = '';
            if (isset($reactions_data[$msg_id])) {
                $reaction_html = "<div class='message-reaction'>";
                $counts = []; 
                foreach ($reactions_data[$msg_id] as $r) {
                    $emo = $r['reaction_emoji'];
                    if(!isset($counts[$emo])) $counts[$emo] = 0;
                    $counts[$emo]++;
                }
                foreach ($counts as $emo => $count) {
                    $reaction_html .= "<span class='message-reaction-item' data-emoji='$emo'>$emo $count</span>";
                }
                $reaction_html .= "</div>";
            }

            $sender_html = '';
            if ($chat_type === 'group' && !$is_outgoing) {
                $sname = htmlspecialchars(($row['firstname'] ?? 'Unknown') . ' ' . ($row['lastname'] ?? ''));
                $sender_html = "<span class='group-sender-name' style='font-size:0.7rem; color:var(--text-gray); margin-left:10px; display:block;'>$sname</span>";
            }

            $output .= "
            <div class='message-wrapper $type'> 
                $sender_html
                <div class='message-bubble $type' data-msg-id='$msg_id'>
                    <div class='bubble-content-wrapper'>
                        $reply_html
                        $msg_content
                        <span class='message-time'>$time</span>
                    </div>
                    $reaction_html
                </div>
                <div class='message-actions'>
                     <button class='btn-react-emoji' data-msg-id='$msg_id'><i class='bx bx-smile'></i></button>
                     <button class='btn-reply' data-msg-id='$msg_id'><i class='bx bx-reply'></i></button>
                     <button class='btn-bubble-menu' data-msg-id='$msg_id'>...</button>
                </div>
            </div>";
        }
    }
} else {
    $output = "<div class='chat-empty-state'>No messages yet. Start the conversation!</div>";
}

// 4. POLL LOGIC (Existing logic)
$poll_html = "";
if ($chat_type === 'group') {
    $poll_sql = "SELECT * FROM group_deletion_requests WHERE group_id = $chat_id AND status = 'pending' LIMIT 1";
    $poll_res = $conn->query($poll_sql);
    if ($poll_res && $poll_res->num_rows > 0) {
        $poll = $poll_res->fetch_assoc();
        $req_id = $poll['request_id'];
        
        $yes = 0;
        $v = $conn->query("SELECT COUNT(*) as c FROM group_deletion_votes WHERE request_id = $req_id AND vote = 1");
        if($v) $yes = $v->fetch_assoc()['c'];
        
        $total = 1;
        $m = $conn->query("SELECT COUNT(*) as c FROM group_members WHERE group_id = $chat_id");
        if($m) $total = $m->fetch_assoc()['c'];
        
        $my = $conn->query("SELECT vote FROM group_deletion_votes WHERE request_id = $req_id AND user_id = $current_user_id");
        $voted = ($my && $my->num_rows > 0);
        
        $disabled = $voted ? 'disabled' : '';
        $needed = intval($total / 2) + 1;

        $poll_html = "
        <div class='vote-poll-bubble' style='margin:10px auto; max-width:80%; background:var(--bg-input); border:1px solid var(--primary-color); border-radius:8px; padding:15px; text-align:center;'>
            <span style='display:block; font-weight:bold; color:var(--primary-color); margin-bottom:5px;'><i class='bx bx-error' style='margin-right:6px;'></i>Deletion Vote</span>
            <span style='display:block; font-size:0.9rem; color:var(--text-light);'>Votes: $yes / $needed</span>
            <div style='margin-top:10px;'>
                <button onclick='initiateVote($req_id)' style='background:#ef4444; color:white; border:none; padding:5px 15px; border-radius:4px; cursor:pointer;' $disabled>Vote Yes</button>
            </div>
        </div>";
    }
}

echo $output . $poll_html;
?>