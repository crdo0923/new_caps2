// ========================================
// PROFILE PAGE FUNCTIONALITY (Cleaned & Updated)
// ========================================

document.addEventListener('DOMContentLoaded', function() {
    
    // ========================================
    // 1. PHOTO UPLOAD PREVIEW (Avatar)
    // ========================================
    const photoUpload = document.getElementById('photoUpload');
    if (photoUpload) {
        photoUpload.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    const imgPath = event.target.result;
                    const photoWrapper = document.querySelector('.profile-photo-wrapper');
                    let existingPhoto = photoWrapper.querySelector('.profile-photo');
                    const existingAvatar = photoWrapper.querySelector('.default-avatar');
                    
                    if (existingPhoto) {
                        existingPhoto.src = imgPath;
                    } else {
                        if (existingAvatar) existingAvatar.style.display = 'none';
                        const img = document.createElement('img');
                        img.src = imgPath;
                        img.className = 'profile-photo';
                        img.id = 'mainProfilePhoto'; 
                        img.alt = 'Preview';
                        photoWrapper.insertBefore(img, photoWrapper.firstChild);
                    }
                };
                reader.readAsDataURL(file);
            }
        });
    }
    
    // ========================================
    // 2. COVER PHOTO HANDLER (Instant Button Switch)
    // ========================================
    const coverUploadTrigger = document.getElementById('coverPhotoUpload'); // Label input
    const coverUploadFile = document.getElementById('coverPhotoUploadFile'); // Actual form input
    const profileBanner = document.getElementById('profileBanner');
    const editCoverBtn = document.getElementById('editCoverBtn');
    const saveCoverBtn = document.getElementById('saveCoverBtn');
    const coverPhotoForm = document.getElementById('cover-photo-form');

    if (coverUploadTrigger && coverUploadFile && profileBanner) {
        
        // Clicks the hidden actual form input when the dummy label input changes
        coverUploadTrigger.addEventListener('change', function(e) {
             // Transfer the file from dummy input to actual input if possible, 
             // BUT file inputs are read-only for security.
             // BETTER APPROACH: Use the label to trigger the ACTUAL input directly.
             // We will change the HTML structure slightly in PHP to make the label target the actual input ID.
             // UPDATE: The HTML in PHP has been updated. The label now points to 'coverPhotoUpload' which is a dummy.
             // FIX in JS: When editCoverBtn (label) is clicked, we prevent default and click the real input.
        });
        
        // Correct Logic: Use the Label to Trigger the Real Input
        const realInput = document.getElementById('coverPhotoUploadFile');
        const triggerLabel = document.getElementById('editCoverBtn');
        
        if(triggerLabel && realInput) {
            triggerLabel.addEventListener('click', function(e) {
                e.preventDefault();
                realInput.click();
            });

            realInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(event) {
                        const imgPath = event.target.result;
                        
                        // 1. Preview
                        profileBanner.style.backgroundImage = `url('${imgPath}')`;
                        
                        // 2. Swap Buttons
                        if(triggerLabel) triggerLabel.style.display = 'none'; // Hide Edit
                        if(coverPhotoForm) coverPhotoForm.style.display = 'flex'; // Show Save Form
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
    }

    // ========================================
    // 3. CHAR COUNT
    // ========================================
    const bioTextarea = document.querySelector('.form-textarea');
    const charCount = document.querySelector('.char-count');
    if (bioTextarea && charCount) {
        const updateCharCount = () => {
            const length = bioTextarea.value.length;
            const maxLength = 200;
            charCount.textContent = `${length} / ${maxLength}`;
            if (length > maxLength) charCount.style.color = 'var(--danger)';
            else if (length > maxLength * 0.9) charCount.style.color = 'var(--warning)';
            else charCount.style.color = 'var(--text-gray)';
        };
        updateCharCount();
        bioTextarea.addEventListener('input', updateCharCount);
    }

    // ========================================
    // 4. SAVE BUTTON ANIMATION
    // ========================================
    const saveButton = document.getElementById('saveProfile');
    const mainForm = document.getElementById('main-profile-form');
    
    if (mainForm && saveButton) {
        mainForm.addEventListener('submit', function() {
            if(saveButton.style.display !== 'none') {
                saveButton.innerHTML = '<span>⏳</span> Saving...';
                saveButton.style.opacity = '0.8';
                saveButton.style.cursor = 'wait';
            }
        });
    }
    
    // Auto-hide success message
    const successMessageDiv = document.querySelector('div[style*="background-color: #10b981"]');
    if (successMessageDiv) {
        setTimeout(() => {
            successMessageDiv.style.transition = 'opacity 1s';
            successMessageDiv.style.opacity = '0';
            setTimeout(() => successMessageDiv.remove(), 1000);
        }, 3000);
    }
});