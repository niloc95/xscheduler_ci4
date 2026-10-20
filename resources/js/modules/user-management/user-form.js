/**
 * User create/edit form behaviour (user-management/create, user-management/edit).
 *
 * - Shows provider-only / staff-only sections as role checkboxes change.
 * - Previews a newly chosen provider profile image and handles "remove image".
 *
 * Idempotent: every element is bound once via a dataset guard, so it is safe to
 * call on initial load and after each `spa:navigated`.
 */

const PROVIDER_SECTIONS = [
  '#providerLocationsWrapper',
  '#providerScheduleSection',
  '#providerPublicProfileSection',
  '#providerAssignmentsSection',
  '[data-provider-staff-manager]',
];

const STAFF_SECTIONS = [
  '#staffAssignmentsSection',
  '[data-staff-providers-manager]',
];

function toggleSections(selectors, visible) {
  selectors.forEach((selector) => {
    document.querySelectorAll(selector).forEach((el) => el.classList.toggle('hidden', !visible));
  });
}

function updateSectionVisibility() {
  const isProvider = document.getElementById('role_provider')?.checked || false;
  const isStaff = document.getElementById('role_staff')?.checked || false;

  toggleSections(PROVIDER_SECTIONS, isProvider);
  toggleSections(STAFF_SECTIONS, isStaff);
}

function initRoleSectionVisibility() {
  const roleCheckboxes = document.querySelectorAll('input[name="roles[]"]');
  if (!roleCheckboxes.length) {
    return;
  }

  roleCheckboxes.forEach((checkbox) => {
    if (checkbox.dataset.roleVisibilityBound === 'true') return;
    checkbox.dataset.roleVisibilityBound = 'true';
    checkbox.addEventListener('change', updateSectionVisibility);
  });

  updateSectionVisibility();
}

function initProviderImagePreview() {
  const imageInput = document.querySelector('[data-provider-image-input]');
  const removeImage = document.querySelector('[data-provider-image-remove]');
  const imagePreview = document.getElementById('providerProfileImagePreview');
  const imageFallback = document.getElementById('providerProfileImageFallback');

  if (!imagePreview) {
    return;
  }

  if (imageInput && imageInput.dataset.previewBound !== 'true') {
    imageInput.dataset.previewBound = 'true';
    imageInput.addEventListener('change', () => {
      const file = imageInput.files?.[0] ?? null;
      if (!file) return;

      if (removeImage) removeImage.checked = false;

      imagePreview.src = URL.createObjectURL(file);
      imagePreview.classList.remove('hidden');
      imageFallback?.classList.add('hidden');
    });
  }

  if (removeImage && removeImage.dataset.previewBound !== 'true') {
    removeImage.dataset.previewBound = 'true';
    removeImage.addEventListener('change', () => {
      if (!removeImage.checked) return;

      if (imageInput) imageInput.value = '';

      imagePreview.src = '';
      imagePreview.classList.add('hidden');
      imageFallback?.classList.remove('hidden');
    });
  }
}

export function initUserForm() {
  initRoleSectionVisibility();
  initProviderImagePreview();
}
