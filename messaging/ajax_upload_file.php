<?php
// messaging/ajax_upload_file.php
include 'config.php';

// 1. Security Check
if (!isset($_SESSION['user_id'])) { echo "auth_error"; exit; }

$sender_id = $_SESSION['user_id'];

// 2. Handle IDs: Convert 'null', '0', or empty to PHP NULL
// Ito ang fix para hindi mag-error ang Foreign Key constraint ng database
$receiver_id = (isset($_POST['receiver_id']) && $_POST['receiver_id'] !== 'null' && $_POST['receiver_id'] != 0) ? $_POST['receiver_id'] : NULL;
$group_id = (isset($_POST['group_id']) && $_POST['group_id'] !== 'null' && $_POST['group_id'] != 0) ? $_POST['group_id'] : NULL;

if (isset($_FILES['file'])) {
    $file = $_FILES['file'];
    $fileName = $file['name'];
    $fileTmpName = $file['tmp_name'];
    $fileSize = $file['size'];
    $fileError = $file['error'];
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    // 3. Allowed File Types (Security)
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'docx', 'doc', 'zip', 'rar', 'txt'];

    if (in_array($fileExt, $allowed)) {
        if ($fileError === 0) {
            if ($fileSize < 20000000) { // 20MB Limit
                
                // Create unique filename
                $fileNameNew = uniqid('', true) . "." . $fileExt;
                
                // Define paths
                $uploadDir = '../uploads/'; // Actual folder path
                $dbPath = 'uploads/' . $fileNameNew; // Path saved in DB

                // 4. Create directory if it doesn't exist
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $uploadPath = $uploadDir . $fileNameNew;

                if (move_uploaded_file($fileTmpName, $uploadPath)) {
                    
                    // 5. Unified INSERT Query (Handles NULLs correctly)
                    $sql = "INSERT INTO messages (sender_id, receiver_id, group_id, message) VALUES (?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    
                    // "iiis" = integer, integer, integer, string
                    $stmt->bind_param("iiis", $sender_id, $receiver_id, $group_id, $dbPath);

                    if ($stmt->execute()) {
                        echo "success";
                    } else {
                        echo "db_error"; // Pwedeng i-log ang $stmt->error para sa debugging
                    }
                } else {
                    echo "move_error";
                }
            } else {
                echo "size_error";
            }
        } else {
            echo "upload_error_code_" . $fileError;
        }
    } else {
        echo "invalid_type";
    }
} else {
    echo "no_file";
}
?>