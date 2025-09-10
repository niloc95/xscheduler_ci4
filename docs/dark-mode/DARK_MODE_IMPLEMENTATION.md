# WebSchedulr Dark Mode Implementation Guide

## Overview

The WebSchedulr dark mode system provides a comprehensive theme switching solution that maintains brand consistency while offering both light and dark themes. The implementation uses CSS custom properties (variables) with Tailwind CSS classes for seamless theme transitions.

## Architecture

### 1. CSS Variables System

The dark mode system is built on CSS custom properties defined in `resources/scss/app.scss`:

#### Light Theme (Default)
```css
:root {
  /* Material Design 3.0 Variables */
  --md-sys-color-primary: #003049;      /* Ocean Blue */
  --md-sys-color-secondary: #F77F00;    /* Vibrant Orange */
  --md-sys-color-tertiary: #FCBF49;     /* Golden Yellow */
  --md-sys-color-error: #D62828;        /* Crimson Red */
  
  /* Custom WebSchedulr Variables */
  --xs-bg-primary: #ffffff;
  --xs-bg-secondary: #f8fafc;
  --xs-text-primary: #003049;
  --xs-text-secondary: #475569;
  --xs-accent: #F77F00;
  /* ... more variables */
}
```

#### Dark Theme
```css
html.dark {
  /* Adjusted colors for dark theme */
  --md-sys-color-primary: #7FC8E8;      /* Lighter Ocean Blue */
  --md-sys-color-secondary: #FFB366;    /* Lighter Orange */
  --xs-bg-primary: #1a202c;
  --xs-bg-secondary: #2d3748;
  --xs-text-primary: #f7fafc;
  /* ... dark theme variables */
}
```

### 2. JavaScript Management

The dark mode is controlled by `DarkModeManager` class in `resources/js/dark-mode.js`:

```javascript
class DarkModeManager {
  constructor() {
    this.theme = this.getStoredTheme() || this.getPreferredTheme();
    this.init();
  }
  
  toggle() {
    const newTheme = this.theme === 'dark' ? 'light' : 'dark';
    this.applyTheme(newTheme);
  }
  
  applyTheme(theme) {
    if (theme === 'dark') {
      document.documentElement.classList.add('dark');
    } else {
      document.documentElement.classList.remove('dark');
    }
    // Store preference and update UI
  }
}
```

### 3. Tailwind Configuration

Tailwind CSS is configured for class-based dark mode in `tailwind.config.js`:

```javascript
module.exports = {
  darkMode: 'class', // Enable class-based dark mode
  theme: {
    extend: {
      colors: {
        // CSS Variable-based colors
        'xs-bg': {
          'primary': 'var(--xs-bg-primary)',
          'secondary': 'var(--xs-bg-secondary)',
        },
        'xs-text': {
          'primary': 'var(--xs-text-primary)',
          'secondary': 'var(--xs-text-secondary)',
        },
      }
    }
  }
}
```

## Components

### 1. Dark Mode Toggle Component

**File**: `app/Views/components/dark-mode-toggle.php`

A reusable toggle button with:
- Sun/moon icons that swap based on current theme
- Accessible labels and ARIA attributes
- Smooth transitions and hover effects
- Compatible with any layout

**Usage**:
```php
<?= $this->include('components/dark-mode-toggle') ?>
```

### 2. Updated Layout Components

#### Header (`app/Views/components/header.php`)
- Dark mode responsive navigation
- Brand logo with CSS variable colors
- Theme toggle in navigation bar

#### Footer (`app/Views/components/footer.php`)
- Dark mode text and background colors
- Consistent transition animations

#### Main Layout (`app/Views/components/layout.php`)
- Dark mode initialization script (prevents flash)
- HTML class transitions
- Dark mode script inclusion

### 3. Authentication Pages

All authentication pages updated with dark mode support:

#### Login Page (`app/Views/auth/login.php`)
- Dark theme form elements
- CSS variable-based brand colors
- Dark mode toggle in top-right corner
- Enhanced form styling with dark variants

#### Features:
- Background and card colors adapt to theme
- Form inputs with dark mode styling
- Flash messages with dark variants
- Consistent brand color usage

## Implementation Features

### 1. Theme Persistence
- User preference stored in `localStorage`
- Survives browser sessions
- Automatic system preference detection

### 2. No Flash Prevention
Initialization script in document `<head>` prevents flash of unstyled content:

```javascript
(function() {
  const storedTheme = localStorage.getItem('xs-theme');
  const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
  const theme = storedTheme || (prefersDark ? 'dark' : 'light');
  
  if (theme === 'dark') {
    document.documentElement.classList.add('dark');
  }
})();
```

### 3. Smooth Transitions
All elements include transition classes:
```css
.transition-colors.duration-200
```

### 4. Accessibility
- ARIA labels for theme toggle
- High contrast ratios maintained
- Focus indicators work in both themes
- Screen reader friendly

### 5. Brand Consistency
- WebSchedulr brand colors adapt appropriately for each theme
- Material Design 3.0 color system integration
- Professional appearance in both themes

## Usage Examples

### 1. Basic Dark Mode Classes
```html
<!-- Background colors -->
<div class="bg-white dark:bg-gray-800">Content</div>

<!-- Text colors -->
<p class="text-gray-900 dark:text-white">Text</p>

<!-- Borders -->
<div class="border-gray-200 dark:border-gray-700">Bordered</div>
```

### 2. CSS Variable Usage
```html
<!-- Brand colors that adapt to theme -->
<button style="background-color: var(--md-sys-color-primary);">
  Primary Button
</button>

<!-- Custom WebSchedulr variables -->
<div style="background-color: var(--xs-bg-primary); color: var(--xs-text-primary);">
  Adaptive content
</div>
```

### 3. Form Elements
```html
<input class="bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 border-gray-300 dark:border-gray-600 focus:ring-blue-500 dark:focus:ring-blue-400">
```

## Build Process

The dark mode system is integrated into the Vite build:

1. **SCSS Compilation**: CSS variables processed with PostCSS
2. **JavaScript Bundling**: Dark mode script compiled as separate entry
3. **Tailwind Processing**: Dark mode classes included in final CSS

**Build Command**:
```bash
npm run build
```

**Output Files**:
- `public/build/assets/style.css` - Includes all dark mode styles
- `public/build/assets/dark-mode.js` - Theme management script

## Testing

### Test Page
Visit `/dark-mode-test` to see comprehensive examples:
- Theme switching controls
- Component showcases
- Typography scales
- Color palette display
- Real-time CSS variable values

### Manual Testing
1. Toggle between light and dark modes
2. Check localStorage persistence
3. Test system preference detection
4. Verify no flash on page load
5. Test all form elements and components

## Browser Support

- **Modern Browsers**: Full support (Chrome 49+, Firefox 31+, Safari 9.1+)
- **CSS Variables**: Widely supported
- **Dark Mode Media Query**: Modern browser support
- **LocalStorage**: Universal support

## Migration Guide

### Existing Components
To add dark mode to existing components:

1. **Add dark variants to Tailwind classes**:
   ```html
   <!-- Before -->
   <div class="bg-white text-gray-900">
   
   <!-- After -->
   <div class="bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
   ```

2. **Use CSS variables for brand colors**:
   ```html
   <!-- Before -->
   <div style="background-color: #F77F00;">
   
   <!-- After -->
   <div style="background-color: var(--md-sys-color-secondary);">
   ```

3. **Add transition classes**:
   ```html
   <div class="transition-colors duration-200">
   ```

### New Components
- Start with CSS variables for brand colors
- Include dark variants for all color-related classes
- Add smooth transitions
- Test in both themes

## Best Practices

### 1. Color Usage
- Use CSS variables for brand colors
- Stick to semantic color naming
- Maintain sufficient contrast ratios
- Test readability in both themes

### 2. Component Development
- Always include dark variants
- Use consistent transition durations (200ms)
- Test components in isolation
- Ensure accessibility compliance

### 3. Performance
- CSS variables are performance-friendly
- Transitions should be smooth but not excessive
- Lazy load theme toggle if not immediately needed

### 4. User Experience
- Respect user's system preference
- Persist theme choice
- Provide obvious toggle control
- Maintain brand consistency

## Troubleshooting

### Common Issues

1. **Flash of unstyled content**
   - Ensure dark mode script is in document head
   - Check localStorage access permissions

2. **Colors not updating**
   - Verify CSS variable syntax
   - Check if dark class is applied to html element
   - Ensure variables are defined in both themes

3. **Toggle not working**
   - Check if dark-mode.js is loaded
   - Verify button has `data-theme-toggle` attribute
   - Check browser console for errors

4. **Styles not building**
   - Run `npm run build` to compile changes
   - Check Vite configuration includes dark-mode.js
   - Verify Tailwind config has `darkMode: 'class'`

## Future Enhancements

1. **Multiple Theme Support**: Extend to support more than light/dark
2. **Theme Customization**: Allow users to customize brand colors
3. **Automatic Scheduling**: Switch themes based on time of day
4. **High Contrast Mode**: Additional accessibility theme
5. **Theme Presets**: Predefined theme combinations

---

## Quick Reference

### Key Files
- `resources/scss/app.scss` - CSS variables and themes
- `resources/js/dark-mode.js` - Theme management
- `app/Views/components/dark-mode-toggle.php` - Toggle component
- `tailwind.config.js` - Dark mode configuration

### Essential Classes
- `dark:` prefix for dark mode variants
- `transition-colors duration-200` for smooth transitions
- CSS variables: `var(--xs-bg-primary)`, `var(--md-sys-color-primary)`

### Testing URLs
- `/dark-mode-test` - Comprehensive test page
- `/auth/login` - Dark mode authentication example

The WebSchedulr dark mode system provides a professional, accessible, and maintainable solution for theme switching while preserving the distinctive brand identity across all themes.
