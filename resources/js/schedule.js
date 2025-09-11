// Schedule calendar using FullCalendar (vanilla) + Tailwind classes
// Imports
import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';

// Inject minimal FullCalendar CSS tweaks via JS (we rely on FC core CSS from CDN-less build styles inlined by Vite bundling)
// Consumers should include a small CSS snippet via Tailwind to adjust fonts; we'll keep defaults.

const STATUS_COLORS = {
  booked: 'bg-blue-500 text-white',
  confirmed: 'bg-green-500 text-white',
  rescheduled: 'bg-amber-500 text-gray-900',
  cancelled: 'bg-red-500 text-white',
  completed: 'bg-gray-400 text-white',
};

function getStatusClass(status) {
  return STATUS_COLORS[status] || 'bg-blue-500 text-white';
}

async function fetchJSON(url, opts) {
  const res = await fetch(url, opts);
  if (!res.ok) throw new Error('Request failed: ' + res.status);
  return await res.json();
}

function buildEventFromAppointment(appt) {
  return {
    id: String(appt.id),
    title: `${appt.customer_name || 'Customer'} • ${appt.service_name || appt.service || 'Service'}`,
    start: appt.start_time,
    end: appt.end_time,
    extendedProps: {
      status: appt.status,
      customer: {
        name: appt.customer_name,
        email: appt.customer_email,
      },
      service: {
        id: appt.service_id,
        name: appt.service_name || appt.service,
        duration: appt.duration_min || appt.duration,
      },
      provider_id: appt.provider_id,
      notes: appt.notes || '',
    },
  };
}

function eventClassNames(arg) {
  const status = arg.event.extendedProps.status;
  const base = 'rounded-md px-2 py-1 text-xs md:text-sm shadow-sm border border-white/20 flex items-center gap-1';
  return [base, getStatusClass(status)];
}

function renderEventContent(arg) {
  const status = arg.event.extendedProps.status;
  const label = status ? status.charAt(0).toUpperCase() + status.slice(1) : '';
  const title = arg.event.title;
  return {
    html: `<div class="flex items-center gap-2">
      <span class="inline-flex items-center justify-center text-[10px] leading-none px-1.5 py-0.5 rounded bg-white/20">${label}</span>
      <span class="truncate">${title}</span>
    </div>`
  };
}

function applyBusinessHoursAndBlocks(calendar, meta) {
  // meta: { businessHours: [{ daysOfWeek:[1..], startTime:'09:00', endTime:'17:00' }], blocks: [{ start, end, title }] }
  // Business hours via built-in option
  if (meta?.businessHours) {
    calendar.setOption('businessHours', meta.businessHours);
  }
  // Render blocked times as background events
  if (meta?.blocks?.length) {
    const evts = meta.blocks.map(b => ({
      id: `block-${b.id || b.start}`,
      start: b.start,
      end: b.end,
      display: 'background',
      classNames: ['bg-red-200', 'dark:bg-red-900/40'],
      title: b.title || 'Blocked',
    }));
    calendar.addEventSource(evts);
  }
}

function openModal(data) {
  const modal = document.getElementById('scheduleModal');
  const content = document.getElementById('scheduleModalContent');
  if (!modal || !content) return;
  content.innerHTML = '';
  content.appendChild(data);
  modal.classList.remove('hidden');
}

function closeModal() {
  const modal = document.getElementById('scheduleModal');
  if (modal) modal.classList.add('hidden');
}

function buildAppointmentView(event) {
  const d = document.createElement('div');
  const p = event.extendedProps || {};
  d.className = 'space-y-3';
  d.innerHTML = `
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
    </div>
  `;
  // Wire actions
  d.querySelector('#actReschedule')?.addEventListener('click', () => {
    closeModal();
    // Enable drag mode hint
  });
  d.querySelector('#actConfirm')?.addEventListener('click', async () => {
    try {
  await fetchJSON(`${window.__BASE_URL__ || ''}/api/appointments/update`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: event.id, status: 'confirmed' }),
      });
      event.setExtendedProp('status', 'confirmed');
      closeModal();
    } catch (e) { console.error(e); }
  });
  d.querySelector('#actCancel')?.addEventListener('click', async () => {
    if (!confirm('Cancel this appointment?')) return;
    try {
  await fetchJSON(`${window.__BASE_URL__ || ''}/api/appointments/delete`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: event.id }),
      });
      event.remove();
      closeModal();
    } catch (e) { console.error(e); }
  });
  return d;
}

function buildCreateForm(info) {
  const d = document.createElement('div');
  d.className = 'space-y-3';
  const start = info.startStr;
  const end = info.endStr;
  d.innerHTML = `
    <h3 class="text-lg font-semibold">New Appointment</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
      <div>
        <label class="text-sm">Customer Name</label>
        <input id="new_name" class="w-full px-3 py-2 border rounded dark:bg-gray-800" />
      </div>
      <div>
        <label class="text-sm">Email</label>
        <input id="new_email" type="email" class="w-full px-3 py-2 border rounded dark:bg-gray-800" />
      </div>
      <div>
        <label class="text-sm">Service ID</label>
        <input id="new_service" type="number" class="w-full px-3 py-2 border rounded dark:bg-gray-800" />
      </div>
      <div>
        <label class="text-sm">Provider ID</label>
        <input id="new_provider" type="number" class="w-full px-3 py-2 border rounded dark:bg-gray-800" />
      </div>
    </div>
    <div class="text-sm text-gray-500">${new Date(start).toLocaleString()} - ${new Date(end).toLocaleTimeString()}</div>
    <div class="flex items-center gap-2">
      <button id="createBtn" class="btn btn-primary">Create</button>
      <button id="cancelBtn" class="btn">Close</button>
    </div>
  `;
  d.querySelector('#cancelBtn')?.addEventListener('click', closeModal);
  d.querySelector('#createBtn')?.addEventListener('click', async () => {
    const payload = {
      name: d.querySelector('#new_name').value,
      email: d.querySelector('#new_email').value,
      provider_id: parseInt(d.querySelector('#new_provider').value || '1', 10),
      service_id: parseInt(d.querySelector('#new_service').value || '1', 10),
      date: start.substring(0,10),
      start: new Date(start).toTimeString().substring(0,5),
      notes: '',
    };
    try {
      await fetchJSON(`${window.__BASE_URL__ || ''}/api/book`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      closeModal();
      window.dispatchEvent(new CustomEvent('schedule:refresh'));
    } catch (e) { console.error(e); }
  });
  return d;
}

async function initScheduleCalendar() {
  const el = document.getElementById('calendar');
  if (!el) return;

  const base = window.__BASE_URL__ || '';

  const calendar = new Calendar(el, {
    plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
    initialView: 'timeGridWeek',
    headerToolbar: {
      left: 'prev,next today',
      center: 'title',
      right: 'dayGridMonth,timeGridWeek,timeGridDay',
    },
    slotMinTime: '06:00:00',
    slotMaxTime: '22:00:00',
    selectable: true,
    editable: true,
    droppable: false,
    nowIndicator: true,
    height: 'auto',
    eventClassNames,
    eventContent: renderEventContent,
    select: (info) => {
      openModal(buildCreateForm(info));
    },
    eventClick: (info) => {
      openModal(buildAppointmentView(info.event));
    },
    eventDrop: async (info) => {
      try {
        await fetchJSON(`${base}/appointments/update`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id: info.event.id, start_time: info.event.start.toISOString(), end_time: info.event.end?.toISOString() || null }),
        });
      } catch (e) {
        console.error(e);
        info.revert();
      }
    },
    events: async (fetchInfo, success, failure) => {
      try {
  const url = `${base}/api/appointments/get?start=${encodeURIComponent(fetchInfo.startStr)}&end=${encodeURIComponent(fetchInfo.endStr)}`;
        const data = await fetchJSON(url);
        const appts = Array.isArray(data) ? data : (data.appointments || []);
        const events = appts.map(buildEventFromAppointment);
        success(events);
      } catch (e) {
        console.error(e);
        failure(e);
      }
    },
  });

  calendar.render();

  try {
  const meta = await fetchJSON(`${base}/api/scheduler/meta`);
    applyBusinessHoursAndBlocks(calendar, meta);
  } catch (_) {}

  window.addEventListener('schedule:refresh', () => calendar.refetchEvents());
}

document.addEventListener('DOMContentLoaded', initScheduleCalendar);
document.addEventListener('spa:navigated', initScheduleCalendar);

// Expose for debugging
window.__schedule = { initScheduleCalendar };
