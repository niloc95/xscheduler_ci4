export const DAY_VIEW_HOUR_HEIGHT_PX = 100;
export const DAY_VIEW_MIN_HEIGHT_PX = 22;
export const DAY_VIEW_SLOT_MARGIN_PX = 4;

export const TIER_TIME_ONLY = 35;
export const TIER_NAME = 65;
export const TIER_SERVICE = 100;
export const TIER_STATUS = 150;

const STATUS_META = {
  confirmed: { label: 'Confirmed', bg: 'bg-emerald-100 dark:bg-emerald-900/40', text: 'text-emerald-700 dark:text-emerald-300', dot: 'bg-emerald-500' },
  pending: { label: 'Pending', bg: 'bg-amber-100 dark:bg-amber-900/40', text: 'text-amber-700 dark:text-amber-300', dot: 'bg-amber-500' },
  cancelled: { label: 'Cancelled', bg: 'bg-red-100 dark:bg-red-900/40', text: 'text-red-700 dark:text-red-300', dot: 'bg-red-500' },
  completed: { label: 'Completed', bg: 'bg-blue-100 dark:bg-blue-900/40', text: 'text-blue-700 dark:text-blue-300', dot: 'bg-blue-500' },
  'no-show': { label: 'No Show', bg: 'bg-gray-100 dark:bg-gray-700/60', text: 'text-gray-600 dark:text-gray-300', dot: 'bg-gray-400' },
  rescheduled: { label: 'Rescheduled', bg: 'bg-purple-100 dark:bg-purple-900/40', text: 'text-purple-700 dark:text-purple-300', dot: 'bg-purple-500' },
};

export function statusMeta(status) {
  return STATUS_META[status] ?? STATUS_META.pending;
}
