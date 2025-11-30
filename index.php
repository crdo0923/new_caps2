<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartStudy - AI-Powered Study Planner</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    
    <link rel="stylesheet" href="css/loading.css">
    <link rel="stylesheet" href="css/index.css">
    <style>
        /* =========================================
           GLOBAL STYLES (Dark Theme)
           ========================================= */
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
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

        a { text-decoration: none; transition: 0.3s; }
        ul { list-style: none; padding: 0; margin: 0; }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 2rem; }

        /* =========================================
           NAVBAR
           ========================================= */
        .navbar {
            position: fixed;
            top: 0;
            width: 100%;
            background: rgba(15, 23, 42, 0.9);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border);
            z-index: 1000;
            padding: 1rem 0;
        }

        .navbar .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .nav-brand {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text-light);
        }

        .nav-brand i { color: var(--primary); font-size: 2rem; }

        .nav-menu { display: flex; gap: 2rem; align-items: center; }
        .nav-menu a { color: var(--text-gray); font-weight: 500; font-size: 0.95rem; }
        .nav-menu a:hover, .nav-menu a.active { color: var(--primary); }

        .btn-login { margin-right: 1rem; }
        .btn-register {
            background: var(--primary);
            color: white !important;
            padding: 0.6rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
        }
        .btn-register:hover { background: var(--primary-dark); transform: translateY(-2px); }

        /* =========================================
           HERO SECTION
           ========================================= */
        .hero {
            padding: 160px 0 100px 0;
            display: flex;
            align-items: center;
            min-height: 80vh;
            position: relative;
        }

        /* Background Glow Effect */
        .hero::before {
            content: '';
            position: absolute;
            top: -10%;
            right: 0;
            width: 50%;
            height: 50%;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.15) 0%, rgba(15, 23, 42, 0) 70%);
            z-index: -1;
        }

        .hero-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: center;
        }

        .hero-text h1 {
            font-size: 3.5rem;
            line-height: 1.1;
            margin-bottom: 1.5rem;
            font-weight: 800;
        }

        .gradient-text {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-text p {
            font-size: 1.125rem;
            color: var(--text-gray);
            margin-bottom: 2.5rem;
            max-width: 90%;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 1rem 2.5rem;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 700;
            display: inline-block;
            box-shadow: 0 10px 25px -5px rgba(99, 102, 241, 0.4);
        }
        .btn-primary:hover { transform: translateY(-3px); box-shadow: 0 15px 30px -5px rgba(99, 102, 241, 0.5); }

        .hero-image { position: relative; display: flex; justify-content: center; }
        
        /* Floating Cards Animation */
        .floating-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            padding: 1rem 1.5rem;
            border-radius: 12px;
            position: absolute;
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            animation: float 6s ease-in-out infinite;
        }
        
        .floating-card i { font-size: 1.5rem; padding: 10px; border-radius: 8px; }
        .card-1 { top: 0; left: 0; animation-delay: 0s; }
        .card-1 i { background: rgba(99, 102, 241, 0.2); color: var(--primary); }
        
        .card-2 { top: 40%; right: -20px; animation-delay: 2s; }
        .card-2 i { background: rgba(236, 72, 153, 0.2); color: var(--secondary); }
        
        .card-3 { bottom: 0; left: 20px; animation-delay: 4s; }
        .card-3 i { background: rgba(16, 185, 129, 0.2); color: #10b981; }

        .hero-illustration {
            width: 400px;
            height: 400px;
            background: var(--bg-card);
            border-radius: 50%;
            border: 2px dashed var(--border);
            position: relative;
            z-index: -2;
        }

        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
            100% { transform: translateY(0px); }
        }

        /* =========================================
           FEATURES SECTION
           ========================================= */
        .features { padding: 100px 0; background-color: #0b1120; }
        .section-header { text-align: center; margin-bottom: 4rem; }
        .section-header h2 { font-size: 2.5rem; margin-bottom: 1rem; font-weight: 700; }
        .section-header p { color: var(--text-gray); font-size: 1.1rem; }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .feature-card {
            background: var(--bg-card);
            padding: 2rem;
            border-radius: 16px;
            border: 1px solid var(--border);
            transition: 0.3s;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
            box-shadow: 0 10px 30px rgba(99, 102, 241, 0.1);
        }

        .feature-icon {
            width: 50px;
            height: 50px;
            background: rgba(99, 102, 241, 0.1);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .feature-card h3 { margin: 0 0 0.75rem 0; font-size: 1.25rem; }
        .feature-card p { margin: 0; color: var(--text-gray); font-size: 0.95rem; }

        /* =========================================
           HOW IT WORKS
           ========================================= */
        .how-it-works { padding: 100px 0; }
        .steps {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 3rem;
            position: relative;
        }

        .step { text-align: center; flex: 1; padding: 0 1rem; z-index: 2; }
        
        .step-number {
            width: 60px;
            height: 60px;
            background: var(--bg-card);
            border: 2px solid var(--primary);
            color: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 800;
            margin: 0 auto 1.5rem auto;
            box-shadow: 0 0 20px rgba(99, 102, 241, 0.2);
        }

        .step-arrow { color: var(--border); font-size: 2rem; }

        /* =========================================
           CTA SECTION
           ========================================= */
        .cta { padding: 5rem 0; }
        .cta-content {
            background: linear-gradient(135deg, var(--primary-dark), var(--secondary));
            padding: 4rem;
            border-radius: 24px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .cta-content h2 { font-size: 2.5rem; margin-bottom: 1rem; }
        .cta-content p { font-size: 1.2rem; margin-bottom: 2.5rem; opacity: 0.9; }
        
        .btn-large {
            background: white;
            color: var(--primary);
            padding: 1rem 3rem;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1.1rem;
        }
        .btn-large:hover { transform: scale(1.05); box-shadow: 0 10px 30px rgba(0,0,0,0.2); }

        /* =========================================
           FOOTER
           ========================================= */
        .footer {
            background: #020617;
            padding: 4rem 0 2rem 0;
            border-top: 1px solid var(--border);
        }

        .footer-content {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 3rem;
            margin-bottom: 3rem;
        }

        .footer-section h3 { display: flex; align-items: center; gap: 0.5rem; font-size: 1.5rem; margin-bottom: 1rem; }
        .footer-section h3 i { color: var(--primary); }
        .footer-section p { color: var(--text-gray); }
        .footer-section h4 { color: white; margin-bottom: 1.5rem; font-weight: 600; }
        .footer-section ul li { margin-bottom: 0.75rem; }
        .footer-section ul li a { color: var(--text-gray); }
        .footer-section ul li a:hover { color: var(--primary); padding-left: 5px; }

        .footer-bottom {
            text-align: center;
            padding-top: 2rem;
            border-top: 1px solid rgba(255,255,255,0.05);
            color: var(--text-gray);
            font-size: 0.9rem;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .hero-content { grid-template-columns: 1fr; text-align: center; }
            .hero-image { margin-top: 3rem; display: none; } /* Hide complicated graphics on mobile */
            .hero-text h1 { font-size: 2.5rem; }
            .features-grid { grid-template-columns: 1fr; }
            .steps { flex-direction: column; gap: 2rem; }
            .step-arrow { transform: rotate(90deg); }
            .footer-content { grid-template-columns: 1fr; }
            .nav-menu { display: none; } /* Simplified for now */
        }
    </style>
</head>
<body>

    <?php include 'includes/mobile_blocker.php'; ?>

    <div class="page-loader">
    <div class="loader-container">
        <div class="loader-icon"><i class='bx bxl-brain'></i></div>
        <div class="loader-text" style="color: #cbd5e1; font-family: 'Inter', sans-serif; margin-bottom: 10px;"></div>
        <div class="loader-spinner"><div class="spinner-ring"></div></div>
    </div>
</div>

    <nav class="navbar">
        <div class="container">
            <a href="#" class="nav-brand">
                <i class='bx bxl-c-plus-plus'></i> <span>SmartStudy</span>
            </a>
            <ul class="nav-menu">
                <li><a href="#home" class="active">Home</a></li>
                <li><a href="about.php">About</a></li>
                <li><a href="auth.php" class="btn-login">Login</a></li>
                <li><a href="auth.php" class="btn-register">Sign Up</a></li>
            </ul>
        </div>
    </nav>

    <section id="home" class="hero">
        <div class="container">
            <div class="hero-content">
                <div class="hero-text">
                    <h1>Master Your Study Time with <span class="gradient-text">AI Power</span></h1>
                    <p>Ang intelligent study planner na tutulong sayo mag-organize, mag-focus, at mag-succeed sa academics mo!</p>
                    <div class="hero-buttons">
                        <a href="auth.php" class="btn btn-primary">Get Started Free <i class='bx bx-right-arrow-alt'></i></a>
                    </div>
                </div>
                
                <div class="hero-image">
                    <div class="floating-card card-1">
                        <i class='bx bx-book-open'></i>
                        <div>
                            <strong>Smart Plan</strong>
                            <div style="font-size: 0.8rem; color: #94a3b8;">Auto-scheduled</div>
                        </div>
                    </div>
                    <div class="floating-card card-2">
                        <i class='bx bx-target-lock'></i>
                        <div>
                            <strong>Focus Mode</strong>
                            <div style="font-size: 0.8rem; color: #94a3b8;">Block distractions</div>
                        </div>
                    </div>
                    <div class="floating-card card-3">
                        <i class='bx bx-bar-chart-alt-2'></i>
                        <div>
                            <strong>Analytics</strong>
                            <div style="font-size: 0.8rem; color: #94a3b8;">Track progress</div>
                        </div>
                    </div>
                    <div class="hero-illustration"></div>
                </div>
            </div>
        </div>
    </section>

    <section id="features" class="features">
        <div class="container">
            <div class="section-header">
                <h2>Powerful Features Para Sa'yo</h2>
                <p>Everything you need para maging productive at successful student</p>
            </div>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon"><i class='bx bx-bot'></i></div>
                    <h3>AI-Powered Scheduler</h3>
                    <p>Automatic na mag-create ng optimal study schedule based sa deadlines at preferences mo.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class='bx bx-shield-quarter'></i></div>
                    <h3>Distraction Blocker</h3>
                    <p>Stay focused! Block social media at distracting websites habang nag-aaral ka.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class='bx bx-timer'></i></div>
                    <h3>Pomodoro Timer</h3>
                    <p>Built-in focus timer with smart break reminders para sa maximum productivity.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class='bx bx-line-chart'></i></div>
                    <h3>Progress Analytics</h3>
                    <p>Track your study patterns, productivity, at improvement over time.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class='bx bx-crosshair'></i></div>
                    <h3>Smart Prioritization</h3>
                    <p>AI na mag-prioritize ng tasks based sa urgency at importance. No more cramming!</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class='bx bx-trophy'></i></div>
                    <h3>Rewards System</h3>
                    <p>Earn points, badges, at achievements habang nag-aaral ka. Make studying fun!</p>
                </div>
            </div>
        </div>
    </section>

    <section class="how-it-works">
        <div class="container">
            <div class="section-header">
                <h2>How It Works?</h2>
                <p>It's simple! 3 steps to better studying</p>
            </div>
            <div class="steps">
                <div class="step">
                    <div class="step-number">1</div>
                    <h3>Create Account</h3>
                    <p style="color: var(--text-gray);">Sign up in less than a minute</p>
                </div>
                <div class="step-arrow"><i class='bx bx-chevron-right'></i></div>
                <div class="step">
                    <div class="step-number">2</div>
                    <h3>Add Your Tasks</h3>
                    <p style="color: var(--text-gray);">Input your subjects at deadlines</p>
                </div>
                <div class="step-arrow"><i class='bx bx-chevron-right'></i></div>
                <div class="step">
                    <div class="step-number">3</div>
                    <h3>Let AI Help</h3>
                    <p style="color: var(--text-gray);">Get personalized study plans!</p>
                </div>
            </div>
        </div>
    </section>

    <section class="cta">
        <div class="container">
            <div class="cta-content">
                <h2>Ready to Transform Your Study Habits?</h2>
                <p>Join hundreds of PLMun students achieving their academic goals</p>
                <a href="auth.php" class="btn-large">Start Free Today</a>
            </div>
        </div>
    </section>

    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3><i class='bx bxl-c-plus-plus'></i> SmartStudy</h3>
                    <p>AI-Powered Study Planner for PLMun Students. Helping you achieve academic excellence through smart time management.</p>
                </div>
                <div class="footer-section">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="#home">Home</a></li>
                        <li><a href="about.php">About Us</a></li>
                        <li><a href="auth.php">Login</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4>Developers</h4>
                    <ul>
                        <li>FLANDEZ, RICARDO Jr. G.</li>
                        <li>COLLADO, PATRICK S.</li>
                        <li>PISTON, CHRIS ZAN D.</li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4>Contact</h4>
                    <ul>
                        <li><i class='bx bxs-school'></i> PLMun CITCS</li>
                        <li><i class='bx bxs-map'></i> Muntinlupa City</li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2025 SmartStudy. Capstone Project - PLMun CITCS</p>
            </div>
        </div>
    </footer>

    <script src="js/main.js"></script>
    <script>
    // Universal Loader Fade-out
    document.addEventListener('DOMContentLoaded', function() {
        const pageLoader = document.querySelector('.page-loader');
        if (pageLoader) {
            // Mabilis na fade-out (300ms)
            setTimeout(() => {
                pageLoader.classList.add('fade-out');
                // Tanggalin sa DOM after animation
                setTimeout(() => { pageLoader.style.display = 'none'; }, 1500);
            }, 1500); // Delay ng konti para di glitchy tingnan
        }
    });
</script>
    <script>
        // Simple Navbar Scroll Effect
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.style.background = 'rgba(15, 23, 42, 0.95)';
                navbar.style.boxShadow = '0 4px 20px rgba(0,0,0,0.4)';
            } else {
                navbar.style.background = 'rgba(15, 23, 42, 0.9)';
                navbar.style.boxShadow = 'none';
            }
        });
    </script>
</body>
</html>