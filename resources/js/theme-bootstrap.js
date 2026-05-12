(() => {
    const theme = localStorage.getItem('xs-theme')
        || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    document.documentElement.setAttribute('data-theme', theme);
    // .dark class required for Tailwind dark: utilities (darkMode: 'class')
    document.documentElement.classList.toggle('dark', theme === 'dark');
})();
