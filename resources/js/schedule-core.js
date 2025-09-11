// FullCalendar core scheduler rebuild entry
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
  try {
    // API expects Y-m-d dates (no time); unversioned endpoint avoids auth token requirement
    const startDate = info.startStr.substring(0,10);
    const endDate = info.endStr.substring(0,10);
    const params = new URLSearchParams();
    params.set('start', startDate);
    params.set('end', endDate);
    const service = document.getElementById('filterService')?.value || '';
    const provider = document.getElementById('filterProvider')?.value || '';
    if(service) params.set('serviceId', service);
    if(provider) params.set('providerId', provider);
    const url = '/api/appointments?' + params.toString();
    const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
    if(!res.ok) throw new Error('HTTP '+res.status);
    const data = await res.json();
    const src = data.data || data || [];
    const events = src.map(a => ({
      id: a.id,
      title: a.title || a.serviceName || a.service_name || 'Appointment',
      start: a.start || a.start_time || a.start_at,
      end: a.end || a.end_time || a.end_at,
      extendedProps: a,
    }));
    success(events);
  } catch(err){
    console.error('Events load failed', err);
    failure(err);
  }
}

function init(){
  const el = document.getElementById('calendar');
  if(!el) return;

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
    }
  });

  if(state.currentDate){
    try { calendar.gotoDate(new Date(state.currentDate)); } catch(e) {}
  }

  calendar.render();
  state.calendar = calendar;
  wireControls();
}

function wireControls(){
  const c = state.calendar; if(!c) return;
  document.getElementById('todayBtn')?.addEventListener('click', () => c.today());
  document.getElementById('prevBtn')?.addEventListener('click', () => c.prev());
  document.getElementById('nextBtn')?.addEventListener('click', () => c.next());
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
