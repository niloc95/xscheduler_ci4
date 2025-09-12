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
};

function saveState(cal){
  localStorage.setItem('scheduler.view', cal.view.type);
  localStorage.setItem('scheduler.date', cal.getDate().toISOString());
}

async function fetchEvents(info, success, failure){
  const debug = [];
  const fetchStart = performance.now();
  console.log('[schedule-core] fetchEvents -> start', { rangeStart: info.startStr, rangeEnd: info.endStr });
  let finished = false;
  // Hard timeout safeguard (network hang, etc.)
  const timeoutMs = 10000; // 10s
  const timeoutId = setTimeout(() => {
    if (finished) return;
    finished = true;
    console.warn('[schedule-core] fetchEvents timeout exceeded', { timeoutMs, debug });
    const statusEl = document.getElementById('scheduleStatus');
    if(statusEl){
      statusEl.textContent = 'Request timeout fetching appointments';
      statusEl.classList.add('text-amber-600');
    }
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
    const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
    debug.push(['status', res.status]);
    if(!res.ok) throw new Error('HTTP '+res.status);
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
  console.log('[schedule-core] events loaded', { debug, count: events.length });
  finished = true; clearTimeout(timeoutId);
  success(events);
  } catch(err){
  finished = true; clearTimeout(timeoutId);
  console.error('[schedule-core] events load failed', err, debug);
    const statusEl = document.getElementById('scheduleStatus');
    if(statusEl){
      statusEl.textContent = 'Failed to load appointments';
      statusEl.classList.add('text-red-600');
    }
    failure(err);
  }
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
    datesSet(){ saveState(calendar); },
    eventClick(info){
      alert('Appointment '+ info.event.id);
    },
    loading(isLoading){
      const overlay = document.getElementById('calendarLoading');
      if(overlay){ overlay.classList.toggle('hidden', !isLoading); }
      // Secondary failsafe: hide loader after 12s even if FullCalendar misses callback
      if(isLoading){
        if(window.__scheduleLoadingTimer) clearTimeout(window.__scheduleLoadingTimer);
        window.__scheduleLoadingTimer = setTimeout(()=>{
          const ov = document.getElementById('calendarLoading');
          if(ov && !ov.classList.contains('hidden')){
            console.warn('[schedule-core] loading overlay auto-hidden after failsafe interval');
            ov.classList.add('hidden');
            const statusEl = document.getElementById('scheduleStatus');
            if(statusEl && !statusEl.textContent){
              statusEl.textContent = 'Calendar loaded (failsafe)';
              statusEl.classList.add('text-amber-600');
            }
          }
        }, 12000);
      } else {
        if(window.__scheduleLoadingTimer) { clearTimeout(window.__scheduleLoadingTimer); window.__scheduleLoadingTimer = null; }
      }
      if(!isLoading){
        const statusEl = document.getElementById('scheduleStatus');
        if(statusEl && !statusEl.textContent){
          statusEl.textContent = 'Loaded';
          setTimeout(()=>{ if(statusEl.textContent==='Loaded') statusEl.textContent=''; }, 1500);
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
  document.getElementById('refreshCalendar')?.addEventListener('click', () => { console.log('[schedule-core] manual refresh'); c.refetchEvents(); });
  document.querySelectorAll('.view-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const type = btn.getAttribute('data-view');
      c.changeView(type);
      document.querySelectorAll('.view-btn').forEach(b=>b.classList.remove('bg-indigo-50','dark:bg-indigo-900','text-indigo-700','dark:text-indigo-200'));
      btn.classList.add('bg-indigo-50','dark:bg-indigo-900','text-indigo-700','dark:text-indigo-200');
    });
  });
  document.getElementById('filterService')?.addEventListener('change', ()=> c.refetchEvents());
  document.getElementById('filterProvider')?.addEventListener('change', ()=> c.refetchEvents());
}

if(document.readyState === 'loading'){
  document.addEventListener('DOMContentLoaded', init);
}else{
  init();
}

// Expose for debug
window.__scheduleDebug = state;
