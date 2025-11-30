<style>
    #mobile-notice { display: none; }

    @media (max-width: 1024px) {
        #mobile-notice {
            position: fixed;
            top: 0; left: 0;
            width: 100vw; height: 100vh;
            background-color: #0f172a;
            z-index: 9999999;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            text-align: center;
            color: #f8fafc;
        }
        
        #mobile-notice i { 
            font-size: 5rem; 
            color: #6366f1; 
            margin-bottom: 1.5rem; 
            animation: float 3s ease-in-out infinite;
        }
        
        #mobile-notice h2 { font-size: 1.8rem; font-weight: 800; margin-bottom: 0.5rem; }
        #mobile-notice h3 { font-size: 1.2rem; font-weight: 500; color: #ec4899; margin-bottom: 1rem; }
        #mobile-notice p { color: #94a3b8; font-size: 1rem; max-width: 400px; line-height: 1.6; }
        
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }
        body { overflow: hidden !important; }
    }
</style>

<div id="mobile-notice">
    <i class='bx bx-desktop'></i>
    <h2>Desktop View Only</h2>
    
    <?php if(isset($_SESSION['firstname'])): ?>
        <h3>Hi, <?php echo htmlspecialchars($_SESSION['firstname']); ?>! 👋</h3>
    <?php endif; ?>

    <p>Pasensya na! 😅 Ang <strong>SmartStudy</strong> ay naka-optimize muna para sa Desktop at Laptop para sa best experience.</p>
    <p style="font-size: 0.9rem; margin-top: 1rem; opacity: 0.7;">📱 Mobile responsive version coming soon! 🚀</p>
</div>