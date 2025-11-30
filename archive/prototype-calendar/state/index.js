// Calendar state entrypoint (Phase 3)
import { createCalendarStore } from './store.js';
import * as actions from './actions.js';
import * as selectors from './selectors.js';

const calendarStore = createCalendarStore();

const boundActions = {
	hydrate: (payload, meta) => actions.hydrate(calendarStore, payload, meta),
	setActiveView: view => actions.setActiveView(calendarStore, view),
	setRange: range => actions.setRange(calendarStore, range),
	shiftRange: direction => actions.shiftRange(calendarStore, direction),
	setToday: () => actions.setToday(calendarStore),
	toggleProvider: providerId => actions.toggleProvider(calendarStore, providerId),
	fetchRange: options => actions.fetchRange(calendarStore, options),
};

export { calendarStore, boundActions, actions, selectors };
