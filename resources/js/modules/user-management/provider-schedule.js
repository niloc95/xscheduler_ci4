function createNotifier() {
  return {
    warning(title, message) {
      window.XSNotify?.toast?.({ type: 'warning', title, message }) ?? window.alert?.(message);
    },
    success(title, message) {
      window.XSNotify?.toast?.({ type: 'success', title, message }) ?? console.log(message);
    },
  };
}

export function initProviderSchedule() {
  const scheduleSection = document.querySelector('[data-provider-schedule-section]');
  if (!scheduleSection || scheduleSection.dataset.providerScheduleInitialized === 'true') {
    return;
  }

  const roleSelect = document.getElementById('role');
  if (!roleSelect) {
    return;
  }

  const notifier = createNotifier();
  const sourceDayKey = scheduleSection.dataset.sourceDay || 'monday';
  const copyBtn = scheduleSection.querySelector('[data-copy-schedule]');

  const setCopyButtonDisabled = (disabled) => {
    if (!copyBtn) return;
    copyBtn.disabled = disabled;
    copyBtn.setAttribute('aria-disabled', disabled ? 'true' : 'false');
  };

  const getSourceRow = () => scheduleSection.querySelector(`[data-schedule-day="${sourceDayKey}"]`);
  const getField = (row, field) => row ? row.querySelector(`[data-field="${field}"]`) : null;

  const parseToMinutes = (value) => {
    if (!value) return null;
    const match = value.trim().match(/^([01]?\d|2[0-3]):([0-5]\d)$/);
    if (!match) return null;
    return parseInt(match[1], 10) * 60 + parseInt(match[2], 10);
  };

  const isValidTimeRange = (start, end) => {
    const startMinutes = parseToMinutes(start);
    const endMinutes = parseToMinutes(end);
    if (startMinutes === null || endMinutes === null) return false;
    return startMinutes < endMinutes;
  };

  const toggleDayInputs = (dayRow, isActive) => {
    if (!dayRow) return;

    dayRow.querySelectorAll('[data-time-input]').forEach((input) => {
      input.disabled = !isActive;
      if (input.dataset.field === 'start' || input.dataset.field === 'end') {
        input.required = Boolean(isActive);
      } else {
        input.required = false;
      }
      input.classList.toggle('opacity-70', !isActive);
    });

    const locContainer = dayRow.querySelector('.schedule-location-checkboxes');
    if (locContainer) {
      locContainer.classList.toggle('opacity-40', !isActive);
      locContainer.classList.toggle('pointer-events-none', !isActive);
      locContainer.querySelectorAll('input[type="checkbox"]').forEach((checkbox) => {
        checkbox.disabled = !isActive;
      });
    }
  };

  const setScheduleSectionEnabled = (enabled) => {
    scheduleSection.querySelectorAll('[data-schedule-day]').forEach((row) => {
      const checkbox = row.querySelector('.js-day-active');
      const isActive = Boolean(enabled && checkbox && checkbox.checked);

      if (checkbox) {
        checkbox.disabled = !enabled;
      }

      const hiddenActiveInput = row.querySelector('input[type="hidden"][name$="[is_active]"]');
      if (hiddenActiveInput) {
        hiddenActiveInput.disabled = !enabled;
      }

      toggleDayInputs(row, isActive);
    });
  };

  const updateCopyButtonState = () => {
    if (!copyBtn) return;
    if (scheduleSection.classList.contains('hidden')) {
      setCopyButtonDisabled(true);
      return;
    }

    const sourceRow = getSourceRow();
    const checkbox = sourceRow ? sourceRow.querySelector('.js-day-active') : null;
    const startInput = getField(sourceRow, 'start');
    const endInput = getField(sourceRow, 'end');
    const enabled = Boolean(
      checkbox && checkbox.checked && startInput && endInput && isValidTimeRange(startInput.value, endInput.value)
    );

    setCopyButtonDisabled(!enabled);
  };

  const toggleScheduleSection = (roleValue) => {
    const isProvider = roleValue === 'provider';

    const wrapper = document.getElementById('providerScheduleSection');
    if (wrapper) wrapper.classList.toggle('hidden', !isProvider);

    setScheduleSectionEnabled(isProvider);

    const locationsWrapper = document.getElementById('providerLocationsWrapper');
    if (locationsWrapper) locationsWrapper.classList.toggle('hidden', !isProvider);

    const providerAssignments = document.getElementById('providerAssignmentsSection');
    if (providerAssignments) providerAssignments.classList.toggle('hidden', !isProvider);

    const staffAssignments = document.getElementById('staffAssignmentsSection');
    if (staffAssignments) staffAssignments.classList.toggle('hidden', roleValue !== 'staff');

    document.querySelectorAll('.provider-color-field').forEach((field) => {
      field.classList.toggle('hidden', !isProvider);
    });

    const roleDesc = document.getElementById('role-description');
    const rolePerms = document.getElementById('role-permissions');
    if (roleValue) {
      const descriptions = {
        admin: 'Full system access including settings, user management, and all features.',
        provider: 'Can manage own calendar, create staff, manage services and categories.',
        staff: 'Limited to managing own calendar and assigned appointments. Provider assignments managed after creation.',
      };

      if (rolePerms) rolePerms.innerHTML = descriptions[roleValue] || '';
      if (roleDesc) roleDesc.classList.remove('hidden');
    } else if (roleDesc) {
      roleDesc.classList.add('hidden');
    }

    if (!isProvider) {
      setCopyButtonDisabled(true);
      return;
    }

    updateCopyButtonState();
  };

  const applyValues = (row, checkbox, values, activateIfNeeded) => {
    if (!row) return;

    const shouldActivate = Boolean(activateIfNeeded && checkbox && !checkbox.checked);
    if (shouldActivate) {
      checkbox.checked = true;
      checkbox.dispatchEvent(new Event('change', { bubbles: true }));
    }

    ['start', 'end', 'break_start', 'break_end'].forEach((key) => {
      const value = values[key];
      if (!value && (key === 'break_start' || key === 'break_end')) {
        return;
      }

      const input = getField(row, key);
      if (!input || input.disabled) {
        return;
      }

      if (value) {
        input.value = value;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });
  };

  const handleCopyClick = () => {
    const sourceRow = getSourceRow();
    if (!sourceRow) return;

    const checkbox = sourceRow.querySelector('.js-day-active');
    const startInput = getField(sourceRow, 'start');
    const endInput = getField(sourceRow, 'end');
    const breakStartInput = getField(sourceRow, 'break_start');
    const breakEndInput = getField(sourceRow, 'break_end');
    const start = startInput?.value || '';
    const end = endInput?.value || '';

    if (!checkbox || !checkbox.checked) {
      setCopyButtonDisabled(true);
      return;
    }

    if (!start || !end) {
      notifier.warning('Incomplete schedule', 'Set start and end times before copying.');
      return;
    }

    if (!isValidTimeRange(start, end)) {
      notifier.warning('Invalid time range', 'Start time must be earlier than end time.');
      return;
    }

    const values = {
      start,
      end,
      break_start: breakStartInput?.value || '',
      break_end: breakEndInput?.value || '',
    };

    const rows = Array.from(scheduleSection.querySelectorAll('[data-schedule-day]'));
    const inactive = [];
    const active = [];

    rows.forEach((row) => {
      if (row === sourceRow) return;
      const rowCheckbox = row.querySelector('.js-day-active');
      if (rowCheckbox && rowCheckbox.checked) {
        active.push({ row, checkbox: rowCheckbox });
      } else {
        inactive.push({ row, checkbox: rowCheckbox });
      }
    });

    active.forEach(({ row, checkbox }) => applyValues(row, checkbox, values, false));

    if (inactive.length) {
      const sourceLabel = sourceRow.dataset.dayLabel || 'Source day';
      const shouldActivate = window.confirm(`Some days are inactive. Activate them and apply ${sourceLabel}'s schedule?`);
      if (shouldActivate) {
        inactive.forEach(({ row, checkbox }) => applyValues(row, checkbox, values, true));
      }
    }

    const sourceLabel = sourceRow.dataset.dayLabel || 'Source day';
    notifier.success('Schedule copied', `${sourceLabel}'s hours copied to other days.`);
    updateCopyButtonState();
  };

  scheduleSection.querySelectorAll('[data-schedule-day]').forEach((row) => {
    const checkbox = row.querySelector('.js-day-active');
    toggleDayInputs(row, Boolean(checkbox && checkbox.checked));
    if (checkbox && checkbox.dataset.scheduleDayBound !== 'true') {
      checkbox.addEventListener('change', () => {
        toggleDayInputs(row, checkbox.checked);
        if (row === getSourceRow()) {
          updateCopyButtonState();
        }
      });
      checkbox.dataset.scheduleDayBound = 'true';
    }
  });

  if (copyBtn && copyBtn.dataset.scheduleCopyBound !== 'true') {
    copyBtn.addEventListener('click', () => {
      if (copyBtn.disabled) return;
      handleCopyClick();
    });

    const sourceRow = getSourceRow();
    if (sourceRow) {
      ['start', 'end'].forEach((field) => {
        const input = getField(sourceRow, field);
        if (input && input.dataset.scheduleSourceBound !== 'true') {
          input.addEventListener('input', updateCopyButtonState);
          input.addEventListener('change', updateCopyButtonState);
          input.dataset.scheduleSourceBound = 'true';
        }
      });

      const checkbox = sourceRow.querySelector('.js-day-active');
      if (checkbox && checkbox.dataset.scheduleSourceCheckboxBound !== 'true') {
        checkbox.addEventListener('change', updateCopyButtonState);
        checkbox.dataset.scheduleSourceCheckboxBound = 'true';
      }
    }

    copyBtn.dataset.scheduleCopyBound = 'true';
  }

  if (roleSelect.dataset.scheduleToggleBound !== 'true') {
    roleSelect.addEventListener('change', () => {
      toggleScheduleSection(roleSelect.value);
    });
    roleSelect.dataset.scheduleToggleBound = 'true';
  }

  toggleScheduleSection(roleSelect.value);
  updateCopyButtonState();
  scheduleSection.dataset.providerScheduleInitialized = 'true';
}