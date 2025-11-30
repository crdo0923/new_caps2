<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - SmartStudy</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    
    <link rel="stylesheet" href="css/loading.css">
        <link rel="stylesheet" href="css/index.css">
    <style>
        /* =========================================
           GLOBAL STYLES (Dark Theme Match)
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
           ABOUT HERO SECTION
           ========================================= */
        .about-hero {
            padding: 180px 0 100px 0;
            text-align: center;
            background: radial-gradient(circle at top center, rgba(99, 102, 241, 0.15), transparent 70%);
        }

        .about-hero h1 {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 1.5rem;
        }

        .gradient-text {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .about-hero p {
            font-size: 1.25rem;
            color: var(--text-gray);
            max-width: 600px;
            margin: 0 auto;
        }

        /* =========================================
           MISSION & VISION
           ========================================= */
        .mission-vision { padding: 50px 0 100px 0; }
        .mission-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        .mission-card {
            background: var(--bg-card);
            padding: 3rem;
            border-radius: 20px;
            border: 1px solid var(--border);
            text-align: center;
            transition: transform 0.3s;
        }

        .mission-card:hover { transform: translateY(-10px); border-color: var(--primary); }

        .mission-card .icon {
            font-size: 3rem;
            color: var(--primary);
            margin-bottom: 1.5rem;
            background: rgba(99, 102, 241, 0.1);
            width: 80px;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            margin-left: auto;
            margin-right: auto;
        }

        .mission-card h2 { font-size: 1.75rem; margin-bottom: 1rem; }
        .mission-card p { color: var(--text-gray); font-size: 1rem; }

        /* =========================================
           PROJECT BACKGROUND
           ========================================= */
        .project-background { background-color: #0b1120; padding: 100px 0; }
        .section-title { text-align: center; font-size: 2.5rem; margin-bottom: 4rem; font-weight: 700; }
        
        .background-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: center;
        }

        .background-text p { margin-bottom: 1.5rem; color: var(--text-gray); font-size: 1.05rem; }

        .background-features {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        .feature-item {
            background: var(--bg-card);
            padding: 1.5rem;
            border-radius: 12px;
            border: 1px solid var(--border);
            display: flex;
            align-items: flex-start;
            gap: 1rem;
        }

        .feature-item i { font-size: 1.5rem; color: var(--secondary); margin-top: 3px; }
        .feature-item h4 { margin: 0 0 0.5rem 0; font-size: 1.1rem; }
        .feature-item p { margin: 0; font-size: 0.85rem; color: var(--text-gray); }

        /* =========================================
           TEAM SECTION
           ========================================= */
        .team-section { padding: 100px 0; }
        .team-subtitle { text-align: center; color: var(--primary); margin-top: -3.5rem; margin-bottom: 4rem; font-weight: 600; letter-spacing: 1px; }

        .team-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2.5rem;
        }

        .team-card {
            background: var(--bg-card);
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid var(--border);
            transition: 0.3s;
        }

        .team-card:hover { transform: translateY(-10px); box-shadow: 0 20px 40px rgba(0,0,0,0.4); }

        .team-image { position: relative; height: 350px; overflow: hidden; }
        .team-image img { width: 100%; height: 100%; object-fit: cover; transition: 0.5s; }
        .team-card:hover .team-image img { transform: scale(1.1); }

        .team-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            padding: 1rem;
            background: linear-gradient(to top, rgba(15,23,42,0.9), transparent);
            color: white;
            text-align: center;
            font-weight: 700;
            letter-spacing: 1px;
        }

        .team-info { padding: 2rem; text-align: center; }
        .team-info h3 { font-size: 1.25rem; margin-bottom: 0.5rem; color: var(--text-light); }
        .role { color: var(--primary); font-weight: 600; font-size: 0.9rem; margin-bottom: 1rem; text-transform: uppercase; }
        .description { font-size: 0.9rem; color: var(--text-gray); margin-bottom: 1.5rem; line-height: 1.5; }

        .team-skills { display: flex; justify-content: center; gap: 0.5rem; flex-wrap: wrap; }
        .skill {
            background: rgba(255,255,255,0.05);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            color: var(--text-light);
            border: 1px solid var(--border);
        }

        /* =========================================
           TECHNOLOGIES SECTION
           ========================================= */
        .technologies { padding: 100px 0; background-color: #0b1120; }
        .tech-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1.5rem;
        }

        .tech-card {
            background: var(--bg-card);
            padding: 2rem;
            border-radius: 16px;
            text-align: center;
            border: 1px solid var(--border);
            transition: 0.3s;
        }

        .tech-card:hover { border-color: var(--primary); background: rgba(99, 102, 241, 0.05); }
        .tech-icon { font-size: 2.5rem; color: var(--text-light); margin-bottom: 1rem; }
        .tech-card h4 { margin: 0 0 0.5rem 0; font-size: 1.1rem; }
        .tech-card p { font-size: 0.85rem; color: var(--text-gray); margin: 0; }

        /* =========================================
           CONTACT SECTION
           ========================================= */
        .contact-section { padding: 100px 0; }
        .contact-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
        }

        .contact-card {
            background: var(--bg-card);
            padding: 2.5rem;
            border-radius: 16px;
            text-align: center;
            border: 1px solid var(--border);
        }

        .contact-icon {
            width: 60px;
            height: 60px;
            background: var(--bg-dark);
            color: var(--primary);
            font-size: 1.75rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem auto;
            border: 1px solid var(--border);
        }

        .contact-card h4 { font-size: 1.25rem; margin-bottom: 1rem; }
        .contact-card p { color: var(--text-gray); margin: 0.25rem 0; font-size: 0.95rem; }

        /* =========================================
           FOOTER (Match Index)
           ========================================= */
        .footer { background: #020617; padding: 4rem 0 2rem 0; border-top: 1px solid var(--border); }
        .footer-content { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 3rem; margin-bottom: 3rem; }
        .footer-section h3 { display: flex; align-items: center; gap: 0.5rem; font-size: 1.5rem; margin-bottom: 1rem; }
        .footer-section h3 i { color: var(--primary); }
        .footer-section p { color: var(--text-gray); }
        .footer-section h4 { color: white; margin-bottom: 1.5rem; font-weight: 600; }
        .footer-section ul li { margin-bottom: 0.75rem; }
        .footer-section ul li a { color: var(--text-gray); }
        .footer-section ul li a:hover { color: var(--primary); padding-left: 5px; }
        .footer-bottom { text-align: center; padding-top: 2rem; border-top: 1px solid rgba(255,255,255,0.05); color: var(--text-gray); font-size: 0.9rem; }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .navbar .nav-menu { display: none; }
            .about-hero h1 { font-size: 2.5rem; }
            .mission-grid, .background-content, .background-features, .footer-content { grid-template-columns: 1fr; }
            .team-image { height: 300px; }
        }
    </style>
</head>
    <?php include 'includes/mobile_blocker.php'; ?>

<body>

    <div class="page-loader">
    <div class="loader-container">
        <div class="loader-icon"><i class='bx bxl-brain' style="font-size: 4rem; color: #6366f1;"></i></div>        <div class="loader-text" style="color: #cbd5e1; font-family: 'Inter', sans-serif; margin-bottom: 10px;"></div>
        <div class="loader-spinner"><div class="spinner-ring"></div></div>
    </div>
</div>

    <nav class="navbar">
        <div class="container">
            <a href="index.php" class="nav-brand">
                <i class='bx bxl-c-plus-plus'></i>
                <span>SmartStudy</span>
            </a>
            <ul class="nav-menu">
                <li><a href="index.php">Home</a></li>
                <li><a href="about.php" class="active">About</a></li>
                <li><a href="auth.php" class="btn-login">Login</a></li>
                <li><a href="auth.php" class="btn-register">Sign Up</a></li>
            </ul>
        </div>
    </nav>

    <section class="about-hero">
        <div class="container">
            <h1>About <span class="gradient-text">SmartStudy</span></h1>
            <p>Empowering PLMun students to achieve academic excellence through intelligent planning</p>
        </div>
    </section>

    <section class="mission-vision">
        <div class="container">
            <div class="mission-grid">
                <div class="mission-card">
    <div class="icon"><i class='bx bx-target-lock'></i></div>
    <h2>Our Mission</h2>
    <p>To provide students with an intelligent, user-friendly study planning application that helps them manage their time effectively, minimize distractions, and achieve their academic goals through AI-powered features and personalized recommendations.</p>
</div>
                <div class="mission-card">
                    <div class="icon"><i class='bx bx-show-alt'></i></div>
                    <h2>Our Vision</h2>
                    <p>To become the leading study management platform for students in the Philippines, transforming how students approach learning and time management through innovative technology and data-driven insights.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="project-background">
        <div class="container">
            <h2 class="section-title">Project Background</h2>
            <div class="background-content">
                <div class="background-text">
                    <p>SmartStudy is a capstone project developed by students from the College of Information Technology and Computer Studies at Pamantasan ng Lungsod ng Muntinlupa.</p>
                    <p>This project addresses the growing need for effective time management and distraction control among students in today's digital age. Through extensive research and user feedback, we identified key challenges that students face in maintaining focus and organizing their study schedules.</p>
                    <p>Our solution combines artificial intelligence, modern web technologies, and user-centered design to create a comprehensive study planning platform that adapts to individual learning patterns and preferences.</p>
                </div>
                <div class="background-features">
                    <div class="feature-item">
                        <i class='bx bx-bot'></i>
                        <div>
                            <h4>AI-Powered</h4>
                            <p>Smart algorithms for optimal scheduling</p>
                        </div>
                    </div>
                    <div class="feature-item">
                        <i class='bx bx-shield-quarter'></i>
                        <div>
                            <h4>Distraction Control</h4>
                            <p>Focus mode and website blocking</p>
                        </div>
                    </div>
                    <div class="feature-item">
                        <i class='bx bx-bar-chart-alt-2'></i>
                        <div>
                            <h4>Progress Tracking</h4>
                            <p>Detailed analytics and insights</p>
                        </div>
                    </div>
                    <div class="feature-item">
                        <i class='bx bx-trophy'></i>
                        <div>
                            <h4>Goal-Oriented</h4>
                            <p>Set and achieve study goals</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="team-section">
        <div class="container">
            <h2 class="section-title">Meet the <span class="gradient-text">Development Team</span></h2>
            <p class="team-subtitle">BSIT Students | Pamantasan ng Lungsod ng Muntinlupa</p>
            
            <div class="team-grid">
                <div class="team-card">
                    <div class="team-image">
                        <img src="images/cardo.jfif" alt="Ricardo Flandez Jr.">
                        <div class="team-overlay">Project Leader</div>
                    </div>
                    <div class="team-info">
                        <h3>FLANDEZ, RICARDO Jr. G.</h3>
                        <p class="role">Lead Developer & Project Manager</p>
                        <p class="description">Specializes in system architecture, AI integration, and project coordination. Leads the development team in creating innovative solutions.</p>
                        <div class="team-skills">
                            <span class="skill">PHP</span>
                            <span class="skill">JavaScript</span>
                            <span class="skill">AI Integration</span>
                        </div>
                    </div>
                </div>

                <div class="team-card">
                    <div class="team-image">
                        <img src="images/patrick.jfif" alt="Patrick Collado">
                        <div class="team-overlay">Full-Stack Developer</div>
                    </div>
                    <div class="team-info">
                        <h3>COLLADO, PATRICK S.</h3>
                        <p class="role">Backend Developer & Database Architect</p>
                        <p class="description">Expert in database design, server-side programming, and API development. Ensures system reliability and data security.</p>
                        <div class="team-skills">
                            <span class="skill">MySQL</span>
                            <span class="skill">PHP</span>
                            <span class="skill">Backend</span>
                        </div>
                    </div>
                </div>

                <div class="team-card">
                    <div class="team-image">
                        <img src="images/chris.jfif" alt="Chris Zan Piston">
                        <div class="team-overlay">Frontend Specialist</div>
                    </div>
                    <div class="team-info">
                        <h3>PISTON, CHRIS ZAN D.</h3>
                        <p class="role">UI/UX Designer & Frontend Developer</p>
                        <p class="description">Focuses on creating intuitive user interfaces and engaging user experiences. Implements responsive design and interactive features.</p>
                        <div class="team-skills">
                            <span class="skill">HTML/CSS</span>
                            <span class="skill">JavaScript</span>
                            <span class="skill">UI/UX</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="technologies">
        <div class="container">
            <h2 class="section-title">Technologies Used</h2>
            <div class="tech-grid">
                <div class="tech-card">
                    <div class="tech-icon"><i class='bx bxl-html5'></i></div>
                    <h4>HTML5 & CSS3</h4>
                    <p>Modern web standards for structure and styling</p>
                </div>
                <div class="tech-card">
                    <div class="tech-icon"><i class='bx bxl-javascript'></i></div>
                    <h4>JavaScript</h4>
                    <p>Interactive features and dynamic content</p>
                </div>
                <div class="tech-card">
                    <div class="tech-icon"><i class='bx bxl-php'></i></div>
                    <h4>PHP</h4>
                    <p>Server-side processing and logic</p>
                </div>
                <div class="tech-card">
                    <div class="tech-icon"><i class='bx bxs-data'></i></div>
                    <h4>MySQL</h4>
                    <p>Reliable database management</p>
                </div>
                <div class="tech-card">
                    <div class="tech-icon"><i class='bx bx-brain'></i></div>
                    <h4>AI Algorithms</h4>
                    <p>Smart scheduling and recommendations</p>
                </div>
                <div class="tech-card">
    <div class="tech-icon"><i class="bx bx-mobile-alt"></i></div>
    <h4>Mobile App</h4>
    <p>Coming Soon to iOS & Android</p>
</div>
            </div>
        </div>
    </section>

    <section class="contact-section">
        <div class="container">
            <h2 class="section-title">Get In Touch</h2>
            <div class="contact-info">
                <div class="contact-card">
                    <div class="contact-icon"><i class='bx bxs-school'></i></div>
                    <h4>Institution</h4>
                    <p>Pamantasan ng Lungsod ng Muntinlupa</p>
                    <p>College of Information Technology and Computer Studies</p>
                </div>
                <div class="contact-card">
                    <div class="contact-icon"><i class='bx bxs-map'></i></div>
                    <h4>Location</h4>
                    <p>National Road, Poblacion</p>
                    <p>Muntinlupa City, Metro Manila</p>
                </div>
                <div class="contact-card">
                    <div class="contact-icon"><i class='bx bxs-envelope'></i></div>
                    <h4>Email</h4>
                    <p>smartstudy@plmun.edu.ph</p>
                    <p>citcs@plmun.edu.ph</p>
                </div>
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
                        <li><a href="index.php">Home</a></li>
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
<script>
    // page loader removed
</script>
    <script src="js/main.js"></script>
    <script>
        // Navbar Scroll Effect
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