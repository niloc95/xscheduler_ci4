const COLOR_THEME_MAP = {
  'blue-500': {
    dot: 'bg-blue-500',
    chipBg: 'bg-blue-50',
    chipText: 'text-blue-900',
    chipBorder: 'border-blue-100',
    badge: 'text-blue-900',
  },
  'emerald-500': {
    dot: 'bg-emerald-500',
    chipBg: 'bg-emerald-50',
    chipText: 'text-emerald-900',
    chipBorder: 'border-emerald-100',
    badge: 'text-emerald-900',
  },
  'amber-500': {
    dot: 'bg-amber-500',
    chipBg: 'bg-amber-50',
    chipText: 'text-amber-900',
    chipBorder: 'border-amber-100',
    badge: 'text-amber-900',
  },
};

const DEFAULT_THEME = {
  dot: 'bg-slate-400',
  chipBg: 'bg-slate-100',
  chipText: 'text-slate-700',
  chipBorder: 'border-slate-200',
  badge: 'text-slate-600',
};

const PIXELS_PER_MINUTE = 1.2;

function getProviderTheme(provider) {
  return (provider && COLOR_THEME_MAP[provider.colorToken]) || DEFAULT_THEME;
}

function minutesToPixels(minutes, minimum = 18) {
  return Math.max(minimum, Math.round(minutes * PIXELS_PER_MINUTE));
}

function formatRangeLabel(dateString) {
  const date = dateString ? new Date(dateString) : new Date();
  return date.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
}

function formatWeekRangeLabel(dateString) {
  const start = dateString ? new Date(dateString) : new Date();
  const end = new Date(start);
  end.setDate(start.getDate() + 6);
  const startLabel = start.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
  const endLabel = end.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
  return `Week of ${startLabel} – ${endLabel}`;
}

function formatDayLabel(dateString) {
  const date = dateString ? new Date(dateString) : new Date();
  return date.toLocaleDateString(undefined, { weekday: 'short', day: 'numeric' });
}

function formatFullDate(dateString) {
  const date = dateString ? new Date(dateString) : new Date();
  return date.toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric' });
}

function formatTimeLabel(dateString) {
  const date = dateString ? new Date(dateString) : new Date();
  return date.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }).toLowerCase();
}

function getNowOffset(nowTimestamp) {
  const now = nowTimestamp ? new Date(nowTimestamp) : new Date();
  return (now.getHours() * 60 + now.getMinutes()) * PIXELS_PER_MINUTE;
}

export {
  COLOR_THEME_MAP,
  DEFAULT_THEME,
  getProviderTheme,
  minutesToPixels,
  formatRangeLabel,
  formatWeekRangeLabel,
  formatDayLabel,
  formatFullDate,
  formatTimeLabel,
  getNowOffset,
};
