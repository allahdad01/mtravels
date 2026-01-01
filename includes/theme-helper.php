<?php
/**
 * Theme Management Helper
 * Provides dark mode CSS variables and JavaScript functionality
 * Include in <head> section of your page
 */

function renderThemeStyles() {
    ?>
    <style>
        :root {
            /* Light mode colors */
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --bg-surface: #ffffff;
        }

        html.dark-mode {
            /* Dark mode colors */
            --bg-primary: #0f172a;
            --bg-secondary: #1a2332;
            --text-primary: #f8fafc;
            --text-secondary: #cbd5e1;
            --bg-surface: #1e293b;
        }

        body {
            transition: background 0.3s ease, color 0.3s ease;
        }

        /* Theme Toggle Button */
        .theme-toggle {
            background: transparent;
            border: 2px solid var(--primary);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 1.2rem;
            color: var(--primary);
        }

        .theme-toggle:hover {
            background: rgba(64, 153, 255, 0.1);
            transform: scale(1.1);
        }

        .theme-toggle:active {
            transform: scale(0.95);
        }

        /* Dark Mode Navbar */
        html.dark-mode .navbar {
            background: rgba(30, 41, 59, 0.85);
            border-color: rgba(64, 153, 255, 0.1);
        }

        html.dark-mode .nav-links a {
            color: #cbd5e1;
        }

        html.dark-mode .nav-links a:hover {
            color: #4099ff;
        }

        html.dark-mode .hamburger {
            color: #cbd5e1;
        }

        html.dark-mode .hamburger:hover {
            background: rgba(64, 153, 255, 0.2);
        }

        html.dark-mode .theme-toggle {
            border-color: #4099ff;
            color: #4099ff;
        }

        html.dark-mode .theme-toggle:hover {
            background: rgba(64, 153, 255, 0.2);
        }
    </style>
    <?php
}

function renderThemeScript() {
    ?>
    <script>
        // Theme Toggle Functionality
        function initThemeToggle() {
            const themeToggle = document.getElementById('themeToggle');
            const htmlElement = document.documentElement;
            const storedTheme = localStorage.getItem('theme');
            
            // Set initial theme based on stored preference or system preference
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const initialDarkMode = storedTheme === 'dark' || (storedTheme === null && prefersDark);
            
            if (initialDarkMode) {
                htmlElement.classList.add('dark-mode');
                updateThemeIcon(true);
            } else {
                htmlElement.classList.remove('dark-mode');
                updateThemeIcon(false);
            }
            
            // Toggle theme on button click
            if (themeToggle) {
                themeToggle.addEventListener('click', function() {
                    const isDark = htmlElement.classList.contains('dark-mode');
                    
                    if (isDark) {
                        htmlElement.classList.remove('dark-mode');
                        localStorage.setItem('theme', 'light');
                        updateThemeIcon(false);
                    } else {
                        htmlElement.classList.add('dark-mode');
                        localStorage.setItem('theme', 'dark');
                        updateThemeIcon(true);
                    }
                });
            }
            
            function updateThemeIcon(isDark) {
                const icon = document.querySelector('.theme-icon');
                if (icon) {
                    icon.textContent = isDark ? '☀️' : '🌙';
                }
            }
        }

        // Initialize theme toggle when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            initThemeToggle();
        });
    </script>
    <?php
}
?>
