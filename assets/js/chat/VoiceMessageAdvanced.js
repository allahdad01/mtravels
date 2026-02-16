/**
 * VoiceMessageAdvanced - Advanced features for voice messages
 * Includes: transcription, playback control, metadata, downloading, sharing
 */
class VoiceMessageAdvanced {
    constructor(chatAPI) {
        this.chatAPI = chatAPI;
        this.playingMessageId = null;
        this.audioElements = new Map();
        this.playbackSpeeds = new Map();
        this.favorites = new Set();
        this.setupEventListeners();
    }

    /**
     * Initialize advanced features
     */
    init() {
        this.loadFavorites();
        this.setupPlaybackControls();
        this.setupMessageActions();
    }

    /**
     * Setup message action handlers (delete, download, favorite, etc.)
     */
    setupMessageActions() {
        document.addEventListener('click', (e) => {
            if (e.target.closest('.voice-action-btn')) {
                const btn = e.target.closest('.voice-action-btn');
                const messageId = btn.dataset.messageId;
                const action = btn.dataset.action;
                
                this.handleVoiceAction(messageId, action, btn);
            }
        });
    }

    /**
     * Handle voice message actions
     */
    async handleVoiceAction(messageId, action, button) {


        switch (action) {
            case 'play':
            case 'pause':
                this.togglePlayback(messageId, button);
                break;
            case 'download':
                this.downloadVoiceMessage(messageId, button);
                break;
            case 'favorite':
                this.toggleFavorite(messageId, button);
                break;
            case 'transcribe':
                this.transcribeVoiceMessage(messageId, button);
                break;
            case 'speed':
                this.cyclePlaybackSpeed(messageId, button);
                break;
            case 'delete':
                this.deleteVoiceMessage(messageId, button);
                break;
            case 'forward':
                this.forwardVoiceMessage(messageId, button);
                break;
            case 'info':
                this.showVoiceMessageInfo(messageId, button);
                break;
        }
    }

    /**
     * Toggle playback (play/pause)
     */
    togglePlayback(messageId, button) {
        const icon = button.querySelector('i');
        let audio = this.audioElements.get(messageId);

        if (!audio) {
            const url = button.dataset.url;
            if (!url || url === '#') {
                alert('Voice message URL not available');
                return;
            }

            audio = new Audio(url);
            this.audioElements.set(messageId, audio);

            // Set playback speed if available
            const speed = this.playbackSpeeds.get(messageId) || 1;
            audio.playbackRate = speed;

            audio.addEventListener('ended', () => {
                icon.className = 'fas fa-play';
                button.classList.remove('playing');
            });

            audio.addEventListener('timeupdate', () => {
                this.updatePlaybackTime(messageId, audio);
            });

            audio.addEventListener('error', (e) => {

                alert('Failed to play voice message');
                icon.className = 'fas fa-play';
                button.classList.remove('playing');
            });
        }

        if (audio.paused) {
            const playPromise = audio.play();
            if (playPromise !== undefined) {
                playPromise.then(() => {
                    icon.className = 'fas fa-pause';
                    button.classList.add('playing');
                    this.playingMessageId = messageId;
                }).catch(error => {

                    alert('Failed to play voice message');
                });
            } else {
                icon.className = 'fas fa-pause';
                button.classList.add('playing');
                this.playingMessageId = messageId;
            }
        } else {
            audio.pause();
            icon.className = 'fas fa-play';
            button.classList.remove('playing');
            this.playingMessageId = null;
        }
    }

    /**
     * Update playback time display
     */
    updatePlaybackTime(messageId, audio) {
        const messageEl = document.querySelector(`[data-message-id="${messageId}"]`);
        if (!messageEl) return;

        const timeEl = messageEl.querySelector('.voice-current-time');
        if (timeEl) {
            const current = Math.floor(audio.currentTime);
            timeEl.textContent = VoiceRecorder.formatTime(current);
        }

        // Update progress bar
        const progressEl = messageEl.querySelector('.voice-progress');
        if (progressEl && audio.duration) {
            const percent = (audio.currentTime / audio.duration) * 100;
            progressEl.style.width = percent + '%';
        }
    }

    /**
     * Cycle through playback speeds
     */
    cyclePlaybackSpeed(messageId, button) {
        const speeds = [0.75, 1, 1.25, 1.5, 2];
        let currentSpeed = this.playbackSpeeds.get(messageId) || 1;
        let nextSpeedIndex = (speeds.indexOf(currentSpeed) + 1) % speeds.length;
        let nextSpeed = speeds[nextSpeedIndex];

        this.playbackSpeeds.set(messageId, nextSpeed);

        const audio = this.audioElements.get(messageId);
        if (audio) {
            audio.playbackRate = nextSpeed;
        }

        button.textContent = `${nextSpeed}x`;
        button.title = `Playback speed: ${nextSpeed}x`;
    }

    /**
     * Download voice message
     */
    downloadVoiceMessage(messageId, button) {
        const url = button.closest('.voice-player')?.querySelector('[data-url]')?.dataset.url;
        if (!url) {
            alert('Cannot download: URL not available');
            return;
        }

        const link = document.createElement('a');
        link.href = url;
        link.download = `voice_message_${messageId}.webm`;
        link.click();

        // Show feedback
        const originalIcon = button.innerHTML;
        button.innerHTML = '<i class="fas fa-check"></i>';
        setTimeout(() => {
            button.innerHTML = originalIcon;
        }, 2000);
    }

    /**
     * Toggle favorite status
     */
    toggleFavorite(messageId, button) {
        if (this.favorites.has(messageId)) {
            this.favorites.delete(messageId);
            button.classList.remove('favorited');
            button.innerHTML = '<i class="fas fa-star"></i>';
            button.title = 'Add to favorites';
        } else {
            this.favorites.add(messageId);
            button.classList.add('favorited');
            button.innerHTML = '<i class="fas fa-star" style="color: #ffc107;"></i>';
            button.title = 'Remove from favorites';
        }

        // Save to localStorage
        this.saveFavorites();
    }

    /**
     * Save favorites to localStorage
     */
    saveFavorites() {
        localStorage.setItem('voiceFavorites', JSON.stringify(Array.from(this.favorites)));
    }

    /**
     * Load favorites from localStorage
     */
    loadFavorites() {
        const saved = localStorage.getItem('voiceFavorites');
        if (saved) {
            try {
                this.favorites = new Set(JSON.parse(saved));
            } catch (e) {

            }
        }
    }

    /**
     * Transcribe voice message using Web Speech API
     */
    async transcribeVoiceMessage(messageId, button) {
        const messageEl = document.querySelector(`[data-message-id="${messageId}"]`);
        if (!messageEl) return;

        // Show loading state
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        button.title = 'Transcribing...';

        try {
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            if (!SpeechRecognition) {
                alert('Speech recognition not supported in your browser');
                return;
            }

            // Get audio URL and fetch the blob
            const url = button.closest('.voice-player')?.querySelector('[data-url]')?.dataset.url;
            if (!url) {
                alert('Cannot transcribe: URL not available');
                return;
            }

            // Note: Full transcription from audio URL requires advanced processing
            // For now, show a placeholder with instructions
            const transcriptEl = document.createElement('div');
            transcriptEl.className = 'voice-transcript';
            transcriptEl.innerHTML = `
                <div class="transcript-header">
                    <strong>Transcript:</strong>
                    <button class="transcript-close" onclick="this.parentElement.parentElement.remove()">&times;</button>
                </div>
                <div class="transcript-content">
                    <p><em>Transcription feature requires audio processing.</em></p>
                    <p>To enable automatic transcription:</p>
                    <ol>
                        <li>Connect to a speech-to-text API (Google Cloud Speech, Azure, etc.)</li>
                        <li>Send audio to the service and receive transcript</li>
                        <li>Display and store transcript with message</li>
                    </ol>
                </div>
            `;

            const voicePlayer = button.closest('.voice-player');
            voicePlayer.parentElement.appendChild(transcriptEl);

        } catch (error) {

            alert('Transcription failed: ' + error.message);
        } finally {
            button.disabled = false;
            button.innerHTML = '<i class="fas fa-closed-captioning"></i>';
            button.title = 'Transcribe message';
        }
    }

    /**
     * Delete voice message
     */
    async deleteVoiceMessage(messageId, button) {
        if (!confirm('Delete this voice message? This cannot be undone.')) {
            return;
        }

        try {
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            // Call API to delete message
            const response = await fetch(`api/messages.php`, {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'delete',
                    message_id: messageId
                })
            });

            if (response.ok) {
                const messageEl = document.querySelector(`[data-message-id="${messageId}"]`);
                if (messageEl) {
                    messageEl.style.opacity = '0.5';
                    const bubble = messageEl.querySelector('.message-bubble');
                    if (bubble) {
                        bubble.innerHTML = '<em>Message deleted</em>';
                    }
                }
            } else {
                alert('Failed to delete message');
                button.disabled = false;
                button.innerHTML = '<i class="fas fa-trash"></i>';
            }
        } catch (error) {

            alert('Error deleting message');
            button.disabled = false;
            button.innerHTML = '<i class="fas fa-trash"></i>';
        }
    }

    /**
     * Forward voice message to another contact
     */
    forwardVoiceMessage(messageId, button) {
        const url = button.closest('.voice-player')?.querySelector('[data-url]')?.dataset.url;
        if (!url) {
            alert('Cannot forward: URL not available');
            return;
        }

        // Show contact selection dialog
        const contactList = document.getElementById('contactList');
        if (!contactList) return;

        const contacts = Array.from(contactList.querySelectorAll('.contact-item')).map(el => ({
            id: el.dataset.contactId,
            name: el.querySelector('.contact-name')?.textContent || 'Unknown'
        }));

        if (contacts.length === 0) {
            alert('No contacts available to forward to');
            return;
        }

        // Create selection modal
        this.showForwardDialog(contacts, messageId, url);
    }

    /**
     * Show forward dialog
     */
    showForwardDialog(contacts, messageId, url) {
        const dialog = document.createElement('div');
        dialog.className = 'voice-forward-dialog';
        dialog.innerHTML = `
            <div class="forward-modal">
                <div class="forward-header">
                    <h6>Forward voice message to:</h6>
                    <button class="btn-close" onclick="this.closest('.voice-forward-dialog').remove()">&times;</button>
                </div>
                <div class="forward-contacts">
                    ${contacts.map(c => `
                        <button class="forward-contact" data-contact-id="${c.id}" onclick="window.chatApp.voiceAdvanced.sendForwardedMessage('${c.id}', '${url}'); this.closest('.voice-forward-dialog').remove();">
                            ${this.escape(c.name)}
                        </button>
                    `).join('')}
                </div>
            </div>
        `;

        document.body.appendChild(dialog);

        // Add click outside to close
        dialog.addEventListener('click', (e) => {
            if (e.target === dialog) {
                dialog.remove();
            }
        });
    }

    /**
     * Send forwarded message
     */
    async sendForwardedMessage(contactId, url) {
        try {
            // Fetch the audio file
            const response = await fetch(url);
            const blob = await response.blob();

            // Send as new voice message
            const result = await this.chatAPI.sendVoiceMessage(contactId, blob, 0);
            if (result.success) {
                alert('Voice message forwarded successfully');
            }
        } catch (error) {

            alert('Failed to forward message');
        }
    }

    /**
     * Show voice message info panel
     */
    showVoiceMessageInfo(messageId, button) {
        const messageEl = document.querySelector(`[data-message-id="${messageId}"]`);
        if (!messageEl) return;

        const existing = messageEl.querySelector('.voice-info-panel');
        if (existing) {
            existing.remove();
            return;
        }

        const duration = button.closest('.voice-player')?.querySelector('.voice-duration')?.textContent || 'Unknown';
        const audio = this.audioElements.get(messageId);
        const currentTime = audio ? VoiceRecorder.formatTime(Math.floor(audio.currentTime)) : '0:00';

        const infoPanel = document.createElement('div');
        infoPanel.className = 'voice-info-panel';
        infoPanel.innerHTML = `
            <div class="info-header">Message Info</div>
            <div class="info-row">
                <span class="info-label">Duration:</span>
                <span class="info-value">${duration}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Current Time:</span>
                <span class="info-value">${currentTime}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Format:</span>
                <span class="info-value">WebM Audio</span>
            </div>
            <div class="info-row">
                <span class="info-label">Message ID:</span>
                <span class="info-value">${messageId}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Saved:</span>
                <span class="info-value">${this.favorites.has(parseInt(messageId)) ? 'Yes' : 'No'}</span>
            </div>
        `;

        const voicePlayer = button.closest('.voice-player');
        voicePlayer.parentElement.appendChild(infoPanel);
    }

    /**
     * Setup playback controls UI
     */
    setupPlaybackControls() {
        // Controls are created dynamically with voice messages
    }

    /**
     * Escape HTML special characters
     */
    escape(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }

    /**
     * Setup event listeners
     */
    setupEventListeners() {
        // Will be populated as needed
    }
}

// Export for use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = VoiceMessageAdvanced;
}
