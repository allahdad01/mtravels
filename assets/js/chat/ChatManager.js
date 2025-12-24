/**
 * ChatManager - Core state management for the chat application
 * Handles contacts, messages, preferences, and application state
 */
class ChatManager {
    constructor() {
        this.currentContactId = null;
        this.currentRoomId = null;
        this.contacts = [];
        this.messages = new Map(); // contactId -> array of messages
        this.preferences = {
            blocked: new Set(),
            muted: new Set()
        };
        this.settings = {};
        this.unread = new Map(); // roomId -> unread count
        this.isOnline = false;
        this.typingUsers = new Set();
    }

    /**
     * Initialize the chat manager
     */
    async init() {
        try {
            await Promise.all([
                this.loadSettings(),
                this.loadContacts(),
                this.loadPreferences()
            ]);
            
            // Load user status separately to ensure contacts are loaded first
            await this.loadUserStatus();
            
            // Refresh online status every 30 seconds
            this.statusRefreshInterval = setInterval(() => this.loadUserStatus(), 30000);
            
            return true;
        } catch (error) {
            console.error('[ChatManager] Initialization failed:', error);
            return false;
        }
    }

    /**
     * Load chat settings from server
     */
    async loadSettings() {
        try {
            const response = await fetch('api/chat_settings.php', { 
                credentials: 'include' 
            });
            if (!response.ok) throw new Error('Failed to load settings');
            
            this.settings = await response.json();
            return this.settings;
        } catch (error) {
            console.error('[ChatManager] Failed to load settings:', error);
            this.settings = this.getDefaultSettings();
            return this.settings;
        }
    }

    /**
     * Load contacts from server
     */
    async loadContacts() {
        try {
            const response = await fetch('api/contacts.php', { 
                credentials: 'include' 
            });
            if (!response.ok) throw new Error('Failed to load contacts');
            
            const data = await response.json();
            this.contacts = data.contacts || [];
            return this.contacts;
        } catch (error) {
            console.error('[ChatManager] Failed to load contacts:', error);
            return [];
        }
    }

    /**
     * Load user preferences (blocked/muted)
     */
    async loadPreferences() {
        try {
            const response = await fetch('api/chat_prefs.php?action=list', { 
                credentials: 'include' 
            });
            if (!response.ok) throw new Error('Failed to load preferences');
            
            const data = await response.json();
            this.preferences.blocked = new Set(data.blocked || []);
            this.preferences.muted = new Set(data.muted || []);
            return this.preferences;
        } catch (error) {
            console.error('[ChatManager] Failed to load preferences:', error);
            return this.preferences;
        }
    }

    /**
     * Load online status for all users (real-time)
     */
    async loadUserStatus() {
        try {
            // Ping the server to mark current user as online
            const response = await fetch('api/online_sessions.php?action=ping', { 
                method: 'GET',
                credentials: 'include' 
            });
            if (!response.ok) throw new Error('Failed to load user status');
            
            const data = await response.json();
            this.onlineUsers = new Set(data.online || []);
            this.typingUsers = new Set(data.typing || []);
            
            // Update contact online status
            this.contacts.forEach(contact => {
                contact.online = this.onlineUsers.has(contact.id);
                contact.typing = this.typingUsers.has(contact.id);
            });
            
            console.log('[ChatManager] Online users:', Array.from(this.onlineUsers));
            
            // Dispatch event so UI can update
            window.dispatchEvent(new CustomEvent('userStatusUpdated', { 
                detail: { online: this.onlineUsers, typing: this.typingUsers } 
            }));
            
            return { online: this.onlineUsers, typing: this.typingUsers };
        } catch (error) {
            console.warn('[ChatManager] Failed to load user status:', error);
            return { online: new Set(), typing: new Set() };
        }
    }

    /**
     * Load message history for a contact
     */
    async loadMessages(contactId, limit = 50) {
        try {
            const response = await fetch(
                `api/messages.php?peer_id=${encodeURIComponent(contactId)}&limit=${limit}`,
                { credentials: 'include' }
            );
            if (!response.ok) throw new Error('Failed to load messages');
            
            const data = await response.json();
            this.messages.set(contactId, data.messages || []);
            return this.getMessages(contactId);
        } catch (error) {
            console.error('[ChatManager] Failed to load messages:', error);
            return [];
        }
    }

    /**
     * Send a message
     */
    async sendMessage(contactId, content) {
        try {
            const response = await fetch('api/messages.php', {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    to_user_id: contactId,
                    content: content
                })
            });
            
            if (!response.ok) throw new Error('Failed to send message');
            
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('[ChatManager] Failed to send message:', error);
            throw error;
        }
    }

    /**
     * Select a contact as current
     */
    selectContact(contactId) {
        const contact = this.contacts.find(c => c.id === contactId);
        if (!contact) return null;
        
        this.currentContactId = contactId;
        this.currentRoomId = contact.room_id;
        
        // Clear unread for this contact
        this.unread.set(contact.room_id, 0);
        
        return contact;
    }

    /**
     * Get messages for a contact
     */
    getMessages(contactId) {
        return this.messages.get(contactId) || [];
    }

    /**
     * Get current contact
     */
    getCurrentContact() {
        return this.contacts.find(c => c.id === this.currentContactId);
    }

    /**
     * Check if a user is blocked
     */
    isBlocked(userId) {
        return this.preferences.blocked.has(userId);
    }

    /**
     * Check if a user is muted
     */
    isMuted(userId) {
        return this.preferences.muted.has(userId);
    }

    /**
     * Block a user
     */
    async blockUser(userId) {
        try {
            const response = await fetch('api/chat_prefs.php', {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'block',
                    target_id: userId
                })
            });
            
            if (response.ok) {
                this.preferences.blocked.add(userId);
                return true;
            }
        } catch (error) {
            console.error('[ChatManager] Failed to block user:', error);
        }
        return false;
    }

    /**
     * Unblock a user
     */
    async unblockUser(userId) {
        try {
            const response = await fetch('api/chat_prefs.php', {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'unblock',
                    target_id: userId
                })
            });
            
            if (response.ok) {
                this.preferences.blocked.delete(userId);
                return true;
            }
        } catch (error) {
            console.error('[ChatManager] Failed to unblock user:', error);
        }
        return false;
    }

    /**
     * Mute a user
     */
    async muteUser(userId) {
        try {
            const response = await fetch('api/chat_prefs.php', {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'mute',
                    target_id: userId
                })
            });
            
            if (response.ok) {
                this.preferences.muted.add(userId);
                return true;
            }
        } catch (error) {
            console.error('[ChatManager] Failed to mute user:', error);
        }
        return false;
    }

    /**
     * Unmute a user
     */
    async unmuteUser(userId) {
        try {
            const response = await fetch('api/chat_prefs.php', {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'unmute',
                    target_id: userId
                })
            });
            
            if (response.ok) {
                this.preferences.muted.delete(userId);
                return true;
            }
        } catch (error) {
            console.error('[ChatManager] Failed to unmute user:', error);
        }
        return false;
    }

    /**
     * Update typing status
     */
    async setTyping(contactId, isTyping) {
        try {
            await fetch('api/chat.php?action=typing', {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    peer_id: contactId,
                    typing: isTyping ? '1' : '0'
                })
            });
        } catch (error) {
            console.warn('[ChatManager] Failed to update typing status:', error);
        }
    }

    /**
     * Mark messages as read
     */
    async markAsRead(contactId) {
        try {
            await fetch('api/messages.php', {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'mark_seen',
                    peer_id: contactId
                })
            });
        } catch (error) {
            console.warn('[ChatManager] Failed to mark messages as read:', error);
        }
    }

    /**
     * Get default settings
     */
    getDefaultSettings() {
        return {
            max_file_bytes: 26214400,
            allowed_mime_prefixes: ['image/', 'video/', 'audio/', 'application/pdf', 'text/'],
            default_auto_download: false
        };
    }
}

// Export for use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ChatManager;
}
