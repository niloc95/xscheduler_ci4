export function initProviderPicker(root) {
  const picker = root?.querySelector('[data-provider-picker]');
  if (!picker) return;

  const checkboxes = Array.from(picker.querySelectorAll('input[name="provider_ids[]"]'));
  const countLabel = picker.querySelector('[data-provider-selection-count]');
  const selectAllBtn = picker.querySelector('[data-provider-picker-action="select-all"]');
  const clearAllBtn = picker.querySelector('[data-provider-picker-action="clear-all"]');

  const sync = () => {
    let count = 0;

    checkboxes.forEach((cb) => {
      const card = cb.closest('label');
      if (!card) return;

      if (cb.checked) {
        count += 1;
        card.classList.add('border-primary-300', 'bg-primary-50/70', 'dark:border-primary-700', 'dark:bg-primary-900/20');
        card.classList.remove('border-gray-200', 'dark:border-gray-700');
      } else {
        card.classList.remove('border-primary-300', 'bg-primary-50/70', 'dark:border-primary-700', 'dark:bg-primary-900/20');
        card.classList.add('border-gray-200', 'dark:border-gray-700');
      }
    });

    if (countLabel) {
      countLabel.textContent = String(count);
    }
  };

  checkboxes.forEach((cb) => cb.addEventListener('change', sync));

  if (selectAllBtn) {
    selectAllBtn.addEventListener('click', () => {
      checkboxes.forEach((cb) => {
        cb.checked = true;
      });
      sync();
    });
  }

  if (clearAllBtn) {
    clearAllBtn.addEventListener('click', () => {
      checkboxes.forEach((cb) => {
        cb.checked = false;
      });
      sync();
    });
  }

  sync();
}

export function initServiceManagementForms() {
  const form = document.getElementById('createServiceForm') || document.getElementById('editServiceForm');
  if (!form) {
    return;
  }

  if (typeof window.initProviderPicker === 'function' && form.dataset.providerPickerInitialized !== 'true') {
    form.dataset.providerPickerInitialized = 'true';
    window.initProviderPicker(form);
  }

  form.dataset.serviceViewInitialized = 'true';
}

export function togglePassword(fieldId) {
  const field = document.getElementById(fieldId);
  const icon = document.getElementById(`${fieldId}-icon`);
  if (!field || !icon) return;

  if (field.type === 'password') {
    field.type = 'text';
    icon.textContent = 'visibility_off';
  } else {
    field.type = 'password';
    icon.textContent = 'visibility';
  }
}
