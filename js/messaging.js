// ========================================================
// MESSAGING PAGE LOGIC (Final Merged Version - Realtime Updates)
// ========================================================

// --- 1. GLOBAL VARIABLES (Using window object for persistence) ---
// This ensures variables survive SPA page transitions
if (typeof window.galleryImages === 'undefined') {
    window.galleryImages = [];
    window.currentGalleryIndex = 0;
    window.messagePollingInterval = null;
    window.statusPollingInterval = null;
    window.markReadTimeout = null;
    window.callTimerInterval = null;

    // Call State
    window.callStartTime = null;
    window.peer = null;
    window.localStream = null;
    window.currentCall = null;
    window.currentRemotePeerId = null;
    window.isCallInitiator = false;

    // Tracking
    window.currentChatType = null; 
    window.currentChatId = null; 
    window.currentChatName = null;
    window.currentFilter = 'all';
    
    // UI State
    window.contextMenuTargetId = null;
    window.contextMenuTargetType = null;
    window.currentReplyToId = null;
    window.lastChatHTML = '';
    window.addToGroupModal = null;
}

// ========================================================
// 2. HELPER FUNCTIONS (Defined globally)
// ========================================================

// --- Refresh Modal Lists ---
if (!window.refreshModalList) {
    window.refreshModalList = function(type) {
        let containerId = '';
        if (type === 'blocked') containerId = 'blockedListContainer';
        if (type === 'restricted') containerId = 'restrictedListContainer';
        if (type === 'archived') containerId = 'archivedListContainer';
        if (type === 'requests') containerId = 'msgRequestContainer';

        const container = document.getElementById(containerId);
        if (!container) return;

        container.innerHTML = '<div class="empty-state-container" style="padding:20px;"><p style="color:#888;text-align:center;">Loading...</p></div>';

        const formData = new URLSearchParams();
        formData.append('type', type);

        fetch('messaging/ajax_fetch_modal_list.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: formData
        })
        .then(r => r.text())
        .then(html => { container.innerHTML = html; })
        .catch(err => {
            console.error("Error fetching list:", err);
            container.innerHTML = '<p style="color:red;text-align:center;">Error loading data.</p>';
        });
    };
}

// Custom confirm now provided globally in js/main.js (site-wide)

// --- Scroll to Unread ---
window.scrollToUnread = function() {
    const divider = document.querySelector('.unread-divider');
    if (divider) {
        divider.scrollIntoView({ behavior: 'smooth', block: 'center' });
        document.getElementById('btnGoToUnread').style.display = 'none';
    }
};

// --- Delete Message ---
window.deleteMessage = function(msgId, deleteType) {
    const executeDelete = () => {
        const formData = new FormData();
        formData.append('msg_id', msgId);
        formData.append('delete_type', deleteType);

        fetch('messaging/ajax_delete_message.php', { method: 'POST', body: formData })
            .then(response => response.text())
            .then(data => {
                const cleanData = data.trim();
                
                if (cleanData === 'success') {
                    // --- SUCCESS LOGIC ---
                    const previewModal = document.getElementById('imagePreviewModal');
                    if (previewModal) previewModal.style.display = 'none';

                    const bubble = document.querySelector(`.message-bubble[data-msg-id='${msgId}']`);
                    if (bubble) {
                        if (deleteType === 'everyone') {
                            // Update UI to 'Unsent'
                            const wrapper = bubble.closest('.message-wrapper');
                            if(wrapper) wrapper.innerHTML = `<div class='message-bubble outgoing unsent-bubble'><p><i class='bx bx-block'></i> Message unsent</p></div>`;
                            if (typeof window.showToast === "function") window.showToast('Deleted', 'Message unsent for everyone', '<i class="bx bx-trash"></i>', null, 'toast-success');
                        } else {
                            // Hide element
                            const wrapper = bubble.closest('.message-wrapper');
                            if (wrapper) {
                                wrapper.style.transition = 'opacity 0.3s ease, height 0.3s ease';
                                wrapper.style.opacity = '0';
                                wrapper.style.height = '0';
                                setTimeout(() => wrapper.remove(), 300);
                            }
                            if (typeof window.showToast === "function") window.showToast('Deleted', 'Message deleted for you', '<i class="bx bx-trash"></i>', null, 'toast-success');
                        }
                        
                        // 1. Refresh main chat area
                        window.fetchAndDisplayMessages(window.currentChatId, window.currentChatType, false);
                        
                        // [NEW FIX] 2. Refresh Media & Files Panel if Open
                        const mediaContent = document.getElementById('mediaAccordionContent');
                        if (mediaContent && mediaContent.style.display !== 'none') {
                            window.fetchMediaFiles();
                            if (typeof window.showToast === "function") window.showToast('Media Updated', 'Gallery updated successfully.', '<i class="bx bx-refresh"></i>');
                        }
                    }
                } else {
                    // --- ERROR LOGIC ---
                    alert("DELETE FAILED. Server Response: " + cleanData); 
                    if (typeof window.showToast === "function") window.showToast('Error', 'Could not delete message. Check Alert Box for details.', '<i class="bx bx-x-circle"></i>');
                }
            });
    };

    let title = deleteType === 'everyone' ? 'Unsend Message?' : 'Delete Message?';
    let msg = deleteType === 'everyone' ? 'Remove for everyone.' : 'Remove for you only.';
    window.showCustomConfirm(title, msg, executeDelete);
};

// --- Image Preview Logic ---
window.showImagePreview = function(src, clickedElement) {
    const modal = document.getElementById('imagePreviewModal');
    const messageArea = document.getElementById('messageArea');
    const allImages = messageArea.querySelectorAll('.chat-image');

    window.galleryImages = [];
    window.currentGalleryIndex = 0;
    const clickedMsgId = clickedElement.dataset.msgId;

    allImages.forEach((img, index) => {
        if (img.dataset.msgId == clickedMsgId) window.currentGalleryIndex = index;
        window.galleryImages.push({
            src: img.src,
            id: img.dataset.msgId,
            sender: img.dataset.senderId,
            name: img.dataset.filename || 'image.png'
        });
    });

    updateModalContent();
    const menu = document.getElementById('previewMenu');
    if (menu) menu.style.display = 'none';
    modal.style.display = 'flex';
};

window.updateModalContent = function() {
    if (!window.galleryImages || window.galleryImages.length === 0) return;
    const currentItem = window.galleryImages[window.currentGalleryIndex];
    const currentUserId = document.getElementById('currentUserId').value;

    const fullImage = document.getElementById('fullScreenImage');
    const downloadBtn = document.getElementById('downloadImageBtn');
    const filenameEl = document.getElementById('previewFilename');
    const deleteMeBtn = document.getElementById('previewDeleteForMe');
    const deleteAllBtn = document.getElementById('previewDeleteEveryone');
    const prevBtn = document.getElementById('prevImageBtn');
    const nextBtn = document.getElementById('nextImageBtn');

    fullImage.src = currentItem.src;
    if (filenameEl) filenameEl.textContent = currentItem.name;

    if (downloadBtn) {
        downloadBtn.setAttribute('href', currentItem.src);
        downloadBtn.setAttribute('download', currentItem.name);
    }

    if (deleteMeBtn) {
        const newDeleteMe = deleteMeBtn.cloneNode(true);
        deleteMeBtn.parentNode.replaceChild(newDeleteMe, deleteMeBtn);
        newDeleteMe.addEventListener('click', () => {
            window.showCustomConfirm('Delete for yourself?', 'This will delete the message only for you. Continue?', () => {
                document.getElementById('imagePreviewModal').style.display = 'none';
                window.deleteMessage(currentItem.id, 'me');
            });
        });
    }

    if (deleteAllBtn) {
        const newDeleteAll = deleteAllBtn.cloneNode(true);
        deleteAllBtn.parentNode.replaceChild(newDeleteAll, deleteAllBtn);
        if (currentItem.sender == currentUserId) {
            newDeleteAll.style.display = 'block';
            newDeleteAll.addEventListener('click', () => {
                window.showCustomConfirm('Delete for everyone?', 'This will remove the message for everyone in the chat. Continue?', () => {
                    document.getElementById('imagePreviewModal').style.display = 'none';
                    window.deleteMessage(currentItem.id, 'everyone');
                });
            });
        } else {
            newDeleteAll.style.display = 'none';
        }
    }

    if (window.galleryImages.length > 1) {
        prevBtn.style.display = 'flex';
        nextBtn.style.display = 'flex';
    } else {
        prevBtn.style.display = 'none';
        nextBtn.style.display = 'none';
    }
};

// --- Dynamic Status Checker ---
window.checkUserStatus = function(chatId, chatType) {
    const formData = new URLSearchParams();
    formData.append('chat_id', chatId);
    formData.append('chat_type', chatType);

    fetch('messaging/ajax_get_chat_status.php', {
        method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        const statusEl = document.getElementById('dynamicUserStatus');
        const detailsEl = document.querySelector('.chat-status-details');
        if (statusEl) {
            statusEl.className = `chat-status ${data.status}`;
            statusEl.innerHTML = `<span class="status-dot"></span><span class="status-text">${data.text}</span>`;
        }
        if (detailsEl) detailsEl.textContent = data.text;
    });
};

// messaging.js (OVERWRITE ang window.startCall function)

window.startCall = async function(type) {
    // Mark this client as the initiator of the call so only the initiator logs the call history
    window.isCallInitiator = true;
    const name = window.currentChatName ? window.currentChatName : 'User'; 
    const targetPeerId = 'smart_study_user_' + window.currentChatId;
    window.currentRemotePeerId = targetPeerId;
    
    document.getElementById('outgoingName').textContent = name; 
    const outgoingUI = document.getElementById('outgoingCallUI');
    outgoingUI.style.display = 'flex';

    document.getElementById('btnCancelOutgoing').onclick = () => {
        // user canceled the outgoing call — ensure we don't later log a call
        window.isCallInitiator = false;
        window.endCall(true);
        outgoingUI.style.display = 'none';
    };

    try {
        // 1. Get Local Media via centralized helper so the "Try Again" UI works and callers
        //    can reuse the resolved MediaStream and knowledge about whether we're audio-only.
        const res = await window.requestMediaPermissions();
        const stream = res && res.stream ? res.stream : res;
        window.localStream = stream;
        
        // Visual: attach preview and adapt UI for audio-only/video-only
        const localVid = document.getElementById('localVideo');
        if (stream) localVid.srcObject = stream;
        const audioOnlyUI = document.getElementById('audioOnlyUI');
        if (res && res.audioOnly) {
            // audio-only: hide local video and show audio-only UI
            localVid.style.display = 'none';
            if (audioOnlyUI) audioOnlyUI.style.display = 'block';
        } else if (res && res.videoOnly) {
            // video-only: show local preview
            localVid.style.display = 'block';
            if (audioOnlyUI) audioOnlyUI.style.display = 'none';
        } else {
            // default (audio+video): continue to keep hidden unless user toggles cam
            localVid.style.display = 'none';
            if (audioOnlyUI) audioOnlyUI.style.display = 'none';
        }

        // 2. Initiate Call
        const call = window.peer.call(targetPeerId, window.localStream);
        window.currentCall = call;

        // 3. Wait for Answer
        call.on('stream', (remoteStream) => {
            // SUCCESSFUL CONNECT
            window.callStartTime = Date.now(); 
            window.updateCallState(true, window.currentChatId, window.currentChatName, window.currentChatType); 

            outgoingUI.style.display = 'none';
            document.getElementById('callModal').style.display = 'flex';
            document.getElementById('callStatus').style.display = 'none'; 
            
            const audioNameEl = document.getElementById('audioName');
            if(audioNameEl) audioNameEl.textContent = name;

            // Visual Hide Remote
            const remoteVid = document.getElementById('remoteVideo');
            remoteVid.srcObject = remoteStream;
            remoteVid.style.opacity = '0'; 
            
            const camBtn = document.getElementById('btnToggleCam');
            if (camBtn) camBtn.style.background = '#ef4444';

            window.startCallTimer();
        });

        call.on('close', () => { window.endCall(false); outgoingUI.style.display = 'none'; });
        call.on('error', (err) => { 
            console.error("Peer Error:", err); 
            window.showCallStatusModal('<i class="bx bx-x-circle"></i>', 'Connection Error', 'The remote user may be offline or connection failed.');
            window.endCall(false); 
        });

    } catch (err) {
        console.error("Media Error:", err);
        window.showCallStatusModal('<i class="bx bx-ban"></i>', 'Permission Denied', 'Please grant microphone and camera access and try again.<div style="margin-top:10px;"><button class="modal-option" onclick="requestMediaPermissions()">Try Again</button></div>');
        window.endCall(false);
    }
};
window.startCallTimer = function() {
    let seconds = 0;
    if(window.callTimerInterval) clearInterval(window.callTimerInterval);
    window.callTimerInterval = setInterval(() => { seconds++; }, 1000);
};

window.endCall = function(notifyOther = true) {
    const mainModal = document.getElementById('callModal');
    const outgoingUI = document.getElementById('outgoingCallUI');
    const incomingUI = document.getElementById('incomingCallUI');

    // 1. LOG DURATION — only the caller/initiator should send this to avoid duplicate entries
    if (window.callStartTime) {
        const durationSeconds = Math.floor((Date.now() - window.callStartTime) / 1000);
        if (window.isCallInitiator) {
            fetch('messaging/ajax_log_call_history.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `duration=${durationSeconds}&target_id=${window.currentChatId}&chat_id=${window.currentChatId}&chat_type=${window.currentChatType}&caller_id=${document.getElementById('currentUserId').value}`
            });
        }
        // reset flags
        window.callStartTime = null;
        window.isCallInitiator = false;
    }

    // 2. [CLEANUP FIXES] Stop Audio and Clear Timers
    if (window.callAudio) {
        window.callAudio.pause();
        window.callAudio.currentTime = 0; 
        window.callAudio.src = '';
    }
    
    if (window.outgoingCallTimeout) {
        clearTimeout(window.outgoingCallTimeout);
        window.outgoingCallTimeout = null;
    }
    
    // 3. NOTIFY PEER (Keep existing logic)
    if (notifyOther && window.currentRemotePeerId && window.peer) {
        const conn = window.peer.connect(window.currentRemotePeerId);
        if(conn) {
            conn.on('open', () => {
                conn.send({type: 'ended'});
                setTimeout(() => conn.close(), 500);
            });
        }
    }

    // 4. STREAM/PEER CLEANUP (Keep existing logic)
    if (window.currentCall) window.currentCall.close();
    if (window.localStream) {
        window.localStream.getTracks().forEach(track => track.stop());
        window.localStream = null;
    }
    
    // 5. UI CLEANUP (Keep existing logic)
    if(window.callTimerInterval) clearInterval(window.callTimerInterval);
    if(mainModal) mainModal.style.display = 'none';
    if(outgoingUI) outgoingUI.style.display = 'none';
    if(incomingUI) incomingUI.style.display = 'none';

    window.updateCallState(false); 
    window.currentRemotePeerId = null; 
};

window.updateCallState = function(isActive, targetId, targetName, targetType) {
    if (isActive) {
        sessionStorage.setItem('activeCall', JSON.stringify({
            active: true, id: targetId, name: targetName, type: targetType
        }));
    } else {
        sessionStorage.removeItem('activeCall');
    }
    if(typeof window.togglePersistentCallButton === 'function') window.togglePersistentCallButton();
};

window.togglePersistentCallButton = function() {
    const state = sessionStorage.getItem('activeCall');
    const btn = document.getElementById('persistentCallBtn');
    if (btn) btn.style.display = state ? 'flex' : 'none';
};

window.showCallStatusModal = function(icon, title, message) {
    const modal = document.getElementById('callStatusModal');
    if(modal) {
        // statusIcon supports HTML (Boxicon markup)
        document.getElementById('statusIcon').innerHTML = icon;
        document.getElementById('statusTitle').textContent = title;
        // allow HTML in the message so we can render actions like 'Try Again'
        document.getElementById('statusMessage').innerHTML = message;
        modal.style.display = 'flex';
    }
};

// Try to re-request camera/microphone permissions from the user
window.requestMediaPermissions = async function() {
    // Returns an object: { stream: MediaStream, audioOnly: bool, videoOnly: bool }
    try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true, video: true });
        if (typeof window.showToast === 'function') window.showToast('Permissions Granted', 'Microphone & camera access granted.', '<i class="bx bx-check"></i>');
        const modal = document.getElementById('callStatusModal'); if (modal) modal.style.display = 'none';
        return { stream, audioOnly: false, videoOnly: false };
    } catch (err) {
        console.error('Permission retry failed', err);

        // If devices were not found, try graceful fallback (audio-only or video-only) when possible.
        try {
            const devices = await navigator.mediaDevices.enumerateDevices();
            const hasAudio = devices.some(d => d.kind === 'audioinput');
            const hasVideo = devices.some(d => d.kind === 'videoinput');

            // If no devices at all, inform the user specifically
            if (!hasAudio && !hasVideo) {
                window.showCallStatusModal('<i class="bx bx-ban"></i>', 'No Devices Found', 'No microphone or camera was detected on this machine. Please connect a device and try again.');
                throw err;
            }

            // Try audio-only if there is a microphone available
            if (hasAudio) {
                try {
                    const audioOnlyStream = await navigator.mediaDevices.getUserMedia({ audio: true });
                    if (typeof window.showToast === 'function') window.showToast('Partial Permissions', 'No camera detected — proceeding with audio only.', '<i class="bx bx-info-circle"></i>');
                    return { stream: audioOnlyStream, audioOnly: true, videoOnly: false };
                } catch (audioErr) {
                    console.warn('Audio-only attempt failed', audioErr);
                }
            }

            // Try video-only if there is a camera available
            if (hasVideo) {
                try {
                    const videoOnlyStream = await navigator.mediaDevices.getUserMedia({ video: true });
                    if (typeof window.showToast === 'function') window.showToast('Partial Permissions', 'No microphone detected — proceeding with video only.', '<i class="bx bx-info-circle"></i>');
                    return { stream: videoOnlyStream, audioOnly: false, videoOnly: true };
                } catch (videoErr) {
                    console.warn('Video-only attempt failed', videoErr);
                }
            }
        } catch (enumErr) {
            console.warn('enumerateDevices failed', enumErr);
        }

        // Generic guidance for permission denied or other errors
        window.showCallStatusModal('<i class="bx bx-ban"></i>', 'Permission Denied', 'Permissions still denied. Please enable microphone and camera in your browser settings and try again.<div style="margin-top:10px;"><button class="modal-option" onclick="requestMediaPermissions()">Try Again</button></div>');
        throw err;
    }
};

// --- GLOBAL UI FUNCTIONS ---
window.unrestrictUser = function (userId) {
    const formData = new URLSearchParams();
    formData.append('user_id', userId);
    fetch('messaging/ajax_toggle_mute.php', { method: 'POST', body: formData })
    .then(r => r.text()).then(() => {
        window.showToast('Success', 'Account unrestricted.', '<i class="bx bx-check"></i>', null, 'toast-success');
        window.refreshModalList('restricted');
        if (typeof window.loadChatList === 'function') window.loadChatList();
    });
};
window.archiveChat = function (id, type) {
    const formData = new URLSearchParams();
    formData.append('action', 'update_chat_status');
    formData.append('target_id', id);
    formData.append('chat_type', type);
    formData.append('status_field', 'is_archived');
    formData.append('status_value', 1);
    fetch('messaging/ajax_update_settings.php', { method: 'POST', body: formData })
    .then(r => r.text()).then(res => {
        if (res.trim() === 'success') {
            window.showToast('Archived', 'Chat moved to Archive.', '<i class="bx bx-archive"></i>', null, 'toast-success');
            window.refreshModalList('archived');
        }
    });
};
window.unarchiveChat = function (id, type) {
    const formData = new URLSearchParams();
    formData.append('action', 'update_chat_status');
    formData.append('target_id', id);
    formData.append('chat_type', type);
    formData.append('status_field', 'is_archived');
    formData.append('status_value', 0);
    fetch('messaging/ajax_update_settings.php', { method: 'POST', body: formData })
    .then(r => r.text()).then(res => {
        if (res.trim() === 'success') {
            window.showToast('Unarchived', 'Chat returned to inbox.', '<i class="bx bx-inbox"></i>', null, 'toast-success');
            window.refreshModalList('archived');
            if (typeof window.loadChatList === 'function') window.loadChatList();
        }
    });
};
window.blockUser = function (userId) {
    const formData = new URLSearchParams();
    formData.append('action', 'block_user');
    formData.append('target_id', userId);
    fetch('messaging/ajax_update_settings.php', { method: 'POST', body: formData })
    .then(r => r.text()).then(res => {
        if (res.trim() === 'blocked') {
            window.showToast('Blocked', 'User has been blocked.', '<i class="bx bx-block"></i>', null, 'toast-warning');
            window.refreshModalList('blocked');
        }
    });
};
window.unblockUser = function (userId) {
    const formData = new URLSearchParams();
    formData.append('action', 'unblock_user');
    formData.append('target_id', userId);
    fetch('messaging/ajax_update_settings.php', { method: 'POST', body: formData })
    .then(r => r.text()).then(res => {
        if (res.trim() === 'success') {
            window.showToast('Unblocked', 'User can message you again.', '<i class="bx bx-check"></i>', null, 'toast-success');
            window.refreshModalList('blocked');
            if (typeof window.loadChatList === 'function') window.loadChatList();
        }
    });
};
window.acceptRequest = function (partnerId) {
    const formData = new URLSearchParams();
    formData.append('action', 'accept');
    formData.append('partner_id', partnerId);
    fetch('messaging/ajax_handle_request.php', { method: 'POST', body: formData })
    .then(r => r.text()).then(res => {
        if (res.trim() === 'success') {
            window.showToast('Accepted', 'Conversation moved to inbox.', '<i class="bx bx-check"></i>', null, 'toast-success');
            window.refreshModalList('requests'); 
            window.loadChatList();
        }
    });
};
window.declineRequest = function (partnerId) {
    // Use site-wide custom confirm (async) instead of native confirm
    const doDecline = () => {
        const formData = new URLSearchParams();
        formData.append('action', 'decline');
        formData.append('partner_id', partnerId);
        fetch('messaging/ajax_handle_request.php', { method: 'POST', body: formData })
        .then(r => r.text()).then(res => {
            if (res.trim() === 'success') {
                window.showToast('Declined', 'Request removed.', '<i class="bx bx-trash"></i>', null, 'toast-warning');
                window.refreshModalList('requests');
            }
        });
    };
    if (typeof window.showCustomConfirm === 'function') {
        window.showCustomConfirm('Decline request?', 'Are you sure you want to decline this message request?', doDecline);
        return;
    }
    const formData = new URLSearchParams();
    formData.append('action', 'decline');
    formData.append('partner_id', partnerId);
    fetch('messaging/ajax_handle_request.php', { method: 'POST', body: formData })
    .then(r => r.text()).then(res => {
        if (res.trim() === 'success') {
            window.showToast('Declined', 'Request removed.', '<i class="bx bx-trash"></i>', null, 'toast-warning');
            window.refreshModalList('requests');
        }
    });
};

// ========================================================
// 3. MAIN INITIALIZATION (Run Once on Load)
// ========================================================
document.addEventListener('DOMContentLoaded', function () {
    const currentUserIdVal = document.getElementById('currentUserId') ? document.getElementById('currentUserId').value : null;
    if(!currentUserIdVal) return; 

    // --- A. PEERJS SETUP (WITH STUN) ---
    if (!window.peer) {
        console.log("Initializing PeerJS...");
        window.peer = new Peer('smart_study_user_' + currentUserIdVal, {
            config: {
                'iceServers': [
                    { url: 'stun:stun.l.google.com:19302' },
                    { url: 'stun:stun1.l.google.com:19302' }
                ]
            }
        });

        window.peer.on('open', (id) => { console.log('My peer ID is: ' + id); });
        window.peer.on('error', (err) => { console.error("PeerJS Error:", err); });

        // Global Listener
        window.peer.on('connection', (conn) => {
            conn.on('data', (data) => {
                console.log("Signal Received:", data); 
                if (data.type === 'declined') {
                    showCallStatusModal('<i class="bx bx-ban"></i>', 'Call Declined', 'User declined your call.');
                    const outgoingUI = document.getElementById('outgoingCallUI');
                    if(outgoingUI) outgoingUI.style.display = 'none'; 
                    window.endCall(false); 
                }
                if (data.type === 'ended') {
                     showCallStatusModal('<i class="bx bx-phone"></i>', 'Video Call Ended', 'The other person hung up.');
                     window.endCall(false); 
                }
            });
        });

        // messaging.js (Inside DOMContentLoaded, overwrite the existing peer.on('call') block)

        window.peer.on('call', (call) => {
            const callerPeerId = call.peer;
            window.currentRemotePeerId = callerPeerId; 
            const callerId = callerPeerId.replace('smart_study_user_', '');

            const incomingUI = document.getElementById('incomingCallUI');
            document.getElementById('incomingName').textContent = "Checking...";
            incomingUI.style.display = 'flex';

            // Fetch Name
            const formData = new URLSearchParams();
            formData.append('user_id', callerId);
            fetch('messaging/ajax_get_caller_details.php', {
                method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: formData
            }).then(r => r.json()).then(data => {
                if(data.status === 'success') {
                    document.getElementById('incomingName').textContent = data.name;
                    document.getElementById('incomingAvatar').innerHTML = data.avatar;
                }
            });

            // Decline Logic
            const handleDecline = () => {
                const conn = window.peer.connect(callerPeerId);
                if(conn) conn.on('open', () => conn.send({type: 'declined'}));
                setTimeout(() => { call.close(); window.currentRemotePeerId = null; }, 300); 
                incomingUI.style.display = 'none';
            };
            document.getElementById('btnDeclineCall').onclick = handleDecline;
            document.getElementById('btnCloseIncoming').onclick = handleDecline;

            // Accept Logic (VISUAL HIDE TRICK)
            document.getElementById('btnAcceptCall').onclick = async () => {
                // make sure the acceptor isn't treated as the initiator
                window.isCallInitiator = false;
                incomingUI.style.display = 'none';
                const mainModal = document.getElementById('callModal');
                mainModal.style.display = 'flex';
                
                const callerName = document.getElementById('incomingName').textContent;
                const nameEl = document.getElementById('audioName') || document.getElementById('callName');
                if(nameEl) nameEl.textContent = callerName;

                document.getElementById('callStatus').textContent = "Connecting...";
                document.getElementById('callStatus').style.display = 'block';

                try {
                    // Use centralized helper so "Try Again" will re-request and return a usable stream
                    const res = await window.requestMediaPermissions();
                    const stream = res && res.stream ? res.stream : res;
                    window.localStream = stream;
                    
                    const localVid = document.getElementById('localVideo');
                    if (stream) localVid.srcObject = stream;
                    const audioOnlyUI = document.getElementById('audioOnlyUI');
                    if (res && res.audioOnly) {
                        localVid.style.display = 'none';
                        if (audioOnlyUI) audioOnlyUI.style.display = 'block';
                    } else if (res && res.videoOnly) {
                        localVid.style.display = 'block';
                        if (audioOnlyUI) audioOnlyUI.style.display = 'none';
                    } else {
                        localVid.style.display = 'none';
                        if (audioOnlyUI) audioOnlyUI.style.display = 'none';
                    }
                    
                    call.answer(stream); 
                    window.currentCall = call;

                    const camBtn = document.getElementById('btnToggleCam');
                    if (camBtn) camBtn.style.background = '#ef4444';

                    call.on('stream', (remoteStream) => {
                        window.callStartTime = Date.now();
                        window.updateCallState(true, callerId, callerName, 'user');
                        
                        const remoteVid = document.getElementById('remoteVideo');
                        remoteVid.srcObject = remoteStream;
                        remoteVid.style.opacity = '0'; 
                        document.getElementById('callStatus').style.display = 'none';
                        
                        const pBtn = document.getElementById('persistentCallBtn');
                        if(pBtn) pBtn.style.display = 'none';
                        
                        window.startCallTimer();
                    });
                    
                    call.on('close', window.endCall);
                } catch (err) {
                    console.error('Media Error: Receiver denied access.', err);
                    mainModal.style.display = 'none';
                    // requestMediaPermissions already shows helpful guidance; provide context then end
                    window.showCallStatusModal('<i class="bx bx-ban"></i>', 'Permissions Required', 'Cannot answer call without mic/cam access.<div style="margin-top:10px;"><button class="modal-option" onclick="requestMediaPermissions()">Try Again</button></div>');
                    window.endCall(false);
                }
            };
        });
    }

    // --- B. UI RE-ATTACHMENT ---
    if (typeof window.togglePersistentCallButton === 'function') window.togglePersistentCallButton();

    document.getElementById('persistentCallBtn')?.addEventListener('click', function() {
        const state = JSON.parse(sessionStorage.getItem('activeCall'));
        if (state) {
            const callModal = document.getElementById('callModal');
            if (callModal) {
                callModal.style.display = 'flex';
                this.style.display = 'none'; 
            }
        }
    });

    document.getElementById('btnMinimizeCall')?.addEventListener('click', () => {
        document.getElementById('callModal').style.display = 'none';
        if (typeof window.togglePersistentCallButton === 'function') window.togglePersistentCallButton();
        const persistentBtn = document.getElementById('persistentCallBtn');
        if (persistentBtn) persistentBtn.style.display = 'flex';
    });

    document.getElementById('btnToggleCam')?.addEventListener('click', function() {
        const localVideoEl = document.getElementById('localVideo');
        const remoteVideoEl = document.getElementById('remoteVideo');
        const isOff = (this.style.background === 'rgb(239, 68, 68)' || this.style.background === '#ef4444');
        if (isOff) {
            localVideoEl.style.display = 'block';
            remoteVideoEl.style.opacity = '1';
            this.style.background = '#334155'; 
        } else {
            localVideoEl.style.display = 'none';
            remoteVideoEl.style.opacity = '0';
            this.style.background = '#ef4444'; 
        }
    });

    document.getElementById('btnToggleMic')?.addEventListener('click', function() {
        if (window.localStream) {
            const track = window.localStream.getAudioTracks()[0];
            track.enabled = !track.enabled;
            this.style.background = track.enabled ? '#334155' : '#ef4444';
        }
    });

    if (document.getElementById('closeCallModal')) document.getElementById('closeCallModal').addEventListener('click', window.endCall);
    if (document.getElementById('closeStatusModal')) document.getElementById('closeStatusModal').addEventListener('click', () => document.getElementById('callStatusModal').style.display = 'none');
    if (document.getElementById('endCallBtn')) document.getElementById('endCallBtn').addEventListener('click', window.endCall);

    // --- C. MESSAGING SPECIFIC ---
    const chatList = document.getElementById('chatList');
    const searchInput = document.getElementById('searchInput');
    const newMessageBtn = document.getElementById('newMessageButton');

    if (chatList) {
        window.loadChatList = function(query = '') {
            fetch('messaging/ajax_get_chat_list.php', {
                method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'query=' + encodeURIComponent(query)
            }).then(r => r.text()).then(data => {
                chatList.innerHTML = data;
                attachChatClickListeners();
                if (window.currentChatId) {
                    const activeItem = document.querySelector(`.chat-item[data-chat-id='${window.currentChatId}'][data-chat-type='${window.currentChatType}']`);
                    if (activeItem) activeItem.classList.add('active');
                }
                applyChatFilter();
            });
        };

        window.loadChatList();
        window.chatListInterval = setInterval(() => { if (document.activeElement !== searchInput) window.loadChatList(searchInput.value); }, 5000);
        searchInput.addEventListener('keyup', function () { window.loadChatList(this.value); });

        const autoId = document.body.dataset.openChatId;
        const autoType = document.body.dataset.openChatType;
        if (autoId && autoId !== 'null') {
            setTimeout(() => {
                const item = document.querySelector(`.chat-item[data-chat-id='${autoId}'][data-chat-type='${autoType}']`);
                if (item) item.click();
                else if (autoType === 'user') {
                    const pending = JSON.parse(sessionStorage.getItem('pendingChatUser') || 'null');
                    if (pending && pending.id == autoId) {
                        window.loadMessages(pending.id, 'user', pending.name, pending.avatar, 0);
                        sessionStorage.removeItem('pendingChatUser');
                    } else {
                        window.loadMessages(autoId, 'user', 'Loading...', '<div class="default-avatar-small">?</div>', 0);
                    }
                }
            }, 500);
        }
    }

    // Modals & Menus
    if (newMessageBtn) {
        newMessageBtn.addEventListener('click', (e) => {
            e.preventDefault();
            document.getElementById('newChatModal').style.display = 'flex';
            selectedUsers = [];
            const selectedArea = document.getElementById('selectedUsersArea');
            selectedArea.querySelectorAll('.user-chip').forEach(chip => chip.remove());
            document.getElementById('groupNameSection').style.display = 'none';
            const createBtn = document.getElementById('btnCreateChat');
            if (createBtn) { createBtn.textContent = 'Chat'; createBtn.disabled = true; }
            const userInput = document.getElementById('userSearchInput');
            if (userInput) { userInput.value = ''; userInput.focus(); }
        });
    }

    // ============================================================
    // MISSING LOGIC: FILTER BUTTONS (All / Unread / Groups)
    // ============================================================
    const filterBtns = document.querySelectorAll('.filter-btn');
    
    if (filterBtns.length > 0) {
        filterBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                // 1. Update UI Active State
                filterBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                
                // 2. Update Global State
                window.currentFilter = btn.dataset.filter;
                
                // 3. Apply Filter Function
                if (typeof window.applyChatFilter === 'function') {
                    window.applyChatFilter();
                }
            });
        });
    }
    document.getElementById('closeNewChatModal')?.addEventListener('click', () => document.getElementById('newChatModal').style.display = 'none');

    // Static Gallery Listeners
    document.getElementById('nextImageBtn')?.addEventListener('click', (e) => {
        e.stopPropagation();
        if (window.galleryImages.length > 0) {
            window.currentGalleryIndex = (window.currentGalleryIndex + 1) % window.galleryImages.length;
            window.updateModalContent();
        }
    });
    document.getElementById('prevImageBtn')?.addEventListener('click', (e) => {
        e.stopPropagation();
        if (window.galleryImages.length > 0) {
            window.currentGalleryIndex = (window.currentGalleryIndex - 1 + window.galleryImages.length) % window.galleryImages.length;
            window.updateModalContent();
        }
    });
    const imgModal = document.getElementById('imagePreviewModal');
    if (imgModal) {
        document.getElementById('imageModalCloseButton')?.addEventListener('click', () => imgModal.style.display = 'none');
        imgModal.addEventListener('click', (e) => {
            if (e.target === imgModal || e.target.closest('.preview-top-bar')) {
                if (!e.target.closest('button') && !e.target.closest('.menu-item')) imgModal.style.display = 'none';
            }
        });
    }
    
    // Modal Maps for Menus
    const optionsBtn = document.getElementById('chatOptionsButton');
    const dropdown = document.getElementById('sidebarDropdown');
    
    function pollRequests() {
        fetch('messaging/ajax_get_request_count.php')
        .then(r => r.text())
        .then(cnt => {
            const count = parseInt(cnt) || 0;
            const dBadge = document.getElementById('dotsBadge');
            const mBadge = document.getElementById('menuReqBadge');
            if(count > 0) {
                if(dBadge) { dBadge.style.display = 'flex'; dBadge.textContent = count; }
            } else {
                if(dBadge) dBadge.style.display = 'none';
            }
        });
    }
    window.pollRequestsInterval = setInterval(pollRequests, 3000);
    pollRequests();

    if (optionsBtn && dropdown) {
        optionsBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            dropdown.classList.toggle('active');
        });
    }

    const modalMap = {
        'menuPreferences': 'preferencesModal', 'menuMsgRequests': 'msgRequestsModal',
        'menuArchived': 'archivedModal', 'menuRestricted': 'restrictedModal',
        'menuPrivacy': 'privacyModal', 'menuHelp': 'helpModal'
    };
    Object.keys(modalMap).forEach(menuId => {
        const btn = document.getElementById(menuId);
        const targetModal = document.getElementById(modalMap[menuId]);
        if (btn && targetModal) {
            btn.addEventListener('click', () => {
                if (dropdown) dropdown.classList.remove('active');
                targetModal.style.display = 'flex';
                if (menuId === 'menuArchived') window.refreshModalList('archived');
                if (menuId === 'menuRestricted') window.refreshModalList('restricted');
                if (menuId === 'menuMsgRequests') window.refreshModalList('requests');
            });
        }
    });
    // ============================================================
    // MISSING LOGIC: BLOCKED ACCOUNTS HANDLER
    // ============================================================
    const btnBlockedMenu = document.getElementById('menuBlocked');
    const btnBlockedPrivacy = document.getElementById('btnBlockedAccounts'); // Button inside Privacy Modal
    const blockedModal = document.getElementById('blockedModal');

    function openBlockedModal() {
        // 1. Close Sidebar Dropdown
        const dd = document.getElementById('sidebarDropdown');
        if (dd) dd.classList.remove('active');

        // 2. Close Privacy Modal if open (para hindi magpatong)
        const privModal = document.getElementById('privacyModal');
        if (privModal) privModal.style.display = 'none';
        
        // 3. Open Blocked Modal & Refresh List
        if (blockedModal) {
            blockedModal.style.display = 'flex';
            if(typeof window.refreshModalList === 'function') {
                window.refreshModalList('blocked');
            }
        }
    }
    // ========================================================
// MISSING FUNCTION: VIEW GROUP MEMBERS
// ========================================================
window.viewGroupMembers = function() {
    // 1. Check if valid group
    // Gamitin ang currentChatId kung galing sa active chat, 
    // o contextMenuTargetId kung galing sa right-click menu
    const targetId = window.contextMenuTargetId || window.currentChatId;

    if (!targetId) return;

    // 2. Open Modal
    const modal = document.getElementById('membersModal');
    const container = document.getElementById('membersListContainer');
    
    if (modal && container) {
        modal.style.display = 'flex';
        container.innerHTML = '<div class="empty-state-container" style="padding:20px;"><p style="color:#888;text-align:center;">Loading members...</p></div>';

        // 3. Fetch Data
        fetch('messaging/ajax_get_group_members.php?group_id=' + targetId)
        .then(r => r.text())
        .then(html => {
            container.innerHTML = html;
        })
        .catch(err => {
            console.error("Error loading members:", err);
            container.innerHTML = '<p style="text-align:center; padding:20px; color:#ef4444;">Error loading list.</p>';
        });
    }
    
    // Reset context target after opening
    window.contextMenuTargetId = null; 
};
    // Attach Listeners
    if (btnBlockedMenu) btnBlockedMenu.addEventListener('click', openBlockedModal);
    if (btnBlockedPrivacy) btnBlockedPrivacy.addEventListener('click', openBlockedModal);
    document.querySelectorAll('.modal-close-btn, .modal-close-btn-icon, .close-sub-modal').forEach(btn => {
        btn.addEventListener('click', function() {
            const m = this.closest('.modal-overlay');
            if(m) m.style.display = 'none';
        });
    });

// --- MEMBERS MODAL LOGIC FIX ---

// 1. Close Button Logic
const closeMemBtn = document.getElementById('closeMembersModal');
if (closeMemBtn) {
    closeMemBtn.addEventListener('click', (e) => {
        e.preventDefault(); // Iwas refresh kung nasa form
        document.getElementById('membersModal').style.display = 'none';
    });
}

// 2. Open Modal Logic (Update mo yung existing viewGroupMembers function mo)
window.viewGroupMembers = function() {
    const targetId = window.contextMenuTargetId || window.currentChatId;
    if (!targetId) return;

    const modal = document.getElementById('membersModal');
    const container = document.getElementById('membersListContainer');
    
    if (modal && container) {
        modal.style.display = 'flex'; // Show modal
        
        // Reset content habang naglo-load
        container.innerHTML = '<div class="empty-state-container" style="padding:20px;"><p style="color:#888;text-align:center;">Loading members...</p></div>';

        // Fetch data
        fetch('messaging/ajax_get_group_members.php?group_id=' + targetId)
        .then(r => r.text())
        .then(html => {
            container.innerHTML = html;
        });
    }
    // Close context menu if open
    const ctxMenu = document.getElementById('chatListContextMenu');
    if(ctxMenu) ctxMenu.classList.remove('active');
};

}); // END DOMContentLoaded

// ========================================================
// 4. CORE MESSAGING FUNCTIONS (Window Scope)
// ========================================================

// ========================================================
// 1. MAIN LOAD MESSAGES FUNCTION (Final Fixes)
// ========================================================
window.loadMessages = function(chatId, chatType, chatName, avatarHTML, isMuted = 0) {
    // Clear Intervals
    if (window.messagePollingInterval) clearInterval(window.messagePollingInterval);
    if (window.statusPollingInterval) clearInterval(window.statusPollingInterval);
    
    window.currentChatId = chatId;
    window.currentChatType = chatType;
    window.currentChatName = chatName;
    
    // Setup Chat Window
    const callButtons = `<button class="btn-icon" id="btnVoiceCall" title="Start Call"><i class="bx bx-phone"></i></button>`;
    const chatWindow = document.getElementById('chatWindow');
    const detailsPanel = document.getElementById('chatDetails');
    
    chatWindow.innerHTML = `
        <div class="chat-header">
            <div class="chat-header-info">
                <div class="chat-avatar">${avatarHTML}</div>
                <div><h3>${chatName}</h3><p class="chat-status" id="dynamicUserStatus"><span class="status-dot"></span><span class="status-text">Checking...</span></p></div>
            </div>
            <div class="chat-header-actions">${callButtons}<button class="btn-icon" id="toggleDetailsButtonDynamic">ℹ️</button></div>
        </div>
        <div class="message-area" id="messageArea"><div class="chat-empty-state">Loading...</div></div>
        <button id="scrollBottomBtn" class="btn-scroll-bottom" title="Go to latest">⬇️</button>
        <div class="reply-preview-bar" id="replyPreviewBar"><div class="reply-preview-content"><p id="replyPreviewText">...</p></div><button class="btn-icon" id="cancelReplyButton">×</button></div>
        <div class="message-input-area">
            <button class="btn-icon" id="uploadButtonDynamic"><i class="bx bx-plus"></i></button>
            <input type="text" placeholder="Type a message..." id="messageInputDynamic" autocomplete="off">
            <button class="btn-icon btn-send" id="sendMessageButtonDynamic"><i class="bx bx-send"></i></button>
        </div>
        <div id="convoSearchContainer" class="convo-search-container" style="display: none;">
            <input type="text" id="convoSearchInput" placeholder="Find in chat...">
            <button id="convoSearchClose" class="btn-icon">×</button>
        </div>
    `;
    
    // Scroll Logic
    const messageArea = document.getElementById('messageArea');
    const scrollBtn = document.getElementById('scrollBottomBtn');
    messageArea.addEventListener('scroll', () => {
        if (messageArea.scrollHeight - (messageArea.scrollTop + messageArea.clientHeight) > 150) scrollBtn.classList.add('show');
        else scrollBtn.classList.remove('show');
    });
    scrollBtn.addEventListener('click', () => messageArea.scrollTo({ top: messageArea.scrollHeight, behavior: 'smooth' }));

    // Call Button
    document.getElementById('btnVoiceCall').addEventListener('click', () => window.startCall('voice'));
    
    // Attach Listeners
    window.attachDynamicButtonListeners(chatId, chatType);
    window.fetchAndDisplayMessages(chatId, chatType, true);
    window.checkUserStatus(chatId, chatType);
    window.statusPollingInterval = setInterval(() => window.checkUserStatus(chatId, chatType), 5000);
    window.messagePollingInterval = setInterval(() => window.fetchAndDisplayMessages(chatId, chatType, false), 3000);

    // Mark Read
    window.markReadTimeout = setTimeout(() => {
        fetch('messaging/ajax_mark_read.php', {
            method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `target_id=${chatId}&type=${chatType}`
        }).then(() => window.loadChatList());
    }, 1000);

    // ==========================================
    // UPDATED DETAILS PANEL (Fixed Profile & Layout)
    // ==========================================
    let actionsHTML = '';
    
    if (chatType === 'group') {
        actionsHTML = `
            <div class="action-button" onclick="window.viewGroupMembers()">
                <div class="icon"><i class="bx bx-group"></i></div><span>Members</span>
            </div>
            <div class="action-button danger" onclick="document.getElementById('ctxDeleteGroup').click()">
                <div class="icon" style="color:#ef4444;"><i class="bx bx-trash"></i></div><span>Leave</span>
            </div>
        `;
    } else {
        // [FIXED] Direct Profile Link + Removed Create Group Button
        actionsHTML = `
                <div class="action-button" onclick="window.location.assign('profile.php?user_id=${chatId}&from=messages')">
                <div class="icon"><i class="bx bx-user"></i></div><span>Profile</span>
            </div>
        `;
    }

    detailsPanel.innerHTML = `
        <div class="details-header">
            <div class="details-avatar">${avatarHTML}</div>
            <div class="editable-name-container">
                <h3 id="detailsChatName">${chatName}</h3>
                <button class="btn-edit-name" onclick="window.editChatName('${chatId}', '${chatType}')" title="Rename"><i class="bx bx-edit-alt"></i></button>
            </div>
            <p class="chat-status-details">Checking status...</p>
        </div>
        
        <div class="details-actions">
            ${actionsHTML}
        </div>

        <div class="details-accordion">
            <div class="accordion-item">
                <button class="accordion-header" id="mediaAccordionHeader" onclick="window.toggleMediaAccordion()">
                    <span>Media & files</span>
                    <span class="accordion-icon">▼</span>
                </button>
                <div class="accordion-content" id="mediaAccordionContent" style="display: none;">
                    <div class="media-tabs">
                        <button class="media-tab active" onclick="window.switchMediaTab('media')">Photos</button>
                        <button class="media-tab" onclick="window.switchMediaTab('files')">Files</button>
                    </div>
                    
                    <div id="tab-media-content" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 2px;">
                        <p class='empty-media'>Loading photos...</p>
                    </div>

                    <div id="tab-files-content" style="display: none;">
                        <p class='empty-media'>Loading files...</p>
                    </div>
                </div>
            </div>
        </div>
    `;
};

window.fetchAndDisplayMessages = function(chatId, chatType, scrollToBottom = true) {
    const messageArea = document.getElementById('messageArea');
    if (!messageArea) return;
    const isNearBottom = (messageArea.scrollHeight - messageArea.scrollTop - messageArea.clientHeight < 200);
    fetch('messaging/ajax_fetch_messages.php?v=' + Date.now(), {
        method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `chat_id=${chatId}&chat_type=${chatType}`
    }).then(r => r.text()).then(data => {
        if (data.trim() === window.lastChatHTML.trim()) return;
        window.lastChatHTML = data;
        messageArea.innerHTML = data;
        if (scrollToBottom) setTimeout(() => messageArea.scrollTop = messageArea.scrollHeight, 50);
        else if (isNearBottom) messageArea.scrollTop = messageArea.scrollHeight;
    });
};

window.sendMessage = function(chatId) {
    const input = document.getElementById('messageInputDynamic');
    if (!input.value.trim()) return;
    const formData = new URLSearchParams();
    if (window.currentChatType === 'group') formData.append('group_id', chatId);
    else formData.append('receiver_id', chatId);
    formData.append('message', input.value);
    if (window.currentReplyToId) formData.append('reply_to_id', window.currentReplyToId);

    fetch('messaging/ajax_send_message.php', { method: 'POST', body: formData })
    .then(() => {
        input.value = '';
        window.hideReplyPreviewBar();
        window.fetchAndDisplayMessages(chatId, window.currentChatType, true);
        window.loadChatList();
    });
};

window.attachDynamicButtonListeners = function(chatId, chatType) {
    document.getElementById('sendMessageButtonDynamic').addEventListener('click', () => window.sendMessage(chatId));
    document.getElementById('messageInputDynamic').addEventListener('keyup', (e) => { if (e.key === 'Enter') window.sendMessage(chatId); });
    document.getElementById('toggleDetailsButtonDynamic').addEventListener('click', () => document.querySelector('.messaging-container').classList.toggle('details-hidden'));
    document.getElementById('uploadButtonDynamic').addEventListener('click', () => document.getElementById('uploadModal').style.display = 'flex');
    document.getElementById('cancelReplyButton').addEventListener('click', window.hideReplyPreviewBar);
    
    const searchBtn = document.getElementById('searchConvoButtonDynamic');
    if (searchBtn) searchBtn.addEventListener('click', () => {
        document.getElementById('convoSearchContainer').style.display = 'flex';
        document.getElementById('convoSearchInput').focus();
    });
    document.getElementById('convoSearchClose').addEventListener('click', () => {
        document.getElementById('convoSearchContainer').style.display = 'none';
    });
    
    const searchInputConvo = document.getElementById('convoSearchInput');
    if (searchInputConvo) searchInputConvo.addEventListener('keyup', function () {
        const term = this.value.toLowerCase();
        document.querySelectorAll('.message-bubble p').forEach(p => {
            const text = p.textContent;
            if (term && text.toLowerCase().includes(term)) {
                p.innerHTML = text.replace(new RegExp(`(${term})`, 'gi'), '<span class="highlight-text">$1</span>');
                p.scrollIntoView({ behavior: 'smooth', block: 'center' });
            } else {
                p.innerHTML = text;
            }
        });
    });
    
};

window.showReplyPreviewBar = function(msgId, senderName, text) {
    window.currentReplyToId = msgId;
    const bar = document.getElementById('replyPreviewBar');
    document.getElementById('replyPreviewText').innerHTML = `Replying to <strong>${senderName}</strong>: ${text}`;
    bar.classList.add('active');
    document.getElementById('messageInputDynamic')?.focus();
};

window.hideReplyPreviewBar = function() {
    window.currentReplyToId = null;
    document.getElementById('replyPreviewBar').classList.remove('active');
};

window.attachChatClickListeners = function() {
    document.querySelectorAll('.chat-item').forEach(item => {
        item.addEventListener('click', function (e) {
            if (e.target.classList.contains('btn-chat-item-options')) return;
            const id = this.dataset.chatId;
            const type = this.dataset.chatType;
            const name = this.dataset.chatName;
            const avatar = this.querySelector('.chat-avatar').innerHTML;
            window.loadMessages(id, type, name, avatar, this.dataset.muted);
        });
    });
};

window.applyChatFilter = function() {
    const items = document.querySelectorAll('.chat-item');
    items.forEach(item => {
        let show = false;
        if (window.currentFilter === 'all') show = true;
        else if (window.currentFilter === 'unread' && item.querySelector('.chat-unread-count')) show = true;
        else if (window.currentFilter === 'groups' && item.dataset.chatType === 'group') show = true;
        item.style.display = show ? 'flex' : 'none';
    });
};// ========================================================
// 5. NAVIGATION GUARD (Aggressive Fix)
// ========================================================

if (typeof window.pendingUrl === 'undefined') window.pendingUrl = null; // Store URL destination (guarded to avoid duplicate includes)

// Listen during the CAPTURE phase (true) to catch the click first!
document.addEventListener('click', function(e) {
    // 1. Check if clicked element is a Link or inside a Link
    const link = e.target.closest('a');
    
    // 2. Check if there is TRULY an active call
    // Check variables AND session storage to be sure
    const isCallActive = window.currentCall || window.localStream || sessionStorage.getItem('activeCall');

    if (link && isCallActive) {
        const href = link.getAttribute('href');

        // Ignore internal actions (hashtags, javascript calls, new tabs)
        if (!href || href.startsWith('#') || href.startsWith('javascript') || link.target === '_blank') {
            return; 
        }

        // 3. STOP EVERYTHING IMMEDIATELY
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation(); // Kill other listeners

        // 4. Show the Custom Warning Modal
        pendingUrl = href;
        const warningModal = document.getElementById('leaveCallModal');
        
        if (warningModal) {
            warningModal.style.display = 'flex';
            } else {
                // Fallback kung sakaling wala yung modal HTML (Emergency Alert)
                if (typeof window.askConfirm === 'function') {
                    window.askConfirm('Active call', 'Warning — You have an active call. Leaving will disconnect you immediately. Do you want to leave?')
                        .then(yes => { if (yes) { if (window.endCall) window.endCall(true); window.location.href = href; } });
                } else {
                    // extreme fallback
                    if (window.endCall) window.endCall(true);
                    window.location.href = href;
                }
            }
        }
    }
}, true); // <--- "true" is CRITICAL here for capture phase

// --- MODAL BUTTON HANDLERS (Run once) ---
document.addEventListener('DOMContentLoaded', function() {
    
    // "Stay in Call" Button
    document.getElementById('btnStayInCall')?.addEventListener('click', () => {
        document.getElementById('leaveCallModal').style.display = 'none';
        pendingUrl = null;
    });

    // "End & Leave" Button
    document.getElementById('btnLeaveCall')?.addEventListener('click', () => {
        // Clean up call first
        if (window.endCall) window.endCall(true); 
        
        // Clear session explicitly
        sessionStorage.removeItem('activeCall');

        // Proceed to the blocked URL
        if (pendingUrl) {
            window.location.href = pendingUrl;
        }
    });
});

// --- BROWSER REFRESH/CLOSE GUARD (Native Fallback) ---
window.addEventListener('beforeunload', function (e) {
    const isCallActive = window.currentCall || window.localStream || sessionStorage.getItem('activeCall');
    if (isCallActive) {
        const msg = "You have an active call. Leaving will end it.";
        e.returnValue = msg;
        return msg;
    }
});
// ============================================================
    // MISSING LOGIC: NEW CHAT / SEARCH USERS / CREATE GROUP
    // ============================================================
    const searchInputNewChat = document.getElementById('userSearchInput');
    const resultsList = document.getElementById('searchResultsList');
    const selectedArea = document.getElementById('selectedUsersArea');
    const createBtn = document.getElementById('btnCreateChat');
    const groupSection = document.getElementById('groupNameSection');
    const groupNameInput = document.getElementById('groupNameInput');
    
    let selectedUsers = []; // Local array for this modal

    // 1. SEARCH INPUT LISTENER
    if (searchInputNewChat) {
        searchInputNewChat.addEventListener('input', function () {
            const query = this.value.trim();
            if (query.length < 1) {
                resultsList.innerHTML = '';
                return;
            }

            fetch('messaging/ajax_search_users.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'query=' + encodeURIComponent(query)
            })
            .then(r => r.json())
            .then(users => {
                resultsList.innerHTML = '';
                if (users.length === 0) {
                    resultsList.innerHTML = '<div style="padding:10px; color:#ccc; text-align:center;">No users found.</div>';
                    return;
                }
                users.forEach(user => {
                    if (selectedUsers.find(u => u.id == user.id)) return; // Skip if selected

                    const div = document.createElement('div');
                    div.className = 'search-result-item';
                    div.style.cssText = 'display:flex; align-items:center; padding:10px; cursor:pointer; border-radius:8px; transition:background 0.2s;';
                    div.innerHTML = `
                        <div style="margin-right:10px;">${user.avatar_html}</div>
                        <span style="color:var(--text-light); font-weight:500;">${user.firstname} ${user.lastname}</span>
                    `;
                    
                    div.addEventListener('mouseenter', () => div.style.background = 'var(--bg-hover)');
                    div.addEventListener('mouseleave', () => div.style.background = 'transparent');
                    
                    div.addEventListener('click', () => {
                        selectedUsers.push({ id: user.id, name: `${user.firstname} ${user.lastname}`, avatar: user.avatar_html });
                        searchInputNewChat.value = '';
                        resultsList.innerHTML = '';
                        renderChips();
                        searchInputNewChat.focus();
                    });

                    resultsList.appendChild(div);
                });
            });
        });
    }

    // 2. RENDER CHIPS FUNCTION
    function renderChips() {
        selectedArea.querySelectorAll('.user-chip').forEach(chip => chip.remove());
        
        selectedUsers.forEach((u, index) => {
            const chip = document.createElement('span');
            chip.className = 'user-chip';
            chip.style.cssText = 'background:rgba(99, 102, 241, 0.2); color:#818cf8; padding:4px 10px; border-radius:15px; font-size:0.9rem; display:flex; align-items:center; gap:5px; margin-right:5px; margin-bottom:5px;';
            chip.innerHTML = `${u.name} <span class="remove-chip" style="cursor:pointer; font-weight:bold; margin-left:5px;">&times;</span>`;
            
            chip.querySelector('.remove-chip').addEventListener('click', (e) => {
                e.stopPropagation();
                selectedUsers.splice(index, 1);
                renderChips();
            });
            selectedArea.insertBefore(chip, searchInputNewChat);
        });

        if (selectedUsers.length > 1) {
            groupSection.style.display = 'block';
            createBtn.textContent = 'Create Group';
        } else {
            groupSection.style.display = 'none';
            createBtn.textContent = 'Chat';
        }
        createBtn.disabled = selectedUsers.length === 0;
    }

    // 3. CREATE CHAT BUTTON LISTENER
    if (createBtn) {
        createBtn.addEventListener('click', () => {
            if (selectedUsers.length === 0) return;

            // CASE A: Single User
            if (selectedUsers.length === 1) {
                const u = selectedUsers[0];
                document.getElementById('newChatModal').style.display = 'none';
                
                // Use global loadMessages instead of redirecting if possible, or redirect
                window.location.href = `messaging.php?user_id=${u.id}`;
            } 
            // CASE B: Create Group
            else {
                const groupName = groupNameInput.value.trim() || "New Group";
                const memberIds = selectedUsers.map(u => u.id);
                const formData = new URLSearchParams();
                formData.append('group_name', groupName);
                formData.append('members', JSON.stringify(memberIds));

                fetch('messaging/ajax_create_group.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    if (res.status === 'success') {
                        window.location.href = `messaging.php?group_id=${res.group_id}`;
                    } else {
                        alert(res.message);
                    }
                });
            }
        });
    }

    // 4. "ADD TO GROUP" HELPER (Expose globally so other buttons can use it)
    window.addToGroupModal = function(initialUser) {
        if(initialUser) {
            selectedUsers = [{ id: initialUser.id, name: initialUser.name, avatar: '' }]; // Add initial user
            renderChips();
        }
    };
    // ============================================================
    // MISSING LOGIC: SIDEBAR CONTEXT MENU (3-DOTS)
    // ============================================================
    const listContextMenu = document.getElementById('chatListContextMenu');
    
    if (listContextMenu && chatList) {
        // 1. OPEN MENU ON CLICK
        chatList.addEventListener('click', (e) => {
            if (e.target.classList.contains('btn-chat-item-options')) {
                e.stopPropagation();
                const btn = e.target;
                const chatItem = btn.closest('.chat-item');

                // Save target info globally
                window.contextMenuTargetId = chatItem.dataset.chatId;
                window.contextMenuTargetType = chatItem.dataset.chatType;
                const isMuted = chatItem.dataset.muted == '1';

                let menuHTML = '';
                const muteText = isMuted ? 'Unmute Notifications' : 'Mute Notifications';
                const muteIcon = isMuted ? '<i class="bx bx-volume-full"></i>' : '<i class="bx bx-volume-mute"></i>';
                
                // Common Option: Mute
                menuHTML += `<button class="chat-list-option" id="ctxMute"><span>${muteIcon}</span> ${muteText}</button>`;

                if (window.contextMenuTargetType === 'group') {
                    // Group Options
                    menuHTML += `<button class="chat-list-option" id="ctxMembers"><span><i class="bx bx-group"></i></span> View Members</button>`;
                    menuHTML += `<button class="chat-list-option danger" id="ctxDeleteGroup"><span><i class="bx bx-trash"></i></span> Initiate Deletion Vote</button>`;
                } else {
                    // User Options
                    menuHTML += `<button class="chat-list-option" id="ctxArchive"><span><i class="bx bx-archive"></i></span> Archive Chat</button>`;
                    menuHTML += `<button class="chat-list-option danger" id="ctxBlock"><span><i class="bx bx-block"></i></span> Block User</button>`;
                    menuHTML += `<button class="chat-list-option danger" id="ctxDeleteConvo"><span><i class="bx bx-trash"></i></span> Delete Conversation</button>`;
                }

                listContextMenu.innerHTML = menuHTML;
                
                // Positioning logic
                const rect = btn.getBoundingClientRect();
                listContextMenu.style.top = `${rect.bottom + 5}px`;
                // Adjust left position to prevent overflow
                listContextMenu.style.left = `${Math.max(10, rect.right - 200)}px`; 
                listContextMenu.classList.add('active');

                // Attach listeners to the NEW buttons immediately
                setTimeout(attachContextMenuListeners, 0);
            }
        });

      // --- // ========================================================
// MASTER GLOBAL CLICK HANDLER (All-in-One)
// ========================================================
document.addEventListener('click', function (e) {
    const target = e.target;

    // ----------------------------------------------------
    // SECTION 1: SIDEBAR & MODALS (Left Side UI)
    // ----------------------------------------------------

    // A. Sidebar Dropdown (Preferences, etc.)
    const sbDropdown = document.getElementById('sidebarDropdown');
    const sbBtn = document.getElementById('chatOptionsButton');
    if (sbDropdown && sbDropdown.classList.contains('active')) {
        if (!target.closest('#sidebarDropdown') && (!sbBtn || !target.closest('#chatOptionsButton'))) {
            sbDropdown.classList.remove('active');
        }
    }

    // B. Chat List Context Menu (Left side 3-dots)
    const ctxMenu = document.getElementById('chatListContextMenu');
    if (ctxMenu && ctxMenu.classList.contains('active')) {
        if (!target.closest('#chatListContextMenu')) {
            ctxMenu.classList.remove('active');
        }
    }

    // C. Close Modals on Overlay Click
    if (target.classList.contains('modal-overlay')) {
        target.style.display = 'none';
        if (target.id === 'callModal') {
            const minBtn = document.getElementById('btnMinimizeCall');
            if (minBtn) minBtn.click(); 
        }
    }

    // ----------------------------------------------------
    // SECTION 2: MESSAGE AREA INTERACTIONS (Right Side UI)
    // ----------------------------------------------------

    // D. Close Message Popups (Delete Menu / Emoji Picker)
    if (!target.closest('.message-dropdown-menu') && !target.closest('.reaction-picker-dropdown') && !target.closest('.btn-bubble-menu') && !target.closest('.btn-react-emoji')) {
        document.querySelectorAll('.message-dropdown-menu').forEach(el => el.remove());
        document.querySelectorAll('.reaction-picker-dropdown').forEach(el => el.remove());
    }

    // E. Toggle Message Actions (Bubble Hover Menu)
    const bubbleWrapper = target.closest('.message-wrapper');
    if (bubbleWrapper) {
        // Kung HINDI ka pumindot sa buttons (Reply, Emoji, Dots), i-toggle ang visibility
        if (!target.closest('.message-actions')) {
            document.querySelectorAll('.message-wrapper.actions-visible').forEach(w => {
                if (w !== bubbleWrapper) w.classList.remove('actions-visible');
            });
            bubbleWrapper.classList.toggle('actions-visible');
        }
    } else {
        // Kung click sa labas (empty space), isara lahat
        if (!target.closest('.message-actions') && !target.closest('.message-dropdown-menu') && !target.closest('.reaction-picker-dropdown')) {
            document.querySelectorAll('.message-wrapper.actions-visible').forEach(w => w.classList.remove('actions-visible'));
        }
    }

    // F. DELETE MENU (3 Dots Function)
    if (target.closest('.btn-bubble-menu')) {
        const btn = target.closest('.btn-bubble-menu');
        const msgId = btn.dataset.msgId;
        const wrapper = btn.closest('.message-wrapper');
        const isOutgoing = wrapper.classList.contains('outgoing');

        document.querySelectorAll('.message-dropdown-menu').forEach(el => el.remove());

        const menu = document.createElement('div');
        menu.className = 'message-dropdown-menu active';
        
        let menuHTML = `<button class="msg-menu-item" onclick="window.deleteMessage(${msgId}, 'me'); document.querySelector('.message-dropdown-menu').remove();"><span><i class=\"bx bx-trash\"></i></span> Delete for me</button>`;
        
        if (isOutgoing) {
            menuHTML += `<button class="msg-menu-item danger" onclick="window.deleteMessage(${msgId}, 'everyone'); document.querySelector('.message-dropdown-menu').remove();"><span><i class=\"bx bx-error\"></i></span> Unsend</button>`;
        }
        
        menu.innerHTML = menuHTML;
        document.body.appendChild(menu);

        const rect = btn.getBoundingClientRect();
        menu.style.top = (rect.bottom + 5) + 'px';
        menu.style.left = (rect.left - 100) + 'px'; 
    }

    // G. EMOJI PICKER (Smiley Function)
    if (target.closest('.btn-react-emoji')) {
        const btn = target.closest('.btn-react-emoji');
        const msgId = btn.dataset.msgId;

        document.querySelectorAll('.reaction-picker-dropdown').forEach(el => el.remove());

        const picker = document.createElement('div');
        picker.className = 'reaction-picker-dropdown';
        
        const emojis = ['👍', '❤️', '😂', '😮', '😢', '😡'];
        emojis.forEach(emo => {
            const b = document.createElement('button');
            b.className = 'reaction-opt';
            b.textContent = emo;
            b.onclick = () => { window.submitReaction(msgId, emo); picker.remove(); };
            picker.appendChild(b);
        });

        document.body.appendChild(picker);

        const rect = btn.getBoundingClientRect();
        picker.style.top = (rect.top - 50) + 'px';
        picker.style.left = (rect.left - 80) + 'px';
    }

    // H. REPLY BUTTON
    if (target.closest('.btn-reply')) {
        const btn = target.closest('.btn-reply');
        const wrapper = btn.closest('.message-wrapper');
        let text = wrapper.querySelector('.message-bubble p')?.textContent || 'Attachment';
        let name = wrapper.classList.contains('outgoing') ? 'You' : window.currentChatName;
        window.showReplyPreviewBar(btn.dataset.msgId, name, text);
    }

    // I. IMAGE PREVIEW
    if (target.classList.contains('chat-image')) {
        window.showImagePreview(target.src, target);
    }
});

        // 3. BUTTON ACTIONS
        function attachContextMenuListeners() {
            document.getElementById('ctxMute')?.addEventListener('click', () => {
                listContextMenu.classList.remove('active');
                const body = (window.contextMenuTargetType === 'group') ? 'group_id=' + window.contextMenuTargetId : 'user_id=' + window.contextMenuTargetId;
                fetch('messaging/ajax_toggle_mute.php', {
                    method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body
                }).then(() => { window.loadChatList(); window.refreshModalList('restricted'); });
            });

            document.getElementById('ctxArchive')?.addEventListener('click', () => {
                listContextMenu.classList.remove('active');
                window.archiveChat(window.contextMenuTargetId, window.contextMenuTargetType);
            });

            document.getElementById('ctxBlock')?.addEventListener('click', () => {
                listContextMenu.classList.remove('active');
                window.showCustomConfirm('Block User?', 'They won\'t be able to message you.', () => {
                    window.blockUser(window.contextMenuTargetId);
                });
            });

            document.getElementById('ctxDeleteConvo')?.addEventListener('click', () => {
                listContextMenu.classList.remove('active');
                window.showCustomConfirm('Delete Chat?', 'This will clear your history.', () => {
                    const fd = new URLSearchParams();
                    fd.append('action', 'delete_conversation');
                    fd.append('partner_id', window.contextMenuTargetId);
                    fetch('messaging/ajax_group_actions.php', { method: 'POST', body: fd })
                    .then(() => { window.showToast('Deleted', 'Conversation cleared.', '<i class="bx bx-trash"></i>', null, 'toast-success'); window.location.reload(); });
                });
            });

            document.getElementById('ctxDeleteGroup')?.addEventListener('click', () => {
                listContextMenu.classList.remove('active');
                window.currentChatId = window.contextMenuTargetId;
                document.getElementById('voteModal').style.display = 'flex';
            });
            
            document.getElementById('ctxMembers')?.addEventListener('click', () => {
                 listContextMenu.classList.remove('active');
                 window.currentChatId = window.contextMenuTargetId;
                 // Tawagin ang viewGroupMembers kung defined, o fetch manually
                 const memModal = document.getElementById('membersModal');
                 if(memModal) {
                     memModal.style.display = 'flex';
                     fetch('messaging/ajax_get_group_members.php?group_id=' + window.contextMenuTargetId)
                     .then(r=>r.text())
                     .then(h=>{ document.getElementById('membersListContainer').innerHTML = h; });
                 }
            });
        }
    }

   // =========================================
// KICK MEMBER FUNCTION (With Styled Confirm)
// =========================================
window.kickMember = function(groupId, memberId) {
    // 1. Use Custom Modal instead of native confirm
    window.showCustomConfirm(
        'Remove Member?', 
        'Are you sure you want to kick this user from the group? This action cannot be undone.', 
        function() {
            // Ito yung code na tatakbo PAG nag-YES
            executeKick(groupId, memberId);
        }
    );
};

// Helper function para malinis tignan
function executeKick(groupId, memberId) {
    const formData = new URLSearchParams();
    formData.append('group_id', groupId);
    formData.append('user_id', memberId);

    fetch('messaging/ajax_kick_member.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: formData
    })
    .then(r => r.text())
    .then(res => {
        if (res.trim() === 'success') {
            window.showToast('Removed', 'User has been kicked.', '<i class="bx bx-user-x"></i>', null, 'toast-success');
            
            // Refresh list
            const container = document.getElementById('membersListContainer');
            if (container) {
                fetch('messaging/ajax_get_group_members.php?group_id=' + groupId)
                .then(r => r.text())
                .then(html => container.innerHTML = html);
            }
        } else if (res.trim() === 'unauthorized') {
            window.showToast('Error', 'Only admins can kick members.', '<i class="bx bx-x-circle"></i>');
        } else {
            window.showToast('Error', 'Failed to remove user.', '<i class="bx bx-x-circle"></i>');
        }
    })
    .catch(err => console.error(err));
}// =========================================
// SUBMIT REACTION FUNCTION
// =========================================
window.submitReaction = function(msgId, emoji) {
    const formData = new URLSearchParams();
    formData.append('msg_id', msgId);
    formData.append('emoji', emoji);

    fetch('messaging/ajax_add_reaction.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: formData
    })
    .then(r => r.text())
    .then(res => {
        if (res.trim() === 'success') {
            // Refresh messages silently to show new reaction
            // Gagamitin natin ang existing function mo pero false ang scroll
            window.fetchAndDisplayMessages(window.currentChatId, window.currentChatType, false);
        } else {
            console.error('Reaction error:', res);
        }
    });
};

// =========================================
// UPLOAD LOGIC (ADD TO MESSAGING.JS)
// =========================================

// 1. Close Button Handler
document.getElementById('modalCloseButton')?.addEventListener('click', () => {
    document.getElementById('uploadModal').style.display = 'none';
});

// 2. Generic Upload Function
function handleFileUpload(inputElement) {
    const file = inputElement.files[0];
    if (!file) return;

    // Check Chat ID
    if (!window.currentChatId) {
        alert("Please select a chat first.");
        return;
    }

    // Show Loading Feedback
    if (typeof window.showToast === 'function') {
        window.showToast('Uploading...', 'Please wait while we send your file.', '<i class="bx bx-loader-alt"></i>');
    }

    // Prepare Data
    const formData = new FormData();
    formData.append('file', file);
    
    if (window.currentChatType === 'group') {
        formData.append('group_id', window.currentChatId);
    } else {
        formData.append('receiver_id', window.currentChatId);
    }

    // Send to Backend
    fetch('messaging/ajax_upload_file.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.text())
    .then(res => {
        // Reset Input
        inputElement.value = ''; 
        document.getElementById('uploadModal').style.display = 'none';

        if (res.trim() === 'success') {
            if (typeof window.showToast === 'function') {
                window.showToast('Sent', 'File uploaded successfully.', '<i class="bx bx-check"></i>', null, 'toast-success');
            }
            // Refresh Messages
            window.fetchAndDisplayMessages(window.currentChatId, window.currentChatType, true);
        } else {
            alert("Upload Failed: " + res);
        }
    })
    .catch(err => {
        console.error(err);
        alert("Connection Error during upload.");
    });
}

// 3. Attach Listeners to Inputs
const photoInput = document.getElementById('photoUpload');
const fileInput = document.getElementById('fileUpload');

if (photoInput) {
    photoInput.addEventListener('change', function() {
        handleFileUpload(this);
    });
}

if (fileInput) {
    fileInput.addEventListener('change', function() {
        handleFileUpload(this);
    });
}
// ==========================================
// HELPER FUNCTIONS (Updated Layout & Logic)
// ==========================================

// 1. Edit Name Logic (Using Custom Modal)
window.editChatName = function(id, type) {
    const currentName = document.getElementById('detailsChatName').textContent;
    const modal = document.getElementById('renameModal');
    const input = document.getElementById('renameInput');
    const saveBtn = document.getElementById('btnSaveNickname');

    // Setup Modal
    input.value = currentName;
    modal.style.display = 'flex';
    input.focus();

    // Handle Save Click
    saveBtn.onclick = function() {
        const newName = input.value.trim();
        
        if (newName && newName !== currentName) {
            const formData = new URLSearchParams();
            formData.append('type', type);
            formData.append('target_id', id);
            formData.append('new_name', newName);

            fetch('messaging/ajax_update_name.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: formData
            })
            .then(r => r.text())
            .then(res => {
                if (res.trim() === 'success') {
                    // Update UI immediately
                    document.getElementById('detailsChatName').textContent = newName;
                    document.querySelector('.chat-header h3').textContent = newName;
                    
                    window.loadChatList(); // Refresh list sidebar
                    window.showToast('Updated', 'Nickname changed.', '<i class="bx bx-check"></i>', null, 'toast-success');
                    modal.style.display = 'none';
                } else {
                    alert('Failed to update name. ' + res);
                }
            })
            .catch(err => console.error(err));
        } else {
            modal.style.display = 'none'; // Just close if empty or same
        }
    };
    
    // Allow Enter key to save
    input.onkeyup = function(e) {
        if(e.key === 'Enter') saveBtn.click();
    };
};

// 2. Switch Tabs Logic (Cleaner)
window.switchMediaTab = function(tabName) {
    const btns = document.querySelectorAll('.media-tab');
    btns.forEach(b => {
        // Check text content to match button logic
        if(b.textContent.toLowerCase().includes(tabName === 'media' ? 'photos' : 'files')) {
            b.classList.add('active');
        } else {
            b.classList.remove('active');
        }
    });

    const mediaContent = document.getElementById('tab-media-content');
    const fileContent = document.getElementById('tab-files-content');

    if (tabName === 'media') {
        mediaContent.style.display = 'grid';
        fileContent.style.display = 'none';
    } else {
        mediaContent.style.display = 'none';
        fileContent.style.display = 'flex'; // Use flex for list
    }
};
// ==========================================
// HELPER FUNCTIONS (Fixed)
// ==========================================

// 1. Edit Name Logic (AUTO REFRESH FIX)
window.editChatName = function(id, type) {
    const currentName = document.getElementById('detailsChatName').textContent;
    const modal = document.getElementById('renameModal');
    const input = document.getElementById('renameInput');
    const saveBtn = document.getElementById('btnSaveNickname');
    const resetBtn = document.getElementById('btnResetNickname');

    // Show Modal
    input.value = currentName;
    modal.style.display = 'flex';
    input.focus();

    // Function to handle API call
    const performUpdate = (nameVal) => {
        const formData = new URLSearchParams();
        formData.append('type', type);
        formData.append('target_id', id);
        formData.append('new_name', nameVal);

        fetch('messaging/ajax_update_name.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: formData
        })
        .then(r => r.text())
        .then(res => {
            if (res.trim() === 'success') {
                // [FIX] FORCE RELOAD PAGE TO PREVENT STUCK STATE
                window.location.reload(); 
            } else {
                alert('Failed: ' + res);
            }
        })
        .catch(err => {
            console.error(err);
            alert("Connection error");
        });
    };

    // Attach Listeners (Clone to remove old listeners)
    const newSave = saveBtn.cloneNode(true);
    saveBtn.parentNode.replaceChild(newSave, saveBtn);
    
    newSave.onclick = () => {
        const newName = input.value.trim();
        if (newName && newName !== currentName) performUpdate(newName);
        else modal.style.display = 'none';
    };

    const newReset = resetBtn.cloneNode(true);
    resetBtn.parentNode.replaceChild(newReset, resetBtn);
    
                newReset.onclick = () => {
                    if (typeof window.askConfirm === 'function') {
                        window.askConfirm('Reset nickname?', 'Reset nickname to original?').then(ok => { if (ok) performUpdate(''); });
                    } else {
                        // extreme fallback: reset without confirmation
                        performUpdate('');
                    }
                };
};

// 2. Switch Media Tabs (Correct Visibility)
window.switchMediaTab = function(tabName) {
    // Toggle Buttons
    const buttons = document.querySelectorAll('.media-tab');
    buttons.forEach(btn => {
        if (btn.textContent.toLowerCase().includes(tabName)) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });

    // Toggle Content Areas
    const mediaDiv = document.getElementById('tab-media-content');
    const filesDiv = document.getElementById('tab-files-content');

    if (tabName === 'media') {
        if(mediaDiv) mediaDiv.style.display = 'block'; // Block kasi may headers na tayo
        if(filesDiv) filesDiv.style.display = 'none';
    } else {
        if(mediaDiv) mediaDiv.style.display = 'none';
        if(filesDiv) filesDiv.style.display = 'block';
    }
};
// ==============================================================
// HELPER FUNCTIONS (Ilagay sa PINAKA-DULO ng messaging.js)
// ==============================================================

// 1. TOGGLE MEDIA ACCORDION (Para bumukas/sumara)
window.toggleMediaAccordion = function() {
    console.log("Media Accordion Clicked!"); // Check console kung lumalabas ito

    const content = document.getElementById('mediaAccordionContent');
    const header = document.getElementById('mediaAccordionHeader');
    
    if (!content || !header) {
        console.error("Accordion elements not found!");
        return;
    }

    // Check visibility
    if (content.style.display === 'none' || content.style.display === '') {
        // OPEN
        content.style.display = 'block';
        header.classList.add('active');
        window.fetchMediaFiles(); // Load content
    } else {
        // CLOSE
        content.style.display = 'none';
        header.classList.remove('active');
    }
};

// 2. FETCH MEDIA FILES (Kukuha sa Database)
window.fetchMediaFiles = function() {
    if (!window.currentChatId) return;

    const formData = new URLSearchParams();
    formData.append('target_id', window.currentChatId);
    formData.append('chat_type', window.currentChatType);

    fetch('messaging/ajax_fetch_media.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        // Update HTML Content
        const mediaDiv = document.getElementById('tab-media-content');
        const filesDiv = document.getElementById('tab-files-content');
        
        if (mediaDiv) mediaDiv.innerHTML = data.media;
        if (filesDiv) filesDiv.innerHTML = data.files;
    })
    .catch(err => console.error("Media Load Error:", err));
};

// 3. SWITCH TABS (Photos vs Files)
window.switchMediaTab = function(tabName) {
    // A. Update Buttons Active State
    const buttons = document.querySelectorAll('.media-tab');
    buttons.forEach(btn => {
        // Simple string match kasi minsan 'Photos' ang text, minsan 'Media'
        const txt = btn.textContent.toLowerCase();
        if ((tabName === 'media' && txt.includes('photo')) || 
            (tabName === 'files' && txt.includes('file'))) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });

    // B. Toggle Content Area
    const mediaDiv = document.getElementById('tab-media-content');
    const filesDiv = document.getElementById('tab-files-content');

    if (tabName === 'media') {
        if (mediaDiv) mediaDiv.style.display = 'grid'; // Grid for photos
        if (filesDiv) filesDiv.style.display = 'none';
    } else {
        if (mediaDiv) mediaDiv.style.display = 'none';
        if (filesDiv) filesDiv.style.display = 'flex'; // Flex list for files
    }
};

// 4. RENAME LOGIC (Fix Loading Stuck)
window.editChatName = function(id, type) {
    const currentName = document.getElementById('detailsChatName').textContent;
    const modal = document.getElementById('renameModal');
    const input = document.getElementById('renameInput');
    const saveBtn = document.getElementById('btnSaveNickname');
    const resetBtn = document.getElementById('btnResetNickname');

    // Open Modal
    if(input) input.value = currentName;
    if(modal) modal.style.display = 'flex';
    if(input) input.focus();

    // Helper to Send Request
    const performUpdate = (nameVal) => {
        const formData = new URLSearchParams();
        formData.append('type', type);
        formData.append('target_id', id);
        formData.append('new_name', nameVal);

        fetch('messaging/ajax_update_name.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: formData
        })
        .then(r => r.text())
        .then(res => {
            if (res.trim() === 'success') {
                // [FIX] Force Reload para iwas stuck sa loading
                window.location.reload(); 
            } else {
                alert('Failed: ' + res);
            }
        });
    };

    // Attach Listeners (Cloning to avoid duplicate events)
    if (saveBtn) {
        const newSave = saveBtn.cloneNode(true);
        saveBtn.parentNode.replaceChild(newSave, saveBtn);
        newSave.onclick = () => {
            const newName = input.value.trim();
            if (newName && newName !== currentName) performUpdate(newName);
            else modal.style.display = 'none';
        };
    }

    if (resetBtn) {
        const newReset = resetBtn.cloneNode(true);
        resetBtn.parentNode.replaceChild(newReset, resetBtn);
        newReset.onclick = () => {
                if (typeof window.askConfirm === 'function') {
                    window.askConfirm('Reset nickname?', 'Reset nickname to original?').then(ok => { if (ok) performUpdate(''); });
                } else {
                    // extreme fallback: reset without confirmation
                    performUpdate('');
                }
            };
    }
};
// 2. Switch Media Tabs (STRICT HIDE/SHOW FIX)
window.switchMediaTab = function(tabName) {
    // A. Update Buttons Highlight
    const buttons = document.querySelectorAll('.media-tab');
    buttons.forEach(btn => {
        const txt = btn.textContent.toLowerCase();
        if ((tabName === 'media' && txt.includes('photo')) || 
            (tabName === 'files' && txt.includes('file'))) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });

    // B. Toggle Content (The Fix)
    const mediaDiv = document.getElementById('tab-media-content');
    const filesDiv = document.getElementById('tab-files-content');

    if (!mediaDiv || !filesDiv) return;

    if (tabName === 'media') {
        // Show Media, Force Hide Files
        mediaDiv.style.setProperty('display', 'block', 'important');
        filesDiv.style.setProperty('display', 'none', 'important');
    } else {
        // Force Hide Media, Show Files
        mediaDiv.style.setProperty('display', 'none', 'important');
        filesDiv.style.setProperty('display', 'flex', 'important');
    }
};

// SPA teardown for messaging: clear intervals, stop media, and destroy peer
window.spaTeardown = function() {
    try { if (window.chatListInterval) { clearInterval(window.chatListInterval); window.chatListInterval = null; } } catch(e) {}
    try { if (window.pollRequestsInterval) { clearInterval(window.pollRequestsInterval); window.pollRequestsInterval = null; } } catch(e) {}
    try { if (window.callTimerInterval) { clearInterval(window.callTimerInterval); window.callTimerInterval = null; } } catch(e) {}
    try { if (window.messagePollingInterval) { clearInterval(window.messagePollingInterval); window.messagePollingInterval = null; } } catch(e) {}
    try { if (window.statusPollingInterval) { clearInterval(window.statusPollingInterval); window.statusPollingInterval = null; } } catch(e) {}
    try { if (window.currentCall) { window.currentCall.close(); window.currentCall = null; } } catch(e) {}
    try {
        if (window.localStream) {
            window.localStream.getTracks().forEach(track => track.stop());
            window.localStream = null;
        }
    } catch(e) {}
    try { if (window.peer && typeof window.peer.destroy === 'function') { window.peer.destroy(); window.peer = null; } } catch(e) {}
    try { window.galleryImages = []; window.currentGalleryIndex = 0; } catch(e) {}
    // Clear large arrays / conversation caches if present
    try { if (window.geminiConversation) { window.geminiConversation = []; } } catch(e) {}
    // Remove any lingering timeouts we may have created
    try { if (window.markReadTimeout) { clearTimeout(window.markReadTimeout); window.markReadTimeout = null; } } catch(e) {}
};