/**
 * ChatAPI - Centralized API calls for chat operations
 * Handles all HTTP requests to the backend
 */
class ChatAPI {
    constructor() {
        this.baseUrl = '';
        this.timeout = 10000; // 10 seconds
    }

    /**
     * Make an API request with error handling
     */
    async request(endpoint, options = {}) {
        const {
            method = 'GET',
            data = null,
            headers = {},
            timeout = this.timeout
        } = options;

        const url = this.baseUrl + endpoint;
        const config = {
            method,
            credentials: 'include', // Include cookies for session
            headers: {
                'Content-Type': 'application/json',
                ...headers
            }
        };

        if (data && (method === 'POST' || method === 'PUT')) {
            config.body = typeof data === 'string' ? data : JSON.stringify(data);
        }

        try {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), timeout);

            const response = await fetch(url, { ...config, signal: controller.signal });
            clearTimeout(timeoutId);

            if (!response.ok) {
                const error = new Error(`HTTP ${response.status}`);
                error.status = response.status;
                throw error;
            }

            return await response.json();
        } catch (error) {
            if (error.name === 'AbortError') {
                throw new Error('Request timeout');
            }
            throw error;
        }
    }

    /**
     * Fetch chat settings
     */
    async getSettings() {
        try {
            return await this.request('api/chat_settings.php');
        } catch (error) {

            throw error;
        }
    }

    /**
     * Fetch all contacts
     */
    async getContacts() {
        try {
            return await this.request('api/contacts.php');
        } catch (error) {

            throw error;
        }
    }

    /**
     * Fetch messages for a contact
     */
    async getMessages(contactId, options = {}) {
        const { limit = 50, beforeId = null } = options;
        
        let endpoint = `api/messages.php?peer_id=${encodeURIComponent(contactId)}&limit=${limit}`;
        if (beforeId) {
            endpoint += `&before_id=${encodeURIComponent(beforeId)}`;
        }

        try {
            return await this.request(endpoint);
        } catch (error) {

            throw error;
        }
    }

    /**
     * Send a text message
     */
    async sendMessage(contactId, content) {
        if (!contactId || !content) {
            throw new Error('Contact ID and content are required');
        }

        try {
            const formData = new URLSearchParams({
                to_user_id: contactId,
                content: content
            });

            const response = await fetch('api/messages.php', {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString()
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            return await response.json();
        } catch (error) {

            throw error;
        }
    }

    /**
     * Edit a message
     */
    async editMessage(messageId, content) {
        if (!messageId || !content) {
            throw new Error('Message ID and content are required');
        }

        try {
            const formData = new URLSearchParams({
                action: 'edit',
                message_id: messageId,
                content: content
            });

            const response = await fetch('api/messages.php', {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString()
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            return await response.json();
        } catch (error) {

            throw error;
        }
    }

    /**
     * Delete a message
     */
    async deleteMessage(messageId) {
        if (!messageId) {
            throw new Error('Message ID is required');
        }

        try {
            const formData = new URLSearchParams({
                action: 'delete',
                message_id: messageId
            });

            const response = await fetch('api/messages.php', {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString()
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            return await response.json();
        } catch (error) {

            throw error;
        }
    }

    /**
     * Mark messages as read
     */
    async markAsRead(contactId) {
        if (!contactId) return;

        try {
            const formData = new URLSearchParams({
                action: 'mark_seen',
                peer_id: contactId
            });

            return await fetch('api/messages.php', {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString()
            }).then(r => r.json());
        } catch (error) {

        }
    }

    /**
     * Upload a file
     */
    async uploadFile(file, contactId = null) {
        if (!file) {
            throw new Error('File is required');
        }

        try {
            const formData = new FormData();
            formData.append('file', file);
            if (contactId) {
                formData.append('to_user_id', contactId);
            }
            // Add CSRF token
            formData.append('csrf_token', window.csrfToken || '');

            const response = await fetch('api/upload.php', {
                method: 'POST',
                credentials: 'include',
                body: formData
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            return await response.json();
        } catch (error) {

            throw error;
        }
    }

    /**
     * Send a voice message
     */
    async sendVoiceMessage(contactId, audioBlob, duration = 0) {
        if (!contactId || !audioBlob) {
            throw new Error('Contact ID and audio blob are required');
        }

        try {
            const formData = new FormData();
            formData.append('to_user_id', contactId);
            formData.append('audio', audioBlob, `voice-${Date.now()}.webm`);
            formData.append('duration', duration);

            const response = await fetch('api/voice_messages.php', {
                method: 'POST',
                credentials: 'include',
                body: formData
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            return await response.json();
        } catch (error) {

            throw error;
        }
    }

    /**
     * Get user preferences (block/mute lists)
     */
    async getPreferences() {
        try {
            const response = await fetch('api/chat_prefs.php?action=list', {
                credentials: 'include'
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            return await response.json();
        } catch (error) {

            throw error;
        }
    }

    /**
     * Block a user
     */
    async blockUser(userId) {
        if (!userId) throw new Error('User ID is required');

        try {
            const formData = new URLSearchParams({
                action: 'block',
                target_id: userId
            });

            const response = await fetch('api/chat_prefs.php', {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString()
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            return await response.json();
        } catch (error) {

            throw error;
        }
    }

    /**
     * Unblock a user
     */
    async unblockUser(userId) {
        if (!userId) throw new Error('User ID is required');

        try {
            const formData = new URLSearchParams({
                action: 'unblock',
                target_id: userId
            });

            const response = await fetch('api/chat_prefs.php', {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString()
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            return await response.json();
        } catch (error) {

            throw error;
        }
    }

    /**
     * Mute a user
     */
    async muteUser(userId) {
        if (!userId) throw new Error('User ID is required');

        try {
            const formData = new URLSearchParams({
                action: 'mute',
                target_id: userId
            });

            const response = await fetch('api/chat_prefs.php', {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString()
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            return await response.json();
        } catch (error) {

            throw error;
        }
    }

    /**
     * Unmute a user
     */
    async unmuteUser(userId) {
        if (!userId) throw new Error('User ID is required');

        try {
            const formData = new URLSearchParams({
                action: 'unmute',
                target_id: userId
            });

            const response = await fetch('api/chat_prefs.php', {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString()
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            return await response.json();
        } catch (error) {

            throw error;
        }
    }

    /**
     * Send typing indicator
     */
    async sendTyping(contactId, isTyping = true) {
        if (!contactId) return;

        try {
            const formData = new URLSearchParams({
                peer_id: contactId,
                typing: isTyping ? '1' : '0'
            });

            return await fetch('api/messages.php', {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString()
            }).then(r => r.json()).catch(() => null);
        } catch (error) {

        }
    }

    /**
     * Load message reactions
     */
    async getReactions(messageId) {
        if (!messageId) return {};

        try {
            const response = await fetch(`api/chat/reactions.php?message_id=${encodeURIComponent(messageId)}`, {
                credentials: 'include'
            });

            if (!response.ok) return {};

            return await response.json();
        } catch (error) {

            return {};
        }
    }

    /**
     * Add a reaction to a message
     */
    async addReaction(messageId, emoji) {
        if (!messageId || !emoji) return;

        try {
            const formData = new URLSearchParams({
                action: 'add',
                message_id: messageId,
                emoji: emoji
            });

            return await fetch('api/chat/reactions.php', {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString()
            }).then(r => r.json()).catch(() => null);
        } catch (error) {

        }
    }
}

// Export for use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ChatAPI;
}
