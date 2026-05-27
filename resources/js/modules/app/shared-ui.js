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

export function initHelpFaq(root = document) {
  const scope = root?.body || root;
  if (!scope || scope.dataset.xsHelpFaqBound === 'true') {
    return;
  }

  scope.dataset.xsHelpFaqBound = 'true';

  scope.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-faq-toggle]');
    if (!toggle) {
      return;
    }

    const item = toggle.closest('[data-faq-item]');
    const content = item?.querySelector('.faq-content');
    const icon = toggle.querySelector('.material-symbols-outlined');

    if (!content) {
      return;
    }

    const isHidden = content.classList.contains('hidden');
    content.classList.toggle('hidden', !isHidden);

    if (icon) {
      icon.classList.toggle('rotate-180', isHidden);
    }
  });
}

export function initConfirmActions(root = document) {
  const scope = root?.body || root;
  if (!scope || scope.dataset.xsConfirmActionsBound === 'true') {
    return;
  }

  scope.dataset.xsConfirmActionsBound = 'true';

  scope.addEventListener('submit', async (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) {
      return;
    }

    const message = form.dataset.confirmMessage;
    if (!message) {
      return;
    }

    event.preventDefault();
    const confirmed = await window.XSConfirm.show({ message, danger: true });
    if (confirmed) {
      delete form.dataset.confirmMessage;
      form.requestSubmit();
    }
  }, true);
}

export function initPasswordToggles(root = document) {
  const scope = root?.body || root;
  if (!scope || scope.dataset.xsPasswordTogglesBound === 'true') {
    return;
  }

  scope.dataset.xsPasswordTogglesBound = 'true';

  scope.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-password-toggle]');
    if (!toggle) {
      return;
    }

    const fieldId = toggle.dataset.passwordToggle;
    if (!fieldId) {
      return;
    }

    togglePassword(fieldId);
  });
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
