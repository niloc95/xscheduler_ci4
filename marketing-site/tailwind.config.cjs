/**
 * Tailwind config for the standalone marketing site.
 * Brand palette + font stack are copied from the app's tailwind.config.js so the
 * marketing site and the product read as one visual system:
 *   ocean  #003049 (primary)   orange #F77F00 (accent/CTA)
 *   golden #FCBF49 (highlight)  crimson #D62828 (danger)  cream #EAE2B7 (warm bg)
 */
/** @type {import('tailwindcss').Config} */
module.exports = {
  darkMode: 'class',
  content: ['./marketing-site/**/*.html'],
  theme: {
    extend: {
      colors: {
        brand: {
          ocean: '#003049',
          crimson: '#D62828',
          orange: '#F77F00',
          golden: '#FCBF49',
          cream: '#EAE2B7',
        },
        primary: {
          50: '#f0f9ff',
          100: '#e0f2fe',
          200: '#bae6fd',
          300: '#7dd3fc',
          400: '#38bdf8',
          500: '#003049',
          600: '#002a3d',
          700: '#001f2e',
          800: '#001419',
          900: '#000a0f',
        },
        accent: {
          50: '#fff7ed',
          100: '#ffedd5',
          200: '#fed7aa',
          300: '#fdba74',
          400: '#fb923c',
          500: '#F77F00',
          600: '#ea580c',
          700: '#c2410c',
        },
      },
      fontFamily: {
        sans: ['Inter', 'system-ui', '-apple-system', 'sans-serif'],
      },
      boxShadow: {
        brand: '0 4px 14px 0 rgba(0, 48, 73, 0.15)',
        'brand-lg': '0 10px 25px -3px rgba(0, 48, 73, 0.25)',
        'brand-xl': '0 24px 60px -12px rgba(0, 48, 73, 0.35)',
      },
      borderRadius: {
        xl: '1rem',
        '2xl': '1.5rem',
      },
      maxWidth: {
        '7xl': '80rem',
      },
    },
  },
  plugins: [require('@tailwindcss/forms')],
};
