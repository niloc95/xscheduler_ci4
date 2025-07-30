/** @type {import('tailwindcss').Config} */
module.exports = {
  darkMode: 'class', // Enable class-based dark mode
  content: [
    "./app/Views/**/*.php",
    "./resources/**/*.{js,ts,jsx,tsx,vue}",
    "./public/**/*.html",
  ],
  theme: {
    extend: {
      colors: {
        // Your NEW brand palette
        brand: {
          'ocean': '#003049',      // Primary - Deep, trustworthy blue
          'crimson': '#D62828',    // Error/Urgent - Clear danger indication
          'orange': '#F77F00',     // Warning/CTA - Energetic action color
          'golden': '#FCBF49',     // Success/Highlight - Positive outcomes
          'cream': '#EAE2B7',      // Background/Neutral - Warm, welcoming
        },
        
        // Semantic color system for better UX
        primary: {
          50: '#f0f9ff',
          100: '#e0f2fe', 
          200: '#bae6fd',
          300: '#7dd3fc',
          400: '#38bdf8',
          500: '#003049',          // Your ocean blue as primary
          600: '#002a3d',
          700: '#001f2e',
          800: '#001419',
          900: '#000a0f',
        },
        
        secondary: {
          50: '#fff7ed',
          100: '#ffedd5',
          200: '#fed7aa',
          300: '#fdba74',
          400: '#fb923c',
          500: '#F77F00',          // Your orange as secondary
          600: '#ea580c',
          700: '#c2410c',
          800: '#9a3412',
          900: '#7c2d12',
        },
        
        success: {
          50: '#fefce8',
          100: '#fef9c3',
          200: '#fef08a',
          300: '#fde047',
          400: '#facc15',
          500: '#FCBF49',          // Your golden as success
          600: '#ca8a04',
          700: '#a16207',
          800: '#854d0e',
          900: '#713f12',
        },
        
        error: {
          50: '#fef2f2',
          100: '#fee2e2',
          200: '#fecaca',
          300: '#fca5a5',
          400: '#f87171',
          500: '#D62828',          // Your crimson as error
          600: '#b91c1c',
          700: '#991b1b',
          800: '#7f1d1d',
          900: '#651212',
        },
        
        warning: {
          50: '#fff7ed',
          100: '#ffedd5',
          200: '#fed7aa',
          300: '#fdba74',
          400: '#fb923c',
          500: '#F77F00',          // Your orange as warning
          600: '#ea580c',
          700: '#c2410c',
          800: '#9a3412',
          900: '#7c2d12',
        },
        
        neutral: {
          50: '#EAE2B7',           // Your cream as lightest neutral
          100: '#f5f5f4',
          200: '#e7e5e4',
          300: '#d6d3d1',
          400: '#a8a29e',
          500: '#78716c',
          600: '#57534e',
          700: '#44403c',
          800: '#292524',
          900: '#003049',          // Your ocean for dark text
        },
        
        // Scheduling-specific colors
        appointment: {
          'confirmed': '#FCBF49',   // Golden - positive confirmation
          'pending': '#F77F00',     // Orange - needs attention
          'cancelled': '#D62828',   // Crimson - negative state
          'completed': '#003049',   // Ocean - finished/archived
          'rescheduled': '#8B5CF6', // Purple - modified
        },
        
        // Priority levels
        priority: {
          'low': '#FCBF49',        // Golden - low urgency
          'medium': '#F77F00',     // Orange - moderate urgency  
          'high': '#D62828',       // Crimson - high urgency
          'critical': '#7C2D12',   // Dark red - critical
        },

        // CSS Variable-based colors for dark mode
        'xs-bg': {
          'primary': 'var(--xs-bg-primary)',
          'secondary': 'var(--xs-bg-secondary)',
          'tertiary': 'var(--xs-bg-tertiary)',
        },
        'xs-text': {
          'primary': 'var(--xs-text-primary)',
          'secondary': 'var(--xs-text-secondary)',
          'muted': 'var(--xs-text-muted)',
        },
        'xs-border': 'var(--xs-border)',
        'xs-accent': 'var(--xs-accent)',
        'xs-success': 'var(--xs-success)',
        'xs-error': 'var(--xs-error)',
        'xs-warning': 'var(--xs-warning)',
      },
      
      fontFamily: {
        sans: ['Inter', 'system-ui', 'sans-serif'],
      },
      
      spacing: {
        '18': '4.5rem',
        '88': '22rem',
      },
      
      borderRadius: {
        'xl': '1rem',
        '2xl': '1.5rem',
      },
      
      boxShadow: {
        'brand': '0 4px 14px 0 rgba(0, 48, 73, 0.15)',
        'brand-lg': '0 10px 25px -3px rgba(0, 48, 73, 0.25)',
      }
    },
  },
  plugins: [
    require('@tailwindcss/forms'),
    require('@tailwindcss/typography'),
  ],
}
