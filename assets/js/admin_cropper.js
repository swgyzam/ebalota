// admin_cropper.js
// Handles profile picture cropping & upload for admin_profile.php

document.addEventListener('DOMContentLoaded', function () {

    // UI elements
    const openBtn    = document.getElementById('openCropper');
    const modal      = document.getElementById('cropperModal');
    const cropImg    = document.getElementById('cropperImage');
    const inputFile  = document.getElementById('profileInput');
    const cancelBtn  = document.getElementById('cancelCrop');
    const saveBtn    = document.getElementById('saveCrop');
    const preview    = document.getElementById('profilePreview');

    // Success Modal
    const successModal   = document.getElementById('profileSuccessModal');
    const successMessage = document.getElementById('profileSuccessMessage');
    const successOkBtn   = document.getElementById('profileSuccessOk');

    // If one required element is missing → do nothing
    if (!openBtn || !modal || !cropImg || !inputFile || !cancelBtn || !saveBtn || !preview) {
        return;
    }

    let cropper = null;

    // Circle mask styling for Cropper.js (Facebook-style)
    const style = document.createElement('style');
    style.innerHTML = `
      .cropper-view-box,
      .cropper-face {
          border-radius: 50% !important;
      }
      .cropper-modal {
          background: rgba(0,0,0,0.35) !important;
      }
    `;
    document.head.appendChild(style);

    // Open file input
    openBtn.addEventListener('click', function () {
        inputFile.click();
    });

    // When user chooses a file
    inputFile.addEventListener('change', function (e) {
        const file = e.target.files[0];
        if (!file) return;

        // ✅ client-side validation
        const maxSize = 3 * 1024 * 1024; // 3 MB
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];

        if (!allowedTypes.includes(file.type)) {
            alert('Invalid image format. Please upload a JPG or PNG file.');
            inputFile.value = '';
            return;
        }

        if (file.size > maxSize) {
            alert('Image is too large. Maximum allowed size is 3 MB.');
            inputFile.value = '';
            return;
        }

        const reader = new FileReader();
        reader.onload = function () {
            cropImg.src = reader.result;
            modal.classList.remove('hidden');

            if (cropper) {
                cropper.destroy();
            }

            cropper = new Cropper(cropImg, {
                aspectRatio: 1,
                viewMode: 1,
                dragMode: 'move',
                background: false,
                guides: false,
                autoCropArea: 1,
            });
        };
        reader.readAsDataURL(file);
    });

    // Cancel button
    cancelBtn.addEventListener('click', function () {
        modal.classList.add('hidden');
        inputFile.value = '';
        if (cropper) {
            cropper.destroy();
            cropper = null;
        }
    });

    // Success modal OK button → reload page
    if (successOkBtn) {
        successOkBtn.addEventListener('click', hideSuccessModalAndReload);
    }    

    // Save cropped image
    saveBtn.addEventListener('click', function () {
        if (!cropper) return;

        cropper.getCroppedCanvas({
            width: 400,
            height: 400,
        }).toBlob(function (blob) {

            const formData = new FormData();
            formData.append('image', blob, 'admin_profile.jpg');

            fetch('upload_admin_profile_picture.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.text())
            .then(text => {

                console.log('RAW RESPONSE:', text);

                let data;
                try {
                    data = JSON.parse(text);
                } catch (err) {
                    alert("Server returned non-JSON response:\n" + text);
                    return;
                }

                if (data.status === 'success') {

                    // Update main preview instantly
                    preview.src = data.newPath + "?v=" + Date.now();

                    // Remove initials overlay if exists
                    const initialsOverlay = document.querySelector('.profile-initials-overlay');
                    if (initialsOverlay) initialsOverlay.style.display = 'none';

                    // Update sidebar avatar if exists
                    const sidebarAvatar = document.getElementById('sidebarProfileAvatar');
                    if (sidebarAvatar) {
                        sidebarAvatar.src = data.newPath + "?v=" + Date.now();
                    }

                    // Close crop modal
                    modal.classList.add('hidden');
                    inputFile.value = '';
                    cropper.destroy();
                    cropper = null;

                    // SHOW PREMIUM SUCCESS MODAL
                    showSuccessModal();

                } else {
                    alert("Upload error: " + (data.message || "Unknown error"));
                }
            })
            .catch(err => {
                console.error(err);
                alert("Network error: " + err);
            });

        }, 'image/jpeg', 0.95);
    });

});
