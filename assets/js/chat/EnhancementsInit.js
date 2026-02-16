/**
 * EnhancementsInit - Initialize all UI enhancements
 */
class EnhancementsInit {
    constructor() {
        this.ui = null;
        this.messageActions = null;
        this.keyboardNav = null;
        this.accessibility = null;
        this.emojiPicker = null;
        this.fileUpload = null;
    }

    /**
     * Initialize all enhancements after DOM is ready
     */
    async init(chatManager) {
        try {


            // Initialize utilities
            this.ui = new UIUtilities();


            // Initialize features
            this.messageActions = new MessageActions(chatManager, this.ui);


            this.keyboardNav = new KeyboardNavigation(chatManager, this.ui);


            this.accessibility = new Accessibility();


            this.emojiPicker = new EmojiPickerEnhanced();


            this.fileUpload = new FileUploadProgress(this.ui);


            // Setup emoji button
            this.setupEmojiButton();

            // Setup file upload
            this.setupFileUpload();

            // Setup message actions on send
            this.setupMessageSending();

            // Add accessibility support
            this.setupAccessibilityFeatures();


            this.ui.showSuccess('Chat enhancements loaded');

            // Make utilities globally accessible
            window.chatApp.ui = this.ui;
            window.chatApp.messageActions = this.messageActions;
            window.chatApp.keyboardNav = this.keyboardNav;
            window.chatApp.enhancements = this;

        } catch (error) {

            this.ui?.showError('Failed to initialize chat features');
        }
    }

    /**
     * Setup emoji picker button
     */
    setupEmojiButton() {
        const emojiBtn = document.getElementById('emojiBtn');
        if (emojiBtn) {
            emojiBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();

                const existing = document.querySelector('.emoji-picker-enhanced');
                if (existing) {
                    existing.remove();
                    return;
                }

                const picker = this.emojiPicker.render(emojiBtn, (emoji) => {
                    const input = document.getElementById('messageInput');
                    if (input) {
                        const cursorPos = input.selectionStart;
                        const text = input.value;
                        input.value = text.substring(0, cursorPos) + emoji + text.substring(cursorPos);
                        input.selectionStart = input.selectionEnd = cursorPos + emoji.length;
                        input.focus();
                    }
                });
            });
        }
    }

    /**
     * Setup file upload with progress
     */
    setupFileUpload() {
        const fileInput = document.getElementById('fileUploadBtn');
        const fileLabel = document.querySelector('.file-upload-label');

        if (fileInput && fileLabel) {
            fileInput.addEventListener('change', (e) => {
                this.fileUpload.handleFileSelect(
                    e.target.files,
                    null,
                    (response) => {
                        if (response.success) {
                            // Emit event for chat manager to handle
                            window.dispatchEvent(new CustomEvent('fileUploaded', {
                                detail: response
                            }));
                        }
                    }
                );
            });

            // Drag and drop
            const messagesContainer = document.querySelector('.messages-container');
            if (messagesContainer) {
                this.fileUpload.setupDragDrop(messagesContainer, (files) => {
                    fileInput.files = files;
                    const event = new Event('change', { bubbles: true });
                    fileInput.dispatchEvent(event);
                });
            }
        }
    }

    /**
     * Setup enhanced message sending with loading indicator
     */
    setupMessageSending() {
        document.addEventListener('sendMessage', (e) => {
            const input = document.getElementById('messageInput');
            const sendBtn = document.getElementById('sendBtn');
            const message = input?.value?.trim();

            if (!message) {
                this.ui.showWarning('Message cannot be empty');
                return;
            }

            // Show loading state on send button
            if (sendBtn) {
                sendBtn.disabled = true;
                const originalIcon = sendBtn.innerHTML;
                sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

                // Restore after delay
                setTimeout(() => {
                    if (sendBtn) {
                        sendBtn.disabled = false;
                        sendBtn.innerHTML = originalIcon;
                    }
                }, 500);
            }

            // Clear reply preview if sending
            const replyPreview = document.querySelector('.reply-preview');
            if (replyPreview && input?.value) {
                // Reply will be sent with message
            }
        });
    }

    /**
     * Setup accessibility features
     */
    setupAccessibilityFeatures() {
        // Add skip links
        this.accessibility.addSkipLinks();

        // Setup keyboard focus tracking
        this.accessibility.setupKeyboardFocusTracking();

        // Run accessibility audit in console
        if (window.location.hash === '#audit') {
            setTimeout(() => {
                this.accessibility.runAccessibilityAudit();
            }, 2000);
        }

        // Listen for important events and announce them
        document.addEventListener('userOnline', (e) => {
            const detail = e.detail || {};
            window.announceForScreenReader?.(`${detail.userName || 'User'} is now online`);
        });

        document.addEventListener('userOffline', (e) => {
            const detail = e.detail || {};
            window.announceForScreenReader?.(`${detail.userName || 'User'} is now offline`);
        });
    }

    /**
     * Selected emoji callback
     */
    selectedEmoji(emoji) {
        const input = document.getElementById('messageInput');
        if (input) {
            const cursorPos = input.selectionStart;
            const text = input.value;
            input.value = text.substring(0, cursorPos) + emoji + text.substring(cursorPos);
            input.focus();
        }
    }

    /**
     * Get current state for debugging
     */
    getState() {
        return {
            ui: !!this.ui,
            messageActions: !!this.messageActions,
            keyboardNav: !!this.keyboardNav,
            accessibility: !!this.accessibility,
            emojiPicker: !!this.emojiPicker,
            fileUpload: !!this.fileUpload,
            timestamp: new Date().toISOString()
        };
    }

    /**
     * Show enhancement status
     */
    showStatus() {
        const state = this.getState();
        const status = Object.entries(state)
            .map(([key, value]) => `${key}: ${value ? '✓' : '✗'}`)
            .join('\n');


        this.ui?.showInfo('Chat enhancements status logged to console');
    }
}

// Initialize enhancements when document is ready
document.addEventListener('DOMContentLoaded', () => {
    window.enhancementsInit = new EnhancementsInit();
});

// Wait for chatApp to be initialized, then init enhancements
window.addEventListener('chatAppReady', (e) => {
    if (window.enhancementsInit && window.chatApp) {
        window.enhancementsInit.init(window.chatApp);
    }
});

// Export
window.EnhancementsInit = EnhancementsInit;
