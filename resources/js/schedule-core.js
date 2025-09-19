// Unified scheduler module (merged schedule-core + schedule.js)
// Guards ------------------------------------------------------------
if (window.__scheduleInit) {
  console.log('[scheduler] Already initialized, skipping.');
} else {
  window.__scheduleInit = true;
}

// Imports -----------------------------------------------------------
import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';

// Style Injection (minimal baseline) -------------------------------
(function injectStyles(){
  if(document.getElementById('fc-base-styles')) return;
  const style=document.createElement('style');
  style.id='fc-base-styles';
  style.textContent=`
  .fc { font-family: system-ui, sans-serif; font-size: 0.85rem; }
  .fc .fc-toolbar-title { font-size:1.1rem; }
  .fc-daygrid-day-number { font-size:0.65rem; padding:2px 4px; }
  .fc-event { font-size:0.65rem; }
  .fc .fc-scrollgrid { border-radius: 4px; }
  `;
  document.head.appendChild(style);
})();

// State -------------------------------------------------------------
const state = {
  calendar: null,
  currentView: localStorage.getItem('scheduler.view') || 'dayGridMonth',
  currentDate: localStorage.getItem('scheduler.date') || null,
  activeLoads: 0,
  currentRequest: null,
  debounceTimer: null,
};

// Status / Overlay Helpers -----------------------------------------
function updateOverlay(){
  const overlay = document.getElementById('calendarLoading');
  const loadingText = document.getElementById('calendarLoadingText');
  const refreshBtn = document.getElementById('refreshCalendar');
  if(!overlay) return;
  if(state.activeLoads > 0){
    overlay.classList.remove('hidden');
    overlay.style.opacity = '1';
    if(loadingText){
      loadingText.textContent = state.activeLoads > 1 ? `Loading (${state.activeLoads})...` : 'Loading...';
    }
    if(refreshBtn){
      refreshBtn.disabled = true;
      refreshBtn.classList.add('opacity-50','cursor-not-allowed');
    }
  } else {
    overlay.style.opacity = '0';
    setTimeout(()=>{
      if(state.activeLoads === 0){
        overlay.classList.add('hidden');
      }
    },200);
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

// Local Storage -----------------------------------------------------
// (saveState defined later after merge; first duplicate removed during consolidation)

// Appointment / Event Helpers --------------------------------------
const STATUS_COLORS = {
  booked: 'bg-blue-500 text-white',
  confirmed: 'bg-green-500 text-white',
  rescheduled: 'bg-amber-500 text-gray-900',
  cancelled: 'bg-red-500 text-white',
  completed: 'bg-gray-400 text-white',
};
function getStatusClass(status){
  return STATUS_COLORS[status] || 'bg-blue-500 text-white';
}
async function fetchJSON(url, opts){
  const res = await fetch(url, opts);
  if(!res.ok) throw new Error('Request failed: '+res.status);
  return await res.json();
}
function buildEventFromAppointment(appt){
  return {
    id: String(appt.id),
    title: `${appt.customer_name || 'Customer'} • ${appt.service_name || appt.service || 'Service'}`,
    start: appt.start_time || appt.start || appt.start_at,
    end: appt.end_time || appt.end || appt.end_at,
    extendedProps: {
      status: appt.status,
      customer: { name: appt.customer_name, email: appt.customer_email },
      service: { id: appt.service_id, name: appt.service_name || appt.service, duration: appt.duration_min || appt.duration },
      provider_id: appt.provider_id,
      notes: appt.notes || '',
    },
  };
}
function eventClassNames(arg){
  const status = arg.event.extendedProps.status;
  const base = 'rounded-md px-2 py-1 text-xs md:text-sm shadow-sm border border-white/20 flex items-center gap-1';
  return [base, getStatusClass(status)];
}
function renderEventContent(arg){
  const status = arg.event.extendedProps.status;
  const label = status ? status.charAt(0).toUpperCase()+status.slice(1) : '';
  const title = arg.event.title;
  return { html: `<div class="flex items-center gap-2"><span class="inline-flex items-center justify-center text-[10px] leading-none px-1.5 py-0.5 rounded bg-white/20">${label}</span><span class="truncate">${title}</span></div>` };
}
function openModal(node){
  const modal = document.getElementById('scheduleModal');
  const content = document.getElementById('scheduleModalContent');
  if(!modal || !content) return;
  content.innerHTML='';
  content.appendChild(node);
  modal.classList.remove('hidden');
}
function closeModal(){
  const modal = document.getElementById('scheduleModal');
  if(modal) modal.classList.add('hidden');
}
function buildAppointmentView(event){
  const d = document.createElement('div');
  const p = event.extendedProps || {};
  d.className='space-y-3';
  d.innerHTML=`
    <div class="flex items-start justify-between">
      <div>
        <h3 class="text-lg font-semibold">Appointment</h3>
        <p class="text-sm text-gray-500 dark:text-gray-400">${new Date(event.start).toLocaleString()} - ${new Date(event.end).toLocaleTimeString()}</p>
      </div>
      <span class="inline-flex ${getStatusClass(p.status)} rounded px-2 py-0.5 text-xs">${p.status}</span>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
      <div class="p-3 rounded border dark:border-gray-700">
        <div class="text-xs uppercase text-gray-500 mb-1">Customer</div>
        <div class="text-sm">${p.customer?.name || 'N/A'}</div>
        <div class="text-xs text-gray-500">${p.customer?.email || ''}</div>
      </div>
      <div class="p-3 rounded border dark:border-gray-700">
        <div class="text-xs uppercase text-gray-500 mb-1">Service</div>
        <div class="text-sm">${p.service?.name || 'N/A'}</div>
      </div>
    </div>
    <div class="flex items-center gap-2 pt-2">
      <button id="actReschedule" class="btn btn-secondary">Reschedule</button>
      <button id="actConfirm" class="btn btn-primary">Confirm</button>
      <button id="actCancel" class="btn bg-red-500 hover:bg-red-600 text-white rounded px-3 py-2 text-sm">Cancel</button>
    </div>`;
  d.querySelector('#actReschedule')?.addEventListener('click',()=>{ closeModal(); });
  d.querySelector('#actConfirm')?.addEventListener('click', async ()=>{
    try {
      beginLoad('confirm');
      await fetchJSON(`${window.__BASE_URL__ || ''}/api/appointments/update`,{
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ id: event.id, status:'confirmed' })
      });
      event.setExtendedProp('status','confirmed');
      closeModal();
    } catch(e){ console.error(e); } finally { endLoad('confirm'); }
  });
  d.querySelector('#actCancel')?.addEventListener('click', async ()=>{
    if(!confirm('Cancel this appointment?')) return;
    try {
      beginLoad('cancel');
      await fetchJSON(`${window.__BASE_URL__ || ''}/api/appointments/delete`,{
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ id: event.id })
      });
      event.remove();
      closeModal();
    } catch(e){ console.error(e); } finally { endLoad('cancel'); }
  });
  return d;
}
function buildCreateForm(info){
  const d = document.createElement('div');
  d.className='space-y-3';
  const start = info.startStr; const end = info.endStr;
  d.innerHTML=`
    <h3 class="text-lg font-semibold">New Appointment</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
      <div><label class="text-sm">Customer Name</label><input id="new_name" class="w-full px-3 py-2 border rounded dark:bg-gray-800"/></div>
      <div><label class="text-sm">Email</label><input id="new_email" type="email" class="w-full px-3 py-2 border rounded dark:bg-gray-800"/></div>
      <div><label class="text-sm">Service ID</label><input id="new_service" type="number" class="w-full px-3 py-2 border rounded dark:bg-gray-800"/></div>
      <div><label class="text-sm">Provider ID</label><input id="new_provider" type="number" class="w-full px-3 py-2 border rounded dark:bg-gray-800"/></div>
    </div>
    <div class="text-sm text-gray-500">${new Date(start).toLocaleString()} - ${new Date(end).toLocaleTimeString()}</div>
    <div class="flex items-center gap-2">
      <button id="createBtn" class="btn btn-primary">Create</button>
      <button id="cancelBtn" class="btn">Close</button>
    </div>`;
  d.querySelector('#cancelBtn')?.addEventListener('click', closeModal);
  d.querySelector('#createBtn')?.addEventListener('click', async ()=>{
    const payload = {
      name: d.querySelector('#new_name').value,
      email: d.querySelector('#new_email').value,
      provider_id: parseInt(d.querySelector('#new_provider').value || '1',10),
      service_id: parseInt(d.querySelector('#new_service').value || '1',10),
      date: start.substring(0,10),
      start: new Date(start).toTimeString().substring(0,5),
      notes: ''
    };
    try {
      beginLoad('create');
      await fetchJSON(`${window.__BASE_URL__ || ''}/api/book`,{
        method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload)
      });
      closeModal();
      window.dispatchEvent(new CustomEvent('schedule:refresh'));
    } catch(e){ console.error(e); } finally { endLoad('create'); }
  });
  return d;
}

// Events loader (merged with overlay) -------------------------------
async function loadEvents(fetchInfo, success, failure){
  beginLoad('events');
  try {
    const base = window.__BASE_URL__ || '';
    const url = `${base}/api/appointments/get?start=${encodeURIComponent(fetchInfo.startStr)}&end=${encodeURIComponent(fetchInfo.endStr)}`;
    const data = await fetchJSON(url);
    const appts = Array.isArray(data) ? data : (data.appointments || data.data || []);
    const events = appts.map(buildEventFromAppointment);
    success(events);
  } catch(e){
    console.error('[scheduler] events load failed', e);
    failure(e);
  } finally { endLoad('events'); }
}

function saveState(cal){
  localStorage.setItem('scheduler.view', cal.view.type);
  localStorage.setItem('scheduler.date', cal.getDate().toISOString());
}

// (Removed old fetchEvents + duplicate overlay functions during merge)

function init(){
  const el = document.getElementById('calendar');
  if(!el){
    console.log('[scheduler] #calendar not found (page without scheduler).');
    return;
  }
  console.log('[scheduler] Initializing unified calendar...');

  const base = window.__BASE_URL__ || '';
  const calendar = new Calendar(el, {
    plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
    initialView: state.currentView,
    headerToolbar: false,
    height: 'auto',
    firstDay: 0,
    selectable: true,
    editable: true,
    nowIndicator: true,
    events: loadEvents,
    eventClassNames,
    eventContent: renderEventContent,
    datesSet(info){
      saveState(calendar);
    },
    viewDidMount(viewInfo){
      state.currentView = viewInfo.view.type;
      document.querySelectorAll('.view-btn').forEach(b=>b.classList.remove('bg-indigo-50','dark:bg-indigo-900','text-indigo-700','dark:text-indigo-200'));
      const activeBtn = document.querySelector(`[data-view="${viewInfo.view.type}"]`);
      if(activeBtn) activeBtn.classList.add('bg-indigo-50','dark:bg-indigo-900','text-indigo-700','dark:text-indigo-200');
    },
    select(info){ openModal(buildCreateForm(info)); },
    eventClick(info){ openModal(buildAppointmentView(info.event)); },
    eventDrop: async (info) => {
      try {
        beginLoad('drag');
        await fetchJSON(`${base}/appointments/update`,{
          method:'POST', headers:{'Content-Type':'application/json'},
          body: JSON.stringify({ id: info.event.id, start_time: info.event.start.toISOString(), end_time: info.event.end?.toISOString() || null })
        });
      } catch(e){ console.error(e); info.revert(); } finally { endLoad('drag'); }
    },
    loading(isLoading){
      if(!isLoading){
        updateOverlay();
        const statusEl = document.getElementById('scheduleStatus');
        if(statusEl && !statusEl.textContent){
          statusEl.textContent='Loaded';
          setTimeout(()=>{ if(statusEl.textContent==='Loaded') statusEl.textContent=''; }, 1200);
        }
      }
    }
  });

  if(state.currentDate){
    try { calendar.gotoDate(new Date(state.currentDate)); } catch(e) {}
  }

  try { calendar.render(); }
  catch(e){
    console.error('[scheduler] render failed', e);
    const statusEl = document.getElementById('scheduleStatus');
    if(statusEl){ statusEl.textContent='Calendar failed to initialize'; statusEl.classList.add('text-red-600'); }
    return;
  }
  state.calendar = calendar;
  wireControls();
  // External triggers
  window.addEventListener('schedule:refresh', ()=> calendar.refetchEvents());
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

if(!window.__scheduleCalendarBootstrapped){
  window.__scheduleCalendarBootstrapped = true;
  if(document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
  document.addEventListener('spa:navigated', () => {
    // Attempt re-init only if calendar not present
    if(!state.calendar) init();
  });
}

// Debug handle
window.__scheduleDebug = state;
window.__scheduleRefresh = () => state.calendar?.refetchEvents();
