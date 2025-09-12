// FullCalendar core scheduler rebuild entry
if (window.__scheduleLoaded) {
  console.log('[schedule-core] Already loaded, skipping second evaluation');
} else {
  window.__scheduleLoaded = true;
}
import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';

// Basic minimal CSS injection (we can refine later or compile SCSS)
(function injectStyles(){
  if(document.getElementById('fc-base-styles')) return;
  const style=document.createElement('style');
  style.id='fc-base-styles';
  style.textContent=`
  .fc { font-family: system-ui, sans-serif; font-size: 0.85rem; }
  .fc .fc-toolbar-title { font-size:1.1rem; }
  .fc-daygrid-day-number { font-size:0.65rem; padding:2px 4px; }
  .fc-event { font-size:0.65rem; }
  `;
  document.head.appendChild(style);
})();

const state = {
  calendar: null,
  currentView: localStorage.getItem('scheduler.view') || 'dayGridMonth',
  currentDate: localStorage.getItem('scheduler.date') || null,
  activeLoads: 0,
  isLoading: false,
  currentRequest: null,
  debounceTimer: null,
};

function saveState(cal){
  localStorage.setItem('scheduler.view', cal.view.type);
  localStorage.setItem('scheduler.date', cal.getDate().toISOString());
}

async function fetchEvents(info, success, failure){
  const debug = [];
  const fetchStart = performance.now();
  
  // Cancel any existing request to prevent overlapping
  if (state.currentRequest) {
    console.log('[schedule-core] Cancelling previous request');
    state.currentRequest = null;
  }
  
  const requestId = Date.now();
  state.currentRequest = requestId;
  
  console.log('[schedule-core] fetchEvents -> start', { rangeStart: info.startStr, rangeEnd: info.endStr, requestId });
  
  let finished = false;
  beginLoad('events');
  
  // Hard timeout safeguard (network hang, etc.)
  const timeoutMs = 10000; // 10s
  const timeoutId = setTimeout(() => {
    if (finished || state.currentRequest !== requestId) return;
    finished = true;
    console.warn('[schedule-core] fetchEvents timeout exceeded', { timeoutMs, debug, requestId });
    const statusEl = document.getElementById('scheduleStatus');
    if(statusEl){
      statusEl.textContent = 'Request timeout fetching appointments';
      statusEl.classList.add('text-amber-600');
    }
    endLoad('events');
    try { failure(new Error('timeout')); } catch(_){}
  }, timeoutMs);

  try {
    const startDate = info.startStr.substring(0,10);
    const endDate = info.endStr.substring(0,10);
    debug.push(['range', startDate, endDate]);
    const params = new URLSearchParams();
    params.set('start', startDate);
    params.set('end', endDate);
    const service = document.getElementById('filterService')?.value || '';
    const provider = document.getElementById('filterProvider')?.value || '';
    if(service) params.set('serviceId', service);
    if(provider) params.set('providerId', provider);
    const url = '/api/appointments?' + params.toString();
    debug.push(['url', url]);
    
    // Check if this request is still current
    if (state.currentRequest !== requestId) {
      console.log('[schedule-core] Request superseded, aborting', { requestId });
      return;
    }
    
    const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
    debug.push(['status', res.status]);
    if(!res.ok) throw new Error('HTTP '+res.status);
    
    // Final check before processing response
    if (state.currentRequest !== requestId) {
      console.log('[schedule-core] Request superseded after fetch, aborting', { requestId });
      return;
    }
    
    const data = await res.json();
    debug.push(['payloadKeys', Object.keys(data)]);
    const src = data.data || data || [];
    if(!Array.isArray(src)) debug.push(['nonArrayData', src]);
    const events = Array.isArray(src) ? src.map(a => ({
      id: a.id,
      title: a.title || a.serviceName || a.service_name || 'Appointment',
      start: a.start || a.start_time || a.start_at,
      end: a.end || a.end_time || a.end_at,
      extendedProps: a,
    })) : [];
    if(events.length === 0) debug.push(['emptyEvents']);
    const dur = (performance.now() - fetchStart).toFixed(1);
    debug.push(['durationMs', dur]);
    console.log('[schedule-core] events loaded', { debug, count: events.length, requestId });
    
    if (state.currentRequest === requestId) {
      finished = true; 
      clearTimeout(timeoutId);
      success(events);
    }
  } catch(err){
    if (state.currentRequest === requestId) {
      finished = true; 
      clearTimeout(timeoutId);
      console.error('[schedule-core] events load failed', err, debug, { requestId });
      const statusEl = document.getElementById('scheduleStatus');
      if(statusEl){
        statusEl.textContent = 'Failed to load appointments';
        statusEl.classList.add('text-red-600');
      }
      failure(err);
    }
  } finally {
    if (state.currentRequest === requestId) {
      endLoad('events');
      state.currentRequest = null;
    }
  }
}function updateOverlay(){
  const overlay = document.getElementById('calendarLoading');
  const loadingText = document.getElementById('calendarLoadingText');
  const refreshBtn = document.getElementById('refreshCalendar');
  if(!overlay) return;
  
  if(state.activeLoads > 0){
    overlay.classList.remove('hidden');
    overlay.style.opacity = '1';
    if(loadingText) {
      loadingText.textContent = state.activeLoads > 1 ? `Loading (${state.activeLoads})...` : 'Loading...';
    }
    if(refreshBtn){ 
      refreshBtn.disabled = true; 
      refreshBtn.classList.add('opacity-50','cursor-not-allowed'); 
    }
  } else {
    overlay.style.opacity = '0';
    setTimeout(() => {
      if(state.activeLoads === 0) {
        overlay.classList.add('hidden');
      }
    }, 200); // Match CSS transition duration
    if(refreshBtn){ 
      refreshBtn.disabled = false; 
      refreshBtn.classList.remove('opacity-50','cursor-not-allowed'); 
    }
  }
}

function beginLoad(tag){
  state.activeLoads++;
  updateOverlay();
}
function endLoad(tag){
  state.activeLoads = Math.max(0, state.activeLoads - 1);
  updateOverlay();
}

function init(){
  const el = document.getElementById('calendar');
  if(!el){
    console.log('[schedule-core] #calendar element not found. Script loaded but calendar container not present on this page.');
    return;
  }

  console.log('[schedule-core] Initializing calendar...');

  const calendar = new Calendar(el, {
    plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
    initialView: state.currentView,
    headerToolbar: false,
    height: 'auto',
    firstDay: 0,
    events: fetchEvents,
    datesSet(dateInfo){ 
      // Only save state, don't trigger additional fetches
      console.log('[schedule-core] datesSet triggered', { view: dateInfo.view.type, start: dateInfo.startStr, end: dateInfo.endStr });
      saveState(calendar); 
    },
    viewDidMount(viewInfo){
      // View has changed and mounted - this is where we update UI state
      console.log('[schedule-core] viewDidMount', { view: viewInfo.view.type });
      state.currentView = viewInfo.view.type;
      // Update view button highlighting
      document.querySelectorAll('.view-btn').forEach(b=>b.classList.remove('bg-indigo-50','dark:bg-indigo-900','text-indigo-700','dark:text-indigo-200'));
      const activeBtn = document.querySelector(`[data-view="${viewInfo.view.type}"]`);
      if(activeBtn) activeBtn.classList.add('bg-indigo-50','dark:bg-indigo-900','text-indigo-700','dark:text-indigo-200');
    },
    eventClick(info){
      alert('Appointment '+ info.event.id);
    },
    // Retain loading callback only as a safety signal; we no longer mutate counters here to avoid double counting
    loading(isLoading){
      if(!isLoading){
        // On completion, ensure overlay reflects counter state & show transient status if not already set
        updateOverlay();
        const statusEl = document.getElementById('scheduleStatus');
        if(statusEl && !statusEl.textContent){
          statusEl.textContent = 'Loaded';
          setTimeout(()=>{ if(statusEl.textContent==='Loaded') statusEl.textContent=''; }, 1200);
        }
      }
    }
  });

  if(state.currentDate){
    try { calendar.gotoDate(new Date(state.currentDate)); } catch(e) {}
  }

  try {
    calendar.render();
  } catch(e){
    console.error('[schedule-core] calendar.render failed', e);
    const statusEl = document.getElementById('scheduleStatus');
    if(statusEl){ statusEl.textContent='Calendar failed to initialize'; statusEl.classList.add('text-red-600'); }
    return;
  }
  state.calendar = calendar;
  wireControls();
}

function wireControls(){
  const c = state.calendar; if(!c) return;
  document.getElementById('todayBtn')?.addEventListener('click', () => c.today());
  document.getElementById('prevBtn')?.addEventListener('click', () => c.prev());
  document.getElementById('nextBtn')?.addEventListener('click', () => c.next());
  document.getElementById('refreshCalendar')?.addEventListener('click', () => { 
    console.log('[schedule-core] manual refresh'); 
    c.refetchEvents(); 
  });
  document.querySelectorAll('.view-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      const type = btn.getAttribute('data-view');
      if(type === c.view.type) {
        console.log('[schedule-core] view already active, skipping', { type });
        return; // Don't change to same view
      }
      console.log('[schedule-core] changing view', { from: c.view.type, to: type });
      c.changeView(type);
      // Button highlighting handled in viewDidMount callback
    });
  });
  
  // Debounced filter handlers to prevent rapid-fire requests
  const debouncedRefetch = () => {
    if (state.debounceTimer) clearTimeout(state.debounceTimer);
    state.debounceTimer = setTimeout(() => {
      console.log('[schedule-core] debounced filter refetch');
      c.refetchEvents();
    }, 300);
  };
  
  document.getElementById('filterService')?.addEventListener('change', debouncedRefetch);
  document.getElementById('filterProvider')?.addEventListener('change', debouncedRefetch);
}

if(document.readyState === 'loading'){
  document.addEventListener('DOMContentLoaded', init);
}else{
  init();
}

// Expose for debug
window.__scheduleDebug = state;
