// Absolute minimal FullCalendar test
console.log('🔄 Calendar test script starting...');

// State
let calendar = null;
let initialized = false;

// Prevent multiple initializations
function init() {
  if (initialized) {
    console.log('⚠️ Calendar already initialized, skipping...');
    return;
  }
  
  console.log('🔍 Looking for calendar container...');
  const container = document.getElementById('calendarRoot');
  
  if (!container) {
    console.log('❌ Calendar container not found');
    return;
  }
  
  console.log('✅ Calendar container found');
  initialized = true;
  
// Absolute minimal FullCalendar test
console.log('🔄 Calendar test script starting...');

// State
let calendar = null;
let initialized = false;

// Prevent multiple initializations
function init() {
  if (initialized) {
    console.log('⚠️ Calendar already initialized, skipping...');
    return;
  }

  console.log('🔍 Looking for calendar container...');
  const container = document.getElementById('calendarRoot');

  if (!container) {
    console.log('❌ Calendar container not found');
    return;
  }

  console.log('✅ Calendar container found');
  initialized = true;

  try {
    // Clear container
    container.innerHTML = '';

    // Import FullCalendar dynamically to avoid any load issues
    import('@fullcalendar/core').then(({ Calendar }) => {
      import('@fullcalendar/daygrid').then((dayGridPlugin) => {
        console.log('📅 Creating FullCalendar...');

        calendar = new Calendar(container, {
          plugins: [dayGridPlugin.default],
          initialView: 'dayGridMonth',
          headerToolbar: false,
          height: 'auto', // Changed from 400 to auto for better sizing
          contentHeight: 600, // Set content height
          aspectRatio: 1.35, // Good aspect ratio for month view
          events: [
            { title: 'Test Event 1', start: '2025-09-08', backgroundColor: '#3b82f6' },
            { title: 'Test Event 2', start: '2025-09-10', backgroundColor: '#10b981' }
          ],
          eventDisplay: 'block', // Make events visible
          dayMaxEvents: 3, // Show up to 3 events per day
          moreLinkClick: 'popover' // Handle overflow events
        });

        console.log('🎯 Rendering calendar...');
        calendar.render();
        console.log('✅ Calendar rendered successfully!');

        // Add some debugging info
        setTimeout(() => {
          console.log('📊 Calendar debug info:');
          console.log('- View type:', calendar.view.type);
          console.log('- Container:', container);
          console.log('- Container children:', container.children.length);
          console.log('- Container HTML:', container.innerHTML.substring(0, 200) + '...');
        }, 1000);

      }).catch(err => {
        console.error('❌ Failed to load dayGrid plugin:', err);
      });
    }).catch(err => {
      console.error('❌ Failed to load FullCalendar core:', err);
    });

  } catch (error) {
    console.error('❌ Calendar initialization error:', error);
  }
}
}

// Single initialization
console.log('📋 Document ready state:', document.readyState);

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init, { once: true });
} else {
  setTimeout(init, 50);
}

// Export for debugging
window.debugCalendar = { calendar, init, initialized };
