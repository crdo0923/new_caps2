<?php
session_start();

// Check if user is logged in. Redirect to auth.php if not.
if (!isset($_SESSION['user_id'])) {
    header('Location: auth.php');
    exit();
}

$firstname = htmlspecialchars($_SESSION['firstname'] ?? 'Student');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Learning Resources - SmartStudy</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    
    <link rel="stylesheet" href="css/loading.css">
    <?php include __DIR__ . '/includes/layout_preamble.php'; ?>
    <link rel="stylesheet" href="css/layout.css">
    <!-- dashboard.css removed: this page uses its own resources styles -->
    
    <style>
        /* =========================================
           DARK THEME RESOURCES STYLES
           ========================================= */
        :root {
            --primary: #6366f1;
            --primary-hover: #4f46e5;
            --secondary: #ec4899;
            --bg-dark: #0f172a;
            --bg-card: #1e293b;
            --text-light: #f8fafc;
            --text-gray: #94a3b8;
            --border: #334155;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-dark);
            color: var(--text-light);
            margin: 0;
            padding: 0;
            overflow-x: hidden;
            line-height: 1.6;
        }

        .resources-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        /* ACTIONS & HEADER */
        .resources-actions { margin-bottom: 2rem; }
        .btn-back {
            color: var(--text-gray);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
            transition: color 0.3s;
            font-size: 1rem;
        }
        .btn-back:hover { color: var(--primary); }

        .section-header { text-align: center; margin-bottom: 3rem; }
        .section-header h1 { font-size: 2.5rem; font-weight: 800; margin-bottom: 0.5rem; color: white; letter-spacing: -0.5px; }
        .section-header p { color: var(--text-gray); font-size: 1.1rem; }

        /* GRID LAYOUT */
        .resources-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        /* RESOURCE CARD */
        .resource-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 2rem;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .resource-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        /* CARD ICON */
        .icon-wrapper {
            width: 60px;
            height: 60px;
            background: rgba(99, 102, 241, 0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            color: var(--primary);
            font-size: 2rem;
            transition: 0.3s;
        }
        .resource-card:hover .icon-wrapper { background: var(--primary); color: white; }

        /* Color Variants per Card Type (Optional Polish) */
        .card-db .icon-wrapper { color: #10b981; background: rgba(16, 185, 129, 0.1); }
        .resource-card.card-db:hover .icon-wrapper { background: #10b981; color: white; }
        
        .card-sec .icon-wrapper { color: #f59e0b; background: rgba(245, 158, 11, 0.1); }
        .resource-card.card-sec:hover .icon-wrapper { background: #f59e0b; color: white; }

        .card-pomo .icon-wrapper { color: #ec4899; background: rgba(236, 72, 153, 0.1); }
        .resource-card.card-pomo:hover .icon-wrapper { background: #ec4899; color: white; }

        /* CARD CONTENT */
        .resource-card h3 { font-size: 1.25rem; font-weight: 700; margin: 0 0 0.5rem 0; color: white; }
        .resource-card p { font-size: 0.95rem; color: var(--text-gray); margin-bottom: 2rem; flex-grow: 1; line-height: 1.5; }

        /* BUTTON */
        .btn-resource-link {
            text-decoration: none;
            color: var(--text-light);
            font-weight: 600;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 1.2rem;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            transition: 0.3s;
            width: 100%;
            justify-content: center;
            border: 1px solid var(--border);
            box-sizing: border-box;
        }
        
        .resource-card:hover .btn-resource-link {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }
        
        /* Full Width Span for Last Item if odd number */
        .full-width { grid-column: 1 / -1; display: flex; flex-direction: row; align-items: center; gap: 2rem; }
        .full-width .icon-wrapper { margin-bottom: 0; width: 80px; height: 80px; font-size: 2.5rem; }
        .full-width-content { flex-grow: 1; }
        .full-width .btn-resource-link { width: auto; padding: 0.8rem 2rem; }

        @media (max-width: 768px) {
            .resources-container { padding: 0 1rem; }
            .section-header h1 { font-size: 2rem; }
            .full-width { flex-direction: column; align-items: flex-start; gap: 1rem; }
            .full-width .icon-wrapper { margin-bottom: 1rem; }
            .full-width .btn-resource-link { width: 100%; }
        }
    </style>
</head>
<body class="resources-body"> 
    
    <?php include 'includes/mobile_blocker.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    <!-- keep loader and original layout (no shared sidebar) -->
    <div class="page-loader">
    <div class="loader-container">
<div class="loader-icon"><i class='bx bx-brain' style="font-size: 4rem; color: #6366f1;"></i></div>        <div class="loader-text" style="color: #cbd5e1; font-family: 'Inter', sans-serif; margin-bottom: 10px;"></div>
        <div class="loader-spinner"><div class="spinner-ring"></div></div>
    </div>
</div>

    <main class="main-content">
    <div class="resources-container">
        
        <!-- Removed redundant back-to-dashboard button: navigation via sidebar available -->

        <div class="resources-content">
            
            <div class="section-header">
                <h1><i class='bx bxs-book-content'></i> Learning Resources</h1>
                <p>Curated links, tools, and guides to help you master your subjects.</p>
            </div>

            <div class="resources-grid">
                
                <div class="resource-card">
                    <div class="icon-wrapper"><i class='bx bx-code-alt'></i></div>
                    <h3>Web Development Mastery</h3>
                    <p>Links to the best free courses and documentation for HTML, CSS, JavaScript, and PHP frameworks.</p>
                    <a href="#" class="btn-resource-link">Go to Tutorials <i class='bx bx-right-arrow-alt'></i></a>
                </div>

                <div class="resource-card card-db">
                    <div class="icon-wrapper"><i class='bx bxs-data'></i></div>
                    <h3>Database Systems Guides</h3>
                    <p>SQL cheatsheets, normalization guides, ERD tools, and PostgreSQL/MySQL optimization techniques.</p>
                    <a href="#" class="btn-resource-link">View SQL Guides <i class='bx bx-right-arrow-alt'></i></a>
                </div>

                <div class="resource-card">
                    <div class="icon-wrapper"><i class='bx bx-code-curly'></i></div>
                    <h3>DSA Practice Problems</h3>
                    <p>Access competitive programming platforms (LeetCode, HackerRank) and algorithm visualizers.</p>
                    <a href="#" class="btn-resource-link">Start Practice <i class='bx bx-right-arrow-alt'></i></a>
                </div>

                <div class="resource-card card-sec">
                    <div class="icon-wrapper"><i class='bx bx-shield-quarter'></i></div>
                    <h3>Networking & Security</h3>
                    <p>Videos and articles covering TCP/IP, subnetting, basic security principles, and ethical hacking.</p>
                    <a href="#" class="btn-resource-link">Explore Security <i class='bx bx-right-arrow-alt'></i></a>
                </div>
                
                <div class="resource-card card-pomo full-width">
                    <div class="icon-wrapper"><i class='bx bxs-timer'></i></div>
                    <div class="full-width-content">
                        <h3>Focus Tools & Pomodoro Apps</h3>
                        <p>Find external tools, timers, and ambient noise generators to enhance your focus and time management while studying.</p>
                    </div>
                    <a href="#" class="btn-resource-link">Check Focus Apps <i class='bx bx-right-arrow-alt'></i></a>
                </div>

            </div>
        </div>
    </div>
    </main>

    <script src="js/main.js"></script>
    <script src="js/sidebar.js"></script>
    <script>
    // Standard Loader Fadeout
    document.addEventListener('DOMContentLoaded', function() {
        const pageLoader = document.querySelector('.page-loader');
        if (pageLoader) {
            setTimeout(() => {
                pageLoader.style.opacity = '0';
                setTimeout(() => { pageLoader.style.display = 'none'; }, 1500);
            }, 1500);
        }
    });
</script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Standard Loader Fadeout
            // page loader removed
        });
    </script>
    <?php include 'includes/call_overlay.php'; ?> 
</body>
</html>