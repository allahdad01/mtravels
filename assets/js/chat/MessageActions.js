/**
 * MessageActions - Handle message context menu and actions (reply, edit, delete, etc.)
 */
class MessageActions {
    constructor(chatManager, uiUtilities) {
        this.chatManager = chatManager;
        this.ui = uiUtilities;
        this.editingMessageId = null;
        this.init();
    }

    init() {
        this.attachMessageListeners();
    }

    /**
     * Attach event listeners to messages
     */
    attachMessageListeners() {
        document.addEventListener('mouseenter', (e) => {
            if (e.target.closest('.message')) {
                this.showMessageActions(e.target.closest('.message'));
            }
        }, true);

        document.addEventListener('mouseleave', (e) => {
            if (e.target.closest('.message')) {
                this.hideMessageActions(e.target.closest('.message'));
            }
        }, true);
    }

    /**
     * Show action menu on message hover
     */
    showMessageActions(messageElement) {
        let menu = messageElement.querySelector('.message-dropdown');
        if (!menu) {
            menu = this.createActionMenu(messageElement);
            messageElement.appendChild(menu);
        }
    }

    /**
     * Hide action menu
     */
    hideMessageActions(messageElement) {
        const menu = messageElement.querySelector('.message-dropdown');
        if (menu) {
            menu.classList.remove('open');
        }
    }

    /**
     * Create context menu for message
     */
    createActionMenu(messageElement) {
        const messageId = messageElement.getAttribute('data-message-id');
        const isOwn = messageElement.classList.contains('outgoing');

        const menu = document.createElement('div');
        menu.className = 'message-dropdown';

        // Get message type
        const messageType = messageElement.getAttribute('data-message-type') || 'text';
        const actions = [];

        // Common actions
        actions.push({
            id: 'reply',
            label: 'Reply',
            icon: 'fa-reply',
            handler: () => this.replyToMessage(messageElement)
        });

        actions.push({
            id: 'forward',
            label: 'Forward',
            icon: 'fa-share',
            handler: () => this.forwardMessage(messageElement)
        });

        actions.push({
            id: 'copy',
            label: 'Copy',
            icon: 'fa-copy',
            handler: () => this.copyMessage(messageElement)
        });

        actions.push({
            id: 'react',
            label: 'React',
            icon: 'fa-heart',
            handler: () => this.showReactionPicker(messageElement)
        });

        // Own message actions
        if (isOwn) {
            actions.push({
                id: 'divider',
                isDivider: true
            });

            actions.push({
                id: 'edit',
                label: 'Edit',
                icon: 'fa-edit',
                handler: () => this.editMessage(messageElement)
            });

            actions.push({
                id: 'delete',
                label: 'Delete',
                icon: 'fa-trash',
                className: 'danger',
                handler: () => this.deleteMessage(messageElement)
            });
        }

        // Build menu HTML
        let html = '';
        actions.forEach(action => {
            if (action.isDivider) {
                html += '<div class="message-dropdown-divider"></div>';
            } else {
                html += `
                    <button class="message-dropdown-item ${action.className || ''}" data-action="${action.id}">
                        <i class="fas ${action.icon}"></i>
                        <span>${action.label}</span>
                    </button>
                `;
            }
        });

        menu.innerHTML = html;

        // Attach handlers
        menu.querySelectorAll('[data-action]').forEach(btn => {
            const action = actions.find(a => a.id === btn.getAttribute('data-action'));
            if (action && action.handler) {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    action.handler();
                    menu.classList.remove('open');
                });
            }
        });

        // Toggle menu visibility
        const menuBtn = messageElement.querySelector('.message-menu-btn');
        if (menuBtn) {
            menuBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                menu.classList.toggle('open');
            });
        }

        return menu;
    }

    /**
     * Reply to message
     */
    replyToMessage(messageElement) {
        const messageId = messageElement.getAttribute('data-message-id');
        const messageText = messageElement.querySelector('.message-bubble')?.textContent || '';
        const senderName = messageElement.getAttribute('data-sender-name') || 'User';

        // Store reply context
        window.chatApp.replyToMessageId = messageId;
        window.chatApp.replyContext = {
            messageId,
            sender: senderName,
            text: messageText.substring(0, 100)
        };

        // Show reply preview in input area
        this.showReplyPreview(messageId, senderName, messageText);
        this.ui.showInfo('Replying to: ' + senderName);
    }

    /**
     * Forward message
     */
    forwardMessage(messageElement) {
        const messageText = messageElement.querySelector('.message-bubble')?.textContent || '';
        this.ui.showInfo('Forward feature coming soon');
        // TODO: Implement forward functionality
    }

    /**
     * Copy message to clipboard
     */
    copyMessage(messageElement) {
        const messageText = messageElement.querySelector('.message-bubble')?.textContent || '';

        if (navigator.clipboard) {
            navigator.clipboard.writeText(messageText).then(() => {
                this.ui.showSuccess('Message copied to clipboard');
            }).catch(() => {
                this.ui.showError('Failed to copy message');
            });
        } else {
            // Fallback for older browsers
            const textArea = document.createElement('textarea');
            textArea.value = messageText;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            this.ui.showSuccess('Message copied');
        }
    }

    /**
     * Edit message
     */
    editMessage(messageElement) {
        const messageId = messageElement.getAttribute('data-message-id');
        const messageText = messageElement.querySelector('.message-bubble')?.textContent || '';

        this.editingMessageId = messageId;

        // Get input element
        const input = document.getElementById('messageInput');
        if (input) {
            input.value = messageText;
            input.focus();

            // Show edit mode indicator
            const editIndicator = document.createElement('div');
            editIndicator.id = 'editModeIndicator';
            editIndicator.style.cssText = `
                background: #fef3c7;
                border-left: 3px solid #f59e0b;
                padding: 8px 12px;
                margin-bottom: 10px;
                border-radius: 4px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 10px;
            `;
            editIndicator.innerHTML = `
                <span style="color: #92400e; font-size: 0.9rem;">
                    <i class="fas fa-edit"></i> Editing message
                </span>
                <button type="button" class="btn btn-sm btn-link p-0" id="cancelEdit">
                    <i class="fas fa-times"></i>
                </button>
            `;

            const inputArea = input.closest('.input-area');
            if (inputArea) {
                const existingIndicator = inputArea.querySelector('#editModeIndicator');
                if (existingIndicator) {
                    existingIndicator.remove();
                }
                inputArea.insertBefore(editIndicator, inputArea.firstChild);

                // Cancel edit
                editIndicator.querySelector('#cancelEdit').addEventListener('click', () => {
                    this.editingMessageId = null;
                    input.value = '';
                    editIndicator.remove();
                });
            }
        }

        this.ui.showInfo('Editing message...');
    }

    /**
     * Delete message
     */
    deleteMessage(messageElement) {
        const messageId = messageElement.getAttribute('data-message-id');

        this.ui.showConfirm(
            'Delete Message',
            'Are you sure you want to delete this message? This action cannot be undone.',
            () => {
                this.performDelete(messageId, messageElement);
            }
        );
    }

    /**
     * Perform actual deletion
     */
    performDelete(messageId, messageElement) {
        const spinnerId = this.ui.showLoadingSpinner(messageElement);

        fetch('api/chat_delete_message.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.csrfToken
            },
            body: JSON.stringify({
                message_id: messageId
            })
        })
        .then(response => response.json())
        .then(data => {
            this.ui.stopLoadingSpinner(spinnerId);
            if (data.success) {
                messageElement.remove();
                this.ui.showSuccess('Message deleted');
            } else {
                this.ui.showError(data.message || 'Failed to delete message');
            }
        })
        .catch(error => {
            this.ui.stopLoadingSpinner(spinnerId);
            this.ui.showError('Error deleting message');

        });
    }

    /**
     * Show reaction picker
     */
    showReactionPicker(messageElement) {
        const messageId = messageElement.getAttribute('data-message-id');
        let picker = messageElement.querySelector('.reaction-picker');

        if (!picker) {
            picker = document.createElement('div');
            picker.className = 'reaction-picker';

            const emojis = ['😀', '😂', '❤️', '👍', '😢', '😡', '🎉', '🔥', '✨', '👋', '🚀', '⚡'];

            emojis.forEach(emoji => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'reaction-btn';
                btn.textContent = emoji;
                btn.addEventListener('click', () => {
                    this.addReaction(messageId, emoji);
                    picker.remove();
                });
                picker.appendChild(btn);
            });

            messageElement.appendChild(picker);
        } else {
            picker.remove();
        }
    }

    /**
     * Add reaction to message
     */
    addReaction(messageId, emoji) {
        fetch('api/chat_add_reaction.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.csrfToken
            },
            body: JSON.stringify({
                message_id: messageId,
                reaction: emoji
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.ui.showSuccess('Reaction added');
            } else {
                this.ui.showError(data.message || 'Failed to add reaction');
            }
        })
        .catch(error => {

        });
    }

    /**
     * Show reply preview
     */
    showReplyPreview(messageId, senderName, messageText) {
        const inputArea = document.querySelector('.input-area');
        if (!inputArea) return;

        // Remove existing reply preview
        const existing = inputArea.querySelector('.reply-preview');
        if (existing) {
            existing.remove();
        }

        const preview = document.createElement('div');
        preview.className = 'reply-preview';
        preview.innerHTML = `
            <div class="reply-preview-content">
                <div>
                    <div class="reply-preview-header">
                        <i class="fas fa-reply" style="font-size: 0.8rem; color: #4099ff;"></i>
                        <span class="reply-preview-sender">${this.ui.escapeHtml(senderName)}</span>
                    </div>
                    <div class="reply-preview-text">${this.ui.escapeHtml(messageText.substring(0, 100))}</div>
                </div>
                <button class="reply-preview-close" type="button">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;

        preview.querySelector('.reply-preview-close').addEventListener('click', () => {
            window.chatApp.replyToMessageId = null;
            window.chatApp.replyContext = null;
            preview.remove();
        });

        inputArea.insertBefore(preview, inputArea.querySelector('.d-flex'));
    }
}

// Export for use
window.MessageActions = MessageActions;
