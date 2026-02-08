// Global Dark Mode Manager
window.DarkModeManager = {
    setTheme(theme) {
        const htmlElement = document.documentElement;

        // Always remove dark class first
        htmlElement.classList.remove('dark');

        if (theme === 'system') {
            // Use system preference
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (prefersDark) {
                htmlElement.classList.add('dark');
            }
        } else if (theme === 'dark') {
            htmlElement.classList.add('dark');
        } else if (theme === 'light') {
            // Light theme - ensure dark class is removed
            htmlElement.classList.remove('dark');
        }

        localStorage.setItem('theme', theme);
        this.updateToggleStates();

        // Emit custom event for other components
        setTimeout(() => {
            document.dispatchEvent(new Event('themechange'));
        }, 0);
    },

    initializeTheme() {
        const savedTheme = localStorage.getItem('theme') || 'system';
        this.setTheme(savedTheme);
    },

    updateToggleStates() {
        const darkModeToggles = document.querySelectorAll('.dark-mode-toggle');
        const currentTheme = localStorage.getItem('theme') || 'system';

        darkModeToggles.forEach(toggle => {
            if (toggle.value === currentTheme) {
                toggle.checked = true;
            } else {
                toggle.checked = false;
            }
        });
    },

    setupListeners() {
        const darkModeToggles = document.querySelectorAll('.dark-mode-toggle');

        darkModeToggles.forEach(toggle => {
            toggle.addEventListener('change', (e) => {
                this.setTheme(e.target.value);
            });
        });
    }
};

// Listen for system theme changes
window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
    const currentTheme = localStorage.getItem('theme') || 'system';
    if (currentTheme === 'system') {
        const htmlElement = document.documentElement;
        if (e.matches) {
            htmlElement.classList.add('dark');
        } else {
            htmlElement.classList.remove('dark');
        }
        DarkModeManager.updateToggleStates();
    }
});

// Initialize on page load
function initDarkMode() {
    DarkModeManager.initializeTheme();
    DarkModeManager.setupListeners();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initDarkMode);
} else {
    initDarkMode();
}
