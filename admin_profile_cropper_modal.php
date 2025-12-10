<?php
// admin_profile_cropper_modal.php
// Simple reusable modal for cropping profile picture
?>

<!-- CROP PROFILE PICTURE MODAL -->
<div
    id="cropperModal"
    class="modal hidden"
    aria-hidden="true"
>
  <div class="modal-backdrop">
    <div class="modal-panel">
      <h2 class="modal-title">
        <i class="fas fa-image mr-2 text-[var(--cvsu-green)]"></i>
        Adjust Profile Picture
      </h2>

      <div class="modal-body">
        <!-- Image to be cropped -->
        <img id="cropperImage" class="crop-img" alt="Profile preview for cropping" />

        <p class="modal-helper-text">
          Drag the image and zoom to fit inside the circle.
        </p>

        <!-- ✅ Requirements text -->
        <p class="mt-2 text-xs text-gray-500">
          <span class="font-semibold">Note:</span>
          Only <span class="font-semibold">JPG, JPEG, PNG</span> files are allowed.
          Maximum file size is <span class="font-semibold">3&nbsp;MB</span>.
        </p>
      </div>

      <div class="modal-actions">
        <button
          type="button"
          id="cancelCrop"
          class="btn-cancel"
        >
          Cancel
        </button>
        <button
          type="button"
          id="saveCrop"
          class="btn-save"
        >
          Save
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Hidden file input (triggered by “Change” button) -->
<input
  type="file"
  id="profileInput"
  accept=".jpg,.jpeg,.png,image/jpeg,image/png"
  class="hidden"
/>
