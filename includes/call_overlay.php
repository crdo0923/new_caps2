<link rel="stylesheet" href="css/messaging.css"> 

<?php if(isset($_SESSION['user_id'])): ?>
    <input type="hidden" id="currentUserId" value="<?php echo $_SESSION['user_id']; ?>">
<?php endif; ?>

<div id="callModal" class="modal-overlay" style="background:rgba(0,0,0,0.95); z-index:2020; display: none;">
    <div class="modal-content" style="background:transparent; border:none; display:flex; flex-direction:column; align-items:center; width:100%; height:100%; max-width: none;">
        <button id="btnMinimizeCall" title="Minimize Call"
            style="position: absolute; top: 20px; left: 20px; z-index: 100; 
                   background: rgba(255, 255, 255, 0.15); backdrop-filter: blur(5px);
                   border: 1px solid rgba(255,255,255,0.2);
                   border-radius: 50%; width: 45px; height: 45px; 
                   cursor: pointer; color: white; font-size: 1.2rem;
                   display: flex; align-items: center; justify-content: center;
                   transition: background 0.2s;">
            <i class="bx bx-chevron-down-left"></i>
        </button>

        <div id="videoContainer" style="position:relative; width:100%; max-width:1000px; height:80%; display:flex; align-items:center; justify-content:center; background:#000; border-radius:15px; overflow:hidden; margin-top: 20px;">
            <p id="callStatus" style="position:absolute; color:white; z-index:10; background:rgba(0,0,0,0.5); padding:5px 10px; border-radius:5px;">Initializing...</p>
            <video id="remoteVideo" autoplay playsinline style="width:100%; height:100%; object-fit:cover;"></video>
            <video id="localVideo" autoplay playsinline muted style="position:absolute; bottom:20px; right:20px; width:180px; height:120px; object-fit:cover; border-radius:8px; border:2px solid white; z-index:20; background:#1e293b;"></video>
            <div id="audioOnlyUI" style="position:absolute; z-index:5; text-align:center; display:none;">
                <div id="audioAvatar" style="width:150px; height:150px; border-radius:50%; border:4px solid white; overflow:hidden; margin-bottom:20px;"></div>
                <h2 id="audioName" style="color:white; text-shadow:0 2px 5px rgba(0,0,0,0.8);">User</h2>
            </div>
        </div>

        <div class="call-controls" style="display:flex; gap:30px; margin-top:30px; z-index: 9999; position: relative;">
            <button id="btnToggleMic" style="width:60px; height:60px; border-radius:50%; border:none; background:#334155; color:white; cursor:pointer; font-size:1.5rem;"><i class="bx bx-microphone"></i></button>
            <button id="btnToggleCam" style="width:60px; height:60px; border-radius:50%; border:none; background:#334155; color:white; cursor:pointer; font-size:1.5rem;"><i class="bx bx-camera"></i></button>
            <button id="endCallBtn" style="width:70px; height:70px; border-radius:50%; border:none; background:#ef4444; color:white; cursor:pointer; font-size:2rem; box-shadow: 0 0 20px rgba(239, 68, 68, 0.5);"><i class="bx bx-phone-call"></i></button>
        </div>
    </div>
</div>

<div id="outgoingCallUI" class="modal-overlay" style="z-index: 3000; background: black; display: none;">
    <div class="call-ui-content" style="display:flex; flex-direction:column; align-items:center; justify-content:center; height:100%; width:100%;">
        <div id="outgoingAvatar" style="width: 120px; height: 120px; border-radius: 50%; overflow: hidden; border: 2px solid #333; margin-bottom: 20px;"></div>
        <h2 id="outgoingName" style="color: white; font-size: 1.5rem; font-weight: 600;">User</h2>
        <p style="color: #cbd5e1; margin-top: 5px; animation: pulse 1.5s infinite;">Calling...</p>
        <div style="margin-top: 50px;">
            <button id="btnCancelOutgoing" style="background: #ef4444; width: 60px; height: 60px; border-radius: 50%; border: none; color: white; font-size: 1.5rem; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 15px rgba(239, 68, 68, 0.4);"><i class="bx bx-phone-call"></i></button> 
        </div>
    </div>
</div>

<div id="incomingCallUI" class="modal-overlay" style="z-index: 3100; display: none; background: rgba(0,0,0,0.7);">
    <div class="incoming-card" style="background: #242526; width: 350px; padding: 30px; border-radius: 15px; text-align: center; box-shadow: 0 10px 40px rgba(0,0,0,0.5); position: relative;">
        <button id="btnCloseIncoming" style="position: absolute; top: 15px; right: 15px; background: rgba(255,255,255,0.1); border: none; color: #ccc; border-radius: 50%; width: 30px; height: 30px; cursor: pointer;">&times;</button>
        <div id="incomingAvatar" style="width: 80px; height: 80px; border-radius: 50%; overflow: hidden; margin: 0 auto 15px; border: 2px solid #3b82f6;"></div>
        <h3 id="incomingName" style="color: white; font-size: 1.3rem; margin-bottom: 5px;">User</h3>
        <p style="color: #e4e6eb; font-weight: 500; font-size: 1.1rem;">is calling you</p>
        <p style="color: #b0b3b8; font-size: 0.8rem; margin-top: 5px;"><i class="bx bx-lock" style="margin-right:8px;"></i>End-to-end encrypted</p>
        <div style="display: flex; justify-content: center; gap: 40px; margin-top: 30px;">
            <div style="text-align: center;">
                <button id="btnDeclineCall" style="background: #ef4444; width: 60px; height: 60px; border-radius: 50%; border: none; color: white; font-size: 1.5rem; cursor: pointer; display: flex; align-items: center; justify-content: center; margin-bottom: 8px;"><i class="bx bx-x"></i></button>
                <span style="color: #b0b3b8; font-size: 0.8rem;">Decline</span>
            </div>
            <div style="text-align: center;">
                <button id="btnAcceptCall" style="background: #22c55e; width: 60px; height: 60px; border-radius: 50%; border: none; color: white; font-size: 1.5rem; cursor: pointer; display: flex; align-items: center; justify-content: center; margin-bottom: 8px;"><i class="bx bx-phone"></i></button>
                <span style="color: #b0b3b8; font-size: 0.8rem;">Accept</span>
            </div>
        </div>
    </div>
</div>

<div id="callStatusModal" class="modal-overlay" style="z-index: 5000; display: none;">
    <div class="modal-content" style="max-width: 350px; text-align: center;">
        <div style="font-size: 3rem;" id="statusIcon"><i class="bx bx-phone" style="font-size:3rem"></i></div>
        <h3 id="statusTitle">Call Ended</h3>
        <p id="statusMessage">The call was terminated.</p>
        <button id="closeStatusModal" class="modal-option" style="background: var(--primary-color); color:white; margin-top: 15px;">Close</button>
    </div>
</div>

<button id="persistentCallBtn" 
        style="position: fixed; top: 80px; right: 20px; z-index: 10000; 
               background: #10b981; color: white; padding: 10px 20px; 
               border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; 
               border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.5); 
               display: none; animation: pulseGreen 1.5s infinite;">
    <i class="bx bx-phone"></i> Back to Call
</button>

<script src="https://unpkg.com/peerjs@1.5.2/dist/peerjs.min.js"></script>
<script src="js/messaging.js?v=<?php echo time(); ?>"></script>

<script src="js/spa_navigation.js?v=<?php echo time(); ?>"></script>
<?php
    // Add gamer-tag overlay to most pages (skip messaging which explicitly disables it)
    $current_file = basename($_SERVER['PHP_SELF']);
    if ($current_file !== 'messaging.php' && file_exists(__DIR__ . '/gametag_overlay.php')) {
        include_once __DIR__ . '/gametag_overlay.php';
    }
?>
<div id="leaveCallModal" class="modal-overlay" style="z-index: 20000; display: none; backdrop-filter: blur(5px);">
    <div class="modal-content" style="max-width: 400px; text-align: center; background: #1e293b; border: 1px solid #334155; border-radius: 16px; box-shadow: 0 20px 50px rgba(0,0,0,0.5); padding: 30px;">
        
        <div style="width: 70px; height: 70px; background: rgba(239, 68, 68, 0.15); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
            <span style="font-size: 2rem;"><i class="bx bx-error" style="font-size:2rem"></i></span>
        </div>

        <h3 style="color: white; font-size: 1.5rem; margin-bottom: 10px; font-weight: 600;">End Active Call?</h3>
        <p style="color: #94a3b8; font-size: 0.95rem; line-height: 1.5; margin-bottom: 30px;">
            You are currently in a call. Leaving this page will <strong>disconnect</strong> you immediately.
        </p>

        <div style="display: flex; gap: 15px; justify-content: center;">
            <button id="btnStayInCall" style="flex: 1; padding: 12px; border-radius: 10px; border: 1px solid #475569; background: transparent; color: white; font-weight: 600; cursor: pointer; transition: all 0.2s;">
                Stay in Call
            </button>
            <button id="btnLeaveCall" style="flex: 1; padding: 12px; border-radius: 10px; border: none; background: #ef4444; color: white; font-weight: 600; cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);">
                End & Leave
            </button>
        </div>
    </div>
</div>

<!-- GLOBAL CUSTOM CONFIRM (site-wide) -->
<style>
    /* Custom confirm modal - prettier, animated */
    #customConfirmModal .modal-content { transform: translateY(8px) scale(0.98); opacity: 0; transition: transform 220ms cubic-bezier(.2,.9,.2,1), opacity 200ms ease; }
    #customConfirmModal.show .modal-content { transform: translateY(0) scale(1); opacity: 1; }
    #customConfirmModal .confirm-icon { width:64px; height:64px; border-radius:16px; display:flex; align-items:center; justify-content:center; margin:0 auto 10px; font-size:28px; background: linear-gradient(90deg,#ef4444,#ec4899); color:white; box-shadow: 0 8px 30px rgba(236,72,153,0.18); }
    #customConfirmModal .confirm-title { margin:0; color:#f8fafc; font-weight:700; font-size:1.15rem; }
    #customConfirmModal .confirm-message { color:#9aa7c7; margin-top:8px; margin-bottom:14px; }
    #customConfirmModal .btn-cancel { padding:10px 14px; border-radius:10px; background:transparent; color:#cfe0ff; border:1px solid rgba(255,255,255,0.06); }
    #customConfirmModal .btn-yes { padding:10px 14px; border-radius:10px; background:linear-gradient(90deg,#6366f1,#ef4444); color:white; border:none; font-weight:700; box-shadow: 0 8px 24px rgba(99,102,241,0.18); }
    #customConfirmModal .modal-close-x { position: absolute; top:12px; right:12px; width:34px; height:34px; border-radius:8px; background: rgba(255,255,255,0.03); color:#e6eef9; display:flex; align-items:center; justify-content:center; border:1px solid rgba(255,255,255,0.03); cursor:pointer; }
</style>
<div id="customConfirmModal" class="modal-overlay" style="z-index: 12000; display: none; align-items: center; justify-content: center;">
    <div class="modal-content" style="max-width: 460px; background: linear-gradient(180deg,#071028,#0c1420); border-radius: 14px; padding: 20px 22px; border: 1px solid rgba(255,255,255,0.04); box-shadow: 0 18px 50px rgba(2,6,23,0.6); text-align:center; position:relative;">
        <button id="btnCloseCustomConfirm" title="Close" class="modal-close-x">×</button>
        <div class="confirm-icon" id="confirmIcon">⚠️</div>
        <h3 id="confirmTitle" class="confirm-title">Confirm action</h3>
        <p id="confirmMessage" class="confirm-message">Are you sure?</p>
        <div class="modal-options" style="display:flex; gap:12px; justify-content:center; margin-top:6px;">
            <button id="btnCancelConfirm" class="modal-option btn-cancel">Cancel</button>
            <button id="btnYesConfirm" class="modal-option btn-yes">Confirm</button>
        </div>
    </div>
</div>

<!-- GLOBAL LOGOUT MODAL (site-wide) -->
<div id="logoutModal" class="modal-overlay" style="z-index: 12000; display: none; align-items: center; justify-content: center;">
        <div class="logout-modal-content modal-content" style="max-width:420px; padding:20px; background: linear-gradient(180deg,#071025,#0f172a); border-radius: 12px; border:1px solid rgba(255,255,255,0.03); text-align:center;">
                <h3 style="color:#fff; margin-top:0;">Confirm Logout</h3>
                <p style="color:#9aa7c7;">Are you sure you want to logout?</p>
                <div style="display:flex; gap:12px; justify-content:center; margin-top: 16px;">
                        <button id="cancelLogout" class="btn-small">Cancel</button>
                        <button id="confirmLogout" class="btn-add" style="background:#ef4444;">Logout</button>
                </div>
        </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('logoutModal');
        const openBtns = document.querySelectorAll('.openLogoutModal');
        const cancelBtn = document.getElementById('cancelLogout');
        const confirmBtn = document.getElementById('confirmLogout');
        if (openBtns && modal && openBtns.length) {
            openBtns.forEach(function(openBtn){
                openBtn.addEventListener('click', function (e) { e.preventDefault(); modal.style.display = 'flex'; setTimeout(() => modal.classList.add('active'), 10); });
            });
        }
        if (cancelBtn && modal) {
            cancelBtn.addEventListener('click', function (e) { e.preventDefault(); modal.classList.remove('active'); setTimeout(()=> modal.style.display='none', 220); });
        }
        if (confirmBtn) {
            confirmBtn.addEventListener('click', function (e) { e.preventDefault(); window.location.href = 'php/logout.php'; });
        }
    });
</script>