<?php
session_start();

// --- 1. Database Connection Configuration ---
$servername = 'localhost';
$username = 'root'; 
$password = ''; 
$database = 'smart_study'; 

$conn = new mysqli($servername, $username, $password, $database); 

// Define Redirect Path
$base_redirect_with_form = '/new_caps/auth.php?form=register'; // Para bumalik sa Register tab
$base_redirect_login = '/new_caps/auth.php'; // Para sa success redirect (balik sa Login tab)

if ($conn->connect_error) {
    // Handling ng connection error
    $_SESSION['register_error'] = "System Error: Failed to connect to the database.";
    header("Location: {$base_redirect_with_form}"); 
    exit();
}

// --- 2. Process POST Request ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Kunin at i-sanitize ang data
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $email = trim($_POST['email']);
    $student_id = trim($_POST['student_id']);
    $program = trim($_POST['program']);
    
    // Passwords
    $password = $_POST['password']; 
    $confirm_password = $_POST['confirm_password']; 

    // I-save ang input data (maliban sa password) para maibalik sa form
    // Gagamitin ito ng auth.php para hindi mawala ang data
    $_SESSION['form_data'] = [
        'firstname' => $firstname,
        'lastname' => $lastname,
        'email' => $email,
        'student_id' => $student_id,
        'program' => $program,
    ];

    $errors = []; // Array para sa field-specific errors

    // --- 3. Input Validation (Checks are now aggregated in $errors array) ---
    
    // Password Mismatch
    if ($password !== $confirm_password) {
        $errors['confirm_password'] = "Password mismatch. The passwords do not match.";
    }
    
    // Password Length Check
    if (strlen($password) < 8) {
        $errors['password'] = "Password must be at least 8 characters long.";
    }


    // 4. Check kung may existing user na sa email/student_id
    $check_stmt = $conn->prepare("SELECT email, student_id FROM users WHERE email = ? OR student_id = ?"); 
    $check_stmt->bind_param("ss", $email, $student_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        $row = $check_result->fetch_assoc();
        if ($row['email'] === $email) {
            $errors['email'] = "This email is already registered.";
        }
        if ($row['student_id'] === $student_id) {
            $errors['student_id'] = "This Student ID is already registered.";
        }
    }
    $check_stmt->close();
    
    // --- 4. Handle Errors (If any field-specific errors exist) ---
    if (!empty($errors)) {
        // I-save ang mga field-specific errors sa session
        $_SESSION['validation_errors'] = $errors;
        
        // I-redirect pabalik sa register form (dala ang form_data at validation_errors)
        $conn->close();
        header("Location: {$base_redirect_with_form}");
        exit();
    }
    
    // --- 5. Insert new user using Prepared Statement ---
    
    // Hashing the password (Security Feature!)
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $insert_stmt = $conn->prepare("INSERT INTO users (firstname, lastname, email, student_id, program, password) VALUES (?, ?, ?, ?, ?, ?)");
    $insert_stmt->bind_param("ssssss", $firstname, $lastname, $email, $student_id, $program, $hashed_password);

    if ($insert_stmt->execute()) {
        // Successful Registration 
        $_SESSION['register_success'] = "Success! Account created for {$firstname}. Please log in now.";

        // Clear session data pagkatapos maging success
        unset($_SESSION['form_data']);
        
        // Redirect sa /new_caps/auth.php (Login tab)
        $insert_stmt->close();
        $conn->close(); 
        header("Location: {$base_redirect_login}"); 
        exit();
    } else {
        // Failed Insertion - General Database Error
        $error_message = $conn->error;
        $_SESSION['register_error'] = 'Database Error: Registration failed. Please try again later. (' . $error_message . ')';
        
        $insert_stmt->close();
        $conn->close(); 
        header("Location: {$base_redirect_with_form}"); // Redirect pabalik sa Register form
        exit();
    }
}

// --- 6. Final Close ---
if (isset($conn)) {
    $conn->close();
}
// Redirect non-POST requests back to auth page
header("Location: {$base_redirect_login}");
exit();
?>