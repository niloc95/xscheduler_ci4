/**
 * Appointments Calendar Module
 * 
 * Renders an interactive appointment calendar using FullCalendar v6.
 * Supports day/week/month views, provider color coding, and event interaction.
 * 
 * Features:
 * - Provider-specific color coding (from xs_users.color)
 * - Multiple views (day/week/month)
 * - Event filtering by provider/service/status
 * - Click to view appointment details
 * - Drag-and-drop rescheduling (admin/provider only)
 * 
 * @module appointments-calendar
 * @version 1.0.0
 * @created October 22, 2025
 */

import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';

/**
 * Initialize the appointments calendar
 * @param {HTMLElement} containerEl - The calendar container element
 * @param {Object} options - Configuration options
 * @param {Object} options.settings - Business settings (work hours, time format, etc.)
 * @param {Object} options.filters - Initial filter values
 * @param {Function} options.onEventClick - Callback when event is clicked
 * @param {Function} options.onDateSelect - Callback when date range is selected
 * @param {Function} options.onEventDrop - Callback when event is dragged
 * @returns {Calendar} The initialized calendar instance
 */
export function initAppointmentsCalendar(containerEl, options = {}) {
  if (!containerEl) {
    console.error('[appointments-calendar] Container element not found');
    return null;
  }

  const {
    settings = {},
    filters = {},
    onEventClick = null,
    onDateSelect = null,
    onEventDrop = null,
    userRole = 'customer'
  } = options;

  // Parse business settings
  const workStart = settings['business.work_start'] || '08:00';
  const workEnd = settings['business.work_end'] || '18:00';
  const timeFormat = settings['localization.time_format'] || '24';
  const firstDayOfWeek = parseInt(settings['localization.first_day_of_week'] || '0');
  const timezone = settings['localization.timezone'] || 'local';

  // Check if user can edit appointments
  const canEdit = ['admin', 'provider'].includes(userRole);

  // Initialize calendar
  const calendar = new Calendar(containerEl, {
    plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
    
    // Initial view
    initialView: 'timeGridWeek',
    initialDate: containerEl.dataset.initialDate || new Date(),
    
    // Header toolbar (hidden - we use custom buttons)
    headerToolbar: false,
    
    // View configuration
    views: {
      timeGridDay: {
        titleFormat: { year: 'numeric', month: 'long', day: 'numeric' },
        slotDuration: '00:30:00',
        slotLabelInterval: '01:00:00',
        slotMinTime: workStart,
        slotMaxTime: workEnd,
        slotEventOverlap: false, // Side-by-side for same time slots
      },
      timeGridWeek: {
        titleFormat: { year: 'numeric', month: 'short', day: 'numeric' },
        slotDuration: '00:30:00',
        slotLabelInterval: '01:00:00',
        slotMinTime: workStart,
        slotMaxTime: workEnd,
        slotEventOverlap: false,
        dayHeaderFormat: { weekday: 'short', day: 'numeric' },
      },
      dayGridMonth: {
        titleFormat: { year: 'numeric', month: 'long' },
        dayMaxEvents: 3, // Show "+N more" link
      }
    },
    
    // Localization
    firstDay: firstDayOfWeek,
    locale: settings['localization.language'] || 'en',
    timeZone: timezone,
    
    // Time formatting
    eventTimeFormat: {
      hour: '2-digit',
      minute: '2-digit',
      meridiem: timeFormat === '12' ? 'short' : false,
      hour12: timeFormat === '12'
    },
    slotLabelFormat: {
      hour: '2-digit',
      minute: '2-digit',
      meridiem: timeFormat === '12' ? 'short' : false,
      hour12: timeFormat === '12'
    },
    
    // Interaction
    editable: canEdit, // Enable drag-and-drop for admin/provider
    selectable: canEdit, // Enable date selection for creating appointments
    selectMirror: true,
    dayMaxEvents: true,
    
    // Event styling
    eventMinHeight: 60,
    eventDisplay: 'block',
    
    // Event sources - fetch from API
    eventSources: [
      {
        url: '/api/appointments',
        method: 'GET',
        extraParams() {
          // Include current filters
          const params = {};
          
          if (filters.providerId) {
            params.providerId = filters.providerId;
          }
          if (filters.serviceId) {
            params.serviceId = filters.serviceId;
          }
          if (filters.status) {
            params.status = filters.status;
          }
          
          return params;
        },
        success(response) {
          // Extract events from response
          return response.data || response;
        },
        failure(error) {
          console.error('[appointments-calendar] Failed to load appointments:', error);
          return [];
        }
      }
    ],
    
    // Event loading callbacks
    loading(isLoading) {
      if (isLoading) {
        console.log('[appointments-calendar] Loading appointments...');
      } else {
        console.log('[appointments-calendar] Appointments loaded');
      }
    },
    
    // Event rendering - apply provider colors
    eventDidMount(info) {
      const { event } = info;
      const providerColor = event.extendedProps.provider_color;
      
      // Apply provider color if available
      if (providerColor) {
        info.el.style.backgroundColor = providerColor;
        info.el.style.borderColor = providerColor;
      }
      
      // Add status class for additional styling
      const status = event.extendedProps.status || 'booked';
      info.el.classList.add(`appointment-status-${status}`);
      
      // Add tooltip with appointment details
      const tooltip = [
        event.title,
        event.extendedProps.serviceName,
        event.extendedProps.providerName
      ].filter(Boolean).join(' • ');
      
      info.el.title = tooltip;
    },
    
    // Event content - custom rendering for better UX
    eventContent(arg) {
      const { event, timeText } = arg;
      const { status, serviceName, providerName } = event.extendedProps;
      
      // Create custom HTML structure
      const wrapper = document.createElement('div');
      wrapper.className = 'fc-event-main-frame';
      
      // Time
      const timeEl = document.createElement('div');
      timeEl.className = 'fc-event-time text-xs font-semibold opacity-90';
      timeEl.textContent = timeText;
      wrapper.appendChild(timeEl);
      
      // Title (customer name)
      const titleEl = document.createElement('div');
      titleEl.className = 'fc-event-title font-medium truncate';
      titleEl.textContent = event.title;
      wrapper.appendChild(titleEl);
      
      // Service name (if available)
      if (serviceName && arg.view.type !== 'dayGridMonth') {
        const serviceEl = document.createElement('div');
        serviceEl.className = 'fc-event-service text-xs opacity-75 truncate';
        serviceEl.textContent = serviceName;
        wrapper.appendChild(serviceEl);
      }
      
      return { domNodes: [wrapper] };
    },
    
    // Event click - show details modal
    eventClick(info) {
      info.jsEvent.preventDefault();
      
      if (onEventClick) {
        onEventClick(info.event, info);
      } else {
        console.log('[appointments-calendar] Event clicked:', info.event.id);
      }
    },
    
    // Date selection - create new appointment
    select(info) {
      if (onDateSelect) {
        onDateSelect(info);
      } else {
        console.log('[appointments-calendar] Date range selected:', info);
      }
      
      // Clear selection
      calendar.unselect();
    },
    
    // Event drag/drop - reschedule appointment
    eventDrop(info) {
      if (onEventDrop) {
        onEventDrop(info);
      } else {
        console.log('[appointments-calendar] Event dropped:', info.event.id, info.event.start);
      }
    },
    
    // Date change callback - update title
    datesSet(info) {
      // Update custom calendar title if element exists
      const titleEl = document.getElementById('appointments-inline-calendar-title');
      if (titleEl) {
        titleEl.textContent = info.view.title;
      }
      
      // Update active view button
      updateActiveViewButton(info.view.type);
    }
  });

  // Render the calendar
  calendar.render();
  
  console.log('[appointments-calendar] Calendar initialized successfully');
  
  return calendar;
}

/**
 * Update active view button styling
 * @param {string} viewType - Current view type (timeGridDay, timeGridWeek, dayGridMonth)
 */
function updateActiveViewButton(viewType) {
  // Map view types to button actions
  const viewMap = {
    'timeGridDay': 'day',
    'timeGridWeek': 'week',
    'dayGridMonth': 'month'
  };
  
  const action = viewMap[viewType];
  if (!action) return;
  
  // Remove active class from all buttons
  document.querySelectorAll('[data-calendar-action]').forEach(btn => {
    btn.classList.remove('active', 'bg-blue-600', 'text-white');
    btn.classList.add('bg-white', 'text-gray-600');
  });
  
  // Add active class to current view button
  const activeBtn = document.querySelector(`[data-calendar-action="${action}"]`);
  if (activeBtn) {
    activeBtn.classList.remove('bg-white', 'text-gray-600');
    activeBtn.classList.add('active', 'bg-blue-600', 'text-white');
  }
}

/**
 * Setup view switching buttons
 * @param {Calendar} calendar - Calendar instance
 */
export function setupViewButtons(calendar) {
  if (!calendar) return;
  
  // Previous button
  const prevBtn = document.querySelector('[data-calendar-action="prev"]');
  if (prevBtn) {
    prevBtn.addEventListener('click', () => {
      calendar.prev();
    });
  }
  
  // Next button
  const nextBtn = document.querySelector('[data-calendar-action="next"]');
  if (nextBtn) {
    nextBtn.addEventListener('click', () => {
      calendar.next();
    });
  }
  
  // Today button
  const todayBtn = document.querySelector('[data-calendar-action="today"]');
  if (todayBtn) {
    todayBtn.addEventListener('click', () => {
      calendar.today();
    });
  }
  
  // View switching buttons
  const viewButtons = document.querySelectorAll('[data-calendar-action="day"], [data-calendar-action="week"], [data-calendar-action="month"]');
  viewButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      const action = btn.dataset.calendarAction;
      const viewMap = {
        'day': 'timeGridDay',
        'week': 'timeGridWeek',
        'month': 'dayGridMonth'
      };
      
      const viewType = viewMap[action];
      if (viewType) {
        calendar.changeView(viewType);
      }
    });
  });
  
  console.log('[appointments-calendar] View buttons initialized');
}

/**
 * Apply filters to calendar
 * @param {Calendar} calendar - Calendar instance
 * @param {Object} filters - Filter object
 */
export function applyFilters(calendar, filters) {
  if (!calendar) return;
  
  // Store filters (will be used in next refetch)
  calendar.filters = filters;
  
  // Refetch events with new filters
  calendar.refetchEvents();
  
  console.log('[appointments-calendar] Filters applied:', filters);
}

/**
 * Refresh calendar events
 * @param {Calendar} calendar - Calendar instance
 */
export function refreshCalendar(calendar) {
  if (!calendar) return;
  
  calendar.refetchEvents();
  console.log('[appointments-calendar] Calendar refreshed');
}

/**
 * Go to specific date
 * @param {Calendar} calendar - Calendar instance
 * @param {Date|string} date - Target date
 */
export function goToDate(calendar, date) {
  if (!calendar) return;
  
  calendar.gotoDate(date);
  console.log('[appointments-calendar] Navigated to:', date);
}

/**
 * Destroy calendar instance
 * @param {Calendar} calendar - Calendar instance
 */
export function destroyCalendar(calendar) {
  if (!calendar) return;
  
  calendar.destroy();
  console.log('[appointments-calendar] Calendar destroyed');
}
