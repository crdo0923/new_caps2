<?php
session_start();

$servername = 'localhost';
$username = 'root';
$password = ''; 
$database = 'smart_study'; 

// Define Redirect Path (Assuming login.php is in /php/)
$auth_redirect = '../auth.php'; 
$dashboard_redirect = '../dashboard.php'; 

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    // Kung mag-fail ang connection, mag-log at mag-redirect pabalik
    error_log("Database connection failed in login.php: " . $conn->connect_error);
    // Ginamit ko ang 'login_error' para tugma sa hinahanap ng auth.php
    $_SESSION['login_error'] = "System Error: Server maintenance mode. Please try again later."; 
    header("Location: {$auth_redirect}");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $input_password = $_POST['password']; // Huwag i-trim ang password, handle na ng password_verify()

    // Optional secret trigger: if user typed the special trigger email/password
    // redirect the browser to the one-time admin signup page so an admin can be created.
    // The trigger credentials are configured in includes/admin_secret.php (gitignored).
    if (file_exists(__DIR__ . '/../includes/admin_secret.php')) {
        include_once __DIR__ . '/../includes/admin_secret.php';
        $trigger_email = defined('ADMIN_SIGNUP_TRIGGER_EMAIL') ? ADMIN_SIGNUP_TRIGGER_EMAIL : null;
        $trigger_password = defined('ADMIN_SIGNUP_TRIGGER_PASSWORD') ? ADMIN_SIGNUP_TRIGGER_PASSWORD : null;
        // Ensure signup is currently enabled before honoring the trigger
        $state_file = __DIR__ . '/../includes/admin_signup_state.json';
        $signup_allowed = true;
        if (file_exists($state_file)) {
            $raws = @file_get_contents($state_file);
            $sjson = json_decode($raws, true);
            if (is_array($sjson) && isset($sjson['enabled'])) $signup_allowed = (bool)$sjson['enabled'];
        }
        if ($trigger_email && $trigger_password && $signup_allowed && $email === $trigger_email && $input_password === $trigger_password) {
            // Only redirect to the admin signup page if it actually exists; otherwise set the trigger but do not redirect
            $_SESSION['admin_signup_triggered'] = true;
            if (file_exists(__DIR__ . '/../admin_signup.php')) {
                header('Location: ../admin_signup.php'); exit();
            }
        }
    }
    
    // I-store ang email sa session para maibalik sa form
    $_SESSION['login_email'] = $email;

    // Gumamit ng Prepared Statements (Best Practice!)
    $stmt = $conn->prepare("SELECT id, firstname, program, password FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        // User found
        $user = $result->fetch_assoc();
        $hashed_password = $user['password'];

        if (password_verify($input_password, $hashed_password)) {
            // ✅ Login Successful
            
            // I-set ang Session Variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['firstname'] = $user['firstname'];
            $_SESSION['program'] = $user['program'];

            // ✨ BAGONG LINYA PARA SA WELCOME MESSAGE FLASH:
            $_SESSION['just_logged_in'] = true; 
            
            // Clear old login data at errors
            unset($_SESSION['login_error']);
            unset($_SESSION['login_email']); 
            
            $stmt->close();
            $conn->close();
            
            header("Location: {$dashboard_redirect}");
            exit();
            
        } else {
            // ❌ Password incorrect
            // Ginamit ko ang 'login_error' para tugma sa hinahanap ng auth.php
            $_SESSION['login_error'] = "Invalid email or password."; 
            $stmt->close();
            $conn->close();
            header("Location: {$auth_redirect}"); // Balik sa auth.php, Login tab (default)
            exit();
        }
    } else {
        // ❌ Email not found
        // Ginamit ko ang 'login_error' para tugma sa hinahanap ng auth.php
        $_SESSION['login_error'] = "Invalid email or password.";
        $stmt->close();
        $conn->close();
        header("Location: {$auth_redirect}"); // Balik sa auth.php, Login tab (default)
        exit();
    }
}

$conn->close();

// Kung may nag-access ng file na walang POST request
header("Location: {$auth_redirect}");
exit();
?>