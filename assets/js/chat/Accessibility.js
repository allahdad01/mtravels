/**
 * Accessibility - WCAG AA compliance and accessibility improvements
 */
class Accessibility {
    constructor() {
        this.init();
    }

    init() {
        this.addAriaLabels();
        this.improveContrast();
        this.setupScreenReaderSupport();
        this.enhanceFocusIndicators();
    }

    /**
     * Add ARIA labels to interactive elements
     */
    addAriaLabels() {
        // Chat wrapper
        const chatWrapper = document.querySelector('.chat-wrapper');
        if (chatWrapper) {
            chatWrapper.setAttribute('role', 'main');
        }

        // Sidebar
        const sidebar = document.getElementById('sidebar');
        if (sidebar) {
            sidebar.setAttribute('role', 'region');
            sidebar.setAttribute('aria-label', 'Contacts list');
        }

        // Chat area
        const chatArea = document.getElementById('chatArea');
        if (chatArea) {
            chatArea.setAttribute('role', 'main');
            chatArea.setAttribute('aria-label', 'Chat messages');
        }

        // Contacts list
        const contactList = document.getElementById('contactList');
        if (contactList) {
            contactList.setAttribute('role', 'list');
        }

        // Messages container
        const messagesContainer = document.querySelector('.messages-container');
        if (messagesContainer) {
            messagesContainer.setAttribute('role', 'log');
            messagesContainer.setAttribute('aria-live', 'polite');
            messagesContainer.setAttribute('aria-label', 'Messages');
        }

        // Buttons
        document.querySelectorAll('button').forEach(btn => {
            if (!btn.getAttribute('aria-label') && !btn.getAttribute('title')) {
                const icon = btn.querySelector('i');
                const text = btn.textContent?.trim();

                if (icon) {
                    const iconClass = icon.className;
                    const label = this.getAriaLabelFromIcon(iconClass) || text || 'Button';
                    btn.setAttribute('aria-label', label);
                }
            }

            // Add role for buttons without explicit type
            if (!btn.getAttribute('role')) {
                btn.setAttribute('role', 'button');
            }
        });

        // Input fields
        document.querySelectorAll('input, textarea').forEach(input => {
            if (!input.getAttribute('aria-label') && input.getAttribute('placeholder')) {
                input.setAttribute('aria-label', input.getAttribute('placeholder'));
            }
        });

        // Links
        document.querySelectorAll('a').forEach(link => {
            if (!link.getAttribute('aria-label') && !link.textContent?.trim()) {
                link.setAttribute('aria-label', 'Link');
            }
        });
    }

    /**
     * Get ARIA label from icon class
     */
    getAriaLabelFromIcon(iconClass) {
        const iconMap = {
            'fa-search': 'Search',
            'fa-paperclip': 'Attach file',
            'fa-microphone': 'Voice message',
            'fa-smile': 'Emoji',
            'fa-paper-plane': 'Send message',
            'fa-arrow-left': 'Back',
            'fa-arrow-right': 'Next',
            'fa-plus': 'Add',
            'fa-times': 'Close',
            'fa-ellipsis-v': 'More options',
            'fa-reply': 'Reply',
            'fa-share': 'Forward',
            'fa-copy': 'Copy',
            'fa-heart': 'React',
            'fa-edit': 'Edit',
            'fa-trash': 'Delete',
            'fa-phone': 'Call',
            'fa-video': 'Video call',
            'fa-info-circle': 'Information',
            'fa-check': 'Confirm',
            'fa-download': 'Download'
        };

        for (const [key, label] of Object.entries(iconMap)) {
            if (iconClass.includes(key)) {
                return label;
            }
        }
        return null;
    }

    /**
     * Improve color contrast for accessibility
     */
    improveContrast() {
        const style = document.createElement('style');
        style.id = 'a11yContrastStyle';
        style.textContent = `
            /* Ensure minimum contrast ratios */
            .message-status {
                color: #374151 !important;
                font-weight: 600;
            }

            .contact-agency {
                color: #6b7280 !important;
            }

            .chat-header-status {
                color: #4099ff !important;
                font-weight: 500;
            }

            .message-bubble {
                color: #1f2937 !important;
            }

            .message.outgoing .message-bubble {
                color: white !important;
            }

            button:focus,
            input:focus,
            textarea:focus,
            a:focus {
                outline: 2px solid #4099ff !important;
                outline-offset: 2px !important;
            }

            /* Focus visible for keyboard navigation */
            *:focus-visible {
                outline: 2px solid #4099ff;
                outline-offset: 2px;
            }
        `;
        document.head.appendChild(style);
    }

    /**
     * Setup screen reader support
     */
    setupScreenReaderSupport() {
        // Add live region announcements
        const liveRegion = document.createElement('div');
        liveRegion.id = 'a11yLiveRegion';
        liveRegion.setAttribute('aria-live', 'polite');
        liveRegion.setAttribute('aria-atomic', 'true');
        liveRegion.style.cssText = `
            position: absolute;
            left: -10000px;
            width: 1px;
            height: 1px;
            overflow: hidden;
        `;
        document.body.appendChild(liveRegion);

        // Monitor for important events
        window.announceForScreenReader = (message) => {
            liveRegion.textContent = message;
        };

        // Announce new messages
        document.addEventListener('newMessage', (e) => {
            const detail = e.detail || {};
            window.announceForScreenReader(`New message from ${detail.senderName || 'user'}: ${detail.text?.substring(0, 100) || 'message received'}`);
        });

        // Announce connection status
        document.addEventListener('connectionStatus', (e) => {
            const status = e.detail?.status || 'unknown';
            window.announceForScreenReader(`Connection status: ${status}`);
        });

        // Announce typing indicator
        document.addEventListener('typingIndicator', (e) => {
            const detail = e.detail || {};
            window.announceForScreenReader(`${detail.senderName || 'user'} is typing...`);
        });
    }

    /**
     * Enhance focus indicators
     */
    enhanceFocusIndicators() {
        const style = document.createElement('style');
        style.id = 'a11yFocusStyle';
        style.textContent = `
            /* Visible focus indicators */
            :focus {
                outline: 2px solid #4099ff;
                outline-offset: 2px;
            }

            .contact-item:focus {
                background: #eff6ff;
                border-left-color: #4099ff;
            }

            .message:focus {
                outline: 2px solid #4099ff;
                outline-offset: -2px;
            }

            button:focus {
                box-shadow: 0 0 0 3px rgba(64, 153, 255, 0.25);
            }

            input:focus,
            textarea:focus {
                border-color: #4099ff !important;
                box-shadow: 0 0 0 3px rgba(64, 153, 255, 0.1) !important;
            }

            /* Remove default focus outline on Firefox */
            ::-moz-focus-inner {
                border: 0;
            }

            /* High contrast mode support */
            @media (prefers-contrast: more) {
                button, input, textarea, a {
                    border: 2px solid currentColor;
                }

                .message-bubble {
                    border: 1px solid #374151;
                }
            }

            /* Reduced motion support */
            @media (prefers-reduced-motion: reduce) {
                * {
                    animation-duration: 0.01ms !important;
                    animation-iteration-count: 1 !important;
                    transition-duration: 0.01ms !important;
                }
            }
        `;
        document.head.appendChild(style);
    }

    /**
     * Add keyboard-visible focus management
     */
    setupKeyboardFocusTracking() {
        let isKeyboardUser = false;

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Tab' || e.key === 'Enter') {
                isKeyboardUser = true;
                document.body.classList.add('keyboard-focus-visible');
            }
        });

        document.addEventListener('mousedown', (e) => {
            isKeyboardUser = false;
            document.body.classList.remove('keyboard-focus-visible');
        });

        const style = document.createElement('style');
        style.textContent = `
            body.keyboard-focus-visible *:focus {
                outline: 2px solid #4099ff !important;
            }
        `;
        document.head.appendChild(style);
    }

    /**
     * Make skip links for navigation
     */
    addSkipLinks() {
        const skipNav = document.createElement('div');
        skipNav.style.cssText = `
            position: absolute;
            top: 0;
            left: 0;
            z-index: 10000;
        `;
        skipNav.innerHTML = `
            <a href="#messagesContainer" style="
                position: absolute;
                left: -9999px;
                padding: 10px;
                background: #4099ff;
                color: white;
                text-decoration: none;
                border-radius: 4px;
            " class="skip-link">
                Skip to main content
            </a>
        `;
        document.body.insertBefore(skipNav, document.body.firstChild);

        // Show on focus
        skipNav.querySelector('.skip-link').addEventListener('focus', function() {
            this.style.left = '10px';
            this.style.top = '10px';
        });

        skipNav.querySelector('.skip-link').addEventListener('blur', function() {
            this.style.left = '-9999px';
        });
    }

    /**
     * Create accessible toast notification
     */
    createAccessibleToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.setAttribute('role', 'status');
        toast.setAttribute('aria-live', 'polite');
        toast.setAttribute('aria-atomic', 'true');
        toast.textContent = message;

        return toast;
    }

    /**
     * Test accessibility (development tool)
     */
    runAccessibilityAudit() {
        const issues = [];

        // Check for missing alt text on images
        document.querySelectorAll('img').forEach(img => {
            if (!img.getAttribute('alt')) {
                issues.push(`Image missing alt text: ${img.src}`);
            }
        });

        // Check for proper heading hierarchy
        let lastHeadingLevel = 0;
        document.querySelectorAll('h1, h2, h3, h4, h5, h6').forEach(heading => {
            const level = parseInt(heading.tagName[1]);
            if (level > lastHeadingLevel + 1) {
                issues.push(`Heading hierarchy broken: ${heading.tagName} after H${lastHeadingLevel}`);
            }
            lastHeadingLevel = level;
        });

        // Check for inputs without labels
        document.querySelectorAll('input').forEach(input => {
            if (input.type !== 'hidden' && !input.getAttribute('aria-label') && !input.closest('label')) {
                issues.push(`Input without label: ${input.name || input.id}`);
            }
        });

        // Check for buttons with no text or aria-label
        document.querySelectorAll('button').forEach(btn => {
            if (!btn.textContent?.trim() && !btn.getAttribute('aria-label')) {
                issues.push(`Button with no accessible text`);
            }
        });


        if (issues.length === 0) {

        } else {


        }

        return issues;
    }
}

// Export
window.Accessibility = Accessibility;
