// =========================================
// GLOBAL CONSTANTS & TIMER STATE (SA ITAAS NG FILE)
// =========================================
window.timerInterval = null;
let isTimerRunning = false;
let FOCUS_TIME_SECONDS = 25 * 60; // Default: 25 minutes (in seconds)
let BREAK_TIME_SECONDS = 5 * 60;  // Default: 5 minutes (in seconds)
let timeRemaining = FOCUS_TIME_SECONDS;
let currentMode = 'focus'; 
const CIRCUMFERENCE = 534.07; 
const alarmSound = new Audio('/new_caps/assets/sounds/alarm.wav'); 
let progressCircle = null; 

// [NEW] Para sa Database Saving
const POINTS_PER_MINUTE = 5; // Reward rate: 5 points per minute
let currentStudySubject = 'General Study'; // Default subject

// ========================================
// 1. TIMER CORE LOGIC & DISPLAY (Dapat Ito ang Una)
// ========================================
function updateTimerDisplay() {
    const minutes = Math.floor(timeRemaining / 60);
    const seconds = timeRemaining % 60;
    
    // 1. Update the display elements
    document.querySelector('#timerMinutes').textContent = String(minutes).padStart(2, '0');
    document.querySelector('#timerSeconds').textContent = String(seconds).padStart(2, '0');
    
    // 2. Update the browser tab title
    document.title = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')} - ${currentMode === 'focus' ? 'Focus' : 'Break'}`;

    // 3. Update the progress circle 
    updateProgressCircle(); 

    // 4. Time decrement
    timeRemaining--;

    // 5. Timer Expiry Check (Ang CRITICAL na part)
    if (timeRemaining < 0) {
        timeRemaining = 0; 
        clearInterval(timerInterval);
        isTimerRunning = false;
        
        alarmSound.play(); 

        // SAVING LOGIC 
        if (currentMode === 'focus') {
            const completedMinutes = Math.floor(FOCUS_TIME_SECONDS / 60); 
            if (completedMinutes > 0) { 
                // CRITICAL: Dapat defined ang function na ito bago pa man tumawag!
                saveSessionToDatabase(completedMinutes); 
            }
        }
        
        // MODE SWITCHING LOGIC
        if (currentMode === 'focus') {
            currentMode = 'break';
            timeRemaining = BREAK_TIME_SECONDS;
            document.getElementById('focusModeTitle').textContent = '☕ Break Time';
            document.querySelector('.focus-mode-description').textContent = 'Time to relax and recharge.';
        } else { // Katatapos lang ng break
            currentMode = 'focus';
            timeRemaining = FOCUS_TIME_SECONDS;
            document.getElementById('focusModeTitle').textContent = '🎯 Focus Mode';
            document.querySelector('.focus-mode-description').textContent = 'Time to concentrate on your tasks.';
        }

        // I-update ulit ang display para sa bagong mode (e.g., 5:00)
        updateTimerDisplay(); // Para ma-update agad ang display sa bagong oras
        
        return; 
    }
}

// Export SPA teardown for dashboard — clear timers and cleanup
window.spaTeardown = function() {
    try { if (window.timerInterval) { clearInterval(window.timerInterval); window.timerInterval = null; } } catch(e) {}
    try { if (typeof stopFocusMode === 'function') stopFocusMode(false); } catch(e) {}
};
// ========================================
// 2. TIMER CONTROL FUNCTIONS (Ang Missing Piece)
// ========================================

function startTimer() {
    console.log('E: startTimer() called. Starting interval...'); 
    isTimerRunning = true;
    // I-update ang button text at icon
    document.getElementById('startFocus').innerHTML = '<span class="btn-icon">⏸️</span> Pause Focus'; 
    document.getElementById('startFocus').classList.add('btn-focus-active');

    // I-clear muna ang previous interval
    if (timerInterval) clearInterval(timerInterval);

    // I-set ang bagong interval
    window.timerInterval = setInterval(updateTimerDisplay, 1000); 
    updateTimerDisplay(); // Initial display update
}

function stopTimer() {
    isTimerRunning = false;
    if (window.timerInterval) { clearInterval(window.timerInterval); window.timerInterval = null; }
    // Ibalik ang button sa Resume
    document.getElementById('startFocus').innerHTML = '<span class="btn-icon">▶️</span> Resume Focus'; 
    document.getElementById('startFocus').classList.remove('btn-focus-active');
}

function resetTimer() {
    clearInterval(timerInterval);
    isTimerRunning = false;
    
    // I-reset ang timeRemaining sa default
    timeRemaining = (currentMode === 'focus') ? FOCUS_TIME_SECONDS : BREAK_TIME_SECONDS;
    
    // Ibalik ang button sa Start
    document.getElementById('startFocus').innerHTML = '<span class="btn-icon">▶️</span> Start Focus'; 
    document.getElementById('startFocus').classList.remove('btn-focus-active');

    // I-update ang Focus Mode Title at Description
    document.getElementById('focusModeTitle').textContent = (currentMode === 'focus') ? '🎯 Focus Mode' : '☕ Break Time';
    document.querySelector('.focus-mode-description').textContent = (currentMode === 'focus') ? 'Time to concentrate on your tasks.' : 'Time to relax and recharge.';
    
    updateTimerDisplay();
}
// ========================================
// 3. PROGRESS CIRCLE (Kasama rin sa Taas)
// ========================================
function updateProgressCircle() {
    // Kinukuha ang progressCircle element sa loob ng DOMContentLoaded block
    if (!progressCircle) return;
    
    let totalTime = (currentMode === 'focus') ? FOCUS_TIME_SECONDS : BREAK_TIME_SECONDS;
    
    if (totalTime === 0) return;
    
    let dashoffset = (timeRemaining / totalTime) * CIRCUMFERENCE;
    progressCircle.style.strokeDashoffset = dashoffset;
}

// ========================================
// 4. DATABASE INTEGRATION FUNCTION 
// ========================================
async function saveSessionToDatabase(durationMinutes) {
    const pointsEarned = durationMinutes * POINTS_PER_MINUTE;
    const subject = currentStudySubject;
    
    if (durationMinutes < 1) return; 

    try {
        const response = await fetch('save_session.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                duration: durationMinutes,
                points: pointsEarned,
                subject: subject
            })
        });

        const result = await response.json();

        if (result.success) {
            console.log('Session Saved:', result.message);
            if (typeof showToast !== 'undefined') {
                showToast('Study Complete! 🎉', result.message, 'success');
            }
        } else {
            console.error('Save Failed:', result.message);
            if (typeof showToast !== 'undefined') {
                showToast('Error', result.message, 'error');
            }
        }
    } catch (error) {
        console.error('Error saving session:', error);
        if (typeof showToast !== 'undefined') {
            showToast('Network Error', 'Could not connect to server to save session.', 'error');
        }
    }
}

// ========================================
// DOM CONTENT LOADED - INITIALIZATION
// ========================================
document.addEventListener('DOMContentLoaded', function() {
    // Navigation click handler (Yung dating code mo, intact)
    const navItems = document.querySelectorAll('.nav-item[data-section]');
    const sections = document.querySelectorAll('.content-section');

    navItems.forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();

            // FIX: Ang subject input listener ay dapat nasa labas ng loop na ito.
            
            // Remove active class from all nav items
            navItems.forEach(nav => nav.classList.remove('active'));

            // Add active class to clicked item
            this.classList.add('active');

            // Hide all sections
            sections.forEach(section => section.classList.remove('active'));

            // Show selected section
            const sectionId = this.getAttribute('data-section') + '-section';
            const targetSection = document.getElementById(sectionId);
            if (targetSection) {
                targetSection.classList.add('active');
            }
            if (typeof togglePersistentCallButton === 'function') {
        togglePersistentCallButton();
    }
        });
    });
        // ... (Nandito ang navigation logic mo na nagtatapos sa: }); )

// ========================================
// FOCUS MODE INITIALIZATION (DITO MO ILALAGAY)
// ========================================
const startFocusButton = document.getElementById('startFocus');
const resetFocusButton = document.getElementById('resetFocus'); 
const focusSubjectInput = document.getElementById('focusSubject'); 

// 1. Hook sa Start/Pause Button
if (startFocusButton) {
    startFocusButton.addEventListener('click', function() {
        if (isTimerRunning) {
            stopTimer();
        } else {
            startTimer();
        }
    });
}

// 2. Hook sa Reset Button
if (resetFocusButton) {
    resetFocusButton.addEventListener('click', resetTimer);
}

// 3. Subject Input Listener
if (focusSubjectInput) {
    focusSubjectInput.addEventListener('input', function() {
        currentStudySubject = this.value.trim() || 'General Study';
    });
}

// Initialize core features
// initFocusMode(); // Kung may initFocusMode ka, ilagay rito
initScheduleGenerator();

// Initial call para lumabas ang tamang oras (25:00) at circle
updateTimerDisplay();

    // I-initialize ang progressCircle element
    progressCircle = document.querySelector('.timer-ring circle:nth-child(2)');
// updateProgressCircle(); // Kung mayroon kang updateProgressCircle() function
    

    // ===============================================
    // 1. LOGOUT MODAL LOGIC (New)
    // ===============================================
    const logoutButton = document.getElementById('logoutButton');
    const logoutModal = document.getElementById('logoutModal');
    const cancelLogoutButton = document.getElementById('cancelLogout');

    // Show the modal when the sidebar logout button is clicked
    if (logoutButton) {
        logoutButton.addEventListener('click', function(e) {
            e.preventDefault();
            if (logoutModal) {
                logoutModal.classList.add('active');
            }
        });
    }

    // Hide the modal when the Cancel button is clicked
    if (cancelLogoutButton) {
        cancelLogoutButton.addEventListener('click', function() {
            if (logoutModal) {
                logoutModal.classList.remove('active');
            }
        });
    }

    // Hide the modal when clicking outside the modal content
    if (logoutModal) {
        logoutModal.addEventListener('click', function(e) {
            // Check if the click occurred directly on the overlay, not the content
            if (e.target === logoutModal) {
                logoutModal.classList.remove('active');
            }
        });
    }

    // ===============================================
    // 2. CHART.JS INITIALIZATION (New)
    // ===============================================
    var ctx = document.getElementById('weeklyChart');
    if (ctx) {
        // Tiyakin na naka-load ang Chart.js bago gamitin
        if (typeof Chart !== 'undefined') {
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                    datasets: [{
                        label: 'Study Time (Hours)',
                        data: [3, 4.5, 2, 5, 3.5, 7, 6], // Example data
                        backgroundColor: 'rgba(99, 102, 241, 0.7)',
                        borderColor: '#6366f1',
                        borderWidth: 1,
                        borderRadius: 4, // Added for modern look
                        hoverBackgroundColor: '#8b5cf6'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(148, 163, 184, 0.1)', // Dark mode grid line color
                            },
                            ticks: {
                                color: '#94a3b8' // Dark mode tick color
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                color: '#94a3b8'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            labels: {
                                color: '#f1f5f9' // Dark mode legend color
                            }
                        }
                    }
                }
            });
        } else {
             console.warn('Chart.js not loaded. Cannot initialize weekly chart.');
        }
    }


    // INITIAL LOADING STATE HANDLING (restored)
    const pageLoader = document.querySelector('.page-loader');
    if (pageLoader) {
        setTimeout(() => {
            // Use hidden class to gracefully hide the loader
            pageLoader.classList.add('hidden');
        }, 500); // show loader for at least 0.5s
    }

    // 🔔 Request Notification permission agad sa start
    if (Notification.permission !== 'denied') {
        Notification.requestPermission();
    }
    
    updateDashboardStats(); // Start any continuous updates
if (typeof togglePersistentCallButton === 'function') {
        togglePersistentCallButton();
    }
});

// ========================================
// SCHEDULE GENERATOR / QUICK ADD TASK
// ========================================
function initScheduleGenerator() {
    const scheduleForm = document.getElementById('quickAddForm'); 
    if (!scheduleForm) return;

    scheduleForm.addEventListener('submit', function(e) {
        e.preventDefault();
        generateScheduleFromQuickAdd(); // Use dedicated function
    });
}

async function generateScheduleFromQuickAdd() {
    // quickAdd form uses id="taskInput" in the UI
    // quickAdd form uses id="taskInput" in the UI
    const taskNameEl = document.getElementById('taskInput') || document.getElementById('taskName');
    const taskName = (taskNameEl ? taskNameEl.value : '').trim();
    const taskSubject = document.getElementById('taskSubject').value;
    const taskPriority = document.getElementById('taskPriority').value;

    if (taskName.length === 0 || taskSubject.length === 0) {
        showNotification('Please fill out the task name and select a subject', 'error');
        return;
    }

    // if the user chose Auto Detect, attempt to detect the subject from the task name
    let chosenSubject = taskSubject;
    if (chosenSubject === 'Auto Detect' && typeof window.detectSubjectFromText === 'function') {
        chosenSubject = detectSubjectFromText(taskName);
        // if heuristics ambiguous (Other or General) and ai fallback available, try AI
        if ((chosenSubject === 'Other' || chosenSubject === 'General') && typeof window.aiDetectSubject === 'function') {
            showNotification('Auto Detect ambiguous — asking AI to guess subject...', 'info');
            try { const aiSub = await window.aiDetectSubject(taskName); if (aiSub) chosenSubject = aiSub; } catch (e) { console.error('ai detect failed', e); }
        }

        // Show confirmation UI so user can confirm or edit detected subject
        const confirmRoot = document.getElementById('detectedSubjectConfirm');
        const sel = document.getElementById('detectedSubjectSelectConfirm');
        if (confirmRoot && sel) {
            // Populate select with options from main subject list
            const mainSelect = document.getElementById('taskSubject');
            sel.innerHTML = '';
            for (const opt of mainSelect.options) {
                if (opt.value === 'Auto Detect') continue;
                const o = document.createElement('option'); o.value = opt.value; o.text = opt.text; sel.appendChild(o);
            }
            // add detected subject if not present
            let found = Array.from(sel.options).some(o => o.value.toLowerCase() === chosenSubject.toLowerCase());
            if (!found) { const o = document.createElement('option'); o.value = chosenSubject; o.text = chosenSubject; sel.appendChild(o); }
            sel.value = chosenSubject;
            confirmRoot.style.display = 'block';

            // Bind actions
            const confirmBtn = document.getElementById('confirmDetectedSaveBtn');
            const cancelBtn = document.getElementById('cancelDetectedBtn');
            const finishSave = () => {
                // hide confirm UI and save with currently selected subject
                chosenSubject = sel.value;
                confirmRoot.style.display = 'none';
                doSave();
            };
            const cancelSave = () => { confirmRoot.style.display = 'none'; showNotification('Add cancelled.', 'info'); };
            confirmBtn.onclick = finishSave; cancelBtn.onclick = cancelSave;
            return; // wait for user to confirm
        }
    }

    const newItem = {
        time: new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }),
        subject: chosenSubject,
        task: taskName,
        duration: '2 hours (Estimate)',
        priority: taskPriority
    };

    showNotification(`🤖 AI is integrating "${taskName}" into your schedule...`, 'info');

    function doSave() {
        setTimeout(() => {
            insertNewScheduleItem(newItem);
            if (taskNameEl) taskNameEl.value = ''; // Clear input
            const subjEl = document.getElementById('taskSubject'); if (subjEl) subjEl.selectedIndex = 0;
            const prioEl = document.getElementById('taskPriority'); if (prioEl) prioEl.value = 'Medium';
            // Persist to server
            try {
                const fd = new FormData();
                fd.append('title', taskName);
                fd.append('description', '');
                fd.append('subject', chosenSubject);
                fd.append('priority', taskPriority);
                fd.append('time', newItem.time);
                fd.append('duration', newItem.duration);

                fetch('ajax_save_task.php', { method: 'POST', body: fd })
                    .then(async (r) => {
                        const txt = await r.text();
                        try { return JSON.parse(txt); } catch(e) { console.warn('ajax_save_task non-JSON response', txt); return { status: 'error', message: 'Server returned non-JSON', _raw: txt }; }
                    })
                    .then(resp => {
                        if (resp.status !== 'success') {
                            console.warn('Quick add save failed:', resp);
                        } else {
                            // Assign server task id to the newly inserted DOM element and add action buttons
                            try {
                                const scheduleList = document.querySelector('.schedule-list');
                                const first = scheduleList ? scheduleList.firstElementChild : null;
                                if (first) {
                                    const tid = resp.task_id;
                                    first.id = 'task-' + tid;

                                    // add actions container (start/edit/delete) similar to server-rendered items
                                    const actions = document.createElement('div');
                                    actions.className = 'task-actions';
                                    actions.style.display = 'flex'; actions.style.alignItems = 'center'; actions.style.gap = '10px';

                                    const startBtn = document.createElement('button');
                                    startBtn.title = 'Start Focus';
                                    startBtn.style.cssText = "background:#6366f1;color:white;border:none;padding:10px 20px;border-radius:10px;cursor:pointer;font-weight:600;display:flex;align-items:center;gap:6px;";
                                    startBtn.innerHTML = "<i class='bx bx-play' style='font-size:1.4rem;'></i>";
                                    startBtn.addEventListener('click', () => startFocusMode(newItem.duration || null, tid, newItem.task));

                                    const editBtn = document.createElement('button');
                                    editBtn.title = 'Edit';
                                    editBtn.style.cssText = "background:rgba(255,255,255,0.05);border:1px solid #334155;color:#94a3b8;width:45px;height:45px;border-radius:10px;cursor:pointer;display:flex;align-items:center;justify-content:center;";
                                    editBtn.innerHTML = "<i class='bx bx-edit-alt' style='font-size:1.4rem;'></i>";
                                    editBtn.addEventListener('click', () => openEditTaskModal(tid, newItem.task.replace(/"/g, '&quot;'), ''));

                                    const delBtn = document.createElement('button');
                                    delBtn.title = 'Delete';
                                    delBtn.style.cssText = "background:rgba(239,68,68,0.1);border:1px solid #ef4444;color:#ef4444;width:45px;height:45px;border-radius:10px;cursor:pointer;display:flex;align-items:center;justify-content:center;";
                                    delBtn.innerHTML = "<i class='bx bx-trash' style='font-size:1.4rem;'></i>";
                                    delBtn.addEventListener('click', () => confirmDeleteTask(tid));

                                    actions.appendChild(startBtn); actions.appendChild(editBtn); actions.appendChild(delBtn);

                                    // append actions to the new element
                                    first.appendChild(actions);
                                }
                            } catch (e) { console.warn('Could not attach actions to quick-add item', e); }
                        }
                    })
                    .catch(err => console.error('Quick add fetch error:', err));
            } catch (e) { console.error(e); }

            showNotification('✅ Task added and optimized!', 'success');
        }, 1500);
    }

    // If we reached here and confirmation UI wasn't used, just save
    if (!document.getElementById('detectedSubjectConfirm') || document.getElementById('detectedSubjectConfirm').style.display === 'none') {
        doSave();
    }
}

function insertNewScheduleItem(item) {
    const scheduleList = document.querySelector('.schedule-list');
    if (!scheduleList) return;

    const priorityClass = item.priority === 'Urgent' ? 'priority-urgent' : item.priority === 'High' ? 'priority-high' : '';
    const priorityBadgeClass = item.priority === 'Urgent' ? 'urgent' : item.priority === 'High' ? 'high' : 'normal';
    const priorityIcon = item.priority === 'Urgent' ? '🔴' : '🟡'; // Simplified icons

    const html = `
        <div class="schedule-item ${priorityClass} new-task-animated">
            <div class="schedule-indicator ${priorityBadgeClass}"></div>
            <div class="schedule-time-block">
                <span class="time-main">${item.time}</span>
                <span class="time-duration">${item.duration}</span>
            </div>
            <div class="schedule-content">
                <div class="schedule-header-row">
                    <h4>${item.task} (${item.subject})</h4>
                    <span class="priority-badge ${priorityBadgeClass}">
                        <span class="badge-icon">${priorityIcon}</span>
                        ${item.priority.toUpperCase()}
                    </span>
                </div>
                <p class="schedule-desc">AI recommended slot based on focus time.</p>
                <div class="schedule-meta">
                    <span class="meta-tag">🧠 New Task</span>
                </div>
            </div>
            <button class="btn-start-task">Start</button>
        </div>
    `;

    scheduleList.insertAdjacentHTML('afterbegin', html);
    // Wire up the start button on the newly inserted element so it can start focus mode
    try {
        const newEl = scheduleList.firstElementChild;
        if (newEl) {
            const startBtn = newEl.querySelector('.btn-start-task');
            if (startBtn) startBtn.addEventListener('click', () => startFocusMode(null, null, item.task));
        }
    } catch (e) { console.warn('Failed to bind start button on new schedule item', e); }
}

// ========================================
// FOCUS MODE / POMODORO TIMER
// ========================================
function getSelectedTime(mode) {
    const selector = mode === 'focus' ? 'input[name="study-time"]:checked' : 'input[name="break-time"]:checked';
    const selectedRadio = document.querySelector(selector);

    if (!selectedRadio) return mode === 'focus' ? 25 * 60 : 5 * 60; // Fallback to default (in seconds)

    if (selectedRadio.value === 'custom') {
        const customInputId = mode === 'focus' ? 'customStudyTime' : 'customBreakTime';
        // Naka-minutes ang value sa custom input
        const customValue = parseInt(document.getElementById(customInputId)?.value ?? 0, 10); 
        return isNaN(customValue) || customValue < 1 ? (mode === 'focus' ? 25 : 5) * 60 : customValue * 60;
    }

    // Assumes data-time is in MINUTES
    const standardTimeMinutes = selectedRadio.getAttribute('data-time') ?? '0'; 
    return parseInt(standardTimeMinutes, 10) * 60; // Convert minutes to seconds
}

function updateFocusConstants() {
    FOCUS_TIME_SECONDS = getSelectedTime('focus');
    BREAK_TIME_SECONDS = getSelectedTime('break');
}

function initFocusMode() {
    const startButton = document.getElementById('startFocus');
    const resetButton = document.getElementById('resetFocus'); 
    const timeOptions = document.querySelectorAll('.timer-settings input[type="radio"]');

    if (!startButton) return;
    
    // 🚨 Initialization ng global progressCircle variable DITO
    progressCircle = document.querySelector('.timer-progress');
    if (progressCircle) {
        progressCircle.style.strokeDasharray = CIRCUMFERENCE;
    }

    // 1. Setup Time Option Listeners (Handles standard and custom toggle)
    timeOptions.forEach(radio => {
        radio.addEventListener('change', function() {
            updateFocusConstants();
            
            if (!isTimerRunning) {
                resetFocusTimer(this.name === 'study-time' ? 'focus' : 'break'); 
            }
            
            // Handle Custom Input Visibility (CRITICAL LOGIC)
            const isCustom = this.value === 'custom';
            const inputId = this.name === 'study-time' ? 'customStudyTime' : 'customBreakTime';
            const customInput = document.getElementById(inputId);
            
            if (customInput) {
                customInput.style.display = isCustom ? 'block' : 'none'; 
            }
            
            // Ensure the other custom input is hidden when selecting a radio option for the current group
            if (!isCustom) {
                const otherInputId = this.name === 'study-time' ? 'customBreakTime' : 'customStudyTime';
                const otherCustomInput = document.getElementById(otherInputId);
                if(otherCustomInput) {
                     // Hide only if the other group's custom radio is NOT selected
                     if(document.getElementById(otherInputId)?.style.display === 'block' && this.name !== (otherInputId.includes('Study') ? 'study-time' : 'break-time')) {
                         // Pass. Let the other radio group handle its own visibility.
                } else if(this.name.includes('study-time')) {
                // When selecting a study time, ensure break time custom is hidden if a standard study time is picked
                const cb = document.getElementById('customBreakTime'); if (cb) cb.style.display = 'none';
            } else {
                // When selecting a break time, ensure study time custom is hidden if a standard break time is picked
                const cs = document.getElementById('customStudyTime'); if (cs) cs.style.display = 'none';
                     }
                }
            }
        });
    });

    // 2. Setup Custom Input Listeners 
    document.getElementById('customStudyTime')?.addEventListener('input', () => {
        if (document.getElementById('customStudyToggle')?.checked && !isTimerRunning) { 
            updateFocusConstants();
            resetFocusTimer('focus');
        }
    });
    document.getElementById('customBreakTime')?.addEventListener('input', () => {
        if (document.getElementById('customBreakToggle')?.checked && !isTimerRunning) {
            updateFocusConstants();
            resetFocusTimer('break');
        }
    });


    // 3. Setup Controls
    startButton.addEventListener('click', toggleFocusMode);

    if (resetButton) {
        resetButton.addEventListener('click', () => resetFocusTimer('focus'));
    }

    // 4. Initial Update (Important for display)
    updateFocusConstants(); 
    updateTimerDisplay();
    updateTimerProgress();
    updateFocusModeTitle();
    
    // Initial Custom Input Visibility Fix (From user's new block, cleaned)
    const cst = document.getElementById('customStudyTime'); const cstToggle = document.getElementById('customStudyToggle'); if (cst) cst.style.display = (cstToggle && cstToggle.checked) ? 'block' : 'none';
    const cbt = document.getElementById('customBreakTime'); const cbtToggle = document.getElementById('customBreakToggle'); if (cbt) cbt.style.display = (cbtToggle && cbtToggle.checked) ? 'block' : 'none';
}

function toggleFocusMode() {
    const startButton = document.getElementById('startFocus');

    // Disable settings only when the timer is RUNNING (opposite of !isTimerRunning)
    document.querySelectorAll('.timer-settings input').forEach(input => input.disabled = isTimerRunning);

    if (!isTimerRunning) {
        updateFocusConstants(); 
        const totalTime = currentMode === 'focus' ? FOCUS_TIME_SECONDS : BREAK_TIME_SECONDS;
        
        if (timeRemaining <= 0 || timeRemaining === totalTime) {
            timeRemaining = totalTime; 
        }

        startFocusTimer();
        startButton.innerHTML = '<span class="btn-icon">⏸️</span> Pause Session'; 
        startButton.style.background = 'linear-gradient(135deg, #ef4444, #dc2626)';
        showNotification(`Starting ${currentMode} session!`, 'info');
    } else {
        stopFocusTimer();
        startButton.innerHTML = '<span class="btn-icon">▶️</span> Resume Session'; 
        startButton.style.background = 'linear-gradient(135deg, #f59e0b, #eab308)'; 
        showNotification(`${currentMode} session paused.`, 'warning');
    }
}

function startFocusTimer() {
    isTimerRunning = true;
    
    window.timerInterval = setInterval(() => {
        if (timeRemaining <= 0) {
            notifySessionComplete(); 
            return;
        }
        
        timeRemaining--;
        updateTimerDisplay();
        updateTimerProgress();
    }, 1000);
}

function stopFocusTimer() {
    isTimerRunning = false;
    clearInterval(timerInterval);
    document.querySelectorAll('.timer-settings input').forEach(input => input.disabled = false);
}

function resetFocusTimer(mode = 'focus') {
    stopFocusTimer();
    currentMode = mode;
    updateFocusConstants(); 
    timeRemaining = mode === 'focus' ? FOCUS_TIME_SECONDS : BREAK_TIME_SECONDS;
    updateTimerDisplay();
    updateTimerProgress();
    updateFocusModeTitle();

    const startButton = document.getElementById('startFocus');
    startButton.innerHTML = '<span class="btn-icon">▶️</span> Start Focus Session';
    startButton.style.background = 'linear-gradient(135deg, #6366f1, #8b5cf6)';
    
    document.querySelectorAll('.timer-settings input').forEach(input => input.disabled = false);
}

function updateTimerDisplay() {
    const minutes = Math.floor(timeRemaining / 60);
    const seconds = timeRemaining % 60;
    
    document.querySelector('#timerMinutes').textContent = String(minutes).padStart(2, '0');
    document.querySelector('#timerSeconds').textContent = String(seconds).padStart(2, '0');
    
    document.title = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')} - ${currentMode === 'focus' ? 'Focus' : 'Break'}`;
    
    // =======================================================
    // TIMER LOGIC (Ito ang idadagdag/i-a-update mo)
    // =======================================================
    timeRemaining--; // Bawasan ang natitirang oras

    if (timeRemaining < 0) {
        timeRemaining = 0; // Tiyakin na hindi magiging negative
        clearInterval(timerInterval);
        isTimerRunning = false;
        
        // Mag-alarm
        alarmSound.play(); 

        // SAVING LOGIC: I-save lang kung Focus Mode ang katatapos
        if (currentMode === 'focus') {
            // Gumamit ng FOCUS_TIME_SECONDS na in-set sa simula
            const completedMinutes = Math.floor(FOCUS_TIME_SECONDS / 60); 
            
            // Tiyakin na nag-aral ng at least 1 minute
            if (completedMinutes > 0) { 
                saveSessionToDatabase(completedMinutes); // <--- ITO ANG CRUCIAL LINE
            }
        }
        
        // MODE SWITCHING LOGIC
        if (currentMode === 'focus') {
            currentMode = 'break';
            timeRemaining = BREAK_TIME_SECONDS;
            document.getElementById('focusModeTitle').textContent = '☕ Break Time';
            document.querySelector('.focus-mode-description').textContent = 'Time to relax and recharge.';
        } else { // Katatapos lang ng break
            currentMode = 'focus';
            timeRemaining = FOCUS_TIME_SECONDS;
            document.getElementById('focusModeTitle').textContent = '🎯 Focus Mode';
            document.querySelector('.focus-mode-description').textContent = 'Time to concentrate on your tasks.';
        }

        // I-update ang display sa bagong oras (para makita agad ang 5:00 o 25:00)
        document.getElementById('timerMinutes').textContent = String(Math.floor(timeRemaining / 60)).padStart(2, '0');
        document.getElementById('timerSeconds').textContent = String(timeRemaining % 60).padStart(2, '0');

        // Note: Kung may Auto-Break logic ka, dapat ilagay mo rin dito ang pag-restart ng timer.
        
        return; // Tapusin ang function para hindi na magpatuloy ang pag-update
    }
}

function updateTimerProgress() {
    // Gumamit ng global variable na progressCircle
    if (!progressCircle) {
        progressCircle = document.querySelector('.timer-progress');
        if (!progressCircle) return;
        progressCircle.style.strokeDasharray = CIRCUMFERENCE; // Set dasharray once
    }

    const totalTime = currentMode === 'focus' ? FOCUS_TIME_SECONDS : BREAK_TIME_SECONDS;
    // I-calculate kung gaano karami ang natitirang dash length
    const progress = (timeRemaining / totalTime) * CIRCUMFERENCE; 

    progressCircle.style.strokeDashoffset = CIRCUMFERENCE - progress; 
}

function updateFocusModeTitle() {
    const titleElement = document.getElementById('focusModeTitle'); 
    const descriptionElement = document.querySelector('.focus-mode-description');
    
    const totalSeconds = currentMode === 'focus' ? FOCUS_TIME_SECONDS : BREAK_TIME_SECONDS;
    const duration = Math.floor(totalSeconds / 60);

    if (titleElement) {
        titleElement.textContent = currentMode === 'focus' 
            ? `🎯 Focus Mode (${duration} min)` 
            : `☕ Break Time (${duration} min)`;
    }

    if (descriptionElement) {
        descriptionElement.textContent = currentMode === 'focus' 
            ? 'Time to concentrate on your tasks.' 
            : 'Take a quick breather, relax your eyes and body!';
    }
}

function playAlarmAndNotify(mode) {
    alarmSound.currentTime = 0; 
    alarmSound.play().catch(e => console.error("Error playing alarm sound:", e)); 

    if (window.navigator && window.navigator.vibrate) {
        window.navigator.vibrate([500, 250, 500, 250, 500]);
    }

    if (Notification.permission === 'granted') {
           const title = mode === 'focus' ? '🎉 SESSION COMPLETE!' : '✅ BREAK IS OVER!';
           const body = mode === 'focus' ? 'Great job! Time for a well-deserved break.' : 'Time to get back to focus mode!';
           new Notification(title, { body: body, icon: '../assets/images/focus-icon.png' }); 
    }
}


function notifySessionComplete() {
    stopFocusTimer(); 
    playAlarmAndNotify(currentMode); 

    const autoBreakToggle = document.getElementById('autoBreakToggle');
    const autoStartNext = autoBreakToggle ? autoBreakToggle.checked : true;

    let nextMode;
    let successMessage;

    if (currentMode === 'focus') {
        successMessage = '🎉 Focus session complete! Time for a break.';
        addCompletedSession(FOCUS_TIME_SECONDS); 
        nextMode = 'break';
    } else if (currentMode === 'break') {
        successMessage = '✅ Break complete! Time to get back to focus mode.';
        nextMode = 'focus';
    }
    
    showNotification(successMessage, 'success');
    
    currentMode = nextMode;
    updateFocusConstants();
    timeRemaining = nextMode === 'focus' ? FOCUS_TIME_SECONDS : BREAK_TIME_SECONDS;
    
    updateTimerDisplay();
    updateTimerProgress();
    updateFocusModeTitle();
    
    const startButton = document.getElementById('startFocus');
    
    if (autoStartNext) {
        setTimeout(() => {
            startButton.innerHTML = '<span class="btn-icon">⏸️</span> Pause Session';
            startButton.style.background = 'linear-gradient(135deg, #ef4444, #dc2626)';
            startFocusTimer();
        }, 1000); 
    } else {
        startButton.innerHTML = `<span class="btn-icon">▶️</span> Start ${nextMode === 'focus' ? 'Focus' : 'Break'} Session`;
        startButton.style.background = 'linear-gradient(135deg, #6366f1, #8b5cf6)';
        document.querySelectorAll('.timer-settings input').forEach(input => input.disabled = false);
    }
}

function addCompletedSession(durationSeconds) {
    const sessionsContainer = document.querySelector('.focus-sessions');
    if (!sessionsContainer) return;

    const now = new Date();
    const timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    const durationMins = durationSeconds / 60; 
    
    const sessionHTML = `
        <div class="session-item completed">
            <span class="session-icon">✅</span>
            <div class="session-details">
                <h4>Study Session</h4>
                <p>${durationMins} minutes • Completed at ${timeStr}</p>
            </div>
        </div>
    `;
    
    sessionsContainer.insertAdjacentHTML('afterbegin', sessionHTML);
}

// ========================================
// HELPER FUNCTIONS 
// ========================================

function formatTime(seconds) {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
}

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    
    let bgColor;
    if (type === 'success') bgColor = '#10b981';
    else if (type === 'error') bgColor = '#ef4444';
    else if (type === 'warning') bgColor = '#f59e0b';
    else bgColor = '#6366f1';
    
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 1rem 1.5rem;
        background: ${bgColor};
        color: white;
        border-radius: 10px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        z-index: 10000;
        animation: slideIn 0.3s ease;
    `;

    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(400px); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(400px); opacity: 0; }
    }
    
    .loading {
        text-align: center;
        padding: 2rem;
        color: var(--text-gray);
        animation: pulse 1.5s ease-in-out infinite;
    }
    
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }
    .new-task-animated {
        opacity: 0;
        transform: translateY(-10px);
        animation: fadeInSlideDown 0.5s ease forwards;
    }
    @keyframes fadeInSlideDown {
        to { opacity: 1; transform: translateY(0); }
    }
    
`;
document.head.appendChild(style);

// ========================================
// STATS UPDATES & CHART INTERACTIONS
// ========================================
// NOTE: Empty interval removed for performance optimization

// NOTE: Itong block na ito ay para lang sa simulated chart interaction. 
// Kung ginagamit mo ang Chart.js canvas sa taas, pwedeng alisin ito.
const chartBars = document.querySelectorAll('.chart-bar');
chartBars.forEach(bar => {
    bar.addEventListener('click', function() {
        chartBars.forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        
        const day = this.querySelector('span').textContent;
        showNotification(`Viewing stats for ${day}`, 'info');
    });
});