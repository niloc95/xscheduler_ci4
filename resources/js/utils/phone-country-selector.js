const DEFAULT_COUNTRY_OPTIONS = [
  { code: '+27', label: 'South Africa', flag: '🇿🇦' },
  { code: '+1', label: 'United States/Canada', flag: '🇺🇸' },
  { code: '+44', label: 'United Kingdom', flag: '🇬🇧' },
  { code: '+353', label: 'Ireland', flag: '🇮🇪' },
  { code: '+33', label: 'France', flag: '🇫🇷' },
  { code: '+49', label: 'Germany', flag: '🇩🇪' },
  { code: '+34', label: 'Spain', flag: '🇪🇸' },
  { code: '+39', label: 'Italy', flag: '🇮🇹' },
  { code: '+31', label: 'Netherlands', flag: '🇳🇱' },
  { code: '+41', label: 'Switzerland', flag: '🇨🇭' },
  { code: '+46', label: 'Sweden', flag: '🇸🇪' },
  { code: '+47', label: 'Norway', flag: '🇳🇴' },
  { code: '+45', label: 'Denmark', flag: '🇩🇰' },
  { code: '+358', label: 'Finland', flag: '🇫🇮' },
  { code: '+351', label: 'Portugal', flag: '🇵🇹' },
  { code: '+43', label: 'Austria', flag: '🇦🇹' },
  { code: '+48', label: 'Poland', flag: '🇵🇱' },
  { code: '+30', label: 'Greece', flag: '🇬🇷' },
  { code: '+7', label: 'Russia/Kazakhstan', flag: '🇷🇺' },
  { code: '+90', label: 'Turkey', flag: '🇹🇷' },
  { code: '+61', label: 'Australia', flag: '🇦🇺' },
  { code: '+64', label: 'New Zealand', flag: '🇳🇿' },
  { code: '+65', label: 'Singapore', flag: '🇸🇬' },
  { code: '+60', label: 'Malaysia', flag: '🇲🇾' },
  { code: '+66', label: 'Thailand', flag: '🇹🇭' },
  { code: '+84', label: 'Vietnam', flag: '🇻🇳' },
  { code: '+62', label: 'Indonesia', flag: '🇮🇩' },
  { code: '+63', label: 'Philippines', flag: '🇵🇭' },
  { code: '+81', label: 'Japan', flag: '🇯🇵' },
  { code: '+82', label: 'South Korea', flag: '🇰🇷' },
  { code: '+86', label: 'China', flag: '🇨🇳' },
  { code: '+91', label: 'India', flag: '🇮🇳' },
  { code: '+92', label: 'Pakistan', flag: '🇵🇰' },
  { code: '+880', label: 'Bangladesh', flag: '🇧🇩' },
  { code: '+94', label: 'Sri Lanka', flag: '🇱🇰' },
  { code: '+971', label: 'UAE', flag: '🇦🇪' },
  { code: '+966', label: 'Saudi Arabia', flag: '🇸🇦' },
  { code: '+974', label: 'Qatar', flag: '🇶🇦' },
  { code: '+965', label: 'Kuwait', flag: '🇰🇼' },
  { code: '+968', label: 'Oman', flag: '🇴🇲' },
  { code: '+20', label: 'Egypt', flag: '🇪🇬' },
  { code: '+212', label: 'Morocco', flag: '🇲🇦' },
  { code: '+216', label: 'Tunisia', flag: '🇹🇳' },
  { code: '+213', label: 'Algeria', flag: '🇩🇿' },
  { code: '+234', label: 'Nigeria', flag: '🇳🇬' },
  { code: '+233', label: 'Ghana', flag: '🇬🇭' },
  { code: '+254', label: 'Kenya', flag: '🇰🇪' },
  { code: '+256', label: 'Uganda', flag: '🇺🇬' },
  { code: '+255', label: 'Tanzania', flag: '🇹🇿' },
  { code: '+250', label: 'Rwanda', flag: '🇷🇼' },
  { code: '+260', label: 'Zambia', flag: '🇿🇲' },
  { code: '+263', label: 'Zimbabwe', flag: '🇿🇼' },
  { code: '+267', label: 'Botswana', flag: '🇧🇼' },
  { code: '+264', label: 'Namibia', flag: '🇳🇦' },
  { code: '+268', label: 'Eswatini', flag: '🇸🇿' },
  { code: '+230', label: 'Mauritius', flag: '🇲🇺' },
  { code: '+55', label: 'Brazil', flag: '🇧🇷' },
  { code: '+54', label: 'Argentina', flag: '🇦🇷' },
  { code: '+56', label: 'Chile', flag: '🇨🇱' },
  { code: '+57', label: 'Colombia', flag: '🇨🇴' },
  { code: '+51', label: 'Peru', flag: '🇵🇪' },
  { code: '+52', label: 'Mexico', flag: '🇲🇽' },
];

function resolveCountryCode(value) {
  if (!value) return null;
  const trimmed = String(value).trim();
  if (!trimmed) return null;

  const digits = trimmed.replace(/\D+/g, '');
  if (!digits || digits.startsWith('0')) return null;
  return `+${digits.slice(0, 4)}`;
}

function detectFromPhoneValue(rawValue) {
  const value = String(rawValue || '').trim();
  if (!value.startsWith('+')) {
    return null;
  }

  const digits = value.slice(1).replace(/\D+/g, '');
  if (!digits) {
    return null;
  }

  const sorted = [...DEFAULT_COUNTRY_OPTIONS].sort((a, b) => b.code.length - a.code.length);
  const match = sorted.find(option => digits.startsWith(option.code.slice(1)));
  return match?.code || null;
}

function buildSelect(name, selectedCode, inputClassName = '') {
  const select = document.createElement('select');
  select.name = name;

  const mirroredTokens = String(inputClassName)
    .split(/\s+/)
    .filter(token => token)
    .filter(token => !/^m[tyb]-/.test(token))
    .filter(token => !/^(w|min-w|max-w)-/.test(token));

  const fallbackTokens = [
    'w-full',
    'px-2',
    'py-2',
    'border',
    'border-gray-300',
    'dark:border-gray-600',
    'rounded-lg',
    'bg-white',
    'dark:bg-gray-700',
    'text-gray-800',
    'dark:text-gray-100',
    'text-sm',
  ];

  const selectTokens = (mirroredTokens.length > 0 ? mirroredTokens : fallbackTokens).concat([
    'w-full',
    'max-w-[7.5rem]',
  ]);

  select.className = Array.from(new Set(selectTokens)).join(' ');
  select.setAttribute('aria-label', 'Country code');

  DEFAULT_COUNTRY_OPTIONS.forEach(option => {
    const opt = document.createElement('option');
    opt.value = option.code;
    opt.textContent = `${option.flag || ''} ${option.code}`.trim();
    opt.title = `${option.label} (${option.code})`;
    if (option.code === selectedCode) {
      opt.selected = true;
    }
    select.appendChild(opt);
  });

  return select;
}

export function initPhoneCountrySelectors(root = document, options = {}) {
  const defaultCode = resolveCountryCode(options.defaultCountryCode)
    || resolveCountryCode(window.__DEFAULT_PHONE_COUNTRY_CODE__)
    || '+27';

  const inputs = Array.from(root.querySelectorAll('input[name="phone"], input[name="customer_phone"]'));

  inputs.forEach(input => {
    if (!(input instanceof HTMLInputElement)) {
      return;
    }

    if (input.dataset.phoneCountryEnhanced === 'true') {
      return;
    }

    const countryFieldName = `${input.name}_country_code`;
    const existing = input.form?.querySelector(`select[name="${countryFieldName}"]`);
    if (existing) {
      input.dataset.phoneCountryEnhanced = 'true';
      return;
    }

    const inferred = detectFromPhoneValue(input.value);
    const select = buildSelect(countryFieldName, inferred || defaultCode, input.className);

    const inlineWrapper = document.createElement('div');
    inlineWrapper.className = 'grid grid-cols-[6.5rem_1fr] gap-2 items-start';

    if (input.classList.contains('mt-1')) {
      input.classList.remove('mt-1');
    }

    input.parentNode?.insertBefore(inlineWrapper, input);
    inlineWrapper.appendChild(select);
    inlineWrapper.appendChild(input);
    input.dataset.phoneCountryEnhanced = 'true';
  });
}