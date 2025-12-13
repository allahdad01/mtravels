/**
 * KeyboardNavigation - Full keyboard navigation support for chat
 */
class KeyboardNavigation {
    constructor(chatManager, ui) {
        this.chatManager = chatManager;
        this.ui = ui;
        this.focusedContactIndex = 0;
        this.focusedMessageIndex = 0;
        this.init();
    }

    init() {
        document.addEventListener('keydown', (e) => this.handleKeydown(e));
    }

    /**
     * Main keyboard event handler
     */
    handleKeydown(e) {
        const input = document.getElementById('messageInput');
        const isInputFocused = document.activeElement === input;

        // Check if input is focused
        if (isInputFocused) {
            // Shift+Enter: Add new line
            if (e.key === 'Enter' && e.shiftKey) {
                e.preventDefault();
                const cursorPos = input.selectionStart;
                const text = input.value;
                input.value = text.substring(0, cursorPos) + '\n' + text.substring(cursorPos);
                input.selectionStart = input.selectionEnd = cursorPos + 1;
                return;
            }

            // Tab: Insert tab
            if (e.key === 'Tab') {
                e.preventDefault();
                const cursorPos = input.selectionStart;
                const text = input.value;
                input.value = text.substring(0, cursorPos) + '\t' + text.substring(cursorPos);
                input.selectionStart = input.selectionEnd = cursorPos + 1;
                return;
            }

            // Escape: Clear input / cancel operations
            if (e.key === 'Escape') {
                e.preventDefault();
                input.value = '';
                this.clearEditMode();
                this.clearReplyPreview();
                return;
            }

            // Enter: Send message (if not shift)
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                window.dispatchEvent(new CustomEvent('sendMessage'));
                return;
            }
        } else {
            // Global shortcuts (when not typing)
            this.handleGlobalShortcuts(e);
        }
    }

    /**
     * Handle global keyboard shortcuts
     */
    handleGlobalShortcuts(e) {
        // Ctrl+/ or Cmd+/: Show help
        if ((e.ctrlKey || e.metaKey) && e.key === '/') {
            e.preventDefault();
            this.showKeyboardHelp();
            return;
        }

        // Alt+N: New message (focus input)
        if ((e.altKey || e.metaKey) && e.key === 'n') {
            e.preventDefault();
            this.focusMessageInput();
            return;
        }

        // Alt+Up/Down: Navigate contacts
        if ((e.altKey || e.metaKey) && (e.key === 'ArrowUp' || e.key === 'ArrowDown')) {
            e.preventDefault();
            this.navigateContacts(e.key === 'ArrowUp' ? -1 : 1);
            return;
        }

        // Alt+Left/Right: Switch between panels (mobile)
        if ((e.altKey || e.metaKey) && (e.key === 'ArrowLeft' || e.key === 'ArrowRight')) {
            e.preventDefault();
            this.switchPanels(e.key === 'ArrowLeft' ? 'sidebar' : 'chat');
            return;
        }

        // Tab: Navigate contacts
        const contactList = document.getElementById('contactList');
        if (document.activeElement === contactList || contactList?.contains(document.activeElement)) {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                this.focusNextContact();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                this.focusPrevContact();
            } else if (e.key === 'Enter') {
                e.preventDefault();
                const focused = document.querySelector('.contact-item:focus');
                if (focused) {
                    focused.click();
                }
            }
        }

        // In messages: Arrow navigation
        const messagesContainer = document.querySelector('.messages-container');
        if (messagesContainer?.contains(document.activeElement) || document.activeElement === messagesContainer) {
            if (e.key === 'ArrowUp') {
                e.preventDefault();
                this.focusPrevMessage();
            } else if (e.key === 'ArrowDown') {
                e.preventDefault();
                this.focusNextMessage();
            }
        }
    }

    /**
     * Focus message input
     */
    focusMessageInput() {
        const input = document.getElementById('messageInput');
        if (input) {
            input.focus();
            input.select();
        }
    }

    /**
     * Navigate through contacts
     */
    navigateContacts(direction) {
        const contacts = Array.from(document.querySelectorAll('.contact-item'));
        if (contacts.length === 0) return;

        const currentIndex = contacts.findIndex(c => c.classList.contains('active'));
        let nextIndex = currentIndex + direction;

        // Wrap around
        if (nextIndex < 0) nextIndex = contacts.length - 1;
        if (nextIndex >= contacts.length) nextIndex = 0;

        const nextContact = contacts[nextIndex];
        if (nextContact) {
            nextContact.click();
            nextContact.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

    /**
     * Focus next contact
     */
    focusNextContact() {
        const contacts = Array.from(document.querySelectorAll('.contact-item'));
        const currentIndex = contacts.findIndex(c => c.classList.contains('active'));
        const nextIndex = Math.min(currentIndex + 1, contacts.length - 1);

        if (contacts[nextIndex]) {
            contacts[nextIndex].focus();
            contacts[nextIndex].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

    /**
     * Focus previous contact
     */
    focusPrevContact() {
        const contacts = Array.from(document.querySelectorAll('.contact-item'));
        const currentIndex = contacts.findIndex(c => c.classList.contains('active'));
        const prevIndex = Math.max(currentIndex - 1, 0);

        if (contacts[prevIndex]) {
            contacts[prevIndex].focus();
            contacts[prevIndex].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

    /**
     * Focus next message
     */
    focusNextMessage() {
        const messages = Array.from(document.querySelectorAll('.message'));
        const currentIndex = messages.findIndex(m => m === document.activeElement);
        const nextIndex = Math.min(currentIndex + 1, messages.length - 1);

        if (messages[nextIndex]) {
            messages[nextIndex].focus();
            messages[nextIndex].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

    /**
     * Focus previous message
     */
    focusPrevMessage() {
        const messages = Array.from(document.querySelectorAll('.message'));
        const currentIndex = messages.findIndex(m => m === document.activeElement);
        const prevIndex = Math.max(currentIndex - 1, 0);

        if (messages[prevIndex]) {
            messages[prevIndex].focus();
            messages[prevIndex].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

    /**
     * Switch between sidebar and chat area
     */
    switchPanels(panel) {
        const sidebar = document.getElementById('sidebar');
        const chatArea = document.getElementById('chatArea');

        if (!sidebar || !chatArea) return;

        if (panel === 'sidebar') {
            sidebar.classList.add('show');
            chatArea.classList.remove('show');
        } else {
            sidebar.classList.remove('show');
            chatArea.classList.add('show');
        }
    }

    /**
     * Show keyboard shortcuts help
     */
    showKeyboardHelp() {
        const helpHTML = `
            <div class="modal fade show" style="display: block; background: rgba(0, 0, 0, 0.5);">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header border-bottom">
                            <h5 class="modal-title">
                                <i class="fas fa-keyboard"></i> Keyboard Shortcuts
                            </h5>
                            <button type="button" class="btn-close" data-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 style="color: #4099ff; margin-bottom: 1rem;">Editing</h6>
                                    <table style="font-size: 0.9rem; width: 100%;">
                                        <tr><td><kbd>Shift + Enter</kbd></td><td>New line</td></tr>
                                        <tr><td><kbd>Tab</kbd></td><td>Insert tab</td></tr>
                                        <tr><td><kbd>Enter</kbd></td><td>Send message</td></tr>
                                        <tr><td><kbd>Esc</kbd></td><td>Cancel/Clear</td></tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <h6 style="color: #4099ff; margin-bottom: 1rem;">Navigation</h6>
                                    <table style="font-size: 0.9rem; width: 100%;">
                                        <tr><td><kbd>Alt + N</kbd></td><td>Focus input</td></tr>
                                        <tr><td><kbd>Alt + ↑↓</kbd></td><td>Navigate contacts</td></tr>
                                        <tr><td><kbd>Alt + ←→</kbd></td><td>Switch panels</td></tr>
                                        <tr><td><kbd>↑↓</kbd></td><td>Navigate messages</td></tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer border-top">
                            <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        const modal = document.createElement('div');
        modal.innerHTML = helpHTML;
        const modalEl = modal.querySelector('.modal');
        document.body.appendChild(modalEl);

        // Close button
        const closeBtn = modalEl.querySelector('.btn-close');
        const dismissBtn = modalEl.querySelector('[data-dismiss="modal"]');

        const closeHandler = () => {
            modalEl.remove();
            document.removeEventListener('click', outsideClickHandler);
        };

        const outsideClickHandler = (e) => {
            if (e.target === modalEl) {
                closeHandler();
            }
        };

        closeBtn.addEventListener('click', closeHandler);
        dismissBtn.addEventListener('click', closeHandler);
        setTimeout(() => {
            document.addEventListener('click', outsideClickHandler);
        }, 100);
    }

    /**
     * Clear edit mode
     */
    clearEditMode() {
        const indicator = document.getElementById('editModeIndicator');
        if (indicator) {
            indicator.remove();
        }
        if (window.chatApp) {
            window.chatApp.messageActions && (window.chatApp.messageActions.editingMessageId = null);
        }
    }

    /**
     * Clear reply preview
     */
    clearReplyPreview() {
        const preview = document.querySelector('.reply-preview');
        if (preview) {
            preview.remove();
        }
        if (window.chatApp) {
            window.chatApp.replyToMessageId = null;
            window.chatApp.replyContext = null;
        }
    }
}

// Export
window.KeyboardNavigation = KeyboardNavigation;
