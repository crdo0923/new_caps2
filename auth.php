<?php
session_start();

// --- 1. Database Connection and Metric Fetching ---

// Connection Parameters
$servername = 'localhost';
$username = 'root'; 
$password = ''; 
$dbname = 'smart_study'; 

// Initialize metrics with fallback/default values
$active_students = "500+"; 
$total_sessions = "10k+";
$success_rate = "95%";
$connection_error = false; 

// Establish connection (wrapped in try/catch to avoid uncaught exceptions when MySQL is down)
try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new mysqli_sql_exception($conn->connect_error);
    }
} catch (mysqli_sql_exception $e) {
    // Log the technical error for the server logs
    error_log("Database Connection Failed in auth.php: " . $e->getMessage());
    // Friendly UI flow: keep $connection_error true so page still loads with placeholder stats
    $connection_error = true;
    $conn = null;
}

if (!$connection_error && $conn) {
    // 1. Fetch Total Students
    $sql_students = "SELECT COUNT(*) AS total_students FROM users";
    $result_students = $conn->query($sql_students);
    if ($result_students && $result_students->num_rows > 0) {
        $row = $result_students->fetch_assoc();
        $active_students = number_format($row["total_students"]) . ""; 
    }

    // 2. Fetch Total Sessions
    $sql_sessions = "SELECT COUNT(*) AS total_sessions FROM study_sessions";
    $result_sessions = $conn->query($sql_sessions);
    if ($result_sessions && $result_sessions->num_rows > 0) {
        $row = $result_sessions->fetch_assoc();
        $total_sessions = number_format($row["total_sessions"]) . "";
    }

    // 3. Fetch Success Rate
    $sql_rate = "SELECT success_rate FROM analytics ORDER BY recorded_at DESC LIMIT 1";
    $result_rate = $conn->query($sql_rate);
    if ($result_rate && $result_rate->num_rows > 0) {
        $row = $result_rate->fetch_assoc();
        $success_rate = round($row["success_rate"]) . "%"; 
    }
    
    $conn->close();
}

// --- PHP for handling old input and errors ---
$form_data = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']); 

$validation_errors = $_SESSION['validation_errors'] ?? [];
unset($_SESSION['validation_errors']); 

$login_email = $_SESSION['login_email'] ?? '';
unset($_SESSION['login_email']); 

$has_login_error = isset($_SESSION['login_error']); 

function old_value($field) {
    global $form_data;
    echo htmlspecialchars($form_data[$field] ?? '');
}

function field_error($field) {
    global $validation_errors;
    if (isset($validation_errors[$field])) {
        return '<span class="error-message"><i class="bx bx-error-circle"></i> ' . htmlspecialchars($validation_errors[$field]) . '</span>';
    }
    return '';
}

function is_selected($field, $value) {
    global $form_data;
    if (isset($form_data[$field]) && $form_data[$field] === $value) {
        return 'selected';
    }
    return '';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login / Register - SmartStudy</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    
    <link rel="stylesheet" href="css/loading.css">
    <link rel="stylesheet" href="css/index.css">
    <style>
        /* =========================================
           DARK THEME AUTH STYLES
           ========================================= */
        :root {
            --primary: #6366f1;
            --primary-hover: #4f46e5;
            --bg-dark: #0f172a;
            --bg-card: #1e293b;
            --text-light: #f8fafc;
            --text-gray: #94a3b8;
            --border: #334155;
            --error: #ef4444;
            --success: #10b981;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-dark);
            color: var(--text-light);
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow-x: hidden;
        }

        .back-home {
            position: absolute;
            top: 2rem;
            left: 2rem;
            color: var(--text-gray);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
            transition: color 0.3s;
            z-index: 10;
        }
        .back-home:hover { color: var(--primary); }

        .auth-container {
            display: flex;
            width: 100%;
            max-width: 1100px;
            background: var(--bg-card);
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            border: 1px solid var(--border);
            margin: 2rem;
            min-height: 700px;
        }

        /* LEFT SIDE: INFO & STATS */
        .auth-info {
            flex: 1;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(15, 23, 42, 0.8)), url('images/auth-bg-pattern.png'); /* Optional pattern */
            padding: 4rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            border-right: 1px solid var(--border);
        }

        .logo-section { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 2rem; }
        .logo-section i { font-size: 2.5rem; color: var(--primary); }
        .logo-section h1 { font-size: 2rem; font-weight: 800; color: white; margin: 0; }

        .auth-info h2 { font-size: 2rem; font-weight: 700; margin-bottom: 1rem; line-height: 1.2; }
        .auth-info p { color: var(--text-gray); font-size: 1.1rem; margin-bottom: 3rem; line-height: 1.6; }

        .features-list { display: flex; flex-direction: column; gap: 1.5rem; margin-bottom: 4rem; }
        .feature-item { display: flex; align-items: flex-start; gap: 1rem; }
        .feature-item .icon { 
            background: rgba(99, 102, 241, 0.1); color: var(--primary); 
            width: 40px; height: 40px; border-radius: 10px; 
            display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;
        }
        .feature-item h4 { margin: 0 0 0.25rem 0; font-weight: 600; color: var(--text-light); }
        .feature-item p { margin: 0; font-size: 0.9rem; color: var(--text-gray); }

        .stats-mini { display: flex; gap: 2rem; padding-top: 2rem; border-top: 1px solid var(--border); }
        .stat-item h3 { font-size: 1.5rem; font-weight: 800; color: white; margin: 0; }
        .stat-item p { margin: 0; font-size: 0.85rem; color: var(--text-gray); text-transform: uppercase; letter-spacing: 0.5px; }

        /* RIGHT SIDE: FORMS */
        .auth-forms {
            flex: 1;
            padding: 4rem;
            background: var(--bg-card);
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .forms-wrapper { max-width: 400px; margin: 0 auto; width: 100%; }

        .form-toggle {
            display: flex;
            background: #0f172a;
            padding: 4px;
            border-radius: 12px;
            margin-bottom: 2rem;
            border: 1px solid var(--border);
        }
        .toggle-btn {
            flex: 1;
            padding: 0.75rem;
            border: none;
            background: transparent;
            color: var(--text-gray);
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            transition: 0.3s;
        }
        .toggle-btn.active { background: var(--primary); color: white; box-shadow: 0 4px 6px -1px rgba(99, 102, 241, 0.4); }

        .form-header { text-align: center; margin-bottom: 2rem; }
        .form-header h2 { font-size: 1.75rem; margin-bottom: 0.5rem; }
        .form-header p { color: var(--text-gray); }

        /* INPUT FIELDS */
        .form-group { margin-bottom: 1.25rem; position: relative; }
        .form-group label { display: block; font-size: 0.9rem; font-weight: 500; margin-bottom: 0.5rem; color: var(--text-light); }
        
        .input-wrapper { position: relative; }
        .input-wrapper input, .input-wrapper select {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.75rem;
            background: #0f172a;
            border: 1px solid var(--border);
            border-radius: 10px;
            color: white;
            font-size: 0.95rem;
            transition: 0.3s;
            box-sizing: border-box; /* Important styling fix */
        }
        .input-wrapper input:focus, .input-wrapper select:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }
        
        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-gray);
            font-size: 1.2rem;
            pointer-events: none;
        }

        .toggle-password {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-gray);
            cursor: pointer;
            font-size: 1.2rem;
        }
        .toggle-password:hover { color: var(--text-light); }

        .btn-submit {
            width: 100%;
            padding: 0.875rem;
            background: linear-gradient(135deg, var(--primary), var(--primary-hover));
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1.5rem;
        }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 10px 20px -5px rgba(99, 102, 241, 0.4); }

        .form-options { display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem; margin-top: 1rem; }
        .checkbox-label { display: flex; align-items: center; gap: 0.5rem; color: var(--text-gray); cursor: pointer; }
        .forgot-link, .checkbox-label a { color: var(--primary); text-decoration: none; font-weight: 500; }
        
        /* ALERTS */
        .alert-box { padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.9rem; text-align: center; }
        .alert-danger { background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); }
        .alert-success { background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2); }
        .error-message { color: var(--error); font-size: 0.8rem; margin-top: 0.4rem; display: flex; align-items: center; gap: 0.25rem; }

        /* Logic Styles */
        .form-container { display: none; animation: fadeIn 0.3s ease-out; }
        .form-container.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        /* Responsive */
        @media (max-width: 900px) {
            .auth-container { flex-direction: column; max-width: 500px; margin: 6rem 1rem 2rem 1rem; }
            .auth-info { display: none; } /* Hide info on small screens for cleaner look */
            .auth-forms { padding: 2.5rem; }
        }
    </style>
</head>
<body>

    <?php include 'includes/mobile_blocker.php'; ?>

    <div class="page-loader">
    <div class="loader-container">
        <div class="loader-icon"><i class='bx bxl-brain' style="font-size: 4rem; color: #6366f1;"></i></div>
        <div class="loader-text" style="color: #cbd5e1; font-family: 'Inter', sans-serif; margin-bottom: 10px;"></div>
        <div class="loader-spinner"><div class="spinner-ring"></div></div>
    </div>
</div>

    <a href="index.php" class="back-home"><i class='bx bx-left-arrow-alt'></i> Back to Home</a>

    <div class="auth-container">
        <div class="auth-info">
            <div class="info-content">
                <div class="logo-section"><i class='bx bxl-c-plus-plus'></i><h1>SmartStudy</h1></div>
                <h2>Welcome to Your Study Revolution</h2>
                <p>Transform your academic journey with AI-powered study planning and focus tools.</p>
                
                <div class="features-list">
                    <div class="feature-item"><div class="icon"><i class='bx bx-bot'></i></div><div><h4>AI-Powered Scheduling</h4><p>Smart algorithms optimize your time</p></div></div>
                    <div class="feature-item"><div class="icon"><i class='bx bx-target-lock'></i></div><div><h4>Focus & Productivity</h4><p>Block distractions effectively</p></div></div>
                    <div class="feature-item"><div class="icon"><i class='bx bx-bar-chart-alt-2'></i></div><div><h4>Track Progress</h4><p>Detailed analytics and insights</p></div></div>
                </div>

                <?php if ($connection_error): ?>
                    <div style="background: rgba(239,68,68,0.06); color: #f8e7e7; border: 1px solid rgba(239,68,68,0.12); padding: 8px 12px; border-radius:8px; margin-bottom:12px; font-size:0.9rem;">
                        ⚠️ Live metrics unavailable — database connection failed. Please start MySQL/XAMPP and refresh the page.
                    </div>
                <?php endif; ?>

                <div class="stats-mini">
                    <div class="stat-item"><h3><?= htmlspecialchars($active_students); ?></h3><p>Students</p></div>
                    <div class="stat-item"><h3><?= htmlspecialchars($total_sessions); ?></h3><p>Sessions</p></div>
                    <div class="stat-item"><h3><?= htmlspecialchars($success_rate); ?></h3><p>Success</p></div>
                </div>
            </div>
        </div>

        <div class="auth-forms">
            <div class="forms-wrapper">
                <div class="form-toggle">
                    <button class="toggle-btn active" data-form="login">Login</button>
                    <button class="toggle-btn" data-form="register">Sign Up</button>
                </div>

                <div class="form-container active" id="login-form">
                    <div class="form-header"><h2>Welcome Back!</h2><p>Login to continue your progress</p></div>

                    <?php 
                        if ($has_login_error) echo '<div class="alert-box alert-danger"><i class="bx bx-error"></i> ' . htmlspecialchars($_SESSION['login_error']) . '</div>';
                        if (isset($_SESSION['register_success'])) { echo '<div class="alert-box alert-success"><i class="bx bx-check-circle"></i> ' . htmlspecialchars($_SESSION['register_success']) . '</div>'; unset($_SESSION['register_success']); }
                    ?>

                    <form action="php/login.php" method="POST">
                        <div class="form-group">
                            <label>Email Address</label>
                            <div class="input-wrapper">
                                <i class='bx bx-envelope input-icon'></i>
                                <input type="email" name="email" placeholder="your.email@plmun.edu.ph" required value="<?= htmlspecialchars($login_email); ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Password</label>
                            <div class="input-wrapper">
                                <i class='bx bx-lock-alt input-icon'></i>
                                <input type="password" id="login-pass" name="password" placeholder="Enter password" required>
                                <button type="button" class="toggle-password" onclick="togglePassword('login-pass')"><i class='bx bx-show'></i></button>
                            </div>
                        </div>

                        <div class="form-options">
                            <label class="checkbox-label"><input type="checkbox" name="remember"> Remember me</label>
                            <a href="#" class="forgot-link">Forgot Password?</a>
                        </div>

                        <button type="submit" class="btn-submit">Login <i class='bx bx-right-arrow-alt'></i></button>
                    </form>
                    <?php if ($has_login_error) unset($_SESSION['login_error']); ?>
                </div>

                <div class="form-container" id="register-form">
                    <div class="form-header"><h2>Create Account</h2><p>Join thousands of students</p></div>
                    
                    <?php if (isset($_SESSION['register_error'])) { echo '<div class="alert-box alert-danger">' . htmlspecialchars($_SESSION['register_error']) . '</div>'; unset($_SESSION['register_error']); } ?>

                    <form action="php/register.php" method="POST">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label>First Name</label>
                                <div class="input-wrapper"><i class='bx bx-user input-icon'></i><input type="text" name="firstname" placeholder="Juan" required value="<?php old_value('firstname'); ?>"></div>
                            </div>
                            <div class="form-group">
                                <label>Last Name</label>
                                <div class="input-wrapper"><i class='bx bx-user input-icon'></i><input type="text" name="lastname" placeholder="Dela Cruz" required value="<?php old_value('lastname'); ?>"></div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Email Address</label>
                            <div class="input-wrapper"><i class='bx bx-envelope input-icon'></i><input type="email" name="email" placeholder="email@plmun.edu.ph" required value="<?php old_value('email'); ?>"></div>
                            <?php echo field_error('email'); ?>
                        </div>

                        <div class="form-group">
                            <label>Student ID</label>
                            <div class="input-wrapper"><i class='bx bx-id-card input-icon'></i><input type="text" name="student_id" placeholder="2021-12345" required value="<?php old_value('student_id'); ?>"></div>
                            <?php echo field_error('student_id'); ?>
                        </div>

                        <div class="form-group">
                            <label>Program</label>
                            <div class="input-wrapper">
                                <i class='bx bx-book input-icon'></i>
                                <select name="program" required>
                                    <option value="" <?php echo is_selected('program', ''); ?>>Select Program</option>
                                    <option value="BSIT" <?php echo is_selected('program', 'BSIT'); ?>>BS Information Technology</option>
                                    <option value="BSCS" <?php echo is_selected('program', 'BSCS'); ?>>BS Computer Science</option>
                                </select>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label>Password</label>
                                <div class="input-wrapper">
                                    <i class='bx bx-lock-alt input-icon'></i>
                                    <input type="password" id="reg-pass" name="password" placeholder="Min. 8 chars" required>
                                    <button type="button" class="toggle-password" onclick="togglePassword('reg-pass')"><i class='bx bx-show'></i></button>
                                </div>
                                <?php echo field_error('password'); ?>
                            </div>
                            <div class="form-group">
                                <label>Confirm</label>
                                <div class="input-wrapper">
                                    <i class='bx bx-lock-alt input-icon'></i>
                                    <input type="password" id="reg-confirm" name="confirm_password" placeholder="Repeat" required>
                                </div>
                                <?php echo field_error('confirm_password'); ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="terms" required> I agree to <a href="#">Terms</a> & <a href="#">Privacy</a>
                            </label>
                        </div>

                        <button type="submit" class="btn-submit">Create Account <i class='bx bx-user-plus'></i></button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="js/main.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Loader Fadeout
        const pageLoader = document.querySelector('.page-loader');
            if (pageLoader) {
                setTimeout(() => {
                    pageLoader.classList.add('fade-out');
                    setTimeout(() => { pageLoader.style.display = 'none'; }, 1500);
                }, 1000);
            }
        
        // Tab Switching
        const urlParams = new URLSearchParams(window.location.search);
        const formParam = urlParams.get('form');
        const btns = document.querySelectorAll('.toggle-btn');
        const forms = document.querySelectorAll('.form-container');

        function switchTab(tabName) {
            btns.forEach(b => b.classList.remove('active'));
            forms.forEach(f => f.classList.remove('active'));
            
            document.querySelector(`.toggle-btn[data-form="${tabName}"]`).classList.add('active');
            document.getElementById(`${tabName}-form`).classList.add('active');
        }

        btns.forEach(btn => {
            btn.addEventListener('click', () => switchTab(btn.dataset.form));
        });

        // PHP logic to auto-switch tab on error
        <?php if (!empty($form_data) || !empty($validation_errors) || (isset($_GET['form']) && $_GET['form'] === 'register')): ?>
            switchTab('register');
        <?php endif; ?>
    });
    
    function togglePassword(id) {
        const input = document.getElementById(id);
        const icon = input.nextElementSibling.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.replace('bx-show', 'bx-hide');
        } else {
            input.type = 'password';
            icon.classList.replace('bx-hide', 'bx-show');
        }
    }
    </script>
</body>
</html>