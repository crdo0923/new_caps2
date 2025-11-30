<?php
// messaging/ajax_group_actions.php
include 'config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) { echo json_encode(['status'=>'error']); exit; }

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

if ($action === 'initiate_delete') {
    $group_id = $_POST['group_id'];
    $delay = $_POST['delay'];

    // 1. Check if request exists
    $check = $conn->query("SELECT request_id FROM group_deletion_requests WHERE group_id = $group_id AND status = 'pending'");
    if ($check->num_rows > 0) {
        echo json_encode(['status'=>'error', 'message'=>'A vote is already in progress.']);
        exit;
    }

    // 2. Create Request
    $stmt = $conn->prepare("INSERT INTO group_deletion_requests (group_id, initiator_id, delete_delay) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $group_id, $user_id, $delay);
    if ($stmt->execute()) {
        $req_id = $stmt->insert_id;
        // Auto-vote YES for initiator
        $conn->query("INSERT INTO group_deletion_votes (request_id, user_id, vote) VALUES ($req_id, $user_id, 1)");
        echo json_encode(['status'=>'success']);
    }
}

if ($action === 'vote') {
    $request_id = $_POST['request_id'];
    $vote = $_POST['vote']; // 1 or 0

    // 1. Insert/Update Vote
    $stmt = $conn->prepare("INSERT INTO group_deletion_votes (request_id, user_id, vote) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE vote = ?");
    $stmt->bind_param("iiii", $request_id, $user_id, $vote, $vote);
    $stmt->execute();

    // 2. Check Status (Simple Majority)
    // Get Group ID from Request
    $g_res = $conn->query("SELECT group_id, delete_delay FROM group_deletion_requests WHERE request_id = $request_id");
    $g_row = $g_res->fetch_assoc();
    $group_id = $g_row['group_id'];

    // Count Members
    $m_res = $conn->query("SELECT COUNT(*) as total FROM group_members WHERE group_id = $group_id");
    $total_members = $m_res->fetch_assoc()['total'];

    // Count YES Votes
    $v_res = $conn->query("SELECT COUNT(*) as yes_votes FROM group_deletion_votes WHERE request_id = $request_id AND vote = 1");
    $yes_votes = $v_res->fetch_assoc()['yes_votes'];

    $status = "Voting in progress ($yes_votes / $total_members)";
    
    // Logic: Majority > 50%
    if ($yes_votes > ($total_members / 2)) {
        // DELETE GROUP
        $conn->query("DELETE FROM `groups` WHERE group_id = $group_id");
        $conn->query("DELETE FROM `messages` WHERE group_id = $group_id"); // Clean up
        echo json_encode(['status'=>'deleted']);
    } else {
        echo json_encode(['status'=>'voted', 'msg'=>$status]);
    }
}

if ($action === 'delete_conversation') {
    // Soft Delete for 1-on-1
    $partner_id = $_POST['partner_id'];
    // Mark all active messages as deleted for current user
    // Logic: We set 'deleted_by_sender' if I sent it, 'deleted_by_receiver' if I received it
    $conn->query("UPDATE messages SET deleted_by_sender = 1 WHERE sender_id = $user_id AND receiver_id = $partner_id");
    $conn->query("UPDATE messages SET deleted_by_receiver = 1 WHERE receiver_id = $user_id AND sender_id = $partner_id");
    echo json_encode(['status'=>'success']);
}
?>